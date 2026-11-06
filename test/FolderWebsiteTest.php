<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\CacheKonfigurasi;
use AdeMS\Dapur\MultiWebsite\FolderWebsite;
use PHPUnit\Framework\TestCase;

final class FolderWebsiteTest extends TestCase
{
    private string $folder;
    private int $umaskAwal;

    protected function setUp(): void
    {
        $this->umaskAwal = umask();
        $this->folder    = sys_get_temp_dir() . '/dapur-multiwebsite-uji-' . bin2hex(random_bytes(6));
        mkdir($this->folder, 0755);
    }

    protected function tearDown(): void
    {
        umask($this->umaskAwal);
        $this->hapus($this->folder);
    }

    public function testFolderBaruMendapatIzinGrupWalauUmaskKetat(): void
    {
        umask(027);
        FolderWebsite::buat($this->folder . '/data/cache/app');

        foreach (['data', 'data/cache', 'data/cache/app'] as $sub) {
            self::assertSame(FolderWebsite::IZIN, fileperms($this->folder . '/' . $sub) & 07777, $sub);
        }
    }

    public function testFolderYangSudahAdaTidakDiubah(): void
    {
        FolderWebsite::buat($this->folder . '/data');

        self::assertSame(0755, fileperms($this->folder) & 07777);
    }

    public function testMenghapusCacheYangAda(): void
    {
        $file = $this->folder . '/config-cache.php';
        touch($file);

        self::assertSame(CacheKonfigurasi::DIHAPUS, CacheKonfigurasi::hapus($file));
        self::assertFileDoesNotExist($file);
    }

    public function testCacheYangTidakAda(): void
    {
        self::assertSame(CacheKonfigurasi::TIDAK_ADA, CacheKonfigurasi::hapus($this->folder . '/a/b/config-cache.php'));
    }

    public function testFolderYangTidakBisaDibacaTidakDilaporkanKosong(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root selalu bisa membaca folder');
        }

        $tertutup = $this->folder . '/tertutup';
        mkdir($tertutup);
        touch($tertutup . '/config-cache.php');
        chmod($tertutup, 0);

        $status = CacheKonfigurasi::hapus($tertutup . '/config-cache.php');
        chmod($tertutup, 0755);

        self::assertSame(CacheKonfigurasi::TIDAK_DIPERIKSA, $status);
        self::assertFalse(CacheKonfigurasi::berhasil($status));
        self::assertFileExists($tertutup . '/config-cache.php');
    }

    private function hapus(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $isi) {
                if ($isi !== '.' && $isi !== '..') {
                    $this->hapus($path . '/' . $isi);
                }
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
