<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\NamaHost;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamaHostTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function hostSah(): iterable
    {
        yield 'biasa'            => ['contoh.com', 'contoh.com'];
        yield 'huruf besar'      => ['WWW.Contoh.COM', 'www.contoh.com'];
        yield 'dengan port'      => ['contoh.com:8080', 'contoh.com'];
        yield 'titik di akhir'   => ['contoh.com.', 'contoh.com'];
        yield 'spasi di tepi'    => ['  contoh.com ', 'contoh.com'];
        yield 'tanda hubung'     => ['toko-baju.contoh.co.id', 'toko-baju.contoh.co.id'];
        yield 'satu label'       => ['localhost', 'localhost'];
    }

    #[DataProvider('hostSah')]
    public function testMenormalkanHostSah(string $masukan, string $harapan): void
    {
        self::assertSame($harapan, NamaHost::normalkan($masukan));
    }

    /** @return iterable<string, array{string}> */
    public static function hostTidakSah(): iterable
    {
        yield 'kosong'               => [''];
        yield 'naik folder'          => ['../../etc'];
        yield 'garis miring'         => ['contoh.com/admin'];
        yield 'garis miring terbalik' => ['contoh\\com'];
        yield 'titik ganda'          => ['contoh..com'];
        yield 'diawali titik'        => ['.contoh.com'];
        yield 'diawali tanda hubung' => ['-contoh.com'];
        yield 'diakhiri tanda hubung' => ['contoh-.com'];
        yield 'garis bawah'          => ['con_toh.com'];
        yield 'ipv6'                 => ['[::1]'];
        yield 'karakter null'        => ["contoh.com\0.evil"];
        yield 'label terlalu panjang' => [str_repeat('a', 64) . '.com'];
        yield 'nama terlalu panjang' => [str_repeat('a.', 127) . 'com'];
    }

    #[DataProvider('hostTidakSah')]
    public function testMenolakHostTidakSah(string $masukan): void
    {
        self::assertNull(NamaHost::normalkan($masukan));
    }
}
