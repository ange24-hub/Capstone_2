<?php

namespace App\Http\Controllers;

use App\Services\RbimAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __invoke(Request $request, RbimAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($assistant->respond(
            $request->user(),
            trim($validated['message'])
        ));
    }
}
