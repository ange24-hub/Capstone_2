<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Read-only aggregate audit. The verified forecast engine runs in the Laravel app.
$exitCode = Illuminate\Support\Facades\Artisan::call('migration:forecast-audit');
echo Illuminate\Support\Facades\Artisan::output();
exit($exitCode);
