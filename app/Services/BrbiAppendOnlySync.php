<?php

namespace App\Services;

use App\Models\{Barangay, DeceasedInhabitant, Inhabitant, NewInhabitant};
use App\Support\BrbiArchiveWorkbook;
use Illuminate\Support\Facades\DB;

/** Recipient IDs are resolved by barangay name. Existing identities are never updated. */
class BrbiAppendOnlySync
{
    public const TABLES = ['households', 'inhabitants', 'deceased_inhabitants', 'new_inhabitants',
        'migration_records', 'barangay_rbi_updates', 'barangay_rbi_families', 'barangay_rbi_members',
        'barangay_rbi_deceased_records', 'registry_activities'];

    public function plan(array $source): array
    {
        $areas = Barangay::all()->keyBy(fn ($a) => self::text($a->name));
        $plan = ['append_only'=>true, 'source_hashes'=>[], 'areas'=>[], 'skipped'=>0, 'review'=>[]];
        foreach ($source['areas'] as $item) {
            $area = $areas->get(self::text($item['name'])) ?? throw new \RuntimeException('Unmapped barangay.');
            $item['barangay_id'] = $area->id;
            $item['name'] = $area->name;
            $living = Inhabitant::where('barangay_id', $area->id)->get()->map->getAttributes()->all();
            $dead = DeceasedInhabitant::where('barangay_id', $area->id)->get()->map->getAttributes()->all();
            if ($item['registry']) {
                foreach (['residents', 'deceased'] as $group) {
                    $accepted = [];
                    foreach ($item['registry'][$group] as $person) {
                        $matches = self::matches($person, array_merge($living, $dead));
                        if ($matches !== 'new') {
                            $plan['skipped']++;
                            if ($matches === 'review') $plan['review'][] = ['barangay'=>$area->name,
                                'source'=>$person['_source'] ?? null, 'reason'=>'Possible existing identity; preserved for review'];
                            continue;
                        }
                        $accepted[] = $person;
                        if ($group === 'residents') $living[] = $person; else $dead[] = $person;
                    }
                    $item['registry'][$group] = $accepted;
                }
            }
            $history = NewInhabitant::where('barangay_id', $area->id)->get()->map->getAttributes()->all();
            $missing = [];
            foreach ($item['new_rows'] as $person) {
                // NEW history is retained once per identity, including unknown reporting months.
                if (self::matches($person, $history) !== 'new') { $plan['skipped']++; continue; }
                $missing[] = $person;
                $history[] = $person;
            }
            $item['new_rows'] = $missing;
            $plan['areas'][] = $item;
        }
        return $plan;
    }

    private static function text(mixed $text): string
    {
        return mb_strtoupper(trim(preg_replace('/[\p{P}\p{Z}\s]+/u', ' ', (string) $text)));
    }

    private static function matches(array $person, array $existing): string
    {
        foreach ($existing as $record) {
            if (self::text($person['first_name'] ?? '') !== self::text($record['first_name'] ?? '')
                || self::text($person['last_name'] ?? '') !== self::text($record['last_name'] ?? '')) continue;
            $birth = substr((string) ($person['birth_date'] ?? ''), 0, 10);
            $other = substr((string) ($record['birth_date'] ?? ''), 0, 10);
            if ($birth !== '' && $other !== '' && $birth !== $other) continue;
            if ($birth !== '' && $birth === $other
                && self::text($person['middle_name'] ?? '') === self::text($record['middle_name'] ?? '')
                && self::text($person['suffix'] ?? '') === self::text($record['suffix'] ?? '')) return 'same';
            return 'review';
        }
        return 'new';
    }

    public function apply(array $source, string $backup): array
    {
        if (! is_dir($backup) && ! mkdir($backup, 0700, true)) throw new \RuntimeException('Private backup unavailable.');
        return DB::transaction(function () use ($source, $backup) {
            Barangay::orderBy('id')->lockForUpdate()->get();
            $before = [];
            foreach (self::TABLES as $table) {
                $stream = fopen($backup.'/'.$table.'.jsonl', 'x');
                if (! $stream) throw new \RuntimeException('Backup already exists or is not writable.');
                $hash = hash_init('sha256'); $count = 0; $max = 0;
                try {
                    foreach (DB::table($table)->orderBy('id')->lockForUpdate()->cursor() as $row) {
                        $line = json_encode($row, JSON_THROW_ON_ERROR)."\n";
                        if (fwrite($stream, $line) !== strlen($line)) throw new \RuntimeException('Incomplete backup.');
                        hash_update($hash, $line); $count++; $max = $row->id;
                    }
                } finally { fclose($stream); }
                @chmod($backup.'/'.$table.'.jsonl', 0600);
                $before[$table] = ['count'=>$count, 'max_id'=>$max, 'sha256'=>hash_final($hash)];
            }
            file_put_contents($backup.'/before.json', json_encode($before, JSON_THROW_ON_ERROR));
            $plan = $this->plan($source);
            file_put_contents($backup.'/review.json', json_encode($plan['review'], JSON_THROW_ON_ERROR));
            $results = app(BrbiArchiveImport::class)->apply($plan);
            foreach ($before as $table => $state) {
                // Existing draft headers may gain rows. Every original row prefix and other field is checked below.
                if ($table === 'barangay_rbi_updates') {
                    $stream = fopen($backup.'/'.$table.'.jsonl', 'r');
                    try {
                        while (($line = fgets($stream)) !== false) {
                            $old = json_decode($line, true, flags:JSON_THROW_ON_ERROR);
                            $now = (array) DB::table($table)->find($old['id']);
                            if (($old['status'] ?? null) === 'draft') {
                                $oldRows = json_decode($old['rows'] ?: '[]', true, flags:JSON_THROW_ON_ERROR);
                                $nowRows = json_decode($now['rows'] ?: '[]', true, flags:JSON_THROW_ON_ERROR);
                                if (array_slice($nowRows, 0, count($oldRows)) !== $oldRows) throw new \RuntimeException('Existing draft rows changed.');
                                unset($old['rows'], $old['updated_at'], $now['rows'], $now['updated_at']);
                            }
                            if ($old !== $now) throw new \RuntimeException('Existing form changed.');
                        }
                    } finally { fclose($stream); }
                } else {
                    $hash = hash_init('sha256'); $count = 0;
                    foreach (DB::table($table)->where('id', '<=', $state['max_id'])->orderBy('id')->cursor() as $row) {
                        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n"); $count++;
                    }
                    if ($count !== $state['count'] || hash_final($hash) !== $state['sha256']) throw new \RuntimeException('Existing records changed; sync rolled back.');
                }
            }
            $summary = array_map(fn ($r) => array_diff_key($r, ['pending'=>true])+['pending_review'=>count($r['pending'])], $results);
            $result = ['existing_records_preserved'=>true, 'skipped'=>$plan['skipped'], 'identity_review'=>count($plan['review']), 'barangays'=>$summary];
            file_put_contents($backup.'/result.json', json_encode($result, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            return $result;
        });
    }
}
