<?php
namespace App\Support;
class HouseholdRbi
{
    public static function fields(): array { return [
        'last_name'=>'Last name', 'first_name'=>'First name', 'middle_name'=>'Middle name', 'suffix'=>'Qualifier',
        'relationship'=>'Relationship to HH head', 'complete_address'=>'Complete address', 'birth_place'=>'Place of birth',
        'birth_date'=>'Date of birth', 'recorded_age'=>'Age', 'sex'=>'Sex (M/F)', 'civil_status'=>'Civil status',
        'education_level'=>'School grade/year/level completed', 'religion'=>'Religion', 'occupation'=>'Occupation', 'remarks'=>'Remarks / Other info',
    ]; }
    public static function normalize(array $row): array {
        if (empty($row['last_name']) && empty($row['first_name']) && ! empty($row['inhabitant_name'])) {
            $parts=array_map('trim',explode(',', $row['inhabitant_name']));
            if(count($parts)>=2 && count($parts)<=4) { $row['last_name']=$parts[0]; $row['first_name']=$parts[1]; $row['middle_name']=$parts[2]??''; $row['suffix']=$parts[3]??($row['suffix']??''); }
            else { $row['last_name']=$row['inhabitant_name']; }
        }
        if (! empty($row['first_name']) && ! empty($row['last_name'])) $row['inhabitant_name']=trim($row['last_name']).', '.trim($row['first_name']).(filled($row['middle_name']??null)?', '.trim($row['middle_name']):'');
        return $row;
    }
    public static function widths(): array { return [8,8,8,3,7,10,9,7,3,3,5,9,6,7,7]; }
}
