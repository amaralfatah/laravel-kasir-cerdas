# Dokumentasi Migrasi Database

## Standar Pengembangan Migrasi Database

### Timestamp Migrasi
Sistem ini menggunakan timestamp migrasi dengan format `YYYY_MM_DD_HHMMSS`. Meskipun beberapa migrasi masih menggunakan timestamp 2025, untuk pengembangan selanjutnya gunakan timestamp saat ini.

### Urutan Migrasi
Migrasi dasar yang harus dijalankan terlebih dahulu:

1. `0001_01_01_000000_create_shops_table.php`
2. `0001_01_01_000001_create_users_table.php`
3. `0001_01_01_000002_create_cache_table.php`
4. `0001_01_01_000003_create_jobs_table.php`

Selanjutnya, migrasi tabel dependent akan mengikuti urutan timestamp.

### Panduan Pembuatan Migrasi Baru

Untuk membuat migrasi baru, gunakan command artisan:

```bash
php artisan make:migration nama_migrasi
```

Pastikan untuk mengikuti panduan berikut:

1. **Konvensi Penamaan**:
   - Nama tabel: huruf kecil, jamak (contoh: `products`, `users`)
   - Foreign key: nama tabel singular + `_id` (contoh: `user_id`, `product_id`)

2. **Struktur Standar**:
   - Semua tabel utama harus memiliki `id`, `timestamps()`, dan `softDeletes()`
   - Semua foreign key harus menggunakan `constrained()`
   - Semua kolom harus memiliki `comment()` untuk dokumentasi

3. **Dokumentasi**:
   - Tambahkan dokumentasi pada fungsi `up()` untuk menjelaskan tujuan migrasi
   - Jelaskan relasi dengan tabel lain
   - Jelaskan alur data yang melibatkan tabel tersebut

4. **Indeks dan Performa**:
   - Tambahkan indeks untuk kolom yang sering digunakan dalam pencarian
   - Gunakan prefiks `idx_` untuk indeks biasa
   - Gunakan prefiks `unq_` untuk indeks unik

## Menjalankan Migrasi

```bash
# Menjalankan migrasi
php artisan migrate

# Rollback migrasi terakhir
php artisan migrate:rollback

# Reset dan jalankan ulang semua migrasi
php artisan migrate:fresh

# Reset dan jalankan ulang migrasi dengan seeder
php artisan migrate:fresh --seed
```

## Pengujian Migrasi

Sebelum menerapkan migrasi ke lingkungan produksi, pastikan untuk mengujinya di lingkungan pengembangan:

1. Jalankan migrasi dan verifikasi struktur tabel
2. Pastikan foreign key constraints berfungsi dengan benar
3. Uji performa query umum untuk memastikan indeks berfungsi dengan baik
4. Verifikasi bahwa soft delete berfungsi sebagaimana mestinya

## Keamanan Database

1. Jangan pernah menyimpan password atau informasi sensitif dalam bentuk plaintext
2. Gunakan enkripsi untuk data sensitif
3. Implementasikan validasi di level aplikasi untuk memastikan integritas data

## Referensi Lainnya

Lihat file `DATABASE_DOCUMENTATION.md` untuk informasi lebih lanjut tentang struktur relasi database dan alur data. 
