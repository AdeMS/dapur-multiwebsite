# Dapur AdeMS Multi-Website

Paket `adems/dapur-multiwebsite` membuat satu instalasi [Dapur AdeMS](https://github.com/AdeMS/dapur)
(disebut **inti**) melayani banyak website. Setiap website punya konfigurasi, route, modul, cache,
log, dan upload sendiri, sementara kode inti dan `vendor/` cukup satu.

Tanpa daftar website (`config/multiwebsite.local.php`), paket ini tidak melakukan apa pun dan
Dapur berjalan sebagai satu website seperti biasa.

## Persyaratan

- Dapur AdeMS 2.4 atau lebih baru (memiliki kait `DAPUR_CONFIG_CACHE_PATH` dan
  `DAPUR_CONFIG_PROVIDERS` di `config/config.php`)
- PHP 8.2 sampai 8.5

## Instalasi

Jalankan di folder inti Dapur:

```bash
composer require adems/dapur-multiwebsite
```

Paket bekerja otomatis lewat autoload Composer; tidak perlu didaftarkan di
`config/modul.local.php`. Multi-website aktif setelah daftar website dibuat:

```bash
cp vendor/adems/dapur-multiwebsite/config/multiwebsite.local.php.dist config/multiwebsite.local.php
```

File `config/multiwebsite.local.php`, folder `config/website/`, dan folder `data/website/`
berisi data milik instalasi Anda (nama domain, lokasi folder, password database website), dan
sudah diabaikan Git oleh inti Dapur.

## Daftar website

```php
<?php

return [
    // Host yang menampilkan inti Dapur (dashboard)
    'dashboard' => ['dashboard.contoh.com'],

    'website' => [
        // Mode folder domain
        'contoh.com' => [
            'lokasi' => '/var/www/contoh.com',
            'alias'  => ['www.contoh.com', 'contoh.co.id', 'www.contoh.co.id'],
        ],

        // Mode terpusat (tanpa 'lokasi')
        'blog.contoh.com' => [
            'alias' => ['www.blog.contoh.com'],
        ],
    ],
];
```

- **`dashboard`**: host yang menampilkan inti. Dashboard hanya dilayani lewat `public/` inti,
  tidak lewat folder website mana pun.
- **`website`**: kuncinya nama website, yaitu domain utamanya. `lokasi` menunjuk folder website
  dalam mode folder domain.
- **`alias`**: domain lain yang membuka website yang sama, boleh lebih dari satu. Cache dan data
  website tetap satu, dan `$config['website']['host']` tetap berisi nama website. Nama website
  selalu menang atas alias website lain; bila satu alias tercantum di dua website, yang pertama
  ditulis yang dipakai.

> Untuk website publik, mesin pencari menganggap alias sebagai salinan halaman yang sama.
> Bila itu tidak diinginkan, alihkan alias ke domain utama di web server, misalnya lewat
> `.htaccess`:
>
> ```apache
> RewriteCond %{HTTP_HOST} !^contoh\.com$ [NC]
> RewriteRule ^ https://contoh.com%{REQUEST_URI} [L,R=301]
> ```

Host yang tidak dikenal, termasuk akses lewat alamat IP, mendapat halaman
**"Website tidak ditemukan"** (kode 404) tanpa memuat konfigurasi apa pun.

### Urutan pencarian website

1. Daftar `website` di `config/multiwebsite.local.php` (nama host atau alias).
2. Folder terpusat `config/website/<host>/` di inti.
3. Folder domain yang memanggil inti lewat `public/index.php` miliknya.

Yang dikelola di inti selalu menang atas folder domain.

## Susunan folder website

Isi folder website sama di kedua mode; hanya folder akarnya yang berbeda.

### Mode folder domain

Document root domain mengarah ke `public/` milik website:

```
/var/www/contoh.com/
├── config/
│   ├── autoload/          ← *.global.php dan *.local.php milik website (menimpa bawaan inti)
│   ├── routes.php         ← route website
│   └── website.php        ← judul, tema, daftar modul website
├── data/
│   ├── cache/             ← cache konfigurasi dan cache data aplikasi website
│   ├── log/
│   └── upload/
├── module/                ← modul khusus website: Nama\ dimuat dari module/Nama/src/
└── public/
    ├── .htaccess          ← salin dari public/.htaccess inti
    ├── asset              ← symlink ke public/asset inti
    └── index.php
```

`public/index.php` milik website cukup beberapa baris:

```php
<?php

declare(strict_types=1);

// Folder website ini
define('DAPUR_WEBSITE_PATH', dirname(__DIR__));

// Jalankan inti Dapur
require '/var/www/dapur/public/index.php';
```

### Mode terpusat

Document root domain langsung mengarah ke `public/` inti; website dikenali dari nama host.

```
<inti>/config/website/blog.contoh.com/   ← autoload/, routes.php, website.php, module/
<inti>/data/website/blog.contoh.com/     ← cache/, log/, upload/
```

## Konfigurasi website

Urutan penggabungan konfigurasi:

1. Bawaan inti: paket Laminas/Mezzio, modul `App`, modul inti (`config/modul.local.php`),
   dan `config/autoload/` inti.
2. Modul pilihan website (`'modul'` di `website.php`).
3. Bawaan paket ini (folder cache data aplikasi website, route website).
4. `config/autoload/*.php` milik website.

Karena lapisan website dimuat paling akhir, `config/autoload/database.local.php` milik website
menimpa koneksi database inti.

Contoh `config/website.php`:

```php
<?php

return [
    'judul' => 'Contoh',
    'tema'  => 'dapur',
    'modul' => [
        Toko\ConfigProvider::class,     // dari module/Toko/src/ConfigProvider.php
    ],
];
```

Isinya bisa dibaca di aplikasi lewat `$config['website']`, ditambah kunci `host`.

`config/routes.php` milik website memakai format yang sama dengan `config/routes.php` inti.
Route website didaftarkan lebih dulu daripada route inti; path yang sama tidak boleh didaftarkan
dua kali. Route dan modul inti berlaku untuk semua website.

## Perintah Composer untuk website tertentu

Tambahkan `-- --website=<host>` pada perintah Composer inti:

```bash
composer clear-config-cache -- --website=contoh.com
composer cek-database -- --website=contoh.com
```

Atau pakai variabel lingkungan `DAPUR_WEBSITE=contoh.com`. Tanpa opsi, perintah berjalan untuk
inti. Website mode folder domain harus tercantum di daftar website agar bisa dipilih dari baris
perintah.

Setelah mengubah `config/multiwebsite.local.php`, `website.php`, daftar modul, atau
`config/autoload/` website, hapus cache konfigurasi website tersebut.

## Izin folder

Folder `data/` website diisi oleh web server (cache konfigurasi juga memuat password database):

- `data/cache/` dan `data/log/`: hanya pemilik dan grup web server, misalnya `2770`.
- `data/upload/`: boleh ditulis web server, tetapi berada di luar `public/` dan PHP tidak boleh
  dijalankan di sana.

Folder `data/cache/app` dibuat otomatis bila belum ada.

## Lisensi

BSD 3-Clause. Lihat [LICENSE.md](LICENSE.md).
