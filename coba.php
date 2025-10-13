<?php
// ====== KONEKSI ======
$koneksi = new mysqli("localhost", "root", "", "test");
if ($koneksi->connect_errno) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
include 'koneksi.php'; // Jika diperlukan, tapi mungkin redundant

// Ambil daftar WO unik dari batch
$woList = mysqli_query($koneksi, "SELECT DISTINCT wo FROM batch ORDER BY wo ASC");
$selectedWO = isset($_GET['wo']) ? $_GET['wo'] : '';

// ====== UPLOAD WO ======
$msgUpload = '';
if (isset($_POST['upload'])) {
    $wo   = $koneksi->real_escape_string(trim($_POST['wo']));
    $file = $_FILES['file']['tmp_name'];
    if ($file && is_uploaded_file($file)) {
        $handle = fopen($file, "r");
        if ($handle) {
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if (!isset($data[0])) continue;
                $nomor = trim($data[0]);
                if ($nomor === '') continue;
                $koneksi->query("INSERT INTO nomor_barcode (nomor, wo) VALUES ('{$koneksi->real_escape_string($nomor)}', '$wo')");
            }
            fclose($handle);
            $msgUpload = "Upload berhasil untuk WO: $wo";
        } else $msgUpload = "Gagal membuka file upload.";
    } else $msgUpload = "File tidak terdeteksi.";
}

// ====== SPLIT PER WO (999) ======
$msgSplit = '';
if (isset($_POST['split']) && !empty($_POST['wo_selected'])) {
    $wo = $koneksi->real_escape_string($_POST['wo_selected']);
    $ids = [];
    $result = $koneksi->query("SELECT id FROM nomor_barcode WHERE wo='$wo' AND batch_id IS NULL ORDER BY id ASC");
    while ($row = $result->fetch_assoc()) $ids[] = (int)$row['id'];

    $batchSize = 999; $batchNum = 1;
    for ($i = 0; $i < count($ids); $i += $batchSize) {
        $nama_batch = "Batch $batchNum - $wo";
        $koneksi->query("INSERT INTO batch (nama_batch, wo) VALUES ('$nama_batch', '$wo')");
        $batch_id = $koneksi->insert_id;
        if ($batch_id > 0) {
            $chunk = array_slice($ids, $i, $batchSize);
            if (!empty($chunk)) {
                $in = implode(',', array_map('intval', $chunk));
                $koneksi->query("UPDATE nomor_barcode SET batch_id=$batch_id WHERE id IN ($in)");
            }
        }
        $batchNum++;
    }
    $msgSplit = "WO $wo berhasil di-split!";
}

// ====== FINISH (hapus 1 WO) ======
$msgFinish = '';
if (isset($_POST['finish']) && !empty($_POST['wo_selected'])) {
    $wo = $koneksi->real_escape_string($_POST['wo_selected']);
    $koneksi->query("DELETE FROM nomor_barcode WHERE wo='$wo'");
    $koneksi->query("DELETE FROM batch WHERE wo='$wo'");
    $msgFinish = "WO $wo sudah dihapus!";
}

$printScript = ''; 
$msg = '';
if (isset($_POST['print']) || isset($_POST['test_print'])) {
    $batch_id   = (int)$_POST['batch_id'];
    $wo         = $koneksi->real_escape_string($_POST['wo']);
    $start      = $koneksi->real_escape_string($_POST['start']);
    $end        = $koneksi->real_escape_string($_POST['end']);
    $customText = isset($_POST['custom_text']) ? $koneksi->real_escape_string($_POST['custom_text']) : '';

    // Ambil numbers untuk print
    $res = $koneksi->query("SELECT nomor FROM nomor_barcode WHERE batch_id=$batch_id ORDER BY id ASC");
    $numbers = [];
    while ($r = $res->fetch_assoc()) { $numbers[] = $r['nomor']; }

    if (isset($_POST['print']) && $_POST['print'] === '1') {
        $batchRes = $koneksi->query("SELECT nama_batch FROM batch WHERE id=$batch_id");
        $batchRow = $batchRes->fetch_assoc();
        $nama_batch = $batchRow['nama_batch'];

        $sql = "INSERT INTO log_print (start_number, end_number, batch_id, nama_batch, printed_at) 
                VALUES ('$start','$end','$batch_id','$nama_batch',NOW())";
        $koneksi->query($sql);

        $msg = "✔ Data batch $batch_id berhasil disimpan ke log_print!";
    } else {
        $msg = "ℹ Test Print: Tidak disimpan ke database.";
    }

    // Generate print script
    if (!empty($numbers)) {
        ob_start();
?>
<script>
var w = window.open('', '_blank');
w.document.write('<html><head><title>Cetak Barcode</title>');
w.document.write('<style>');
w.document.write(`
  @page {
    size: 8cm 3.4cm;
    margin: 0;
  }
  body {
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
  }
  .page {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(3, 1fr);
    width: 8cm;
    height: 3.4cm;
    margin-top: -4px;
    page-break-after: always;
  }
  .label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 10pt;
  }
  .label svg {
    width: 88px;
    height: 0.8cm;
    
  }
  .label small {
    font-family: Arial, sans-serif;
    font-size: 6.1pt;
    font-weight: bold;
    margin-top: -4px;
  }
  .custom {
    font-family: Arial, sans-serif;
    font-size: 5pt;
    font-weight: bold;
    margin-top: 1px;
  }
`);
w.document.write('</style></head><body onload="window.print();">');

<?php 
$chunks = array_chunk($numbers, 9); 
foreach ($chunks as $pageIndex => $chunk): ?>
  w.document.write('<div class="page">');
  <?php foreach ($chunk as $i => $num): 
    $id = "code{$pageIndex}_{$i}"; ?>
    w.document.write('<div class="label">');
    w.document.write('<svg id="<?= $id ?>"></svg>');
    w.document.write('<small><?= $num ?></small>');
    w.document.write('<div class="custom"><?= htmlspecialchars($customText) ?></div>');
    w.document.write('</div>');
  <?php endforeach; ?>
  w.document.write('</div>');
<?php endforeach; ?>

w.document.write('<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>');
w.document.write('<script>');
<?php 
foreach ($chunks as $pageIndex => $chunk) {
  foreach ($chunk as $i => $num) {
    $id = "code{$pageIndex}_{$i}";
    echo "w.document.write('JsBarcode(\"#{$id}\",\"{$num}\",{format:\"CODE128\",displayValue:false,fontSize:11,height:35});');\n";
  }
}
?>
w.document.write('<\/script>');
w.document.write('</body></html>');
w.document.close();
</script>
<?php
        $printScript = ob_get_clean();
    }
}

// ====== DATA PREVIEW BATCH ======
$selectedBatchId = isset($_GET['batch_id']) && $_GET['batch_id'] !== '' ? (int)$_GET['batch_id'] : null;
$awal9 = $akhir9 = []; $woBatch = ''; $start = ''; $end = '';
$isPrinted = false;
if ($selectedBatchId) {
    $res = $koneksi->query("SELECT nomor, wo FROM nomor_barcode WHERE batch_id=$selectedBatchId ORDER BY id ASC");
    $numbers = [];
    while ($r = $res->fetch_assoc()) { $numbers[] = $r['nomor']; $woBatch = $r['wo']; }
    if (count($numbers) > 0) {
        $awal9  = array_slice($numbers, 0, 9);
        $akhir9 = array_slice($numbers, -9);
        $start  = $numbers[0]; $end = end($numbers);
    }

    // Cek apakah batch sudah di-print
    $checkPrint = $koneksi->query("SELECT COUNT(*) as cnt FROM log_print WHERE batch_id=$selectedBatchId");
    $printRow = $checkPrint->fetch_assoc();
    if ($printRow['cnt'] > 0) {
        $isPrinted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>PCB Barcode Print</title>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f8f9fa;
      color: #333;
      padding: 20px;
      margin: 0;
    }

    header {
      text-align: center;
      margin-bottom: 40px;
    }

    h1 {
      color: #212529;
      font-size: 1.5em;
      margin-bottom: 5px;
    }

    .subtitle {
      color: #6c757d;
      font-size: 1em;
    }

    .card {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-bottom: 20px;
    }

    .step-title {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
      color: #6c757d;
      font-size: 0.9em;
    }

    .icon {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 10px;
      font-size: 1em;
    }

    .upload-icon {
      background-color: #a5d8ff;
      color: #228be6;
    }

    .split-icon {
      background-color: #d3adf7;
      color: #6741d9;
    }

    .select-icon {
      background-color: #b2f2bc;
      color: #2f9e44;
    }

    label {
      display: block;
      margin-bottom: 5px;
      color: #495057;
      font-size: 0.9em;
    }

    input[type="text"], input[type="file"], select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ced4da;
      border-radius: 5px;
      margin-bottom: 15px;
      box-sizing: border-box;
      background-color: #f8f9fa;
    }

    .file-input {
      display: flex;
      align-items: center;
    }

    .file-input input[type="file"] {
      flex: 1;
      margin-right: 10px;
    }

    .file-input button {
      background-color: #6c757d;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
    }

    .required {
      color: #6c757d;
      font-size: 0.8em;
      margin-left: auto;
    }

    .button-group {
      display: flex;
      gap: 10px;
    }

    button {
      padding: 10px 20px;
      border: none;
      border-radius: 20px;
      cursor: pointer;
      color: #fff;
    }

    button.split {
      background-color: #6c757d;
    }

    button.finish {
      background-color: #f783ac;
    }

    /* Preview styles remain as is */
    .preview-wrap {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
    }

    .col h2 {
      margin: 0 0 10px;
      text-align: center;
      color: var(--green);
    }

    .grid9 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    .label {
      background: #fff;
      border: 1px dashed #ced4da;
      padding: 8px;
      text-align: center;
      border-radius: 6px;
      color: #212529;
    }

    .label small {
      display: block;
      margin-top: 4px;
      font-weight: 700;
    }

    /* Modal styles adjusted to match theme */
    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
    }

    .modal-content {
      background: #fff;
      margin: 15% auto;
      padding: 20px;
      width: 400px;
      border-radius: 10px;
      text-align: center;
      color: #212529;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .modal button {
      margin: 10px;
      padding: 8px 15px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .btn-ok {
      background: #28a745;
      color: #fff;
    }

    .btn-ok:hover {
      background: #218838;
    }

    .btn-cancel {
      background: #dc3545;
      color: #fff;
    }

    .btn-cancel:hover {
      background: #c82333;
    }

    span[style*="color:green;"] {
      color: #28a745 !important;
    }

    span[style*="color:blue;"] {
      color: #007bff !important;
    }

    span[style*="color:red;"] {
      color: #dc3545 !important;
    }

    p[style*="color:red;"] {
      color: #dc3545 !important;
    }
  </style>
</head>
<body>
  <header>
    <h1>PCB Barcode Print</h1>
    <p class="subtitle">Upload, split, and manage your PCB work orders</p>
  </header>

  <!-- Step 1: Upload -->
  <div class="card">
    <div class="step-title">
      <span class="icon upload-icon">↑</span>
      Step 1: Upload WO File
      <span class="required">Required</span>
    </div>
    <p>Upload your work order file and enter the WO number</p>
    <form method="POST" enctype="multipart/form-data">
      <label>WO Number</label>
      <input type="text" name="wo" placeholder="Enter work order number" required>
      <label>Upload File</label>
      <div class="file-input">
        <input type="file" name="file" required>
        <button type="submit" name="upload">Upload</button>
      </div>
      <?php if(!empty($msgUpload)) echo "<span style='color:green;'>$msgUpload</span>"; ?>
    </form>
  </div>

  <!-- Step 2: Split -->
  <div class="card">
    <div class="step-title">
      <span class="icon split-icon">⇧</span>
      Step 2: Split Data WO
    </div>
    <p>Process up to 999 items per work order</p>
    <form method="POST">
      <label>Select Work Order</label>
      <select name="wo_selected" required>
        <option value="">Choose a work order</option>
        <?php
        $woRes = $koneksi->query("SELECT wo, COUNT(*) jml FROM nomor_barcode WHERE wo IS NOT NULL GROUP BY wo ORDER BY wo");
        while ($w = $woRes->fetch_assoc()) {
          $woVal = htmlspecialchars($w['wo']);
          echo "<option value='{$woVal}'>{$woVal} ({$w['jml']})</option>";
        }
        ?>
      </select>
      <div class="button-group">
        <button type="submit" name="split" class="split">Split</button>
        <button type="submit" name="finish" class="finish" onclick="return confirm('Yakin hapus WO ini?')">Finish</button>
      </div>
      <?php if(!empty($msgSplit)) echo "<span style='color:blue;'>$msgSplit</span>"; ?>
      <?php if(!empty($msgFinish)) echo "<span style='color:red;'>$msgFinish</span>"; ?>
    </form>

    <!-- Tambahan Dropdown Pilih WO untuk Print (di bawah Step 2) -->
    <label>Select Work Order for Print</label>
    <form method="GET">
      <select name="wo" onchange="this.form.submit()">
        <option value="">Choose a work order</option>
        <?php mysqli_data_seek($woList, 0); // Reset pointer jika diperlukan
        while($wo = mysqli_fetch_assoc($woList)) { ?>
            <option value="<?= $wo['wo'] ?>" <?= $selectedWO==$wo['wo']?'selected':'' ?>>
                <?= $wo['wo'] ?>
            </option>
        <?php } ?>
      </select>
    </form>
  </div>

  <!-- Step 3: Select Batch -->
  <div class="card">
    <div class="step-title">
      <span class="icon select-icon">✓</span>
      Step 3: Select Batch
    </div>
    <p>Choose the batch for processing</p>
    <form method="GET">
      <input type="hidden" name="wo" value="<?= $selectedWO ?>">
      <select name="batch_id" onchange="this.form.submit()">
        <option value="">-- Pilih Batch --</option>
        <?php
        $batchQuery = "SELECT * FROM batch";
        if ($selectedWO != '') $batchQuery .= " WHERE wo='".mysqli_real_escape_string($koneksi,$selectedWO)."'";
        $batchQuery .= " ORDER BY id ASC";
        $batchList = mysqli_query($koneksi, $batchQuery);
        while($batch = mysqli_fetch_assoc($batchList)) {
            $id = (int)$batch['id'];
            $sel = ($selectedBatchId==$id)?"selected":""; 
            $label = htmlspecialchars($batch['nama_batch']);
            $disabled = '';
            $checkBatchPrint = $koneksi->query("SELECT COUNT(*) as cnt FROM log_print WHERE batch_id=$id");
            $batchPrintRow = $checkBatchPrint->fetch_assoc();
            if ($batchPrintRow['cnt'] > 0) {
                $disabled = 'disabled';
                $label .= ' (Sudah di-print)';
            }
            echo "<option value='{$id}' {$sel} {$disabled}>{$label}</option>";
        }
        ?>
      </select>
    </form>
  </div>

  <!-- Preview -->
  <?php if ($selectedBatchId): ?>
  <div class="card">
    <?php if (!empty($awal9) || !empty($akhir9)): ?>
      <div class="preview-wrap">
        <div class="col">
          <h2>AWAL</h2>
          <div class="grid9">
            <?php foreach ($awal9 as $i => $num): ?>
              <div class="label">
                <svg id="awal-<?= $i ?>"></svg>
                <small><?= htmlspecialchars($num) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col">
          <h2>AKHIR</h2>
          <div class="grid9">
            <?php foreach ($akhir9 as $i => $num): ?>
              <div class="label">
                <svg id="akhir-<?= $i ?>"></svg>
                <small><?= htmlspecialchars($num) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php if ($isPrinted): ?>
        <p style="color:red; font-weight:bold;">Batch ini sudah di-print dan tidak dapat di-print ulang.</p>
      <?php else: ?>
      <form id="printForm" method="POST">
        <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">
        <input type="hidden" name="wo" value="<?= htmlspecialchars($woBatch) ?>">
        <input type="hidden" name="start" value="<?= htmlspecialchars($start) ?>">
        <input type="hidden" name="end" value="<?= htmlspecialchars($end) ?>">
        <input type="hidden" name="print" id="printHidden" value="">
        <label>Custom Text: <input type="text" name="custom_text" placeholder="Tulisan tambahan"></label><br>
        <button type="submit" name="test_print">Test Print</button>
        <button type="button" onclick="openModal()">Print</button>
      </form>
      <?php endif; ?>

      <!-- Modal Konfirmasi -->
      <div id="myModal" class="modal">
          <div class="modal-content">
              <h3>Konfirmasi Print</h3>
              <p>Yakin ingin melakukan Print dan simpan ke database?</p>
              <button class="btn-ok" onclick="submitForm()">Ya, Print</button>
              <button class="btn-cancel" onclick="closeModal()">Batal</button>
          </div>
      </div>

      <!-- Modal Hasil -->
      <?php if (!empty($msg)): ?>
      <div id="resultModal" class="modal" style="display:block;">
          <div class="modal-content">
              <p><?= htmlspecialchars($msg) ?></p>
              <button class="btn-cancel" onclick="closeResult()">OK</button>
          </div>
      </div>
      <?php endif; ?>

      <script>
          function openModal() {
              document.getElementById("myModal").style.display = "block";
          }
          function closeModal() {
              document.getElementById("myModal").style.display = "none";
          }
          function submitForm() {
              // Set hidden input untuk menandai ini adalah 'print'
              document.getElementById("printHidden").value = "1";
              closeModal();
              document.getElementById("printForm").submit();
          }
          function closeResult() {
              document.getElementById("resultModal").style.display = "none";
              // Optional: redirect kembali ke halaman utama atau refresh jika diperlukan
              // window.location.href = window.location.pathname + '?wo=<?= $selectedWO ?>&batch_id=<?= $selectedBatchId ?>';
          }
      </script>

      <script>
        (function () {
          var awal = <?= json_encode($awal9) ?>;
          var akhir = <?= json_encode($akhir9) ?>;
          function draw(list, prefix){
            for (var i=0;i<list.length;i++){
              JsBarcode("#"+prefix+"-"+i, String(list[i]), {
                format:"CODE128", displayValue:true, fontSize:15, height:50
              });
            }
          }
          draw(awal,"awal"); draw(akhir,"akhir");
        })();
      </script>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($printScript)) echo $printScript; ?>
</body>
</html>