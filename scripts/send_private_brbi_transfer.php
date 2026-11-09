<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DeploymentBrbiSync;

$body=file_get_contents(storage_path('app/'.DeploymentBrbiSync::ENVELOPE));
if (!hash_equals(DeploymentBrbiSync::EXPECTED_SHA256,hash('sha256',$body))) throw new RuntimeException('Unapproved transfer payload.');
$senderPrivateKey=file_get_contents(storage_path('app/deployment-data-sync/sender-private.pem'));
if (!in_array('--send',$argv,true)) {
    echo json_encode(['mode'=>'dry_run','destination'=>'https://'.DeploymentBrbiSync::HOST,
        'envelope_sha256'=>hash('sha256',$body),'bytes'=>strlen($body),'transport'=>'direct-private-https'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$serverDate=null;
$probe=curl_init('https://'.DeploymentBrbiSync::HOST.'/api/deployment-data-sync/'.DeploymentBrbiSync::RELEASE.'?private_transport_probe=1');
curl_setopt_array($probe,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>30,
    CURLOPT_HTTPHEADER=>['Accept: application/json','Cache-Control: no-cache'],
    CURLOPT_HEADERFUNCTION=>function($curl,string $header)use(&$serverDate){
        if(str_starts_with(strtolower($header),'date:')) $serverDate=trim(substr($header,5));
        return strlen($header);
    }]);
$probeBody=curl_exec($probe);$probeStatus=curl_getinfo($probe,CURLINFO_RESPONSE_CODE);curl_close($probe);
if($probeBody===false || $probeStatus!==200) {
    echo json_encode(['state'=>'cannot_verify_server_clock'],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);
}
$recipient=json_decode($probeBody,true,flags:JSON_THROW_ON_ERROR);
$packet=json_decode($body,true,flags:JSON_THROW_ON_ERROR);
if(($recipient['key_sha256']??null)!==$packet['key_sha256'] || ($recipient['expected_envelope_sha256']??null)!==hash('sha256',$body)) {
    throw new RuntimeException('Live recipient changed.');
}
// Use the authenticated receiver's application clock, retaining its short freshness window.
if(($recipient['transport']??null)!=='private-https-signed' || !is_int($recipient['server_timestamp']??null)) {
    throw new RuntimeException('Signed private transfer is not ready.');
}
$timestamp=(string)$recipient['server_timestamp'];
$signature=DeploymentBrbiSync::signPrivateTransfer($body,$timestamp,$senderPrivateKey);
$curl=curl_init('https://'.DeploymentBrbiSync::HOST.'/api/deployment-data-sync/'.DeploymentBrbiSync::RELEASE.'/private-transfer');
curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>60,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json',
        'X-Brbi-Timestamp: '.$timestamp,'X-Brbi-Signature: '.$signature]]);
$response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
if ($response===false) {echo json_encode(['state'=>'tls_or_connection_failed','curl_code'=>curl_errno($curl)],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
curl_close($curl);
if ($status!==202) {echo json_encode(['http'=>$status,'state'=>'transfer_not_accepted'],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
$report=json_decode($response,true,flags:JSON_THROW_ON_ERROR);
if (!in_array($report['state']??null,['staged','completed'],true) || ($report['envelope_sha256']??null)!==hash('sha256',$body)) {
    throw new RuntimeException('Unexpected private transfer acknowledgement.');
}
file_put_contents(storage_path('logs/brbi-private-transfer-acknowledgement.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['http'=>$status,'state'=>$report['state'],'envelope_sha256'=>$report['envelope_sha256']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
