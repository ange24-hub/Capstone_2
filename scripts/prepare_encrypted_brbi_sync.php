<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\{BrbiArchiveImport, DeploymentBrbiSync};

$recipientPath = storage_path('app/deployment-data-sync/online-public-key.json');
$recipient = json_decode(file_get_contents($recipientPath), true, flags:JSON_THROW_ON_ERROR);
$source = app(BrbiArchiveImport::class)->plan(storage_path('app/import-sources/brbi-20261108'), true);
$source['release'] = DeploymentBrbiSync::RELEASE;
$source['source_sha256'] = array_combine(array_map('basename', array_keys($source['source_hashes'])), array_values($source['source_hashes']));
$source['source_hashes'] = [];
// Never transfer laptop-local database IDs or machine paths; resolve barangays on the recipient.
foreach ($source['areas'] as &$area) unset($area['barangay_id']);
unset($area);
$envelope = DeploymentBrbiSync::encrypt($source, $recipient);
$destination = resource_path(DeploymentBrbiSync::ENVELOPE);
if (! is_dir(dirname($destination))) mkdir(dirname($destination), 0755, true);
if (file_put_contents($destination, json_encode($envelope, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot save encrypted transfer.');
}
echo json_encode(['release'=>$source['release'], 'ciphertext_sha256'=>hash_file('sha256', $destination),
    'recipient_key_sha256'=>$recipient['key_sha256'], 'barangays'=>count($source['areas']),
    'source_residents'=>array_sum(array_map(fn($a)=>count($a['registry']['residents']??[]),$source['areas'])),
    'source_deceased'=>array_sum(array_map(fn($a)=>count($a['registry']['deceased']??[]),$source['areas'])),
    'source_new_history'=>array_sum(array_map(fn($a)=>count($a['new_rows']),$source['areas'])),
    'encrypted_bytes'=>filesize($destination)], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
