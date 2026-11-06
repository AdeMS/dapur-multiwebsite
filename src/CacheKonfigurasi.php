<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Menghapus file cache konfigurasi dan melaporkan hasilnya apa adanya.
 *
 * File di dalam folder yang tidak bisa dibaca terlihat seperti tidak ada, sehingga izin folder
 * diperiksa lebih dulu agar tidak dilaporkan "tidak ada cache" padahal cache-nya ada.
 */
final class CacheKonfigurasi
{
    public const DIHAPUS         = 'dihapus';
    public const TIDAK_ADA       = 'tidak ada cache';
    public const TIDAK_DIPERIKSA = 'TIDAK BISA DIPERIKSA (periksa izin folder)';
    public const GAGAL           = 'GAGAL dihapus (periksa izin folder)';

    public static function hapus(string $file): string
    {
        $folder = dirname($file);
        while (! is_dir($folder) && $folder !== dirname($folder)) {
            $folder = dirname($folder);
        }

        if (! is_readable($folder) || ! is_executable($folder)) {
            return self::TIDAK_DIPERIKSA;
        }

        if (! is_file($file)) {
            return self::TIDAK_ADA;
        }

        return @unlink($file) ? self::DIHAPUS : self::GAGAL;
    }

    public static function berhasil(string $status): bool
    {
        return $status === self::DIHAPUS || $status === self::TIDAK_ADA;
    }
}
