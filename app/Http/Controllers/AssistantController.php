<?php

namespace App\Http\Controllers;

use App\Services\RbimAssistant;
use App\Services\AssistantFollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __invoke(Request $request, RbimAssistant $assistant, AssistantFollowUp $followUp): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'previous_question' => ['nullable', 'string', 'max:500'],
        ]);

        $question = $followUp->resolve(trim($validated['message']), $validated['previous_question'] ?? null);
        $answer = $followUp->clarification($question, $validated['previous_question'] ?? null) ?? $assistant->respond(
            $request->user(),
            $question
        );
        $answer['previous_question'] = $followUp->context($answer);
        if ($question !== trim($validated['message'])) $answer['interpreted_question'] = $question;

        return response()->json($answer);
    }
}
