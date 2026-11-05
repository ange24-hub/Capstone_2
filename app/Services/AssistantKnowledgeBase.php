<?php

namespace App\Services;

use App\Models\User;

class AssistantKnowledgeBase
{
    public static function entries(): array
    {
        return require resource_path('assistant/faq.php');
    }

    public static function questions(User $user): array
    {
        return array_merge(...array_column(self::questionGroups($user), 'questions'));
    }

    public static function questionGroups(User $user): array
    {
        $specific = match ($user->role) {
            User::ROLE_BARANGAY => [...range(20, 31), 44],
            User::ROLE_MUNICIPAL_LGU => range(32, 39),
            default => range(8, 19),
        };
        $entries = self::entries();
        $roleLabel = match ($user->role) {
            User::ROLE_BARANGAY => 'Barangay workflows',
            User::ROLE_MUNICIPAL_LGU => 'Municipal workflows',
            default => 'Resident services',
        };
        $groups = ['About RBIM & accounts' => range(1, 7), $roleLabel => $specific,
            'GIS & migration' => [...range(40, 43), ...range(45, 47), 66], 'Privacy & limitations' => range(48, 55),
            'System help & troubleshooting' => range(56, 65)];
        $result = [];
        foreach ($groups as $label => $ids) {
            $result[] = ['label' => AssistantLanguage::text($label), 'questions' => array_map(function ($id) use ($entries) {
                if (app()->getLocale() === 'ceb') {
                    foreach ((self::aliases()[$id] ?? []) as $alias) {
                        if (preg_match('/^(?:unsa|unsay|unsaon|asa|kinsa|pwede|ngano|gi |pareho|dili|wala|na )/iu', $alias)) return $alias;
                    }
                }
                return $entries[$id][0];
            }, $ids)];
        }
        return $result;
    }

    public function questionId(string $question): ?int
    {
        $key = $this->key($question);
        $id = null;
        foreach (self::entries() as $number => [$prompt]) {
            if ($key === $this->key($prompt)) { $id = $number; break; }
        }
        // Reviewed Cebuano aliases for common system questions, not guessed facts.
        $aliases = [
            'unsa ang rbim' => 1, 'unsa ang purpose sa rbim' => 1,
            'unsa ang mga serbisyo sa rbim' => 2, 'kinsa ang makagamit sa rbim' => 3,
            'unsaon pag register sa rbim' => 6, 'unsaon pag register as resident' => 8,
            'unsa ang mga document nga pwede i request' => 11,
            'unsaon pag upload sa proof of payment' => 15,
            'unsaon pag record sa deceased resident' => 23,
            'unsaon pag generate ug monthly population report' => 26,
            'unsa ang purpose sa gis map' => 40, 'unsa ang in migration ug out migration' => 43,
            'makatrack ba ang rbim sa real time location' => 51,
        ];
        if ($id !== null || isset($aliases[$key])) return $id ?? $aliases[$key];
        foreach (self::aliases() as $number => $phrases) {
            foreach ($phrases as $phrase) if ($key === $this->key($phrase)) return $number;
        }
        if (! $this->isWorkflowQuestion($question)) return null;

        // Only match complete concept sets. Extra names, places, dates or filters
        // must not disappear when a paraphrase is mapped to a reviewed answer.
        $tokens = $this->tokens($key);
        if (count($tokens) < 2) return null;
        $matches = [];
        $entries = self::entries();
        foreach (self::aliases() as $number => $phrases) {
            foreach ([...$phrases, $entries[$number][0]] as $phrase) {
                $candidate = $this->tokens($this->key($phrase));
                if ($tokens === $candidate) $matches[$number] = true;
            }
        }
        return count($matches) === 1 ? array_key_first($matches) : null;
    }

    private static function aliases(): array
    {
        static $aliases;
        return $aliases ??= require resource_path('assistant/question-aliases.php');
    }

    public function isWorkflowQuestion(string $question): bool
    {
        return ! preg_match('/\b(?:how (?:many|much)|pila)\b/iu', $question)
            && preg_match('/\b(?:how|unsaon|where|asa|explain|unsay|ngano|why|what (?:is|are|does|should)|unsa (?:ang|ni))\b/iu', $question);
    }

    private function tokens(string $key): array
    {
        $key = preg_replace('/\b(?:walk me through|tell me|can you|could you|bag ong|bag o)\b/u', ' ', $key);
        foreach ([
            'adding|create|creating|encode|encoding|magdugang|pagdugang|dugang' => 'add',
            'residente|inhabitant|inhabitants' => 'resident',
            'updating|update|editing|change|correct|usob|usab' => 'edit',
            'paghimo|himo' => 'add', 'pangita|pagpangita|find|searching' => 'search',
            'mapa' => 'map', 'pag' => '',
        ] as $pattern => $replacement) $key = preg_replace('/\b(?:'.$pattern.')\b/u', $replacement, $key);
        $stop = explode(' ', 'how can could do does did i we you a an the to of for in on with and or is are be my our your this that it please me new existing information details system rbim sa ang ug og nga mga ko ako akong among nako namo nimo imong inyong ba pa pag palihog palihug');
        $tokens = array_values(array_unique(array_diff(preg_split('/\s+/u', trim($key)), $stop, [''])));
        sort($tokens);
        return $tokens;
    }

    public function respond(User $user, string $question): ?array
    {
        $id = $this->questionId($question);
        if ($id === null) {
            if (in_array($this->key($question), ['unsaon pag update', 'unsaon pag edit', 'how do i update', 'how do i edit', 'how to update', 'how to edit'], true)) {
                $bisaya = app()->getLocale() === 'ceb';
                return ['reply' => $bisaya
                    ? 'Unsa ang imong i-update: resident information, imong profile, monthly RBI form, o moved-out record? Pilia ang usa sa mga pangutana sa ubos aron mahatag nako ang sakto nga mga lakang.'
                    : 'What would you like to update: resident information, your profile, a monthly RBI form, or a moved-out record? Choose a question below so I can give the correct steps.',
                    'mode' => 'system_guidance', 'needs_clarification' => true,
                    'scope' => AssistantLanguage::text('RBIM system only'), 'actions' => [],
                    'suggestions' => $bisaya ? ['Unsaon pag update sa resident?', 'Unsaon pag update sa profile?', 'Unsaon pag correct sa moved out record?']
                        : ['How do I update a resident?', 'How do I edit my profile?', 'How do I correct a moved out record?']];
            }
            return null;
        }
        $entry = self::entries()[$id];
        $translations = require resource_path('lang/ceb/assistant_faq.php');
        $reply = app()->getLocale() === 'ceb' ? ($translations[$id] ?? $entry[1]) : $entry[1];

        return ['reply' => $reply, 'mode' => 'system_guidance', 'faq_id' => $id,
            'scope' => AssistantLanguage::text('RBIM system only'), 'actions' => $this->actions($user, $id),
            'suggestions' => [AssistantLanguage::text('What can I ask?')]];
    }

    public function clarifyWorkflow(User $user, string $question): ?array
    {
        if (preg_match('/\bhow (?:many|much)\b/iu', $question)
            || ! preg_match('/\b(?:how|unsaon|where|asa|steps|guide|explain)\b/iu', $question)
            || ! preg_match('/\b(?:residents?|residente|registry|inhabitant|households?|rbi|gis|map|mapa|accounts?|profile|password|documents?|certificate|payment|concerns?)\b/iu', $question)) return null;
        $editingRegistry = preg_match('/\b(?:add|edit|update|delete|remove|encode|record|usob|usab|dugang)\b/iu', $question)
            && preg_match('/\b(?:residents?|residente|registry|inhabitant|households?|rbi)\b/iu', $question);
        if (! $editingRegistry && app(AssistantGuide::class)->respond($user, $question) !== null) return null;

        $ids = match (true) {
            (bool) preg_match('/\b(?:residents?|residente|registry|inhabitant)\b/iu', $question) => [20, 22, 44],
            (bool) preg_match('/\b(?:households?|gis|map|mapa)\b/iu', $question) => [21, 41, 66],
            (bool) preg_match('/\b(?:account|profile|password)\b/iu', $question) => [6, 7, 17],
            (bool) preg_match('/\brbi\b/iu', $question) => [20, 25, 61], default => [10, 14, 18],
        };
        $entries = self::entries();
        $ceb = app()->getLocale() === 'ceb';
        $suggestions = array_map(function ($id) use ($entries, $ceb) {
            if ($ceb) foreach (self::aliases()[$id] as $alias) {
                if (preg_match('/^(?:unsaon|asa)/iu', $alias)) return $alias;
            }
            return $entries[$id][0];
        }, $ids);
        return ['reply' => $ceb
            ? 'Aron mahatag ang sakto nga mga lakang, unsa nga page ug field ang imong pasabot? Pilia ang pinakaduol nga pangutana sa ubos. Dili nako usbon o ihapon ang records isip tubag sa usa ka workflow question.'
            : 'Which page and field do you mean? Choose the closest question below so I can give the correct steps. A workflow question does not change records or check record totals.',
            'mode' => 'system_guidance', 'needs_clarification' => true, 'scope' => AssistantLanguage::text('RBIM system only'),
            'actions' => [], 'suggestions' => $suggestions];
    }

    private function key(string $question): string
    {
        $question = app(AssistantQuestionNormalizer::class)->normalize(html_entity_decode($question, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $question = preg_replace('/[\x{2019}\x{0027}]/u', '', mb_strtolower($question));
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $question));
    }

    private function actions(User $user, int $id): array
    {
        if (! $user->isApproved()) return [];
        if ($user->hasRole(User::ROLE_BARANGAY) && $user->barangay_id) {
            $route = match ($id) {
                20 => 'barangay.rbi-updates.residents', 21, 22 => 'barangay.registry.active',
                23 => 'barangay.rbi-updates.deceased', 24, 25 => 'barangay.rbi-updates.history',
                26, 27 => 'reports.population', 28 => 'barangay.resident-approvals.index',
                29, 30 => 'barangay.document-requests.index', 31 => 'concerns.index', 44 => 'barangay.registry.moved-out',
                40, 41, 42, 62, 66 => 'spatial.index', 43, 45, 46, 47 => 'migration.dashboard',
                56, 57 => 'barangay.registry.moved-out', 59, 60 => 'offline.household', 61 => 'barangay.rbi-updates.history',
                63 => 'reports.population', default => null,
            };
        } elseif ($user->hasRole(User::ROLE_MUNICIPAL_LGU)) {
            $route = match ($id) {
                26, 27, 33, 34, 38, 39 => 'reports.population', 32, 35 => 'dashboard.municipal',
                36, 43, 45, 46, 47 => 'migration.dashboard', 37 => 'municipal.approvals.index',
                40, 41, 42, 62, 66 => 'spatial.index', 63 => 'reports.population', default => null,
            };
        } elseif ($user->hasRole(User::ROLE_RESIDENT) && $user->barangay_id) {
            $route = match ($id) {
                10, 11 => 'resident.document-requests.create', 12, 13, 14, 15, 16, 55 => 'resident.document-requests.index',
                18 => 'concerns.create', 19 => 'concerns.index', default => null,
            };
        } else { $route = null; }
        return $route ? [['label' => AssistantLanguage::text('Open relevant page'), 'url' => route($route)]] : [];
    }
}
