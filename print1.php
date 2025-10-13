<?php
// Koneksi DB
$koneksi = mysqli_connect("localhost", "root", "", "test");
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = (int)$_POST['batch_id'];
    $mode     = $_POST['mode']; // "test" atau "save"

    // Ambil data batch
    $sql = "SELECT * FROM batch WHERE id = $batch_id LIMIT 1";
    $res = mysqli_query($koneksi, $sql);
    $batchData = mysqli_fetch_assoc($res);

    if (!$batchData) {
        die("Batch tidak ditemukan.");
    }

    $nomor_awal = (int)$batchData['nomor_awal'];
    $nomor_akhir = $nomor_awal + 999;

    // Tentukan list nomor yang akan diprint
    $numbers = [];
    if ($mode === "test") {
        // ambil contoh: awal, tengah, akhir
        $numbers = [$nomor_awal, intval(($nomor_awal+$nomor_akhir)/2), $nomor_akhir];
    } else {
        // mode save → semua nomor
        for ($i=$nomor_awal; $i<=$nomor_akhir; $i++) {
            $numbers[] = $i;
        }
    }

    // Generate ZPL untuk barcode
    $zpl = "";
    foreach ($numbers as $num) {
        $zpl .= "^XA"; // start label
        $zpl .= "^FO50,50^BY3"; // posisi X,Y dan lebar barcode
        $zpl .= "^BCN,100,Y,N,N"; // barcode Code128
        $zpl .= "^FD{$num}^FS"; // data barcode
        $zpl .= "^FO50,180^A0N,40,40^FD{$num}^FS"; // teks di bawah barcode
        $zpl .= "^XZ"; // end label
    }

    // Kirim ZPL ke printer
    // === Cara 1: Windows (printer share name) ===
    // $printerName = "\\\\localhost\\Zebra";
    // $fp = fopen($printerName, "w");
    // fwrite($fp, $zpl);
    // fclose($fp);

    // === Cara 2: Output langsung ke browser (download .zpl) ===
    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=print_batch{$batch_id}.zpl");
    echo $zpl;

    // Simpan log kalau mode save
    if ($mode === "save") {
        $stmt = $koneksi->prepare("INSERT INTO log_print (batch_id, nomor_awal, nomor_akhir, printed_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $batch_id, $nomor_awal, $nomor_akhir);
        $stmt->execute();
        $stmt->close();
    }
}
?>
