<?php
$start = $_POST['start'] ?? 6001200000;
$text = $_POST['text'] ?? 'Custom Text';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Cetak Barcode</title>
  <script src="js/JsBarcode.all.min.js"></script>
  <style>
    @media print {
      body {
        margin: 0;
        padding: 0;
      }
    }
    .label {
      width: 8cm;
      height: 3.4cm;
      margin: 0.1cm;
      float: left;
      text-align: center;
      font-family: Arial, sans-serif;
    }
    svg {
      width: 100%;
      height: 60%;
    }
    .code {
      font-size: 14px;
      font-weight: bold;
    }
    .text {
      font-size: 12px;
    }
    .page-break {
      page-break-after: always;
      clear: both;
    }
  </style>
</head>
<body onload="window.print()">
  <?php for ($i = 0; $i < 1000; $i++):
    $code = str_pad($start + $i, 10, '0', STR_PAD_LEFT); ?>
    <div class="label">
      <svg id="barcode_<?= $i ?>"></svg>
      <div class="code"><?= $code ?></div>
      <div class="text"><?= $text ?></div>
    </div>
    <?php if (($i + 1) % 3 === 0) echo "<div class='page-break'></div>"; ?>
  <?php endfor; ?>
  <script>
    <?php for ($i = 0; $i < 1000; $i++):
      $code = str_pad($start + $i, 10, '0', STR_PAD_LEFT); ?>
      JsBarcode("#barcode_<?= $i ?>", "<?= $code ?>", {
        format: "CODE128",
        displayValue: false,
        fontSize: 16,
        height: 40
      });
    <?php endfor; ?>
  </script>
</body>
</html>
