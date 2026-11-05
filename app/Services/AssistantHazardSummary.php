<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\User;

class AssistantHazardSummary
{
    public function __construct(private GisLayers $layers) {}

    public static function isRequest(string $question): bool
    {
        $instructions = ! preg_match('/\bhow (?:many|much)\b/iu', $question)
            && preg_match('/\b(?:how|unsaon|explain|instructions|steps|guide)\b/iu', $question);
        return preg_match('/\b(?:flood(?:ing)?|flodd|baha|landslide|land slide|storm[ -]surge)\b/iu', $question) && ! $instructions;
    }

    public function respond(User $user, string $question): ?array
    {
        if (! self::isRequest($question)) return null;
        $ceb = app()->getLocale() === 'ceb';
        if (! $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]) || ! $user->isApproved()
            || ($user->hasRole(User::ROLE_BARANGAY) && ! $user->barangay_id)) {
            return $this->answer($ceb
                ? 'Authorized ug approved barangay o municipal staff lamang ang makasusi sa household hazard records. Kontaka ang imong barangay para sa pag-verify sa inyong lugar. Dili live alert ang supplied map layers.'
                : 'Only authorized, approved barangay or municipal staff can inspect household hazard records. Contact your barangay to verify your area. The supplied map layers are not live alerts.', 'restricted');
        }

        $areas = Barangay::orderBy('name')->get(['id', 'name']);
        $mentioned = $areas->filter(fn ($area) => preg_match('/(?<![\pL\pN])'.preg_quote($area->name, '/').'(?![\pL\pN])/iu', $question));
        if ($user->hasRole(User::ROLE_BARANGAY) && ($mentioned->contains(fn ($area) => $area->id !== $user->barangay_id)
            || preg_match('/\b(?:all barangays|municipal|municipality|tibuok|tanang barangay)\b/iu', $question))) {
            return $this->answer(AssistantLanguage::text('You can only view reports for your assigned barangay.'), 'restricted');
        }
        if ($mentioned->count() > 1) {
            return $this->answer($ceb ? 'Usa lang ka barangay matag pangutana, o pangayo og municipal summary.'
                : 'Ask about one barangay at a time, or request a municipal summary.', 'clarification');
        }
        // Do not quietly drop an unknown location or unsupported time/private filter.
        preg_match_all('/\b(?:in|from|sa|barangay|brgy)\s+([\pL][\pL .-]*)/iu', $question, $places);
        foreach ($places[1] as $place) {
            if (preg_match('/^(?:among|akong|atong|our|my|the|all barangays|whole municipality|mga|ilang|inyong|barangay|flood|flodd|baha|landslide|land slide|storm|hazard|high|low|moderate|very|Tomas Oppus)\b/iu', $place)) continue;
            if (! $areas->contains(fn ($area) => preg_match('/^'.preg_quote($area->name, '/').'(?![\pL\pN])/iu', $place))) {
                return $this->answer($ceb ? 'Wala mailhi ang maong lugar. Gamita ang registered Tomas Oppus barangay name.'
                    : 'That place was not recognized. Use a registered Tomas Oppus barangay name.', 'clarification');
            }
        }
        if (preg_match('/\b(?:today|tonight|tomorrow|now|live|karong|karon|ugma|next|last|this month|last month|january|february|march|april|may|june|july|august|september|october|november|december|addresses|phone|names|ngalan|pwd|senior)\b|\b\d{4}\b|#\d+/iu', $question)) {
            return $this->answer($ceb
                ? 'Saved household coordinates ug supplied susceptibility layers lamang ang ma-compare dinhi. Dili kini live o historical hazard forecast, ug walay individual resident lookup. Pangutana bahin sa household map overlaps sa usa ka barangay.'
                : 'This comparison uses saved household coordinates and supplied susceptibility layers. It does not provide live or historical hazard forecasts or individual resident lookups. Ask about household map overlaps for one barangay.', 'clarification');
        }

        $area = $user->hasRole(User::ROLE_BARANGAY) ? $areas->firstWhere('id', $user->barangay_id) : $mentioned->first();
        $scope = $area ? 'Barangay '.$area->name : Barangay::MUNICIPALITY;
        $selected = [];
        if (preg_match('/\b(?:flood(?:ing)?|flodd|baha)\b/iu', $question)) $selected['flood'] = $ceb ? 'Baha' : 'Flood';
        if (preg_match('/\b(?:landslide|land slide)\b/iu', $question)) $selected['landslide'] = 'Landslide';
        if (preg_match('/\bstorm[ -]surge\b/iu', $question)) $selected['storm-surge'] = 'Storm surge';
        $levelFilter = [];
        if (preg_match('/\b(?:very high|taas kaayo)\b/iu', $question)) $levelFilter[] = 'Very high';
        $remaining = preg_replace('/\b(?:very high|taas kaayo)\b/iu', '', $question);
        if (preg_match('/\b(?:high|taas)\b/iu', $remaining)) $levelFilter = [...$levelFilter, 'High', 'Very high'];
        if (preg_match('/\b(?:moderate|kasarangan)\b/iu', $remaining)) $levelFilter[] = 'Moderate';
        if (preg_match('/\b(?:low|ubos)\b/iu', $remaining)) $levelFilter[] = 'Low';
        $levelFilter = array_values(array_unique($levelFilter));
        if ($levelFilter && isset($selected['storm-surge'])) {
            return $this->answer($ceb ? 'Walay susceptibility level nga gihatag sa supplied storm-surge layer. Pangutana nga walay high/low filter.'
                : 'The supplied storm-surge layer does not specify susceptibility levels. Ask without a high/low filter.', 'clarification');
        }
        $polygons = [];
        try {
            foreach ($selected as $layer => $label) {
                foreach ($this->layers->read($layer)['features'] as $feature) {
                    $geometry = $feature['geometry'] ?? [];
                    $parts = match ($geometry['type'] ?? '') {
                        'Polygon' => [$geometry['coordinates']], 'MultiPolygon' => $geometry['coordinates'], default => [],
                    };
                    $level = $this->level($feature['properties'] ?? [], $layer);
                    if ($levelFilter && ! in_array($level, $levelFilter, true)) continue;
                    foreach ($parts as $rings) {
                        if (empty($rings[0])) continue;
                        $xs = array_column($rings[0], 0);
                        $ys = array_column($rings[0], 1);
                        $polygons[] = ['layer' => $layer, 'level' => $level,
                            'bounds' => [min($xs), min($ys), max($xs), max($ys)],
                            'geometry' => ['type' => 'Polygon', 'coordinates' => $rings]];
                    }
                }
            }
        } catch (\Throwable) {
            return $this->answer($ceb ? 'Dili mabasa ang hazard layer karon. Ablihi ang GIS map o sulayi pag-usab; dili ko makakumpirma sa household overlaps samtang unavailable ang layer.'
                : 'A hazard layer is unavailable. Open the GIS map or try again; household overlaps cannot be confirmed while the layer is unavailable.', 'unavailable');
        }

        $query = Household::query()->when($area, fn ($query) => $query->where('barangay_id', $area->id));
        $total = (clone $query)->count();
        $mapped = 0;
        $matched = 0;
        $rows = [];
        $counts = array_fill_keys(array_keys($selected), 0);
        foreach ($query->with('barangay:id,name')->orderBy('barangay_id')->orderBy('household_number')->lazy(200) as $household) {
            if ($household->latitude === null || $household->longitude === null) continue;
            $lat = (float) $household->latitude;
            $lng = (float) $household->longitude;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) continue;
            $mapped++;
            $overlaps = [];
            foreach ($polygons as $polygon) {
                [$west, $south, $east, $north] = $polygon['bounds'];
                if ($lng < $west - 1e-10 || $lng > $east + 1e-10 || $lat < $south - 1e-10 || $lat > $north + 1e-10) continue;
                if ($this->layers->containsGeometry($polygon['geometry'], $lat, $lng)) $overlaps[$polygon['layer']][$polygon['level']] = true;
            }
            if (! $overlaps) continue;
            $matched++;
            foreach (array_keys($overlaps) as $layer) $counts[$layer]++;
            if (count($rows) < 50) $rows[] = ['household_number' => $household->household_number,
                'barangay' => $household->barangay->name, 'overlaps' => array_map('array_keys', $overlaps)];
        }
        $lines = [$scope, $ceb ? 'Household overlaps sa supplied hazard map:' : 'Household overlaps with the supplied hazard map:'];
        foreach ($selected as $layer => $label) $lines[] = $label.': '.number_format($counts[$layer]);
        $lines[] = ($ceb ? 'Nasusi nga households nga adunay valid coordinates: ' : 'Households checked with valid coordinates: ').number_format($mapped);
        $lines[] = ($ceb ? 'Households nga walay valid coordinates: ' : 'Households without valid coordinates: ').number_format($total - $mapped);
        if ($levelFilter) $lines[] = ($ceb ? 'Gipiling susceptibility levels: ' : 'Selected susceptibility levels: ').implode(', ', $levelFilter);
        foreach ($rows as $index => $row) {
            $labels = [];
            foreach ($row['overlaps'] as $layer => $levels) $labels[] = $selected[$layer].' ('.implode(', ', array_map(fn ($level) => $this->label($level, $ceb), $levels)).')';
            $lines[] = ($index + 1).'. Household '.$row['household_number'].' — '.$row['barangay'].': '.implode('; ', $labels);
        }
        if ($matched > count($rows)) $lines[] = $ceb ? 'Gipakita ang unang 50 ka matching households. Ablihi ang GIS map aron susihon ang uban.' : 'Showing the first 50 matching households. Open the GIS map to inspect the rest.';
        if (! $matched) $lines[] = $ceb ? 'Walay matching household point sa gipiling supplied polygons. Dili kini pamatuod nga luwas ang lugar.' : 'No household points matched the selected supplied polygons. This does not establish that the area is safe.';
        $lines[] = $ceb ? 'Base sa saved household points ug supplied Tomas Oppus susceptibility layers lamang. Dili kini live alert, forecast o site assessment. Ang walay coordinates o overlap wala makumpirmang luwas. I-verify ang kondisyon ug plano uban sa LGU.'
            : 'Based only on saved household points and supplied Tomas Oppus susceptibility layers. This is not a live alert, forecast or site assessment. Missing coordinates or no overlap do not confirm safety. Verify site conditions and plans with the LGU.';
        $answer = $this->answer(implode("\n\n", $lines), 'database_summary');
        $answer['facts'] = ['scope' => $scope, 'mapped_households' => $mapped, 'unmapped_households' => $total - $mapped,
            'matching_households' => $matched, 'hazard_counts' => $counts, 'households' => $rows,
            'basis' => 'Saved household points intersecting supplied susceptibility polygons; not a live alert or safety determination.'];
        $answer['actions'] = [['label' => AssistantLanguage::text('Open relevant page'), 'url' => route('spatial.index', $area ? ['barangay_id' => $area->id] : [])]];
        return $answer;
    }

    private function level(array $properties, string $layer): string
    {
        if ($layer === 'storm-surge') return 'Level not specified';
        $value = strtoupper((string) ($properties[$layer === 'flood' ? 'FloodSusc' : 'LndslideSu'] ?? ''));
        return match (true) {
            $value === 'VHL', str_contains($value, 'VERY HIGH') => 'Very high',
            in_array($value, ['HF', 'HL']), str_contains($value, 'HIGH') => 'High',
            in_array($value, ['MF', 'ML']), str_contains($value, 'MOD') => 'Moderate',
            in_array($value, ['LF', 'LL']), str_contains($value, 'LOW') => 'Low', default => 'Unclassified',
        };
    }

    private function label(string $level, bool $ceb): string
    {
        return $ceb ? (['Very high' => 'Taas kaayo', 'High' => 'Taas', 'Moderate' => 'Kasarangan', 'Low' => 'Ubos',
            'Unclassified' => 'Walay classification', 'Level not specified' => 'Walay gihatag nga level'][$level] ?? $level) : $level;
    }

    private function answer(string $reply, string $mode): array
    {
        return ['reply' => $reply, 'mode' => $mode, 'scope' => AssistantLanguage::text('RBIM system only'),
            'actions' => [], 'suggestions' => [], 'needs_clarification' => $mode === 'clarification'];
    }
}
