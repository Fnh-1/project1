CREATE DATABASE IF NOT EXISTS kasir_taichan_anin
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kasir_taichan_anin;

DROP VIEW IF EXISTS v_menu_terlaris;
DROP VIEW IF EXISTS v_penjualan_harian;
DROP TABLE IF EXISTS transaksi_item;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS stok_bahan;
DROP TABLE IF EXISTS menu;

CREATE TABLE menu (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kategori VARCHAR(30) NOT NULL,
    harga INT UNSIGNED NOT NULL,
    gambar VARCHAR(255) DEFAULT NULL,
    status ENUM('Tersedia', 'Habis') NOT NULL DEFAULT 'Tersedia',
    dibuat_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE stok_bahan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    stok DECIMAL(10,2) NOT NULL DEFAULT 0,
    satuan VARCHAR(20) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE transaksi (
    id VARCHAR(30) PRIMARY KEY,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal INT UNSIGNED NOT NULL,
    pajak INT UNSIGNED NOT NULL,
    total INT UNSIGNED NOT NULL,
    metode ENUM('Tunai', 'QRIS', 'E-Wallet') NOT NULL DEFAULT 'Tunai',
    INDEX idx_tanggal (tanggal)
) ENGINE=InnoDB;

CREATE TABLE transaksi_item (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaksi_id VARCHAR(30) NOT NULL,
    menu_id INT UNSIGNED NULL,
    nama_menu VARCHAR(100) NOT NULL,
    harga INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL,
    total_item INT UNSIGNED NOT NULL,
    CONSTRAINT fk_item_transaksi FOREIGN KEY (transaksi_id)
        REFERENCES transaksi(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_menu FOREIGN KEY (menu_id)
        REFERENCES menu(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE VIEW v_penjualan_harian AS
SELECT DATE(tanggal) AS tanggal,
       COUNT(*) AS jumlah_transaksi,
       SUM(total) AS total_penjualan
FROM transaksi
GROUP BY DATE(tanggal);

CREATE VIEW v_menu_terlaris AS
SELECT nama_menu, SUM(qty) AS jumlah_terjual
FROM transaksi_item
GROUP BY nama_menu
ORDER BY jumlah_terjual DESC;

INSERT INTO menu (nama, kategori, harga, status) VALUES
('Sate Kulit Saiyan', 'Sate', 12000, 'Tersedia'),
('Tahu Crispy Krilin', 'Camilan', 10000, 'Tersedia'),
('Kentang Goreng Piccolo', 'Camilan', 12000, 'Tersedia'),
('Es Teh Shenron', 'Minuman', 5000, 'Tersedia'),
('Sate Kamehameha (Original)', 'Sate', 15000, 'Tersedia'),
('Sate Super Saiyan (Mozzarella)', 'Sate', 20000, 'Tersedia'),
('Es Bulma', 'Minuman', 6000, 'Tersedia');

INSERT INTO stok_bahan (nama, stok, satuan) VALUES
('Daging Ayam', 5, 'kg'),
('Tahu', 20, 'pcs'),
('Kentang', 3, 'kg'),
('Teh Celup', 50, 'pcs');
