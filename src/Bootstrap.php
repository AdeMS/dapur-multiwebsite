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
        $daftar     = self::bacaDaftar($folderInti);
        if ($daftar === null) {
            // Multi-website belum diaktifkan: Dapur berjalan sebagai satu website.
            return;
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

        $pencari = new PencariWebsite($daftar, $folderInti);

        if ($pencari->adalahDashboard($host)) {
            // Dashboard hanya dilayani lewat public/ inti, tidak lewat folder website mana pun
            // (mis. bila vhost website menjadi vhost bawaan dan header Host dipalsukan).
            // DAPUR_WEBSITE_PATH menandai request yang masuk lewat index.php folder website.
            if (defined('DAPUR_WEBSITE_PATH')) {
                Halaman::websiteTidakDitemukan($host);
            }

            return;
        }

        $website = $pencari->cari($host);
        if ($website === null) {
            $nama = $pencari->namaTerdaftar($host);
            if ($nama !== null) {
                Halaman::folderWebsiteBelumAda($nama);
            }

            Halaman::websiteTidakDitemukan($host);
        }

        // Inti lama (sebelum 2.4) mengabaikan konstanta di bawah, sehingga semua website akan
        // menampilkan inti. Lebih baik berhenti dengan pesan kesalahan.
        if (! self::intiMendukung($folderInti)) {
            Halaman::intiTidakMendukung();
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

    /**
     * Isi config/multiwebsite.local.php, atau null bila file itu tidak ada (multi-website tidak aktif).
     * Isi yang bukan array menghentikan request/perintah dengan pesan kesalahan.
     *
     * @return array<mixed>|null
     */
    public static function bacaDaftar(string $folderInti): ?array
    {
        $file = $folderInti . '/' . self::FILE_DAFTAR;
        if (! is_file($file)) {
            return null;
        }

        $daftar = require $file;
        if (! is_array($daftar)) {
            Halaman::konfigurasiTidakValid($file);
        }

        return $daftar;
    }

    /**
     * Apakah config/config.php inti memiliki kait lapisan website (Dapur 2.4 atau lebih baru).
     * Diperiksa dari isi file karena versi inti tidak selalu diketahui Composer
     * (instalasi create-project tidak memiliki .git).
     */
    public static function intiMendukung(string $folderInti): bool
    {
        $isi = @file_get_contents($folderInti . '/config/config.php');

        return is_string($isi) && str_contains($isi, 'DAPUR_CONFIG_PROVIDERS');
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
