<?php
require '../db.php';

$sql = "SELECT ht.*, ul.username, ap.nama_produk, 
               IFNULL(vp.varian, '-') AS varian,
               au.label_alamat, au.kota, au.provinsi,
               (ht.harga * ht.jumlah) AS calculated_total
        FROM tb_historytransactions ht
        JOIN tb_userLogin ul ON ht.user_id = ul.id
        JOIN tb_adminProduct ap ON ht.product_id = ap.id
        LEFT JOIN tb_varian_product vp ON ht.varian_id = vp.id
        JOIN tb_alamat_user au ON ht.alamat_id = au.id
        ORDER BY ht.date DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Terjadi kesalahan saat menjalankan query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            padding: 8px 12px;
            border: 1px solid #ccc;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .mismatch {
            background-color: #ffdddd;
        }
    </style>
</head>
<body>
    <h2>Riwayat Transaksi</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>User</th>
                <th>Produk</th>
                <th>Varian</th>
                <th>Harga Satuan</th>
                <th>Jumlah</th>
                <th>Total (Database)</th>
                <th>Total (Dihitung)</th>
                <th>Alamat</th>
                <th>Metode Pembayaran</th>
                <th>Tanggal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php $no = 1 ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php 
                    $db_total = $row['total_harga'];
                    $calculated_total = $row['harga'] * $row['jumlah'];
                    $is_mismatch = $db_total != $calculated_total;
                    ?>
                    <tr <?= $is_mismatch ? 'class="mismatch"' : '' ?>>
                        <td><?= htmlspecialchars($no++) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                        <td><?= htmlspecialchars($row['varian']) ?></td>
                        <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($row['jumlah']) ?></td>
                        <td>Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($calculated_total, 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($row['label_alamat']) ?>, <?= htmlspecialchars($row['kota']) ?>, <?= htmlspecialchars($row['provinsi']) ?></td>
                        <td><?= strtoupper($row['pay_method']) ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">Tidak ada data transaksi.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
