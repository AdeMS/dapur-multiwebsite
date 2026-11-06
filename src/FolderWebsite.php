<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Folder data website yang dibuat oleh web server.
 *
 * Izin diatur dengan chmod setelah mkdir karena hasil mkdir terpotong oleh umask web server,
 * sehingga anggota grup tidak bisa menulis dan cache tidak bisa dihapus dari baris perintah.
 */
final class FolderWebsite
{
    public const IZIN = 02770;

    /** Membuat folder beserta induknya yang belum ada; folder yang sudah ada tidak diubah. */
    public static function buat(string $folder): void
    {
        $belumAda = [];
        while (! is_dir($folder) && $folder !== dirname($folder)) {
            $belumAda[] = $folder;
            $folder     = dirname($folder);
        }

        foreach (array_reverse($belumAda) as $baru) {
            if (@mkdir($baru) || is_dir($baru)) {
                @chmod($baru, self::IZIN);
            }
        }
    }
}
