<?php
session_start();
include '../koneksi.php'; // Sesuaikan path jika berbeda

$service_data = null;
$error_message = '';
$success_message = '';

// --- Logika Ambil Data Servis untuk Form ---
if (isset($_GET['id'])) {
    $id_service = $_GET['id'];
    $stmt = $koneksi->prepare("SELECT service.*, customer.nama_customer FROM service JOIN customer ON service.id_customer = customer.id_customer WHERE id_service = ?");
    if ($stmt === false) {
        die("Error preparing statement: " . $koneksi->error);
    }
    $stmt->bind_param("i", $id_service);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $service_data = $result->fetch_assoc();
    } else {
        $error_message = "Data service tidak ditemukan.";
    }
    $stmt->close();
} else {
    $error_message = "ID Service tidak diberikan.";
}

// --- Logika UPDATE Data Servis ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_service'])) {
    $id_service = $_POST['id_service'];
    $status     = $_POST['status'];
    $kerusakan  = $_POST['kerusakan'];
    $estimasi_harga = $_POST['estimasi_harga'];
    $estimasi_waktu = $_POST['estimasi_waktu'];
    $keluhan    = $_POST['keluhan']; // Ambil keluhan juga jika ingin bisa diupdate

    // Hapus data detail_service lama dulu (biar ga numpuk)
    $stmt_delete = $koneksi->prepare("DELETE FROM detail_service WHERE id_service = ?");
    $stmt_delete->bind_param("i", $id_service);
    $stmt_delete->execute();
    $stmt_delete->close();

    // Siapin total
    $total = 0;

    // Proses jasa
    if (!empty($_POST['id_jasa'])) {
        foreach ($_POST['id_jasa'] as $id_jasa) {
            $q = $koneksi->prepare("SELECT harga FROM jasa WHERE id_jasa = ?");
            $q->bind_param("i", $id_jasa);
            $q->execute();
            $q->bind_result($harga_jasa);
            $q->fetch();
            $q->close();

            $total += $harga_jasa;

            $stmt = $koneksi->prepare("INSERT INTO detail_service (id_service, id_jasa, kerusakan, total, catatan) VALUES (?, ?, ?, ?,?)");
            $catatan = $_POST['catatan'];
            $stmt->bind_param("iisis", $id_service, $id_jasa, $kerusakan, $harga_jasa, $catatan);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Proses barang
    if (!empty($_POST['id_barang'])) {
        foreach ($_POST['id_barang'] as $id_barang) {
            $q = $koneksi->prepare("SELECT harga FROM stok WHERE id_barang = ?");
            $q->bind_param("i", $id_barang);
            $q->execute();
            $q->bind_result($harga_barang);
            $q->fetch();
            $q->close();

            $total += $harga_barang;

            $stmt = $koneksi->prepare("INSERT INTO detail_service (id_service, id_barang,kerusakan, total, catatan) VALUES (?, ?, ?, ?, ?)");
            $catatan = $_POST['catatan'];
            $stmt->bind_param("iisis", $id_service, $id_barang, $kerusakan, $harga_barang, $catatan);
            $stmt->execute();
            $stmt->close();
        }
    }

    $catatan = '';
    $qCatatan = $koneksi->prepare("SELECT catatan FROM detail_service WHERE id_service = ? LIMIT 1");
    $qCatatan->bind_param("i", $id_service);
    $qCatatan->execute();
    $qCatatan->bind_result($catatan);
    $qCatatan->fetch();
    $qCatatan->close();


    // Validasi simple
    $allowed = ['diajukan', 'dikonfirmasi', 'menunggu sparepart', 'diperbaiki', 'selesai', 'dibatalkan'];
    if (!in_array($status, $allowed)) {
        $error_message = "Status tidak valid.";
    } else {
        $stmt = $koneksi->prepare("UPDATE service SET status = ?, estimasi_harga=?, estimasi_waktu=?, keluhan=? WHERE id_service = ?");
        if ($stmt === false) {
            $error_message = "Error preparing statement: " . $koneksi->error;
        } else {
            $stmt->bind_param("siisi", $status, $estimasi_harga, $estimasi_waktu, $keluhan, $id_service); // 's' for keluhan (string)

            if ($stmt->execute()) {
                // Jika status menjadi 'Selesai', catat tanggal selesai
                if ($status == 'selesai') {
                    $sql_update_tanggal_selesai = "UPDATE service SET tanggal_selesai = CURRENT_DATE() WHERE id_service = ?";
                    $stmt_tanggal_selesai = $koneksi->prepare($sql_update_tanggal_selesai);
                    if ($stmt_tanggal_selesai === false) {
                        error_log("Error preparing tanggal_selesai statement: " . $koneksi->error);
                    } else {
                        $stmt_tanggal_selesai->bind_param("i", $id_service);
                        $stmt_tanggal_selesai->execute();
                        $stmt_tanggal_selesai->close();
                    }
                }
                $success_message = "Data service berhasil diupdate!";
                // Refresh data setelah update agar form menampilkan data terbaru
                $stmt_refresh = $koneksi->prepare("SELECT service.*, customer.nama_customer FROM service JOIN customer ON service.id_customer = customer.id_customer WHERE id_service = ?");
                $stmt_refresh->bind_param("i", $id_service);
                $stmt_refresh->execute();
                $result_refresh = $stmt_refresh->get_result();
                $service_data = $result_refresh->fetch_assoc();
                $stmt_refresh->close();
            } else {
                $error_message = "Gagal update: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$namaAkun = "Admin";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Service - Thraz Computer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .form-input {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #4A5568;
            background-color: #F7FAFC;
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-input:focus {
            outline: none;
            border-color: #63B3ED;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }

        .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: #4A5568;
            background-color: #F7FAFC;
            border: 1px solid #CBD5E0;
            border-radius: 0.375rem;
            appearance: none;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-select:focus {
            outline: none;
            border-color: #63B3ED;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
    </style>
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
                        <a href="data_service.php" class="flex items-center space-x-3 p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
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
                        <a href="riwayat_transaksi.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">
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
                &copy; Tharz Computer 2025
            </div>
        </div>

        <div class="flex-1 flex flex-col">

            <div class="flex justify-between items-center p-5 bg-white shadow-md">
                <h2 class="text-2xl font-bold text-gray-800">Detail Service</h2>
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
                    <a href="data_service.php" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        &larr; Kembali ke Data Service
                    </a>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <strong class="font-bold">Sukses!</strong>
                            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($service_data): ?>
                        <h2 class="text-xl font-semibold mb-6 text-gray-700 text-center">Detail dan Update Service ID: <?php echo htmlspecialchars($service_data['id_service']); ?></h2>

                        <form method="POST" action="edit_service.php?id=<?php echo htmlspecialchars($service_data['id_service']); ?>" class="space-y-4">
                            <input type="hidden" name="id_service" value="<?php echo htmlspecialchars($service_data['id_service']); ?>">

                            <div>
                                <label for="nama_customer" class="block text-sm font-medium text-gray-700">Nama Pelanggan:</label>
                                <input type="text" id="nama_customer" value="<?php echo htmlspecialchars($service_data['nama_customer']); ?>" class="form-input bg-gray-100 cursor-not-allowed" disabled>
                            </div>
                            <div>
                                <label for="device" class="block text-sm font-medium text-gray-700">Device:</label>
                                <input type="text" id="device" value="<?php echo htmlspecialchars($service_data['device']); ?>" class="form-input bg-gray-100 cursor-not-allowed" disabled>
                            </div>
                            <div>
                                <label for="tanggal_masuk" class="block text-sm font-medium text-gray-700">Tanggal Masuk:</label>
                                <input type="text" id="tanggal_masuk" value="<?php echo htmlspecialchars($service_data['tanggal']); ?>" class="form-input bg-gray-100 cursor-not-allowed" disabled>
                            </div>
                            <div>
                                <label for="keluhan" class="block text-sm font-medium text-gray-700">Keluhan:</label>
                                <textarea id="keluhan" name="keluhan" rows="3" class="form-input"><?php echo htmlspecialchars($service_data['keluhan']); ?></textarea>
                            </div>
                            <div>
                                <label for="kerusakan" class="block text-sm font-medium text-gray-700">Kerusakan:</label>
                                <input type="text" id="kerusakan" name="kerusakan" class="form-input">
                            </div>
                            <div>
                                <label for="estimasi_harga" class="block text-sm font-medium text-gray-700">Estimasi Harga (Rp):</label>
                                <input type="number" id="estimasi_harga" name="estimasi_harga" value="<?php echo htmlspecialchars($service_data['estimasi_harga']); ?>" class="form-input">
                            </div>
                            <div>
                                <label for="estimasi_waktu" class="block text-sm font-medium text-gray-700">Estimasi Waktu:</label>
                                <input type="text" id="estimasi_waktu" name="estimasi_waktu" value="<?php echo htmlspecialchars($service_data['estimasi_waktu']); ?>" class="form-input">
                            </div>
                            <div>
                                <!-- Multi-select jasa -->
                                <label for="id_jasa">Pilih Jasa:</label><br>
                                <select name="id_jasa[]" multiple>
                                    <?php
                                    $resultJasa = $koneksi->query("SELECT * FROM jasa");
                                    while ($row = $resultJasa->fetch_assoc()) {
                                        echo '<option value="' . $row['id_jasa'] . '">' . $row['jenis_jasa'] . ' - Rp' . $row['harga'] . '</option>';
                                    }
                                    ?>
                                </select><br><br>
                            </div>
                            <!-- Multi-select barang -->
                            <div>
                                <label for="id_barang">Pilih Sparepart:</label><br>
                                <select name="id_barang[]" multiple>
                                    <?php
                                    $resultBarang = $koneksi->query("SELECT * FROM stok");
                                    while ($row = $resultBarang->fetch_assoc()) {
                                        echo '<option value="' . $row['id_barang'] . '">' . $row['nama_barang'] . ' - Rp' . $row['harga'] . '</option>';
                                    }
                                    ?>
                                </select><br><br>
                            </div>
                            <div>
                                <label for="catatan">Catatan:</label>
                                <textarea name="catatan"><?php echo htmlspecialchars($catatan); ?></textarea>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status:</label>
                                <select id="status" name="status" class="form-select">
                                    <?php
                                    $statusList = ['diajukan', 'dikonfirmasi', 'menunggu sparepart', 'diperbaiki', 'selesai', 'dibatalkan'];
                                    foreach ($statusList as $status) {
                                        $selected = ($service_data['status'] == $status) ? 'selected' : '';
                                        echo "<option value='$status' $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php if ($service_data['tanggal_selesai']): ?>
                                <div>
                                    <label for="tanggal_selesai" class="block text-sm font-medium text-gray-700">Tanggal Selesai:</label>
                                    <input type="text" id="tanggal_selesai" value="<?php echo htmlspecialchars($service_data['tanggal_selesai']); ?>" class="form-input bg-gray-100 cursor-not-allowed" disabled>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-end pt-4">
                                <button type="submit" name="update_service" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-md shadow-md hover:bg-blue-700 transition duration-200">
                                    Update Service
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>

</html>