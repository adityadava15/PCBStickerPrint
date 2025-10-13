<?php
$start = $_POST['start'] ?? 6001200000;
$text = $_POST['text'] ?? 'Custom Text';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Preview Barcode</title>
  <script src="js/JsBarcode.all.min.js"></script>
  <style>
    .label {
      width: 8cm;
      height: 3.4cm;
      margin: 0.2cm;
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
    .clear {
      clear: both;
    }
  </style>
</head>
<body>
  <h3>Preview Barcode</h3>
  <form method="POST" action="print.php" target="_blank">
    <input type="hidden" name="start" value="<?= $start ?>">
    <input type="hidden" name="text" value="<?= htmlspecialchars($text) ?>">
    <button type="submit">Cetak</button>
  </form>
  <div id="previewArea">
    <?php for ($i = 0; $i < 1000; $i++):
      $code = str_pad($start + $i, 10, '0', STR_PAD_LEFT); ?>
      <div class="label">
        <svg id="barcode_<?= $i ?>"></svg>
        <div class="code"><?= $code ?></div>
        <div class="text"><?= $text ?></div>
      </div>
    <?php endfor; ?>
  </div>
  <div class="clear"></div>
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
