<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Website yang sedang dilayani: nama host dan folder-foldernya.
 *
 * Susunan folder sama di kedua mode, hanya folder akarnya yang berbeda:
 * - mode folder domain: <lokasi>/config, <lokasi>/module, <lokasi>/data
 * - mode terpusat:      <inti>/config/website/<host>, .../module, <inti>/data/website/<host>
 */
final class Website
{
    private static ?self $aktif = null;

    public function __construct(
        public readonly string $host,
        public readonly string $folderConfig,
        public readonly string $folderModul,
        public readonly string $folderData,
    ) {
    }

    /** Website di folder domain sendiri (folder berisi public/, config/, module/, data/). */
    public static function dariFolderDomain(string $host, string $lokasi): self
    {
        $lokasi = rtrim($lokasi, '/');

        return new self($host, $lokasi . '/config', $lokasi . '/module', $lokasi . '/data');
    }

    /** Website terpusat di folder config/website/<host> dan data/website/<host> milik inti. */
    public static function dariInti(string $host, string $folderInti): self
    {
        $folderConfig = $folderInti . '/config/website/' . $host;

        return new self($host, $folderConfig, $folderConfig . '/module', $folderInti . '/data/website/' . $host);
    }

    public static function aktif(): ?self
    {
        return self::$aktif;
    }

    public static function aktifkan(self $website): void
    {
        self::$aktif = $website;
    }

    public function fileCacheKonfigurasi(): string
    {
        return $this->folderData . '/cache/config-cache.php';
    }

    public function folderCacheAplikasi(): string
    {
        return $this->folderData . '/cache/app';
    }
}
