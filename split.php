<?php
include 'koneksi.php';

// Ambil nomor WO yang aktif
if (isset($_POST['wo'])) {
    $wo = $_POST['wo'];

    // Ambil data nomor WO
    $query = "SELECT * FROM nomor WHERE wo='$wo' AND batch_id IS NULL ORDER BY id ASC";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $batchSize = 999;

        // Hitung batch terakhir biar nggak dobel
        $lastBatch = mysqli_query($conn, "SELECT MAX(batch_id) as last_id FROM nomor");
        $row = mysqli_fetch_assoc($lastBatch);
        $batchId = ($row['last_id']) ? $row['last_id'] + 1 : 1;

        $count = 0;
        while ($data = mysqli_fetch_assoc($result)) {
            $id = $data['id'];

            // Update batch_id untuk data ini
            mysqli_query($conn, "UPDATE nomor SET batch_id = $batchId WHERE id = $id");

            $count++;

            // Kalau sudah 999 nomor, buat batch baru
            if ($count >= $batchSize) {
                $batchId++;
                $count = 0;
            }
        }

        header("Location: view.php?split=success");
    } else {
        header("Location: view.php?split=empty");
    }
}
?>
