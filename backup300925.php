<?php
// ====== KONEKSI ======
$koneksi = new mysqli("localhost", "root", "", "test");
if ($koneksi->connect_errno) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
include 'koneksi.php';

// Ambil daftar WO unik
$woList = mysqli_query($koneksi, "SELECT DISTINCT wo FROM batch ORDER BY wo ASC");
$selectedWO = isset($_GET['wo']) ? $_GET['wo'] : '';

// ====== UPLOAD WO ======
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
if (isset($_POST['finish']) && !empty($_POST['wo_selected'])) {
    $wo = $koneksi->real_escape_string($_POST['wo_selected']);
    $koneksi->query("DELETE FROM nomor_barcode WHERE wo='$wo'");
    $koneksi->query("DELETE FROM batch WHERE wo='$wo'");
    $msgFinish = "WO $wo sudah dihapus!";
}

$printScript = ''; 
if (isset($_POST['print']) || isset($_POST['test_print'])) {
    $batch_id   = (int)$_POST['batch_id'];
    $wo         = $koneksi->real_escape_string($_POST['wo']);
    $start      = $koneksi->real_escape_string($_POST['start']);
    $end        = $koneksi->real_escape_string($_POST['end']);
    $customText = isset($_POST['custom_text']) ? $koneksi->real_escape_string($_POST['custom_text']) : '';

    if (isset($_POST['print'])) {
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

    // simpan pesan ke session / query string
    header("Location: ".$_SERVER['PHP_SELF']."?wo=$wo&batch_id=$batch_id&msg=".urlencode($msg));
    exit;

    ob_start();
?>
<script>
        // Confirm sebelum kirim form
        function confirmPrint() {
            return confirmed("Yakin ingin melakukan Print dan simpan ke database?");
        }
</script>
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
    width: 90%;
    height: 0.8cm;
  }
  .label small {
    font-size: 7pt;
    font-weight: bold;
    margin-top: -4px;
  }
  .custom {
    font-size: 4pt;
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
    echo "w.document.write('JsBarcode(\"#{$id}\",\"{$num}\",{format:\"CODE128\",displayValue:false,fontSize:10,height:40});');\n";
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

// ====== DATA PREVIEW BATCH ======
$selectedBatchId = isset($_GET['batch_id']) && $_GET['batch_id'] !== '' ? (int)$_GET['batch_id'] : null;
$awal9 = $akhir9 = []; $woBatch = ''; $start = ''; $end = '';
if ($selectedBatchId) {
    $res = $koneksi->query("SELECT nomor, wo FROM nomor_barcode WHERE batch_id=$selectedBatchId ORDER BY id ASC");
    $numbers = [];
    while ($r = $res->fetch_assoc()) { $numbers[] = $r['nomor']; $woBatch = $r['wo']; }
    if (count($numbers) > 0) {
        $awal9  = array_slice($numbers, 0, 9);
        $akhir9 = array_slice($numbers, -9);
        $start  = $numbers[0]; $end = end($numbers);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Barcode</title>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; padding:20px; }
    form { margin-bottom:20px; }
    .card { border:1px solid #ddd; padding:15px; border-radius:10px; margin-bottom:20px; }
    .preview-wrap { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    .col h2 { margin:0 0 10px; text-align:center; }
    .grid9 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
    .label { border:1px dashed #aaa; padding:8px; text-align:center; border-radius:6px; }
    .label small { display:block; margin-top:4px; font-weight:700; }
    button { margin-top:15px; padding:8px 16px; border:none; border-radius:6px; cursor:pointer; }
    button[name="test_print"] { background:#666; color:#fff; margin-right:10px; }
    button[name="print"] { background:#28a745; color:#fff; }
    /* Style Modal */
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
        }
        .modal button {
            margin: 10px;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-ok { background: #28a745; color: #fff; }
        .btn-cancel { background: #dc3545; color: #fff; }
  </style>
</head>
<body>
  <h2>Dashboard Barcode</h2>

  <!-- Upload -->
  <div class="card">
    <h3>Upload File Nomor (WO)</h3>
    <form method="POST" enctype="multipart/form-data">
      <label>WO: <input type="text" name="wo" required></label>
      <input type="file" name="file" required>
      <button type="submit" name="upload">Upload</button>
      <?php if(isset($msgUpload)) echo "<span style='color:green;'>$msgUpload</span>"; ?>
    </form>
  </div>

  <!-- Split + Finish -->
  <div class="card">
    <h3>Split Data per WO (999 nomor)</h3>
    <form method="POST">
      <label>Pilih WO:</label>
      <select name="wo_selected" required>
        <option value="">-- Pilih WO --</option>
        <?php
        $woRes = $koneksi->query("SELECT wo, COUNT(*) jml FROM nomor_barcode WHERE wo IS NOT NULL GROUP BY wo ORDER BY wo");
        while ($w = $woRes->fetch_assoc()) {
          $woVal = htmlspecialchars($w['wo']);
          echo "<option value='{$woVal}'>{$woVal} ({$w['jml']})</option>";
        }
        ?>
      </select>
      <button type="submit" name="split">Split</button>
      <button type="submit" name="finish" onclick="return confirm('Yakin hapus WO ini?')">Finish</button>
      <?php if(isset($msgSplit)) echo "<span style='color:blue;'>$msgSplit</span>"; ?>
      <?php if(isset($msgFinish)) echo "<span style='color:red;'>$msgFinish</span>"; ?>
    </form>
  </div>

  <!-- Pilih WO -->
  <form method="GET">
    <label>Pilih WO:</label>
    <select name="wo" onchange="this.form.submit()">
        <option value="">-- Pilih WO --</option>
        <?php while($wo = mysqli_fetch_assoc($woList)) { ?>
            <option value="<?= $wo['wo'] ?>" <?= $selectedWO==$wo['wo']?'selected':'' ?>>
                <?= $wo['wo'] ?>
            </option>
        <?php } ?>
    </select>
  </form>

  <!-- Pilih Batch -->
  <div class="card">
    <h3>Pilih Batch</h3>
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
            $label = "Batch {$id} | WO: ".htmlspecialchars($batch['wo']);
            echo "<option value='{$id}' {$sel}>{$label}</option>";
        }
        ?>
      </select>
    </form>
    <?php if(isset($msgPrint)) echo "<span style='color:blue;'>$msgPrint</span>"; ?>
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
      <form id="printForm" method="POST">
        <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">
        <input type="hidden" name="wo" value="<?= htmlspecialchars($woBatch) ?>">
        <input type="hidden" name="start" value="<?= htmlspecialchars($start) ?>">
        <input type="hidden" name="end" value="<?= htmlspecialchars($end) ?>">
        <input type="hidden" name="print" id="printHidden">
        <label>Custom Text: <input type="text" name="custom_text" placeholder="Tulisan tambahan"></label><br>
        <button type="submit" name="test_print">Test Print</button>
        <button type="button" name="print" id="printHidden" onclick="openModal()">Print</button>

    <!-- Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <h3>Konfirmasi Print</h3>
            <p>Yakin ingin melakukan Print dan simpan ke database?</p>
            <button class="btn-ok" onclick="submitForm()">Ya, Print</button>
            <button class="btn-cancel" onclick="closeModal()">Batal</button>
        </div>
    </div>

    <!-- Modal hasil dari PHP -->
    <?php if (!empty($msgPrint)): ?>
    <div id="resultModal" class="modal" style="display:block;">
        <div class="modal-content">
            <p><?= $msgPrint ?></p>
            <button class="btn-cancel" onclick="closeResult()">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if(isset($_GET['msg'])): ?>
      
    <div id="resultModal" class="modal" style="display:block;">
        <div class="modal-content">
            <p><?= htmlspecialchars($_GET['msg']) ?></p>
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
            // isi hidden input supaya PHP tahu ini Print
            document.getElementById("printHidden").value = "1";

            // tutup modal
            closeModal();

            // submit form
            document.getElementById("printForm").submit();
        }
        function closeResult() {
            document.getElementById("resultModal").style.display = "block";
        }
    </script>

      </form>
      <script>
        (function () {
          var awal = <?= json_encode($awal9) ?>;
          var akhir = <?= json_encode($akhir9) ?>;
          function draw(list, prefix){
            for (var i=0;i<list.length;i++){
              JsBarcode("#"+prefix+"-"+i, String(list[i]), {
                format:"CODE128", displayValue:true, fontSize:14, height:50
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
