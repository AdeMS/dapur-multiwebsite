<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

use RuntimeException;

/**
 * Membuat folder website dan mendaftarkannya di config/multiwebsite.local.php.
 *
 * File yang sudah ada tidak pernah ditimpa. Folder data tidak dibuat di sini, karena harus bisa
 * ditulis web server; folder itu dibuat otomatis oleh web server atau disiapkan oleh admin.
 */
final class PembuatWebsite
{
    public function __construct(
        private readonly string $folderInti,
    ) {
    }

    /**
     * Tanpa $lokasi, website dibuat di <folder_utama>/<nama> bila 'folder_utama' diatur di daftar
     * website; selain itu, atau dengan $terpusat, website dibuat terpusat di config/website/<nama>.
     *
     * @param list<string> $alias
     */
    public function buat(
        string $nama,
        ?string $lokasi = null,
        array $alias = [],
        ?string $judul = null,
        bool $terpusat = false,
    ): HasilBuatWebsite {
        if ($terpusat && $lokasi !== null) {
            throw new RuntimeException('Pilih salah satu: --lokasi atau --terpusat.');
        }

        $namaAsli = $nama;
        $nama     = NamaHost::normalkan($nama);
        if ($nama === null) {
            throw new RuntimeException(sprintf("Nama domain '%s' tidak sah.", $namaAsli));
        }

        $aliasSah = [];
        foreach ($alias as $satu) {
            $normal = NamaHost::normalkan($satu);
            if ($normal === null) {
                throw new RuntimeException(sprintf("Alias '%s' tidak sah.", $satu));
            }
            $aliasSah[] = $normal;
        }
        $aliasSah = array_values(array_unique($aliasSah));

        $fileDaftar = $this->folderInti . '/' . Bootstrap::FILE_DAFTAR;
        $daftar     = Bootstrap::bacaDaftar($this->folderInti);
        if ($daftar === null) {
            throw new RuntimeException(sprintf("Multi-website belum aktif: '%s' tidak ditemukan.", Bootstrap::FILE_DAFTAR));
        }

        if ($lokasi === null && ! $terpusat) {
            $folderUtama = $daftar['folder_utama'] ?? null;
            if (is_string($folderUtama) && $folderUtama !== '') {
                $lokasi = rtrim($folderUtama, '/') . '/' . $nama;
            }
        }

        if ($lokasi !== null && ! $this->lokasiSah($lokasi)) {
            throw new RuntimeException('Lokasi harus berupa path absolut tanpa bagian "..".');
        }
        $lokasi = $lokasi !== null ? rtrim($lokasi, '/') : null;

        $pencari = new PencariWebsite($daftar, $this->folderInti);
        foreach ([$nama, ...$aliasSah] as $host) {
            if ($pencari->adalahDashboard($host)) {
                throw new RuntimeException(sprintf("'%s' adalah host dashboard.", $host));
            }
            if ($pencari->namaTerdaftar($host) !== null) {
                throw new RuntimeException(sprintf("'%s' sudah terdaftar.", $host));
            }
        }

        $fileWebsite = ($lokasi !== null ? $lokasi . '/config' : $this->folderInti . '/config/website/' . $nama) . '/website.php';
        $sudahAda    = is_file($fileWebsite);
        $judulLama   = $sudahAda ? $this->bacaJudul($fileWebsite) : null;

        // Daftar diuji lebih dulu, agar folder tidak dibuat bila daftarnya tidak bisa diperbarui.
        $isiBaru = $this->isiDaftarBaru($fileDaftar, $nama, $lokasi, $aliasSah);

        $dibuat = $lokasi !== null
            ? $this->buatFolderDomain($lokasi, $nama, $judul)
            : $this->buatFolderTerpusat($nama, $judul);

        if (file_put_contents($fileDaftar, $isiBaru, LOCK_EX) === false) {
            throw new RuntimeException(sprintf("Gagal menulis '%s'.", Bootstrap::FILE_DAFTAR));
        }

        $judulDipakai = $sudahAda ? ($judulLama ?? $nama) : ($judul ?? $nama);
        $diabaikan    = $sudahAda && $judul !== null && $judul !== $judulDipakai ? $judul : null;

        return new HasilBuatWebsite($nama, $lokasi, $judulDipakai, $aliasSah, $dibuat, $fileWebsite, $diabaikan);
    }

    /** Judul dari website.php yang sudah ada; null bila tidak ada atau file tidak bisa dibaca. */
    private function bacaJudul(string $fileWebsite): ?string
    {
        try {
            $isi = require $fileWebsite;
        } catch (\Throwable) {
            return null;
        }

        $judul = is_array($isi) ? ($isi['judul'] ?? null) : null;

        return is_string($judul) ? $judul : null;
    }

    private function lokasiSah(string $lokasi): bool
    {
        return str_starts_with($lokasi, '/')
            && strlen($lokasi) > 1
            && ! in_array('..', explode('/', $lokasi), true);
    }

    /** @return list<string> */
    private function buatFolderTerpusat(string $nama, ?string $judul): array
    {
        $dibuat = [];
        $folder = $this->folderInti . '/config/website/' . $nama;
        $this->folder($folder . '/autoload', $dibuat);
        $this->folder($folder . '/module', $dibuat);
        $this->file($folder . '/website.php', $this->isiWebsite($nama, $judul), $dibuat);
        $this->file($folder . '/routes.php', $this->isiRute(), $dibuat);

        return $dibuat;
    }

    /** @return list<string> */
    private function buatFolderDomain(string $lokasi, string $nama, ?string $judul): array
    {
        $dibuat = [];
        $this->folder($lokasi . '/config/autoload', $dibuat);
        $this->folder($lokasi . '/module', $dibuat);
        $this->folder($lokasi . '/public', $dibuat);
        $this->file($lokasi . '/config/website.php', $this->isiWebsite($nama, $judul), $dibuat);
        $this->file($lokasi . '/config/routes.php', $this->isiRute(), $dibuat);
        $this->file($lokasi . '/public/index.php', $this->isiIndex(), $dibuat);

        $htaccess = $this->folderInti . '/public/.htaccess';
        if (is_file($htaccess)) {
            $this->file($lokasi . '/public/.htaccess', (string) file_get_contents($htaccess), $dibuat);
        }

        $aset = $lokasi . '/public/asset';
        if (! file_exists($aset) && ! is_link($aset)) {
            if (! @symlink($this->folderInti . '/public/asset', $aset)) {
                throw new RuntimeException(sprintf("Gagal membuat symlink '%s'.", $aset));
            }
            $dibuat[] = $aset;
        }

        return $dibuat;
    }

    /** @param list<string> $dibuat */
    private function folder(string $folder, array &$dibuat): void
    {
        if (is_dir($folder)) {
            return;
        }

        if (! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException(sprintf("Gagal membuat folder '%s'.", $folder));
        }
        $dibuat[] = $folder;
    }

    /** @param list<string> $dibuat */
    private function file(string $file, string $isi, array &$dibuat): void
    {
        if (file_exists($file)) {
            return;
        }

        if (file_put_contents($file, $isi) === false) {
            throw new RuntimeException(sprintf("Gagal menulis '%s'.", $file));
        }
        $dibuat[] = $file;
    }

    /** @param list<string> $alias */
    private function isiDaftarBaru(string $fileDaftar, string $nama, ?string $lokasi, array $alias): string
    {
        $isi    = (string) file_get_contents($fileDaftar);
        $jumlah = preg_match_all("/(['\"])website\\1\\s*=>\\s*\\[/", $isi, $cocok, PREG_OFFSET_CAPTURE);
        if ($jumlah !== 1) {
            throw new RuntimeException($this->pesanManual($nama, $lokasi, $alias));
        }

        $posisi = $cocok[0][0][1] + strlen($cocok[0][0][0]);
        $baru   = substr($isi, 0, $posisi) . "\n" . $this->entri($nama, $lokasi, $alias) . substr($isi, $posisi);

        $this->pastikanHasil($baru, $nama, $lokasi, $alias);

        return $baru;
    }

    /**
     * Isi baru dijalankan dari file sementara, memastikan hasilnya sah dan berisi website baru.
     *
     * @param list<string> $alias
     */
    private function pastikanHasil(string $isi, string $nama, ?string $lokasi, array $alias): void
    {
        $sementara = tempnam(sys_get_temp_dir(), 'dapur-website-');
        if ($sementara === false || file_put_contents($sementara, $isi) === false) {
            throw new RuntimeException('Gagal menyiapkan pemeriksaan daftar website.');
        }

        try {
            $hasil = require $sementara;
        } catch (\Throwable) {
            $hasil = null;
        } finally {
            unlink($sementara);
        }

        if (! is_array($hasil) || ! isset($hasil['website'][$nama])) {
            throw new RuntimeException($this->pesanManual($nama, $lokasi, $alias));
        }
    }

    /** @param list<string> $alias */
    private function entri(string $nama, ?string $lokasi, array $alias): string
    {
        $baris = [];
        if ($lokasi !== null) {
            $baris[] = "            'lokasi' => " . var_export($lokasi, true) . ',';
        }
        if ($alias !== []) {
            $daftarAlias = implode(', ', array_map(static fn (string $a): string => var_export($a, true), $alias));
            $baris[]     = "            'alias'  => [" . $daftarAlias . '],';
        }

        $kunci = '        ' . var_export($nama, true) . ' => ';
        if ($baris === []) {
            return $kunci . "[],\n";
        }

        return $kunci . "[\n" . implode("\n", $baris) . "\n        ],\n";
    }

    /** @param list<string> $alias */
    private function pesanManual(string $nama, ?string $lokasi, array $alias): string
    {
        return sprintf(
            "Tidak bisa memperbarui '%s' secara otomatis, dan belum ada yang dibuat. Tambahkan ke bagian 'website':%s%s",
            Bootstrap::FILE_DAFTAR,
            PHP_EOL,
            $this->entri($nama, $lokasi, $alias)
        );
    }

    private function isiWebsite(string $nama, ?string $judul): string
    {
        $judul ??= $nama;

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n"
            . '    \'judul\' => ' . var_export($judul, true) . ",\n"
            . "    'tema'  => 'dapur',\n"
            . "    'modul' => [],\n];\n";
    }

    private function isiRute(): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n"
            . "use Mezzio\\Application;\nuse Mezzio\\MiddlewareFactory;\nuse Psr\\Container\\ContainerInterface;\n\n"
            . "// Route website ini, formatnya sama dengan config/routes.php inti.\n"
            . "return function (Application \$app, MiddlewareFactory \$factory, ContainerInterface \$container): void {\n};\n";
    }

    private function isiIndex(): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n"
            . "// Penanda bahwa request masuk lewat folder website: dashboard tidak dilayani dari sini.\n"
            . "define('DAPUR_WEBSITE_PATH', dirname(__DIR__));\n\n"
            . "// Jalankan inti Dapur\n"
            . 'require ' . var_export($this->folderInti . '/public/index.php', true) . ";\n";
    }
}
