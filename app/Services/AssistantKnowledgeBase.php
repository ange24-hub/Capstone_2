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
            'GIS & migration' => [...range(40, 43), ...range(45, 47)], 'Privacy & limitations' => range(48, 55)];
        $result = [];
        foreach ($groups as $label => $ids) {
            $result[] = ['label' => AssistantLanguage::text($label), 'questions' => array_map(fn ($id) => $entries[$id][0], $ids)];
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
        return $id ?? $aliases[$key] ?? null;
    }

    public function respond(User $user, string $question): ?array
    {
        $id = $this->questionId($question);
        if ($id === null) return null;
        $entry = self::entries()[$id];
        $translations = require resource_path('lang/ceb/assistant_faq.php');
        $reply = app()->getLocale() === 'ceb' ? ($translations[$id] ?? $entry[1]) : $entry[1];

        return ['reply' => $reply, 'mode' => 'system_guidance', 'faq_id' => $id,
            'scope' => AssistantLanguage::text('RBIM system only'), 'actions' => $this->actions($user, $id),
            'suggestions' => [AssistantLanguage::text('What can I ask?')]];
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
                40, 41, 42 => 'spatial.index', 43, 45, 46, 47 => 'migration.dashboard', default => null,
            };
        } elseif ($user->hasRole(User::ROLE_MUNICIPAL_LGU)) {
            $route = match ($id) {
                26, 27, 33, 34, 38, 39 => 'reports.population', 32, 35 => 'dashboard.municipal',
                36, 43, 45, 46, 47 => 'migration.dashboard', 37 => 'municipal.approvals.index',
                40, 41, 42 => 'spatial.index', default => null,
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
