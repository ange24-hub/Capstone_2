<?php

namespace App\Services;

use App\Support\{PopulationMarkers, RegistryRemarks};

/** RBI Form C presentation of existing records; no missing sector or historical values are invented. */
class PopulationMonitoringReport
{
    public function build($records, array $report, $barangays): array
    {
        $ages=[];
        for($start=0;$start<=80;$start+=5) {
            $label=$start===0?'Under 5 years old':($start===80?'80 years old and over':$start.'-'.($start+4).' years old');
            $ages[$start]=$this->row($label);
        }
        $unknown=$this->row('Age not recorded / invalid');
        $definitions=[
            'labor'=>['Labor Force','LABOU?R FORCE'],
            'unemployed'=>['Unemployed','UNEMPLOYED'],
            'osc'=>['Out of School Children (OSC) (6-14 years old)','OSC|OUT[ -]OF[ -]SCHOOL CHILD(?:REN)?'],
            'osy'=>['Out of School Youth (OSY) (15-24 years old)','OSY|OUT[ -]OF[ -]SCHOOL YOUTH'],
            'pwd'=>['Person with Disabilities (PWDs)',null],
            'ofw'=>['Overseas Filipino Workers (OFWs)','OFWS?|OVERSEAS FILIPINO WORKERS?'],
            'solo'=>['Solo Parents','SOLO PARENTS?|SINGLE PARENTS?'],
            'ip'=>['Indigenous Peoples (IPs)','IPS?|INDIGENOUS (?:PEOPLES?|PERSONS?)'],
        ];
        $sectors=[];
        foreach($definitions as $key=>[$label]) $sectors[$key]=$this->row($label)+['available'=>false,'remarks'=>'Not recorded'];
        $single=$this->row('Civil Status: Single');$married=$this->row('Civil Status: Married');$civilKnown=false;
        foreach($records as $record) {
            $sex=match(strtolower(trim((string)$record->sex))){'m','male'=>'male','f','female'=>'female',default=>'unspecified'};
            $age=null;
            try{if($record->birth_date && $record->birth_date->lte($report['generatedAt']->copy()->startOfDay())) $age=(int)$record->birth_date->diffInYears($report['generatedAt']->copy()->startOfDay());}catch(\Throwable){}
            if($age===null) $this->increment($unknown,$sex);
            else {$key=min(80,intdiv($age,5)*5);$this->increment($ages[$key],$sex);}
            $remarks=RegistryRemarks::display($record->remarks);
            foreach($definitions as $key=>[$label,$pattern]) {
                $text=$remarks;
                if(in_array($key,['unemployed','ofw'],true)) $text.=' '.(string)$record->occupation;
                $matched=$key==='pwd'?PopulationMarkers::has($record->remarks,'pwd'):$this->marked($text,$pattern);
                if($key==='osc') $matched=$matched && $age!==null && $age>=6 && $age<=14;
                if($key==='osy') $matched=$matched && $age!==null && $age>=15 && $age<=24;
                if($matched){$this->increment($sectors[$key],$sex);$sectors[$key]['available']=true;$sectors[$key]['remarks']='Tagged records only';}
            }
            $civil=strtolower(trim((string)$record->civil_status));
            if(in_array($civil,['single','s','married','m','widowed','w','separated','divorced'],true)) $civilKnown=true;
            if(in_array($civil,['single','s'],true)) $this->increment($single,$sex);
            if(in_array($civil,['married','m'],true)) $this->increment($married,$sex);
        }
        if($unknown['total']>0) $ages['unknown']=$unknown;
        $sectors['single']=$single+['available'=>$civilKnown,'remarks'=>$civilKnown?'Recorded civil status':'Not recorded'];
        $sectors['married']=$married+['available'=>$civilKnown,'remarks'=>$civilKnown?'Recorded civil status':'Not recorded'];
        // Nationality/citizenship cannot be inferred from ethnicity, name, birthplace or residence.
        $sectors['filipino']=$this->row('Citizenship: Filipino')+['available'=>false,'remarks'=>'Not recorded'];
        $sectors['foreigner']=$this->row('Citizenship: Foreigner')+['available'=>false,'remarks'=>'Not recorded'];
        $area=$barangays->count()===1?$barangays->first():null;
        return ['ages'=>array_values($ages),'sectors'=>array_values($sectors),'year'=>(int)$report['generatedAt']->year,
            'semester'=>$report['generatedAt']->month<=6?1:2,'barangay'=>$area?->name ?? 'All barangays - consolidated',
            'preparedBy'=>$area?->secretary_name,'submittedBy'=>$area?->punong_barangay_name,'isBarangay'=>$area!==null];
    }

    public static function sectorRemarks(array $row): string
    {
        $remarks = $row['remarks'] ?? '';
        $remarks = $remarks === 'Not recorded' ? '' : $remarks;
        $unspecified = $row['unspecified'] ?? 0;
        if ($unspecified) {
            $remarks = $remarks === '' ? 'Sex unspecified: '.$unspecified : $remarks.'; sex unspecified: '.$unspecified;
        }

        return $remarks;
    }

    private function row(string $label): array {return ['label'=>$label,'male'=>0,'female'=>0,'unspecified'=>0,'total'=>0];}
    private function increment(array &$row,string $sex): void {$row[$sex]++;$row['total']++;}
    private function marked(string $text,string $pattern): bool
    {
        $marker='(?:'.$pattern.')';
        if(preg_match('/\b(?:NO|NOT|NON|POSSIBLE|SUSPECTED)\s*[-:]?\s*'.$marker.'\b|\b'.$marker.'\s*[:=\-]?\s*(?:NO|NONE|FALSE|UNKNOWN|UNCONFIRMED)\b|\b'.$marker.'\s*\?/i',$text)) return false;
        return (bool)preg_match('/(?<![A-Z0-9])'.$marker.'(?![A-Z0-9])/i',$text);
    }
}
