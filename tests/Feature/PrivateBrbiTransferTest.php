<?php

namespace Tests\Feature;

use App\Services\DeploymentBrbiSync;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateBrbiTransferTest extends TestCase
{
    private const PATH='/api/deployment-data-sync/brbi-20261108/private-transfer';
    private string $senderPrivate='';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env']='production';
        config(['app.url'=>'https://'.DeploymentBrbiSync::HOST]);
        $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key,$this->senderPrivate);
        $public=openssl_pkey_get_details($key)['key'];
        $sync=\Mockery::mock(DeploymentBrbiSync::class)->makePartial();
        $sync->shouldReceive('senderPublicKey')->andReturn($public);
        $this->app->instance(DeploymentBrbiSync::class,$sync);
        $this->travelTo(Carbon::parse('2026-11-09T00:35:00Z'));
    }

    private function sendBody(string $body, ?string $signature=null, ?string $timestamp=null, bool $https=true)
    {
        $timestamp ??= (string)now()->timestamp;
        return $this->call('POST',($https?'https://':'http://').DeploymentBrbiSync::HOST.self::PATH,[],[],[],[
            'CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json',
            'HTTP_X_BRBI_TIMESTAMP'=>$timestamp,'HTTP_X_BRBI_SIGNATURE'=>$signature??'',
        ],$body);
    }

    public function test_missing_wrong_or_stale_authentication_cannot_stage_any_packet(): void
    {
        Storage::fake('local');
        $this->sendBody('{}')->assertUnauthorized();
        $this->sendBody('{}',str_repeat('0',64))->assertUnauthorized();
        $old=(string)(now()->timestamp-121);
        $signature=DeploymentBrbiSync::signPrivateTransfer('{}',$old,$this->senderPrivate);
        $this->sendBody('{}',$signature,$old)->assertUnauthorized();
        Storage::disk('local')->assertMissing(DeploymentBrbiSync::ENVELOPE);
    }

    public function test_authenticated_requests_still_cannot_send_unapproved_payloads(): void
    {
        Storage::fake('local');$body='{"not":"the approved packet"}';$timestamp=(string)now()->timestamp;
        $signature=DeploymentBrbiSync::signPrivateTransfer($body,$timestamp,$this->senderPrivate);
        $this->sendBody($body,$signature,$timestamp)->assertStatus(422);
        Storage::disk('local')->assertMissing(DeploymentBrbiSync::ENVELOPE);
    }

    public function test_http_and_expired_transfer_are_rejected(): void
    {
        $this->sendBody('{}',null,null,false)->assertNotFound();
        $this->travelTo(Carbon::parse(DeploymentBrbiSync::TRANSPORT_EXPIRES_AT)->addSecond());
        $this->sendBody('{}')->assertStatus(410);
    }

    public function test_approved_authenticated_packet_is_only_staged_not_imported(): void
    {
        $body='{"fixture":"approved ciphertext"}';$timestamp=(string)now()->timestamp;$hash=hash('sha256',$body);
        $sync=app(DeploymentBrbiSync::class);
        $sync->shouldReceive('expectedEnvelopeSha256')->once()->andReturn($hash);
        $sync->shouldReceive('stagePrivateTransfer')->once()->with($body)->andReturn(['state'=>'staged','envelope_sha256'=>$hash]);
        $this->app->instance(DeploymentBrbiSync::class,$sync);
        $signature=DeploymentBrbiSync::signPrivateTransfer($body,$timestamp,$this->senderPrivate);
        $this->sendBody($body,$signature,$timestamp)->assertStatus(202)->assertJsonPath('state','staged');
    }

    public function test_private_staging_validates_real_encryption_and_retains_packet_only_in_private_storage(): void
    {
        Storage::fake('local');$sync=\Mockery::mock(DeploymentBrbiSync::class)->makePartial();$sync->initialize();
        $source=['release'=>DeploymentBrbiSync::RELEASE,'areas'=>array_fill(0,29,['name'=>'Synthetic Fixture'])];
        $body=json_encode(DeploymentBrbiSync::encrypt($source,$sync->publicMetadata()),JSON_THROW_ON_ERROR);
        $sync->shouldReceive('expectedEnvelopeSha256')->andReturn(hash('sha256',$body));
        $result=$sync->stagePrivateTransfer($body);
        $this->assertSame('staged',$result['state']);
        $this->assertSame($body,Storage::disk('local')->get(DeploymentBrbiSync::ENVELOPE));
        $this->assertSame($source,$sync->decrypt(json_decode($body,true)));
        Storage::disk('local')->assertMissing(DeploymentBrbiSync::DIRECTORY.'/backups');
    }
}
