<?php
session_start();
include 'koneksi.php';

$id_customer = $_POST['id_customer'];
$jasa = $_POST['jasa'];
$sparepart = $_POST['sparepart'];
$tanggal = date('Y-m-d H:i:s');
$jenis = 'service';

$total = 0;

// Hitung total
foreach ($jasa as $item) {
    $total += $item['harga'] * $item['jumlah'];
}
foreach ($sparepart as $item) {
    $total += $item['harga'] * $item['jumlah'];
}

// Insert ke transaksi
$stmt = $koneksi->prepare("INSERT INTO transaksi (id_customer, jenis, total, tanggal) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isis", $id_customer, $jenis, $total, $tanggal);
$stmt->execute();
$id_transaksi = $koneksi->insert_id;
$stmt->close();

// Insert detail jasa
$stmt_detail_jasa = $koneksi->prepare("INSERT INTO detail_transaksi (id_transaksi, id_jasa, jumlah, subtotal) VALUES (?, ?, ?, ?)");
foreach ($jasa as $item) {
    $subtotal = $item['harga'] * $item['jumlah'];
    $stmt_detail_jasa->bind_param("iiii", $id_transaksi, $item['id_jasa'], $item['jumlah'], $subtotal);
    $stmt_detail_jasa->execute();
}
$stmt_detail_jasa->close();

// Insert detail sparepart dan update stok
$stmt_detail_sparepart = $koneksi->prepare("INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, subtotal) VALUES (?, ?, ?, ?)");
$stmt_update_stok = $koneksi->prepare("UPDATE stok SET stok = stok - ? WHERE id_barang = ?");
foreach ($sparepart as $item) {
    $subtotal = $item['harga'] * $item['jumlah'];
    $stmt_detail_sparepart->bind_param("iiii", $id_transaksi, $item['id_barang'], $item['jumlah'], $subtotal);
    $stmt_detail_sparepart->execute();

    $stmt_update_stok->bind_param("ii", $item['jumlah'], $item['id_barang']);
    $stmt_update_stok->execute();
}
$stmt_detail_sparepart->close();
$stmt_update_stok->close();

echo "<script>alert('Transaksi service berhasil!'); window.location='transaksi_service.php';</script>";
exit;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <h2>Form Transaksi Service</h2>
    <form action="proses_transaksi_service.php" method="POST">

        <!-- Customer -->
        <label for="id_customer">ID Customer:</label>
        <input type="number" name="id_customer" id="id_customer" required><br><br>

        <!-- Jasa -->
        <h3>Jasa</h3>
        <div id="jasa-container">
            <div class="jasa-item">
                <label>ID Jasa: </label><input type="number" name="jasa[0][id_jasa]" required>
                <label>Harga: </label><input type="number" name="jasa[0][harga]" required>
                <label>Jumlah: </label><input type="number" name="jasa[0][jumlah]" value="1" required>
            </div>
        </div>
        <button type="button" onclick="tambahJasa()">+ Tambah Jasa</button>

        <br><br>

        <!-- Sparepart -->
        <h3>Sparepart</h3>
        <div id="sparepart-container">
            <div class="sparepart-item">
                <label>ID Barang: </label><input type="number" name="sparepart[0][id_barang]" required>
                <label>Harga: </label><input type="number" name="sparepart[0][harga]" required>
                <label>Jumlah: </label><input type="number" name="sparepart[0][jumlah]" value="1" required>
            </div>
        </div>
        <button type="button" onclick="tambahSparepart()">+ Tambah Sparepart</button>

        <br><br>
        <button type="submit">Simpan Transaksi</button>
    </form>

    <script>
        let jasaIndex = 1;
        let sparepartIndex = 1;

        function tambahJasa() {
            const container = document.getElementById('jasa-container');
            const html = `
                <div class="jasa-item">
                    <label>ID Jasa: </label><input type="number" name="jasa[${jasaIndex}][id_jasa]" required>
                    <label>Harga: </label><input type="number" name="jasa[${jasaIndex}][harga]" required>
                    <label>Jumlah: </label><input type="number" name="jasa[${jasaIndex}][jumlah]" value="1" required>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            jasaIndex++;
        }

        function tambahSparepart() {
            const container = document.getElementById('sparepart-container');
            const html = `
                <div class="sparepart-item">
                    <label>ID Barang: </label><input type="number" name="sparepart[${sparepartIndex}][id_barang]" required>
                    <label>Harga: </label><input type="number" name="sparepart[${sparepartIndex}][harga]" required>
                    <label>Jumlah: </label><input type="number" name="sparepart[${sparepartIndex}][jumlah]" value="1" required>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            sparepartIndex++;
        } 
        </script>
    </body>
</html>