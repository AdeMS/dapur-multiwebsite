<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

use Composer\InstalledVersions;

/**
 * Menentukan website yang sedang dilayani sebelum konfigurasi inti dimuat.
 *
 * - Web: dari nama host request. Host dashboard menampilkan inti; host lain dicari di
 *   daftar website; bila tidak ditemukan, halaman "website tidak ditemukan" dikirim.
 * - Baris perintah: dari opsi --website=<host> (atau variabel lingkungan DAPUR_WEBSITE);
 *   tanpa opsi, perintah berjalan untuk inti.
 */
final class Bootstrap
{
    public const FILE_DAFTAR = 'config/multiwebsite.local.php';

    public static function jalankan(): void
    {
        // Lapisan website sudah ditentukan pihak lain.
        if (defined('DAPUR_CONFIG_PROVIDERS')) {
            return;
        }

        $folderInti = self::folderInti();
        $fileDaftar = $folderInti . '/' . self::FILE_DAFTAR;
        if (! is_file($fileDaftar)) {
            // Multi-website belum diaktifkan: Dapur berjalan sebagai satu website.
            return;
        }

        $daftar = require $fileDaftar;
        if (! is_array($daftar)) {
            Halaman::konfigurasiTidakValid($fileDaftar);
        }

        $cli  = PHP_SAPI === 'cli';
        $asli = $cli ? self::hostDariBarisPerintah() : self::hostDariRequest();
        if ($cli && $asli === null) {
            return;
        }

        $host = NamaHost::normalkan((string) $asli);
        if ($host === null) {
            Halaman::websiteTidakDitemukan((string) $asli);
        }

        $pencari      = new PencariWebsite($daftar, $folderInti);
        $folderDomain = defined('DAPUR_WEBSITE_PATH') ? (string) DAPUR_WEBSITE_PATH : null;

        if ($pencari->adalahDashboard($host)) {
            // Dashboard hanya dilayani lewat public/ inti, tidak lewat folder website mana pun
            // (mis. bila vhost website menjadi vhost bawaan dan header Host dipalsukan).
            if ($folderDomain !== null) {
                Halaman::websiteTidakDitemukan($host);
            }

            return;
        }

        $website = $pencari->cari($host, $folderDomain);
        if ($website === null) {
            Halaman::websiteTidakDitemukan($host);
        }

        Website::aktifkan($website);
        PemuatModul::daftarkan($website->folderModul);

        if (! defined('DAPUR_CONFIG_CACHE_PATH')) {
            define('DAPUR_CONFIG_CACHE_PATH', $website->fileCacheKonfigurasi());
        }
        define('DAPUR_CONFIG_PROVIDERS', [WebsiteConfigProvider::class]);
    }

    /** Folder akar inti Dapur (paket utama Composer). */
    public static function folderInti(): string
    {
        $folder = InstalledVersions::getRootPackage()['install_path'];

        return rtrim((string) (realpath($folder) ?: $folder), '/');
    }

    private static function hostDariRequest(): string
    {
        return (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    }

    private static function hostDariBarisPerintah(): ?string
    {
        foreach ($_SERVER['argv'] ?? [] as $argumen) {
            if (is_string($argumen) && str_starts_with($argumen, '--website=')) {
                return substr($argumen, strlen('--website='));
            }
        }

        $env = getenv('DAPUR_WEBSITE');

        return is_string($env) && $env !== '' ? $env : null;
    }
}
