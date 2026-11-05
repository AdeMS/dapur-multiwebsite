<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\PencariWebsite;
use PHPUnit\Framework\TestCase;

final class PencariWebsiteTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/dapur-multiwebsite-uji-' . bin2hex(random_bytes(6));
        foreach (['inti/config/website/terpusat.uji', 'a.uji/config', 'x.uji/config', 'domain.uji/public'] as $sub) {
            mkdir($this->folder . '/' . $sub, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->hapus($this->folder);
    }

    private function pencari(array $website = [], array $dashboard = ['dash.uji']): PencariWebsite
    {
        return new PencariWebsite(['dashboard' => $dashboard, 'website' => $website], $this->folder . '/inti');
    }

    public function testMengenaliDashboard(): void
    {
        $pencari = $this->pencari();

        self::assertTrue($pencari->adalahDashboard('dash.uji'));
        self::assertFalse($pencari->adalahDashboard('a.uji'));
    }

    public function testWebsiteTerdaftarModeFolderDomain(): void
    {
        $website = $this->pencari(['a.uji' => ['lokasi' => $this->folder . '/a.uji']])->cari('a.uji');

        self::assertNotNull($website);
        self::assertSame('a.uji', $website->host);
        self::assertSame($this->folder . '/a.uji/config', $website->folderConfig);
        self::assertSame($this->folder . '/a.uji/data/cache/config-cache.php', $website->fileCacheKonfigurasi());
    }

    public function testWebsiteTerpusatDitemukanTanpaDidaftarkan(): void
    {
        $website = $this->pencari()->cari('terpusat.uji');

        self::assertNotNull($website);
        self::assertSame($this->folder . '/inti/config/website/terpusat.uji', $website->folderConfig);
        self::assertSame($this->folder . '/inti/data/website/terpusat.uji', $website->folderData);
    }

    public function testBanyakAliasMembukaWebsiteYangSama(): void
    {
        $pencari = $this->pencari([
            'a.uji' => ['lokasi' => $this->folder . '/a.uji', 'alias' => ['www.a.uji', 'a.co.uji']],
        ]);

        foreach (['www.a.uji', 'a.co.uji'] as $alias) {
            self::assertSame('a.uji', $pencari->cari($alias)?->host, $alias);
        }
    }

    public function testAliasBolehBerupaString(): void
    {
        $pencari = $this->pencari(['terpusat.uji' => ['alias' => 'www.terpusat.uji']]);

        self::assertSame('terpusat.uji', $pencari->cari('www.terpusat.uji')?->host);
    }

    public function testNamaWebsiteMenangAtasAliasWebsiteLain(): void
    {
        $pencari = $this->pencari([
            'x.uji' => ['lokasi' => $this->folder . '/x.uji', 'alias' => ['a.uji', 'bersama.uji']],
            'a.uji' => ['lokasi' => $this->folder . '/a.uji', 'alias' => ['bersama.uji']],
        ]);

        self::assertSame('a.uji', $pencari->cari('a.uji')?->host);
        self::assertSame('x.uji', $pencari->cari('bersama.uji')?->host, 'alias ganda: yang ditulis pertama');
    }

    public function testLokasiYangTidakAdaTidakDitemukan(): void
    {
        $pencari = $this->pencari(['hilang.uji' => ['lokasi' => $this->folder . '/tidak-ada']]);

        self::assertNull($pencari->cari('hilang.uji'));
    }

    public function testTerdaftarTanpaFolderTerpusatTidakDitemukan(): void
    {
        self::assertNull($this->pencari(['kosong.uji' => []])->cari('kosong.uji'));
    }

    public function testDaftarMenangAtasFolderDomain(): void
    {
        $pencari = $this->pencari(['a.uji' => ['lokasi' => $this->folder . '/a.uji']]);

        self::assertSame($this->folder . '/a.uji/config', $pencari->cari('a.uji', $this->folder . '/x.uji')?->folderConfig);
    }

    public function testNamaFolderDomainDiambilDariFolderBukanHost(): void
    {
        $website = $this->pencari()->cari('penyerang.uji', $this->folder . '/domain.uji');

        self::assertNotNull($website);
        self::assertSame('domain.uji', $website->host);
        self::assertSame($this->folder . '/domain.uji/config', $website->folderConfig);
    }

    public function testFolderDomainDenganNamaTidakSahTidakDitemukan(): void
    {
        mkdir($this->folder . '/nama_salah');

        self::assertNull($this->pencari()->cari('domain.uji', $this->folder . '/nama_salah'));
    }

    public function testHostTakDikenalTidakDitemukan(): void
    {
        self::assertNull($this->pencari()->cari('asing.uji'));
    }

    public function testSemuaMenggabungkanDaftarDanTerpusat(): void
    {
        $semua = $this->pencari([
            'a.uji'      => ['lokasi' => $this->folder . '/a.uji'],
            'hilang.uji' => ['lokasi' => $this->folder . '/tidak-ada'],
        ])->semua();

        self::assertSame(['a.uji', 'hilang.uji', 'terpusat.uji'], array_keys($semua));
        self::assertNull($semua['hilang.uji']);
        self::assertSame('terpusat.uji', $semua['terpusat.uji']?->host);
    }

    private function hapus(string $path): void
    {
        if (is_dir($path) && ! is_link($path)) {
            foreach (scandir($path) ?: [] as $isi) {
                if ($isi !== '.' && $isi !== '..') {
                    $this->hapus($path . '/' . $isi);
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}
