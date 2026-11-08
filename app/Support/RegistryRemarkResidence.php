<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\Inhabitant;

/** User-authorized registry categorization. It never invents a departure date. */
class RegistryRemarkResidence
{
    // Southern Leyte municipalities are outside a Tomas Oppus barangay even within the province.
    // Reference: https://psa.gov.ph/classification/psgc/citimuni/0806400000
    private const PLACES = ['MAASIN','SOGOD','BONTOC','MALITBOG','MACROHON','PADRE BURGOS','LIMASAWA',
        'LIBAGON','LILOAN','PINTUYAN','SAN RICARDO','SAN FRANCISCO','SAN JUAN','SAINT BERNARD','ST BERNARD',
        'HINUNANGAN','HINUNDAYAN','SILAGO','CEBU','MANILA','METRO MANILA','DAVAO','BOHOL','ORMOC','TACLOBAN',
        'SAMAR','BICOL','LAGUNA','CAGAYAN','BULACAN','CAVITE','DUMAGUETE','DOMAGUETE','NEGROS','PAMPANGA',
        'BATANGAS','BAGUIO','BACOLOD','ILOILO','TARLAC','QUEZON CITY','MAKATI','PASIG','TAGUIG','CALOOCAN',
        'LAS PINAS','DUBAI','SAUDI','JEDDAH','QATAR','QUATAR','KUWAIT','HONG KONG','HONGKONG','MALAYSIA','TURKEY','TAIWAN',
        'SINGAPORE','JAPAN','KOREA','AUSTRALIA','CANADA','USA','EUROPE','ISRAEL','MINDANAO','LUZON','BORACAY',
        'SIARGAO','BATO','HILONGOS','INOPACAN','BAYBAY','MAHAPLAG','MATALOM','HINDANG','VILLABA','DULAG',
        'ABUYOG','PALO','KANANGA','BURAUEN','TANAUAN','SURIGAO','BISLIG','POLAND','US'];

    public static function clean(?string $remarks): string
    {
        $text=RegistryRemarks::display($remarks);
        $text=preg_replace('/\[(?:Household|Conflicting|Reporting year|Review:|Duplicate|Deceased:|Residence|First saved|Imported)[^\]]*\]/i',' ',$text);
        return strtoupper(trim(preg_replace('/\s+/u',' ',str_replace(['[',']'],' ',$text))));
    }

    private static function contains(string $text, string $place): bool
    {
        return (bool)preg_match('/(?<![\p{L}])'.preg_quote(strtoupper($place),'/').'(?![\p{L}])/u',$text);
    }

    public static function decide(?string $remarks, string $ownBarangay): array
    {
        $text=self::clean($remarks);
        $none=['action'=>null,'reason'=>null];
        if($text===''||$text===strtoupper(\App\Services\MigrationForecastHistory::DEMO_MARKER))return $none;
        if(preg_match('/\b(?:FOR TRANSFER|TRANSFER PENDING|WILL TRANSFER|NOT(?: YET)? TRANSFER(?:RED|ED)?|CANCELLED TRANSFER|TRANSFERRED FROM|TRANSFERED FROM)\b/',$text))return $none;
        if(preg_match('/\b(?:TRANSFERRED|TRANSFERED|TRANSFFERED|TRANSFFERR?ED|TRANSFER\s+TO)\b|^TRANSFER$/',$text))return ['action'=>'transferred','reason'=>'Explicit transfer in source remarks'];
        if(preg_match('/\b(?:OUTSIDE|NOT IN)\s+(?:SOUTHERN|SO\.?)\s*LEYTE\b|\b(?:OTHER|ANOTHER|LAING) BARANGAY\b/',$text))return ['action'=>'moved_out','reason'=>'Remarks explicitly place the resident outside the barangay'];
        $own=strtoupper($ownBarangay);
        if(preg_match('/\b(?:NOW LIVING|CURRENTLY LIVING|RESIDING|STAYING|BACK|RETURNED)\s+(?:IN|AT|TO)\s+(?:BRGY\.?\s+)?'.preg_quote($own,'/').'\b/',$text))return $none;
        if(preg_match('/^(?:BIRTHPLACE|BORN IN|PREVIOUS RESIDENCE|FORMERLY)\b/',$text))return $none;
        if(preg_match('/\b(?:OFW|ABROAD|OVERSEAS|MOVED OUT|MOVE OUT|MIGRATED OUT)\b/',$text))return ['action'=>'moved_out','reason'=>'Remarks explicitly indicate living elsewhere'];
        $withoutSouthernLeyte=preg_replace('/\b(?:SOUTHERN|SO\.?)\s*LEYTE\b/','',$text);
        if(self::contains($withoutSouthernLeyte,'LEYTE'))return ['action'=>'moved_out','reason'=>'Leyte province named separately from Southern Leyte'];
        foreach(self::PLACES as$place)if(self::contains($text,$place))return ['action'=>'moved_out','reason'=>'Location in remarks: '.$place];
        foreach(Barangay::TOMAS_OPPUS_BARANGAYS as$place)if(strcasecmp($place,$ownBarangay)!==0 && self::contains($text,$place))return ['action'=>'moved_out','reason'=>'Other barangay in remarks: '.$place];
        // Own barangay names and Southern Leyte alone do not establish a departure.
        return $none;
    }

    public static function apply(Inhabitant $person): void
    {
        if($person->remarks===\App\Services\MigrationForecastHistory::DEMO_MARKER)return;
        if($person->status && !in_array($person->status,[Inhabitant::STATUS_ACTIVE,Inhabitant::STATUS_MIGRATED_OUT],true))return;
        $decision=self::decide($person->remarks,$person->barangay?->name??'');
        if(!$decision['action'])return;
        if($decision['action']==='transferred')$person->status=Inhabitant::STATUS_MIGRATED_OUT;
        elseif(!$person->status)$person->status=Inhabitant::STATUS_ACTIVE;
        $person->residence_status=Inhabitant::RESIDENCE_ELSEWHERE;
        $person->residence_source='Registry remarks rule: '.$decision['reason'].'; movement date requires confirmation';
    }
}
