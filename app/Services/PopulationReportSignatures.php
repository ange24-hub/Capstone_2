<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PopulationReportSignatures
{
    public function capture(Request $request): array
    {
        $rules = [];
        foreach (['prepared', 'submitted'] as $key) {
            $rules[$key.'_name'] = ['required', 'string', 'max:255'];
            $rules[$key.'_signature_upload'] = ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'];
            $rules[$key.'_signature_data'] = ['nullable', 'string', 'max:2800000'];
        }
        $request->validate($rules);
        $result = [];
        foreach (['prepared'=>'Barangay Secretary', 'submitted'=>'Punong Barangay'] as $key=>$role) {
            $field = $key.'_signature_data';
            $upload = $request->file($key.'_signature_upload');
            $data = (string) $request->input($field, '');
            $fail = fn ($message) => throw ValidationException::withMessages([$field=>$message]);
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
