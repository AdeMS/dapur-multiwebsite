<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\Bootstrap;
use AdeMS\Dapur\MultiWebsite\PembuatWebsite;
use AdeMS\Dapur\MultiWebsite\PencariWebsite;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PembuatWebsiteTest extends TestCase
{
    private string $folder;
    private string $inti;
    private string $fileDaftar;

    protected function setUp(): void
    {
        $this->folder     = sys_get_temp_dir() . '/dapur-multiwebsite-uji-' . bin2hex(random_bytes(6));
        $this->inti       = $this->folder . '/inti';
        $this->fileDaftar = $this->inti . '/config/multiwebsite.local.php';
        mkdir($this->inti . '/config', 0777, true);
        mkdir($this->inti . '/public/asset', 0777, true);
        file_put_contents($this->inti . '/public/.htaccess', "# aturan inti\n");
        file_put_contents($this->fileDaftar, <<<'PHP'
            <?php
            // Catatan pemilik
            return [
                'dashboard' => ['dash.uji'],
                'website'   => [
                    'lama.uji' => ['alias' => ['www.lama.uji']],
                ],
            ];
            PHP);
    }

    protected function tearDown(): void
    {
        $this->hapus($this->folder);
    }

    private function pembuat(): PembuatWebsite
    {
        return new PembuatWebsite($this->inti);
    }

    private function pencari(): PencariWebsite
    {
        return new PencariWebsite((array) Bootstrap::bacaDaftar($this->inti), $this->inti);
    }

    public function testMembuatWebsiteTerpusat(): void
    {
        $this->pembuat()->buat('Baru.Uji', null, [], 'Judul <Baru>');

        $website = $this->pencari()->cari('baru.uji');
        self::assertNotNull($website);
        self::assertSame($this->inti . '/config/website/baru.uji', $website->folderConfig);
        self::assertDirectoryExists($website->folderConfig . '/autoload');
        self::assertDirectoryExists($website->folderModul);
        self::assertSame('Judul <Baru>', (require $website->folderConfig . '/website.php')['judul']);
        self::assertIsCallable(require $website->folderConfig . '/routes.php');
    }

    public function testMembuatWebsiteFolderDomainDenganAlias(): void
    {
        $lokasi = $this->folder . '/domain.uji';
        $this->pembuat()->buat('domain.uji', $lokasi . '/', ['www.domain.uji', 'domain.co.uji']);

        $pencari = $this->pencari();
        self::assertSame($lokasi . '/config', $pencari->cari('domain.uji')?->folderConfig);
        self::assertSame('domain.uji', $pencari->cari('domain.co.uji')?->host);
        self::assertSame('domain.uji', (require $lokasi . '/config/website.php')['judul']);
        self::assertStringContainsString('DAPUR_WEBSITE_PATH', (string) file_get_contents($lokasi . '/public/index.php'));
        self::assertStringContainsString($this->inti . '/public/index.php', (string) file_get_contents($lokasi . '/public/index.php'));
        self::assertSame("# aturan inti\n", file_get_contents($lokasi . '/public/.htaccess'));
        self::assertSame($this->inti . '/public/asset', readlink($lokasi . '/public/asset'));
    }

    public function testFileLamaDanCatatanDaftarTetapUtuh(): void
    {
        $lokasi = $this->folder . '/domain.uji';
        mkdir($lokasi . '/config', 0777, true);
        file_put_contents($lokasi . '/config/website.php', '<?php return ["judul" => "Milik saya"];');

        $dibuat = $this->pembuat()->buat('domain.uji', $lokasi)->dibuat;

        self::assertSame('<?php return ["judul" => "Milik saya"];', file_get_contents($lokasi . '/config/website.php'));
        self::assertNotContains($lokasi . '/config/website.php', $dibuat);
        self::assertStringContainsString('// Catatan pemilik', (string) file_get_contents($this->fileDaftar));
        self::assertSame('lama.uji', $this->pencari()->namaTerdaftar('www.lama.uji'));
    }

    private function aturFolderUtama(): string
    {
        $folderUtama = $this->folder . '/web';
        file_put_contents(
            $this->fileDaftar,
            "<?php\nreturn ['folder_utama' => " . var_export($folderUtama . '/', true) . ", 'website' => []];\n"
        );

        return $folderUtama;
    }

    public function testFolderUtamaMenjadiLokasiBawaan(): void
    {
        $folderUtama = $this->aturFolderUtama();

        $hasil = $this->pembuat()->buat('baru.uji');

        self::assertSame($folderUtama . '/baru.uji', $hasil->lokasi);
        self::assertSame('baru.uji', $hasil->judul);
        self::assertFileExists($folderUtama . '/baru.uji/public/index.php');
        self::assertSame($folderUtama . '/baru.uji/config', $this->pencari()->cari('baru.uji')?->folderConfig);
    }

    public function testLokasiDanTerpusatMengalahkanFolderUtama(): void
    {
        $this->aturFolderUtama();
        $lokasi = $this->folder . '/lain.uji';

        self::assertSame($lokasi, $this->pembuat()->buat('lain.uji', $lokasi)->lokasi);

        $terpusat = $this->pembuat()->buat('pusat.uji', null, [], null, true);
        self::assertNull($terpusat->lokasi);
        self::assertSame($this->inti . '/config/website/pusat.uji', $this->pencari()->cari('pusat.uji')?->folderConfig);
    }

    public function testTanpaFolderUtamaWebsiteTerpusat(): void
    {
        self::assertNull($this->pembuat()->buat('baru.uji')->lokasi);
    }

    public function testLokasiDanTerpusatTidakBolehBersamaan(): void
    {
        $this->expectExceptionMessage('--terpusat');

        $this->pembuat()->buat('baru.uji', '/var/www/baru.uji', [], null, true);
    }

    public function testJudulYangDimintaDiabaikanBilaWebsiteSudahPunyaJudul(): void
    {
        $lokasi = $this->folder . '/domain.uji';
        mkdir($lokasi . '/config', 0777, true);
        file_put_contents($lokasi . '/config/website.php', '<?php return ["judul" => "Lama"];');

        $hasil = $this->pembuat()->buat('domain.uji', $lokasi, [], 'Baru');

        self::assertSame('Lama', $hasil->judul);
        self::assertSame('Baru', $hasil->judulDiabaikan);
        self::assertSame($lokasi . '/config/website.php', $hasil->fileWebsite);
    }

    public function testJudulSamaAtauTanpaJudulTidakDianggapDiabaikan(): void
    {
        $lokasi = $this->folder . '/domain.uji';
        mkdir($lokasi . '/config', 0777, true);
        file_put_contents($lokasi . '/config/website.php', '<?php return ["judul" => "Lama"];');

        $this->assertNull($this->pembuat()->buat('domain.uji', $lokasi, [], 'Lama')->judulDiabaikan);
        $this->assertNull($this->pembuat()->buat('baru.uji')->judulDiabaikan);
    }

    public function testDaftarDenganWebsiteKosong(): void
    {
        file_put_contents($this->fileDaftar, "<?php\nreturn ['dashboard' => ['dash.uji'], 'website' => []];\n");

        $this->pembuat()->buat('baru.uji');

        self::assertNotNull($this->pencari()->cari('baru.uji'));
    }

    /** @return iterable<string, array{string, ?string, list<string>, string}> */
    public static function masukanDitolak(): iterable
    {
        yield 'nama tidak sah'      => ['../etc', null, [], 'tidak sah'];
        yield 'alias tidak sah'     => ['baru.uji', null, ['a/b'], 'tidak sah'];
        yield 'nama sudah ada'      => ['lama.uji', null, [], 'sudah terdaftar'];
        yield 'nama = alias lain'   => ['www.lama.uji', null, [], 'sudah terdaftar'];
        yield 'alias sudah ada'     => ['baru.uji', null, ['lama.uji'], 'sudah terdaftar'];
        yield 'nama dashboard'      => ['dash.uji', null, [], 'dashboard'];
        yield 'alias dashboard'     => ['baru.uji', null, ['dash.uji'], 'dashboard'];
        yield 'lokasi relatif'      => ['baru.uji', 'folder/baru', [], 'path absolut'];
        yield 'lokasi naik folder'  => ['baru.uji', '/var/www/../baru', [], 'path absolut'];
    }

    /** @param list<string> $alias */
    #[\PHPUnit\Framework\Attributes\DataProvider('masukanDitolak')]
    public function testMasukanDitolakTanpaMembuatApaPun(string $nama, ?string $lokasi, array $alias, string $pesan): void
    {
        $sebelum = file_get_contents($this->fileDaftar);

        try {
            $this->pembuat()->buat($nama, $lokasi, $alias);
            self::fail('Seharusnya ditolak');
        } catch (RuntimeException $e) {
            self::assertStringContainsString($pesan, $e->getMessage());
        }

        self::assertSame($sebelum, file_get_contents($this->fileDaftar));
        self::assertDirectoryDoesNotExist($this->inti . '/config/website');
    }

    public function testDaftarYangTidakBisaDiubahTidakMembuatFolder(): void
    {
        file_put_contents($this->fileDaftar, "<?php\n\$website = [];\nreturn ['website' => \$website];\n");

        try {
            $this->pembuat()->buat('baru.uji');
            self::fail('Seharusnya ditolak');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("'baru.uji' => []", $e->getMessage());
        }

        self::assertDirectoryDoesNotExist($this->inti . '/config/website');
    }

    private function hapus(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $isi) {
            if ($isi !== '.' && $isi !== '..') {
                $this->hapus($path . '/' . $isi);
            }
        }
        rmdir($path);
    }
}
