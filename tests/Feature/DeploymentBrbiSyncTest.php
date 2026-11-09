<?php

namespace Tests\Feature;

use App\Services\DeploymentBrbiSync;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeploymentBrbiSyncTest extends TestCase
{
    public function test_recipient_encryption_round_trip_and_ciphertext_contains_no_plaintext_records(): void
    {
        Storage::fake('local');
        $sync=app(DeploymentBrbiSync::class);$sync->initialize();
        $recipient=$sync->publicMetadata();
        $source=['release'=>DeploymentBrbiSync::RELEASE,'areas'=>array_fill(0,29,['name'=>'Sensitive Fixture Name'])];
        $envelope=DeploymentBrbiSync::encrypt($source,$recipient);
        $this->assertSame($source,$sync->decrypt($envelope));
        $this->assertStringNotContainsString('Sensitive Fixture Name',json_encode($envelope));
        $this->assertStringNotContainsString('PRIVATE KEY',json_encode($recipient));
        $sync->initialize();
        $this->assertSame($recipient['key_sha256'],$sync->publicMetadata()['key_sha256']);
        $bytes=base64_decode($envelope['ciphertext']);$bytes[0]=chr(ord($bytes[0])^1);
        $envelope['ciphertext']=base64_encode($bytes);
        $this->expectException(\RuntimeException::class);$sync->decrypt($envelope);
    }

    public function test_wrong_recipient_rejected_and_local_startup_cannot_import(): void
    {
        Storage::fake('local');
        app(DeploymentBrbiSync::class)->run();
        Storage::disk('local')->assertMissing(DeploymentBrbiSync::DIRECTORY.'/private.pem');
        $this->expectException(\RuntimeException::class);
        DeploymentBrbiSync::encrypt([],['recipient_host'=>'another.example']);
    }

    public function test_public_status_only_exposes_public_key_and_aggregate_fields_and_cannot_accept_uploads(): void
    {
        Storage::fake('local');
        $sync=app(DeploymentBrbiSync::class);$sync->initialize();
        Storage::disk('local')->put(DeploymentBrbiSync::DIRECTORY.'/report.json',json_encode([
            'state'=>'completed','private_review_queue'=>[['name'=>'Sensitive Resident']], 'backup_path'=>'private/backup',
            'exception'=>'private credentials', 'existing_records_preserved'=>true]));
        $this->getJson('/api/deployment-data-sync/brbi-20261108')->assertNotFound();
        $this->app['env']='production';config(['app.url'=>'https://'.DeploymentBrbiSync::HOST]);
        $response=$this->getJson('/api/deployment-data-sync/brbi-20261108')->assertOk()
            ->assertJsonPath('state','completed')->assertJsonPath('existing_records_preserved',true)
            ->assertDontSee('Sensitive Resident')->assertDontSee('private credentials')->assertDontSee('private/backup');
        $this->assertEmpty($response->headers->getCookies());
        $this->postJson('/api/deployment-data-sync/brbi-20261108',['rows'=>[]])->assertStatus(405);
        $this->postJson('/api/deployment-data-sync/brbi-20261108/private-transfer',['rows'=>[]])->assertNotFound();
    }
}
