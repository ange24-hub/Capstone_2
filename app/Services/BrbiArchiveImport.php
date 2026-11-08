<?php

namespace App\Services;

use App\Models\{Barangay, BarangayRbiUpdate, DeceasedInhabitant, Household, Inhabitant, MigrationRecord, NewInhabitant, RegistryActivity, User};
use App\Support\{BrbiArchiveWorkbook as Workbook, HouseholdRbi, RbiFirstSave, ResidenceWorkbookReader};
use Illuminate\Support\Facades\DB;

/** Append missing source records; preserve real registries, submitted forms and classifications. */
class BrbiArchiveImport
{
    public function plan(string $directory): array
    {
        $inventory = json_decode(file_get_contents($directory.'/archive-inventory.json'), true, flags: JSON_THROW_ON_ERROR);
        $areas = Barangay::all()->keyBy(fn ($a) => strtoupper($a->name));
        $plan = ['areas'=>[], 'source_hashes'=>[]];
        foreach ($inventory as $file) {
            $area = $areas->get(strtoupper(pathinfo($file['file'], PATHINFO_FILENAME)));
            if (! $area) throw new \RuntimeException('Unmapped workbook '.$file['file']);
            $path = $directory.'/'.basename($file['file']);
            $plan['source_hashes'][$path] = hash_file('sha256', $path);
            $real = Inhabitant::where('barangay_id',$area->id)->where(fn ($q)=>$q->whereNull('remarks')->orWhere('remarks','!=',MigrationForecastHistory::DEMO_MARKER))->exists();
            $registry = ! $real && isset(Workbook::REGISTRY_SHEETS[$area->name]);
            $names = array_values(array_filter($file['sheets'],fn ($s)=>str_contains(strtoupper($s),'NEW') || strtoupper($s)==='NIF'
                || ($registry && (in_array($s,[Workbook::REGISTRY_SHEETS[$area->name],'TRANSFERED'],true) || strtoupper($s)==='DECEASED'))));
            $sheets = $names ? ResidenceWorkbookReader::read($path,$names) : [];
            $entry = ['barangay_id'=>$area->id,'name'=>$area->name,'source_file'=>$file['file'],'registry'=>$registry ? Workbook::registry($area->name,$sheets) : null,
                'new_rows'=>[], 'existing_history_skipped'=>0];
            $existing = NewInhabitant::where('barangay_id',$area->id)->get();
            $keys = $existing->keyBy(fn ($r)=>Workbook::key($r->getAttributes()));
            foreach ($sheets as $sheet=>$rows) {
                if (! str_contains(strtoupper($sheet),'NEW') && strtoupper($sheet)!=='NIF') continue;
                foreach (Workbook::newEntries($area->name,$sheet,$rows) as $row) {
                    $positions=[$row['source_position']];
                    // The unchanged legacy Canlupao import stored its first data row as position 1.
                    if ($area->name==='Canlupao' && $file['same_as_previous']) $positions[]=$row['source_position']-1;
                    $sameSource = $existing->first(fn ($r)=>in_array((int)$r->source_position,$positions,true)
                        && mb_strtolower(trim($r->last_name))===mb_strtolower(trim($row['last_name']))
                        && mb_strtolower(trim($r->first_name))===mb_strtolower(trim($row['first_name'])));
                    if ($sameSource || $keys->has(Workbook::key($row))) { $entry['existing_history_skipped']++; continue; }
                    $entry['new_rows'][]=$row;
                    $keys->put(Workbook::key($row),true);
                }
            }
            $plan['areas'][]=$entry;
        }
        return $plan;
    }

    public function summary(array $plan): array
    {
        return array_map(fn ($a)=>['barangay'=>$a['name'],'registry_residents'=>$a['registry'] ? count($a['registry']['residents']) : 0,
            'registry_deceased'=>$a['registry'] ? count($a['registry']['deceased']) : 0,
            'registry_source_review'=>$a['registry'] ? count($a['registry']['pending']) : 0,
            'new_history_to_add'=>count($a['new_rows']),'existing_history_skipped'=>$a['existing_history_skipped'],
            'new_rows_without_reporting_month'=>count(array_filter($a['new_rows'],fn ($r)=>!$r['reporting_month']))],$plan['areas']);
    }

    private function checkEmptyArea(Barangay $area): void
    {
        if (Household::where('barangay_id',$area->id)->where('household_number','!=','DEMO-MIGRATION-V1')->exists()
            || Inhabitant::where('barangay_id',$area->id)->where(fn ($q)=>$q->whereNull('remarks')->orWhere('remarks','!=',MigrationForecastHistory::DEMO_MARKER)->orWhereNotNull('resident_user_id'))->exists()
            || DeceasedInhabitant::where('barangay_id',$area->id)->exists()) throw new \RuntimeException($area->name.' is no longer an empty real registry.');
        $ids=Inhabitant::where('barangay_id',$area->id)->pluck('id');
        foreach (['document_requests','barangay_rbi_members','barangay_rbi_deceased_records','registry_activities'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table,'inhabitant_id') && DB::table($table)->whereIn('inhabitant_id',$ids)->exists()) throw new \RuntimeException('Demo records have linked '.$table.'; refusing removal.');
        }
        if (NewInhabitant::whereIn('active_inhabitant_id',$ids)->exists()
            || MigrationRecord::whereIn('inhabitant_id',$ids)->where('barangay_id','!=',$area->id)->exists()
            || MigrationRecord::whereIn('inhabitant_id',$ids)->where(fn ($q)=>$q->where('reason','!=',MigrationForecastHistory::DEMO_MARKER)->orWhereNull('reason'))->exists()) throw new \RuntimeException('Demo records have real references.');
        $homes=Household::where('barangay_id',$area->id)->pluck('id');
        foreach (['barangay_rbi_families','household_offline_syncs'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table,'household_id') && DB::table($table)->whereIn('household_id',$homes)->exists()) throw new \RuntimeException('Demo households have linked '.$table.'.');
        }
    }

    public function apply(array $plan): array
    {
        return DB::transaction(function () use ($plan) {
            Barangay::orderBy('id')->lockForUpdate()->get();
            foreach ($plan['source_hashes'] as $path=>$hash) if (hash_file('sha256',$path)!==$hash) throw new \RuntimeException('Source changed after planning.');
            $result=[];
            foreach ($plan['areas'] as $item) {
                $area=Barangay::findOrFail($item['barangay_id']);
                $stat=['barangay'=>$area->name,'residents_added'=>0,'deceased_added'=>0,'history_added'=>0,'forms_created'=>0,'forms_updated'=>0,'form_rows_added'=>0,'already_in_forms'=>0,'pending'=>[]];
                if ($item['registry']) {
                    $this->checkEmptyArea($area);
                    // Only backed-up, unlinked demonstration placeholders are removed.
                    MigrationRecord::where('barangay_id',$area->id)->where('reason',MigrationForecastHistory::DEMO_MARKER)->delete();
                    Inhabitant::where('barangay_id',$area->id)->where('remarks',MigrationForecastHistory::DEMO_MARKER)->delete();
                    Household::where('barangay_id',$area->id)->where('household_number','DEMO-MIGRATION-V1')->delete();
                    $homes=[];
                    foreach ($item['registry']['residents'] as $person) {
                        $number=$person['household_number'] ?: 'Not recorded';
                        $homes[$number] ??= Household::create(['barangay_id'=>$area->id,'household_number'=>(string)$number,
                            'purok'=>$person['complete_address']??null,'address'=>$person['complete_address']??null]);
                        $data=array_intersect_key($person,array_flip((new Inhabitant)->getFillable()));
                        $data['sex'] ??= '';
                        $moved=(bool)$person['_transferred'];
                        Inhabitant::create($data+['barangay_id'=>$area->id,'household_id'=>$homes[$number]->id,
                            'family_number'=>$person['household_number'],'status'=>$moved ? Inhabitant::STATUS_MIGRATED_OUT : Inhabitant::STATUS_ACTIVE,
                            'residence_status'=>$moved ? Inhabitant::RESIDENCE_ELSEWHERE : ($person['_needs_residence_review'] ? Inhabitant::RESIDENCE_UNCONFIRMED : Inhabitant::RESIDENCE_HERE),
                            'residence_source'=>$person['_source'].($moved ? ': explicit transfer source, movement date unknown' : ': consolidated source; unexplained markings require review')]);
                        $stat['residents_added']++;
                    }
                    foreach ($item['registry']['deceased'] as $person) {
                        $data=array_intersect_key($person,array_flip((new DeceasedInhabitant)->getFillable()));
                        $data['household_number'] ??= '';
                        DeceasedInhabitant::create($data+['barangay_id'=>$area->id]);
                        $stat['deceased_added']++;
                    }
                    $stat['pending']=array_merge($stat['pending'],$item['registry']['pending']);
                }
                foreach ($item['new_rows'] as $row) {
                    $data=array_intersect_key($row,array_flip((new NewInhabitant)->getFillable()));
                    $data['household_number'] ??= '';
                    $data['reporting_month']=$row['reporting_month'] ? $row['reporting_month'].'-01' : null;
                    NewInhabitant::create($data+['barangay_id'=>$area->id]); $stat['history_added']++;
                }
                $this->prepareForms($area,$item['source_file'],$stat);
                if ($stat['residents_added']+$stat['deceased_added']+$stat['history_added']+$stat['forms_created']+$stat['forms_updated']>0) {
                    RegistryActivity::create(['barangay_id'=>$area->id,'user_id'=>null,'description'=>'Imported missing BRBI archive records and prepared unsigned RBI drafts',
                        'changes'=>['record_type'=>'brbi_archive_import','source_file'=>$item['source_file'],'counts'=>array_diff_key($stat,['pending'=>true])]]);
                }
                $result[]=$stat;
            }
            return $result;
        });
    }

    private function prepareForms(Barangay $area, string $sourceFile, array &$stat): void
    {
        $reports=BarangayRbiUpdate::where('barangay_name',$area->name)->orderBy('id')->get();
        $represented=[];
        foreach ($reports as $report) foreach ($report->rows??[] as $row) {
            $row=HouseholdRbi::normalize($row);
            if (!empty($row['history_record_id'])) $represented['id:'.$row['history_record_id']]=true;
            $represented['person:'.Workbook::key($row)]=true;
        }
        $history=NewInhabitant::where('barangay_id',$area->id)->orderBy('source_position')->orderBy('id')->get();
        foreach ($history->groupBy(fn ($r)=>$r->reporting_month?->format('Y-m') ?: 'unknown') as $month=>$records) {
            $missing=$records->filter(function ($r) use ($represented,&$stat) {
                if (isset($represented['id:'.$r->id]) || isset($represented['person:'.Workbook::key($r->getAttributes())])) { $stat['already_in_forms']++; return false; }
                return true;
            });
            if ($missing->isEmpty()) continue;
            if ($month==='unknown' || $month>now()->format('Y-m')) {
                foreach ($missing as $r) $stat['pending'][]=['history_id'=>$r->id,'reason'=>'Reporting month/year missing or future; no dated draft invented'];
                continue;
            }
            $matches=$reports->filter(fn ($r)=>$r->reporting_month?->format('Y-m')===$month);
            if ($matches->count()>1 || ($matches->count()===1 && $matches->first()->status!==BarangayRbiUpdate::STATUS_DRAFT)) {
                foreach ($missing as $r) $stat['pending'][]=['history_id'=>$r->id,'reason'=>'Existing month is submitted or has multiple forms; preserved for normal review'];
                continue;
            }
            $report=$matches->first();
            $owners=User::where('barangay_id',$area->id)->where('role',User::ROLE_BARANGAY)->where('approval_status','!=',User::APPROVAL_REJECTED)->get();
            $approved=$owners->where('approval_status',User::APPROVAL_APPROVED);
            // Importing an unsigned draft does not approve its assigned secretary account.
            $owner=$report ? $owners->firstWhere('id',$report->barangay_user_id)
                : ($approved->count()===1 ? $approved->first() : ($owners->count()===1 ? $owners->first() : null));
            if (!$owner) { foreach ($missing as $r) $stat['pending'][]=['history_id'=>$r->id,'reason'=>'Unique assigned secretary ownership requires review']; continue; }
            $rows=[];
            foreach ($missing as $record) {
                $data=$record->getAttributes();
                $row=array_intersect_key($data,array_flip(array_keys(HouseholdRbi::fields())));
                $row['birth_date']=$record->birth_date?->format('Y-m-d') ?: '';
                $row['relationship']=$record->relationship_to_head ?: '';
                $row['history_record_id']=$record->id;
                $row['household_number']=$record->household_number ?: '';
                $home=$record->household_number ? Household::where('barangay_id',$area->id)->where('household_number',$record->household_number)->first() : null;
                $head=$home?->inhabitants()->whereRaw('LOWER(relationship_to_head) = ?', ['head'])->first();
                $row['household_head']=$head ? trim(implode(' ',array_filter([$head->first_name,$head->middle_name,$head->last_name,$head->suffix])))
                    : ($record->household_number ? 'Household '.$record->household_number.' — head needs review' : 'Household not recorded — source row '.$record->source_position);
                $row['household_id']=$home?->id;
                $row['inhabitant_id']=$record->active_inhabitant_id;
                $row=HouseholdRbi::normalize($row);
                if ($row['birth_date'] && substr($row['birth_date'],0,7)>$month) {
                    $row['remarks']=trim(($row['remarks']??'').' [Review: source birth date is after the reporting month. Confirm the original dates.]');
                }
                $keys=app(RbiDuplicateEntries::class)->keys($row,'rows');
                $duplicate=false;
                foreach ($rows as $existing) if (array_intersect($keys,app(RbiDuplicateEntries::class)->keys($existing,'rows'))) {$duplicate=true;break;}
                if ($duplicate) { $stat['pending'][]=['history_id'=>$record->id,'reason'=>'Duplicate identity in NEW history; retained once in draft']; continue; }
                $rows[]=$row;
            }
            if (!$rows) continue;
            $rows=app(RbiFirstSave::class)->stamp($rows,$report,now());
            if (!$report) {
                $report=BarangayRbiUpdate::create(['barangay_user_id'=>$owner->id,'barangay_name'=>$area->name,'reporting_month'=>$month.'-01',
                    'household_head'=>$rows[0]['household_head'],'status'=>BarangayRbiUpdate::STATUS_DRAFT,'rows'=>[],'deceased_rows'=>[],
                    'source_file_name'=>$sourceFile,'source_file_path'=>'import-sources/brbi-20261108/'.$sourceFile]);
                $reports->push($report);$stat['forms_created']++;
            } else $stat['forms_updated']++;
            $report->update(['rows'=>array_merge($report->rows??[],$rows)]);
            $this->appendMembers($report,$report->rbiFamilies()->exists() ? $rows : $report->rows);
            $stat['form_rows_added']+=count($rows);
            foreach ($rows as $r) { $represented['id:'.$r['history_record_id']]=true; $represented['person:'.Workbook::key($r)]=true; }
        }
    }

    private function appendMembers(BarangayRbiUpdate $report, array $rows): void
    {
        foreach ($rows as $row) {
            $row=HouseholdRbi::normalize($row);
            if (empty($row['inhabitant_name'])) continue;
            $head=$row['household_head']??'Household needs review';
            $family=$report->rbiFamilies()->where('household_head',$head)->first();
            $family ??= $report->rbiFamilies()->create(['household_id'=>$row['household_id']??null,'household_number'=>($row['household_number']??'')?:null,
                'household_head'=>$head,'position'=>$report->rbiFamilies()->count()]);
            $family->members()->create(['inhabitant_id'=>$row['inhabitant_id']??null,'inhabitant_name'=>$row['inhabitant_name'],
                'sex'=>($row['sex']??'')?:null,'birth_date'=>($row['birth_date']??'')?:null,'birth_place'=>$row['birth_place']??null,
                'civil_status'=>$row['civil_status']??null,'occupation'=>$row['occupation']??null,'relationship'=>$row['relationship']??null,
                'details'=>$row,'position'=>$family->members()->count()]);
        }
    }
}
