<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\User;

class AssistantPopulationNames
{
    private const CATEGORIES = 'pwd|persons? with disabilit(?:y|ies)|senior citizens?|seniors?|sc|tigulang|tiguwang';
    private const NAME_WORDS = 'names?|ngalan|pangalan|kinsa|list|lista|listahan|ilista';
    private const LIMIT = 50;

    public static function isRequest(string $question): bool
    {
        return preg_match('/\b(?:'.self::CATEGORIES.')\b/iu', $question)
            && preg_match('/\b(?:'.self::NAME_WORDS.')\b/iu', $question);
    }

    public function respond(User $user, string $question): ?array
    {
        if (! self::isRequest($question)) return null;
        $boundary = app(AssistantScopeGuard::class)->respond($user, $question);
        if ($boundary !== null) return $boundary;

        if (! $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]) || ! $user->isApproved()
            || ($user->hasRole(User::ROLE_BARANGAY) && ! $user->barangay_id)) {
            return $this->answer('Only authorized, approved barangay and municipal staff can view these resident name lists.', 'staff_only');
        }

        $pwd = (bool) preg_match('/\b(?:pwd|persons? with disabilit(?:y|ies))\b/iu', $question);
        $senior = (bool) preg_match('/\b(?:seniors?|senior citizens?|sc|tigulang|tiguwang)\b/iu', $question);
        $barangays = Barangay::orderBy('name')->get(['id', 'name']);
        $mentioned = $barangays->filter(fn ($area) => preg_match('/(?<![\pL\pN])'.preg_quote($area->name, '/').'(?![\pL\pN])/iu', $question));
        if (($pwd && $senior) || $mentioned->count() > 1) {
            return $this->answer('Please request one list (PWD or senior citizens) and one barangay at a time, or explicitly ask for a municipal list.', 'unsupported_filter', true);
        }

        // Only the supported list and scope may be requested. Do not silently
        // drop occupations, dates, status, sex, age or other subset filters.
        $remaining = $question;
        foreach ($barangays->pluck('name')->push(Barangay::MUNICIPALITY) as $name) {
            $remaining = preg_replace('/(?<![\pL\pN])'.preg_quote($name, '/').'(?![\pL\pN])/iu', ' ', $remaining);
        }
        $words = self::CATEGORIES.'|'.self::NAME_WORDS.'|show|give|me|please|the|all|hatag|hatagi|ihatag|ipakita|pakita|palihog|palihug|ko|kog|ako|akog|kanako|nako|ug|og|sa|ang|mga|among|akong|atong|our|my|barangay|brgy|municipal|municipality|whole|tanan|tanang|registered|recorded|people|residents?|population|in|of|from|for|nga|naa|are|who|can|you|could|tell';
        $remaining = preg_replace('/\b(?:'.$words.')\b/iu', ' ', $remaining);
        if (preg_match('/[\pL\pN]/u', $remaining)) {
            return $this->answer('I can list registered PWD or senior citizen names for an authorized area, using the current population report criteria. Additional filters are not supported in chat; please use the report page.', 'unsupported_filter', true);
        }
        if ($user->hasRole(User::ROLE_BARANGAY) && preg_match('/\b(?:municipal|municipality|all barangays|whole municipality)\b/iu', $question)) {
            return $this->answer('You can only view reports for your assigned barangay.', 'assigned_barangay_only');
        }
        $areaId = $user->hasRole(User::ROLE_BARANGAY) ? $user->barangay_id : $mentioned->first()?->id;
        $section = $pwd ? 'pwd' : 'seniors';
        // Reuse exactly the report's marker/age rules. Names never reach Ollama.
        $report = app(PopulationSummary::class)->build($areaId, $section);
        $members = $report['detailGroups']->flatMap(fn ($group) => $group['members'])->values();
        $displayed = $members->take(self::LIMIT);
        $reply = $report['scopeLabel'].AssistantLanguage::text(' — as of ').$report['generatedAt']->format('M d, Y')."\n"
            .AssistantLanguage::text($pwd ? 'Registered names with PWD remarks: :total' : 'Registered senior citizen names: :total', ['total' => $members->count()]);
        if ($displayed->isEmpty()) {
            $reply .= "\n".AssistantLanguage::text('No matching records are encoded for this area. This does not confirm that there are no PWD or senior citizens.');
        } else {
            $reply .= "\n".$displayed->map(fn ($member, $index) => ($index + 1).'. '.(trim($member['name']) !== '' ? $member['name'] : AssistantLanguage::text('Name not encoded'))
                .($areaId === null ? ' ('.$member['barangay'].')' : ''))->implode("\n");
        }
        if ($members->count() > self::LIMIT) {
            $reply .= "\n".AssistantLanguage::text('Showing the first :limit of :total records. Open the report for the full list.', ['limit' => self::LIMIT, 'total' => $members->count()]);
        }
        $reply .= "\n".AssistantLanguage::text($pwd
            ? 'Based only on recognized PWD remarks. Missing remarks do not confirm absence of disability.'
            : 'Senior citizens are identified from valid birth dates (age 60 or above) or recognized senior-citizen remarks.');
        $reply .= "\n".AssistantLanguage::text('Based on available records across all statuses; completeness unverified.');
        $params = array_filter(['barangay_id' => $areaId, 'section' => $section]);

        return ['reply' => $reply, 'mode' => 'database_list', 'scope' => AssistantLanguage::text('RBIM system only'),
            'facts' => ['scope' => $report['scopeLabel'], 'as_of' => $report['generatedAt']->format('Y-m-d'),
                'category' => $section, 'total_records' => $members->count(), 'displayed_records' => $displayed->count()],
            'actions' => [['label' => AssistantLanguage::text('Open full name list'), 'url' => route('reports.population', $params)],
                ['label' => AssistantLanguage::text('Download PDF'), 'url' => route('reports.population.pdf', $params)]],
            'suggestions' => ['Show PWD names', 'Show senior citizen names']];
    }

    private function answer(string $reply, string $reason, bool $clarify = false): array
    {
        return ['reply' => AssistantLanguage::text($reply), 'scope' => AssistantLanguage::text('RBIM system only'),
            'blocked_reason' => $reason, 'needs_clarification' => $clarify, 'actions' => [], 'suggestions' => []];
    }
}
