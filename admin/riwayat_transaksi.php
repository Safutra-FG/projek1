<?php
session_start();
include '../koneksi.php'; // Path ini sudah benar jika koneksi.php di folder yang sama

// Pastikan koneksi database sudah dibuat dan valid
if (!isset($koneksi) || !$koneksi instanceof mysqli) {
    die("Koneksi database belum dibuat atau salah.");
}

$namaAkun = "Admin"; // Kamu bisa mengambil nama akun dari session jika sudah ada

// Logika untuk filtering bulan (jika ada) akan tetap di sini
$bulan_filter = $_GET['bulan'] ?? date('Y-m'); // Default bulan saat ini (YYYY-MM)

// Query untuk mengambil data transaksi (kolom 'total' di-alias menjadi 'total_harga')
$sql_transaksi = "
    SELECT
        t.id_transaksi,
        c.nama_customer,
        t.tanggal AS tanggal_transaksi,
        t.jenis AS jenis_transaksi,
        GROUP_CONCAT(CONCAT(dt.jumlah, 'x ', s.nama_barang) SEPARATOR ', ') AS sparepart_dibeli,
        t.total AS total_harga
    FROM
        transaksi t
    JOIN
        customer c ON t.id_customer = c.id_customer
    LEFT JOIN
        detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
    LEFT JOIN
        stok s ON dt.id_barang = s.id_barang
    WHERE
        DATE_FORMAT(t.tanggal, '%Y-%m') = ?
    GROUP BY
        t.id_transaksi, c.nama_customer, t.tanggal, t.jenis, t.total
    ORDER BY
        t.tanggal DESC;
";

$stmt_transaksi = $koneksi->prepare($sql_transaksi);

if ($stmt_transaksi) {
    $stmt_transaksi->bind_param("s", $bulan_filter);
    $stmt_transaksi->execute();
    $result_transaksi = $stmt_transaksi->get_result();
    $stmt_transaksi->close();
} else {
    die("Prepare statement gagal untuk transaksi: " . $koneksi->error);
}

$koneksi->close(); // Tutup koneksi setelah semua data diambil

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-900 font-sans antialiased">

    <div class="flex min-h-screen">

        <div class="w-64 bg-gray-800 shadow-lg flex flex-col justify-between py-6">
            <div>
                <div class="flex flex-col items-center mb-10">
                    <img src="../icons/logo.png" alt="Logo" class="w-16 h-16 rounded-full mb-3 border-2 border-blue-400">
                    <h1 class="text-2xl font-extrabold text-white text-center">Thraz Computer</h1>
                    <p class="text-sm text-gray-400">Admin Panel</p>
                </div>

                <ul class="px-6 space-y-3">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">🏠</span>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📝</span>
                            <span class="font-medium">Data Service</span>
                        </a>
                    </li>
                    <li>
                        <a href="data_pelanggan.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">👥</span>
                            <span class="font-medium">Data Pelanggan</span>
                        </a>
                    </li>
                    <li>
                        <a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                            <span class="text-xl">💳</span>
                            <span class="font-medium">Riwayat Transaksi</span>
                        </a>
                    </li>
                    <li>
                        <a href="stok_gudang.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
                            <span class="text-xl">📦</span>
                            <span class="font-medium">Stok Gudang</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="p-4 border-t border-gray-700 text-center text-sm text-gray-400">
                &copy; Thraz Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Riwayat Transaksi</h2>
                <div class="flex items-center space-x-5">
                    <button class="relative text-gray-600 hover:text-blue-600 transition duration-200" title="Pemberitahuan">
                        <span class="text-2xl">🔔</span>
                    </button>
                    <div class="flex items-center space-x-3">
                        <span class="text-xl text-gray-600">👤</span>
                        <span class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($namaAkun); ?></span>
                        <a href="../logout.php" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-8 overflow-auto">

                <div class="mb-6">
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali ke Dashboard
                    </a>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Filter Riwayat</h2>
                    <form action="" method="GET" class="flex items-center space-x-4">
                        <label for="bulan" class="text-sm font-medium text-gray-700">Riwayat Transaksi Bulan:</label>
                        <input type="month" id="bulan" name="bulan" value="<?php echo htmlspecialchars($bulan_filter); ?>" class="px-3 py-2 border rounded-md text-sm bg-gray-100 focus:ring-blue-500 focus:border-blue-500 focus:outline-none">
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">Filter</button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Riwayat Transaksi</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ID Pesanan</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Pelanggan</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Transaksi</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Sparepart Dibeli</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Harga</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-center">
                                <?php if ($result_transaksi->num_rows > 0) : ?>
                                    <?php while ($row = $result_transaksi->fetch_assoc()) : ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['id_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['nama_customer']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['tanggal_transaksi']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['jenis_transaksi']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['sparepart_dibeli'] ?: 'N/A'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <a href="transaksi_service.php?id=<?php echo htmlspecialchars($row['id_transaksi']); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-1 px-3 rounded-md shadow-sm transition duration-200 text-xs">Detail</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada riwayat transaksi untuk bulan ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>

</html>