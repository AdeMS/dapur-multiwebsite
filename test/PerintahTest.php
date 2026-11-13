<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\Perintah;
use PHPUnit\Framework\TestCase;

final class PerintahTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/dapur-multiwebsite-perintah-' . bin2hex(random_bytes(6));
        foreach (['inti/config/website/terpusat.uji', 'domain.uji/config'] as $sub) {
            mkdir($this->folder . '/' . $sub, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->hapus($this->folder);
    }

    public function testFileLogErrorSemuaTanpaMultiWebsite(): void
    {
        self::assertNull((new Perintah($this->folder . '/inti'))->fileLogErrorSemua('/log/inti.log'));
    }

    public function testFileLogErrorSemua(): void
    {
        file_put_contents($this->folder . '/inti/config/multiwebsite.local.php', '<?php return ' . var_export([
            'dashboard' => ['dash.uji'],
            'website'   => [
                'domain.uji'   => ['lokasi' => $this->folder . '/domain.uji'],
                'terpusat.uji' => [],
                'hilang.uji'   => ['lokasi' => $this->folder . '/hilang.uji'],
            ],
        ], true) . ';');

        self::assertSame([
            '(inti)'       => '/log/inti.log',
            'domain.uji'   => $this->folder . '/domain.uji/data/log/error.log',
            'hilang.uji'   => null,
            'terpusat.uji' => $this->folder . '/inti/data/website/terpusat.uji/log/error.log',
        ], (new Perintah($this->folder . '/inti'))->fileLogErrorSemua('/log/inti.log'));
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
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
