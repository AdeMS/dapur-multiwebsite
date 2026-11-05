<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Tanggapan saat website tidak bisa dilayani. Dikirim sebelum konfigurasi apa pun dimuat,
 * sehingga tidak ada informasi website lain atau inti yang ikut terbuka.
 */
final class Halaman
{
    public static function websiteTidakDitemukan(string $host): never
    {
        if (PHP_SAPI === 'cli') {
            self::keluarCli(sprintf("Website '%s' tidak ditemukan di daftar multi-website.", $host));
        }

        self::kirim(404, (string) file_get_contents(__DIR__ . '/../templates/website-tidak-ditemukan.html'));
    }

    public static function konfigurasiTidakValid(string $file): never
    {
        if (PHP_SAPI === 'cli') {
            self::keluarCli(sprintf("File '%s' harus mengembalikan array.", $file));
        }

        // Jangan tampilkan path file kepada pengunjung.
        self::kirim(500, '<!DOCTYPE html><html lang="id"><meta charset="utf-8">'
            . '<title>Kesalahan konfigurasi</title><p>Konfigurasi multi-website tidak valid.</p></html>');
    }

    public static function intiTidakMendukung(): never
    {
        if (PHP_SAPI === 'cli') {
            self::keluarCli('Inti Dapur belum mendukung multi-website; perbarui ke Dapur AdeMS 2.4 atau lebih baru.');
        }

        self::kirim(500, '<!DOCTYPE html><html lang="id"><meta charset="utf-8">'
            . '<title>Kesalahan konfigurasi</title><p>Konfigurasi multi-website tidak valid.</p></html>');
    }

    private static function kirim(int $kode, string $html): never
    {
        if (! headers_sent()) {
            http_response_code($kode);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }

        echo $html;
        exit;
    }

    private static function keluarCli(string $pesan): never
    {
        fwrite(STDERR, $pesan . PHP_EOL);
        exit(1);
    }
}
