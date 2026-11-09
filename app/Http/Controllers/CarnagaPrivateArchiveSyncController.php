<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\HistoricalPopulationReportImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarnagaPrivateArchiveSyncController extends Controller
{
    private const HOST = 'sb-ispgm.bsit4bdevs.tech';
    private const RELEASE = 'carnaga-20261109';
    private const EXPIRES = '2026-11-10T06:40:00+00:00';
    private const ENVELOPE_SHA256 = '1105bfb11132afba6653dad1876ddbbb3186023c01b3486b1840e2fb69f858f8';
    private const PHOTO_COUNT = 5;

    public function __invoke(Request $request, HistoricalPopulationReportImport $import)
    {
        abort_unless(app()->environment('production') && parse_url(config('app.url'), PHP_URL_HOST) === self::HOST, 404);
        abort_if(now()->isAfter(self::EXPIRES), 410);
        $raw = $request->getContent();
        abort_unless(strlen($raw) < 2_000_000 && hash_equals(self::ENVELOPE_SHA256, hash('sha256', $raw)), 403);

        $envelope = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(($envelope['release'] ?? null) === self::RELEASE && ($envelope['recipient_host'] ?? null) === self::HOST, 403);
        $disk = Storage::disk('local');
        $public = $disk->get('deployment-data-sync/public.pem');
        abort_unless(hash_equals(hash('sha256', $public), $envelope['key_sha256'] ?? ''), 403);
        $private = $disk->get('deployment-data-sync/private.pem');
        abort_unless(openssl_private_decrypt(base64_decode($envelope['wrapped_key'], true), $key, $private, OPENSSL_PKCS1_OAEP_PADDING) && strlen($key) === 32, 403);
        $aad = self::RELEASE.'|'.self::HOST.'|'.$envelope['key_sha256'];
        $compressed = openssl_decrypt(base64_decode($envelope['ciphertext'], true), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
            base64_decode($envelope['iv'], true), base64_decode($envelope['tag'], true), $aad);
        abort_unless($compressed !== false, 403);
        $plain = gzdecode($compressed, 2_000_000);
        abort_unless($plain !== false, 403);
        $reports = json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($reports) && count($reports) === self::PHOTO_COUNT, 422);

        $barangay = Barangay::where('name', 'Carnaga')->firstOrFail();
        $result = DB::transaction(function () use ($reports, $barangay, $import, $disk) {
            $result = [];
            foreach ($reports as $document) {
                $photo = base64_decode($document['photo'] ?? '', true);
                abort_unless($photo !== false && preg_match('/\A[a-f0-9]{64}\z/', $document['sha'] ?? '')
                    && hash_equals($document['sha'], hash('sha256', $photo)), 422);
                $path = 'private/population-reports/'.$document['sha'].'.jpg';
                // Temporary verified copy; the import preserves it at the same private path.
                abort_unless($disk->put($path, $photo), 500);
                $saved = $import->import($barangay, $disk->path($path), [
                    'year' => $document['year'], 'semester' => $document['semester'],
                    'population' => $document['population'], 'households' => $document['households'],
                    'families' => $document['families'], 'age_totals' => $document['age_totals'],
                ]);
                $result[] = ['reference' => $saved->reference, 'year' => $document['year'],
                    'semester' => $document['semester'], 'population' => $document['population']];
            }
            return $result;
        });
        return response()->json(['state' => 'completed', 'reports' => $result])->header('Cache-Control', 'no-store');
    }
}
