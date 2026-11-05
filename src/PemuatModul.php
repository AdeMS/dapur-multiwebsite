<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Autoloader untuk modul milik website: kelas Nama\Sub\Kelas dimuat dari
 * <folder modul>/Nama/src/Sub/Kelas.php (PSR-4, satu namespace per folder modul).
 */
final class PemuatModul
{
    public static function daftarkan(string $folderModul): void
    {
        if (! is_dir($folderModul)) {
            return;
        }

        spl_autoload_register(static function (string $kelas) use ($folderModul): void {
            // Hanya nama kelas PHP yang sah, agar tidak bisa dipakai untuk memuat file lain.
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\\\\([A-Za-z_][A-Za-z0-9_\\\\]*)$/', $kelas, $bagian) !== 1) {
                return;
            }

            $file = $folderModul . '/' . $bagian[1] . '/src/' . str_replace('\\', '/', $bagian[2]) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
