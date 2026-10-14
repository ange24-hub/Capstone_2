<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\User;

class AssistantScopeGuard
{
    public function respond(User $user, string $question): ?array
    {
        // These checks run before model interpretation and also protect offline chat.
        if (preg_match('/\b(weather|recipe|recipes|horoscope|astrology|quantum|crypto|bitcoin|stock prices?|exchange rates?|medical advice|diagnose|diagnosis|prescribe|dosage)\b|\b(?:write|compose)\b.*\b(?:poem|song|story|code|program)\b|\b(?:capital of|president of|tell me a joke)\b/iu', $question)
            || preg_match('/\b(?:ignore|override|forget|bypass)\b.{0,60}\b(?:rules|restrictions|instructions|permissions|system|scope)\b|\b(?:system prompt|developer instructions|app_key|db_password|mail_password|private key)\b/iu', $question)) {
            return $this->answer($user, 'outside_system', 'I can only help with the RBIM system—accounts, document requests, approvals, registry records, RBI forms, migration monitoring, and the features permitted for your role. I can’t answer topics outside this system.');
        }

        $guidance = ! preg_match('/\bhow (?:many|much)\b/iu', $question)
            && preg_match('/\b(?:how|unsaon|explain|pasabut|guide|instructions)\b/iu', $question);
        if (! $guidance && preg_match('/^(?:(?:please|palihog|palihug|can you|could you|will you)\s+)*(?:approve|reject|delete|remove|edit|update|change|mark|submit|pay|register|i[- ]?(?:approve|delete|update)|tangtanga|usba|usabi)\b/iu', trim($question))) {
            return $this->answer($user, 'read_only', 'Chat can explain records and guide you to the correct page, but it cannot change passwords, edit records, approve requests, or make payments. Use the authorized system form.', false);
        }

        // Exact reviewed FAQ questions are instructions, never database lookups.
        // Do not mistake "types of documents" or "in the system" for place names.
        if (app(AssistantKnowledgeBase::class)->questionId($question) !== null) return null;

        $records = preg_match('/\b(?:population|populasyon|residents?|inhabitants?|people|tawo|nagpuyo|famil(?:y|ies)|pamilya|households?|panimalay|balay|males?|females?|lalaki|babaye|seniors?|pwd|children|adults?|ages?|edad|migration|migrasyon|pagbalhin|balhin|movements?|approvals?|accounts?|registrations?|rbi|forms?|documents?|requests?|payment|papeles)\b/iu', $question);
        if (! $records || $guidance) return null;

        if (preg_match('/\b(?:population|populasyon)\b/iu', $question)
            && preg_match('/\b(?:migration|migrasyon|pagbalhin|movement)\b/iu', $question)) {
            return $this->answer($user, 'unsupported_filter', 'Please ask one question at a time so I can use the correct records and reporting period.', true);
        }
        if (preg_match('/\b(?:documents?|requests?|payment|approvals?|rbi)\b/iu', $question)
            && preg_match('/\b(?:19|20)\d{2}\b|\b(today|yesterday|this month|last month|this year|last year|karong bulana|miaging bulan|niaging bulan|january|february|march|april|may|june|july|august|september|october|november|december)\b/iu', $question)) {
            return $this->answer($user, 'unsupported_filter', 'This chat summary covers all recorded dates and cannot apply that time filter. Use the authorized records page for date-specific details.', true);
        }

        if (preg_match('/\b(?:aged?|ages|edad|under|over|below|above|younger than|older than)\s+\d|\b\d+\s*(?:-|–|to|ug|and)\s*\d+\s*(?:years?|tuig)|\b\d+\s*(?:years? old|anyos)\b/iu', $question)
            || preg_match('/\b(?:19|20)\d{2}-\d{2}-\d{2}\b|\b(?:january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec)\s+(?:[0-2]?\d|3[01])(?:st|nd|rd|th)?\b/iu', $question)
            || preg_match('/\b[A-Z][A-Z\d]{1,12}-[A-Z\d-]*\d[A-Z\d-]*\b|#\d+/u', $question)
            || (preg_match('/\b(?:names?|ngalan|pangalan|kinsa)\b/iu', $question) && ! AssistantPopulationNames::isRequest($question))
            || preg_match('/\b(?:addresses?|phone numbers?|contact numbers?|individual records?|named person|other residents?|someone else|another person)\b/iu', $question)) {
            return $this->answer($user, 'unsupported_filter', 'That detailed lookup is not supported in chat. I can show standard age groups, population summaries, monthly or annual migration, and your permitted document-status summaries. Please use the authorized records page for individual records or a specific request.', true);
        }

        // Do not silently replace an unrecognized place with the user's barangay.
        preg_match_all('/\b(?:in|of|from|sa)\s+([\pL][\pL .’\x{0027}-]*)/iu', $question, $matches);
        $names = null;
        foreach ($matches[1] as $candidate) {
            $candidate = preg_replace('/^(?:barangay|brgy\.?)\s+/iu', '', trim($candidate));
            if (preg_match('/^(?:our|my|your|the whole municipality|whole municipality|the municipality|this municipality|all barangays|among|akong|inyong|atong|ilang|mga|ang|tanang|diri|dinhi|municipal|recorded|registered|available|families|households|residents|people|population|migration|payment|document|pending|ready|pwd|persons?|seniors?|sc|tigulang|tiguwang|children|adults|latest|recent)\b/iu', $candidate)
                || preg_match('/^(?:the\s+)?(?:this|last|current|previous|latest|recent|miaging|niaging|karong|january|february|march|april|may|june|july|august|september|october|november|december)\b/iu', $candidate)
                || preg_match('/^(?:barangay|municipality)\s*[?!.]*$/iu', $candidate)) continue;
            $names ??= Barangay::pluck('name')->push(Barangay::MUNICIPALITY);
            if (! $names->contains(fn ($name) => preg_match('/^'.preg_quote($name, '/').'(?![\pL\pN])/iu', $candidate))) {
                return $this->answer($user, 'unknown_area', 'I could not identify that place within this system. Please use a registered Tomas Oppus barangay, your assigned barangay, or a municipal summary.', true);
            }
        }

        $names ??= Barangay::pluck('name');
        $mentioned = $names->filter(fn ($name) => $name !== Barangay::MUNICIPALITY
            && preg_match('/(?<![\pL\pN])'.preg_quote($name, '/').'(?![\pL\pN])/iu', $question));
        if ($user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_RESIDENT])
            && $mentioned->contains(fn ($name) => $name !== $user->barangay?->name)) {
            return $this->answer($user, 'assigned_barangay_only', 'You can only view reports for your assigned barangay.');
        }
        $report = preg_match('/\b(?:population|populasyon|migration|migrasyon|pagbalhin|balhin|movements?|famil(?:y|ies)|pamilya|households?|residents?|male|female|seniors?|pwd|children|adults?)\b/iu', $question)
            && ! preg_match('/\b(?:documents?|requests?|approvals?|rbi|forms?|payment)\b/iu', $question);
        if ($user->hasRole(User::ROLE_MUNICIPAL_LGU) && $mentioned->isNotEmpty() && ! $report) {
            return $this->answer($user, 'unsupported_filter', 'That barangay filter is not supported for this chat summary. Use the authorized records page, or ask for a population or migration report for that barangay.', true);
        }

        return null;
    }

    private function answer(User $user, string $reason, string $reply, bool $clarify = false): array
    {
        return [
            'reply' => AssistantLanguage::text($reply),
            'scope' => AssistantLanguage::text('RBIM system only'),
            'blocked_reason' => $reason,
            'needs_clarification' => $clarify,
            'actions' => [],
            'suggestions' => array_slice(AssistantGuide::questions($user), 0, 3),
        ];
    }
}
