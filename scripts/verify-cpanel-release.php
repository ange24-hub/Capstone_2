<?php

// Smoke-test the staged production dependencies without connecting to a database.
$project = dirname(__DIR__);
$stage = $project.'/storage/app/cpanel-release';
require $stage.'/sbispgm/vendor/autoload.php';
$app = require $stage.'/sbispgm/bootstrap/app.php';
$app->usePublicPath($stage.'/public_html');
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
config([
    'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    'app.debug' => false, 'app.url' => 'http://localhost',
    'session.driver' => 'array', 'cache.default' => 'array',
    'view.compiled' => $project.'/storage/framework/views',
]);

$checks = ['/' => 200, '/login' => 200, '/offline-household' => 200, '/offline' => 200, '/.env' => 404];
foreach ($checks as $path => $expected) {
    $request = \Illuminate\Http\Request::create('http://localhost'.$path);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== $expected) {
        fwrite(STDERR, 'FAIL: '.$path.' returned '.$response->getStatusCode().PHP_EOL);
        exit(1);
    }
    echo 'PASS: release '.$path.' ('.$expected.')'.PHP_EOL;
    $kernel->terminate($request, $response);
}

if (! is_file(public_path('build/manifest.json'))) {
    fwrite(STDERR, 'FAIL: cPanel public path does not resolve the frontend manifest'.PHP_EOL);
    exit(1);
}
echo 'PASS: compiled frontend manifest resolves in public_html'.PHP_EOL;
