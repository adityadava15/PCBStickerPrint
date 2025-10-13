<?php
$koneksi = new mysqli("localhost", "root", "", "test");

// Ambil daftar batch
$batches = $koneksi->query("SELECT * FROM batch ORDER BY id ASC");

$selectedBatch = null;
$barcodes = [];

if (isset($_POST['batch_id'])) {
    $batch_id = (int) $_POST['batch_id'];
    $selectedBatch = $batch_id;

    // Ambil semua nomor dari batch terpilih
    $result = $koneksi->query("SELECT nomor FROM nomor_barcode WHERE batch_id=$batch_id ORDER BY id ASC");
    $nomorList = [];
    while ($row = $result->fetch_assoc()) {
        $nomorList[] = $row['nomor'];
    }

    // Ambil 9 awal & 9 akhir
    $awal = array_slice($nomorList, 0, 9);
    $akhir = array_slice($nomorList, -9);

    $barcodes = array_merge($awal, $akhir);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <head>
  <meta charset="UTF-8">
  <title>Dashboard Barcode</title>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; padding:20px; }
    form { margin-bottom:20px; }
    .card { border:1px solid #ddd; padding:15px; border-radius:10px; margin-bottom:20px; }
    .barcode-container { display:flex; flex-wrap:wrap; gap:15px; margin-top:15px; }
    .barcode-box { border:1px solid #ccc; padding:10px; border-radius:6px; text-align:center; }
    span.msg { margin-left:10px; font-weight:bold; }
    span.success { color:green; }
    span.info { color:blue; }
    span.error { color:red; }
  </style>
</head>

</head>
<body>
    <h2>Pilih Batch untuk Cetak Barcode</h2>
    <form method="POST">
        <select name="batch_id" required>
            <option value="">-- Pilih Batch --</option>
            <?php while ($batch = $batches->fetch_assoc()): ?>
                <option value="<?= $batch['id'] ?>" <?= ($selectedBatch==$batch['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($batch['nama_batch']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Tampilkan</button>
    </form>

    <?php if (!empty($barcodes)): ?>
        <h3>Preview (9 Nomor Awal & 9 Nomor Akhir)</h3>
        <div class="barcode-container">
            <?php foreach ($barcodes as $i => $code): ?>
                <div class="barcode">
                    <svg id="barcode<?= $i ?>"></svg>
                    <p><?= htmlspecialchars($code) ?></p>
                    <script>
                        JsBarcode("#barcode<?= $i ?>", "<?= $code ?>", {
                            format: "CODE128",
                            displayValue: false,
                            width: 2,
                            height: 60
                        });
                    </script>
                </div>
            <?php endforeach; ?>
        </div>
        <button onclick="window.print()">🖨 Print</button>
    <?php endif; ?>
</body>
</html>
