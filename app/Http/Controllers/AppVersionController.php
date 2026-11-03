<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['version' => self::version()])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Fingerprint of the files an installed copy can keep outdated: the Vite build, the service worker
     * and the web app manifest.
     */
    public static function version(): string
    {
        $files = [
            public_path('build/manifest.json'),
            public_path('sw.js'),
            public_path('manifest.webmanifest'),
        ];

        $fingerprint = collect($files)
            ->map(fn (string $path): string => is_file($path) ? (string) md5_file($path) : 'x')
            ->implode('|');

        return substr(md5($fingerprint), 0, 12);
    }
}
