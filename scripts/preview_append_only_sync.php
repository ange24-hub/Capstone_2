<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source=app(App\Services\BrbiArchiveImport::class)->plan(storage_path('app/import-sources/brbi-20261108'),true);
$plan=app(App\Services\BrbiAppendOnlySync::class)->plan($source);
echo json_encode(['mode'=>'read_only_preview', 'skipped_existing'=>$plan['skipped'], 'identity_review'=>count($plan['review']),
    'barangays'=>app(App\Services\BrbiArchiveImport::class)->summary($plan)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
