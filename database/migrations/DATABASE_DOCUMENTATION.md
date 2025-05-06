# Dokumentasi Database Kasir Cerdas

## Struktur Relasi Database

### Tabel Utama
1. `shops` - Menyimpan informasi toko yang menggunakan sistem
2. `users` - Menyimpan informasi pengguna (admin, kasir, manajer, dll)
3. `products` - Menyimpan informasi produk yang dijual
4. `transactions` - Menyimpan informasi transaksi penjualan
5. `purchase_orders` - Menyimpan informasi pemesanan pembelian

### Tabel Pendukung
1. `product_stocks` - Menyimpan informasi stok produk per toko
2. `product_wholesale_prices` - Menyimpan informasi harga grosir produk
3. `stock_movements` - Menyimpan informasi pergerakan stok produk
4. `stock_opnames` - Menyimpan informasi stock opname
5. `stock_opname_items` - Menyimpan detail item dalam stock opname
6. `transaction_items` - Menyimpan detail item dalam transaksi
7. `transaction_payments` - Menyimpan informasi pembayaran transaksi
8. `purchase_order_items` - Menyimpan detail item dalam pemesanan pembelian
9. `categories` - Menyimpan informasi kategori produk
10. `customers` - Menyimpan informasi pelanggan
11. `suppliers` - Menyimpan informasi supplier
12. `expenses` - Menyimpan informasi pengeluaran
13. `payment_methods` - Menyimpan informasi metode pembayaran
14. `tax_rules` - Menyimpan informasi aturan pajak
15. `system_settings` - Menyimpan pengaturan sistem

## Diagram Relasi

```
shops
  ↓
  ├─ users
  ├─ products ─ categories
  │   ↓
  │   ├─ product_stocks
  │   ├─ product_wholesale_prices
  │   └─ stock_movements
  │
  ├─ transactions ─ customers
  │   ↓
  │   ├─ transaction_items
  │   └─ transaction_payments ─ payment_methods
  │
  ├─ purchase_orders ─ suppliers
  │   ↓
  │   └─ purchase_order_items
  │
  ├─ stock_opnames
  │   ↓
  │   └─ stock_opname_items
  │
  └─ expenses
```

## Alur Data Utama

### Alur Penjualan
1. Produk diinput ke dalam sistem (`products`)
2. Stok produk diatur per toko (`product_stocks`)
3. Kasir membuat transaksi penjualan (`transactions`)
4. Detail produk yang terjual dicatat (`transaction_items`)
5. Pembayaran transaksi dicatat (`transaction_payments`)
6. Stok produk berkurang secara otomatis (`stock_movements`)

### Alur Pembelian
1. Manajer membuat pemesanan pembelian (`purchase_orders`)
2. Detail produk yang dipesan dicatat (`purchase_order_items`)
3. Ketika barang datang, stok produk bertambah (`stock_movements`)
4. Pengeluaran untuk pembelian dicatat (`expenses`)

### Alur Stock Opname
1. Manajer membuat stock opname (`stock_opnames`)
2. Stok fisik dan digital dicatat (`stock_opname_items`)
3. Perbedaan stok disesuaikan (`stock_movements`)

## Konvensi Pengembangan Database

### Penamaan
- Nama tabel: menggunakan huruf kecil dan snake_case, dalam bentuk jamak (contoh: `products`)
- Nama kolom: menggunakan huruf kecil dan snake_case (contoh: `product_id`)
- Foreign key: nama tabel singular + `_id` (contoh: `product_id`)
- Indeks: menggunakan prefix `idx_` untuk indeks biasa (contoh: `idx_product_name`)
- Unique constraint: menggunakan prefix `unq_` untuk indeks unik (contoh: `unq_product_sku`)

### Konvensi Lainnya
- Semua tabel menggunakan `id` sebagai primary key
- Semua tabel utama menggunakan soft delete (`softDeletes()`)
- Semua tabel menggunakan timestamps (`timestamps()`)
- Foreign key selalu menggunakan constraint (`constrained()`)
- Kolom diberi komentar untuk memperjelas fungsinya (`comment()`)

## Catatan Penting
- Ketika melakukan perubahan pada struktur database, pastikan untuk memperbarui dokumentasi ini
- Perhatikan relasi antar tabel untuk menghindari data yang tidak konsisten
- Pastikan indeks yang dibuat mendukung query yang sering dijalankan
- Gunakan migrasi untuk setiap perubahan struktur database, jangan mengubah struktur secara manual 
