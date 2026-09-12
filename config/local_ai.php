<?php

return [
    'enabled' => (bool) env('LOCAL_AI_ENABLED', false),
    'url' => env('LOCAL_AI_URL', 'http://127.0.0.1:11434'),
    'model' => env('LOCAL_AI_MODEL', 'qwen2.5:1.5b'),
    'timeout' => (int) env('LOCAL_AI_TIMEOUT', 25),
];
