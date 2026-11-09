<?php

namespace App\Services;

use Illuminate\Support\Facades\{Artisan, DB, Schema};

/** Apply only the already-reviewed age-unit migration, with before/after value preservation. */
class BrbiAgeStorage
{
    private const TABLES=['inhabitants','new_inhabitants','deceased_inhabitants'];

    public function prepare(string $backup): void
    {
        $numeric=['smallint','int','integer','bigint','tinyint','mediumint'];
        $needed=false;
        foreach(self::TABLES as $table) if(in_array(Schema::getColumnType($table,'recorded_age'),$numeric,true)) $needed=true;
        if(!$needed) return;
        if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new \RuntimeException('Age storage backup unavailable.');
        $before=[];
        foreach(self::TABLES as $table){
            $stream=fopen($backup.'/'.$table.'.jsonl','x');
            if(!$stream) throw new \RuntimeException('Age backup already exists or is not writable.');
            try{$before[$table]=$this->state($table,null,$stream);}finally{fclose($stream);}
        }
        file_put_contents($backup.'/before.json',json_encode($before,JSON_THROW_ON_ERROR));
        $status=Artisan::call('migrate',['--force'=>true,'--path'=>'database/migrations/2026_11_05_020000_allow_age_units_in_registry_records.php']);
        if($status!==0) throw new \RuntimeException('Targeted age-unit migration failed.');
        foreach($before as $table=>$state){
            if(in_array(Schema::getColumnType($table,'recorded_age'),$numeric,true)) throw new \RuntimeException('Age-unit schema remains numeric.');
            if($this->state($table,$state['max_id'])!==$state) throw new \RuntimeException('Existing age storage values changed; review private backup.');
        }
        file_put_contents($backup.'/verified.json',json_encode(['existing_values_preserved'=>true,'migration'=>'allow_age_units_in_registry_records'],JSON_THROW_ON_ERROR));
    }

    private function state(string $table, ?int $maxId=null, mixed $stream=null): array
    {
        $query=DB::table($table)->orderBy('id');
        if($maxId!==null) $query->where('id','<=',$maxId);
        $hash=hash_init('sha256');$count=0;$max=0;
        foreach($query->cursor() as $row){
            if($stream){$line=json_encode($row,JSON_THROW_ON_ERROR)."\n";if(fwrite($stream,$line)!==strlen($line)) throw new \RuntimeException('Incomplete age backup.');}
            $values=(array)$row;
            // Numeric ages become strings under the wider schema; their actual values must stay identical.
            if($values['recorded_age']!==null) $values['recorded_age']=(string)$values['recorded_age'];
            ksort($values);
            hash_update($hash,json_encode($values,JSON_THROW_ON_ERROR)."\n");$count++;$max=(int)$row->id;
        }
        return ['rows'=>$count,'max_id'=>$max,'sha256'=>hash_final($hash)];
    }
}
