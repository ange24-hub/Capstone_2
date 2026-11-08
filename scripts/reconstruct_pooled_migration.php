<?php
// Aggregate export + private review queue. No source rows or verification flags are changed.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$name = $argv[1] ?? 'pooled-actual-review-20261108';
if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $name)) throw new InvalidArgumentException('Use a simple run name.');
$out = base_path('output/prediction-analysis/'.$name);
$private = storage_path('app/migration-reconstruction/'.$name);
if (file_exists($out) || file_exists($private)) throw new RuntimeException('Use a fresh run name.');
mkdir($out,0775,true); mkdir($private,0775,true);
$tables = ['inhabitants','new_inhabitants','migration_records','barangay_rbi_updates','migration_reporting_periods','rbi_arrival_periods'];
$state = function () use ($tables) {
    $state = [];
    foreach ($tables as $table) {
        $hash = hash_init('sha256'); $count = 0;
        foreach (Illuminate\Support\Facades\DB::table($table)->orderBy('id')->cursor() as $row) {
            hash_update($hash, json_encode($row,JSON_THROW_ON_ERROR)."\n"); $count++;
        }
        $state[$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
    }
    return $state;
};
$before = $state();
$review = app(App\Services\PooledMigrationReconstruction::class)->build();
$after = $state();
if ($before !== $after) throw new RuntimeException('Source tables changed during review; rerun a consistent snapshot.');
$write = fn ($path,$data) => file_put_contents($path,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$write($out.'/reconstructed-source.json',$review['aggregate']);
$write($private.'/review-queue.json',$review['private_review_queue']);
$write($out.'/source-integrity.json',['before'=>$before,'after'=>$after,'source_tables_unchanged'=>true,'classifications_written'=>0,'dates_written'=>0,'official_verifications_written'=>0]);
echo json_encode(['output'=>$out,'private_queue'=>$private.'/review-queue.json',
    'sample_rows'=>count($review['aggregate']['experimental_arrival_samples']),
    'sample_barangays'=>count(array_unique(array_column($review['aggregate']['experimental_arrival_samples'],'barangay_id'))),
    'classified_arrivals'=>$review['aggregate']['existing_classified_arrivals'],
    'review_queue_items'=>$review['aggregate']['review_queue_items'],'source_tables_unchanged'=>true],JSON_PRETTY_PRINT).PHP_EOL;
