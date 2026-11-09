<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/** One recipient and one approved encrypted payload, transferred directly to private server storage. */
class DeploymentBrbiSync
{
    public const HOST = 'sb-ispgm.bsit4bdevs.tech';
    public const RELEASE = 'brbi-20261108';
    public const DIRECTORY = 'deployment-data-sync';
    public const ENVELOPE = 'deployment-data-sync/brbi-20261108.encrypted.json';
    public const EXPECTED_SHA256 = 'd9345b5351e7bb7f04e0fb9934396f2e787048d71c008a3c265bc184c279c545';
    public const TRANSPORT_EXPIRES_AT = '2026-11-09T02:30:00+00:00';

    public function expectedEnvelopeSha256(): string
    {
        return self::EXPECTED_SHA256;
    }

    public function initialize(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);
        $lock = fopen($disk->path(self::DIRECTORY.'/key.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) throw new \RuntimeException('Key lock unavailable.');
        try {
            if ($disk->exists(self::DIRECTORY.'/private.pem')) return;
            $key = openssl_pkey_new(['private_key_bits'=>3072, 'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
            if (! $key || ! openssl_pkey_export($key, $private)) throw new \RuntimeException('Recipient key generation failed.');
            $public = openssl_pkey_get_details($key)['key'];
            if (! $disk->put(self::DIRECTORY.'/private.pem', $private) || ! $disk->put(self::DIRECTORY.'/public.pem', $public)) {
                throw new \RuntimeException('Private key storage failed.');
            }
            @chmod($disk->path(self::DIRECTORY), 0700);
            @chmod($disk->path(self::DIRECTORY.'/private.pem'), 0600);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function publicMetadata(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::DIRECTORY.'/public.pem')) return ['state'=>'not_ready'];
        $public = $disk->get(self::DIRECTORY.'/public.pem');
        $report = $disk->exists(self::DIRECTORY.'/report.json')
            ? json_decode($disk->get(self::DIRECTORY.'/report.json'), true, flags:JSON_THROW_ON_ERROR) : ['state'=>'ready'];
        // This fixed whitelist is the only public output: no rows, backup paths, errors, identities or secrets.
        return ['release'=>self::RELEASE, 'recipient_host'=>self::HOST, 'public_key_pem'=>$public,
            'key_sha256'=>hash('sha256', $public), 'transport'=>'private-https', 'server_timestamp'=>now()->timestamp,
            'expected_envelope_sha256'=>$this->expectedEnvelopeSha256()]+array_intersect_key($report,
                array_flip(['state', 'envelope_sha256', 'completed_at', 'existing_records_preserved', 'skipped', 'identity_review', 'barangays', 'failure_stage', 'failure_category', 'database_sqlstate', 'database_error_number', 'database_column']));
    }

    public static function encrypt(array $source, array $recipient): array
    {
        if (($recipient['recipient_host'] ?? null) !== self::HOST || ($recipient['release'] ?? null) !== self::RELEASE
            || ! hash_equals((string)($recipient['key_sha256'] ?? ''), hash('sha256', $recipient['public_key_pem'] ?? ''))) {
            throw new \RuntimeException('Incorrect recipient.');
        }
        $dataKey = random_bytes(32); $iv = random_bytes(12);
        $aad = self::RELEASE.'|'.self::HOST.'|'.$recipient['key_sha256'];
        $plaintext = gzencode(json_encode($source, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR), 9);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $dataKey, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($ciphertext === false || ! openssl_public_encrypt($dataKey, $wrapped, $recipient['public_key_pem'], OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new \RuntimeException('Transfer encryption failed.');
        }
        return ['format'=>'rbim.brbi.encrypted.v1', 'release'=>self::RELEASE, 'recipient_host'=>self::HOST,
            'key_sha256'=>$recipient['key_sha256'], 'wrapped_key'=>base64_encode($wrapped), 'iv'=>base64_encode($iv),
            'tag'=>base64_encode($tag), 'ciphertext'=>base64_encode($ciphertext)];
    }

    public function decrypt(array $envelope): array
    {
        $metadata = $this->publicMetadata();
        if (($envelope['format'] ?? null) !== 'rbim.brbi.encrypted.v1' || ($envelope['release'] ?? null) !== self::RELEASE
            || ($envelope['recipient_host'] ?? null) !== self::HOST || ($envelope['key_sha256'] ?? null) !== ($metadata['key_sha256'] ?? null)) {
            throw new \RuntimeException('Envelope recipient mismatch.');
        }
        $private = Storage::disk('local')->get(self::DIRECTORY.'/private.pem');
        if (! openssl_private_decrypt(base64_decode($envelope['wrapped_key'], true), $key, $private, OPENSSL_PKCS1_OAEP_PADDING)
            || strlen($key) !== 32) throw new \RuntimeException('Cannot unwrap transfer key.');
        $aad = self::RELEASE.'|'.self::HOST.'|'.$envelope['key_sha256'];
        $compressed = openssl_decrypt(base64_decode($envelope['ciphertext'], true), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
            base64_decode($envelope['iv'], true), base64_decode($envelope['tag'], true), $aad);
        if ($compressed === false) throw new \RuntimeException('Transfer authentication failed.');
        $plaintext = gzdecode($compressed, 16*1024*1024);
        if ($plaintext === false) throw new \RuntimeException('Invalid transfer compression.');
        $source = json_decode($plaintext, true, flags:JSON_THROW_ON_ERROR);
        if (($source['release'] ?? null) !== self::RELEASE || count($source['areas'] ?? []) !== 29) {
            throw new \RuntimeException('Incomplete source package.');
        }
        return $source;
    }

    public function run(): void
    {
        // Docker startup is the sole production caller; local databases must never apply this release automatically.
        if (! app()->environment('production') || parse_url(config('app.url'), PHP_URL_HOST) !== self::HOST) return;
        $this->initialize();
        $envelopePath = Storage::disk('local')->path(self::ENVELOPE);
        if (! is_file($envelopePath)) return;
        $disk = Storage::disk('local');
        $lock = fopen($disk->path(self::DIRECTORY.'/sync.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) throw new \RuntimeException('Sync lock unavailable.');
        try {
            $hash = hash_file('sha256', $envelopePath);
            if (! hash_equals($this->expectedEnvelopeSha256(),$hash)) throw new \RuntimeException('Unexpected release payload.');
            $report = $this->publicMetadata();
            if (($report['state'] ?? null) === 'completed' && ($report['envelope_sha256'] ?? null) === $hash) return;
            $stage='decrypt';
            $source = $this->decrypt(json_decode(file_get_contents($envelopePath), true, flags:JSON_THROW_ON_ERROR));
            $backup = $disk->path(self::DIRECTORY.'/backups/'.now()->format('Ymd-His-u'));
            $stage='backup_and_import';
            $result = app(BrbiAppendOnlySync::class)->apply($source, $backup);
            $report = ['state'=>'completed', 'envelope_sha256'=>$hash, 'completed_at'=>now()->toIso8601String()]+$result;
            $stage='publish_report';
            if (! $disk->put(self::DIRECTORY.'/report.json', json_encode($report, JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Sync report write failed.');
            }
        } catch (\Throwable $error) {
            $column=null;
            if ($error instanceof \Illuminate\Database\QueryException
                && preg_match('/for column [\x27`]([a-z_]+)[\x27`]/i',(string)($error->errorInfo[2]??''),$match)
                && in_array($match[1],['status','sex','civil_status','reporting_month','birth_date','recorded_age','household_number','source_position','residence_status'],true)) {
                $column=$match[1];
            }
            $disk->put(self::DIRECTORY.'/report.json', json_encode(['state'=>'failed', 'envelope_sha256'=>$hash ?? null,
                'failure_stage'=>$stage??'validation', 'failure_category'=>$error instanceof \Illuminate\Database\QueryException ? 'database_error' : 'validation_error',
                'database_sqlstate'=>$error instanceof \Illuminate\Database\QueryException ? ($error->errorInfo[0]??null) : null,
                'database_error_number'=>$error instanceof \Illuminate\Database\QueryException ? ($error->errorInfo[1]??null) : null,
                'database_column'=>$column], JSON_THROW_ON_ERROR));
            // Exception text can contain private source values. Keep it exclusively inside private storage.
            $disk->put(self::DIRECTORY.'/private-error.txt', $error->getMessage());
            throw new \RuntimeException('BRBI release sync failed. Existing records are protected; inspect private sync diagnostics.');
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

}
