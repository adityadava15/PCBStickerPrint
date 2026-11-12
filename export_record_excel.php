<?php
// export_record_excel.php
// Fitur Download Record Range Excel (20 Batch / 20.000 Nomor)
// Sesuai format template Report Print Meter Number / PCB Number QC

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    die("Akses ditolak: Silakan login terlebih dahulu.");
}

$koneksi = new mysqli("192.168.88.254:3307", "root", "", "test");
if ($koneksi->connect_errno) {
    http_response_code(500);
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper untuk sanitasi & deteksi tipe meter
function getCleanTypes($koneksi) {
    $types = [];
    $res = $koneksi->query("SELECT option_name FROM type_meter_option ORDER BY id ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $types[] = $r['option_name'];
        }
    }
    return $types;
}

function detectMeterTypeFromWo($wo, $types) {
    $woClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $wo));
    foreach ($types as $t) {
        $tClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t));
        if ($tClean !== '' && strpos($woClean, $tClean) !== false) {
            return str_replace(['-', '_', ' '], '', $tClean);
        }
        $core = preg_replace('/^HXE|^HXF|^HXM/i', '', $tClean);
        if ($core !== '' && strlen($core) >= 3 && strpos($woClean, $core) !== false) {
            return str_replace(['-', '_', ' '], '', $tClean);
        }
    }
    return '';
}

function resolveTables($category) {
    if ($category === 'ev_charger') {
        return [
            'category' => 'ev_charger',
            'label' => 'EV Charger',
            'batch_table' => 'ev_charger_batch',
            'number_table' => 'ev_charger_nomor_barcode',
            'log_table' => 'ev_charger_log_print',
            'default_title_prefix' => 'REPORT PRINT BARCODE EV CHARGER'
        ];
    } elseif ($category === 'nameplate') {
        return [
            'category' => 'nameplate',
            'label' => 'Nameplate',
            'batch_table' => 'nameplate_batch',
            'number_table' => 'nameplate_nomor_barcode',
            'log_table' => 'nameplate_log_print',
            'default_title_prefix' => 'REPORT PRINT NAMEPLATE'
        ];
    } else {
        return [
            'category' => 'pcb',
            'label' => 'PCB (Smart Meter)',
            'batch_table' => 'batch',
            'number_table' => 'nomor_barcode',
            'log_table' => 'log_print',
            'default_title_prefix' => 'REPORT PRINT METER NUMBER/PCB NUMBER'
        ];
    }
}

// -------------------------------------------------------------------------
// ACTION: get_wo_list (mengambil daftar WO untuk kategori tertentu)
// -------------------------------------------------------------------------
if ($action === 'get_wo_list') {
    header('Content-Type: application/json; charset=utf-8');
    $category = $_GET['category'] ?? 'pcb';
    $cfg = resolveTables($category);

    $sql = "SELECT b.wo, COUNT(DISTINCT b.id) AS total_batches, COUNT(n.id) AS total_barcodes 
            FROM {$cfg['batch_table']} b 
            LEFT JOIN {$cfg['number_table']} n ON b.id = n.batch_id 
            WHERE b.wo IS NOT NULL AND b.wo != '' 
            GROUP BY b.wo 
            ORDER BY b.wo ASC";
    $res = $koneksi->query($sql);
    $woList = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $woList[] = [
                'wo' => $row['wo'],
                'total_batches' => (int)$row['total_batches'],
                'total_barcodes' => (int)$row['total_barcodes']
            ];
        }
    }
    echo json_encode(['success' => true, 'category' => $category, 'wo_list' => $woList]);
    exit;
}

// -------------------------------------------------------------------------
// ACTION: get_wo_batches (mengambil batch dan rentang nomor untuk WO tertentu)
// -------------------------------------------------------------------------
if ($action === 'get_wo_batches') {
    header('Content-Type: application/json; charset=utf-8');
    $category = $_GET['category'] ?? 'pcb';
    $cfg = resolveTables($category);
    $wo = trim((string)($_GET['wo'] ?? ''));

    if ($wo === '') {
        echo json_encode(['success' => false, 'error' => 'WO tidak boleh kosong']);
        exit;
    }

    $safeWo = $koneksi->real_escape_string($wo);
    $sql = "SELECT id, nama_batch FROM {$cfg['batch_table']} WHERE wo='$safeWo' ORDER BY id ASC";
    $res = $koneksi->query($sql);

    $batches = [];
    $idx = 1;
    while ($res && $row = $res->fetch_assoc()) {
        $num = $idx;
        if (preg_match('/Batch\s+(\d+)/i', $row['nama_batch'], $m)) {
            $num = (int)$m[1];
        }
        $batches[] = [
            'id' => (int)$row['id'],
            'batch_num' => $num,
            'nama_batch' => $row['nama_batch']
        ];
        $idx++;
    }

    // Sort berdasarkan batch_num
    usort($batches, function($a, $b) {
        return $a['batch_num'] <=> $b['batch_num'];
    });

    $minBatch = !empty($batches) ? $batches[0]['batch_num'] : 1;
    $maxBatch = !empty($batches) ? end($batches)['batch_num'] : 1;
    $totalBatches = count($batches);

    // Deteksi judul default
    $defaultTitle = $cfg['default_title_prefix'];
    if ($cfg['category'] === 'pcb') {
        $types = getCleanTypes($koneksi);
        $meterType = detectMeterTypeFromWo($wo, $types);
        if ($meterType !== '') {
            $defaultTitle .= ' ' . $meterType;
        } else {
            $defaultTitle .= ' ' . $wo;
        }
    } else {
        $defaultTitle .= ' ' . $wo;
    }

    echo json_encode([
        'success' => true,
        'wo' => $wo,
        'category' => $category,
        'total_batches' => $totalBatches,
        'min_batch' => $minBatch,
        'max_batch' => $maxBatch,
        'default_title' => $defaultTitle,
        'batches' => $batches
    ]);
    exit;
}

// -------------------------------------------------------------------------
// ACTION: download_excel (Menghasilkan dan mendownload file Excel .xls)
// -------------------------------------------------------------------------
if ($action === 'download_excel') {
    $category = $_GET['category'] ?? 'pcb';
    $cfg = resolveTables($category);
    $wo = trim((string)($_GET['wo'] ?? ''));
    $startBatch = isset($_GET['start_batch']) ? (int)$_GET['start_batch'] : 1;
    $endBatch = isset($_GET['end_batch']) ? (int)$_GET['end_batch'] : 20;
    $customTitle = trim((string)($_GET['title'] ?? ''));
    $includePrintInfo = isset($_GET['include_print_info']) && $_GET['include_print_info'] === '1';

    if ($wo === '') {
        die("Error: Silakan pilih Work Order terlebih dahulu.");
    }

    if ($startBatch <= 0) $startBatch = 1;
    if ($endBatch < $startBatch) $endBatch = $startBatch;

    $safeWo = $koneksi->real_escape_string($wo);
    $batchRes = $koneksi->query("SELECT id, nama_batch FROM {$cfg['batch_table']} WHERE wo='$safeWo' ORDER BY id ASC");
    if (!$batchRes || $batchRes->num_rows === 0) {
        die("Error: Tidak ada batch yang ditemukan untuk WO: " . htmlspecialchars($wo));
    }

    $allBatches = [];
    $idx = 1;
    while ($row = $batchRes->fetch_assoc()) {
        $num = $idx;
        if (preg_match('/Batch\s+(\d+)/i', $row['nama_batch'], $m)) {
            $num = (int)$m[1];
        }
        $allBatches[] = [
            'id' => (int)$row['id'],
            'batch_num' => $num,
            'nama_batch' => $row['nama_batch']
        ];
        $idx++;
    }

    // Urutkan berdasarkan batch_num
    usort($allBatches, function($a, $b) {
        return $a['batch_num'] <=> $b['batch_num'];
    });

    // Filter sesuai start_batch dan end_batch
    $targetBatches = [];
    $targetIds = [];
    foreach ($allBatches as $b) {
        if ($b['batch_num'] >= $startBatch && $b['batch_num'] <= $endBatch) {
            $targetBatches[] = $b;
            $targetIds[] = $b['id'];
        }
    }

    if (empty($targetBatches)) {
        die("Error: Tidak ada batch dalam rentang Batch $startBatch s/d Batch $endBatch untuk WO " . htmlspecialchars($wo));
    }

    // Query data rentang barcode untuk batch-batch terpilih
    $idList = implode(',', $targetIds);
    $numSql = "SELECT batch_id, MIN(nomor) AS no_awal, MAX(nomor) AS no_akhir, COUNT(id) AS qty 
               FROM {$cfg['number_table']} 
               WHERE batch_id IN ($idList) 
               GROUP BY batch_id";
    $numRes = $koneksi->query($numSql);
    $numMap = [];
    if ($numRes) {
        while ($r = $numRes->fetch_assoc()) {
            $numMap[$r['batch_id']] = $r;
        }
    }

    // Query print log jika opsi includePrintInfo diaktifkan
    $logMap = [];
    if ($includePrintInfo) {
        $logSql = "SELECT batch_id, MAX(printed_at) AS printed_at, MAX(username) AS username 
                   FROM {$cfg['log_table']} 
                   WHERE batch_id IN ($idList) 
                   GROUP BY batch_id";
        $logRes = $koneksi->query($logSql);
        if ($logRes) {
            while ($lr = $logRes->fetch_assoc()) {
                $logMap[$lr['batch_id']] = $lr;
            }
        }
    }

    // Gabungkan baris data
    $reportRows = [];
    foreach ($targetBatches as $b) {
        $bid = $b['id'];
        $nData = $numMap[$bid] ?? null;
        $lData = $logMap[$bid] ?? null;

        $noAwal = $nData['no_awal'] ?? '';
        $noAkhir = $nData['no_akhir'] ?? '';
        $qty = (int)($nData['qty'] ?? 0);

        $tanggalVal = '';
        $statusVal = '';
        if ($includePrintInfo && !empty($lData['printed_at'])) {
            $tanggalVal = date('d/m/Y', strtotime($lData['printed_at']));
            $statusVal = 'Printed';
        }

        $reportRows[] = [
            'batch_num' => $b['batch_num'],
            'nama_batch' => $b['nama_batch'],
            'no_awal' => $noAwal,
            'no_akhir' => $noAkhir,
            'qty' => $qty,
            'tanggal' => $tanggalVal,
            'status_print' => $statusVal,
            'qc_check_date' => '',
            'qc_check' => '',
            'mark' => ''
        ];
    }

    // Tentukan teks header kanan: "Batch [start] - [end]" (tanpa Part dan tanpa Tanggal)
    if ($startBatch === $endBatch) {
        $batchHeaderRight = "Batch $startBatch";
    } else {
        $batchHeaderRight = "Batch $startBatch - $endBatch";
    }

    // Pisahkan judul utama dan sub-judul (tipe meter / model / WO)
    $titleLine1 = $cfg['default_title_prefix']; // default: REPORT PRINT METER NUMBER/PCB NUMBER
    $titleLine2 = '';

    if ($customTitle !== '') {
        $cleanCustom = trim($customTitle);
        if (strpos($cleanCustom, "\n") !== false) {
            $parts = explode("\n", $cleanCustom, 2);
            $titleLine1 = trim($parts[0]);
            $titleLine2 = trim($parts[1]);
        } elseif (stripos($cleanCustom, $cfg['default_title_prefix']) === 0) {
            $titleLine1 = $cfg['default_title_prefix'];
            $remainder = trim(substr($cleanCustom, strlen($cfg['default_title_prefix'])));
            $titleLine2 = $remainder;
        } elseif (preg_match('/^(REPORT PRINT [^\r\n]+?)\s+([A-Za-z0-9\-_ ]+)$/i', $cleanCustom, $m)) {
            $titleLine1 = trim($m[1]);
            $titleLine2 = trim($m[2]);
        } else {
            $titleLine1 = $cleanCustom;
            $titleLine2 = '';
        }
    } else {
        if ($cfg['category'] === 'pcb') {
            $types = getCleanTypes($koneksi);
            $meterType = detectMeterTypeFromWo($wo, $types);
            $titleLine2 = $meterType !== '' ? $meterType : $wo;
        } else {
            $titleLine2 = $wo;
        }
    }

    $firstDataRow = ($titleLine2 !== '') ? 4 : 3;
    $lastDataRow = $firstDataRow + count($reportRows) - 1;

    // Nama file unduhan
    $safeWoFile = preg_replace('/[\\/:*?"<>|]/', '_', $wo);
    $filename = "REPORT_PRINT_{$safeWoFile}_{$batchHeaderRight}.xls";

    // Set header HTTP Excel
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Output template HTML-Excel
    ?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name><?= htmlspecialchars($batchHeaderRight) ?></x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
     <x:PageSetup>
      <x:Layout x:Orientation="Landscape"/>
      <x:PageMargins x:Top="0.5" x:Bottom="0.5" x:Left="0.5" x:Right="0.5"/>
     </x:PageSetup>
     <x:Print>
      <x:ValidPrinterInfo/>
      <x:PaperSizeIndex>9</x:PaperSizeIndex>
      <x:Scale>100</x:Scale>
      <x:HorizontalResolution>600</x:HorizontalResolution>
      <x:VerticalResolution>600</x:VerticalResolution>
     </x:Print>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  @page {
    mso-page-orientation: landscape;
    size: landscape;
    margin: 0.5in 0.5in 0.5in 0.5in;
  }
  body, table {
    font-family: Arial, Calibri, sans-serif;
    font-size: 10pt;
    border-collapse: collapse;
  }
  
  /* Judul Baris 1 & 2 Sesuai Gambar */
  .title-line1 {
    font-size: 11pt;
    font-weight: bold;
    text-align: left;
    vertical-align: middle;
    font-family: Arial, Calibri, sans-serif;
  }
  .title-line2 {
    font-size: 11pt;
    font-weight: bold;
    text-align: left;
    vertical-align: middle;
    font-family: Arial, Calibri, sans-serif;
  }
  .title-batch {
    font-size: 11pt;
    font-weight: bold;
    text-align: right;
    vertical-align: middle;
    font-family: Arial, Calibri, sans-serif;
  }

  /* Header Box: Border Tebal (1.5pt solid black) Seluruh Sisi Header Box */
  .th-box {
    border: 1.5pt solid #000000;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
    padding: 6px 4px;
    background-color: #ffffff;
    font-size: 10pt;
    font-family: Arial, Calibri, sans-serif;
  }

  /* Baris Data: Border Tipis (0.5pt solid black) */
  .td-data {
    border-top: 0.5pt solid #000000;
    border-bottom: 0.5pt solid #000000;
    border-left: 0.5pt solid #000000;
    border-right: 0.5pt solid #000000;
    vertical-align: middle;
    padding: 3px 6px;
    font-size: 10pt;
    font-family: Arial, Calibri, sans-serif;
  }
  .td-border-left-thick {
    border-left: 1.5pt solid #000000 !important;
  }
  .td-border-right-thick {
    border-right: 1.5pt solid #000000 !important;
  }

  /* Alignment Kolom */
  .align-right {
    text-align: right !important;
  }
  .align-center {
    text-align: center !important;
  }

  /* Format Data */
  .text-format {
    mso-number-format: "\@";
  }
  .qty-format {
    mso-number-format: "#,##0";
  }

  /* Baris TOTAL */
  .td-total-label {
    border-top: 0.5pt solid #000000;
    border-bottom: 1.5pt solid #000000;
    border-left: 1.5pt solid #000000;
    border-right: 0.5pt solid #000000;
    font-weight: bold;
    text-align: right;
    vertical-align: middle;
    padding: 4px 8px;
    font-size: 10pt;
    font-family: Arial, Calibri, sans-serif;
  }
  .td-total-qty {
    border-top: 0.5pt solid #000000;
    border-bottom: 1.5pt solid #000000;
    border-left: 0.5pt solid #000000;
    border-right: 0.5pt solid #000000;
    font-weight: bold;
    text-align: right;
    vertical-align: middle;
    padding: 4px 6px;
    font-size: 10pt;
    mso-number-format: "#,##0";
    font-family: Arial, Calibri, sans-serif;
  }
  .td-total-empty {
    border-top: 0.5pt solid #000000;
    border-bottom: 1.5pt solid #000000;
    border-left: 0.5pt solid #000000;
    border-right: 0.5pt solid #000000;
    font-family: Arial, Calibri, sans-serif;
  }
  .td-total-empty-last {
    border-top: 0.5pt solid #000000;
    border-bottom: 1.5pt solid #000000;
    border-left: 0.5pt solid #000000;
    border-right: 1.5pt solid #000000;
    font-family: Arial, Calibri, sans-serif;
  }
</style>
</head>
<body>
<table>
  <!-- Baris Judul -->
  <?php if ($titleLine2 !== ''): ?>
  <tr style="height: 24px;">
    <td colspan="9" class="title-line1"><?= htmlspecialchars($titleLine1) ?></td>
  </tr>
  <tr style="height: 24px;">
    <td colspan="5" class="title-line2"><?= htmlspecialchars($titleLine2) ?></td>
    <td colspan="4" class="title-batch"><?= htmlspecialchars($batchHeaderRight) ?></td>
  </tr>
  <?php else: ?>
  <tr style="height: 26px;">
    <td colspan="5" class="title-line1"><?= htmlspecialchars($titleLine1) ?></td>
    <td colspan="4" class="title-batch"><?= htmlspecialchars($batchHeaderRight) ?></td>
  </tr>
  <?php endif; ?>

  <!-- Baris Header Tabel (Border Tebal 1.5pt, Teks Center Bold) -->
  <tr style="height: 26px;">
    <th class="th-box" style="width: 45px;">NO</th>
    <th class="th-box" style="width: 125px;">No Awal</th>
    <th class="th-box" style="width: 125px;">No Akhir</th>
    <th class="th-box" style="width: 65px;">QTY</th>
    <th class="th-box" style="width: 90px;">Tanggal</th>
    <th class="th-box" style="width: 105px;">Status Print</th>
    <th class="th-box" style="width: 115px;">Qc check /Date</th>
    <th class="th-box" style="width: 95px;">Qc Check</th>
    <th class="th-box" style="width: 75px;">Mark</th>
  </tr>

  <!-- Baris Data Batch -->
  <?php
  $totalQty = 0;
  $rowNum = 1;
  foreach ($reportRows as $r):
      $qty = (int)($r['qty'] ?? 0);
      $totalQty += $qty;
  ?>
  <tr style="height: 22px;">
    <td class="td-data td-border-left-thick align-right text-format"><?= $rowNum++ ?></td>
    <td class="td-data align-right text-format"><?= htmlspecialchars((string)($r['no_awal'] ?? '')) ?></td>
    <td class="td-data align-right text-format"><?= htmlspecialchars((string)($r['no_akhir'] ?? '')) ?></td>
    <td class="td-data align-right qty-format"><?= $qty ?></td>
    <td class="td-data align-center text-format"><?= htmlspecialchars((string)($r['tanggal'] ?? '')) ?></td>
    <td class="td-data align-center text-format"><?= htmlspecialchars((string)($r['status_print'] ?? '')) ?></td>
    <td class="td-data align-center text-format"><?= htmlspecialchars((string)($r['qc_check_date'] ?? '')) ?></td>
    <td class="td-data align-center text-format"><?= htmlspecialchars((string)($r['qc_check'] ?? '')) ?></td>
    <td class="td-data td-border-right-thick align-center text-format"><?= htmlspecialchars((string)($r['mark'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>

  <!-- Baris Summary: TOTAL -->
  <tr style="height: 24px;">
    <td colspan="3" class="td-total-label">TOTAL</td>
    <td class="td-total-qty" x:num="<?= $totalQty ?>" x:fmla="=SUM(D<?= $firstDataRow ?>:D<?= $lastDataRow ?>)"><?= number_format($totalQty, 0, ',', '.') ?></td>
    <td class="td-total-empty"></td>
    <td class="td-total-empty"></td>
    <td class="td-total-empty"></td>
    <td class="td-total-empty"></td>
    <td class="td-total-empty-last"></td>
  </tr>
</table>
</body>
</html>
<?php
    exit;
}

// Default response jika tanpa action
echo json_encode(['success' => false, 'error' => 'Aksi tidak valid']);
exit;
