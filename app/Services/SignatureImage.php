<?php

namespace App\Services;

/** Presentation-only framing; the signed snapshot and its original image stay intact. */
class SignatureImage
{
    public static function forReport(string $dataUri): string
    {
        if (!str_starts_with($dataUri, 'data:image/png;base64,') || !function_exists('imagecreatefromstring')) {
            return $dataUri;
        }

        $bytes = base64_decode(substr($dataUri, 22), true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$info || $info[0] > 3000 || $info[1] > 2000 || $info['mime'] !== 'image/png') {
            return $dataUri;
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) return $dataUri;
        imagepalettetotruecolor($source);
        $left = $info[0]; $top = $info[1]; $right = -1; $bottom = -1;
        for ($y = 0; $y < $info[1]; $y++) {
            for ($x = 0; $x < $info[0]; $x++) {
                $color = imagecolorat($source, $x, $y);
                if ((($color >> 24) & 127) < 100
                    && ((($color >> 16) & 255) < 220 || (($color >> 8) & 255) < 220 || ($color & 255) < 220)) {
                    $left = min($left, $x); $right = max($right, $x);
                    $top = min($top, $y); $bottom = max($bottom, $y);
                }
            }
        }
        if ($right < $left || $bottom < $top) return $dataUri;

        // Fit the visible ink into a centered 5:1 frame without stretching it.
        $width = $right - $left + 1; $height = $bottom - $top + 1;
        $scale = min(352 / $width, 64 / $height);
        $drawWidth = max(1, (int) round($width * $scale));
        $drawHeight = max(1, (int) round($height * $scale));
        $frame = imagecreatetruecolor(360, 72);
        imagefill($frame, 0, 0, imagecolorallocate($frame, 255, 255, 255));
        imagecopyresampled($frame, $source, (int) floor((360 - $drawWidth) / 2), (int) floor((72 - $drawHeight) / 2),
            $left, $top, $drawWidth, $drawHeight, $width, $height);
        ob_start();
        imagepng($frame);
        $png = ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
