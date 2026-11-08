<?php

namespace App\Support;

use App\Services\RbiReportMonth;

/** Import data only: worksheet prose is never executed or treated as an instruction. */
class BrbiArchiveWorkbook
{
    public const REGISTRY_SHEETS = ['Anahawan' => 'CONSOLIDATED RBI', 'Banday' => 'CONSOLIDATED RBI (2026)',
        'Bogo' => 'CONSOLIDATED RBI (2)2026', 'Cambite' => 'CONSOLIDATED BRBI', 'Maanyag' => 'CONSOLIDATED RBI', 'Mapgap' => 'CONSOLIDATED RBI'];

    public static function key(array $person): string
    {
        $fields=['last_name', 'first_name', 'middle_name', 'suffix', 'birth_date'];
        if (empty($person['birth_date'])) $fields[]='household_number';
        return implode('|', array_map(fn ($field) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) ($person[$field] ?? '')))), $fields));
    }

    public static function date(?string $value, bool $dayFirst = false): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (is_numeric($value)) {
            if ((float) $value < 1000 || (float) $value > 100000) return null;
            $date = (new \DateTimeImmutable('1899-12-30'))->modify('+'.(int) floor((float) $value).' days');
            return $date->format('Y-m-d') <= now()->toDateString() ? $date->format('Y-m-d') : null;
        }
        $value = preg_replace('/,(?=\d)/', ', ', $value);
        $numeric = $dayFirst ? ['!d/m/Y', '!d-m-Y', '!m/d/Y', '!m-d-Y'] : ['!m/d/Y', '!m-d-Y', '!d/m/Y', '!d-m-Y'];
        foreach (array_merge(['!Y-m-d', '!F j, Y', '!F j Y', '!M j, Y', '!M j Y'], $numeric) as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || (! $errors['warning_count'] && ! $errors['error_count']))
                && $date->format('Y') >= '1800' && $date->format('Y-m-d') <= now()->toDateString()) return $date->format('Y-m-d');
        }
        return null;
    }

    public static function month(?string $value, ?string $sectionYear = null): ?string
    {
        $label = trim(preg_replace('/\s+/', ' ', preg_replace('/,(?=\d)/', ', ', (string) $value)));
        if (! preg_match('/\b(?:19|20)\d{2}\b/', $label) && $sectionYear && preg_match('/^[A-Za-z]+$/', $label)) $label .= ' '.$sectionYear;
        return (new RbiReportMonth)->resolve(null, $label);
    }

    private static function sexColumn(array $row, array $allowed, string $fallback): string
    {
        if (preg_match('/^(?:M|F|MALE|FEMALE)$/i', trim((string)($row[$fallback]??'')))) return $fallback;
        foreach ($allowed as $column) {
            $value=strtoupper(trim((string)($row[$column]??'')));
            if (!in_array($value,['M','F','MALE','FEMALE'],true)) continue;
            $age=$row[chr(ord($column)-1)]??'';
            $civil=trim((string)($row[chr(ord($column)+1)]??''));
            if (self::date($row[chr(ord($column)-2)]??null)!==null || (is_numeric($age)&&(float)$age>=0&&(float)$age<=150)
                || in_array($value,['F','FEMALE'],true) || preg_match('/^(?:MARRIED|SINGLE|WIDOWED|WIDOW|SEPARATED|DIVORCED)$/i',$civil)) return $column;
        }
        // A marital-status M beside a blank sex cell must never become a male label.
        return $fallback;
    }

    private static function finish(array $data, string $source, bool $dayFirst = false): array
    {
        $remarks = trim((string) ($data['remarks'] ?? ''));
        foreach (['birth_date', 'death_date'] as $field) {
            if (! array_key_exists($field, $data)) continue;
            $raw = $data[$field]; $data[$field] = self::date($raw, $dayFirst);
            if (filled($raw) && ! $data[$field]) $remarks .= ' [Source '.$field.': '.$raw.'; needs review]';
        }
        $sex = strtoupper(trim((string) ($data['sex'] ?? '')));
        $data['sex'] = match ($sex) {'M', 'MALE' => 'Male', 'F', 'FEMALE' => 'Female', default => null};
        $data['recorded_age'] = filled($data['recorded_age'] ?? null) ? (string) $data['recorded_age'] : null;
        if (strlen((string)$data['recorded_age'])>30) { $remarks.=' [Source age: '.$data['recorded_age'].'; needs review]'; $data['recorded_age']=null; }
        $data['remarks'] = trim($remarks.' [Source: '.$source.']');
        $data['_source'] = $source;
        return $data;
    }

    public static function registry(string $area, array $sheets): array
    {
        $primary = self::REGISTRY_SHEETS[$area] ?? throw new \InvalidArgumentException('Unsupported empty-area registry.');
        if (! isset($sheets[$primary])) throw new \RuntimeException('Missing '.$primary);
        $residents = $deceased = $pending = []; $deadKeys = [];
        foreach ($sheets as $name => $rows) {
            if (strtoupper($name) !== 'DECEASED') continue;
            foreach ($rows as $number => $row) {
                if ($number < 2 || ! filled($row['B'] ?? null) || ! filled($row['C'] ?? null)) continue;
                $sex = self::sexColumn($row, ['K', 'L', 'M'], 'K'); $i = ord($sex);
                $data = ['household_number' => $row['A'] ?? null, 'last_name' => $row['B'], 'first_name' => $row['C'],
                    'middle_name' => $row['D'] ?? null, 'suffix' => $row['E'] ?? null];
                foreach (['relationship_to_head'=>-5,'purok'=>-4,'birth_place'=>-3,'birth_date'=>-2,'recorded_age'=>-1,
                    'sex'=>0,'civil_status'=>1,'education_level'=>2,'religion'=>3,'occupation'=>4,'remarks'=>5] as $field=>$offset) $data[$field] = $row[chr($i+$offset)] ?? null;
                // Some deceased rows insert a blank before sex without shifting earlier fields.
                foreach (['F','G','H'] as $relationshipColumn) {
                    if (!preg_match('/^(?:HEAD|WIFE|HUSBAND|SON|DAUGHTER|MOTHER|FATHER|SISTER|BROTHER)$/i',trim((string)($row[$relationshipColumn]??'')))) continue;
                    $start=ord($relationshipColumn);
                    foreach (['relationship_to_head'=>0,'purok'=>1,'birth_place'=>2,'birth_date'=>3,'recorded_age'=>4] as $field=>$offset) $data[$field]=$row[chr($start+$offset)]??null;
                    break;
                }
                // Use a complete day only; month-only death labels remain notes.
                $death = $row[$area === 'Maanyag' ? 'P' : ($area === 'Mapgap' ? 'R' : 'Q')] ?? '';
                if (! filled($death)) $death = $row['Q'] ?? '';
                $data['death_date'] = $death;
                if ($data['remarks'] === $death) $data['remarks'] = '';
                $data = self::finish($data, $area.' '.$name.' row '.$number);
                $key = self::key($data);
                if (isset($deadKeys[$key])) { $pending[] = ['source'=>$data['_source'],'reason'=>'Duplicate deceased identity']; continue; }
                $deadKeys[$key] = true; $deceased[] = $data;
            }
        }
        foreach ([$primary, ...($area === 'Cambite' ? ['TRANSFERED'] : [])] as $sheet) {
            $household = '';
            foreach ($sheets[$sheet] ?? [] as $number => $row) {
                if ($sheet === $primary && $number < 11) continue;
                $lastColumn = $area === 'Anahawan' ? 'D' : 'C'; $firstColumn = $area === 'Anahawan' ? 'E' : 'D';
                if (! filled($row[$lastColumn] ?? null) && ! filled($row[$firstColumn] ?? null)) continue;
                if (! filled($row[$lastColumn] ?? null) || ! filled($row[$firstColumn] ?? null)
                    || preg_match('/\bVACANT\b|^LAST$|^FIRST NAME$/i', ($row[$lastColumn] ?? '').' '.($row[$firstColumn] ?? ''))) {
                    $pending[] = ['source'=>$area.' '.$sheet.' row '.$number,'reason'=>'Incomplete name or nonperson row']; continue;
                }
                $sex = self::sexColumn($row, ['L', 'M'], $area === 'Cambite' && $sheet === $primary ? 'M' : 'L'); $i = ord($sex);
                $relation = $row[chr($i-5)] ?? '';
                $hh = $row[$area === 'Anahawan' ? 'A' : 'B'] ?? '';
                if (preg_match('/^\d+[A-Za-z-]*$/', trim($hh))) $household = trim($hh);
                elseif (trim($hh) !== '') $household = '';
                elseif (preg_match('/^HEAD$/i', trim($relation))) $household = '';
                $data = ['household_number'=>$household ?: null,'last_name'=>$row[$lastColumn],'first_name'=>$row[$firstColumn],
                    'middle_name'=>$row[$area === 'Anahawan' ? 'F' : 'E'] ?? null,'suffix'=>$area === 'Anahawan' ? null : ($row['F'] ?? null)];
                foreach (['relationship_to_head'=>-5,'complete_address'=>-4,'birth_place'=>-3,'birth_date'=>-2,'recorded_age'=>-1,
                    'sex'=>0,'civil_status'=>1,'education_level'=>2,'religion'=>3,'occupation'=>4,'remarks'=>5] as $field=>$offset) $data[$field] = $row[chr($i+$offset)] ?? null;
                if ($area === 'Banday') { $data['religion']=$row['P']??null; $data['occupation']=$row['Q']??null; $data['remarks']=$row['R']??null; }
                $data = self::finish($data, $area.' '.$sheet.' row '.$number, $area === 'Mapgap');
                $key = self::key($data);
                if (isset($deadKeys[$key])) continue;
                if (preg_match('/\b(?:DECEASED|DESEACED|DESCEASED|DIED)\b/i', $data['remarks'])) {
                    $data['death_date']=null;
                    $deceased[]=$data; $deadKeys[$key]=true;
                    continue;
                }
                if (isset($residents[$key])) {
                    if ($sheet === 'TRANSFERED') $residents[$key]['_transferred'] = true;
                    $pending[]=['source'=>$data['_source'],'reason'=>'Duplicate identity retained once']; continue;
                }
                $data['_transferred'] = $sheet === 'TRANSFERED' || preg_match('/\bTRANSFER(?:RED|ED)\b/i', $data['remarks']);
                // Do not turn unexplained color or place names into confirmed movement.
                $data['_needs_residence_review'] = (bool) array_filter([$row['_fills'][$lastColumn]??'', $row['_fills'][$firstColumn]??''], fn ($v)=>!in_array($v,['','FFFFFFFF','FFFFFF'],true));
                $residents[$key]=$data;
            }
        }
        return ['residents'=>array_values($residents),'deceased'=>$deceased,'pending'=>$pending];
    }

    public static function newEntries(string $area, string $sheet, array $rows): array
    {
        $result = []; $sectionYear = null;
        foreach ($rows as $number => $row) {
            foreach ($row as $value) if (is_string($value) && preg_match('/NEW INHABITANT.*\b((?:19|20)\d{2})\b/i',$value,$m)) $sectionYear=$m[1];
            if ($number < 2 || ! filled($row['B'] ?? null) || ! filled($row['C'] ?? null)) continue;
            if (preg_match('/INHABITANT|LAST NAME|FIRST NAME/i', $row['B'].' '.$row['C'])) continue;
            $sex = self::sexColumn($row, ['H','J','K'], $area === 'Anahawan' ? 'K' : ($area === 'Canlupao' ? 'J' : 'H'));
            $i=ord($sex);
            $data=['household_number'=>$row['A']??null,'last_name'=>$row['B'],'first_name'=>$row['C'],
                'middle_name'=>$row['D']??null,'suffix'=>$row['E']??null];
            foreach (['birth_date'=>-2,'recorded_age'=>-1,'sex'=>0,'civil_status'=>1,'education_level'=>2,'religion'=>3,'occupation'=>4,'remarks'=>5] as $field=>$offset) $data[$field]=$row[chr($i+$offset)]??null;
            if ($area === 'Anahawan') {
                $data['relationship_to_head']=$row['F']??null; $data['complete_address']=$row['G']??null; $data['birth_place']=$row['H']??null;
                $data['religion']=$data['occupation']=$data['remarks']=null;
            }
            if ($sex === 'J') {
                $data['suffix']=null; $data['relationship_to_head']=$row['E']??null;
                $data['complete_address']=$row['F']??null; $data['birth_place']=$row['G']??null;
                $data['religion']=null; $data['occupation']=$row['M']??null; $data['remarks']=null;
            }
            $monthColumn=match(true){$area==='Maanyag'=>'M',$area==='Anahawan' || $sex==='J'=>'N',default=>chr($i+6)};
            $label=$row[$monthColumn]??'';
            if ($area==='Maanyag') $data['remarks']=null;
            $data=self::finish($data,$area.' '.$sheet.' row '.$number);
            $data['source_position']=$number; $data['month_submitted']=$label;
            $data['reporting_month']=self::month($label,$sectionYear);
            $result[]=$data;
        }
        return $result;
    }
}
