<?php

namespace App\Services;

use App\Models\PopulationReportSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PopulationReportSignatures
{
    public function savedFor(User $user, ?int $submissionId = null): array
    {
        if (! $user->hasRole(User::ROLE_BARANGAY) || ! $user->barangay_id) return [];

        // Reuse only this staff member's successfully submitted, intact copy.
        // The report snapshot already persists across sessions and devices.
        $submission = PopulationReportSubmission::query()
            ->where('barangay_id', $user->barangay_id)->where('submitted_by', $user->id)
            ->when($submissionId, fn ($query) => $query->whereKey($submissionId))
            ->whereNotNull('snapshot->signatures')
            ->orderByDesc('submitted_at')->orderByDesc('id')->first();
        if (! $submission) return [];
        $snapshot = $submission->snapshot;
        if (! hash_equals($submission->snapshot_hash, app(PopulationReportSubmissionSnapshot::class)->hash($snapshot))) return [];

        $saved = [];
        foreach (['prepared', 'submitted'] as $key) {
            $signature = $snapshot['signatures'][$key] ?? null;
            if (is_array($signature) && is_string($signature['name'] ?? null)
                && is_string($signature['image'] ?? null)
                && str_starts_with($signature['image'], 'data:image/png;base64,')) {
                $saved[$key] = $signature + ['source_id' => $submission->id];
            }
        }
        return $saved;
    }

    public function capture(Request $request): array
    {
        $rules = ['signature_source_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(
            fn () => $request->boolean('prepared_signature_reuse') || $request->boolean('submitted_signature_reuse')
        )]];
        foreach (['prepared', 'submitted'] as $key) {
            $rules[$key.'_name'] = ['required', 'string', 'max:255'];
            $rules[$key.'_signature_upload'] = ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'];
            $rules[$key.'_signature_data'] = ['nullable', 'string', 'max:2800000'];
            $rules[$key.'_signature_reuse'] = ['nullable', 'boolean'];
        }
        $request->validate($rules);
        $result = [];
        $saved = ($request->boolean('prepared_signature_reuse') || $request->boolean('submitted_signature_reuse'))
            ? $this->savedFor($request->user(), $request->integer('signature_source_id')) : [];
        foreach (['prepared'=>'Barangay Secretary', 'submitted'=>'Punong Barangay'] as $key=>$role) {
            $field = $key.'_signature_data';
            $upload = $request->file($key.'_signature_upload');
            $data = (string) $request->input($field, '');
            $fail = fn ($message) => throw ValidationException::withMessages([$field=>$message]);
            if ($request->boolean($key.'_signature_reuse')) {
                if ($upload || $data !== '') $fail('Choose the saved signature or a replacement, not both.');
                if (! isset($saved[$key])) $fail('No saved '.$role.' signature is available. Please upload or draw one.');
                $result[$key] = ['name' => trim($request->input($key.'_name')),
                    'image' => $saved[$key]['image'], 'method' => $saved[$key]['method']];
                continue;
            }
            if ($upload && $data !== '') $fail('Choose an image or draw a signature, not both.');
            if ($upload) {
                $bytes = file_get_contents($upload->getRealPath());
            } elseif (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $data, $match)) {
                $bytes = base64_decode($match[1], true);
            } else {
                $fail('Upload or draw the '.$role.' signature before submitting.');
            }
            $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
            if (! $info || strlen($bytes)>2097152 || ! in_array($info['mime'], ['image/png','image/jpeg'], true)
                || $info[0]>3000 || $info[1]>2000 || $info[0]<10 || $info[1]<10) {
                $fail('Use a valid PNG/JPG signature up to 2 MB and 3000 × 2000 pixels.');
            }
            $source = @imagecreatefromstring($bytes);
            if (! $source) $fail('Could not read the signature image. Please try again.');
            // I-normalize sa PNG; dili i-save ang arbitrary uploaded file o metadata.
            $scale = min(1, 900/$info[0], 300/$info[1]);
            $width = max(1, (int) round($info[0]*$scale)); $height = max(1, (int) round($info[1]*$scale));
            $image = imagecreatetruecolor($width, $height);
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            $ink = 0; $minX=$width; $maxX=0; $minY=$height; $maxY=0;
            for ($y=0; $y<$height; $y++) for ($x=0; $x<$width; $x++) {
                $color = imagecolorat($image, $x, $y);
                if ((($color>>16)&255)<220 || (($color>>8)&255)<220 || ($color&255)<220) {
                    $ink++; $minX=min($minX,$x); $maxX=max($maxX,$x); $minY=min($minY,$y); $maxY=max($maxY,$y);
                }
            }
            if ($ink<40 || $maxX-$minX<8 || $maxY-$minY<4) $fail('The '.$role.' signature appears blank or incomplete. Please sign again.');
            ob_start(); imagepng($image); $png = ob_get_clean();
            $result[$key] = ['name'=>trim($request->input($key.'_name')), 'image'=>'data:image/png;base64,'.base64_encode($png),
                'method'=>$upload ? 'uploaded_image' : 'drawn_signature'];
        }
        return $result;
    }
}
