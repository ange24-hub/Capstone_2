<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DeploymentBrbiSync;

$body=file_get_contents(storage_path('app/'.DeploymentBrbiSync::ENVELOPE));
if (!hash_equals(DeploymentBrbiSync::EXPECTED_SHA256,hash('sha256',$body))) throw new RuntimeException('Unapproved transfer payload.');
$environment=Dotenv\Dotenv::parse(file_get_contents(base_path('.env.dokploy')));
$timestamp=(string)time();
$signature=DeploymentBrbiSync::transportSignature($body,$timestamp,$environment['APP_KEY']??'');
if (!in_array('--send',$argv,true)) {
    echo json_encode(['mode'=>'dry_run','destination'=>'https://'.DeploymentBrbiSync::HOST,
        'envelope_sha256'=>hash('sha256',$body),'bytes'=>strlen($body),'transport'=>'direct-private-https'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$curl=curl_init('https://'.DeploymentBrbiSync::HOST.'/api/deployment-data-sync/'.DeploymentBrbiSync::RELEASE.'/private-transfer');
curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>60,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json',
        'X-Brbi-Timestamp: '.$timestamp,'X-Brbi-Signature: '.$signature]]);
$response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
if ($response===false) throw new RuntimeException('Private HTTPS transfer failed (cURL code '.curl_errno($curl).').');
curl_close($curl);
if ($status!==202) {echo json_encode(['http'=>$status,'state'=>'transfer_not_accepted'],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
$report=json_decode($response,true,flags:JSON_THROW_ON_ERROR);
if (!in_array($report['state']??null,['staged','completed'],true) || ($report['envelope_sha256']??null)!==hash('sha256',$body)) {
    throw new RuntimeException('Unexpected private transfer acknowledgement.');
}
file_put_contents(storage_path('logs/brbi-private-transfer-acknowledgement.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['http'=>$status,'state'=>$report['state'],'envelope_sha256'=>$report['envelope_sha256']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
