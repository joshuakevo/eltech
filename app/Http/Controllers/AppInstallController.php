<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;

/**
 * Installable web app (PWA): manifest + home-screen icons built from the organisation
 * name and logo in Settings. Public routes (the login page links them).
 */
class AppInstallController extends Controller
{
    private const BG = [15, 36, 68];   // #0f2444, sidebar navy

    public function manifest()
    {
        $name = SystemSetting::get('org_name', 'ElTech Finance');
        $v = $this->logoVersion();

        return response()->json([
            'name'             => $name,
            'short_name'       => mb_strimwidth($name, 0, 15, ''),
            'description'      => "{$name} — financial management",
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'background_color' => '#0f2444',
            'theme_color'      => '#0f2444',
            'icons'            => collect([192, 512])->map(fn ($s) => [
                'src' => route('app.icon', ['size' => $s]) . "?v={$v}", 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any maskable',
            ])->all(),
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Square PNG icon: the organisation logo centred on navy (falls back to the org initials). */
    public function icon(int $size)
    {
        $size = in_array($size, [180, 192, 512], true) ? $size : 192;
        if (!function_exists('imagecreatetruecolor')) {
            $logo = SystemSetting::get('org_logo');
            return $logo && file_exists(public_path($logo)) ? response()->file(public_path($logo)) : abort(404);
        }

        $img = imagecreatetruecolor($size, $size);
        imagefill($img, 0, 0, imagecolorallocate($img, ...self::BG));
        $src = $this->loadLogo();
        if ($src) {
            // keep inside the maskable safe zone (centre 80%), with breathing room
            $box = (int) round($size * 0.76);
            $w = imagesx($src); $h = imagesy($src);
            $scale = min($box / $w, $box / $h);
            $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
            imagealphablending($img, true);
            imagecopyresampled($img, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
        } else {
            $initials = collect(preg_split('/\s+/', SystemSetting::get('org_name', 'ElTech Finance')))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
            $white = imagecolorallocate($img, 255, 255, 255);
            $font = 5; $scaleUp = max(1, (int) ($size / 40));
            $tmp = imagecreatetruecolor(imagefontwidth($font) * strlen($initials), imagefontheight($font));
            imagefill($tmp, 0, 0, imagecolorallocate($tmp, ...self::BG));
            imagestring($tmp, $font, 0, 0, strtoupper($initials), imagecolorallocate($tmp, 255, 255, 255));
            $tw = imagesx($tmp) * $scaleUp; $th = imagesy($tmp) * $scaleUp;
            imagecopyresized($img, $tmp, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), 0, 0, $tw, $th, imagesx($tmp), imagesy($tmp));
            imagedestroy($tmp);
            unset($white);
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);
        return response(ob_get_clean(), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    private function loadLogo()
    {
        $logo = SystemSetting::get('org_logo');
        $path = $logo ? public_path($logo) : null;
        if (!$path || !is_file($path)) {
            return null;
        }
        $data = @file_get_contents($path);
        $img = $data ? @imagecreatefromstring($data) : false;
        return $img ?: null;
    }

    private function logoVersion(): string
    {
        $logo = SystemSetting::get('org_logo');
        return substr(md5(($logo ?: 'none') . ($logo && is_file(public_path($logo)) ? filemtime(public_path($logo)) : '')), 0, 8);
    }
}
