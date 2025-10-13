<!-- HXE12FR 01-07-2025 Batch(9)1 -->
<?php
$start = isset($_POST['start']) ? intval($_POST['start']) : 6001376001;
$text  = isset($_POST['text']) ? $_POST['text'] : 'HXE12FR 01-07-2025 Batch';
$from  = isset($_POST['from']) ? intval($_POST['from']) : 1;
$to    = isset($_POST['to']) ? intval($_POST['to']) : 9;

require 'koneksi.php';
// Validasi range
if ($from < 1) $from = 1;
if ($to > 999) $to = 999;
if ($from > $to) $from = $to;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Barcode Print</title>
<link rel="stylesheet" href="style.css">
<script src="js/JsBarcode.all.min.js"></script>
<style>
body {
  font-family: Arial, sans-serif;
  margin: 0;
  padding: 0;
}

.page {
  width: 8cm;
  height: 3.4cm;
  page-break-after: always;
}

.label {
  width: 2.5cm;
  height: 1.1cm;
  margin: 0;
  padding: 0;
  float: left;
  box-sizing: border-box;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.label-content {
  width: 2.3cm;
  height: 0.5cm;
  font-size: 8px;
  line-height: 1.2;
  margin: 0 auto;
}

svg {
  width: 2.3cm;
  height: 0.5cm;
}
</style>
</head>
<body>

<form method="POST" style="margin:20px;">
  <label>Nomor Awal:</label>
  <input type="number" name="start" value="<?= $start ?>">
  <label>Teks bawah barcode:</label>
  <input type="text" name="text" value="<?= htmlspecialchars($text) ?>">
  <label>From:</label>
  <input type="number" name="from" value="<?= $from ?>" min="1">
  <label>To:</label>
  <input type="number" name="to" value="<?= $to ?>" max="999">
  <button type="submit">Generate</button>
</form>

<div style="clear:both;"></div>

<div id="printArea">
<?php 
$count = 0;
for ($i = $from -1; $i <= $to -1; $i++) {
    $code = str_pad($start + $i, 10, '0', STR_PAD_LEFT);

    echo '<div class="label">
            <svg id="barcode_'.$i.'"></svg>
            <div class="label-content">
              '.$code.'<br>'.htmlspecialchars($text).'
            </div>
          </div>';
    
    $count++;
    if ($count % 9 == 0) echo '<div style="clear:both;"></div>';
}
?>
</div>

<script>
<?php 
for ($i = $from -1; $i <= $to -1; $i++) {
  $code = str_pad($start + $i, 10, '0', STR_PAD_LEFT);
  echo "JsBarcode('#barcode_$i', '$code', {
          format: 'CODE128',
          displayValue: false,
          fontSize: 8,
          height: 20
        });";
}
?>
</script>

</body>
</html>

