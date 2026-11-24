<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Draws a round rubber stamp as a transparent PNG with GD (no packages): organisation name curved along the top,
 * bottom text curved along the bottom, stars between them, an inner ring with the stamp word and the date,
 * slightly tilted with a worn-ink texture. Returns null when GD/FreeType is unavailable (callers fall back).
 */
class RubberStamp
{
    public const COLORS = ['blue' => [29, 58, 180], 'red' => [200, 30, 40], 'green' => [20, 120, 60], 'purple' => [100, 40, 170], 'black' => [25, 30, 45]];

    /** Stamp for certificates, from Settings (stamp_word, stamp_bottom_text, stamp_color). */
    public static function forOrganisation(?string $date = null): ?string
    {
        $org = (string) SystemSetting::get('org_name', 'ElTech Finance');
        return self::png(
            $org,
            (string) SystemSetting::get('stamp_word', 'APPROVED'),
            (string) SystemSetting::get('stamp_bottom_text', ''),
            $date ?? now()->format('d M Y'),
            (string) SystemSetting::get('stamp_color', 'blue')
        );
    }

    public static function dataUri(?string $date = null): ?string
    {
        $png = self::forOrganisation($date);
        return $png ? 'data:image/png;base64,' . base64_encode($png) : null;
    }

    public static function png(string $top, string $word, string $bottom, string $date, string $color = 'blue', float $tilt = 8): ?string
    {
        if (!function_exists('imagettftext') || !function_exists('imagecreatetruecolor')) {
            return null;
        }
        $bold = self::font('DejaVuSans-Bold.ttf');
        if (!$bold) {
            return null;
        }
        [$r, $g, $b] = self::COLORS[$color] ?? self::COLORS['blue'];

        $S = 1200;                       // drawn at 2x, then scaled down (smooth edges)
        $c = $S / 2;
        $R = 580;
        $im = imagecreatetruecolor($S, $S);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        $clear = imagecolorallocatealpha($im, 255, 255, 255, 127);
        imagefill($im, 0, 0, $clear);
        $ink = imagecolorallocatealpha($im, $r, $g, $b, 0);

        // Rings: thick outer, thin inner border, thin ring around the centre
        self::ring($im, $c, $R, $R - 26, $ink, $clear);
        self::ring($im, $c, $R - 40, $R - 48, $ink, $clear);
        $inner = (int) round($R * 0.56);
        self::ring($im, $c, $inner, $inner - 9, $ink, $clear);
        self::ring($im, $c, $inner - 20, $inner - 25, $ink, $clear);
        imagealphablending($im, true);

        // Curved texts in the band between the rings, letters centred in the band
        $bandOuter = $R - 56;
        $bandInner = $inner + 6;
        $mid = ($bandOuter + $bandInner) / 2;
        $size = 20;
        while (self::capHeight($bold, $size + 2) < ($bandOuter - $bandInner) * 0.58) {
            $size += 2;
        }
        $top = mb_strtoupper(trim($top));
        $bottom = mb_strtoupper(trim($bottom));
        $topSize = self::fitArc($bold, $top, $size, $mid, $bottom === '' ? 260 : 175);
        self::arcText($im, $bold, $top, $topSize, $mid - self::capHeight($bold, $topSize) / 2, $ink, true, -90);
        if ($bottom !== '') {
            $botSize = self::fitArc($bold, $bottom, $size, $mid, 120);
            self::arcText($im, $bold, $bottom, $botSize, $mid + self::capHeight($bold, $botSize) / 2, $ink, false, 90);
            // Stars between the top and bottom texts
            $T = rad2deg(self::arcLength($bold, $top, $topSize) / $mid);
            $B = rad2deg(self::arcLength($bold, $bottom, $botSize) / $mid);
            if (360 - $T - $B > 2 * 16) {
                foreach ([($T - $B) / 4, 180 - ($T - $B) / 4] as $deg) {
                    self::centred($im, $bold, '★', $size * 0.7, $c + $mid * cos(deg2rad($deg)), $c + $mid * sin(deg2rad($deg)), $ink);
                }
            }
        }

        // Centre: stamp word between two rules, date below
        $word = mb_strtoupper(trim($word));
        $maxW = ($inner - 30) * 1.75;
        $wSize = 120;
        while ($wSize > 30 && self::width($bold, $word, $wSize) > $maxW) {
            $wSize -= 4;
        }
        $hasDate = trim($date) !== '';
        $wordY = $c + ($hasDate ? -10 : 0);
        self::centred($im, $bold, $word, $wSize, $c, $wordY, $ink);
        $ruleW = min($maxW, self::width($bold, $word, $wSize) + 30) / 2;
        imagealphablending($im, false);
        imagefilledrectangle($im, (int) ($c - $ruleW), (int) ($wordY - $wSize * 0.92 - 7), (int) ($c + $ruleW), (int) ($wordY - $wSize * 0.92), $ink);
        imagefilledrectangle($im, (int) ($c - $ruleW), (int) ($wordY + $wSize * 0.78), (int) ($c + $ruleW), (int) ($wordY + $wSize * 0.78 + 7), $ink);
        imagealphablending($im, true);
        if ($hasDate) {
            self::centred($im, $bold, mb_strtoupper($date), 52, $c, $wordY + $wSize * 0.78 + 75, $ink);
        }

        // Scale down to 600
        $out = imagecreatetruecolor($S / 2, $S / 2);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $S / 2, $S / 2, $S, $S);
        imagedestroy($im);

        self::wornInk($out, crc32($top . $word . $bottom));

        // Tilt like a hand-pressed stamp
        if ($tilt) {
            $rot = imagerotate($out, $tilt, imagecolorallocatealpha($out, 255, 255, 255, 127));
            imagedestroy($out);
            imagesavealpha($rot, true);
            $out = $rot;
        }
        // Interpolation leaves a near-clear haze (alpha 120–126) over the whole square: make it fully clear
        imagealphablending($out, false);
        for ($y = 0, $h = imagesy($out); $y < $h; $y++) {
            for ($x = 0, $w = imagesx($out); $x < $w; $x++) {
                if (((imagecolorat($out, $x, $y) >> 24) & 0x7F) >= 118) {
                    imagesetpixel($out, $x, $y, 0x7FFFFFFF);
                }
            }
        }

        ob_start();
        imagepng($out);
        imagedestroy($out);
        return ob_get_clean();
    }

    private static function font(string $file): ?string
    {
        foreach ([base_path('vendor/dompdf/dompdf/lib/fonts/' . $file), storage_path('fonts/' . $file)] as $p) {
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    private static function ring($im, float $c, float $outer, float $inner, int $ink, int $clear): void
    {
        imagefilledellipse($im, (int) $c, (int) $c, (int) (2 * $outer), (int) (2 * $outer), $ink);
        imagefilledellipse($im, (int) $c, (int) $c, (int) (2 * $inner), (int) (2 * $inner), $clear);
    }

    private static function capHeight(string $font, float $size): float
    {
        $bb = imagettfbbox($size, 0, $font, 'H');
        return abs($bb[1] - $bb[7]);
    }

    private static function width(string $font, string $text, float $size): float
    {
        $bb = imagettfbbox($size, 0, $font, $text);
        return abs($bb[2] - $bb[0]);
    }

    /** Largest size (≤ $size) at which the text fits in $maxDeg degrees of arc at radius $radius. */
    private static function fitArc(string $font, string $text, float $size, float $radius, float $maxDeg): float
    {
        while ($size > 14 && rad2deg(self::arcLength($font, $text, $size) / $radius) > $maxDeg) {
            $size -= 2;
        }
        return $size;
    }

    private static function arcLength(string $font, string $text, float $size): float
    {
        $len = 0;
        foreach (mb_str_split($text) as $ch) {
            $len += self::charWidth($font, $ch, $size) + $size * 0.18;
        }
        return $len;
    }

    private static function charWidth(string $font, string $ch, float $size): float
    {
        return $ch === ' ' ? $size * 0.45 : self::width($font, $ch, $size);
    }

    /**
     * Text along an arc centred at $centreDeg (image angles, clockwise from +x). $outward: letters stand on the
     * circle with their tops outward (top text, read clockwise); otherwise tops point inward (bottom text).
     */
    private static function arcText($im, string $font, string $text, float $size, float $radius, int $ink, bool $outward, float $centreDeg): void
    {
        $S = imagesx($im);
        $c = $S / 2;
        $span = self::arcLength($font, $text, $size) / $radius;      // radians
        $pos = $outward ? deg2rad($centreDeg) - $span / 2 : deg2rad($centreDeg) + $span / 2;
        foreach (mb_str_split($text) as $ch) {
            $w = self::charWidth($font, $ch, $size);
            $step = ($w + $size * 0.18) / $radius;
            $theta = $outward ? $pos + $step / 2 : $pos - $step / 2;
            if ($ch !== ' ') {
                $px = $c + $radius * cos($theta);
                $py = $c + $radius * sin($theta);
                if ($outward) {
                    $tx = -sin($theta);
                    $ty = cos($theta);
                    $angle = -(rad2deg($theta) + 90);
                } else {
                    $tx = sin($theta);
                    $ty = -cos($theta);
                    $angle = 90 - rad2deg($theta);
                }
                imagettftext($im, $size, $angle, (int) round($px - $tx * $w / 2), (int) round($py - $ty * $w / 2), $ink, $font, $ch);
            }
            $pos = $outward ? $pos + $step : $pos - $step;
        }
    }

    /** Text centred on (x, y) — y is the visual middle of capitals. */
    private static function centred($im, string $font, string $text, float $size, float $x, float $y, int $ink): void
    {
        $bb = imagettfbbox($size, 0, $font, $text);
        $w = $bb[2] - $bb[0];
        $h = $bb[1] - $bb[7];
        imagettftext($im, $size, 0, (int) round($x - $w / 2 - $bb[0]), (int) round($y + $h / 2 - $bb[1]), $ink, $font, $text);
    }

    /** Uneven rubber-stamp ink: faded patches and speckles where the ink didn't take. */
    private static function wornInk($im, int $seed): void
    {
        mt_srand($seed);
        $w = imagesx($im);
        $h = imagesy($im);
        $blobs = [];
        for ($i = 0; $i < 14; $i++) {
            $blobs[] = [mt_rand(0, $w), mt_rand(0, $h), mt_rand(25, 90), mt_rand(20, 55) / 100];
        }
        imagealphablending($im, false);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                if ($a >= 127) {
                    continue;
                }
                $fade = 0.0;
                foreach ($blobs as [$bx, $by, $br, $bs]) {
                    $d = (($x - $bx) ** 2 + ($y - $by) ** 2) / ($br * $br);
                    if ($d < 1) {
                        $fade = max($fade, (1 - $d) * $bs);
                    }
                }
                $n = mt_rand(0, 1000);
                if ($n < 35) {
                    $fade = max($fade, 0.8);             // speckle
                } elseif ($n < 110) {
                    $fade = max($fade, 0.3);
                }
                $na = (int) min(127, $a + (127 - $a) * $fade + 8);
                if ($na !== $a) {
                    imagesetpixel($im, $x, $y, ($rgba & 0xFFFFFF) | ($na << 24));
                }
            }
        }
        imagealphablending($im, true);
    }
}
