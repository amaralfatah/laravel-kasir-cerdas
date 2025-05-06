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
2. `product_price_rules` - Menyimpan informasi harga grosir produk
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
  │   ├─ price_categories
  │   ├─ product_price_rules
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





## Skema

// Database Schema for Retail Management System (Simplified but Maintainable)
// For application like Kasir Pintar

// ========================
// CORE TABLES
// ========================

Table users {
  id int [pk, increment]
  name varchar(255) [not null]
  email varchar(255) [unique, not null]
  password varchar(255) [note: "bcrypt hashed"]
  role enum('super_admin', 'owner', 'admin', 'manager', 'cashier') [default: 'cashier']
  shop_id int [ref: > shops.id, null] // null untuk super_admin dan owner
  is_active boolean [default: true]
  last_login timestamp
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    (shop_id, role) [name: 'idx_shop_staff']
    email [type: hash]
  }
}

Table shops {
  id int [pk, increment]
  name varchar(255) [not null]
  address text [not null]
  phone varchar(20)
  tax_id varchar(255)
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
}

Table shop_owners {
  id int [pk, increment]
  user_id int [ref: > users.id, not null]
  shop_id int [ref: > shops.id, not null]
  is_primary_owner boolean [default: false]
  notes text [null]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    (user_id, shop_id) [unique, name: 'idx_unique_shop_owner']
    is_primary_owner [name: 'idx_primary_owner']
  }
}

// ========================
// PRODUCT MANAGEMENT
// ========================

Table categories {
  id int [pk, increment]
  name varchar(255) [not null]
  parent_id int [ref: > categories.id, null]
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    parent_id [name: 'idx_category_tree']
  }
}

Table products {
  id int [pk, increment]
  name varchar(255) [not null]
  sku varchar(50) [unique]
  category_id int [ref: > categories.id, not null]
  purchase_price decimal(10,2) [not null]
  selling_price decimal(10,2) [not null]
  barcode varchar(255) [unique]
  description text
  images text [note: "JSON array of image URLs"]
  is_using_stock boolean [default: true]
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    barcode [name: 'idx_barcode_search']
    sku [name: 'idx_sku_search']
    category_id [name: 'idx_product_category']
  }
}

Table product_stocks {
  id int [pk, increment]
  product_id int [ref: > products.id, not null]
  shop_id int [ref: > shops.id, not null]
  stock int [default: 0]
  min_stock int [default: 5]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    (product_id, shop_id) [unique, name: 'idx_product_shop_stock']
  }
}

// Tabel kategori harga untuk mendefinisikan jenis-jenis harga
Table price_categories {
  id int [pk, increment]
  name varchar(255) [not null, note: "Contoh: 'Normal', 'Pedagang', 'Borongan'"]
  description text
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
}

// Tabel aturan harga produk yang fleksibel
Table product_price_rules {
  id int [pk, increment]
  product_id int [ref: > products.id, not null]
  shop_id int [ref: > shops.id, null, note: "Null jika berlaku untuk semua toko"]
  price_category_id int [ref: > price_categories.id, not null]
  min_quantity int [not null, note: "Jumlah minimum untuk aturan harga ini"]
  price decimal(10,2) [not null, note: "Harga per unit saat aturan ini berlaku"]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    (product_id, shop_id, price_category_id, min_quantity) [name: 'idx_product_price_rule']
  }
}

// ========================
// INVENTORY & PURCHASE
// ========================

Table suppliers {
  id int [pk, increment]
  name varchar(255) [not null]
  contact_name varchar(255)
  phone varchar(20)
  email varchar(255)
  address text
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
}

Table purchase_orders {
  id int [pk, increment]
  po_number varchar(50) [unique, not null]
  supplier_id int [ref: > suppliers.id, not null]
  shop_id int [ref: > shops.id, not null]
  total decimal(10,2) [not null]
  status enum('draft', 'ordered', 'partial', 'received', 'canceled') [default: 'draft']
  created_by int [ref: > users.id, not null]
  received_by int [ref: > users.id]
  notes text
  order_date date [not null]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    po_number [name: 'idx_po_number']
    order_date [name: 'idx_po_date']
  }
}

Table purchase_order_items {
  id int [pk, increment]
  po_id int [ref: > purchase_orders.id, not null]
  product_id int [ref: > products.id, not null]
  quantity int [not null]
  received_quantity int [default: 0]
  unit_price decimal(10,2) [not null]
  
  indexes {
    (po_id, product_id) [unique, name: 'idx_po_product']
  }
}

// ========================
// SALES & TRANSACTIONS
// ========================

Table customers {
  id int [pk, increment]
  name varchar(255) [not null]
  phone varchar(20) [unique]
  email varchar(255)
  address text
  points int [default: 0]
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    phone [name: 'idx_customer_phone']
  }
}

Table transactions {
  id int [pk, increment]
  invoice_number varchar(255) [unique, not null]
  transaction_type enum('sale', 'return', 'adjustment') [default: 'sale']
  user_id int [ref: > users.id, not null]
  shop_id int [ref: > shops.id, not null]
  customer_id int [ref: > customers.id]
  subtotal decimal(10,2) [not null]
  discount_amount decimal(10,2) [default: 0.00]
  tax_amount decimal(10,2) [default: 0.00]
  service_fee decimal(10,2) [default: 0.00]
  total_amount decimal(10,2) [not null]
  payment_method_id int [ref: > payment_methods.id, not null]
  payment_status enum('pending', 'partial', 'completed', 'refunded') [default: 'completed']
  notes text
  transaction_date timestamptz [default: `now()`]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    invoice_number [name: 'idx_invoice_unique']
    transaction_date [name: 'idx_transaction_date']
    customer_id [name: 'idx_transaction_customer']
  }
}

Table transaction_items {
  id int [pk, increment]
  transaction_id int [ref: > transactions.id, not null]
  product_id int [ref: > products.id, not null]
  price_category_id int [ref: > price_categories.id, not null, note: "Kategori harga yang dipilih untuk item ini"]
  quantity int [not null]
  unit_price decimal(10,2) [not null]
  purchase_price decimal(10,2) [not null, note: "For profit calculation"]
  discount_amount decimal(10,2) [default: 0.00]
  tax_amount decimal(10,2) [default: 0.00]
  subtotal decimal(10,2) [not null]
  
  indexes {
    transaction_id [name: 'idx_transaction_items']
    product_id [name: 'idx_sold_products']
  }
}

Table payment_methods {
  id int [pk, increment]
  name varchar(255) [not null]
  is_digital boolean [default: false]
  fee_percentage decimal(5,2) [default: 0.00]
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
}

// For handling partial payments or multiple payment methods per transaction
Table transaction_payments {
  id int [pk, increment]
  transaction_id int [ref: > transactions.id, not null]
  payment_method_id int [ref: > payment_methods.id, not null]
  amount decimal(10,2) [not null]
  payment_reference varchar(255)
  payment_date timestamptz [default: `now()`]
  
  indexes {
    transaction_id [name: 'idx_transaction_payments']
  }
}

// ========================
// FINANCIAL & REPORTING
// ========================

Table expenses {
  id int [pk, increment]
  shop_id int [ref: > shops.id, not null]
  expense_category enum('operational', 'salary', 'rent', 'utility', 'marketing', 'tax', 'other') [not null]
  amount decimal(10,2) [not null]
  description text [not null]
  expense_date date [not null]
  created_by int [ref: > users.id, not null]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    expense_date [name: 'idx_expense_date']
    expense_category [name: 'idx_expense_category']
  }
}

Table tax_rules {
  id int [pk, increment]
  name varchar(255) [not null]
  rate decimal(5,2) [not null]
  applies_to enum('all', 'category', 'product') [not null]
  reference_id int [note: "ID of category or product, null if applies_to='all'"]
  is_active boolean [default: true]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
}

// ========================
// AUDIT & LOGGING
// ========================

Table stock_movements {
  id int [pk, increment]
  product_id int [ref: > products.id, not null]
  shop_id int [ref: > shops.id, not null]
  quantity int [not null, note: "Positive for incoming, negative for outgoing"]
  movement_type enum('purchase', 'sale', 'adjustment', 'return', 'transfer') [not null]
  reference_type varchar(50) [note: "Name of related table: 'transactions', 'purchase_orders', etc."]
  reference_id int [note: "ID in the reference table"]
  notes text
  user_id int [ref: > users.id, not null]
  created_at timestamptz [default: `now()`]
  
  indexes {
    (product_id, shop_id, created_at) [name: 'idx_stock_movement_history']
    (reference_type, reference_id) [name: 'idx_stock_reference']
  }
}

Table stock_opname {
  id int [pk, increment]
  shop_id int [ref: > shops.id, not null]
  status enum('draft', 'pending', 'approved', 'canceled') [default: 'draft']
  notes text
  conducted_by int [ref: > users.id, not null]
  approved_by int [ref: > users.id]
  conducted_at date [not null]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
}

Table stock_opname_items {
  id int [pk, increment]
  stock_opname_id int [ref: > stock_opname.id, not null]
  product_id int [ref: > products.id, not null]
  physical_stock int [not null]
  system_stock int [not null]
  variance int [not null]
  notes text
  
  indexes {
    (stock_opname_id, product_id) [unique, name: 'idx_opname_product']
  }
}

// ========================
// SYSTEM SETTINGS
// ========================

Table system_settings {
  id int [pk, increment]
  category varchar(50) [not null]
  key varchar(255) [not null]
  value text [not null]
  is_public boolean [default: false]
  updated_by int [ref: > users.id]
  updated_at timestamptz [default: `now()`]
  
  indexes {
    (category, key) [unique, name: 'idx_settings_key']
  }
}