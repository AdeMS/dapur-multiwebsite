<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

use RuntimeException;

/**
 * Perintah pengelolaan multi-website, dipanggil dari `php dapur` inti atau vendor/bin/dapur-website.
 */
final class Perintah
{
    public function __construct(
        private readonly string $folderInti,
    ) {
    }

    /** @param list<string> $argumen perintah beserta opsinya */
    public function jalankan(array $argumen): int
    {
        $perintah = $argumen[0] ?? '';
        $sisa     = array_slice($argumen, 1);

        return match ($perintah) {
            'buat', 'buat-website'     => $this->buat($sisa),
            'daftar', 'daftar-website' => $this->daftar(),
            'clear-config-cache'       => $this->hapusCache($sisa),
            ''                         => $this->bantuan(0),
            default                    => $this->bantuan(1),
        };
    }

    /** @param list<string> $argumen */
    private function buat(array $argumen): int
    {
        $opsi     = [];
        $posisi   = [];
        $terpusat = false;
        foreach ($argumen as $satu) {
            if ($satu === '--terpusat') {
                $terpusat = true;
            } elseif (preg_match('/^--(lokasi|alias|judul)=(.*)$/s', $satu, $bagian) === 1) {
                $opsi[$bagian[1]] = $bagian[2];
            } elseif (str_starts_with($satu, '--')) {
                return $this->gagal(sprintf("Opsi '%s' tidak dikenal.", $satu));
            } else {
                $posisi[] = $satu;
            }
        }

        if (count($posisi) !== 1) {
            return $this->gagal('Pemakaian: buat-website <domain> [--lokasi=<folder>] [--terpusat] [--alias=<a,b>] [--judul=<judul>]');
        }

        // Hak tulis grup, agar akun lain di grup yang sama tetap bisa bekerja.
        umask(002);

        $alias = array_values(array_filter(array_map('trim', explode(',', $opsi['alias'] ?? ''))));

        try {
            $hasil = (new PembuatWebsite($this->folderInti))
                ->buat($posisi[0], $opsi['lokasi'] ?? null, $alias, $opsi['judul'] ?? null, $terpusat);
        } catch (RuntimeException $e) {
            return $this->gagal($e->getMessage());
        }

        $this->tampilkanHasil($hasil);

        return 0;
    }

    private function tampilkanHasil(HasilBuatWebsite $hasil): void
    {
        $akar = $hasil->lokasi ?? $this->folderInti;

        printf("Website '%s' terdaftar.%s", $hasil->nama, PHP_EOL);
        printf("  Mode   : %s%s", $hasil->lokasi !== null ? 'folder domain' : 'terpusat', PHP_EOL);
        printf("  Lokasi : %s%s", $hasil->lokasi ?? $this->folderInti . '/config/website/' . $hasil->nama, PHP_EOL);
        printf("  Judul  : %s%s", $hasil->judul, PHP_EOL);
        printf("  Alias  : %s%s", $hasil->alias !== [] ? implode(', ', $hasil->alias) : '(tidak ada)', PHP_EOL);

        echo PHP_EOL, 'Dibuat:', PHP_EOL;
        foreach ($hasil->dibuat as $path) {
            printf("  %s%s", $path, PHP_EOL);
        }
        if ($hasil->dibuat === []) {
            echo '  (tidak ada; semua file sudah ada dan tidak ditimpa)', PHP_EOL;
        }

        if ($hasil->judulDiabaikan !== null) {
            echo PHP_EOL;
            printf("Catatan: judul '%s' tidak dipakai, karena file ini sudah ada dan tidak ditimpa:%s", $hasil->judulDiabaikan, PHP_EOL);
            printf("  %s%s", $hasil->fileWebsite, PHP_EOL);
            printf("  Ubah 'judul' di file itu, lalu jalankan: php dapur clear-config-cache --website=%s%s", $hasil->nama, PHP_EOL);
        }

        echo PHP_EOL, 'Langkah berikutnya:', PHP_EOL;
        printf("  - Arahkan document root domain ke %s/public%s", $akar, PHP_EOL);
        printf(
            "  - Pastikan web server bisa menulis ke %s%s",
            $hasil->lokasi !== null ? $hasil->lokasi . '/data' : $this->folderInti . '/data/website',
            PHP_EOL
        );

        if (getenv('COMPOSER_BINARY') !== false) {
            echo PHP_EOL, 'Catatan: lewat composer, opsi hanya terbaca bila ditulis setelah --, misalnya:', PHP_EOL;
            echo '  composer dapur -- buat-website contoh.com --alias=www.contoh.com', PHP_EOL;
        }
    }

    private function daftar(): int
    {
        $semua = $this->semuaWebsite();
        if ($semua === null) {
            return $this->gagal($this->pesanBelumAktif());
        }

        foreach ($semua as $nama => $website) {
            printf("%-40s %s%s", $nama, $website?->folderConfig ?? '(folder tidak ditemukan)', PHP_EOL);
        }

        return 0;
    }

    /** @param list<string> $argumen */
    private function hapusCache(array $argumen): int
    {
        if (! in_array('--semua', $argumen, true)) {
            return $this->gagal('Pakai --semua untuk menghapus cache konfigurasi inti dan semua website.');
        }

        $semua = $this->semuaWebsite();
        if ($semua === null) {
            return $this->gagal($this->pesanBelumAktif());
        }

        $gagal = false;
        $file  = ['(inti)' => $this->folderInti . '/data/cache/config-cache.php'];
        foreach ($semua as $nama => $website) {
            $file[$nama] = $website?->fileCacheKonfigurasi();
        }

        foreach ($file as $nama => $path) {
            if ($path === null) {
                printf("%-40s %s%s", $nama, CacheKonfigurasi::FOLDER_BELUM_ADA, PHP_EOL);
                continue;
            }

            $status = CacheKonfigurasi::hapus($path);
            printf("%-40s %s%s", $nama, $status, PHP_EOL);
            $gagal = $gagal || ! CacheKonfigurasi::berhasil($status);
        }

        return $gagal ? 1 : 0;
    }

    /**
     * Lokasi log error inti dan semua website, untuk `php dapur log-error --semua` di inti Dapur 2.8 atau lebih
     * baru. Lokasi website mengikuti bawaan paket ini (data/log/error.log website); website yang foldernya belum
     * ada bernilai null.
     *
     * @return array<string, string|null>|null null bila multi-website belum aktif
     */
    public function fileLogErrorSemua(string $fileLogInti): ?array
    {
        $semua = $this->semuaWebsite();
        if ($semua === null) {
            return null;
        }

        $file = ['(inti)' => $fileLogInti];
        foreach ($semua as $nama => $website) {
            $file[$nama] = $website?->fileLogError();
        }

        return $file;
    }

    /** @return array<string, Website|null>|null null bila multi-website belum aktif */
    private function semuaWebsite(): ?array
    {
        $daftar = Bootstrap::bacaDaftar($this->folderInti);

        return $daftar !== null ? (new PencariWebsite($daftar, $this->folderInti))->semua() : null;
    }

    private function pesanBelumAktif(): string
    {
        return sprintf("Multi-website belum aktif: '%s' tidak ditemukan.", Bootstrap::FILE_DAFTAR);
    }

    private function gagal(string $pesan): int
    {
        fwrite(STDERR, $pesan . PHP_EOL);

        return 1;
    }

    private function bantuan(int $kode): int
    {
        echo <<<BANTUAN
            Perintah website:

              buat-website <domain>          Membuat folder website dan mendaftarkannya
                  --lokasi=<folder>          Lokasi website; bawaan <folder_utama>/<domain>
                  --terpusat                 Dibuat di config/website/<domain> inti
                  --alias=<a,b>              Domain lain yang membuka website yang sama
                  --judul=<judul>            Judul website; bawaan nama domain
              daftar-website                 Menampilkan semua website yang terdaftar
              clear-config-cache --semua     Menghapus cache konfigurasi inti dan semua website

            BANTUAN;

        return $kode;
    }
}
