<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\User;

class AssistantReportSummary
{
    public function __construct(private PopulationSummary $population, private MigrationSummary $migration) {}

    public function respond(User $user, string $question): ?array
    {
        if (! $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU])) return null;
        $migration = preg_match('/\\b(migration|migrasyon|movement|moved (in|out)|nibalhin|nangbalhin|balhin|trend)\\b/iu', $question);
        $population = preg_match('/\\b(population|populasyon|famil(?:y|ies)|pamilya|households?|panimalay|balay|pwd|seniors?|tigulang|sc|male|female|lalaki|lalake|babaye|edad|ages?|bata|children|adults?|inhabitants?|residente|tawo|resident count)\\b/iu', $question);
        if (! $migration && ! $population) return null;
        if ($user->hasRole(User::ROLE_BARANGAY) && (! $user->isApproved() || ! $user->barangay_id)) {
            return $this->answer(AssistantLanguage::text('An approved barangay account with an assigned barangay is required to read reports.'));
        }
        $barangays = Barangay::orderBy('name')->get(['id', 'name']);
        $mentioned = $barangays->filter(fn ($area) => preg_match('/(?<![\\pL\\pN])'.preg_quote($area->name, '/').'(?![\\pL\\pN])/iu', $question));
        if ($user->hasRole(User::ROLE_BARANGAY) && $mentioned->contains(fn ($area) => $area->id !== $user->barangay_id)) {
            return $this->answer(AssistantLanguage::text('You can only view reports for your assigned barangay.'));
        }
        if ($mentioned->count() > 1) return $this->answer(AssistantLanguage::text('Please select one barangay per question, or ask for a municipal summary.'));
        if (preg_match('/\\b(?:brgy\\.?|barangay)\\s+([\\pL-]+)/iu', $question, $match) && $mentioned->isEmpty()
            && ! in_array(mb_strtolower($match[1]), ['records','population','report','reports','summary','data','have','has','ang','ug','nga','sa'], true)) {
            return $this->answer(AssistantLanguage::text('I could not identify that barangay. Please use its full registered name.'));
        }
        $areaId = $user->hasRole(User::ROLE_BARANGAY) ? $user->barangay_id : $mentioned->first()?->id;
        preg_match_all('/\\b(?:19|20)\\d{2}\\b/', $question, $years);
        if (count(array_unique($years[0])) > 1) return $this->answer(AssistantLanguage::text('Please ask about one reporting year at a time.'));
        $year = (int) ($years[0][0] ?? now()->year);
        $month = null;
        if (preg_match('/\b(last year|miaging tuig)\b/iu', $question) && empty($years[0])) $year--;
        if ($migration) {
            $months = [];
            foreach (['january','february','march','april','may','june','july','august','september','october','november','december'] as $index => $name) {
                if (preg_match('/\b('.$name.'|'.substr($name, 0, 3).')\b/iu', $question)) $months[] = $index + 1;
            }
            if (preg_match('/\b\d{4}-(\d{2})\b/', $question, $date)) $months[] = (int) $date[1];
            if (preg_match('/\b(this month|karong bulana|karong buwan)\b/iu', $question)) $months[] = now()->month;
            if (preg_match('/\b(last month|miaging bulan|niaging bulan)\b/iu', $question)) {
                $previous = now()->startOfMonth()->subMonth();
                $months[] = $previous->month;
                if (empty($years[0])) $year = $previous->year;
            }
            $months = array_values(array_unique($months));
            if (count($months) > 1 || (isset($months[0]) && ($months[0] < 1 || $months[0] > 12))) return $this->answer(AssistantLanguage::text('Please select one valid reporting month per question, for example migration September 2026.'));
            $month = $months[0] ?? null;
        }
        if (! $migration && $year !== now()->year) return $this->answer(AssistantLanguage::text('Population reports are current database snapshots. Historical population totals are not available from these records.'));
        if (! $migration && preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec|last month|this month|miaging bulan|niaging bulan|karong bulana|karong buwan)\b|\b\d{4}-\d{2}\b/iu', $question)) {
            return $this->answer(AssistantLanguage::text('Population reports are current database snapshots. Historical population totals are not available from these records.'));
        }
        if ($migration && preg_match('/\\b(forecast|predict|prediction|seasonal|next year)\\b/iu', $question)) {
            return $this->answer(AssistantLanguage::text('Forecasting is not enabled. Recorded migration events alone do not establish reliable future or seasonal trends.'));
        }
        if ($migration) {
            $r = $this->migration->build($areaId, $year);
            $allMonths = $r['months'];
            if ($month !== null) $r = array_replace($r, $allMonths->firstWhere('month', sprintf('%04d-%02d', $year, $month)));
            $facts = ['scope' => $r['scopeLabel'], 'year' => $year, 'in_migration' => $r['in'], 'out_migration' => $r['out'], 'net_change' => $r['net'], 'recorded_events' => $r['total'],
                'basis' => 'Recorded movement events, not unique people. No events recorded does not confirm no actual movement. Completeness unverified. No forecasts.'];
            $reply = $r['scopeLabel'].' — '.$year.AssistantLanguage::text("\nIn-migration: ").$r['in'].AssistantLanguage::text("\nOut-migration: ").$r['out'].AssistantLanguage::text("\nNet change: ").$r['net'].AssistantLanguage::text("\nRecorded events: ").$r['total'].AssistantLanguage::text("\nNo recorded events does not confirm zero movement. Reporting completeness is unverified.");
            $route = 'reports.migration';
            if ($month !== null) {
                $facts['month'] = sprintf('%04d-%02d', $year, $month);
                $reply = $r['label'].' '.$year.AssistantLanguage::text(" (selected month)\n").$reply;
                $reply .= AssistantLanguage::text("\nThe linked PDF contains the full year with monthly rows.");
            } elseif (preg_match('/\b(monthly|by month|most|highest|peak|pinakadaghan)\b/iu', $question)) {
                $facts['monthly_events'] = $allMonths->values()->all();
                $reply .= AssistantLanguage::text("\n\nMonthly events (in / out):\n").$allMonths->map(fn ($row) => $row['label'].': '.$row['in'].' / '.$row['out'])->implode("\n");
                if ($r['total'] > 0) $reply .= AssistantLanguage::text("\nHighest recorded activity: ").$allMonths->where('total', $allMonths->max('total'))->pluck('label')->implode(', ').AssistantLanguage::text('. This does not establish a seasonal pattern.');
            }
            $params = array_filter(['barangay_id' => $areaId, 'year' => $year]);
        } else {
            $r = $this->population->build($areaId);
            $facts = ['scope' => $r['scopeLabel'], 'as_of' => $r['generatedAt']->format('Y-m-d'), 'residents' => $r['totalRecords'], 'families' => $r['totalFamilies'], 'records_without_family_number' => $r['recordsWithoutFamily'], 'households' => $r['totalHouseholds'],
                'male' => $r['sex']['Male'], 'female' => $r['sex']['Female'], 'sex_unspecified' => $r['sex']['Not specified / other'], 'age_groups' => $r['ages'], 'seniors' => $r['totalSeniors'], 'pwd' => $r['totalPwd'],
                'basis' => 'Available registry records across all statuses. Families include single-person households. SC uses birth dates or remarks; PWD uses remarks. Missing encoding is not zero population. Not a complete census.'];
            $reply = $r['scopeLabel'].AssistantLanguage::text(' — as of ').$r['generatedAt']->format('M d, Y').AssistantLanguage::text("\nRegistered residents: ").number_format($r['totalRecords']).AssistantLanguage::text("\nFamilies: ").number_format($r['totalFamilies']).AssistantLanguage::text("\nHouseholds: ").number_format($r['totalHouseholds']).AssistantLanguage::text("\nMale: ").number_format($r['sex']['Male']).AssistantLanguage::text(" | Female: ").number_format($r['sex']['Female']).AssistantLanguage::text("\nSenior citizens: ").number_format($r['totalSeniors']).AssistantLanguage::text(" | PWD markers: ").number_format($r['totalPwd']);
            $reply .= AssistantLanguage::text("\nAge groups: ").collect($r['ages'])->map(fn ($count, $label) => AssistantLanguage::text($label).': '.number_format($count))->implode('; ');
            if (preg_match('/\b(bata|children)\b/iu', $question)) $reply .= AssistantLanguage::text("\nChildren aged 0-17: ").collect($r['ages'])->take(2)->sum().AssistantLanguage::text('. Records without valid birth dates are excluded from this age count.');
            if (preg_match('/\b(coverage|encoded|walay data|naay data)\b/iu', $question)) {
                $facts['coverage'] = $r['coverage']->map(fn ($row) => ['barangay' => $row['name'], 'encoded_records' => $row['records']])->values()->all();
                $reply .= AssistantLanguage::text("\n\nEncoding coverage:\n").collect($facts['coverage'])->map(fn ($row) => $row['barangay'].': '.$row['encoded_records'].AssistantLanguage::text(' encoded records'))->implode("\n");
            }
            $reply .= AssistantLanguage::text("\nBased on available records across all statuses; completeness unverified. SC uses birth dates or remarks; PWD uses remarks. Single-person households count as families.");
            if (! $r['totalRecords']) $reply .= AssistantLanguage::text("\nNo resident records encoded; this does not mean zero population.");
            if ($r['recordsWithoutFamily']) $reply .= AssistantLanguage::text("\nSome records have no family number. The identified family count may be incomplete; eligible single-person households are included.");
            $route = 'reports.population';
            $params = array_filter(['barangay_id' => $areaId]);
        }
        $focused = $migration ? null : app(AssistantPopulationAnswer::class)->focus($question, $r);
        if ($focused) $reply = $focused['reply'];
        // Final chatbot wording and numbers always come from verified report templates.
        $result = $this->answer($reply);
        $result['facts'] = $facts;
        if ($focused) $result['focused_metrics'] = $focused['metrics'];
        $result['actions'] = [
            ['label' => AssistantLanguage::text('Open report'), 'url' => route($route, $params)],
            ['label' => AssistantLanguage::text('Download PDF'), 'url' => route($route.'.pdf', $params)],
        ];
        return $result;
    }

    private function answer(string $reply): array
    {
        return ['reply' => $reply, 'mode' => 'database_summary', 'scope' => AssistantLanguage::text('RBIM system only'),
            'suggestions' => ['Pila ka families sa among barangay?', AssistantLanguage::text('Show population summary'), AssistantLanguage::text('Summarize migration this year')],
            'actions' => []];
    }
}
