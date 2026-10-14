<?php

return [
    // Separate from the optional report narrator and concern classifier.
    'enabled' => (bool) env('ASSISTANT_AI_ENABLED', false),
    'url' => env('ASSISTANT_AI_URL', 'http://127.0.0.1:11434'),
    'model' => env('ASSISTANT_AI_MODEL', 'qwen2.5:1.5b'),
    'timeout' => (int) env('ASSISTANT_AI_TIMEOUT', 20),
];
