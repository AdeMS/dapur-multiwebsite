<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Mezzio\Application;

/**
 * Lapisan konfigurasi website, dimuat paling akhir oleh config/config.php inti
 * (lewat konstanta DAPUR_CONFIG_PROVIDERS).
 *
 * Urutan di dalamnya: modul pilihan website ('modul' di website.php) -> bawaan paket
 * (folder cache, log error, routes website) -> config/autoload/*.php milik website.
 */
final class WebsiteConfigProvider
{
    public function __invoke(): array
    {
        $website = Website::aktif();
        if ($website === null) {
            return [];
        }

        $pengaturan = $this->bacaPengaturan($website);
        $modul      = $pengaturan['modul'] ?? [];

        $aggregator = new ConfigAggregator([
            ...(is_array($modul) ? array_values($modul) : []),
            new ArrayProvider($this->bawaan($website, $pengaturan)),
            new PhpFileProvider($website->folderConfig . '/autoload/{{,*.}global,{,*.}local}.php'),
        ]);

        return $aggregator->getMergedConfig();
    }

    /** Isi config/website.php milik website (judul, tema, modul, ...). */
    private function bacaPengaturan(Website $website): array
    {
        $file = $website->folderConfig . '/website.php';
        if (! is_file($file)) {
            return [];
        }

        $isi = require $file;

        return is_array($isi) ? $isi : [];
    }

    private function bawaan(Website $website, array $pengaturan): array
    {
        $folderCache = $website->folderCacheAplikasi();
        FolderWebsite::buat($folderCache);

        return [
            // Pengaturan website, bisa dibaca dari $config['website'].
            'website' => ['host' => $website->host] + $pengaturan,

            // Cache data aplikasi milik website sendiri.
            'caches' => [
                'Laminas\Cache\Storage\StorageInterface' => [
                    'options' => ['cache_dir' => $folderCache],
                ],
            ],

            // Log error milik website sendiri (dipakai inti Dapur 2.8 atau lebih baru).
            'log' => ['error' => $website->fileLogError()],

            // Memuat config/routes.php milik website.
            'dependencies' => [
                'delegators' => [
                    Application::class => [RuteWebsiteDelegator::class],
                ],
            ],
        ];
    }
}
