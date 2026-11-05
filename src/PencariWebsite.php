<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Mencari website untuk sebuah nama host.
 *
 * Urutan pencarian (yang dikelola di inti selalu menang):
 * 1. daftar 'website' di config/multiwebsite.local.php (nama website = nama domain);
 * 2. folder terpusat config/website/<host>/ di inti;
 * 3. folder domain yang dinyatakan oleh index.php website (konstanta DAPUR_WEBSITE_PATH).
 */
final class PencariWebsite
{
    /**
     * @param array<mixed> $daftar     isi config/multiwebsite.local.php
     * @param string       $folderInti folder akar inti Dapur
     */
    public function __construct(
        private readonly array $daftar,
        private readonly string $folderInti,
    ) {
    }

    /** Apakah host ini menampilkan inti (dashboard), bukan website. */
    public function adalahDashboard(string $host): bool
    {
        return in_array($host, $this->normalkanSemua($this->daftar['dashboard'] ?? []), true);
    }

    public function cari(string $host, ?string $folderDomain = null): ?Website
    {
        // 1. Daftar website
        $semua = $this->daftar['website'] ?? [];
        foreach (is_array($semua) ? $semua : [] as $nama => $data) {
            $nama = NamaHost::normalkan((string) $nama);
            $data = is_array($data) ? $data : [];
            // Nama website sama dengan nama domainnya.
            if ($nama === null || $nama !== $host) {
                continue;
            }

            $lokasi = $data['lokasi'] ?? null;
            if (is_string($lokasi) && $lokasi !== '') {
                return is_dir($lokasi) ? Website::dariFolderDomain($nama, $lokasi) : null;
            }

            $website = Website::dariInti($nama, $this->folderInti);

            return is_dir($website->folderConfig) ? $website : null;
        }

        // 2. Folder terpusat di inti
        $website = Website::dariInti($host, $this->folderInti);
        if (is_dir($website->folderConfig)) {
            return $website;
        }

        // 3. Folder domain yang memanggil inti
        if ($folderDomain !== null && $folderDomain !== '' && is_dir($folderDomain)) {
            return Website::dariFolderDomain($host, $folderDomain);
        }

        return null;
    }

    /** @return list<string> */
    private function normalkanSemua(mixed $daftarHost): array
    {
        $hasil = [];
        foreach (is_array($daftarHost) ? $daftarHost : [$daftarHost] as $host) {
            $host = is_string($host) ? NamaHost::normalkan($host) : null;
            if ($host !== null) {
                $hasil[] = $host;
            }
        }

        return $hasil;
    }
}
