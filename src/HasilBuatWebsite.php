<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/** Ringkasan website yang baru dibuat, agar opsi yang benar-benar dipakai terlihat oleh admin. */
final class HasilBuatWebsite
{
    /**
     * @param list<string> $alias
     * @param list<string> $dibuat file dan folder yang dibuat
     * @param ?string $judulDiabaikan judul yang diminta tetapi tidak dipakai, karena website.php sudah ada
     */
    public function __construct(
        public readonly string $nama,
        public readonly ?string $lokasi,
        public readonly string $judul,
        public readonly array $alias,
        public readonly array $dibuat,
        public readonly string $fileWebsite,
        public readonly ?string $judulDiabaikan = null,
    ) {
    }
}
