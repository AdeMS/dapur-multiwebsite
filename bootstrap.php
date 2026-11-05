<?php

/**
 * Dijalankan otomatis oleh vendor/autoload.php (autoload "files" di composer.json),
 * sebelum config/config.php inti dimuat.
 *
 * Menentukan website mana yang sedang dilayani, lalu mengisi konstanta
 * DAPUR_CONFIG_CACHE_PATH dan DAPUR_CONFIG_PROVIDERS yang dibaca oleh inti Dapur.
 * Tanpa config/multiwebsite.local.php di inti, file ini tidak melakukan apa pun.
 */

declare(strict_types=1);

if (! defined('DAPUR_MULTIWEBSITE_BOOTSTRAP')) {
    define('DAPUR_MULTIWEBSITE_BOOTSTRAP', true);
    AdeMS\Dapur\MultiWebsite\Bootstrap::jalankan();
}
