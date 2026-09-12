<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\User;

class AssistantReportSummary
{
    public function __construct(private PopulationSummary $population, private MigrationSummary $migration, private LocalAiNarrator $narrator) {}

    public function respond(User $user, string $question): ?array
    {
        if (! $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU])) return null;
        $migration = preg_match('/\\b(migration|migrasyon|movement|moved (in|out)|nibalhin|nangbalhin|balhin|trend)\\b/iu', $question);
        $population = preg_match('/\\b(population|populasyon|famil(?:y|ies)|pamilya|households?|panimalay|pwd|senior|sc|male|female|lalaki|babaye|edad|age|inhabitants?|resident count)\\b/iu', $question);
        if (! $migration && ! $population) return null;
        if ($user->hasRole(User::ROLE_BARANGAY) && (! $user->isApproved() || ! $user->barangay_id)) {
            return $this->answer('An approved barangay account with an assigned barangay is required to read reports.');
        }
        $barangays = Barangay::orderBy('name')->get(['id', 'name']);
        $mentioned = $barangays->filter(fn ($area) => preg_match('/(?<![\\pL\\pN])'.preg_quote($area->name, '/').'(?![\\pL\\pN])/iu', $question));
        if ($user->hasRole(User::ROLE_BARANGAY) && $mentioned->contains(fn ($area) => $area->id !== $user->barangay_id)) {
            return $this->answer('You can only view reports for your assigned barangay.');
        }
        if ($mentioned->count() > 1) return $this->answer('Please select one barangay per question, or ask for a municipal summary.');
        if (preg_match('/\\b(?:brgy\\.?|barangay)\\s+([\\pL-]+)/iu', $question, $match) && $mentioned->isEmpty()
            && ! in_array(mb_strtolower($match[1]), ['records','population','report','reports','summary','data','have','has'], true)) {
            return $this->answer('I could not identify that barangay. Please use its full registered name.');
        }
        $areaId = $user->hasRole(User::ROLE_BARANGAY) ? $user->barangay_id : $mentioned->first()?->id;
        preg_match_all('/\\b(?:19|20)\\d{2}\\b/', $question, $years);
        if (count(array_unique($years[0])) > 1) return $this->answer('Please ask about one reporting year at a time.');
        $year = (int) ($years[0][0] ?? now()->year);
        if (! $migration && $year !== now()->year) return $this->answer('Population reports are current database snapshots. Historical population totals are not available from these records.');
        if ($migration && preg_match('/\\b(forecast|predict|prediction|seasonal|next year)\\b/iu', $question)) {
            return $this->answer('Forecasting is not enabled. Recorded migration events alone do not establish reliable future or seasonal trends.');
        }
        if ($migration) {
            $r = $this->migration->build($areaId, $year);
            $facts = ['scope' => $r['scopeLabel'], 'year' => $year, 'in_migration' => $r['in'], 'out_migration' => $r['out'], 'net_change' => $r['net'], 'recorded_events' => $r['total'],
                'basis' => 'Recorded movement events, not unique people. No events recorded does not confirm no actual movement. Completeness unverified. No forecasts.'];
            $reply = $r['scopeLabel'].' — '.$year."\nIn-migration: ".$r['in']."\nOut-migration: ".$r['out']."\nNet change: ".$r['net']."\nRecorded events: ".$r['total']."\nNo recorded events does not confirm zero movement. Reporting completeness is unverified.";
            $route = 'reports.migration';
            $params = array_filter(['barangay_id' => $areaId, 'year' => $year]);
        } else {
            $r = $this->population->build($areaId);
            $facts = ['scope' => $r['scopeLabel'], 'as_of' => $r['generatedAt']->format('Y-m-d'), 'residents' => $r['totalRecords'], 'families' => $r['totalFamilies'], 'records_without_family_number' => $r['recordsWithoutFamily'], 'households' => $r['totalHouseholds'],
                'male' => $r['sex']['Male'], 'female' => $r['sex']['Female'], 'sex_unspecified' => $r['sex']['Not specified / other'], 'age_groups' => $r['ages'], 'seniors' => $r['totalSeniors'], 'pwd' => $r['totalPwd'],
                'basis' => 'Available registry records across all statuses. Families include single-person households. SC uses birth dates or remarks; PWD uses remarks. Missing encoding is not zero population. Not a complete census.'];
            $reply = $r['scopeLabel'].' — as of '.$r['generatedAt']->format('M d, Y')."\nRegistered residents: ".number_format($r['totalRecords'])."\nFamilies: ".number_format($r['totalFamilies'])."\nHouseholds: ".number_format($r['totalHouseholds'])."\nMale: ".number_format($r['sex']['Male'])." | Female: ".number_format($r['sex']['Female'])."\nSenior citizens: ".number_format($r['totalSeniors'])." | PWD markers: ".number_format($r['totalPwd']);
            $reply .= "\nAge groups: ".collect($r['ages'])->map(fn ($count, $label) => $label.': '.number_format($count))->implode('; ');
            $reply .= "\nBased on available records across all statuses; completeness unverified. SC uses birth dates or remarks; PWD uses remarks. Single-person households count as families.";
            if (! $r['totalRecords']) $reply .= "\nNo resident records encoded; this does not mean zero population.";
            if ($r['recordsWithoutFamily']) $reply .= "\nSome records have no family number. The identified family count may be incomplete; eligible single-person households are included.";
            $route = 'reports.population';
            $params = array_filter(['barangay_id' => $areaId]);
        }
        $narrative = $this->narrator->explain($facts);
        if ($narrative) $reply .= "\n\nAI explanation (local model):\n".$narrative;
        $result = $this->answer($reply);
        $result['mode'] = $narrative ? 'local_ai' : 'database_summary';
        $result['facts'] = $facts;
        $result['actions'] = [
            ['label' => 'Open report', 'url' => route($route, $params)],
            ['label' => 'Download PDF', 'url' => route($route.'.pdf', $params)],
        ];
        return $result;
    }

    private function answer(string $reply): array
    {
        return ['reply' => $reply, 'mode' => 'database_summary', 'scope' => 'RBIM system only',
            'suggestions' => ['Pila ka families sa among barangay?', 'Show population summary', 'Summarize migration this year'],
            'actions' => []];
    }
}
