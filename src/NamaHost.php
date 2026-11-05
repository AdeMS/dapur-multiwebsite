<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Menormalkan dan memvalidasi nama host.
 *
 * Nama host berasal dari request (header Host) atau dari baris perintah, jadi tidak bisa
 * dipercaya. Hanya huruf kecil, angka, tanda hubung, dan titik di antara label yang diterima,
 * sehingga nama host aman dipakai sebagai bagian path folder (tidak bisa berisi "/" atau "..").
 */
final class NamaHost
{
    private const POLA = '/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/';

    /** Mengembalikan nama host yang sudah dinormalkan, atau null bila tidak valid. */
    public static function normalkan(string $host): ?string
    {
        $host = strtolower(trim($host));

        // Buang nomor port (contoh.com:8080). Alamat IPv6 ([::1]) sengaja tidak diterima.
        $host = preg_replace('/:\d{1,5}$/', '', $host) ?? '';

        // Buang titik di akhir nama (contoh.com.)
        $host = rtrim($host, '.');

        return preg_match(self::POLA, $host) === 1 ? $host : null;
    }
}
