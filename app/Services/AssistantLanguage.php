<?php

namespace App\Services;

class AssistantLanguage
{
    private static ?array $translations = null;

    private static function translations(): array
    {
        return self::$translations ??= require resource_path('lang/ceb/assistant.php');
    }

    /** Explicit language instructions override the current selection. */
    public function resolve(string $message, string $current): array
    {
        // A question about choosing the assistant's language is not itself a
        // command to strip language names from that question.
        if (preg_match('/\b(?:how|unsaon)\b.*\b(?:assistant|chatbot|language|pinulongan)\b/iu', $message)
            && ! preg_match('/^(?:please\s+)?(?:reply|respond|answer|speak|talk|tubag|motubag)\b/iu', $message)) {
            $language = $current === 'auto' ? $this->detect($message) : $current;
            return ['language' => $language, 'preference' => $current, 'question' => $message, 'changed' => false];
        }
        $pattern = '/(?:\b(?:please\s+)?(?:reply|respond|answer|speak|talk|tubag|motubag|mag|pag|pagka|paki|gamit|use|in|sa|og|ug)\s+(?:(?:in|sa|og|ug|mag|pag|gamit\s+og)\s+)*(?:bisaya|binisaya|cebuano|english|iningles)(?:\s+(?:lang|lamang|palihog|please))?\b|\b(?:bisaya|binisaya|cebuano|english|iningles)(?:\s+(?:lang|lamang|palihog|please))?\b)/iu';
        preg_match_all($pattern, $message, $matches);
        $language = $current === 'auto' ? $this->detect($message) : $current;
        foreach ($matches[0] as $instruction) {
            $language = preg_match('/\b(english|iningles)\b/iu', $instruction) ? 'en' : 'ceb';
        }
        $question = trim(preg_replace($pattern, '', $message), " \t\n\r,;:.!?-");
        // Remove connecting words left by a standalone language request.
        if (preg_match('/^(?:(?:pwede|pwed[e]?\s+ba|can you|could you|please|palihog|lang|nalang|na lang|pud|sad|mo|ka|ko|magtubag|mag|pag)\s*)+$/iu', $question)) $question = '';
        return ['language' => $language, 'preference' => $matches[0] ? $language : $current,
            'question' => $question, 'changed' => ! empty($matches[0])];
    }

    private function detect(string $message): string
    {
        return preg_match('/\b(?:unsa|unsay|unsaon|asa|ngano|pila|kinsa|kanus.a|pwede|dili|wala|walay|maayong|palihog|palihug|nako|akong|among|imong|ug|og|nga)\b/iu', $message) ? 'ceb' : 'en';
    }

    public function canonicalQuestion(string $question): string
    {
        // Suggested questions remain usable after translation, without translating names or user data.
        $translations = self::translations();
        $normalized = mb_strtolower(trim($question, " \t\n\r?!."));
        foreach ($translations as $source => $translated) {
            if (mb_strtolower(trim($translated, " \t\n\r?!.")) === $normalized) return $source;
        }
        return $question;
    }

    public static function text(string $text, array $replace = []): string
    {
        if (app()->getLocale() === 'ceb') {
            $translations = self::translations();
            $text = $translations[$text] ?? $text;
        }
        $placeholders = [];
        foreach ($replace as $name => $value) $placeholders[':'.$name] = (string) $value;
        return strtr($text, $placeholders);
    }
}
