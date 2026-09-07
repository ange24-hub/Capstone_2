<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\Inhabitant;
use Illuminate\Support\Facades\DB;

class SourceResidenceSync
{
    public static function isGreen(string $rgb): bool
    {
        $rgb = substr(strtoupper($rgb), -6);
        if (! preg_match('/^[A-F0-9]{6}$/', $rgb)) return false;
        $red = hexdec(substr($rgb, 0, 2));
        $green = hexdec(substr($rgb, 2, 2));
        $blue = hexdec(substr($rgb, 4, 2));
        return $green > 70 && $green > $red + 6 && $green > $blue + 6;
    }

    public static function classify(array $colors, string $remarks): array
    {
        foreach ($colors as $color) {
            if (self::isGreen($color)) return [Inhabitant::RESIDENCE_ELSEWHERE, 'Green source marking'];
        }
        if (preg_match('/\b(OFW|ABROAD|OVERSEAS|SAUDI|HONG\s*KONG|MALAYSIA|TURKEY|MANILA|CEBU|BICOL|SIARGAO|INOPACAN)\b|\bAT\s+(?!HOME\b)|\bTRANSFER(?:RED)?\b/i', $remarks)) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Source remark indicates another residence'];
        }
        // Other colors and unclear place-only notes need confirmation before being
        // represented as physically living in the barangay.
        if (array_filter($colors, fn ($c) => ! in_array($c, ['', 'FFFFFFFF', 'FFFFFF'], true))
            || preg_match('/\b(DECEASE|MOVE|BOARDING|BHOUSE|MAASIN|SOGOD|ORMOC|CABASCAN|FORT MAGSAYSAY)\b/i', $remarks)) {
            return [Inhabitant::RESIDENCE_UNCONFIRMED, 'Other source marking needs residence review'];
        }
        return [Inhabitant::RESIDENCE_HERE, 'Unmarked registered resident in source'];
    }

    private static function key(array $parts): string
    {
        return implode('|', array_map(fn ($v) => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $v))), $parts));
    }

    public static function plan(Barangay $barangay, string $path): array
    {
        if (! $barangay->usesResidenceRegistry()) return ['updates'=>[], 'unmatched'=>[]];
        $sheet = in_array($barangay->name, ['Carnaga', 'Iniguihan'], true) ? 'Sheet1' : 'CONSOLIDATED RBI';
        $sheets = ResidenceWorkbookReader::read($path, in_array($barangay->name, ['San Antonio', 'San Isidro', 'San Miguel', 'San Roque'], true)
            ? [$sheet, 'DECEASED', 'NEW', 'OUT'] : [$sheet]);
        if (! isset($sheets[$sheet])) throw new \RuntimeException('Missing consolidated sheet for '.$barangay->name);
        if ($barangay->name === 'San Antonio') $sheets[$sheet] = SanAntonioWorkbook::plan($sheets)['active'];
        if ($barangay->name === 'San Isidro') $sheets[$sheet] = SanIsidroWorkbook::plan($sheets)['active'];
        if ($barangay->name === 'San Miguel') $sheets[$sheet] = SanMiguelWorkbook::plan($sheets)['active'];
        if ($barangay->name === 'San Roque') $sheets[$sheet] = SanRoqueWorkbook::plan($sheets)['active'];
        [$last, $first, $middle, $household, $remark] = match ($barangay->name) {
            'Biasong' => ['D','E','F','B','Q'],
            'Higosoan' => ['D','E','F','C','R'],
            'Iniguihan' => ['A','B','C',null,'O'],
            'Punong' => ['C','D','E','B','R'],
            default => ['C','D','E','B','Q'],
        };
        $source = [];
        foreach ($sheets[$sheet] as $position => $row) {
            if ($position < (match ($barangay->name) { 'Mag-ata' => 9, 'Punong' => 12, default => 11 })
                || empty($row[$last]) || empty($row[$first])) continue;
            if ($barangay->name === 'Iniguihan' && $row[$last] === 'LAST') continue;
            $key = self::key([$row[$last], $row[$first], $row[$middle] ?? '']);
            $colors = [$row['_fills'][$last] ?? '', $row['_fills'][$first] ?? ''];
            [$status, $reason] = match ($barangay->name) {
                'Maslog' => MaslogWorkbook::residence($colors, $row[$remark] ?? ''),
                'Punong' => PunongWorkbook::residence($row),
                'Rizal' => RizalWorkbook::residence($row),
                'San Agustin' => SanAgustinWorkbook::residence($row),
                'San Antonio' => SanAntonioWorkbook::residence($row),
                'San Isidro' => SanIsidroWorkbook::residence($row),
                'San Miguel' => SanMiguelWorkbook::residence($row),
                'San Roque' => SanRoqueWorkbook::residence($row),
                default => self::classify($colors, $row[$remark] ?? ''),
            };
            $source[$key][] = ['position'=>$position,'household'=>$household ? ($row[$household] ?? '') : '',
                'residence_status'=>$status,'residence_source'=>basename($path).' '.($row['_source'] ?? $sheet.' row '.$position).': '.$reason];
        }
        $updates = $unmatched = [];
        foreach (Inhabitant::with('household')->where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)->get() as $resident) {
            if ($resident->residence_source === 'Confirmed by barangay staff') continue;
            $matches = $source[self::key([$resident->last_name, $resident->first_name, $resident->middle_name])] ?? [];
            if (count($matches) > 1) {
                if (preg_match('/\[Source: [^\]]+ row (\d+)[;\]]/', (string) $resident->remarks, $sourceRow)) {
                    $located = array_values(array_filter($matches, fn ($m) => $m['position'] === (int) $sourceRow[1]));
                    if (count($located) === 1) $matches = $located;
                }
            }
            if (count($matches) > 1) {
                $matches = array_values(array_filter($matches, fn ($m) => (string) $m['household'] === (string) $resident->household->household_number));
            }
            if (count($matches) !== 1) { $unmatched[] = $resident->id; continue; }
            $match = $matches[0];
            if ($resident->residence_status !== $match['residence_status'] || $resident->residence_source !== $match['residence_source']) {
                $updates[$resident->id] = ['residence_status'=>$match['residence_status'],'residence_source'=>$match['residence_source']];
            }
        }
        return ['updates'=>$updates,'unmatched'=>$unmatched];
    }

    public static function apply(Barangay $barangay, string $path): array
    {
        if (! $barangay->usesResidenceRegistry()) return ['updated'=>0, 'unmatched'=>0, 'skipped'=>'Residence update excluded for Biasong'];
        $plan = self::plan($barangay, $path);
        $directory = storage_path('app/import-backups');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $backup = $directory.'/residence-'.$barangay->id.'-'.now()->format('Ymd-His-u').'.json';
        if (file_put_contents($backup, json_encode([
            'source_sha256'=>hash_file('sha256', $path), 'plan'=>$plan,
            'before'=>Inhabitant::where('barangay_id', $barangay->id)->whereIn('id', array_keys($plan['updates']))
                ->get(['id','residence_status','residence_source']),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new \RuntimeException('Residence backup failed.');
        DB::transaction(function () use ($barangay, $plan) {
            foreach ($plan['updates'] as $id=>$data) Inhabitant::where('barangay_id', $barangay->id)->whereKey($id)
                ->where('status', Inhabitant::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('residence_source')->orWhere('residence_source','!=','Confirmed by barangay staff'))->update($data);
        });
        return ['updated'=>count($plan['updates']),'unmatched'=>count($plan['unmatched']),'backup'=>$backup];
    }
}
