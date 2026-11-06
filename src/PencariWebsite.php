<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

/**
 * Mencari website untuk sebuah nama host.
 *
 * Semua website wajib tercantum di daftar 'website' pada config/multiwebsite.local.php, dicari dari
 * nama atau aliasnya. Folder yang tidak terdaftar tidak pernah dilayani, walaupun ada.
 * Website terdaftar yang foldernya belum ada belum dilayani sampai foldernya dibuat.
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

    /** Website untuk host ini, atau null bila tidak terdaftar atau foldernya belum ada. */
    public function cari(string $host): ?Website
    {
        $nama = $this->namaTerdaftar($host);

        return $nama !== null ? $this->buat($nama, $this->websiteTerdaftar()[$nama]) : null;
    }

    /**
     * Nama website untuk host ini, dari nama atau aliasnya; null bila tidak terdaftar.
     * Nama website selalu menang atas alias website lain, tidak bergantung pada urutan penulisan.
     */
    public function namaTerdaftar(string $host): ?string
    {
        $terdaftar = $this->websiteTerdaftar();
        if (isset($terdaftar[$host])) {
            return $host;
        }

        foreach ($terdaftar as $nama => $data) {
            if (in_array($host, $this->normalkanSemua($data['alias'] ?? []), true)) {
                // Dibuka lewat alias tetap memakai nama website, sehingga cache dan datanya satu.
                return $nama;
            }
        }

        return null;
    }

    /**
     * Semua website yang terdaftar.
     *
     * @return array<string, Website|null> nama website => Website (null bila foldernya belum ada)
     */
    public function semua(): array
    {
        $hasil = [];
        foreach ($this->websiteTerdaftar() as $nama => $data) {
            $hasil[$nama] = $this->buat($nama, $data);
        }

        ksort($hasil);

        return $hasil;
    }

    /** @return array<string, array<mixed>> nama website (sudah dinormalkan) => pengaturannya */
    private function websiteTerdaftar(): array
    {
        $semua = $this->daftar['website'] ?? [];
        $hasil = [];
        foreach (is_array($semua) ? $semua : [] as $nama => $data) {
            $nama = NamaHost::normalkan((string) $nama);
            if ($nama !== null && ! isset($hasil[$nama])) {
                $hasil[$nama] = is_array($data) ? $data : [];
            }
        }

        return $hasil;
    }

    /** @param array<mixed> $data */
    private function buat(string $nama, array $data): ?Website
    {
        $lokasi = $data['lokasi'] ?? null;
        if (is_string($lokasi) && $lokasi !== '') {
            return is_dir($lokasi) ? Website::dariFolderDomain($nama, $lokasi) : null;
        }

        $website = Website::dariInti($nama, $this->folderInti);

        return is_dir($website->folderConfig) ? $website : null;
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
