<?php

namespace App\Http\Controllers;

use App\Services\RbimAssistant;
use App\Services\AssistantFollowUp;
use App\Services\AssistantLanguage;
use App\Services\AssistantIntentInterpreter;
use App\Services\AssistantQuestionNormalizer;
use App\Services\AssistantScopeGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __invoke(Request $request, RbimAssistant $assistant, AssistantFollowUp $followUp, AssistantLanguage $languages, AssistantIntentInterpreter $interpreter, AssistantQuestionNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'previous_question' => ['nullable', 'string', 'max:500'],
            'language' => ['nullable', 'in:en,ceb'],
        ]);

        $key = 'assistant.language.'.$request->user()->id;
        $selection = $languages->resolve(trim($validated['message']), $validated['language'] ?? $request->session()->get($key, 'en'));
        $request->session()->put($key, $selection['language']);
        $locale = app()->getLocale();
        app()->setLocale($selection['language']);
        try {
            if ($selection['changed'] && $selection['question'] === '') {
                $answer = ['reply' => $selection['language'] === 'ceb'
                    ? 'Sige, mag-Bisaya ko sa akong mga tubag. Unsa imong pangutana bahin sa RBIM?'
                    : 'Sure, I will answer in English. What would you like to ask about RBIM?',
                    'scope' => AssistantLanguage::text('RBIM system only'), 'actions' => [], 'suggestions' => [],
                    'previous_question' => $validated['previous_question'] ?? null];
            } else {
                $message = $normalizer->normalize($languages->canonicalQuestion($selection['question']));
                $question = $followUp->resolve($message, $validated['previous_question'] ?? null);
                $answer = app(AssistantScopeGuard::class)->respond($request->user(), $question)
                    ?? app(\App\Services\AssistantPopulationNames::class)->respond($request->user(), $question)
                    ?? app(\App\Services\AssistantKnowledgeBase::class)->respond($request->user(), $question)
                    ?? $followUp->clarification($question, $validated['previous_question'] ?? null);
                if ($answer === null) {
                    $interpretation = $interpreter->interpret($request->user(), $question);
                    if (isset($interpretation['question'])) $question = $interpretation['question'];
                    $answer = $interpretation['answer'] ?? $assistant->respond($request->user(), $question);
                    if (config('assistant_ai.enabled') && $interpretation === null) {
                        $answer['notice'] = AssistantLanguage::text('AI question understanding is temporarily unavailable. Using the built-in assistant; try a suggested question if needed.');
                    }
                    if (($answer['needs_clarification'] ?? false) && config('assistant_ai.enabled')) {
                        $answer = array_replace($answer, $interpreter->clarification($request->user()));
                    }
                }
                $answer['previous_question'] = $followUp->context($answer)
                    ?? (($answer['needs_clarification'] ?? false) ? ($validated['previous_question'] ?? null) : null);
                if ($question !== $message) $answer['interpreted_question'] = $question;
            }
            $answer['language'] = $selection['language'];
            $answer['suggestions'] = array_map([AssistantLanguage::class, 'text'], $answer['suggestions'] ?? []);
            foreach ($answer['actions'] ?? [] as $index => $action) {
                $answer['actions'][$index]['label'] = AssistantLanguage::text($action['label']);
            }
        } finally {
            app()->setLocale($locale);
        }

        return response()->json($answer);
    }
}
