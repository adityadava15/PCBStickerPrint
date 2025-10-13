<?php
require 'koneksi.php';
require 'session.php';

// Proteksi login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Timeout session
$timeout = 1200; // 20 menit
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
} elseif (time() - $_SESSION['last_activity'] > $timeout) {
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// Ambil nilai dari form
$start = $_POST['start'] ?? 6001376001;
$text  = $_POST['text']  ?? 'HXE12FR 08-07-2025 Batch(10)1';
$from  = $_POST['from']  ?? 1;
$to    = $_POST['to']    ?? 999;

$showBarcode = false;
$triggerPrint = false;
$duplikat = false;
$isTestPrint = false;

$end = $start + ($to - 1); // ✅ perhitungan nomor akhir

// Cek generate
if (isset($_POST['generate'])) {
    $check = $con->query("SELECT * FROM log_print WHERE start_number = $start AND end_number = $end");
    if ($check->num_rows > 0) $duplikat = true;
    $showBarcode = true;
}

// Proses cetak normal (simpan ke DB)
if (isset($_POST['print_confirm']) && empty($_POST['test_print'])) {
    $stmt = $con->prepare("INSERT INTO log_print (start_number, end_number) VALUES (?, ?)");
    $stmt->bind_param("ii", $start, $end);
    $stmt->execute();
    $showBarcode = true;
    $triggerPrint = true;
}

// Proses test print (tidak simpan ke DB)
if (isset($_POST['test_print'])) {
    $showBarcode = true;
    $isTestPrint = true;
    $triggerPrint = true;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Barcode Generator</title>
<link rel="stylesheet" href="CSS/style.css">
<script src="js/JsBarcode.all.min.js"></script>
<style>
#popup {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: none;
  justify-content: center;
  align-items: center;
}
#popup .modal {
  background: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}
.range-info { /* ✅ styling tambahan */
  margin: 10px 0;
  padding: 8px;
  background: #f3f3f3;
  border-left: 4px solid #007BFF;
  display: inline-block;
}
</style>
</head>
<body>

<div class="form-container no-print">
<h2>Generate & Cetak Barcode</h2>
<form method="POST">
  <label>Nomor Awal:</label>
  <input type="number" name="start" value="<?= $start ?>" required><br><br>

  <label>Teks di bawah barcode:</label>
  <input class="text-input" type="text" name="text" value="<?= htmlspecialchars($text) ?>" required><br><br>

  <label>Cetak dari label ke-</label>
  <input type="number" name="from" min="1" value="<?= $from ?>" required>
  sampai
  <input type="number" name="to" min="1" value="<?= $to ?>" required><br><br>

  <button type="submit" name="generate">Generate</button>
</form>

<?php if ($showBarcode): ?>
  <!-- ✅ Info Nomor Awal - Nomor Akhir -->
  <div class="range-info">
    <strong>Range Nomor:</strong> 
    <?= str_pad($start, 10, '0', STR_PAD_LEFT) ?> - 
    <?= str_pad($end, 10, '0', STR_PAD_LEFT) ?>
  </div><br><br>

  <form id="printForm" method="POST" class="no-print">
    <input type="hidden" name="start" value="<?= $start ?>">
    <input type="hidden" name="text" value="<?= htmlspecialchars($text) ?>">
    <input type="hidden" name="from" value="<?= $from ?>">
    <input type="hidden" name="to" value="<?= $to ?>">
    <input type="hidden" name="print_confirm">
    <button type="button" id="printBtn">Print</button>
  </form>

  <form method="POST" class="no-print">
    <input type="hidden" name="start" value="<?= $start ?>">
    <input type="hidden" name="text" value="<?= htmlspecialchars($text) ?>">
    <input type="hidden" name="from" value="<?= $from ?>">
    <input type="hidden" name="to" value="<?= $to ?>">
    <button type="submit" name="test_print">Test Print</button>
  </form>
<?php endif; ?>
</div>

<p class="no-print"><a href="logout.php">Logout</a></p>

<?php if ($showBarcode): ?>
<div id="barcodeArea" class="labels-container">
<?php for ($i = $from - 1; $i <= $to - 1; $i++): ?>
<div class="label">
  <svg id="barcode_<?= $i ?>"></svg>
  <div class="code"><?= str_pad($start + $i, 10, '0', STR_PAD_LEFT) ?></div>
  <div class="text"><?= htmlspecialchars($text) ?></div>
</div>
<?php endfor; ?>
</div>

<script>
<?php for ($i = $from - 1; $i <= $to - 1; $i++): ?>
JsBarcode("#barcode_<?= $i ?>", "<?= str_pad($start + $i, 10, '0', STR_PAD_LEFT) ?>", {
  format: "CODE128",
  displayValue: false,
  height: 30,
  margin: 0
});
<?php endfor; ?>
</script>
<?php endif; ?>

<?php if ($triggerPrint): ?>
<script>
window.onload = () => setTimeout(() => window.print(), 1000);
</script>
<?php endif; ?>

<div id="popup">
  <div class="modal">
    <p><strong>Nomor range sudah pernah dicetak!</strong><br>Mau cetak ulang?</p>
    <button onclick="closePopup()">Tidak</button>
    <button onclick="confirmPrint()">Print</button>
  </div>
</div>

<script>
document.getElementById('printBtn')?.addEventListener('click', () => {
  const isDuplikat = <?= $duplikat ? 'true' : 'false' ?>;
  if (isDuplikat) {
    document.getElementById('popup').style.display = 'flex';
  } else {
    confirmPrint();
  }
});

function confirmPrint() {
  document.querySelector('[name="print_confirm"]').value = '1';
  document.getElementById('printForm').submit();
}

function closePopup() {
  document.getElementById('popup').style.display = 'none';
}
</script>

</body>
</html>
