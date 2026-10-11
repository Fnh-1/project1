<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/koneksi.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$kategoriValid = ['Sate', 'Camilan', 'Minuman'];

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {
        case 'menu_list':
            out($pdo->query('SELECT id, nama, kategori, harga, status FROM menu ORDER BY id')->fetchAll());

        case 'menu_tambah':
            $nama = trim($input['nama'] ?? '');
            $harga = (int)($input['harga'] ?? 0);
            $kategori = in_array($input['kategori'] ?? '', $kategoriValid, true) ? $input['kategori'] : 'Sate';
            if ($nama === '' || $harga <= 0) out(['error' => 'Nama dan harga menu wajib diisi'], 422);
            $st = $pdo->prepare('INSERT INTO menu (nama, kategori, harga) VALUES (?, ?, ?)');
            $st->execute([$nama, $kategori, $harga]);
            out(['id' => (int)$pdo->lastInsertId()], 201);

        case 'menu_ubah':
            $id = (int)($input['id'] ?? 0);
            $nama = trim($input['nama'] ?? '');
            $harga = (int)($input['harga'] ?? 0);
            $kategori = in_array($input['kategori'] ?? '', $kategoriValid, true) ? $input['kategori'] : 'Sate';
            if ($id <= 0 || $nama === '' || $harga <= 0) out(['error' => 'Nama dan harga menu wajib diisi'], 422);
            $st = $pdo->prepare('UPDATE menu SET nama = ?, kategori = ?, harga = ? WHERE id = ?');
            $st->execute([$nama, $kategori, $harga, $id]);
            out(['ok' => true]);

        case 'menu_status':
            $st = $pdo->prepare("UPDATE menu SET status = IF(status = 'Tersedia', 'Habis', 'Tersedia') WHERE id = ?");
            $st->execute([(int)($input['id'] ?? 0)]);
            out(['ok' => true]);

        case 'menu_hapus':
            $st = $pdo->prepare('DELETE FROM menu WHERE id = ?');
            $st->execute([(int)($input['id'] ?? 0)]);
            out(['ok' => true]);

        case 'stok_list':
            out($pdo->query('SELECT id, nama, stok, satuan FROM stok_bahan ORDER BY id')->fetchAll());

        case 'stok_tambah':
            $nama = trim($input['nama'] ?? '');
            $stok = (float)($input['stok'] ?? 0);
            $satuan = trim($input['satuan'] ?? '');
            if ($nama === '' || $satuan === '' || $stok < 0) out(['error' => 'Nama, stok, dan satuan wajib diisi'], 422);
            $st = $pdo->prepare('INSERT INTO stok_bahan (nama, stok, satuan) VALUES (?, ?, ?)');
            $st->execute([$nama, $stok, $satuan]);
            out(['id' => (int)$pdo->lastInsertId()], 201);

        case 'stok_ubah':
            $id = (int)($input['id'] ?? 0);
            $nama = trim($input['nama'] ?? '');
            $stok = (float)($input['stok'] ?? 0);
            $satuan = trim($input['satuan'] ?? '');
            if ($id <= 0 || $nama === '' || $satuan === '' || $stok < 0) out(['error' => 'Nama, stok, dan satuan wajib diisi'], 422);
            $st = $pdo->prepare('UPDATE stok_bahan SET nama = ?, stok = ?, satuan = ? WHERE id = ?');
            $st->execute([$nama, $stok, $satuan, $id]);
            out(['ok' => true]);

        case 'stok_isi':
            $jumlah = (float)($input['jumlah'] ?? 0);
            if ($jumlah <= 0) out(['error' => 'Jumlah harus lebih dari 0'], 422);
            $st = $pdo->prepare('UPDATE stok_bahan SET stok = stok + ? WHERE id = ?');
            $st->execute([$jumlah, (int)($input['id'] ?? 0)]);
            out(['ok' => true]);

        case 'stok_hapus':
            $st = $pdo->prepare('DELETE FROM stok_bahan WHERE id = ?');
            $st->execute([(int)($input['id'] ?? 0)]);
            out(['ok' => true]);

        case 'transaksi_simpan':
            $items = $input['items'] ?? [];
            $metode = in_array($input['metode'] ?? '', ['Tunai', 'QRIS', 'E-Wallet'], true) ? $input['metode'] : 'Tunai';
            if (!$items) out(['error' => 'Pesanan masih kosong'], 422);

            $pdo->beginTransaction();

            $trxId = 'TRX-' . (int)(microtime(true) * 1000);
            $baris = [];
            $subtotal = 0;
            $totalQty = 0;

            $cari = $pdo->prepare('SELECT id, nama, harga, status FROM menu WHERE id = ?');
            foreach ($items as $it) {
                $qty = max(1, (int)($it['qty'] ?? 1));
                $cari->execute([(int)($it['id'] ?? 0)]);
                $m = $cari->fetch();
                if (!$m) {
                    $pdo->rollBack();
                    out(['error' => 'Ada menu di pesanan yang sudah tidak tersedia di daftar'], 422);
                }
                if ($m['status'] !== 'Tersedia') {
                    $pdo->rollBack();
                    out(['error' => 'Menu ' . $m['nama'] . ' sedang habis'], 422);
                }
                $totalItem = $m['harga'] * $qty;
                $subtotal += $totalItem;
                $totalQty += $qty;
                $baris[] = [$trxId, $m['id'], $m['nama'], $m['harga'], $qty, $totalItem];
            }

            $pajak = (int)round($subtotal * 0.10);
            $total = $subtotal + $pajak;

            $pdo->prepare('INSERT INTO transaksi (id, subtotal, pajak, total, metode) VALUES (?, ?, ?, ?, ?)')
                ->execute([$trxId, $subtotal, $pajak, $total, $metode]);

            $ins = $pdo->prepare('INSERT INTO transaksi_item (transaksi_id, menu_id, nama_menu, harga, qty, total_item) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($baris as $b) $ins->execute($b);

            $pdo->prepare("UPDATE stok_bahan SET stok = GREATEST(0, stok - ?) WHERE nama = 'Daging Ayam'")
                ->execute([$totalQty * 0.1]);

            $pdo->commit();
            out(['id' => $trxId, 'subtotal' => $subtotal, 'pajak' => $pajak, 'total' => $total, 'metode' => $metode], 201);

        case 'laporan':
            $trx = $pdo->query('SELECT * FROM transaksi ORDER BY tanggal DESC, id DESC')->fetchAll();
            $det = $pdo->prepare('SELECT nama_menu, harga, qty, total_item FROM transaksi_item WHERE transaksi_id = ?');
            foreach ($trx as &$t) {
                $det->execute([$t['id']]);
                $t['items'] = $det->fetchAll();
            }
            unset($t);
            out($trx);

        case 'dashboard':
            $hariIni = $pdo->query('SELECT COUNT(*) AS jumlah, COALESCE(SUM(total), 0) AS total FROM transaksi WHERE DATE(tanggal) = CURDATE()')->fetch();
            $terlaris = $pdo->query('SELECT * FROM v_menu_terlaris ORDER BY jumlah_terjual DESC LIMIT 5')->fetchAll();
            $menipis = $pdo->query('SELECT COUNT(*) FROM stok_bahan WHERE stok < 5')->fetchColumn();
            $tujuhHari = $pdo->query('SELECT tanggal, total_penjualan FROM v_penjualan_harian WHERE tanggal >= CURDATE() - INTERVAL 6 DAY ORDER BY tanggal')->fetchAll();
            out([
                'total_hari_ini' => (int)$hariIni['total'],
                'jumlah_transaksi' => (int)$hariIni['jumlah'],
                'menu_terlaris' => $terlaris,
                'stok_menipis' => (int)$menipis,
                'penjualan_7_hari' => $tujuhHari,
            ]);

        default:
            out(['error' => 'Permintaan tidak dikenal'], 404);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['error' => $e->getMessage()], 500);
}
