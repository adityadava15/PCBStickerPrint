<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: Login1.php");
    exit;
}

// ====== KONEKSI ======
$koneksi = new mysqli("localhost", "root", "", "test");
if ($koneksi->connect_errno) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Ambil daftar WO unik dari batch
$woList = mysqli_query($koneksi, "SELECT DISTINCT wo FROM batch ORDER BY wo ASC");
$selectedWO = isset($_GET['wo']) ? $_GET['wo'] : '';

$printScript = ''; 
$msg = '';
$showRecordModal = false;
$tempBatchId = $tempWo = $tempStart = $tempEnd = $tempTypeMeter = $tempDate = $tempBatchNum = '';
if (isset($_POST['print'])) {
    $batch_id   = (int)$_POST['batch_id'];
    $wo         = $koneksi->real_escape_string($_POST['wo']);
    $start      = $koneksi->real_escape_string($_POST['start']);
    $end        = $koneksi->real_escape_string($_POST['end']);
    // Gabung custom text dari 2 field + batch num auto
    $type_meter = isset($_POST['type_meter']) ? $koneksi->real_escape_string($_POST['type_meter']) : '';
    $date = isset($_POST['date']) ? date('d-m-Y', strtotime($_POST['date'])) : date('d-m-Y');
    // Ambil batch num dari nama_batch
    $batchRes = $koneksi->query("SELECT nama_batch FROM batch WHERE id=$batch_id");
    $batchRow = $batchRes->fetch_assoc();
    $nama_batch = $batchRow['nama_batch'];
    preg_match('/Batch (\d+)/', $nama_batch, $matches);
    $batch_num = isset($matches[1]) ? $matches[1] : '';
    $customText = $type_meter . ' ' . $date . ' Batch ' . $batch_num;

    // Simpan temp data untuk record nanti
    $tempBatchId = $batch_id;
    $tempWo = $wo;
    $tempStart = $start;
    $tempEnd = $end;
    $tempTypeMeter = $type_meter;
    $tempDate = $date;
    $tempBatchNum = $batch_num;

    // Ambil numbers untuk print
    $res = $koneksi->query("SELECT nomor FROM nomor_barcode WHERE batch_id=$batch_id ORDER BY id ASC");
    $numbers = [];
    while ($r = $res->fetch_assoc()) { $numbers[] = $r['nomor']; }

    // Generate print script tanpa record dulu
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
  }
  .page {
    width: 8cm;
    height: 3.4cm;
    margin-top: -10px;
    page-break-after: always;
  }
  .page table {
    width: 100%;
    height: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    margin-top: -5px;
  }
  .label {
    text-align: center;
    vertical-align: middle;
    font-size: 8pt;
  }
  .label svg {
    width: 88px;
    height: 0.79cm;
    margin-bottom: 0px;
  }
  .label small {
    font-family: Arial, sans-serif;
    font-size: 6.1pt;
    font-weight: bold;
    margin-top: -4.6px;
    display: block;
  }
  .custom {
    font-family: Arial, sans-serif;
    font-size: 4.4pt;
    font-weight: bold;
    margin-top: -1px;
  }
`);
w.document.write('</style></head><body onload="window.print();">');

<?php 
$chunks = array_chunk($numbers, 9); 
foreach ($chunks as $pageIndex => $chunk): ?>
  w.document.write('<div class="page"><table><tr>');
  <?php for ($row = 0; $row < 3; $row++): ?>
    w.document.write('<tr>');
    <?php for ($col = 0; $col < 3; $col++): ?>
      <?php $idx = $row * 3 + $col; ?>
      <?php if (isset($chunk[$idx])): $num = $chunk[$idx]; $id = "code{$pageIndex}_{$idx}"; ?>
        w.document.write('<td class="label">');
        w.document.write('<svg id="<?= $id ?>"></svg>');
        w.document.write('<small><?= $num ?></small>');
        w.document.write('<div class="custom"><?= htmlspecialchars($customText) ?></div>');
        w.document.write('</td>');
      <?php else: ?>
        w.document.write('<td></td>');
      <?php endif; ?>
    <?php endfor; ?>
    w.document.write('</tr>');
  <?php endfor; ?>
  w.document.write('</table></div>');
<?php endforeach; ?>

w.document.write('<script src="JS2/JsBarcode.all.min.js"><\/script>');
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

    $showRecordModal = true;  // Tampilkan modal setelah print window dibuka
}

if (isset($_POST['record'])) {
    $batch_id = (int)$_POST['batch_id'];
    $wo = $koneksi->real_escape_string($_POST['wo']);
    $start = $koneksi->real_escape_string($_POST['start']);
    $end = $koneksi->real_escape_string($_POST['end']);

    $batchRes = $koneksi->query("SELECT nama_batch FROM batch WHERE id=$batch_id");
    $batchRow = $batchRes->fetch_assoc();
    $nama_batch = $batchRow['nama_batch'];

    $sql = "INSERT INTO log_print (start_number, end_number, batch_id, nama_batch, printed_at) 
            VALUES ('$start','$end','$batch_id','$nama_batch',NOW())";
    $koneksi->query($sql);

    $msg = "✔ Data batch $batch_id berhasil disimpan ke log_print!";
    header("Location: " . $_SERVER['PHP_SELF'] . "?wo=" . urlencode($wo));
    exit;
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
  <script src="JS2/JsBarcode.all.min.js"></script>
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
      color: #28a745;
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

    .custom-fields {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .custom-fields select, .custom-fields input {
      flex: 1;
      min-width: 120px;
    }
  </style>
</head>
<body>
  <header>
    <h1>PCB Barcode Print</h1>
    <p class="subtitle">Upload, split, and manage your PCB work orders</p>
  </header>

  <!-- Select Work Order for Print -->
  <div class="card">
    <div class="step-title">
      <span class="icon select-icon">✓</span>
      Select Work Order for Print
    </div>
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

  <!-- Select Batch -->
  <div class="card">
    <div class="step-title">
      <span class="icon select-icon">✓</span>
      Select Batch
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
        <input type="hidden" name="print" value="1">
        <label>Custom Text:</label>
        <div class="custom-fields">
          <select name="type_meter" required>
            <option value="">Pilih Type Meter</option>
            <option value="HXE310">HXE310</option>
            <option value="HXE12FR">HXE12FR</option>
            <option value="HXF300">HXF300</option>
            <!-- Tambah pilihan lain jika perlu -->
          </select>
          <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
          <!-- Hapus dropdown batch_num -->
        </div>
        <button type="submit">Print</button>
      </form>
      <?php endif; ?>

      <!-- Modal Konfirmasi Record -->
      <div id="recordModal" class="modal" <?= $showRecordModal ? 'style="display:block;"' : '' ?>>
          <div class="modal-content">
              <h3>Konfirmasi Record</h3>
              <p>Apakah batch ini ingin direcord ke database?</p>
              <form method="POST" id="recordForm">
                <input type="hidden" name="batch_id" value="<?= $tempBatchId ?>">
                <input type="hidden" name="wo" value="<?= $tempWo ?>">
                <input type="hidden" name="start" value="<?= $tempStart ?>">
                <input type="hidden" name="end" value="<?= $tempEnd ?>">
                <input type="hidden" name="record" value="1">
                <button type="submit" class="btn-ok">Ya</button>
              </form>
              <button class="btn-cancel" onclick="window.location.href = window.location.pathname">Tidak</button>
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
          function closeResult() {
              document.getElementById("resultModal").style.display = "none";
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

  <div style="text-align: right;">
    <a href="Logout1.php">Logout</a>
  </div>

  <?php if (!empty($printScript)) echo $printScript; ?>
</body>
</html>