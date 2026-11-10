<?php

namespace Tests\Unit;

use App\Services\SignatureImage;
use PHPUnit\Framework\TestCase;

class SignatureImageTest extends TestCase
{
    public function test_visible_ink_is_centered_even_when_the_saved_drawing_is_in_a_corner(): void
    {
        $source = imagecreatetruecolor(600, 200);
        imagefill($source, 0, 0, imagecolorallocate($source, 255, 255, 255));
        imagefilledrectangle($source, 15, 130, 114, 149, imagecolorallocate($source, 20, 40, 60));
        ob_start(); imagepng($source); $original = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        $copy = $original;
        $result = SignatureImage::forReport($original);
        $frame = imagecreatefromstring(base64_decode(substr($result, 22)));
        $this->assertSame($copy, $original);
        $this->assertSame(360, imagesx($frame));
        $this->assertSame(72, imagesy($frame));
        $left = 360; $top = 72; $right = 0; $bottom = 0;
        for ($y = 0; $y < 72; $y++) for ($x = 0; $x < 360; $x++) {
            if ((imagecolorat($frame, $x, $y) & 255) < 220) {
                $left = min($left, $x); $right = max($right, $x);
                $top = min($top, $y); $bottom = max($bottom, $y);
            }
        }
        $this->assertEqualsWithDelta(179.5, ($left + $right) / 2, 1);
        $this->assertEqualsWithDelta(35.5, ($top + $bottom) / 2, 1);
        $this->assertEqualsWithDelta(5, ($right - $left + 1) / ($bottom - $top + 1), .1);
    }

    public function test_blank_or_unrecognized_images_are_left_unchanged(): void
    {
        $source = imagecreatetruecolor(100, 100);
        imagefill($source, 0, 0, imagecolorallocate($source, 255, 255, 255));
        ob_start(); imagepng($source); $blank = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        foreach ([$blank, 'data:image/png;base64,invalid', 'unrecognized'] as $value) {
            $this->assertSame($value, SignatureImage::forReport($value));
        }
    }
}
