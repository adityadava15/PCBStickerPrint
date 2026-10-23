<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: Login1.php");
    exit;
}

$allowedMenus = ['pcb', 'ev_charger', 'ev_charger_sn', 'nameplate', 'custom'];
$requestedMenu = isset($_REQUEST['menu']) ? (string) $_REQUEST['menu'] : '';
$menu = in_array($requestedMenu, $allowedMenus, true) ? $requestedMenu : 'pcb';
$isEvChargerMenu = $menu === 'ev_charger';
$isEvChargerSnMenu = $menu === 'ev_charger_sn';

// ====== KONEKSI ======
$koneksi = new mysqli("192.168.88.254:3307", "root", "", "test");
if ($koneksi->connect_errno) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// ====== AJAX SAVE EV SN LOG HANDLER ======
if (isset($_POST['action']) && $_POST['action'] === 'save_ev_sn_log') {
    header('Content-Type: application/json');
    $totalPrinted = (int)($_POST['total_printed'] ?? 0);
    $startSn = $koneksi->real_escape_string(trim((string)($_POST['start_sn'] ?? '')));
    $endSn = $koneksi->real_escape_string(trim((string)($_POST['end_sn'] ?? '')));
    $prodDate = $koneksi->real_escape_string(trim((string)($_POST['prod_date'] ?? '')));
    $username = isset($_SESSION['username']) ? $koneksi->real_escape_string($_SESSION['username']) : 'user';

    if ($totalPrinted > 0) {
        $koneksi->query("INSERT INTO ev_charger_sn_log_print (total_printed, start_sn, end_sn, prod_date, printed_at, username)
                         VALUES ($totalPrinted, '$startSn', '$endSn', '$prodDate', NOW(), '$username')");
    }
    echo json_encode(['success' => true]);
    exit;
}

$pcbBatchTable = $isEvChargerMenu ? 'ev_charger_batch' : 'batch';
$pcbNumberTable = $isEvChargerMenu ? 'ev_charger_nomor_barcode' : 'nomor_barcode';

// ====== TABLE KHUSUS NAMEPLATE ======
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS ev_charger_batch (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_batch VARCHAR(255) NOT NULL,
        wo VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS ev_charger_nomor_barcode (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nomor VARCHAR(100) NOT NULL,
        wo VARCHAR(100) NOT NULL,
        batch_id INT DEFAULT NULL,
        is_printed TINYINT(1) NOT NULL DEFAULT 0,
        printed_at TIMESTAMP NULL DEFAULT NULL,
        printed_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wo (wo),
        INDEX idx_batch_id (batch_id),
        INDEX idx_batch_printed (batch_id, is_printed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS nameplate_batch (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_batch VARCHAR(255) NOT NULL,
        wo VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS nameplate_nomor_barcode (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nomor VARCHAR(100) NOT NULL,
        wo VARCHAR(100) NOT NULL,
        batch_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wo (wo),
        INDEX idx_batch_id (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS nameplate_log_print (
        id INT AUTO_INCREMENT PRIMARY KEY,
        start_number VARCHAR(100) NOT NULL,
        end_number VARCHAR(100) NOT NULL,
        batch_id INT NOT NULL,
        nama_batch VARCHAR(255) NOT NULL,
        wo VARCHAR(100) NOT NULL,
        printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        username VARCHAR(100) DEFAULT NULL,
        UNIQUE KEY uniq_batch_id (batch_id),
        INDEX idx_wo (wo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS ev_charger_log_print (
        id INT AUTO_INCREMENT PRIMARY KEY,
        start_number VARCHAR(100) NOT NULL,
        end_number VARCHAR(100) NOT NULL,
        batch_id INT NOT NULL,
        nama_batch VARCHAR(255) NOT NULL,
        wo VARCHAR(100) NOT NULL,
        printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        username VARCHAR(100) DEFAULT NULL,
        INDEX idx_batch_id (batch_id),
        INDEX idx_wo (wo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS ev_charger_sn_log_print (
        id INT AUTO_INCREMENT PRIMARY KEY,
        total_printed INT NOT NULL,
        start_sn VARCHAR(100) DEFAULT NULL,
        end_sn VARCHAR(100) DEFAULT NULL,
        prod_date VARCHAR(100) DEFAULT NULL,
        printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        username VARCHAR(100) DEFAULT NULL,
        INDEX idx_printed_at (printed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS ev_charger_type_option (
        id INT AUTO_INCREMENT PRIMARY KEY,
        option_name VARCHAR(190) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_option_name (option_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// ====== UPGRADE SCHEMA ======
$checkCols = ['log_print', 'nameplate_log_print', 'ev_charger_log_print'];
foreach ($checkCols as $tbl) {
    $tblExists = $koneksi->query("SHOW TABLES LIKE '$tbl'");
    if ($tblExists && $tblExists->num_rows > 0) {
        $colExists = $koneksi->query("SHOW COLUMNS FROM `$tbl` LIKE 'username'");
        if ($colExists && $colExists->num_rows === 0) {
            $koneksi->query("ALTER TABLE `$tbl` ADD COLUMN username VARCHAR(100) DEFAULT NULL");
        }
    }
}

// Upgrade schema for EV Charger Custom Range Print
$evPrintColExists = $koneksi->query("SHOW COLUMNS FROM `ev_charger_nomor_barcode` LIKE 'is_printed'");
if ($evPrintColExists && $evPrintColExists->num_rows === 0) {
    $koneksi->query("ALTER TABLE `ev_charger_nomor_barcode` ADD COLUMN is_printed TINYINT(1) NOT NULL DEFAULT 0");
    $koneksi->query("ALTER TABLE `ev_charger_nomor_barcode` ADD COLUMN printed_at TIMESTAMP NULL DEFAULT NULL");
    $koneksi->query("ALTER TABLE `ev_charger_nomor_barcode` ADD COLUMN printed_by VARCHAR(100) DEFAULT NULL");
    $koneksi->query("ALTER TABLE `ev_charger_nomor_barcode` ADD INDEX idx_batch_printed (batch_id, is_printed)");
}
$evUniqIdxRes = $koneksi->query("SHOW INDEX FROM `ev_charger_log_print` WHERE Key_name = 'uniq_batch_id' AND Non_unique = 0");
if ($evUniqIdxRes && $evUniqIdxRes->num_rows > 0) {
    $koneksi->query("ALTER TABLE `ev_charger_log_print` DROP INDEX `uniq_batch_id`");
    $koneksi->query("ALTER TABLE `ev_charger_log_print` ADD INDEX idx_batch_id (batch_id)");
}

function loadEvChargerTypeOptions(mysqli $koneksi, $filePath)
{
    $optionsMap = [];

    if (is_file($filePath)) {
        $fileJson = file_get_contents($filePath);
        $fileOptions = json_decode((string) $fileJson, true);
        if (is_array($fileOptions)) {
            foreach ($fileOptions as $fileOption) {
                $optionName = trim((string) $fileOption);
                if ($optionName !== '') {
                    $optionsMap[$optionName] = true;
                }
            }
        }
    }

    $evTypeOptionsRes = $koneksi->query("SELECT option_name FROM ev_charger_type_option ORDER BY option_name ASC");
    while ($evTypeOptionsRes && $evTypeOptionRow = $evTypeOptionsRes->fetch_assoc()) {
        $optionName = trim((string) $evTypeOptionRow['option_name']);
        if ($optionName !== '') {
            $optionsMap[$optionName] = true;
        }
    }

    $options = array_keys($optionsMap);
    natcasesort($options);
    return array_values($options);
}

$evTypeOptionsFile = __DIR__ . DIRECTORY_SEPARATOR . 'ev_charger_type_options.json';
$evTypeOptions = loadEvChargerTypeOptions($koneksi, $evTypeOptionsFile);

function loadTypeMeterOptions(mysqli $koneksi, $filePath)
{
    $defaultOptions = [
        "HXE310",
        "HXE118-KP PM",
        "HXE12FR",
        "HXF300",
        "HXE310-KP",
        "HXM300",
        "HXE110 AFS",
        "HXE128-KP"
    ];

    $optionsMap = [];

    if (is_file($filePath)) {
        $fileJson = file_get_contents($filePath);
        $fileOptions = json_decode((string) $fileJson, true);
        if (is_array($fileOptions)) {
            foreach ($fileOptions as $fileOption) {
                $optionName = trim((string) $fileOption);
                if ($optionName !== '') {
                    $optionsMap[$optionName] = true;
                }
            }
        }
    }

    $res = $koneksi->query("SELECT option_name FROM type_meter_option ORDER BY option_name ASC");
    while ($res && $row = $res->fetch_assoc()) {
        $optionName = trim((string) $row['option_name']);
        if ($optionName !== '') {
            $optionsMap[$optionName] = true;
        }
    }

    if (empty($optionsMap)) {
        foreach ($defaultOptions as $def) {
            $optionsMap[$def] = true;
        }
    }

    $options = array_keys($optionsMap);
    natcasesort($options);
    return array_values($options);
}

$typeMeterOptionsFile = __DIR__ . DIRECTORY_SEPARATOR . 'type_meter_options.json';
$typeMeterOptions = loadTypeMeterOptions($koneksi, $typeMeterOptionsFile);

// Ambil daftar WO unik dari batch aktif
$woList = mysqli_query($koneksi, "SELECT DISTINCT wo FROM {$pcbBatchTable} ORDER BY wo ASC");
$selectedWO = isset($_REQUEST['wo']) ? (string) $_REQUEST['wo'] : '';
$selectedWONameplate = isset($_GET['wo_np']) ? $_GET['wo_np'] : '';
$selectedBatchIdNameplate = isset($_GET['batch_id_np']) && $_GET['batch_id_np'] !== '' ? (int) $_GET['batch_id_np'] : null;
$productionDateNameplate = isset($_REQUEST['prod_date_np']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_REQUEST['prod_date_np'])
    ? $_REQUEST['prod_date_np']
    : date('Y-m-d');
$nameplatePrintScript = '';

function normalizeNameplateNumber($rawNumber)
{
    return preg_replace('/\D+/', '', (string) $rawNumber);
}

function formatNameplateNumber($rawNumber)
{
    $digits = normalizeNameplateNumber($rawNumber);
    if ($digits === '') {
        return trim((string) $rawNumber);
    }

    $groups = [2, 4, 4];
    $parts = [];
    $cursor = 0;
    foreach ($groups as $groupLen) {
        if (strlen($digits) <= $cursor) {
            break;
        }
        $parts[] = substr($digits, $cursor, $groupLen);
        $cursor += $groupLen;
    }

    if (strlen($digits) > $cursor) {
        $parts[] = substr($digits, $cursor);
    }

    return implode(' ', $parts);
}

function buildNameplateSerialSpans($rawNumber)
{
    $formatted = formatNameplateNumber($rawNumber);
    $groups = preg_split('/\s+/', trim($formatted));
    $groups = array_values(array_filter($groups, static function ($g) {
        return $g !== '';
    }));

    if (empty($groups)) {
        return '<span></span>';
    }

    $html = '';
    foreach ($groups as $group) {
        $html .= '<span>' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return $html;
}

function buildHdTextPlaceholder($className, $text, $align = 'left')
{
    return '<div class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . ' hd-text" data-align="' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . '" data-text="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '"></div>';
}

function buildEvPcbCodeLine($numberText, $batchText = 'Batch', $isPreview = false)
{
    $wrapperClass = $isPreview ? 'ev-pcb-preview-code-line' : 'ev-pcb-code-line';
    $numberClass = $isPreview ? 'ev-pcb-preview-code-text' : 'ev-pcb-code-text';
    $batchClass = $isPreview ? 'ev-pcb-preview-batch-text' : 'ev-pcb-batch-text';

    return '<div class="' . $wrapperClass . '">' .
        buildHdTextPlaceholder($numberClass, $numberText, 'left') .
        buildHdTextPlaceholder($batchClass, $batchText, 'right') .
        '</div>';
}

function buildLocalAssetBaseUrl()
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== ''
        ? $_SERVER['HTTP_HOST']
        : 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $scriptDir = ($scriptDir === '/' || $scriptDir === '.') ? '' : trim($scriptDir, '/');

    return ($isHttps ? 'https' : 'http') . '://' . $host . ($scriptDir !== '' ? '/' . $scriptDir : '') . '/';
}

$assetBaseUrl = buildLocalAssetBaseUrl();

$printScript = '';
if (isset($_POST['print']) || isset($_POST['print_ev_charger'])) {
    $isEvChargerPrint = isset($_POST['print_ev_charger']);
    $batch_id = (int) $_POST['batch_id'];
    $wo = $koneksi->real_escape_string($_POST['wo']);
    $start = $koneksi->real_escape_string($_POST['start']);
    $end = $koneksi->real_escape_string($_POST['end']);
    $recordBatch = isset($_POST['record_batch']) && $_POST['record_batch'] === '1';

    $batchRes = $koneksi->query("SELECT nama_batch FROM {$pcbBatchTable} WHERE id=$batch_id");
    $batchRow = $batchRes->fetch_assoc();
    $nama_batch = $batchRow['nama_batch'];
    preg_match('/Batch (\d+)/', $nama_batch, $matches);
    $batch_num = isset($matches[1]) ? $matches[1] : '';

    $dateInput = isset($_POST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_POST['date'])
        ? (string) $_POST['date']
        : date('Y-m-d');
    $printedRowIds = [];
    if ($isEvChargerPrint) {
        $evTypeName = isset($_POST['ev_type_name']) ? $koneksi->real_escape_string($_POST['ev_type_name']) : '';
        $date = date('d-m-y', strtotime($dateInput));
        $customText = trim(preg_replace('/\s+/', ' ', $evTypeName . ' ' . $date));

        $evStart = trim((string) ($_POST['ev_start_number'] ?? ''));
        $evEnd = trim((string) ($_POST['ev_end_number'] ?? ''));

        // Load all numbers for this batch in order
        $res = $koneksi->query("SELECT id, nomor, is_printed FROM ev_charger_nomor_barcode WHERE batch_id=$batch_id ORDER BY id ASC");
        $allBatchRows = [];
        $unprintedRows = [];
        while ($r = $res->fetch_assoc()) {
            $allBatchRows[] = $r;
            if ((int)$r['is_printed'] === 0) {
                $unprintedRows[] = $r;
            }
        }

        if ($evStart === '' && !empty($unprintedRows)) {
            $evStart = $unprintedRows[0]['nomor'];
        }
        if ($evEnd === '' && !empty($unprintedRows)) {
            $evEnd = end($unprintedRows)['nomor'];
        }

        $startIndex = -1;
        $endIndex = -1;
        foreach ($allBatchRows as $idx => $row) {
            if ($row['nomor'] === $evStart && $startIndex === -1) {
                $startIndex = $idx;
            }
            if ($row['nomor'] === $evEnd) {
                $endIndex = $idx;
            }
        }

        $printError = null;
        if ($startIndex === -1 || $endIndex === -1) {
            $printError = "Nomor awal atau nomor akhir tidak ditemukan dalam batch ini.";
        } elseif ($startIndex > $endIndex) {
            $printError = "Nomor akhir harus lebih besar atau sama dengan nomor awal.";
        } else {
            $selectedRows = array_slice($allBatchRows, $startIndex, ($endIndex - $startIndex + 1));
            $alreadyPrinted = [];
            foreach ($selectedRows as $row) {
                if ((int)$row['is_printed'] === 1) {
                    $alreadyPrinted[] = $row['nomor'];
                }
            }
            if (!empty($alreadyPrinted)) {
                $printError = "Nomor " . $alreadyPrinted[0] . " sudah pernah di-print dan tidak dapat di-print ulang!";
            }
        }

        if ($printError) {
            echo "<script>alert(" . json_encode($printError) . "); window.history.back();</script>";
            exit;
        }

        $numbers = array_column($selectedRows, 'nomor');
        $printedRowIds = array_column($selectedRows, 'id');
    } else {
        $type_meter = isset($_POST['type_meter']) ? $koneksi->real_escape_string($_POST['type_meter']) : '';
        $date = date('d-m-Y', strtotime($dateInput));
        $customText = $type_meter . ' ' . $date . ' Batch ' . $batch_num;

        $res = $koneksi->query("SELECT nomor FROM nomor_barcode WHERE batch_id=$batch_id ORDER BY id ASC");
        $numbers = [];
        while ($r = $res->fetch_assoc()) {
            $numbers[] = $r['nomor'];
        }
    }

    if (!empty($numbers)) {
        $chunks = array_chunk($numbers, 9);
        $totalChunkCount = count($chunks);

        $printedNumbers = $numbers;

        if ($recordBatch && !empty($printedNumbers)) {
            $startPrinted = $koneksi->real_escape_string($printedNumbers[0]);
            $endPrinted = $koneksi->real_escape_string($printedNumbers[count($printedNumbers) - 1]);
            $username = isset($_SESSION['username']) ? $koneksi->real_escape_string($_SESSION['username']) : 'system';
            if ($isEvChargerPrint) {
                $safeWo = $koneksi->real_escape_string($wo);
                $sql = "INSERT INTO ev_charger_log_print (start_number, end_number, batch_id, nama_batch, wo, printed_at, username)
                        VALUES ('$startPrinted','$endPrinted','$batch_id','$nama_batch','$safeWo',NOW(),'$username')";
                $koneksi->query($sql);

                if (!empty($printedRowIds)) {
                    $inIds = implode(',', array_map('intval', $printedRowIds));
                    $koneksi->query("UPDATE ev_charger_nomor_barcode SET is_printed=1, printed_at=NOW(), printed_by='$username' WHERE id IN ($inIds)");
                }
            } else {
                $sql = "INSERT INTO log_print (start_number, end_number, batch_id, nama_batch, printed_at, username)
                        VALUES ('$startPrinted','$endPrinted','$batch_id','$nama_batch',NOW(),'$username')";
                $koneksi->query($sql);
            }
        }
        ob_start();
?>
<script>
var w = window.open('', '_blank');
w.document.write('<html><head><title>Cetak Barcode</title>');
w.document.write('<style>');
w.document.write(`
  @page {
    size: 79.95mm 34.03mm;
    margin: 0;
  }
  body {
    margin: 0;
    padding: 0;
  }
  .page {
    width: 79.95mm;
    height: 34.03mm;
    margin-top: -10px;
    page-break-after: always;
    position: relative;
  }
  .page table {
    width: 100%;
    height: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0 0.5mm;
    margin-top: 0;
  }
  .label {
    text-align: center;
    vertical-align: top;
    font-size: 8pt;
    height: 100%;
    padding-top: 0.2mm;
    transform: translateY(1.2mm);
  }
  .page table tr:first-child .label {
    transform: translateY(2.1mm);
  }
  .label svg {
    width: 23.27mm;
    height: 6.76mm;
    margin-bottom: 0px;
    shape-rendering: crispEdges;
    overflow: visible;
  }
	  .pcb-code-text {
	    width: 100%;
	    height: 9px;
    margin-top: -3.4px;
    font-family: Calibri, Arial, sans-serif;
    font-size: 5.6pt;
    font-weight: 400;
	    letter-spacing: 0.01em;
	    display: block;
	  }
	  .ev-pcb-code-line {
	    display: flex;
	    align-items: flex-end;
	    justify-content: space-between;
	    width: 100%;
	    height: 9px;
	    margin-top: -3.4px;
	    gap: 1mm;
	  }
		  .ev-pcb-code-text {
		    flex: 1 1 auto;
		    min-width: 0;
		    height: 100%;
		    font-family: Calibri, Arial, sans-serif;
		    font-size: 5.6pt;
		    font-weight: 480;
		    letter-spacing: 0.01em;
		    display: block;
		    transform: translateX(1.7mm);
		  }
		  .ev-pcb-batch-text {
		    flex: 0 0 6mm;
		    height: 100%;
		    font-family: Calibri, Arial, sans-serif;
		    font-size: 5.1pt;
		    font-weight: 480;
		    letter-spacing: 0;
		    display: block;
		    transform: translateX(-2.1mm);
		  }
	  .pcb-custom-text {
	    width: 100%;
	    height: 7px;
	    margin-top: -0.4px;
	    font-family: Calibri, Arial, sans-serif;
	    font-size: 6.8px;
	    font-weight: 400;
	    letter-spacing: 0.01em;
	    display: block;
	  }
		  .ev-pcb-custom-text {
		    width: 100%;
		    height: 6.5px;
		    margin-top: -0.4px;
		    font-family: Calibri, Arial, sans-serif;
		    font-size: 6.7px;
		    font-weight: 480;
		    letter-spacing: 0;
		    display: block;
		  }
	  .pcb-code-text svg,
	  .ev-pcb-code-text svg,
	  .ev-pcb-batch-text svg,
	  .pcb-custom-text svg,
	  .ev-pcb-custom-text svg {
	    width: 100%;
	    height: 100%;
	    display: block;
	    overflow: visible;
	  }
  .page-number {
    position: absolute;
    left: 81.22mm;
    top: 2.8cm;
					    font-family: Arial, sans-serif;
    font-size: 8pt;
    color: #333;
    white-space: nowrap;
  }
  .arrow {
    color: #0b1f3a;
    font-weight: bold;
  }
  @media print {
    .page-number {
      display: none;
    }
  }
  td:empty {
    height: 1.133cm;
  }
`);
w.document.write('</style></head><body onload="window.print();">');

<?php foreach ($chunks as $pageIndex => $chunk): ?>
  w.document.write('<div class="page"><table>');
  <?php for ($row = 0; $row < 3; $row++): ?>
    w.document.write('<tr>');
    <?php for ($col = 0; $col < 3; $col++): ?>
      <?php $idx = $row * 3 + $col; ?>
      <?php if (isset($chunk[$idx])): $num = $chunk[$idx]; $id = "code{$pageIndex}_{$idx}"; ?>
        w.document.write('<td class="label">');
        w.document.write('<svg id="<?= $id ?>"></svg>');
	        w.document.write('<?= addslashes($isEvChargerPrint ? buildEvPcbCodeLine($num, 'Batch ' . $batch_num) : buildHdTextPlaceholder('pcb-code-text', $num, 'center')) ?>');
	        w.document.write('<?= addslashes(buildHdTextPlaceholder($isEvChargerPrint ? 'ev-pcb-custom-text' : 'pcb-custom-text', $customText, 'center')) ?>');
        w.document.write('</td>');
      <?php else: ?>
        w.document.write('<td></td>');
      <?php endif; ?>
    <?php endfor; ?>
    w.document.write('</tr>');
  <?php endfor; ?>
  w.document.write('</table>');
  w.document.write('<div class="page-number"><span class="arrow">&larr;</span> Page ke <?= $pageIndex + 1 ?></div>');
  w.document.write('</div>');
<?php endforeach; ?>

w.document.write('<script src="JS2/JsBarcode.all.min.js"><\/script>');
w.document.write('<script src="JS2/text-hd-offline.js"><\/script>');
w.document.write('<script>');
<?php
        foreach ($chunks as $pageIndex => $chunk) {
            foreach ($chunk as $i => $num) {
                $id = "code{$pageIndex}_{$i}";
                echo "w.document.write('JsBarcode(\"#{$id}\",\"{$num}\",{format:\"CODE128\",displayValue:false,fontSize:11,height:52,width:3});');\n";
            }
        }
?>
w.document.write('Array.prototype.forEach.call(document.querySelectorAll(\"svg[id^=\\\"code\\\"]\"), function(svg) { svg.setAttribute(\"shape-rendering\", \"crispEdges\"); svg.setAttribute(\"vector-effect\", \"non-scaling-stroke\"); });');
w.document.write('if (window.OfflineTextRenderer && typeof window.OfflineTextRenderer.renderAll === "function") { window.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]"); } else if (window.opener && window.opener.OfflineTextRenderer && typeof window.opener.OfflineTextRenderer.renderAll === "function") { window.opener.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]"); }');
w.document.write('<\/script>');
w.document.write('</body></html>');
w.document.close();
</script>
<?php
        $printScript = ob_get_clean();
    }

}

if (isset($_POST['print_nameplate'])) {
    $batchIdNameplatePrint = (int) $_POST['batch_id_np'];
    $recordNameplate = isset($_POST['record_nameplate']) && $_POST['record_nameplate'] === '1';
    $productionDateInput = isset($_POST['prod_date_np']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_POST['prod_date_np'])
        ? $_POST['prod_date_np']
        : date('Y-m-d');
    $productionLabel = date('m-y', strtotime($productionDateInput));
    $productionYear2 = date('y', strtotime($productionDateInput));
    $batchMetaRes = $koneksi->query("SELECT nama_batch, wo FROM nameplate_batch WHERE id=$batchIdNameplatePrint LIMIT 1");
    $batchMeta = $batchMetaRes ? $batchMetaRes->fetch_assoc() : null;
    $nameplateBatchName = $batchMeta ? $batchMeta['nama_batch'] : ('Batch ID ' . $batchIdNameplatePrint);
    $nameplateBatchWo = $batchMeta ? $batchMeta['wo'] : '';

    $nameplateRowsRes = $koneksi->query("SELECT nomor FROM nameplate_nomor_barcode WHERE batch_id=$batchIdNameplatePrint ORDER BY id ASC");
    $nameplateNumbersToPrint = [];
    while ($nameplateRowsRes && $nameplateRow = $nameplateRowsRes->fetch_assoc()) {
        $rawNumber = normalizeNameplateNumber($nameplateRow['nomor']);
        if ($rawNumber !== '') {
            $nameplateNumbersToPrint[] = $rawNumber;
        }
    }

    if ($recordNameplate && !empty($nameplateNumbersToPrint)) {
        $startNp = $koneksi->real_escape_string($nameplateNumbersToPrint[0]);
        $endNp = $koneksi->real_escape_string($nameplateNumbersToPrint[count($nameplateNumbersToPrint) - 1]);
        $safeNameplateBatch = $koneksi->real_escape_string($nameplateBatchName);
        $safeNameplateWo = $koneksi->real_escape_string($nameplateBatchWo);
        $username = isset($_SESSION['username']) ? $koneksi->real_escape_string($_SESSION['username']) : 'system';
        $sqlNp = "INSERT IGNORE INTO nameplate_log_print (start_number, end_number, batch_id, nama_batch, wo, printed_at, username)
                  VALUES ('$startNp','$endNp','$batchIdNameplatePrint','$safeNameplateBatch','$safeNameplateWo',NOW(),'$username')";
        $koneksi->query($sqlNp);
    }

    if (!empty($nameplateNumbersToPrint)) {
        $nameplatePages = array_chunk($nameplateNumbersToPrint, 2);
        ob_start();
?>
<script>
var npWin = window.open('', '_blank');
var npAssetBase = <?= json_encode($assetBaseUrl) ?>;
npWin.document.write('<html><head><title>Print Nameplate Barcode</title>');
npWin.document.write('<base href="' + npAssetBase + '">');
npWin.document.write('<style>');
		npWin.document.write(`
				  @page {
				    size: 97.97mm 16.02mm;
			    margin: 0 !important;
			  }
	  * {
	    box-sizing: border-box;
	    -webkit-print-color-adjust: exact;
	    print-color-adjust: exact;
	  }
		  html, body {
		    margin: 0;
		    padding: 0;
		  }
	  body {
						      font-family: Arial, sans-serif;
	    color: #000;
	    background: #55657c;
	    display: block;
	  }
			  .print-page {
			    width: 97.97mm;
			    height: 16.02mm;
			    margin: 0;
		    padding: 0;
		    overflow: hidden;
		    position: relative;
		  }
		  .print-page + .print-page {
		    margin-top: 0;
		  }
		  .page-cut {
		    display: none;
		    width: 0;
		    height: 0;
		    margin: 0;
		    padding: 0;
		  }
		  .page-number {
		    position: absolute;
		    left: calc(100% + 6px);
		    top: 50%;
		    transform: translateY(-50%);
				      font-family: Arial, sans-serif;
		    font-size: 8pt;
		    color: #333;
		    white-space: nowrap;
		    z-index: 5;
		  }
		  .page-number .arrow {
		    color: #0b1f3a;
		    font-weight: bold;
		  }
			  .sheet {
			    width: 97.97mm;
			    margin: 0;
		    padding: 0 2mm;
		    box-sizing: border-box;
		    display: block;
			    height: 16.02mm;
			    min-height: 16.02mm;
		    background: #fff;
		    overflow: hidden;
		    white-space: nowrap;
		    font-size: 0;
		  }
		  .sheet + .sheet {
		    margin-top: 0;
		  }
				  .sticker {
				    display: inline-block;
				    vertical-align: top;
				    width: 45.05mm;
				    height: 13.01mm;
			    border: 0;
			    padding: 0.3mm 0.6mm 0;
			    overflow: hidden;
			    font-size: 12px;
		  }
		  .sheet .sticker:first-child {
		    transform: translate(0mm, 0.3mm);
		  }
		  .sheet .sticker + .sticker {
		    margin-left: 4mm;
		    transform: translate(1mm, 0.3mm);
		  }
				  .sticker-layout {
				    width: 100%;
				    height: 100%;
				    display: table;
				    table-layout: fixed;
				    border-collapse: collapse;
				    transform: translateY(-0.8mm);
			  }
				  .barcode-wrap {
				    display: table-cell;
				    vertical-align: top;
			    min-width: 0;
			    width: 100%;
          transform: translate(1mm, -0.3mm);
			  }
				  .prod-line {
				    width: 100%;
				    height: 2.5mm;
				    font-size: 5.5pt;
				    font-family: "Century Gothic", Arial, sans-serif;
			    font-weight: 420;
			    letter-spacing: 0.01em;
			    line-height: 1;
			    margin: 0 0 0.8mm 0;
			    white-space: nowrap;
				    transform: translate(-0.28mm, 2.2mm);
		  }
			  .prod-line svg,
			  .qr-label svg {
			    display: block;
			    width: 100%;
			    height: 100%;
			    overflow: visible;
			  }
				  .barcode-wrap > svg {
				    width: 30.66mm;
				    max-width: 30.66mm;
				    height: 5.26mm;
				    display: block;
				    margin: 1.4mm 0 0 0;
				    shape-rendering: crispEdges;
				    overflow: visible;
			  }
					  .serial {
					    margin-top: 1.1mm;
					    font-size: 9.4pt;
					    width: 30.66mm;
					    max-width: 30.66mm;
					    display: flex;
				    justify-content: center;
				    gap: 1.1mm;
				    align-items: baseline;
						    font-family: "Century Gothic", Arial, sans-serif;
					    font-weight: 600;
				    letter-spacing: 0;
				    line-height: 1;
				    transform: translateY(-0.9mm);
				  }
		  .serial span {
		    white-space: nowrap;
		  }
					  .qr-wrap {
					    display: table-cell;
					    vertical-align: top;
					    width: 12.64mm;
					    text-align: right;
					    transform: translateX(-2.5mm);
				  }
						  .qr-label {
						    display: block;
						    position: static;
						    width: 13.51mm;
						    height: 6.8mm;
					    font-size: 18.3pt;
					    font-family: Calibri, Arial, sans-serif;
				    font-weight: 440;
				    letter-spacing: 0.01em;
				    line-height: 1;
				    white-space: nowrap;
				    text-align: right;
				    pointer-events: none;
				    margin: 0 0 0.4mm auto;
						    transform: translate(-0.9mm, -0.4mm);
				  }
							  .qr {
							    width: 8.51mm;
							    height: 8.51mm;
					    display: flex;
				    align-items: center;
				    justify-content: center;
				    background: #fff;
					    padding: 0.1mm;
						    overflow: hidden;
						    margin-top: 0;
						    margin-left: auto;
						    transform: translateY(-3.2mm);
						  }
					  .qr svg {
					    width: 100%;
				    height: 100%;
				    display: block;
				    background: #fff;
				  }
	  .page-break {
	    display: none;
	  }
		  @media screen {
		    html {
		      background: #55657c;
		    }
		    body {
		      display: block;
		      width: fit-content;
			      min-width: calc(97.97mm + 96px);
		      padding: 8px 72px 8px 8px;
		    }
		    .print-page + .print-page {
		      margin-top: 6px;
		    }
		    .page-cut {
		      display: none !important;
		    }
		    .print-page {
		      overflow: visible;
		    }
		    .sheet {
		      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.2);
		    }
		  }
		  @media print {
		    html,
		    body {
		      width: 97.97mm !important;
		      min-width: 97.97mm !important;
		      max-width: 97.97mm !important;
		      background: #fff;
		    }
		    body {
		      display: block !important;
		      padding: 0 !important;
		      line-height: 1 !important;
		    }
		    .print-page {
		      width: 97.97mm !important;
		      height: 16.02mm !important;
		      margin: 0 !important;
		      break-inside: avoid;
		      page-break-inside: avoid;
		    }
		    .page-cut {
		      display: block !important;
		      width: 0 !important;
		      height: 0 !important;
		      margin: 0 !important;
		      padding: 0 !important;
		      break-before: page;
		      page-break-before: always;
		    }
		    .sheet {
		      height: 16.02mm;
		      min-height: 16.02mm;
		      margin: 0 !important;
		      box-shadow: none;
		      break-after: auto;
		      page-break-after: auto;
		    }
		    .sticker {
		      height: 13mm;
		    }
		    .print-page + .print-page {
		      margin-top: 0 !important;
		    }
		    .page-number {
		      display: none !important;
		    }
		  }
	`);
npWin.document.write('</style></head><body onload="setTimeout(function(){window.print();}, 300);">');
<?php foreach ($nameplatePages as $pageIndex => $pageNumbers): ?>
npWin.document.write('<div class="print-page"><div class="sheet">');
<?php
for ($idx = 0; $idx < 2; $idx++):
    if (isset($pageNumbers[$idx])):
        $rawNumber = $pageNumbers[$idx];
        $serialHtml = buildNameplateSerialSpans($rawNumber);
        $qrPayload = 'PLN021900005020' . $productionYear2 . $rawNumber;
        $svgId = "np_barcode_{$pageIndex}_{$idx}";
        $stickerHtml =
            '<div class="sticker">' .
                '<div class="sticker-layout">' .
                    '<div class="barcode-wrap">' .
                        buildHdTextPlaceholder('prod-line', 'Produksi : ' . $productionLabel) .
                        '<svg id="' . $svgId . '"></svg>' .
                        '<div class="serial">' . $serialHtml . '</div>' .
                    '</div>' .
                    '<div class="qr-wrap">' . buildHdTextPlaceholder('qr-label', 'Garansi 5 Tahun', 'right') . '<div class="qr"><svg class="np-qr" data-qr="' . htmlspecialchars($qrPayload, ENT_QUOTES) . '" aria-label="QR"></svg></div></div>' .
                '</div>' .
            '</div>';
    else:
        $stickerHtml = '<div class="sticker"></div>';
    endif;
?>
			npWin.document.write('<?= addslashes($stickerHtml) ?>');
			<?php endfor; ?>
			npWin.document.write('</div><div class="page-number"><span class="arrow">&larr;</span> Page ke <?= $pageIndex + 1 ?></div></div>');
			<?php if ($pageIndex < count($nameplatePages) - 1): ?>
			npWin.document.write('<div class="page-cut"></div>');
			<?php endif; ?>
			<?php endforeach; ?>
npWin.document.write('<script src="' + npAssetBase + 'JS2/JsBarcode.all.min.js"><\/script>');
npWin.document.write('<script src="' + npAssetBase + 'JS2/qrcode-generator.min.js"><\/script>');
npWin.document.write('<script src="' + npAssetBase + 'JS2/qrcode-offline.js"><\/script>');
npWin.document.write('<script src="' + npAssetBase + 'JS2/text-hd-offline.js"><\/script>');
npWin.document.write('<script>');
npWin.document.write('(function(){');
npWin.document.write('var barcodeLib = window.JsBarcode || (window.opener && window.opener.JsBarcode);');
<?php foreach ($nameplatePages as $pageIndex => $pageNumbers): ?>
<?php foreach ($pageNumbers as $idx => $rawNumber): ?>
npWin.document.write('if (barcodeLib) { barcodeLib("#np_barcode_<?= $pageIndex ?>_<?= $idx ?>","<?= $rawNumber ?>",{format:"CODE128",displayValue:false,height:26,width:1.8,margin:0}); }');
<?php endforeach; ?>
<?php endforeach; ?>
		npWin.document.write('Array.prototype.forEach.call(document.querySelectorAll(\"svg[id^=\\\"np_barcode_\\\"]\"), function(svg) { svg.setAttribute(\"shape-rendering\", \"crispEdges\"); svg.setAttribute(\"vector-effect\", \"non-scaling-stroke\"); });');
		npWin.document.write('if (window.OfflineTextRenderer && typeof window.OfflineTextRenderer.renderAll === "function") { window.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]"); } else if (window.opener && window.opener.OfflineTextRenderer && typeof window.opener.OfflineTextRenderer.renderAll === "function") { window.opener.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]"); }');
		npWin.document.write('if (window.QRCodeRuntime && typeof window.QRCodeRuntime.renderAll === "function") { window.QRCodeRuntime.renderAll(document, ".np-qr[data-qr]"); } else if (window.opener && window.opener.QRCodeRuntime && typeof window.opener.QRCodeRuntime.renderAll === "function") { window.opener.QRCodeRuntime.renderAll(document, ".np-qr[data-qr]"); }');
npWin.document.write('})();');
npWin.document.write('<\/script>');
npWin.document.write('</body></html>');
npWin.document.close();
</script>
<?php
        $nameplatePrintScript = ob_get_clean();
    }
}

// ====== DATA PREVIEW BATCH ======
$selectedBatchId = isset($_REQUEST['batch_id']) && $_REQUEST['batch_id'] !== '' ? (int) $_REQUEST['batch_id'] : null;
$awal9 = $akhir9 = [];
$woBatch = '';
$pcbBatchLabel = 'Batch';
$pcbTotalPages = 0;
$start = '';
$end = '';
$isPrinted = false;
$isFullyPrinted = false;
$allNumbers = [];
$unprintedNumbers = [];
$printedNumbers = [];
$totalBatchCount = 0;
$printedCount = 0;
$unprintedCount = 0;
$defaultEvStart = '';
$defaultEvEnd = '';
$defaultEvQty = 0;
$pcbPrintLogTable = $isEvChargerMenu ? 'ev_charger_log_print' : 'log_print';

if ($selectedBatchId) {
    $batchMetaPcbRes = $koneksi->query("SELECT nama_batch FROM {$pcbBatchTable} WHERE id=$selectedBatchId LIMIT 1");
    $batchMetaPcb = $batchMetaPcbRes ? $batchMetaPcbRes->fetch_assoc() : null;
    if ($batchMetaPcb && preg_match('/Batch (\d+)/', (string) $batchMetaPcb['nama_batch'], $pcbBatchMatches)) {
        $pcbBatchLabel = 'Batch ' . $pcbBatchMatches[1];
    }

    if ($isEvChargerMenu) {
        $res = $koneksi->query("SELECT id, nomor, wo, is_printed, printed_at, printed_by FROM ev_charger_nomor_barcode WHERE batch_id=$selectedBatchId ORDER BY id ASC");
        while ($r = $res->fetch_assoc()) {
            $num = $r['nomor'];
            $woBatch = $r['wo'];
            $allNumbers[] = $num;
            if ((int) $r['is_printed'] === 1) {
                $printedNumbers[] = $num;
            } else {
                $unprintedNumbers[] = $num;
            }
        }

        $totalBatchCount = count($allNumbers);
        $printedCount = count($printedNumbers);
        $unprintedCount = count($unprintedNumbers);
        $isFullyPrinted = ($totalBatchCount > 0 && $unprintedCount === 0);
        $isPrinted = $isFullyPrinted;

        // Preview numbers: use unprinted numbers if available, else all numbers
        $previewSource = !empty($unprintedNumbers) ? $unprintedNumbers : $allNumbers;
        if (!empty($previewSource)) {
            $awal9 = array_slice($previewSource, 0, 9);
            $akhir9 = array_slice($previewSource, -9);
            $start = $previewSource[0];
            $end = end($previewSource);
        }
        $pcbTotalPages = (int) ceil($unprintedCount / 9);

        $defaultEvStart = !empty($unprintedNumbers) ? $unprintedNumbers[0] : (!empty($allNumbers) ? $allNumbers[0] : '');
        $defaultEvEnd = !empty($unprintedNumbers) ? end($unprintedNumbers) : (!empty($allNumbers) ? end($allNumbers) : '');
        $defaultEvQty = $unprintedCount;
    } else {
        $res = $koneksi->query("SELECT nomor, wo FROM {$pcbNumberTable} WHERE batch_id=$selectedBatchId ORDER BY id ASC");
        $numbers = [];
        while ($r = $res->fetch_assoc()) {
            $numbers[] = $r['nomor'];
            $woBatch = $r['wo'];
        }
        if (count($numbers) > 0) {
            $pcbTotalPages = (int) ceil(count($numbers) / 9);
            $awal9 = array_slice($numbers, 0, 9);
            $akhir9 = array_slice($numbers, -9);
            $start = $numbers[0];
            $end = end($numbers);
        }

        $checkPrint = $koneksi->query("SELECT COUNT(*) as cnt FROM {$pcbPrintLogTable} WHERE batch_id=$selectedBatchId");
        $printRow = $checkPrint->fetch_assoc();
        if ($printRow['cnt'] > 0) {
            $isPrinted = true;
        }
    }
}

$nameplateNumbers = [];
$nameplatePreviewNumbers = [];
$isNameplateRecorded = false;
$nameplateYear2 = date('y', strtotime($productionDateNameplate));
$nameplateProductionLabel = date('m-y', strtotime($productionDateNameplate));
if ($selectedBatchIdNameplate) {
    $checkNpRecorded = $koneksi->query("SELECT COUNT(*) AS cnt FROM nameplate_log_print WHERE batch_id=$selectedBatchIdNameplate");
    $npRecordedRow = $checkNpRecorded ? $checkNpRecorded->fetch_assoc() : ['cnt' => 0];
    if ((int) $npRecordedRow['cnt'] > 0) {
        $isNameplateRecorded = true;
    }

    $nameplateRes = $koneksi->query("SELECT nomor FROM nameplate_nomor_barcode WHERE batch_id=$selectedBatchIdNameplate ORDER BY id ASC");
    while ($nameplateRes && $npRow = $nameplateRes->fetch_assoc()) {
        $rawNumber = normalizeNameplateNumber($npRow['nomor']);
        if ($rawNumber !== '') {
            $nameplateNumbers[] = $rawNumber;
        }
    }
    if (!empty($nameplateNumbers)) {
        $nameplatePreviewNumbers = array_merge(
            array_slice($nameplateNumbers, 0, 2),
            array_slice($nameplateNumbers, -2)
        );
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PCB Barcode Print</title>
  <link rel="icon" type="image/png" sizes="32x32" href="IMG/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="IMG/favicon-16x16.png">
  <link rel="shortcut icon" href="IMG/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="IMG/apple-touch-icon.png">
  <script src="JS2/JsBarcode.all.min.js"></script>
  <script src="JS2/qrcode-generator.min.js"></script>
  <script src="JS2/qrcode-offline.js"></script>
  <script src="JS2/text-hd-offline.js"></script>
  <script>
    window.QRCodeRuntime = window.QRCodeRuntime || (function () {
      function resolveMode(text) {
        if (/^\d+$/.test(text)) {
          return "Numeric";
        }

        if (/^[0-9A-Z $%*+\-./:]+$/.test(text)) {
          return "Alphanumeric";
        }

        return "Byte";
      }

      function buildSvgMarkup(payload) {
        if (typeof window.qrcode !== "function") {
          return "";
        }

        var qr = window.qrcode(0, "L");
        qr.addData(payload, resolveMode(payload));
        qr.make();

        return qr.createSvgTag({
          cellSize: 1,
          margin: 1,
          scalable: true
        });
      }

      function render(target, payload) {
        if (!target) {
          return;
        }

        var value = payload || target.getAttribute("data-qr") || "";
        if (!value) {
          return;
        }

        var markup = buildSvgMarkup(value);
        if (!markup) {
          return;
        }

        var doc = target.ownerDocument || document;
        var holder = doc.createElement("div");
        holder.innerHTML = markup;

        var svg = holder.firstElementChild;
        if (!svg) {
          return;
        }

        var className = target.getAttribute("class");
        if (className) {
          svg.setAttribute("class", className);
        }

        var ariaLabel = target.getAttribute("aria-label");
        if (ariaLabel) {
          svg.setAttribute("aria-label", ariaLabel);
        }

        svg.setAttribute("data-qr", value);
        svg.setAttribute("shape-rendering", "crispEdges");

        if (target.parentNode) {
          target.parentNode.replaceChild(svg, target);
        }
      }

      function renderAll(doc, selector) {
        var targetDoc = doc && typeof doc.querySelectorAll === "function" ? doc : document;
        var query = selector || ".np-qr[data-qr]";
        var targets = targetDoc.querySelectorAll(query);

        Array.prototype.forEach.call(targets, function (target) {
          render(target, target.getAttribute("data-qr"));
        });
      }

      return {
        render: render,
        renderAll: renderAll
      };
    })();
  </script>
  <style>
    :root {
      --bg: #091426;
      --panel: #11223b;
      --panel-soft: #172d4c;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --line: #1d3558;
      --line-strong: #2c5082;
      --accent: #38bdf8;
      --accent-text: #091426;
      --sidebar: #091322;
      --button: #163660;
      --button-hover: #1f4a82;
      --content-bg: linear-gradient(180deg, #0b172a 0%, #0d1e36 100%);
      --content-panel: #11223b;
      --content-panel-soft: #162c4c;
      --content-text: #f1f5f9;
      --content-muted: #94a3b8;
      --content-line: #1d385e;
      --content-line-strong: #2a5084;
      --content-button: #0284c7;
      --content-button-hover: #0369a1;
      --content-button-text: #ffffff;
      --card-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
      --table-header-bg: #173863;
      --table-header-text: #f8fafc;
      --table-row-even: #142844;
      --table-row-hover: #1c365c;
      --table-border: #1d385e;
      --badge-admin-bg: rgba(56, 189, 248, 0.15);
      --badge-admin-text: #38bdf8;
      --badge-admin-border: rgba(56, 189, 248, 0.35);
      --badge-user-bg: rgba(148, 163, 184, 0.15);
      --badge-user-text: #cbd5e1;
      --badge-user-border: rgba(148, 163, 184, 0.3);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    body.light-theme {
      --bg: #f8fafc;
      --panel: #ffffff;
      --panel-soft: #f1f5f9;
      --text: #0f172a;
      --muted: #64748b;
      --line: #e2e8f0;
      --line-strong: #cbd5e1;
      --accent: #0284c7;
      --accent-text: #ffffff;
      --sidebar: #ffffff;
      --button: #f1f5f9;
      --button-hover: #e2e8f0;
      --content-bg: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
      --content-panel: #ffffff;
      --content-panel-soft: #f8fafc;
      --content-text: #0f172a;
      --content-muted: #64748b;
      --content-line: #e2e8f0;
      --content-line-strong: #cbd5e1;
      --content-button: #0284c7;
      --content-button-hover: #0369a1;
      --content-button-text: #ffffff;
      --card-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
      --table-header-bg: #0f2d59;
      --table-header-text: #ffffff;
      --table-row-even: #f8fafc;
      --table-row-hover: #f1f5f9;
      --table-border: #e2e8f0;
      --badge-admin-bg: rgba(2, 132, 199, 0.12);
      --badge-admin-text: #0284c7;
      --badge-admin-border: rgba(2, 132, 199, 0.3);
      --badge-user-bg: rgba(100, 116, 139, 0.12);
      --badge-user-text: #475569;
      --badge-user-border: rgba(100, 116, 139, 0.25);
    }

    .theme-toggle {
      position: fixed;
      right: 18px;
      top: 14px;
      z-index: 1200;
      border: 1px solid var(--line-strong);
      background: var(--button);
      color: var(--text);
      padding: 8px 14px;
      font-size: 12.5px;
      font-weight: 600;
      cursor: pointer;
      letter-spacing: 0.02em;
      border-radius: 999px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16);
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .theme-toggle:hover {
      background: var(--button-hover);
      transform: translateY(-1px);
    }

    .app-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 260px 1fr;
      transition: grid-template-columns 0.25s ease;
    }

    .sidebar {
      background: var(--sidebar);
      border-right: 1px solid var(--line);
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
      gap: 18px;
      overflow: hidden;
    }

    .sidebar-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
    }

    .brand {
      border: none;
      padding: 4px 2px;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 74px;
    }

    .brand-mini {
      display: none;
      width: 48px;
      height: 48px;
    }

    .brand-logo {
      width: 100%;
      max-width: 300px;
      height: auto;
      display: block;
      transform: translateY(-3mm);
      filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.28));
    }

    .brand-mini img {
      width: 100%;
      height: 100%;
      display: block;
    }

    .sidebar-toggle {
      width: 42px;
      height: 42px;
      padding: 0;
      border: 1px solid var(--line-strong);
      background: var(--button);
      color: var(--text);
      font-size: 22px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
    }

    .sidebar-toggle:hover {
      background: var(--button-hover);
    }

    nav {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 10px 14px;
      border: none;
      border-radius: 8px;
      text-decoration: none;
      color: #94a3b8;
      font-size: 13.5px;
      font-weight: 500;
      line-height: 1.3;
      background: transparent;
      transition: all 0.18s ease;
      cursor: pointer;
      box-sizing: border-box;
    }

    .nav-link:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #f8fafc;
    }

    .nav-link.active {
      background: #199bf1;
      color: #071b2d;
      font-weight: 600;
      box-shadow: 0 4px 14px rgba(25, 155, 241, 0.35);
    }

    .nav-link.active::after {
      content: "";
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #071b2d;
      margin-left: auto;
      flex-shrink: 0;
    }

    .nav-icon {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
      stroke-width: 2;
    }

    .nav-link-text {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .nav-dropdown {
      display: flex;
      flex-direction: column;
      margin: 0;
    }

    .nav-dropdown-toggle {
      display: flex;
      align-items: center;
      gap: 11px;
      width: 100%;
      padding: 10px 14px;
      border: none;
      border-radius: 8px;
      background: transparent;
      color: #94a3b8;
      font-size: 13.5px;
      font-weight: 500;
      line-height: 1.3;
      font-family: inherit;
      cursor: pointer;
      text-align: left;
      transition: all 0.18s ease;
      box-sizing: border-box;
    }

    .nav-dropdown-toggle:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #f8fafc;
    }

    .nav-dropdown.open > .nav-dropdown-toggle {
      color: #f8fafc;
    }

    .nav-dropdown-caret {
      width: 14px;
      height: 14px;
      margin-left: auto;
      flex-shrink: 0;
      stroke-width: 2.2;
      color: #64748b;
      transition: transform 0.2s ease, color 0.18s ease;
    }

    .nav-dropdown.open .nav-dropdown-caret {
      transform: rotate(90deg);
      color: #cbd5e1;
    }

    .nav-dropdown-menu {
      display: none;
      flex-direction: column;
      gap: 2px;
      margin: 4px 0 6px 18px;
      padding: 2px 0 2px 14px;
      border-left: 1.5px solid rgba(148, 163, 184, 0.22);
    }

    .nav-dropdown.open .nav-dropdown-menu {
      display: flex;
    }

    .nav-sub-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      border: none;
      border-radius: 6px;
      text-decoration: none;
      color: #8fa0b5;
      font-size: 13px;
      font-weight: 500;
      line-height: 1.3;
      background: transparent;
      transition: all 0.18s ease;
      box-sizing: border-box;
    }

    .nav-sub-link:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #f8fafc;
    }

    .nav-sub-link.active {
      background: #199bf1;
      color: #071b2d;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(25, 155, 241, 0.28);
    }

    .nav-sub-link.active::after {
      content: "";
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #071b2d;
      margin-left: auto;
      flex-shrink: 0;
    }

    .sub-icon {
      width: 15px;
      height: 15px;
      flex-shrink: 0;
      stroke-width: 2;
      opacity: 0.85;
    }

    .nav-divider {
      height: 1px;
      background: var(--line);
      margin: 8px 4px;
      opacity: 0.6;
    }

    .logout-link {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 10px 14px;
      border-radius: 8px;
      border: 1px solid rgba(239, 68, 68, 0.25);
      background: rgba(239, 68, 68, 0.08);
      text-decoration: none;
      color: #f87171;
      font-size: 13.5px;
      font-weight: 600;
      line-height: 1.3;
      transition: all 0.18s ease;
      box-sizing: border-box;
      cursor: pointer;
    }

    .logout-link:hover {
      background: rgba(239, 68, 68, 0.2);
      border-color: rgba(239, 68, 68, 0.5);
      color: #ffffff;
      transform: translateY(-1px);
    }

    .logout-link .nav-icon {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }

    body.light-theme .sidebar {
      background: #ffffff;
      border-right-color: #e2e8f0;
    }

    body.light-theme .sidebar-header .brand {
      border-color: transparent;
    }

    body.light-theme .sidebar-toggle {
      border-color: #cbd5e1;
      background: #f1f5f9;
      color: #0f172a;
    }

    body.light-theme .sidebar-toggle:hover {
      background: #e2e8f0;
    }

    body.light-theme .nav-divider {
      background: #e2e8f0;
    }

    body.light-theme .nav-link,
    body.light-theme .nav-dropdown-toggle {
      color: #475569;
    }

    body.light-theme .nav-link:hover,
    body.light-theme .nav-dropdown-toggle:hover {
      background: #f1f5f9;
      color: #0f172a;
    }

    body.light-theme .nav-dropdown.open > .nav-dropdown-toggle {
      color: #0f172a;
    }

    body.light-theme .nav-dropdown-caret {
      color: #94a3b8;
    }

    body.light-theme .nav-dropdown.open .nav-dropdown-caret {
      color: #334155;
    }

    body.light-theme .nav-dropdown-menu {
      border-left-color: #cbd5e1;
    }

    body.light-theme .nav-sub-link {
      color: #64748b;
    }

    body.light-theme .nav-sub-link:hover {
      background: #f1f5f9;
      color: #0f172a;
    }

    body.light-theme .nav-link.active,
    body.light-theme .nav-sub-link.active {
      background: linear-gradient(135deg, #0284c7, #0369a1);
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.28);
    }

    body.light-theme .nav-link.active::after,
    body.light-theme .nav-sub-link.active::after {
      background: #ffffff;
    }

    body.light-theme .logout-link {
      border-color: rgba(220, 38, 38, 0.25);
      background: rgba(220, 38, 38, 0.06);
      color: #dc2626;
    }

    body.light-theme .logout-link:hover {
      background: rgba(220, 38, 38, 0.14);
      border-color: rgba(220, 38, 38, 0.45);
      color: #b91c1c;
      transform: translateY(-1px);
    }

    .main-content {
      padding: 24px;
      background: var(--content-bg);
      color: var(--content-text);
    }

    .app-shell.sidebar-collapsed {
      grid-template-columns: 82px 1fr;
    }

    .app-shell.sidebar-collapsed .sidebar {
      padding: 24px 10px;
    }

    .app-shell.sidebar-collapsed .sidebar-header {
      flex-direction: column;
      align-items: center;
    }

    .app-shell.sidebar-collapsed .brand {
      padding: 0;
      flex: 0 0 auto;
      width: 100%;
      min-height: auto;
    }

    .app-shell.sidebar-collapsed .brand-text {
      display: none;
    }

    .app-shell.sidebar-collapsed .brand-mini {
      display: block;
    }

    .app-shell.sidebar-collapsed nav,
    .app-shell.sidebar-collapsed .sidebar-footer {
      display: none;
    }

    .topbar {
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--content-line);
    }

    .topbar h1 {
      margin: 0 0 6px;
      font-size: 24px;
      color: var(--content-text);
    }

    .topbar p {
      margin: 0;
      color: var(--content-muted);
      font-size: 14px;
    }

    .card {
      background: var(--content-panel);
      border: 1px solid var(--content-line);
      border-radius: 18px;
      box-shadow: 0 12px 30px rgba(16, 40, 71, 0.08);
      padding: 18px;
      margin-bottom: 16px;
    }

    .step-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
      font-size: 14px;
      color: var(--content-muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .icon {
      width: 22px;
      height: 22px;
      border: 1px solid var(--content-line-strong);
      background: var(--content-panel-soft);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      color: var(--content-text);
      border-radius: 999px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-size: 13px;
      color: var(--content-muted);
    }

    input[type="text"],
    input[type="date"],
    select {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--content-line-strong);
      background: var(--content-panel-soft);
      color: var(--content-text);
      margin-bottom: 12px;
      border-radius: 12px;
    }

    input[type="text"],
    input[type="date"],
    select,
    button {
      transition: border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease, background-color 0.22s ease;
    }

    input[type="text"]:focus,
    input[type="date"]:focus,
    select:focus {
      outline: none;
      border-color: var(--content-button-hover);
      box-shadow: 0 0 0 4px rgba(26, 75, 132, 0.14), 0 10px 22px rgba(18, 57, 104, 0.08);
      transform: translateY(-1px);
    }

    select {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      padding-right: 42px;
      background-image:
        linear-gradient(45deg, transparent 50%, var(--content-button) 50%),
        linear-gradient(135deg, var(--content-button) 50%, transparent 50%);
      background-position:
        calc(100% - 22px) calc(50% - 3px),
        calc(100% - 16px) calc(50% - 3px);
      background-size: 6px 6px, 6px 6px;
      background-repeat: no-repeat;
    }

    select:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(18, 57, 104, 0.08);
    }

    select:focus {
      animation: dropdownLift 180ms ease-out;
    }

    @keyframes dropdownLift {
      0% {
        transform: translateY(4px);
        opacity: 0.88;
      }

      100% {
        transform: translateY(-1px);
        opacity: 1;
      }
    }

    .custom-select {
      position: relative;
      width: 100%;
      margin-bottom: 12px;
      overflow: visible;
    }

    .custom-select select {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      margin: 0;
      opacity: 0;
      pointer-events: none;
      border: 0;
      padding: 0;
      background: transparent;
      box-shadow: none;
      transform: none;
    }

    .custom-select-button {
      width: 100%;
      min-height: 48px;
      padding: 11px 42px 11px 14px;
      border: 1px solid var(--content-line-strong);
      background: var(--content-panel-soft);
      color: var(--content-text);
      border-radius: 12px;
      text-align: left;
      font-weight: 500;
      position: relative;
      box-shadow: 0 0 0 rgba(0, 0, 0, 0);
    }

    .custom-select-button::after {
      content: "";
      position: absolute;
      right: 16px;
      top: 50%;
      width: 8px;
      height: 8px;
      border-right: 2px solid var(--content-button);
      border-bottom: 2px solid var(--content-button);
      transform: translateY(-65%) rotate(45deg);
      transition: transform 0.5s ease;
    }

    .custom-select.is-open .custom-select-button {
      border-color: var(--content-button-hover);
      box-shadow: 0 0 0 4px rgba(26, 75, 132, 0.14), 0 12px 28px rgba(18, 57, 104, 0.12);
      transform: translateY(-1px);
    }

    .custom-select.is-open .custom-select-button::after {
      transform: translateY(-35%) rotate(225deg);
    }

    .custom-select-button.is-placeholder {
      color: var(--content-muted);
    }

    .custom-select-panel {
      position: relative;
      width: 100%;
      margin-top: 8px;
      background: var(--content-panel);
      border: 1px solid var(--content-line-strong);
      border-radius: 16px;
      box-shadow: 0 18px 34px rgba(14, 34, 60, 0.16);
      max-height: 0;
      opacity: 0;
      overflow: hidden;
      transform: translateY(-12px) scaleY(0.94);
      transform-origin: top;
      pointer-events: none;
      transition: max-height 0.56s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.42s ease, transform 0.56s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .custom-select.is-open .custom-select-panel {
      max-height: 320px;
      opacity: 1;
      transform: translateY(0) scaleY(1);
      pointer-events: auto;
    }

    .custom-select-list {
      max-height: 290px;
      overflow-y: auto;
      padding: 8px;
    }

    .custom-select-option {
      width: 100%;
      border: 0;
      background: transparent;
      color: var(--content-text);
      padding: 10px 12px;
      text-align: left;
      border-radius: 10px;
      font-weight: 500;
      transition: background-color 0.24s ease, transform 0.24s ease, color 0.24s ease;
      box-shadow: none;
    }

    .custom-select-option:hover {
      background: var(--content-panel-soft);
      transform: translateX(3px);
    }

    .custom-select-option.is-selected {
      background: linear-gradient(135deg, var(--content-button), var(--content-button-hover));
      color: var(--content-button-text);
    }

    .custom-select-option.is-disabled {
      color: var(--content-muted);
      opacity: 0.65;
      cursor: not-allowed;
    }

    .custom-select-option.is-disabled:hover {
      background: transparent;
      transform: none;
    }

    button {
      border: 1px solid var(--content-button);
      background: var(--content-button);
      color: var(--content-button-text);
      padding: 10px 16px;
      border-radius: 999px;
      font-weight: 600;
      cursor: pointer;
    }

    button:hover {
      background: var(--content-button-hover);
      border-color: var(--content-button-hover);
    }

    button.primary {
      background: linear-gradient(135deg, var(--content-button), var(--content-button-hover));
      color: var(--content-button-text);
      border-color: var(--content-button);
      font-weight: bold;
      box-shadow: 0 10px 24px rgba(18, 57, 104, 0.22);
    }

    .warning-msg {
      margin-top: 12px;
      padding: 10px;
      border: 1px solid var(--content-line-strong);
      background: var(--content-panel-soft);
      color: var(--content-text);
      border-radius: 14px;
      font-weight: bold;
    }

    .preview-wrap {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }

    .col h2 {
      margin: 0 0 10px;
      text-align: center;
      font-size: 15px;
      letter-spacing: 0.05em;
      color: var(--content-text);
    }

    .grid9 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .label {
      background: #ffffff;
      border: 1px solid var(--content-line-strong);
      padding: 8px;
      text-align: center;
      color: #111111;
      overflow: hidden;
      border-radius: 14px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .label svg {
      width: 100%;
      max-width: 100%;
      height: 42px;
      display: block;
      margin: 0 auto;
      shape-rendering: crispEdges;
      overflow: visible;
    }

	    .pcb-preview-text {
	      display: block;
	      width: 100%;
	      height: 16px;
	      margin-top: 4px;
	      font-size: 13px;
	      font-family: Arial, sans-serif;
	      font-weight: 550;
	      letter-spacing: 0.02em;
	    }
	    .ev-pcb-preview-code-line {
	      display: flex;
	      align-items: flex-end;
	      justify-content: space-between;
	      width: 100%;
	      height: 16px;
	      margin-top: 4px;
	      gap: 8px;
	    }
		    .ev-pcb-preview-code-text {
		      flex: 1 1 auto;
		      min-width: 0;
		      height: 100%;
		      font-size: 13px;
		      font-family: Calibri, Arial, sans-serif;
		      font-weight: 600;
		      letter-spacing: 0.02em;
		      transform: translateX(1.7mm);
		    }
		    .ev-pcb-preview-batch-text {
		      flex: 0 0 38px;
		      height: 100%;
		      font-size: 11px;
		      font-family: Calibri, Arial, sans-serif;
		      font-weight: 600;
		      letter-spacing: 0.02em;
		      transform: translateX(-2.1mm);
		    }

	    .pcb-preview-text svg {
	      width: 100%;
      height: 100%;
	      display: block;
	      overflow: visible;
	    }
	    .ev-pcb-preview-code-text svg,
	    .ev-pcb-preview-batch-text svg {
	      width: 100%;
	      height: 100%;
	      display: block;
	      overflow: visible;
	    }

    .custom-fields {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .custom-fields .custom-select,
    .custom-fields select,
    .custom-fields input {
      flex: 1;
      min-width: 150px;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
    }

    .modal-content {
      background: var(--content-panel);
      margin: 13% auto;
      padding: 18px;
      width: 380px;
      border: 1px solid var(--content-line-strong);
      text-align: center;
      color: var(--content-text);
      border-radius: 18px;
    }

    .modal-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 12px;
    }

    .placeholder {
      border: 1px dashed var(--content-line-strong);
      padding: 18px;
      background: var(--content-panel-soft);
      color: var(--content-muted);
      line-height: 1.6;
      border-radius: 16px;
    }

    .nameplate-controls {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      align-items: end;
    }

    .dropdown-overlay-card,
    .dropdown-overlay-card .nameplate-controls,
    .dropdown-overlay-card .nameplate-controls > div {
      overflow: visible;
    }

    .dropdown-overlay-card .custom-select {
      z-index: 40;
    }

    .dropdown-overlay-card .custom-select-panel {
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      right: 0;
      width: auto;
      margin-top: 0;
    }

    .nameplate-preview-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(280px, 1fr));
      gap: 14px;
      margin-top: 12px;
    }

    .nameplate-card {
      background: #ffffff;
      border: 1px solid var(--content-line-strong);
      padding: 8px 10px;
      color: #111111;
      min-height: 122px;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

		    .np-body {
		      display: grid;
		      grid-template-columns: 1fr 100px;
		      gap: 8px;
		      align-items: start;
		      transform: translateY(-0.8mm);
		    }

			    .np-barcode {
			      width: 100%;
			      transform: translateY(0.3px);
			    }

		    .np-barcode > svg {
		      width: 100%;
		      height: 42px;
		      display: block;
		      margin: 6px 0 -2px 0;
		      shape-rendering: crispEdges;
		      overflow: visible;
		    }

		    .np-meta {
		      display: flex;
		      justify-content: space-between;
		      align-items: center;
		      font-size: 12px;
		      font-weight: bold;
		      margin-bottom: 6px;
		      color: #111111;
		      line-height: 1;
		      transform: translateY(2px);
		    }

		    .np-meta-text {
		      display: block;
		      width: 100%;
		      height: 12px;
						      font-family: Calibri, Arial, sans-serif;
		      font-weight: 420;
		      letter-spacing: 0.01em;
		      line-height: 1;
				      transform: translate(-0.28mm, 2mm);
		    }

	    .np-meta-text svg,
	    .np-qr-label svg {
	      display: block;
	      width: 100%;
	      height: 100%;
	      overflow: visible;
	    }

		    .np-number {
		      font-size: 30px;
		      line-height: 1;
		      width: 100%;
      display: flex;
      justify-content: space-between;
		      align-items: baseline;
		      font-family: "Century Gothic", Arial, sans-serif;
		      font-weight: 600;
		      letter-spacing: 0;
		      color: #111111;
		      margin-top: 8px;
		    }

	    .np-number span {
	      white-space: nowrap;
	    }

		    .np-qr-wrap {
		      display: flex;
		      flex-direction: column;
		      align-items: flex-end;
		      justify-content: flex-start;
		      gap: 6px;
		      padding-top: 2px;
		      position: relative;
		      transform: translateX(-2mm);
		    }

				    .np-qr-label {
				      display: block;
				      position: absolute;
				      top: 6px;
				      right: 0;
				      z-index: 2;
				      font-size: 31px;
					      font-family: "Century Gothic", Arial, sans-serif;
				      font-weight: 440;
				      height: 31px;
		      width: 132px;
		      color: #111111;
		      line-height: 1;
			      letter-spacing: 0.01em;
			      white-space: nowrap;
			      text-align: right;
			      pointer-events: none;
						      transform: translate(-0.9mm, -1.2mm);
				    }

		    .np-qr {
		      width: 100.5px;
		      height: 100.5px;
	      display: block;
	      background: #fff;
	      overflow: visible;
			      shape-rendering: crispEdges;
			      flex: 0 0 auto;
			      margin-top: 14px;
				      transform: translate(16px, -3.4mm);
			      position: relative;
			      z-index: 1;
			    }

    .np-hint {
      margin-top: 10px;
      font-size: 13px;
      color: var(--content-muted);
    }

    .main-content {
      padding: clamp(16px, 2vw, 24px);
      overflow-x: hidden;
    }

    .modal-content {
      width: min(380px, calc(100vw - 32px));
      margin: max(48px, 10vh) auto;
    }

    .nameplate-preview-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    #customPreviewContainer .np-number {
      justify-content: center;
      gap: 8px;
    }

    .nameplate-card {
      padding: clamp(8px, 1.2vw, 12px);
      min-height: 0;
    }

    /* Custom Print Centering overrides for text-only (no-barcode/no-qr) label previews */
    .label.no-barcode {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      height: 98px;
      box-sizing: border-box;
    }
    .label.no-barcode .pcb-preview-text {
      margin-top: 0;
      line-height: 1.2;
      height: auto;
    }
    .nameplate-card.no-barcode.no-qr .np-body {
      display: flex !important;
      flex-direction: column !important;
      justify-content: center !important;
      align-items: center !important;
      height: 100% !important;
      min-height: 104px;
      transform: none !important;
    }
    .nameplate-card.no-barcode.no-qr .np-barcode {
      display: flex !important;
      flex-direction: column !important;
      justify-content: center !important;
      align-items: center !important;
      width: 100% !important;
      height: 100% !important;
      transform: none !important;
    }
    .nameplate-card.no-barcode.no-qr .np-meta {
      display: block !important;
      width: 100% !important;
      text-align: center !important;
      margin: 0 !important;
      transform: none !important;
      order: 2;
    }
    .nameplate-card.no-barcode.no-qr .np-meta-text {
      font-size: 11px !important;
      height: auto !important;
      transform: none !important;
      margin-top: 4px !important;
    }
    .nameplate-card.no-barcode.no-qr .np-number {
      font-size: 24px !important;
      justify-content: center !important;
      width: 100% !important;
      margin-top: 0 !important;
      order: 1;
    }
    .nameplate-card.no-barcode:not(.no-qr) .np-barcode {
      display: flex !important;
      flex-direction: column !important;
      justify-content: center !important;
      height: 100% !important;
      min-height: 104px;
      transform: none !important;
    }
    .nameplate-card.no-barcode:not(.no-qr) .np-meta {
      transform: none !important;
      margin-bottom: 8px !important;
    }
    .nameplate-card.no-barcode:not(.no-qr) .np-meta-text {
      transform: none !important;
    }
    .nameplate-card.no-barcode:not(.no-qr) .np-number {
      margin-top: 0 !important;
    }

    .np-body {
      grid-template-columns: minmax(0, 1fr) minmax(78px, 100px);
      gap: clamp(6px, 1vw, 10px);
    }

	    .np-meta {
	      font-size: clamp(10px, 0.95vw, 12px);
	      margin-bottom: 4px;
	      min-height: 14px;
	      transform: none;
	    }

	    .np-meta-text,
	    .np-qr-label {
	      height: clamp(10px, 0.95vw, 12px);
	    }

    .np-barcode svg {
      height: clamp(34px, 3.1vw, 42px);
      margin: 4px 0 0 0;
    }

    .np-number {
      font-size: clamp(19px, 2.1vw, 30px);
      gap: clamp(6px, 0.8vw, 12px);
      flex-wrap: wrap;
      margin-top: 6px;
    }

    .np-qr-wrap {
      width: 100%;
      max-width: 100px;
      gap: 4px;
      padding-top: 0;
      justify-self: end;
    }

			    .np-qr-label {
			      position: static;
			      font-size: clamp(24px, 2vw, 26px);
			      line-height: 1.1;
			      white-space: normal;
			      width: 100%;
			      max-width: 132px;
			      margin-left: auto;
			      transform: translate(-0.9mm, -2mm);
		    }

	    .np-qr {
	      width: clamp(66.5px, 7vw, 100.5px);
	      height: clamp(66.5px, 7vw, 100.5px);
	      margin-top: 0;
	      transform: translateY(-3mm);
	    }

    @media (max-width: 1400px), (max-height: 820px) {
      .app-shell {
        grid-template-columns: 220px 1fr;
      }

      .app-shell.sidebar-collapsed {
        grid-template-columns: 76px 1fr;
      }

      .sidebar {
        padding: 18px 14px;
      }

      .main-content {
        padding: 16px;
      }

      .theme-toggle {
        right: 12px;
        top: 10px;
      }

      .preview-wrap {
        grid-template-columns: 1fr;
      }

      .nameplate-controls {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .nameplate-controls > div:last-child {
        grid-column: 1 / -1;
      }

      .nameplate-preview-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 1100px) {
      .nameplate-controls {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 900px) {
      .app-shell {
        grid-template-columns: 1fr;
      }

      .sidebar {
        border-right: none;
        border-bottom: 1px solid var(--line);
      }

      .app-shell.sidebar-collapsed {
        grid-template-columns: 1fr;
      }

      .app-shell.sidebar-collapsed .sidebar {
        padding: 14px 16px;
      }

      .app-shell.sidebar-collapsed .sidebar-header {
        flex-direction: row;
        align-items: center;
      }

      .app-shell.sidebar-collapsed .brand {
        border: none;
        padding: 6px 4px;
        min-height: 58px;
      }

      .app-shell.sidebar-collapsed .brand-mini {
        display: none;
      }

      .app-shell.sidebar-collapsed .brand-text {
        display: block;
      }

      .preview-wrap {
        grid-template-columns: 1fr;
      }

      .nameplate-controls {
        grid-template-columns: 1fr;
      }

      .nameplate-preview-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="brand">
          <img class="brand-text brand-logo" src="IMG/sidebar-logo.svg" alt="Barcode Print Generator Logo">
          <span class="brand-mini" aria-hidden="true">
            <img src="IMG/sidebar-logo-mini.svg" alt="">
          </span>
        </div>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar" aria-expanded="true">&#9776;</button>
      </div>

	      <nav>
	        <a class="nav-link <?= $menu === 'pcb' ? 'active' : '' ?>" href="userview.php?menu=pcb">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>
	          <span class="nav-link-text">Print PCB Barcode</span>
	        </a>
	        <div class="nav-dropdown <?= ($menu === 'ev_charger' || $menu === 'ev_charger_sn') ? 'open' : '' ?>">
	          <button type="button" class="nav-dropdown-toggle">
	            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
	            <span class="nav-link-text">EV Charge</span>
	            <svg class="nav-dropdown-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
	          </button>
	          <div class="nav-dropdown-menu">
	            <a class="nav-sub-link <?= $menu === 'ev_charger' ? 'active' : '' ?>" href="userview.php?menu=ev_charger">
	              <svg class="nav-icon sub-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
	              <span class="nav-link-text">Print PCB EV Charge</span>
	            </a>
	            <a class="nav-sub-link <?= $menu === 'ev_charger_sn' ? 'active' : '' ?>" href="userview.php?menu=ev_charger_sn">
	              <svg class="nav-icon sub-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="6" height="6" rx="1"></rect><rect x="15" y="3" width="6" height="6" rx="1"></rect><rect x="3" y="15" width="6" height="6" rx="1"></rect><path d="M14 14h3v3h-3z"></path><path d="M20 14v6h-6"></path><path d="M17 17v3"></path></svg>
	              <span class="nav-link-text">Print SN EV Charge</span>
	            </a>
	          </div>
	        </div>
	        <a class="nav-link <?= $menu === 'nameplate' ? 'active' : '' ?>" href="userview.php?menu=nameplate">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
	          <span class="nav-link-text">Print Nameplate Barcode</span>
	        </a>
	        <a class="nav-link <?= $menu === 'custom' ? 'active' : '' ?>" href="userview.php?menu=custom">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
	          <span class="nav-link-text">Custom Print</span>
	        </a>
	        <div class="nav-divider"></div>
	        <a class="logout-link" href="Logout1.php">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
	          <span>Logout</span>
	        </a>
	      </nav>
    </aside>

    <main class="main-content">
      <button type="button" id="themeToggleBtn" class="theme-toggle" aria-label="Toggle Theme">&#9728;&#65039; Light Mode</button>
	      <?php if ($menu === 'pcb' || $menu === 'ev_charger'): ?>
	        <div class="topbar">
	          <h1><?= $isEvChargerMenu ? 'Print PCB EV Chager' : 'Print PCB Barcode' ?></h1>
	          <p><?= $isEvChargerMenu ? 'Halaman print PCB EV Chager dengan format label yang sama seperti PCB barcode.' : 'Halaman utama setelah login untuk preview dan print barcode PCB.' ?></p>
	        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">1</span>
            Select Work Order for Print
          </div>
	          <form method="GET">
	            <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
            <select name="wo" onchange="this.form.submit()">
              <option value="">Choose a work order</option>
              <?php
              mysqli_data_seek($woList, 0);
              while ($wo = mysqli_fetch_assoc($woList)) {
              ?>
                <option value="<?= $wo['wo'] ?>" <?= $selectedWO == $wo['wo'] ? 'selected' : '' ?>>
                  <?= $wo['wo'] ?>
                </option>
              <?php } ?>
            </select>
          </form>
        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">2</span>
            Select Batch
          </div>
          <p>Choose the batch for processing.</p>
	          <form method="GET">
	            <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
	            <input type="hidden" name="wo" value="<?= htmlspecialchars($selectedWO) ?>">
            <select name="batch_id" onchange="this.form.submit()" <?= $selectedWO === '' ? 'disabled' : '' ?>>
              <option value=""><?= $selectedWO === '' ? 'Pilih Work Order dulu' : '-- Pilih Batch --' ?></option>
              <?php
              if ($selectedWO != '') {
                  $batchQuery = "SELECT * FROM {$pcbBatchTable}";
                  $safeWo = mysqli_real_escape_string($koneksi, $selectedWO);
                  $batchQuery .= " WHERE wo='$safeWo'";
                  $batchQuery .= " ORDER BY id ASC";
                  $batchList = mysqli_query($koneksi, $batchQuery);
                  while ($batch = mysqli_fetch_assoc($batchList)) {
                      $id = (int) $batch['id'];
                      $sel = ($selectedBatchId == $id) ? 'selected' : '';
                      $label = htmlspecialchars($batch['nama_batch']);
                      $disabled = '';
                      if ($isEvChargerMenu) {
                          $statRes = $koneksi->query("SELECT 
                              COUNT(*) AS total_cnt,
                              SUM(CASE WHEN is_printed = 1 THEN 1 ELSE 0 END) AS printed_cnt,
                              SUM(CASE WHEN is_printed = 0 THEN 1 ELSE 0 END) AS unprinted_cnt
                              FROM ev_charger_nomor_barcode WHERE batch_id=$id");
                          $statRow = $statRes ? $statRes->fetch_assoc() : null;
                          $totalCnt = (int) ($statRow['total_cnt'] ?? 0);
                          $printedCnt = (int) ($statRow['printed_cnt'] ?? 0);
                          $unprintedCnt = (int) ($statRow['unprinted_cnt'] ?? 0);

                          if ($totalCnt > 0 && $unprintedCnt === 0) {
                              $disabled = 'disabled';
                              $label .= ' (Selesai di-print)';
                          } elseif ($printedCnt > 0) {
                              $label .= " (Sisa {$unprintedCnt} dari {$totalCnt} nomor)";
                          } else {
                              $label .= " ({$totalCnt} nomor, Belum di-print)";
                          }
                      } else {
                          $checkBatchPrint = $koneksi->query("SELECT COUNT(*) as cnt FROM {$pcbPrintLogTable} WHERE batch_id=$id");
                          $batchPrintRow = $checkBatchPrint->fetch_assoc();
                          if ($batchPrintRow['cnt'] > 0) {
                              $disabled = 'disabled';
                              $label .= ' (Sudah di-print)';
                          }
                      }
                      echo "<option value='{$id}' {$sel} {$disabled}>{$label}</option>";
                  }
              }
              ?>
            </select>
          </form>
        </div>

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
	                        <?= $isEvChargerMenu ? buildEvPcbCodeLine($num, $pcbBatchLabel, true) : buildHdTextPlaceholder('pcb-preview-text', $num, 'center') ?>
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
	                        <?= $isEvChargerMenu ? buildEvPcbCodeLine($num, $pcbBatchLabel, true) : buildHdTextPlaceholder('pcb-preview-text', $num, 'center') ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

	              <?php if ($isEvChargerMenu): ?>
	                <div class="ev-batch-status-box" style="margin-bottom: 16px; padding: 14px 18px; border-radius: 12px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong);">
	                  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
	                    <div>
	                      <span style="font-size: 13px; color: var(--content-muted);">Total Barcode Batch:</span>
	                      <strong style="margin-left: 6px; font-size: 15px;"><?= number_format($totalBatchCount, 0, ',', '.') ?></strong>
	                    </div>
	                    <div>
	                      <span style="font-size: 13px; color: #10b981;">Sudah Tercetak:</span>
	                      <strong style="margin-left: 6px; font-size: 15px; color: #10b981;"><?= number_format($printedCount, 0, ',', '.') ?></strong>
	                    </div>
	                    <div>
	                      <span style="font-size: 13px; color: #3b82f6;">Sisa Belum Cetak:</span>
	                      <strong style="margin-left: 6px; font-size: 15px; color: #3b82f6;"><?= number_format($unprintedCount, 0, ',', '.') ?></strong>
	                    </div>
	                  </div>
	                  <?php if ($printedCount > 0 && !empty($printedNumbers)): ?>
	                    <div style="margin-top: 10px; font-size: 12px; color: var(--content-muted); border-top: 1px dashed var(--content-line-strong); padding-top: 8px;">
	                      <span style="color: #10b981; font-weight: 600;">&#10003; Tercetak:</span> <?= htmlspecialchars($printedNumbers[0]) ?> s/d <?= htmlspecialchars(end($printedNumbers)) ?> (<?= number_format($printedCount, 0, ',', '.') ?> nomor, tidak dapat dicetak ulang)
	                    </div>
	                  <?php endif; ?>
	                  <?php if ($unprintedCount > 0 && !empty($unprintedNumbers)): ?>
	                    <div style="margin-top: 6px; font-size: 12px; color: #3b82f6;">
	                      <span style="font-weight: 600;">&#9654; Tersedia untuk Cetak:</span> <?= htmlspecialchars($unprintedNumbers[0]) ?> s/d <?= htmlspecialchars(end($unprintedNumbers)) ?> (<?= number_format($unprintedCount, 0, ',', '.') ?> nomor)
	                    </div>
	                  <?php endif; ?>
	                </div>
	              <?php endif; ?>

	              <?php if ($isPrinted): ?>
	                <div class="warning-msg" style="<?= $isEvChargerMenu ? 'background: rgba(16, 185, 129, 0.1); border-color: #10b981; color: #10b981;' : '' ?>">
	                  <?= $isEvChargerMenu ? 'Batch ini sudah selesai di-print seluruhnya dan tidak dapat di-print ulang.' : 'Batch ini sudah di-print dan tidak dapat di-print ulang.' ?>
	                </div>
	              <?php else: ?>
	                <form id="printForm" method="POST" data-is-printed="<?= $isPrinted ? '1' : '0' ?>" data-is-ev="<?= $isEvChargerMenu ? '1' : '0' ?>">
	                  <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
	                  <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">
	                  <input type="hidden" name="wo" value="<?= htmlspecialchars($woBatch) ?>">
	                  <input type="hidden" name="start" value="<?= htmlspecialchars($start) ?>">
	                  <input type="hidden" name="end" value="<?= htmlspecialchars($end) ?>">
	                  <input type="hidden" name="<?= $isEvChargerMenu ? 'print_ev_charger' : 'print' ?>" value="1">
	                  <input type="hidden" name="record_batch" id="recordBatchChoice" value="0">

	                  <?php if ($isEvChargerMenu): ?>
	                    <div style="margin-bottom: 16px; padding: 14px; border-radius: 10px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong);">
	                      <label style="font-weight: 600; font-size: 14px; margin-bottom: 8px; display: block; color: var(--content-text);">Custom Range Print EV Charger</label>
	                      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
	                        <div>
	                          <label style="font-size: 12px; margin-bottom: 4px;">Nomor Awal Print</label>
	                          <input type="text" name="ev_start_number" id="evStartNumber" value="<?= htmlspecialchars($defaultEvStart) ?>" required style="margin-bottom: 0;">
	                        </div>
	                        <div>
	                          <label style="font-size: 12px; margin-bottom: 4px;">Nomor Akhir Print</label>
	                          <input type="text" name="ev_end_number" id="evEndNumber" value="<?= htmlspecialchars($defaultEvEnd) ?>" required style="margin-bottom: 0;">
	                        </div>
	                        <div>
	                          <label style="font-size: 12px; margin-bottom: 4px;">Quantity (Jumlah Nomor)</label>
	                          <input type="number" name="ev_print_qty" id="evPrintQty" min="1" max="<?= max(1, $unprintedCount) ?>" value="<?= $unprintedCount ?>" required style="margin-bottom: 0;">
	                        </div>
	                      </div>
	                      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
	                        <div style="font-size: 12px; color: var(--content-muted);">
	                          Estimasi: <strong id="evSheetsEstimate" style="color: var(--content-button-hover);"><?= (int) ceil($unprintedCount / 9) ?> Lembar</strong> (3x3 = 9 barcode/lembar)
	                        </div>
	                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
	                          <button type="button" id="btnEvQuickAll" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">Cetak Semua Sisa (<?= $unprintedCount ?>)</button>
	                          <button type="button" id="btnEvQuick9" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">1 Lembar (9)</button>
	                          <button type="button" id="btnEvQuick18" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">2 Lembar (18)</button>
	                        </div>
	                      </div>
	                      <div id="evRangeError" style="display: none; margin-top: 8px; padding: 6px 12px; border-radius: 8px; font-size: 12px; color: #dc2626; background: rgba(220, 38, 38, 0.08); border: 1px solid rgba(220, 38, 38, 0.2);"></div>
	                    </div>

	                    <label>Custom Text & Tanggal</label>
	                    <div class="custom-fields">
	                      <select name="ev_type_name" required>
	                        <option value=""><?= empty($evTypeOptions) ? 'Belum ada custom text EV' : 'Pilih Custom Text EV' ?></option>
	                        <?php foreach ($evTypeOptions as $evTypeOption): ?>
	                          <option value="<?= htmlspecialchars($evTypeOption) ?>"><?= htmlspecialchars($evTypeOption) ?></option>
	                        <?php endforeach; ?>
	                      </select>
	                      <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
	                    </div>
	                    <p class="status-msg" style="margin-top: 6px;">Pilih custom text EV Charger dan range nomor barcode yang akan dicetak. Nomor yang sudah dicetak otomatis tercatat dan tidak dapat dicetak ulang.</p>
	                  <?php else: ?>
	                    <label>Custom Text</label>
	                    <div class="custom-fields">
	                      <select name="type_meter" required>
	                        <option value=""><?= empty($typeMeterOptions) ? 'Belum ada Type Meter' : 'Pilih Type Meter' ?></option>
	                        <?php foreach ($typeMeterOptions as $typeMeterOption): ?>
	                          <option value="<?= htmlspecialchars($typeMeterOption) ?>"><?= htmlspecialchars($typeMeterOption) ?></option>
	                        <?php endforeach; ?>
	                      </select>
	                      <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
	                    </div>
	                  <?php endif; ?>

	                  <button class="primary" type="button" id="printBtn"><?= $isEvChargerMenu ? 'Print Barcode EV' : 'Print' ?></button>
	                </form>
                <div id="recordConfirmModal" class="modal">
                  <div class="modal-content">
                    <h3 id="recordConfirmTitle">Konfirmasi Record</h3>
                    <p id="recordConfirmMessage">Record batch ini ke database sebelum cetak?</p>
                    <div class="modal-actions">
                      <button type="button" id="recordConfirmYes">Ya</button>
                      <button type="button" id="recordConfirmNo">Tidak</button>
                    </div>
                  </div>
                </div>
                <script>
                  (function () {
                    var printForm = document.getElementById("printForm");
                    var printBtn = document.getElementById("printBtn");
                    var recordModal = document.getElementById("recordConfirmModal");
                    var recordField = document.getElementById("recordBatchChoice");
                    var yesBtn = document.getElementById("recordConfirmYes");
                    var noBtn = document.getElementById("recordConfirmNo");
                    var modalTitle = document.getElementById("recordConfirmTitle");
                    var modalMessage = document.getElementById("recordConfirmMessage");

                    if (!printForm || !printBtn || !recordModal || !recordField || !yesBtn || !noBtn || !modalTitle || !modalMessage) {
                      return;
                    }

                    var isEvMode = printForm.dataset.isEv === "1";
                    var evStartInput = document.getElementById("evStartNumber");
                    var evEndInput = document.getElementById("evEndNumber");
                    var evQtyInput = document.getElementById("evPrintQty");
                    var evSheetsEstimate = document.getElementById("evSheetsEstimate");
                    var evRangeError = document.getElementById("evRangeError");
                    var btnQuickAll = document.getElementById("btnEvQuickAll");
                    var btnQuick9 = document.getElementById("btnEvQuick9");
                    var btnQuick18 = document.getElementById("btnEvQuick18");

                    var allBatchList = <?= json_encode(array_values($allNumbers ?? [])) ?>;
                    var unprintedList = <?= json_encode(array_values($unprintedNumbers ?? [])) ?>;
                    var printedSet = new Set(<?= json_encode(array_values($printedNumbers ?? [])) ?>);
                    var allMap = {};
                    for (var i = 0; i < allBatchList.length; i++) {
                      allMap[allBatchList[i]] = i;
                    }

                    function showEvError(msg) {
                      if (evRangeError) {
                        evRangeError.textContent = msg;
                        evRangeError.style.display = "block";
                      }
                      if (printBtn) {
                        printBtn.disabled = true;
                        printBtn.style.opacity = "0.5";
                      }
                    }

                    function clearEvError() {
                      if (evRangeError) {
                        evRangeError.style.display = "none";
                        evRangeError.textContent = "";
                      }
                      if (printBtn) {
                        printBtn.disabled = false;
                        printBtn.style.opacity = "1";
                      }
                    }

                    function validateEvRange() {
                      if (!isEvMode) return true;
                      if (!evStartInput || !evEndInput || !evQtyInput) return false;
                      var sVal = evStartInput.value.trim();
                      var eVal = evEndInput.value.trim();

                      if (!sVal || !eVal) {
                        showEvError("Nomor awal dan nomor akhir wajib diisi.");
                        return false;
                      }

                      if (printedSet.has(sVal)) {
                        showEvError("Nomor awal (" + sVal + ") sudah pernah dicetak dan tidak dapat dicetak lagi.");
                        return false;
                      }
                      if (printedSet.has(eVal)) {
                        showEvError("Nomor akhir (" + eVal + ") sudah pernah dicetak dan tidak dapat dicetak lagi.");
                        return false;
                      }

                      if (allMap[sVal] === undefined) {
                        showEvError("Nomor awal (" + sVal + ") tidak ditemukan dalam data batch ini.");
                        return false;
                      }
                      if (allMap[eVal] === undefined) {
                        showEvError("Nomor akhir (" + eVal + ") tidak ditemukan dalam data batch ini.");
                        return false;
                      }

                      var sIdx = allMap[sVal];
                      var eIdx = allMap[eVal];
                      if (sIdx > eIdx) {
                        showEvError("Nomor akhir harus berada setelah atau sama dengan nomor awal.");
                        return false;
                      }

                      for (var idx = sIdx; idx <= eIdx; idx++) {
                        var num = allBatchList[idx];
                        if (printedSet.has(num)) {
                          showEvError("Range mengandung nomor yang sudah dicetak (" + num + ")! Tidak dapat dicetak ulang.");
                          return false;
                        }
                      }

                      var count = eIdx - sIdx + 1;
                      evQtyInput.value = count;
                      var sheets = Math.ceil(count / 9);
                      if (evSheetsEstimate) {
                        var lastPageCount = count % 9 === 0 ? 9 : (count % 9);
                        evSheetsEstimate.textContent = sheets + " Lembar (" + count + " barcode" + (sheets > 1 ? ", lembar terakhir: " + lastPageCount + " barcode" : "") + ")";
                      }
                      clearEvError();
                      return true;
                    }

                    if (isEvMode) {
                      if (evStartInput) evStartInput.addEventListener("input", validateEvRange);
                      if (evEndInput) evEndInput.addEventListener("input", validateEvRange);
                      if (evQtyInput) {
                        evQtyInput.addEventListener("input", function () {
                          var qty = parseInt(evQtyInput.value, 10);
                          if (isNaN(qty) || qty < 1) return;
                          var sVal = evStartInput.value.trim();
                          if (allMap[sVal] === undefined) return;
                          var sIdx = allMap[sVal];
                          var targetIdx = sIdx + qty - 1;
                          if (targetIdx < allBatchList.length) {
                            evEndInput.value = allBatchList[targetIdx];
                          }
                          validateEvRange();
                        });
                      }

                      if (btnQuickAll && unprintedList.length > 0) {
                        btnQuickAll.addEventListener("click", function () {
                          evStartInput.value = unprintedList[0];
                          evEndInput.value = unprintedList[unprintedList.length - 1];
                          validateEvRange();
                        });
                      }
                      if (btnQuick9 && unprintedList.length > 0) {
                        btnQuick9.addEventListener("click", function () {
                          evStartInput.value = unprintedList[0];
                          var count = Math.min(9, unprintedList.length);
                          evEndInput.value = unprintedList[count - 1];
                          validateEvRange();
                        });
                      }
                      if (btnQuick18 && unprintedList.length > 0) {
                        btnQuick18.addEventListener("click", function () {
                          evStartInput.value = unprintedList[0];
                          var count = Math.min(18, unprintedList.length);
                          evEndInput.value = unprintedList[count - 1];
                          validateEvRange();
                        });
                      }

                      validateEvRange();
                    }

                    function openRecordModal() {
                      if (typeof printForm.reportValidity === "function" && !printForm.reportValidity()) {
                        return false;
                      }
                      if (isEvMode && !validateEvRange()) {
                        return false;
                      }

                      if (isEvMode) {
                        var sVal = evStartInput ? evStartInput.value.trim() : "";
                        var eVal = evEndInput ? evEndInput.value.trim() : "";
                        var qVal = evQtyInput ? evQtyInput.value.trim() : "";
                        modalTitle.textContent = "Konfirmasi Record EV Charger";
                        modalMessage.textContent = "Cetak dan record " + qVal + " barcode (" + sVal + " s/d " + eVal + ") ke database? Nomor yang direcord tidak dapat dicetak ulang.";
                        yesBtn.textContent = "Ya, Record & Cetak";
                        noBtn.textContent = "Hanya Cetak (Tanpa Record)";
                      } else {
                        modalTitle.textContent = "Konfirmasi Record";
                        modalMessage.textContent = "Record batch ini ke database sebelum cetak?";
                        yesBtn.textContent = "Ya";
                        noBtn.textContent = "Tidak";
                      }

                      recordModal.style.display = "block";
                      return true;
                    }

                    printBtn.addEventListener("click", function () {
                      openRecordModal();
                    });

                    printForm.addEventListener("submit", function (event) {
                      if (printForm.dataset.confirmed === "1") {
                        return;
                      }
                      event.preventDefault();
                      openRecordModal();
                    });

                    yesBtn.addEventListener("click", function () {
                      recordField.value = "1";
                      printForm.dataset.confirmed = "1";
                      printForm.submit();
                    });

                    noBtn.addEventListener("click", function () {
                      recordField.value = "0";
                      printForm.dataset.confirmed = "1";
                      printForm.submit();
                    });
                  })();
                </script>
              <?php endif; ?>

              <script>
                (function () {
                  var awal = <?= json_encode($awal9) ?>;
                  var akhir = <?= json_encode($akhir9) ?>;
                  function draw(list, prefix) {
                    for (var i = 0; i < list.length; i++) {
                      JsBarcode("#" + prefix + "-" + i, String(list[i]), {
                        format: "CODE128",
                        displayValue: false,
                        height: 36,
                        width: 1.25,
                        margin: 0
                      });
                    }
                  }
                  draw(awal, "awal");
                  draw(akhir, "akhir");
                  Array.prototype.forEach.call(document.querySelectorAll('svg[id^="awal-"], svg[id^="akhir-"]'), function (svg) {
                    svg.setAttribute("shape-rendering", "crispEdges");
                    svg.setAttribute("vector-effect", "non-scaling-stroke");
                  });
                  if (window.OfflineTextRenderer) {
                    window.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]");
                  }
                })();
              </script>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($menu === 'nameplate'): ?>
        <div class="topbar">
          <h1>Print Nameplate Barcode</h1>
          <p>Data Nameplate terpisah dari PCB. Upload dan split dikerjakan oleh admin.</p>
        </div>

        <div class="card dropdown-overlay-card">
          <div class="step-title">
            <span class="icon">1</span>
            Pilih Work Order, Batch, dan Tanggal Produksi
          </div>
          <form method="GET">
            <input type="hidden" name="menu" value="nameplate">
            <div class="nameplate-controls">
              <div>
                <label>Work Order</label>
                <select name="wo_np" onchange="this.form.submit()">
                  <option value="">Choose a work order</option>
                  <?php
                  $woListNameplatePrint = mysqli_query($koneksi, "SELECT DISTINCT wo FROM nameplate_batch ORDER BY wo ASC");
                  while ($woNp = mysqli_fetch_assoc($woListNameplatePrint)) {
                  ?>
                    <option value="<?= htmlspecialchars($woNp['wo']) ?>" <?= $selectedWONameplate === $woNp['wo'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($woNp['wo']) ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
              <div>
                <label>Batch</label>
                <select name="batch_id_np" onchange="this.form.submit()" <?= $selectedWONameplate === '' ? 'disabled' : '' ?>>
                  <option value=""><?= $selectedWONameplate === '' ? 'Pilih Work Order dulu' : '-- Pilih Batch --' ?></option>
                  <?php
                  if ($selectedWONameplate !== '') {
                      $batchNameplateQuery = "SELECT * FROM nameplate_batch";
                      $safeWoNp = mysqli_real_escape_string($koneksi, $selectedWONameplate);
                      $batchNameplateQuery .= " WHERE wo='$safeWoNp'";
                      $batchNameplateQuery .= " ORDER BY id ASC";
                      $batchNameplateList = mysqli_query($koneksi, $batchNameplateQuery);
                      while ($batchNp = mysqli_fetch_assoc($batchNameplateList)) {
                          $idNp = (int) $batchNp['id'];
                          $selNp = ($selectedBatchIdNameplate === $idNp) ? 'selected' : '';
                          $labelNp = htmlspecialchars($batchNp['nama_batch']);
                          $disabledNp = '';
                          $checkNpPrinted = $koneksi->query("SELECT COUNT(*) AS cnt FROM nameplate_log_print WHERE batch_id=$idNp");
                          $rowNpPrinted = $checkNpPrinted ? $checkNpPrinted->fetch_assoc() : ['cnt' => 0];
                          if ((int) $rowNpPrinted['cnt'] > 0) {
                              $disabledNp = 'disabled';
                              $labelNp .= ' (Sudah di-record)';
                          }
                          echo "<option value='{$idNp}' {$selNp} {$disabledNp}>{$labelNp}</option>";
                      }
                  }
                  ?>
                </select>
              </div>
              <div>
                <label>Tanggal Produksi</label>
                <input type="date" name="prod_date_np" value="<?= htmlspecialchars($productionDateNameplate) ?>" onchange="this.form.submit()">
              </div>
            </div>
          </form>
        </div>

        <?php if ($selectedBatchIdNameplate): ?>
          <div class="card">
            <div class="step-title">
              <span class="icon">2</span>
              Preview Nameplate
            </div>
            <?php if (empty($nameplatePreviewNumbers)): ?>
              <div class="placeholder">Data nomor untuk batch ini tidak ditemukan.</div>
            <?php else: ?>
              <div class="nameplate-preview-grid">
                <?php foreach ($nameplatePreviewNumbers as $npIndex => $npNumber): ?>
                  <?php
                  $npSerialHtml = buildNameplateSerialSpans($npNumber);
	                  $npQrPayload = 'PLN021900005020' . $nameplateYear2 . $npNumber;
	                  ?>
	                  <div class="nameplate-card">
	                    <div class="np-body">
	                      <div class="np-barcode">
	                        <div class="np-meta">
	                          <?= buildHdTextPlaceholder('np-meta-text', 'Produksi : ' . $nameplateProductionLabel) ?>
	                        </div>
	                        <svg id="np-preview-<?= $npIndex ?>"></svg>
	                        <div class="np-number"><?= $npSerialHtml ?></div>
	                      </div>
	                      <div class="np-qr-wrap">
	                        <?= buildHdTextPlaceholder('np-qr-label', 'Garansi 5 Tahun', 'right') ?>
	                        <svg class="np-qr" data-qr="<?= htmlspecialchars($npQrPayload, ENT_QUOTES) ?>" aria-label="QR"></svg>
	                      </div>
	                    </div>
	                  </div>
                <?php endforeach; ?>
              </div>

              <?php if ($isNameplateRecorded): ?>
                <div class="warning-msg">Batch nameplate ini sudah di-record dan tidak bisa di-record ulang.</div>
              <?php else: ?>
	                <form id="nameplatePrintForm" method="POST" style="margin-top:14px;">
	                  <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
	                  <input type="hidden" name="print_nameplate" value="1">
	                  <input type="hidden" name="batch_id_np" value="<?= (int) $selectedBatchIdNameplate ?>">
                  <input type="hidden" name="prod_date_np" value="<?= htmlspecialchars($productionDateNameplate) ?>">
                  <input type="hidden" name="record_nameplate" id="nameplateRecordChoice" value="0">
                  <button class="primary" type="button" id="nameplatePrintBtn">Print Nameplate Barcode</button>
                </form>
                <div id="nameplateConfirmModal" class="modal">
                  <div class="modal-content">
                    <h3>Konfirmasi Record</h3>
                    <p>Record batch ini ke database sebelum cetak?</p>
                    <div class="modal-actions">
                      <button type="button" id="nameplateConfirmYes">Ya</button>
                      <button type="button" id="nameplateConfirmNo">Tidak</button>
                    </div>
                  </div>
                </div>
                <script>
                  (function () {
                    var form = document.getElementById("nameplatePrintForm");
                    var btn = document.getElementById("nameplatePrintBtn");
                    var modal = document.getElementById("nameplateConfirmModal");
                    var recordField = document.getElementById("nameplateRecordChoice");
                    var yesBtn = document.getElementById("nameplateConfirmYes");
                    var noBtn = document.getElementById("nameplateConfirmNo");

                    if (!form || !btn || !modal || !recordField || !yesBtn || !noBtn) {
                      return;
                    }

                    function openModal() {
                      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
                        return false;
                      }
                      modal.style.display = "block";
                      return true;
                    }

                    btn.addEventListener("click", function () {
                      openModal();
                    });

                    form.addEventListener("submit", function (event) {
                      if (form.dataset.confirmed === "1") {
                        return;
                      }
                      event.preventDefault();
                      openModal();
                    });

                    yesBtn.addEventListener("click", function () {
                      recordField.value = "1";
                      form.dataset.confirmed = "1";
                      form.submit();
                    });

                    noBtn.addEventListener("click", function () {
                      recordField.value = "0";
                      form.dataset.confirmed = "1";
                      form.submit();
                    });
                  })();
                </script>
              <?php endif; ?>
	              <p class="np-hint">Nomor ditampilkan dengan format: <strong>XX XXXX XXXX X</strong>. QR memakai isi: <strong>PLN021900005020 + tahun produksi(2 digit) + nomor barcode tanpa spasi</strong>.</p>

              <script>
                (function () {
                  var npPreviewNumbers = <?= json_encode(array_values($nameplatePreviewNumbers)) ?>;
	                  for (var i = 0; i < npPreviewNumbers.length; i++) {
		                    JsBarcode("#np-preview-" + i, String(npPreviewNumbers[i]), {
		                      format: "CODE128",
	                      displayValue: false,
	                      height: 42,
	                      width: 3.0,
		                      margin: 0
		                    });
		                  }
			                  Array.prototype.forEach.call(document.querySelectorAll('svg[id^="np-preview-"]'), function (svg) {
			                    svg.setAttribute("shape-rendering", "crispEdges");
			                    svg.setAttribute("vector-effect", "non-scaling-stroke");
			                  });
			                  if (window.OfflineTextRenderer) {
			                    window.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]");
			                  }
		                  if (window.QRCodeRuntime) {
		                    window.QRCodeRuntime.renderAll(document, ".np-qr[data-qr]");
		                  } else if (window.OfflineQRCode) {
	                    OfflineQRCode.renderAll(".np-qr[data-qr]");
	                  }
                })();
              </script>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($menu === 'custom'): ?>
        <div class="topbar">
          <h1>Custom Print</h1>
          <p>Halaman untuk mencetak barcode atau teks dengan format kustom secara bebas.</p>
        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">1</span>
            Konfigurasi Label Kustom
          </div>
          <form id="customPrintForm" method="POST" style="margin-bottom: 0;">
            <input type="hidden" name="menu" value="custom">
            
            <div style="margin-bottom: 20px;">
              <label for="customLabelType" style="display:block; margin-bottom:8px; font-weight:500;">Pilih Format Layout</label>
              <select id="customLabelType" name="custom_layout" onchange="updateCopyCountConstraint()">
                <option value="pcb">Layout PCB / EV Charger (Grid 3x3, Size 79.95 x 34.03 mm)</option>
                <option value="nameplate">Layout Nameplate (Grid 1x2, Size 97.97 x 16.02 mm)</option>
                <option value="ke_modem">Layout Ke Modem (Grid 1x2 @ 3 Sub-label, Size 97.97 x 16.02 mm)</option>
                <option value="custom_size">Layout Kustom (Ukuran Kustom dalam mm)</option>
              </select>
            </div>

            <div id="customSizeInputs" style="display: none; margin-bottom: 20px; border: 1px solid var(--line-strong); padding: 16px; border-radius: 8px; background: rgba(0,0,0,0.02);">
              <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 12px;">
                <div>
                  <label for="customLabelWidth" style="display:block; margin-bottom:6px; font-weight:500;">Lebar Label (mm)</label>
                  <input type="number" id="customLabelWidth" name="custom_width" value="50" min="5" step="0.1" oninput="updateCustomPreview()" style="width:100%; border:1px solid var(--line-strong); border-radius:6px; padding:8px 12px; background:var(--card);">
                </div>
                <div>
                  <label for="customLabelHeight" style="display:block; margin-bottom:6px; font-weight:500;">Tinggi Label (mm)</label>
                  <input type="number" id="customLabelHeight" name="custom_height" value="25" min="5" step="0.1" oninput="updateCustomPreview()" style="width:100%; border:1px solid var(--line-strong); border-radius:6px; padding:8px 12px; background:var(--card);">
                </div>
              </div>
              <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <div>
                  <label for="customLabelCols" style="display:block; margin-bottom:6px; font-weight:500;">Jumlah Kolom</label>
                  <input type="number" id="customLabelCols" name="custom_cols" value="1" min="1" step="1" oninput="updateCustomPreview()" style="width:100%; border:1px solid var(--line-strong); border-radius:6px; padding:8px 12px; background:var(--card);">
                </div>
                <div>
                  <label for="customLabelRows" style="display:block; margin-bottom:6px; font-weight:500;">Jumlah Baris</label>
                  <input type="number" id="customLabelRows" name="custom_rows" value="1" min="1" step="1" oninput="updateCustomPreview()" style="width:100%; border:1px solid var(--line-strong); border-radius:6px; padding:8px 12px; background:var(--card);">
                </div>
              </div>
            </div>

            <div style="margin-bottom: 20px; display: flex; align-items: center;">
              <input type="checkbox" id="customGenerateBarcode" name="generate_barcode" checked onchange="updateCustomPreview()" style="width: auto; height: auto; margin-right: 8px; cursor: pointer;">
              <label for="customGenerateBarcode" style="margin-bottom: 0; display: inline-block; cursor: pointer; font-weight:500;">Generate Barcode dari teks utama</label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 120px; gap: 16px; margin-bottom: 20px;">
              <div>
                <label for="customTextarea" style="display:block; margin-bottom:8px; font-weight:500;">Daftar Data Label (Satu baris per label)</label>
                <textarea id="customTextarea" name="custom_data" placeholder="Format: Teks Utama | Subtitle (Optional) | QR Payload (Optional - Khusus Nameplate)&#10;&#10;Contoh:&#10;HXE3100001 | HXE310 26-06-2026 Batch 1&#10;HXE3100002 | HXE310 26-06-2026 Batch 1&#10;PLN0219000050202600001 | Produksi : 26 Juni 2026 | PLN0219000050202600001" style="width:100%; height:140px; border-radius:8px; padding:12px; border:1px solid var(--line-strong); font-family:monospace; font-size: 0.95rem; resize:vertical; background-color: var(--card);" oninput="updateCustomPreview()"></textarea>
              </div>
              <div style="display: flex; flex-direction: column;">
                <label for="customCopyCount" style="display:block; margin-bottom:8px; font-weight:500;">Jumlah Copy</label>
                <input type="number" id="customCopyCount" name="copy_count" value="1" min="1" oninput="updateCopyCountConstraint()" style="width:100%; border:1px solid var(--line-strong); border-radius:6px; padding:8px 12px; background:var(--card); font-size:1.1rem; box-sizing:border-box; height: 42px; font-weight: bold; text-align: center;">
                <span id="customCopyHelp" style="display:block; font-size:11px; color:var(--content-muted); margin-top:6px; line-height:1.3;"></span>
              </div>
            </div>

            <button class="primary" type="button" id="customPrintBtn">Print Label Kustom</button>
          </form>
        </div>

        <div class="card" id="customPreviewCard" style="display:none;">
          <div class="step-title">
            <span class="icon">2</span>
            Preview Label Kustom
          </div>
          <div id="customPreviewContainer"></div>
        </div>
      <?php elseif ($menu === 'ev_charger_sn'): ?>
        <?php
        $indonesianMonths = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $currentMonthNum = (int) date('n');
        $currentYear = (int) date('Y');
        ?>
        <div class="topbar">
          <h1>Print SN EV Charge</h1>
          <p>Halaman untuk konfigurasi, generate, preview, dan cetak stiker Serial Number (SN) EV Charge dengan QR Code (Ukuran: 8cm x 2.8cm, Gap: 0.4cm).</p>
        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">1</span>
            Konfigurasi Data Stiker Serial Number
          </div>

          <div style="display: flex; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid var(--content-line); padding-bottom: 12px;">
            <button type="button" id="tabBtnRange" class="primary" style="padding: 8px 16px; font-size: 13px;">Generate Range Otomatis</button>
            <button type="button" id="tabBtnManual" style="padding: 8px 16px; font-size: 13px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); color: var(--content-text); cursor: pointer; border-radius: 6px;">Input / Paste Manual</button>
          </div>

          <!-- Panel 1: Mode Range -->
          <div id="panelRangeMode">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Prefix Baris 1 (Model)</label>
                <input type="text" id="snPrefix1" value="M06012EF" placeholder="M06012EF" style="margin-bottom: 0; font-family: monospace; font-weight: bold;">
              </div>
              <div>
                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Prefix Baris 2 (Kode)</label>
                <input type="text" id="snPrefix2" value="OHDE" placeholder="OHDE" style="margin-bottom: 0; font-family: monospace; font-weight: bold;">
              </div>
              <div>
                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Nomor Awal</label>
                <input type="number" id="snStartNum" value="5" min="0" placeholder="5" style="margin-bottom: 0;">
              </div>
              <div>
                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Nomor Akhir</label>
                <input type="number" id="snEndNum" value="10" min="0" placeholder="10" style="margin-bottom: 0;">
              </div>
              <div>
                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Panjang Digit Counter</label>
                <input type="number" id="snDigits" value="4" min="1" max="10" placeholder="4" style="margin-bottom: 0;">
              </div>
            </div>
            <div style="display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;">
              <button type="button" id="btnApplyRange" style="font-size: 12px; padding: 6px 14px; border-radius: 6px; background: var(--content-button); color: #fff; border: none; cursor: pointer;">&#8635; Generate &amp; Terapkan Range</button>
              <button type="button" id="btnLoadExample" style="font-size: 12px; padding: 6px 14px; border-radius: 6px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">Muat Contoh Foto (0005, 0009, 0010)</button>
            </div>
          </div>

          <!-- Panel 2: Mode Manual Textarea -->
          <div id="panelManualMode" style="display: none; margin-bottom: 14px;">
            <label style="font-size: 12px; margin-bottom: 6px; display: block; font-weight: 500;">Daftar Serial Number (Satu per baris - bisa paste dari Excel):</label>
            <textarea id="snManualTextarea" rows="6" style="width: 100%; border-radius: 8px; padding: 10px 12px; border: 1px solid var(--content-line-strong); font-family: monospace; font-size: 13px; background: var(--content-panel); color: var(--content-text); resize: vertical;" placeholder="M06012EFOHDE0005&#10;M06012EFOHDE0009&#10;M06012EFOHDE0010">M06012EFOHDE0005&#10;M06012EFOHDE0009&#10;M06012EFOHDE0010</textarea>
            <div style="font-size: 11px; color: var(--content-muted); margin-top: 4px;">
              Format otomatis: jika 16 karakter tanpa spasi, otomatis dipecah 8 karakter baris pertama &amp; 8 karakter baris kedua. Bisa juga dipisah spasi (misal: <code>M06012EF OHDE0005</code>).
            </div>
          </div>

          <!-- Pengaturan Cetak & Format Label -->
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; padding: 14px; border-radius: 10px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); margin-bottom: 16px;">
            <div>
              <label style="font-size: 12px; margin-bottom: 4px; display: block;">Bulan Produksi</label>
              <select id="snMonth" style="margin-bottom: 0;">
                <?php foreach ($indonesianMonths as $num => $name): ?>
                  <option value="<?= $name ?>" <?= ($num === $currentMonthNum || $name === 'Agustus') ? ($name === 'Agustus' ? 'selected' : '') : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size: 12px; margin-bottom: 4px; display: block;">Tahun Produksi</label>
              <input type="number" id="snYear" value="<?= $currentYear ?>" min="2020" max="2050" style="margin-bottom: 0;">
            </div>
            <div>
              <label style="font-size: 12px; margin-bottom: 4px; display: block;">Jumlah Copy per SN</label>
              <input type="number" id="snCopies" value="1" min="1" max="50" style="margin-bottom: 0;">
            </div>
            <div>
              <label style="font-size: 12px; margin-bottom: 4px; display: block;">Geser Vertikal (mm)</label>
              <input type="number" id="snOffsetY" value="5" step="0.5" min="-10" max="20" style="margin-bottom: 0;" title="0.5 cm = 5 mm">
            </div>
            <div>
              <label style="font-size: 12px; margin-bottom: 4px; display: block;">Mode Halaman Printer</label>
              <select id="snPrinterMode" style="margin-bottom: 0;">
                <option value="gap_sensor">Thermal Roll (80 x 28 mm, Sensor Gap 4mm)</option>
                <option value="continuous">Continuous Feed (80 x 32 mm include Gap 4mm)</option>
              </select>
            </div>
          </div>

          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <button type="button" id="btnPrintSn" class="primary" style="font-size: 15px; padding: 12px 24px; font-weight: 600;">
              &#128438; Print Stiker SN EV Charge
            </button>
            <div id="snSummaryBadge" style="font-size: 13px; font-weight: 600; color: var(--content-text);">
              Total: <span id="snCountLabel" style="color: #3b82f6;">3</span> Stiker SN (<span id="snSizeLabel">80mm x 28mm, Gap 4mm</span>)
            </div>
          </div>
        </div>

        <!-- Card 2: Live Preview -->
        <div class="card">
          <div class="step-title">
            <span class="icon">2</span>
            Live Preview Stiker SN EV Charge
          </div>
          <p style="margin-top: 0; margin-bottom: 12px; font-size: 13px; color: var(--content-muted);">
            Tampilan persis sesuai stiker fisik yang akan dicetak (Ukuran stiker: <strong>8cm x 2.8cm</strong>, gap antar stiker: <strong>0.4cm</strong>).
          </p>

          <div id="snPreviewScrollArea" style="background: #374151; padding: 24px; border-radius: 12px; overflow-x: auto; display: flex; flex-direction: column; align-items: center; gap: 4mm; min-height: 200px; box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);">
            <!-- Stickers are dynamically injected here -->
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($printScript)) {
          echo $printScript;
      } ?>
      <?php if (!empty($nameplatePrintScript)) {
          echo $nameplatePrintScript;
      } ?>
      <script>
        (function () {
          function closeAllCustomSelects(except) {
            var items = document.querySelectorAll(".custom-select.is-open");
            items.forEach(function (item) {
              if (item !== except) {
                item.classList.remove("is-open");
                var button = item.querySelector(".custom-select-button");
                if (button) {
                  button.setAttribute("aria-expanded", "false");
                }
              }
            });
          }

          function enhanceSelect(select) {
            if (!select || select.dataset.enhancedSelect === "1" || select.multiple) {
              return;
            }

            var wrapper = document.createElement("div");
            wrapper.className = "custom-select";
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);

            var button = document.createElement("button");
            button.type = "button";
            button.className = "custom-select-button";
            button.setAttribute("aria-haspopup", "listbox");
            button.setAttribute("aria-expanded", "false");

            var panel = document.createElement("div");
            panel.className = "custom-select-panel";

            var list = document.createElement("div");
            list.className = "custom-select-list";
            list.setAttribute("role", "listbox");

            panel.appendChild(list);
            wrapper.appendChild(button);
            wrapper.appendChild(panel);
            select.dataset.enhancedSelect = "1";

            function syncFromSelect() {
              var selectedOption = select.options[select.selectedIndex] || select.options[0];
              var selectedText = selectedOption ? selectedOption.text : "Choose an option";
              button.textContent = selectedText;
              button.classList.toggle("is-placeholder", !select.value);
              button.disabled = !!select.disabled;
              list.innerHTML = "";

              Array.prototype.forEach.call(select.options, function (option, index) {
                var item = document.createElement("button");
                item.type = "button";
                item.className = "custom-select-option";
                item.textContent = option.text;
                item.setAttribute("role", "option");

                if (option.disabled) {
                  item.disabled = true;
                  item.classList.add("is-disabled");
                }

                if (index === select.selectedIndex) {
                  item.classList.add("is-selected");
                  item.setAttribute("aria-selected", "true");
                } else {
                  item.setAttribute("aria-selected", "false");
                }

                item.addEventListener("click", function () {
                  if (option.disabled) {
                    return;
                  }

                  var previousValue = select.value;
                  select.selectedIndex = index;
                  syncFromSelect();
                  wrapper.classList.remove("is-open");
                  button.setAttribute("aria-expanded", "false");

                  if (previousValue !== select.value) {
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    select.dispatchEvent(new Event("input", { bubbles: true }));
                  }
                });

                list.appendChild(item);
              });
            }

            button.addEventListener("click", function () {
              var willOpen = !wrapper.classList.contains("is-open");
              closeAllCustomSelects(wrapper);
              wrapper.classList.toggle("is-open", willOpen);
              button.setAttribute("aria-expanded", willOpen ? "true" : "false");
            });

            wrapper.addEventListener("keydown", function (event) {
              if (event.key === "Escape") {
                wrapper.classList.remove("is-open");
                button.setAttribute("aria-expanded", "false");
                button.focus();
              }
            });

            select.addEventListener("change", syncFromSelect);

            if (select.form) {
              select.form.addEventListener("reset", function () {
                window.setTimeout(syncFromSelect, 0);
              });
            }

            syncFromSelect();
          }

          document.querySelectorAll("select").forEach(enhanceSelect);

          document.addEventListener("click", function (event) {
            if (!event.target.closest(".custom-select")) {
              closeAllCustomSelects();
            }
          });
        })();
      </script>
      <script>
        (function () {
          var shell = document.querySelector(".app-shell");
          var toggle = document.getElementById("sidebarToggle");
          if (!shell || !toggle) {
            return;
          }
          var storageKey = "sidebarCollapsedState";
          try {
            if (window.localStorage && window.localStorage.getItem(storageKey) === "1") {
              shell.classList.add("sidebar-collapsed");
              toggle.setAttribute("aria-expanded", "false");
            }
          } catch (err) {
          }
          toggle.addEventListener("click", function () {
            var collapsed = shell.classList.toggle("sidebar-collapsed");
            toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
            try {
              if (window.localStorage) {
                window.localStorage.setItem(storageKey, collapsed ? "1" : "0");
              }
            } catch (err) {
            }
          });
        })();
      </script>
      <script>
        (function () {
          var btn = document.getElementById("themeToggleBtn");
          if (!btn) {
            return;
          }
          var storageKey = "uiThemeMode";
          function applyTheme(theme) {
            var isLight = theme === "light";
            document.body.classList.toggle("light-theme", isLight);
            btn.innerHTML = isLight ? "&#127769; Dark Mode" : "&#9728;&#65039; Light Mode";
            btn.setAttribute("aria-pressed", isLight ? "true" : "false");
          }
          var currentTheme = "dark";
          try {
            if (window.localStorage) {
              var saved = window.localStorage.getItem(storageKey);
              if (saved === "light" || saved === "dark") {
                currentTheme = saved;
              }
            }
          } catch (err) {
          }
          applyTheme(currentTheme);
          btn.addEventListener("click", function () {
            currentTheme = currentTheme === "light" ? "dark" : "light";
            applyTheme(currentTheme);
            try {
              if (window.localStorage) {
                window.localStorage.setItem(storageKey, currentTheme);
              }
            } catch (err) {
            }
          });
        })();
      </script>
    </main>
  </div>

  <script>
    function parseCustomData(textareaValue) {
      var lines = textareaValue.split('\n');
      var result = [];
      
      var copyInput = document.getElementById("customCopyCount");
      var copyCount = copyInput ? parseInt(copyInput.value) : 1;
      if (isNaN(copyCount) || copyCount < 1) {
        copyCount = 1;
      }
      
      var layout = document.getElementById("customLabelType").value;
      if (layout === 'pcb' && copyCount > 9) {
        copyCount = 9;
      }

      if (layout === 'ke_modem' && textareaValue.trim() === '') {
        for (var c = 0; c < copyCount; c++) {
          for (var s = 0; s < 6; s++) {
            result.push({ main: 'KE', subtitle: 'MODEM', qr: '' });
          }
        }
        return result;
      }
      
      for (var i = 0; i < lines.length; i++) {
        var line = lines[i].trim();
        if (line === '') continue;
        var parts = line.split('|');
        var main = parts[0] ? parts[0].trim() : '';
        var subtitle = parts[1] ? parts[1].trim() : '';
        var qr = parts[2] ? parts[2].trim() : '';

        if (layout === 'ke_modem') {
          if (!subtitle && main) {
            var wordParts = main.split(/\s+/);
            if (wordParts.length >= 2) {
              main = wordParts[0];
              subtitle = wordParts.slice(1).join(" ");
            } else {
              subtitle = 'MODEM';
            }
          }
          if (!main) main = 'KE';
          if (!subtitle) subtitle = 'MODEM';
        }
        
        for (var c = 0; c < copyCount; c++) {
          result.push({ main: main, subtitle: subtitle, qr: qr });
        }
      }
      return result;
    }

    function printCustomLabels() {
      var layout = document.getElementById("customLabelType").value;
      var generateBarcode = document.getElementById("customGenerateBarcode").checked;
      var dataText = document.getElementById("customTextarea").value;
      var items = parseCustomData(dataText);
      if (items.length === 0) {
        alert("Masukkan data untuk diprint terlebih dahulu.");
        return;
      }

      var w = window.open('', '_blank');
      w.document.write('<html><head><title>Print Custom Label</title>');
      w.document.write('<style>');
      
      if (layout === 'pcb') {
        w.document.write(`
          @page {
            size: 79.95mm 34.03mm;
            margin: 0;
          }
          body {
            margin: 0;
            padding: 0;
          }
          .page {
            width: 79.95mm;
            height: 34.03mm;
            margin-top: -10px;
            page-break-after: always;
            position: relative;
          }
          .page table {
            width: 100%;
            height: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0 0.5mm;
            margin-top: 0;
          }
          .label {
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
            height: 100%;
            padding-top: 0.2mm;
            transform: translateY(1.2mm);
          }
          .page table tr:first-child .label {
            transform: translateY(2.1mm);
          }
          .label svg {
            width: 23.27mm;
            height: 6.76mm;
            margin-bottom: 0px;
            shape-rendering: crispEdges;
            overflow: visible;
          }
          .pcb-code-text {
            width: 100%;
            height: 9px;
            margin-top: -3.4px;
            font-family: Calibri, Arial, sans-serif;
            font-size: 5.6pt;
            font-weight: 400;
            letter-spacing: 0.01em;
            display: block;
          }
          .pcb-custom-text {
            width: 100%;
            height: 7px;
            margin-top: -0.4px;
            font-family: Calibri, Arial, sans-serif;
            font-size: 6.8px;
            font-weight: 400;
            letter-spacing: 0.01em;
            display: block;
          }
          .pcb-code-text svg,
          .pcb-custom-text svg {
            width: 100%;
            height: 100%;
            display: block;
            overflow: visible;
          }
          .page-number {
            position: absolute;
            left: 81.22mm;
            top: 2.8cm;
            font-family: Arial, sans-serif;
            font-size: 8pt;
            color: #333;
            white-space: nowrap;
          }
          .arrow {
            color: #0b1f3a;
            font-weight: bold;
          }
          @media print {
            .page-number {
              display: none;
            }
          }
          td:empty {
            height: 1.133cm;
          }
          /* Centering for no-barcode text-only labels */
          .label.no-barcode {
            vertical-align: middle !important;
            padding-top: 0 !important;
            transform: none !important;
          }
          .page table tr:first-child .label.no-barcode {
            transform: none !important;
          }
          .label.no-barcode .pcb-code-text {
            height: 14px;
            font-size: 9pt;
            margin-top: 0;
            margin-bottom: 2px;
          }
          .label.no-barcode .pcb-custom-text {
            height: 12px;
            font-size: 8pt;
            margin-top: 0;
          }
        `);
      } else if (layout === 'custom_size') {
        var widthVal = parseFloat(document.getElementById("customLabelWidth").value) || 50;
        var heightVal = parseFloat(document.getElementById("customLabelHeight").value) || 25;
        var colsVal = parseInt(document.getElementById("customLabelCols").value) || 1;
        var rowsVal = parseInt(document.getElementById("customLabelRows").value) || 1;
        var totalPageWidth = widthVal * colsVal;
        var totalPageHeight = heightVal * rowsVal;
        
        w.document.write(`
          @page {
            size: ${totalPageWidth}mm ${totalPageHeight}mm;
            margin: 0 !important;
          }
          * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          html, body {
            margin: 0;
            padding: 0;
          }
          body {
            font-family: Arial, sans-serif;
            color: #000;
            background: #55657c;
            display: block;
          }
          .print-page {
            width: ${totalPageWidth}mm;
            height: ${totalPageHeight}mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            position: relative;
          }
          .print-page + .print-page {
            margin-top: 0;
          }
          .page-cut {
            display: none;
            width: 0;
            height: 0;
            margin: 0;
            padding: 0;
          }
          .page-number {
            position: absolute;
            left: calc(100% + 6px);
            top: 50%;
            transform: translateY(-50%);
            font-family: Arial, sans-serif;
            font-size: 8pt;
            color: #333;
            white-space: nowrap;
            z-index: 5;
          }
          .page-number .arrow {
            color: #0b1f3a;
            font-weight: bold;
          }
          .sheet {
            width: ${totalPageWidth}mm;
            height: ${totalPageHeight}mm;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(${colsVal}, ${widthVal}mm);
            grid-template-rows: repeat(${rowsVal}, ${heightVal}mm);
            background: #fff;
            overflow: hidden;
          }
          .sticker {
            width: ${widthVal}mm;
            height: ${heightVal}mm;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            box-sizing: border-box;
            border: 0;
          }
          .sticker svg {
            max-width: 100%;
            height: auto;
            max-height: 45%;
          }
          .sticker .main-text {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            margin-top: 2mm;
            width: 100%;
          }
          .sticker .sub-text {
            font-size: 9pt;
            text-align: center;
            color: #555;
            margin-top: 1mm;
            width: 100%;
          }
          @media screen {
            html {
              background: #55657c;
            }
            body {
              display: block;
              width: fit-content;
              min-width: calc(${totalPageWidth}mm + 96px);
              padding: 8px 72px 8px 8px;
            }
            .print-page + .print-page {
              margin-top: 6px;
            }
            .page-cut {
              display: none !important;
            }
            .print-page {
              overflow: visible;
            }
            .sheet {
              box-shadow: 0 8px 18px rgba(0, 0, 0, 0.2);
            }
          }
          @media print {
            html,
            body {
              width: ${totalPageWidth}mm !important;
              min-width: ${totalPageWidth}mm !important;
              max-width: ${totalPageWidth}mm !important;
              background: #fff;
            }
            body {
              display: block !important;
              padding: 0 !important;
              line-height: 1 !important;
            }
            .print-page {
              width: ${totalPageWidth}mm !important;
              height: ${totalPageHeight}mm !important;
              margin: 0 !important;
              break-inside: avoid;
              page-break-inside: avoid;
            }
            .page-cut {
              display: block !important;
              width: 0 !important;
              height: 0 !important;
              margin: 0 !important;
              padding: 0 !important;
              break-before: page;
              page-break-before: always;
            }
            .sheet {
              height: ${totalPageHeight}mm;
              min-height: ${totalPageHeight}mm;
              margin: 0 !important;
              box-shadow: none;
              break-after: auto;
              page-break-after: auto;
            }
            .print-page + .print-page {
              margin-top: 0 !important;
            }
            .page-number {
              display: none !important;
            }
          }
          /* Centering for no-barcode text-only labels */
          .sticker.no-barcode .main-text {
            margin-top: 0 !important;
            font-size: 14pt;
          }
          .sticker.no-barcode .sub-text {
            margin-top: 1.5mm;
            font-size: 11pt;
          }
        `);
      } else if (layout === 'ke_modem') {
        w.document.write(`
          @page {
            size: 97.97mm 16.02mm;
            margin: 0 !important;
          }
          * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          html, body {
            margin: 0;
            padding: 0;
          }
          body {
            font-family: Arial, sans-serif;
            color: #000;
            background: #55657c;
            display: block;
          }
          .print-page {
            width: 97.97mm;
            height: 16.02mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            position: relative;
          }
          .print-page + .print-page {
            margin-top: 0;
          }
          .page-cut {
            display: none;
            width: 0;
            height: 0;
            margin: 0;
            padding: 0;
          }
          .page-number {
            position: absolute;
            left: calc(100% + 6px);
            top: 50%;
            transform: translateY(-50%);
            font-family: Arial, sans-serif;
            font-size: 8pt;
            color: #333;
            white-space: nowrap;
            z-index: 5;
          }
          .page-number .arrow {
            color: #0b1f3a;
            font-weight: bold;
          }
          .sheet {
            width: 97.97mm;
            margin: 0;
            padding: 0 2mm;
            box-sizing: border-box;
            display: block;
            height: 16.02mm;
            min-height: 16.02mm;
            background: #fff;
            overflow: hidden;
            white-space: nowrap;
            font-size: 0;
          }
          .sheet + .sheet {
            margin-top: 0;
          }
          .km-group {
            display: inline-block;
            vertical-align: top;
            width: 45.05mm;
            height: 13.01mm;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            border-left: 1px dashed #000;
            border-right: 1px dashed #000;
            overflow: hidden;
            font-size: 0;
            box-sizing: border-box;
          }
          .sheet .km-group:first-child {
            transform: translate(1.5mm, 0.3mm);
          }
          .sheet .km-group + .km-group {
            margin-left: 4mm;
            transform: translate(3mm, 0.3mm);
          }
          .km-sublabel {
            display: inline-block;
            vertical-align: top;
            width: 14.3mm;
            height: 100%;
            box-sizing: border-box;
            text-align: center;
            font-size: 10px;
            position: relative;
            padding: 0.5mm 0;
          }
          .km-sublabel:not(:last-child) {
            border-right: 1px solid #000;
          }
          .km-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            width: 100%;
          }
          .km-line1, .km-line2 {
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.1;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            text-align: center;
          }
          @media screen {
            html {
              background: #55657c;
            }
            body {
              display: block;
              width: fit-content;
              min-width: calc(97.97mm + 96px);
              padding: 8px 72px 8px 8px;
            }
            .print-page + .print-page {
              margin-top: 6px;
            }
            .page-cut {
              display: none !important;
            }
            .print-page {
              overflow: visible;
            }
            .sheet {
              box-shadow: 0 8px 18px rgba(0, 0, 0, 0.2);
            }
          }
          @media print {
            html, body {
              width: 97.97mm !important;
              min-width: 97.97mm !important;
              max-width: 97.97mm !important;
              background: #fff;
            }
            body {
              display: block !important;
              padding: 0 !important;
              line-height: 1 !important;
              margin: 0 0 0 -2.5mm !important;
            }
            .print-page {
              width: 97.97mm !important;
              height: 16.02mm !important;
              margin: 0 !important;
              break-inside: avoid;
              page-break-inside: avoid;
            }
            .page-cut {
              display: block !important;
              width: 0 !important;
              height: 0 !important;
              margin: 0 !important;
              padding: 0 !important;
              break-before: page;
              page-break-before: always;
            }
            .sheet {
              height: 16.02mm;
              min-height: 16.02mm;
              margin: 0 !important;
              box-shadow: none;
              break-after: auto;
              page-break-after: auto;
            }
            .sheet .km-group:first-child {
              transform: translate(1.5mm, 0.3mm) !important;
            }
            .sheet .km-group + .km-group {
              transform: translate(3mm, 0.3mm) !important;
            }
            .km-group {
              height: 13mm;
            }
            .print-page + .print-page {
              margin-top: 0 !important;
            }
            .page-number {
              display: none !important;
            }
          }
        `);
      } else {
        w.document.write(`
          @page {
            size: 97.97mm 16.02mm;
            margin: 0 !important;
          }
          * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          html, body {
            margin: 0;
            padding: 0;
          }
          body {
            font-family: Arial, sans-serif;
            color: #000;
            background: #55657c;
            display: block;
          }
          .print-page {
            width: 97.97mm;
            height: 16.02mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            position: relative;
          }
          .print-page + .print-page {
            margin-top: 0;
          }
          .page-cut {
            display: none;
            width: 0;
            height: 0;
            margin: 0;
            padding: 0;
          }
          .page-number {
            position: absolute;
            left: calc(100% + 6px);
            top: 50%;
            transform: translateY(-50%);
            font-family: Arial, sans-serif;
            font-size: 8pt;
            color: #333;
            white-space: nowrap;
            z-index: 5;
          }
          .page-number .arrow {
            color: #0b1f3a;
            font-weight: bold;
          }
          .sheet {
            width: 97.97mm;
            margin: 0;
            padding: 0 2mm;
            box-sizing: border-box;
            display: block;
            height: 16.02mm;
            min-height: 16.02mm;
            background: #fff;
            overflow: hidden;
            white-space: nowrap;
            font-size: 0;
          }
          .sheet + .sheet {
            margin-top: 0;
          }
          .sticker {
            display: inline-block;
            vertical-align: top;
            width: 45.05mm;
            height: 13.01mm;
            border: 0;
            padding: 0.3mm 0.6mm 0;
            overflow: hidden;
            font-size: 12px;
          }
          .sheet .sticker:first-child {
            transform: translate(0mm, 0.3mm);
          }
          .sheet .sticker + .sticker {
            margin-left: 4mm;
            transform: translate(1mm, 0.3mm);
          }
          .sticker-layout {
            width: 100%;
            height: 100%;
            display: table;
            table-layout: fixed;
            border-collapse: collapse;
            transform: translateY(-0.8mm);
          }
          .barcode-wrap {
            display: table-cell;
            vertical-align: top;
            min-width: 0;
            width: 100%;
            transform: translate(1mm, -0.3mm);
          }
          .prod-line {
            width: 100%;
            height: 2.5mm;
            font-size: 5.5pt;
            font-family: "Century Gothic", Arial, sans-serif;
            font-weight: 420;
            letter-spacing: 0.01em;
            line-height: 1;
            margin: 0 0 0.8mm 0;
            white-space: nowrap;
            transform: translate(-0.28mm, 2.2mm);
          }
          .prod-line svg,
          .qr-label svg {
            display: block;
            width: 100%;
            height: 100%;
            overflow: visible;
          }
          .barcode-wrap > svg {
            width: 30mm;
            max-width: 30mm;
            height: 5.26mm;
            display: block;
            margin: 1.4mm 0 0 0;
            shape-rendering: crispEdges;
          }
          .serial {
            margin-top: 1.1mm;
            font-size: 9.4pt;
            width: 30mm;
            max-width: 30mm;
            display: flex;
            justify-content: center;
            gap: 1.1mm;
            align-items: baseline;
            font-family: "Century Gothic", Arial, sans-serif;
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1;
            transform: translateY(-0.9mm);
          }
          .serial span {
            white-space: nowrap;
          }
          .qr-wrap {
            display: table-cell;
            vertical-align: top;
            width: 12.64mm;
            text-align: right;
            transform: translateX(-2.5mm);
          }
          .qr-label {
            display: block;
            position: static;
            width: 13.51mm;
            height: 6.8mm;
            font-size: 18.3pt;
            font-family: Calibri, Arial, sans-serif;
            font-weight: 440;
            letter-spacing: 0.01em;
            line-height: 1;
            white-space: nowrap;
            text-align: right;
            pointer-events: none;
            margin: 0 0 0.4mm auto;
            transform: translate(-0.9mm, -0.4mm);
          }
          .qr {
            width: 8.51mm;
            height: 8.51mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 0.1mm;
            overflow: hidden;
            margin-top: 0;
            margin-left: auto;
            transform: translateY(-3.2mm);
          }
          .qr svg {
            width: 100%;
            height: 100%;
            display: block;
            background: #fff;
          }
          .page-break {
            display: none;
          }
          @media screen {
            html {
              background: #55657c;
            }
            body {
              display: block;
              width: fit-content;
              min-width: calc(97.97mm + 96px);
              padding: 8px 72px 8px 8px;
            }
            .print-page + .print-page {
              margin-top: 6px;
            }
            .page-cut {
              display: none !important;
            }
            .print-page {
              overflow: visible;
            }
            .sheet {
              box-shadow: 0 8px 18px rgba(0, 0, 0, 0.2);
            }
          }
          @media print {
            html,
            body {
              width: 97.97mm !important;
              min-width: 97.97mm !important;
              max-width: 97.97mm !important;
              background: #fff;
            }
            body {
              display: block !important;
              padding: 0 !important;
              line-height: 1 !important;
            }
            body.no-barcode {
              margin: 0 0 0 -2.5mm !important;
            }
            body:not(.no-barcode) {
              margin: 0 0 0 4.5mm !important;
            }
            .print-page {
              width: 97.97mm !important;
              height: 16.02mm !important;
              margin: 0 !important;
              break-inside: avoid;
              page-break-inside: avoid;
            }
            .page-cut {
              display: block !important;
              width: 0 !important;
              height: 0 !important;
              margin: 0 !important;
              padding: 0 !important;
              break-before: page;
              page-break-before: always;
            }
            .sheet {
              height: 16.02mm;
              min-height: 16.02mm;
              margin: 0 !important;
              box-shadow: none;
              break-after: auto;
              page-break-after: auto;
            }
            .sheet .sticker:first-child {
              transform: translate(0mm, 0.3mm) !important;
            }
            .sheet .sticker + .sticker {
              transform: translate(5mm, 0.3mm) !important;
            }
            .sticker {
              height: 13mm;
            }
            .print-page + .print-page {
              margin-top: 0 !important;
            }
            .page-number {
              display: none !important;
            }
          }
          /* Centering for no-barcode text-only labels */
          .sticker.no-barcode.no-qr .sticker-layout {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            height: 100% !important;
            width: 100% !important;
            transform: none !important;
          }
          .sticker.no-barcode.no-qr .barcode-wrap {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            width: 100% !important;
            height: 100% !important;
            transform: none !important;
            padding: 0 !important;
          }
          .sticker.no-barcode.no-qr .prod-line {
            display: block !important;
            width: 100% !important;
            height: auto !important;
            font-size: 8pt !important;
            text-align: center !important;
            margin: 0 !important;
            transform: none !important;
            order: 2;
          }
          .sticker.no-barcode.no-qr .serial {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            width: 100% !important;
            max-width: 100% !important;
            font-size: 13pt !important;
            font-weight: bold !important;
            margin: 0 !important;
            transform: none !important;
            order: 1;
          }
          .sticker.no-barcode.no-qr .serial span {
            white-space: nowrap !important;
          }
          .sticker.no-barcode:not(.no-qr) .barcode-wrap {
            vertical-align: middle !important;
            transform: translate(1mm, 0) !important;
          }
          .sticker.no-barcode:not(.no-qr) .prod-line {
            transform: translate(-0.28mm, 0) !important;
            margin-bottom: 1mm !important;
          }
          .sticker.no-barcode:not(.no-qr) .serial {
            transform: none !important;
          }
        `);
      }
      
      w.document.write('</style></head><body class="' + (generateBarcode ? '' : 'no-barcode') + '" onload="setTimeout(function(){window.print();}, 300);">');

      if (layout === 'pcb') {
        var chunks = [];
        for (var i = 0; i < items.length; i += 9) {
          chunks.push(items.slice(i, i + 9));
        }
        
        chunks.forEach(function(chunk, pageIndex) {
          w.document.write('<div class="page"><table>');
          for (var row = 0; row < 3; row++) {
            w.document.write('<tr>');
            for (var col = 0; col < 3; col++) {
              var idx = row * 3 + col;
              if (idx < chunk.length) {
                var item = chunk[idx];
                var id = "code_" + pageIndex + "_" + idx;
                var tdClasses = ["label"];
                if (!generateBarcode) tdClasses.push("no-barcode");
                w.document.write('<td class="' + tdClasses.join(' ') + '">');
                if (generateBarcode && item.main) {
                  w.document.write('<svg id="' + id + '"></svg>');
                }
                w.document.write('<div class="pcb-code-text hd-text" data-align="center" data-text="' + escapeHtml(item.main) + '"></div>');
                w.document.write('<div class="pcb-custom-text hd-text" data-align="center" data-text="' + escapeHtml(item.subtitle) + '"></div>');
                w.document.write('</td>');
              } else {
                w.document.write('<td></td>');
              }
            }
            w.document.write('</tr>');
          }
          w.document.write('</table>');
          w.document.write('<div class="page-number"><span class="arrow">&larr;</span> Page ke ' + (pageIndex + 1) + '</div>');
          w.document.write('</div>');
        });
        
      } else if (layout === 'custom_size') {
        var widthVal = parseFloat(document.getElementById("customLabelWidth").value) || 50;
        var heightVal = parseFloat(document.getElementById("customLabelHeight").value) || 25;
        var colsVal = parseInt(document.getElementById("customLabelCols").value) || 1;
        var rowsVal = parseInt(document.getElementById("customLabelRows").value) || 1;
        var itemsPerPage = colsVal * rowsVal;

        var pages = [];
        for (var i = 0; i < items.length; i += itemsPerPage) {
          pages.push(items.slice(i, i + itemsPerPage));
        }

        pages.forEach(function(pageItems, pageIndex) {
          w.document.write('<div class="print-page"><div class="sheet">');
          pageItems.forEach(function(item, idx) {
            var svgId = "custom_barcode_" + pageIndex + "_" + idx;
            var stickerClasses = ["sticker"];
            if (!generateBarcode) stickerClasses.push("no-barcode");
            w.document.write('<div class="' + stickerClasses.join(' ') + '">');
            if (generateBarcode && item.main) {
              w.document.write('<svg id="' + svgId + '"></svg>');
            }
            w.document.write('<div class="main-text hd-text" data-align="center" data-text="' + escapeHtml(item.main) + '"></div>');
            w.document.write('<div class="sub-text hd-text" data-align="center" data-text="' + escapeHtml(item.subtitle) + '"></div>');
            w.document.write('</div>');
          });
          w.document.write('</div><div class="page-number"><span class="arrow">&larr;</span> Page ke ' + (pageIndex + 1) + '</div></div>');
          if (pageIndex < pages.length - 1) {
            w.document.write('<div class="page-cut"></div>');
          }
        });

      } else if (layout === 'ke_modem') {
        var pages = [];
        for (var i = 0; i < items.length; i += 6) {
          pages.push(items.slice(i, i + 6));
        }

        pages.forEach(function(pageItems, pageIndex) {
          w.document.write('<div class="print-page"><div class="sheet">');
          
          for (var g = 0; g < 2; g++) {
            w.document.write('<div class="km-group">');
            for (var s = 0; s < 3; s++) {
              var idx = g * 3 + s;
              var item = pageItems[idx] || { main: "KE", subtitle: "MODEM" };
              var line1 = escapeHtml(item.main || "KE");
              var line2 = escapeHtml(item.subtitle || "MODEM");

              w.document.write('<div class="km-sublabel"><div class="km-content">');
              w.document.write('<div class="km-line1">' + line1 + '</div>');
              w.document.write('<div class="km-line2">' + line2 + '</div>');
              w.document.write('</div></div>');
            }
            w.document.write('</div>');
          }

          w.document.write('</div><div class="page-number"><span class="arrow">&larr;</span> Page ke ' + (pageIndex + 1) + '</div></div>');
          if (pageIndex < pages.length - 1) {
            w.document.write('<div class="page-cut"></div>');
          }
        });

      } else {
        var pages = [];
        for (var i = 0; i < items.length; i += 2) {
          pages.push(items.slice(i, i + 2));
        }
        
        pages.forEach(function(pageItems, pageIndex) {
          w.document.write('<div class="print-page"><div class="sheet">');
          for (var col = 0; col < 2; col++) {
            if (col < pageItems.length) {
              var item = pageItems[col];
              var svgId = "np_barcode_" + pageIndex + "_" + col;
              
              var mainParts = item.main.split(/\s+/).filter(Boolean);
              var serialSpansHtml = '';
              mainParts.forEach(function(part) {
                serialSpansHtml += '<span>' + escapeHtml(part) + '</span> ';
              });
              
              var qrLabelHtml = item.qr ? '<div class="qr-label hd-text" data-align="right" data-text="Garansi 5 Tahun"></div>' : '';
              var qrCodeHtml = item.qr ? '<div class="qr"><svg class="np-qr" data-qr="' + escapeHtml(item.qr) + '" aria-label="QR"></svg></div>' : '';
              
              var stickerClasses = ["sticker"];
              if (!generateBarcode) stickerClasses.push("no-barcode");
              if (!item.qr) stickerClasses.push("no-qr");
              w.document.write('<div class="' + stickerClasses.join(' ') + '">');
              w.document.write('<div class="sticker-layout">');
              w.document.write('<div class="barcode-wrap">');
              w.document.write('<div class="prod-line hd-text" data-align="center" data-text="' + escapeHtml(item.subtitle) + '"></div>');
              if (generateBarcode && item.main) {
                w.document.write('<svg id="' + svgId + '"></svg>');
              }
              w.document.write('<div class="serial">' + serialSpansHtml + '</div>');
              w.document.write('</div>');
              w.document.write('<div class="qr-wrap">' + qrLabelHtml + qrCodeHtml + '</div>');
              w.document.write('</div>');
              w.document.write('</div>');
            } else {
              w.document.write('<div class="sticker"></div>');
            }
          }
          w.document.write('</div><div class="page-number"><span class="arrow">&larr;</span> Page ke ' + (pageIndex + 1) + '</div></div>');
          if (pageIndex < pages.length - 1) {
            w.document.write('<div class="page-cut"></div>');
          }
        });
      }

      w.document.write('<script src="JS2/JsBarcode.all.min.js"><\/script>');
      w.document.write('<script src="JS2/qrcode-generator.min.js"><\/script>');
      w.document.write('<script src="JS2/qrcode-offline.js"><\/script>');
      w.document.write('<script src="JS2/text-hd-offline.js"><\/script>');
      w.document.write('<script>');
      w.document.write('(function(){');
      
      if (layout === 'pcb') {
        var chunks = [];
        for (var i = 0; i < items.length; i += 9) {
          chunks.push(items.slice(i, i + 9));
        }
        chunks.forEach(function(chunk, pageIndex) {
          chunk.forEach(function(item, idx) {
            if (generateBarcode && item.main) {
              var id = "code_" + pageIndex + "_" + idx;
              w.document.write('JsBarcode("#' + id + '", "' + escapeJs(item.main) + '", {format:"CODE128", displayValue:false, fontSize:11, height:52, width:3});');
            }
          });
        });
        w.document.write('Array.prototype.forEach.call(document.querySelectorAll("svg[id^=\\\"code_\\\"]"), function(svg) { svg.setAttribute("shape-rendering", "crispEdges"); svg.setAttribute("vector-effect", "non-scaling-stroke"); });');
      } else if (layout === 'custom_size') {
        var widthVal = parseFloat(document.getElementById("customLabelWidth").value) || 50;
        var heightVal = parseFloat(document.getElementById("customLabelHeight").value) || 25;
        var colsVal = parseInt(document.getElementById("customLabelCols").value) || 1;
        var rowsVal = parseInt(document.getElementById("customLabelRows").value) || 1;
        var itemsPerPage = colsVal * rowsVal;

        var pages = [];
        for (var i = 0; i < items.length; i += itemsPerPage) {
          pages.push(items.slice(i, i + itemsPerPage));
        }
        pages.forEach(function(pageItems, pageIndex) {
          pageItems.forEach(function(item, idx) {
            if (generateBarcode && item.main) {
              var svgId = "custom_barcode_" + pageIndex + "_" + idx;
              var bcHeight = Math.max(15, Math.round(heightVal * 1.2));
              w.document.write('JsBarcode("#' + svgId + '", "' + escapeJs(item.main) + '", {format:"CODE128", displayValue:false, height:' + bcHeight + ', width:1.5, margin:0});');
            }
          });
        });
        w.document.write('Array.prototype.forEach.call(document.querySelectorAll("svg[id^=\\\"custom_barcode_\\\"]"), function(svg) { svg.setAttribute("shape-rendering", "crispEdges"); svg.setAttribute("vector-effect", "non-scaling-stroke"); });');
      } else {
        var pages = [];
        for (var i = 0; i < items.length; i += 2) {
          pages.push(items.slice(i, i + 2));
        }
        pages.forEach(function(pageItems, pageIndex) {
          pageItems.forEach(function(item, idx) {
            if (generateBarcode && item.main) {
              var svgId = "np_barcode_" + pageIndex + "_" + idx;
              w.document.write('JsBarcode("#' + svgId + '", "' + escapeJs(item.main) + '", {format:"CODE128", displayValue:false, height:26, width:1.8, margin:0});');
            }
          });
        });
        w.document.write('Array.prototype.forEach.call(document.querySelectorAll("svg[id^=\\\"np_barcode_\\\"]"), function(svg) { svg.setAttribute("shape-rendering", "crispEdges"); svg.setAttribute("vector-effect", "non-scaling-stroke"); });');
      }

      w.document.write('if (window.OfflineTextRenderer && typeof window.OfflineTextRenderer.renderAll === "function") { window.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]"); } else if (window.opener && window.opener.OfflineTextRenderer && typeof window.opener.OfflineTextRenderer.renderAll === "function") { window.opener.OfflineTextRenderer.renderAll(document, ".hd-text[data-text]"); }');
      w.document.write('if (window.QRCodeRuntime && typeof window.QRCodeRuntime.renderAll === "function") { window.QRCodeRuntime.renderAll(document, ".np-qr[data-qr]"); } else if (window.opener && window.opener.QRCodeRuntime && typeof window.opener.QRCodeRuntime.renderAll === "function") { window.opener.QRCodeRuntime.renderAll(document, ".np-qr[data-qr]"); }');
      w.document.write('})();');
      w.document.write('<\/script>');
      w.document.write('</body></html>');
      w.document.close();
    }

    function escapeHtml(text) {
      return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function escapeJs(text) {
      return String(text)
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/'/g, "\\'")
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r');
    }

    function updateCustomPreview() {
      var layout = document.getElementById("customLabelType").value;
      var generateBarcode = document.getElementById("customGenerateBarcode").checked;
      var dataText = document.getElementById("customTextarea").value;
      var items = parseCustomData(dataText);
      var container = document.getElementById("customPreviewContainer");
      var previewCard = document.getElementById("customPreviewCard");

      var sizeInputs = document.getElementById("customSizeInputs");
      if (sizeInputs) {
        sizeInputs.style.display = (layout === 'custom_size') ? 'block' : 'none';
      }

      if (items.length === 0) {
        previewCard.style.display = "none";
        container.innerHTML = "";
        return;
      }

      previewCard.style.display = "block";
      container.innerHTML = "";

      if (layout === 'pcb') {
        var grid = document.createElement("div");
        grid.className = "grid9";
        container.appendChild(grid);

        items.forEach(function(item, idx) {
          var label = document.createElement("div");
          label.className = "label";
          if (!generateBarcode) {
            label.classList.add("no-barcode");
          }
          
          var svgId = "custom_pcb_preview_" + idx;
          if (generateBarcode && item.main) {
            var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            svg.id = svgId;
            label.appendChild(svg);
          }
          
          var mainDiv = document.createElement("div");
          mainDiv.className = "pcb-preview-text hd-text";
          mainDiv.setAttribute("data-align", "center");
          mainDiv.setAttribute("data-text", item.main);
          label.appendChild(mainDiv);

          var subtitleDiv = document.createElement("div");
          subtitleDiv.className = "pcb-preview-text hd-text";
          subtitleDiv.setAttribute("data-align", "center");
          subtitleDiv.setAttribute("data-text", item.subtitle);
          label.appendChild(subtitleDiv);

          grid.appendChild(label);

          if (generateBarcode && item.main) {
            try {
              JsBarcode("#" + svgId, String(item.main), {
                format: "CODE128",
                displayValue: false,
                height: 36,
                width: 1.25,
                margin: 0
              });
            } catch (e) {
              console.error("Barcode error:", e);
            }
          }
        });

      } else if (layout === 'custom_size') {
        var widthVal = parseFloat(document.getElementById("customLabelWidth").value) || 50;
        var heightVal = parseFloat(document.getElementById("customLabelHeight").value) || 25;
        var colsVal = parseInt(document.getElementById("customLabelCols").value) || 1;
        var rowsVal = parseInt(document.getElementById("customLabelRows").value) || 1;

        var grid = document.createElement("div");
        grid.style.display = "grid";
        grid.style.gridTemplateColumns = "repeat(" + colsVal + ", " + widthVal + "mm)";
        grid.style.gap = "4mm";
        grid.style.justifyContent = "center";
        container.appendChild(grid);

        items.forEach(function(item, idx) {
          var label = document.createElement("div");
          label.style.width = widthVal + "mm";
          label.style.height = heightVal + "mm";
          label.style.border = "1px dashed var(--line-strong)";
          label.style.padding = "2mm";
          label.style.display = "flex";
          label.style.flexDirection = "column";
          label.style.justifyContent = "center";
          label.style.alignItems = "center";
          label.style.backgroundColor = "var(--card)";
          label.style.boxSizing = "border-box";
          label.style.overflow = "hidden";
          
          var svgId = "custom_size_preview_" + idx;
          if (generateBarcode && item.main) {
            var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            svg.id = svgId;
            svg.style.maxWidth = "100%";
            svg.style.height = "auto";
            svg.style.maxHeight = "45%";
            label.appendChild(svg);
          }
          
          var mainDiv = document.createElement("div");
          mainDiv.className = "hd-text";
          mainDiv.style.fontSize = "11px";
          mainDiv.style.fontWeight = "bold";
          mainDiv.style.marginTop = "2px";
          mainDiv.style.textAlign = "center";
          mainDiv.setAttribute("data-align", "center");
          mainDiv.setAttribute("data-text", item.main);
          label.appendChild(mainDiv);

          var subtitleDiv = document.createElement("div");
          subtitleDiv.className = "hd-text";
          subtitleDiv.style.fontSize = "9px";
          subtitleDiv.style.color = "var(--content-muted)";
          subtitleDiv.style.marginTop = "1px";
          subtitleDiv.style.textAlign = "center";
          subtitleDiv.setAttribute("data-align", "center");
          subtitleDiv.setAttribute("data-text", item.subtitle);
          label.appendChild(subtitleDiv);

          if (!generateBarcode) {
            mainDiv.style.marginTop = "0";
            mainDiv.style.fontSize = "13px";
            subtitleDiv.style.fontSize = "10px";
            subtitleDiv.style.marginTop = "2px";
          }

          grid.appendChild(label);

          if (generateBarcode && item.main) {
            try {
              var bcHeight = Math.max(15, Math.round(heightVal * 1.2));
              JsBarcode("#" + svgId, String(item.main), {
                format: "CODE128",
                displayValue: false,
                height: bcHeight,
                width: 1.5,
                margin: 0
              });
            } catch (e) {
              console.error("Barcode error:", e);
            }
          }
        });

      } else if (layout === 'ke_modem') {
        var grid = document.createElement("div");
        grid.style.display = "flex";
        grid.style.flexDirection = "column";
        grid.style.gap = "16px";
        container.appendChild(grid);

        var effectiveItems = items.length > 0 ? items : [
          { main: "KE", subtitle: "MODEM" },
          { main: "KE", subtitle: "MODEM" },
          { main: "KE", subtitle: "MODEM" },
          { main: "KE", subtitle: "MODEM" },
          { main: "KE", subtitle: "MODEM" },
          { main: "KE", subtitle: "MODEM" }
        ];

        var pages = [];
        for (var i = 0; i < effectiveItems.length; i += 6) {
          pages.push(effectiveItems.slice(i, i + 6));
        }

        pages.forEach(function(pageItems, pageIdx) {
          var card = document.createElement("div");
          card.style.background = "#ffffff";
          card.style.border = "1px solid var(--line-strong, #ccc)";
          card.style.borderRadius = "12px";
          card.style.padding = "14px";
          card.style.boxShadow = "0 2px 8px rgba(0,0,0,0.05)";

          var labelHeader = document.createElement("div");
          labelHeader.style.fontSize = "12px";
          labelHeader.style.fontWeight = "bold";
          labelHeader.style.color = "var(--content-muted, #666)";
          labelHeader.style.marginBottom = "10px";
          labelHeader.textContent = "Lembar " + (pageIdx + 1) + " (Layout Ke Modem - 6 Sub-label)";
          card.appendChild(labelHeader);

          var sheetFlex = document.createElement("div");
          sheetFlex.style.display = "grid";
          sheetFlex.style.gridTemplateColumns = "repeat(auto-fit, minmax(240px, 1fr))";
          sheetFlex.style.gap = "16px";
          card.appendChild(sheetFlex);

          for (var g = 0; g < 2; g++) {
            var groupDiv = document.createElement("div");
            groupDiv.style.display = "grid";
            groupDiv.style.gridTemplateColumns = "repeat(3, 1fr)";
            groupDiv.style.border = "1.5px dashed #333";
            groupDiv.style.borderRadius = "3px";
            groupDiv.style.overflow = "hidden";
            groupDiv.style.background = "#fff";

            for (var s = 0; s < 3; s++) {
              var idx = g * 3 + s;
              var item = pageItems[idx] || { main: "KE", subtitle: "MODEM" };
              var sub = document.createElement("div");
              sub.style.padding = "12px 4px";
              sub.style.textAlign = "center";
              sub.style.display = "flex";
              sub.style.flexDirection = "column";
              sub.style.justifyContent = "center";
              sub.style.alignItems = "center";
              sub.style.minHeight = "64px";
              if (s < 2) {
                sub.style.borderRight = "1px solid #333";
              }

              var line1 = document.createElement("div");
              line1.style.fontSize = "13px";
              line1.style.fontWeight = "bold";
              line1.style.lineHeight = "1.1";
              line1.style.color = "#000";
              line1.style.fontFamily = "Arial, sans-serif";
              line1.style.letterSpacing = "0.02em";
              line1.textContent = (item.main || "KE").toUpperCase();
              sub.appendChild(line1);

              var line2 = document.createElement("div");
              line2.style.fontSize = "13px";
              line2.style.fontWeight = "bold";
              line2.style.lineHeight = "1.1";
              line2.style.color = "#000";
              line2.style.fontFamily = "Arial, sans-serif";
              line2.style.letterSpacing = "0.02em";
              line2.textContent = (item.subtitle || "MODEM").toUpperCase();
              sub.appendChild(line2);

              groupDiv.appendChild(sub);
            }
            sheetFlex.appendChild(groupDiv);
          }
          grid.appendChild(card);
        });

      } else {
        var grid = document.createElement("div");
        grid.className = "nameplate-preview-grid";
        container.appendChild(grid);

        items.forEach(function(item, idx) {
          var card = document.createElement("div");
          card.className = "nameplate-card";
          if (!generateBarcode) card.classList.add("no-barcode");
          if (!item.qr) card.classList.add("no-qr");
          
          var body = document.createElement("div");
          body.className = "np-body";
          card.appendChild(body);

          var npBarcode = document.createElement("div");
          npBarcode.className = "np-barcode";
          body.appendChild(npBarcode);

          var meta = document.createElement("div");
          meta.className = "np-meta";
          npBarcode.appendChild(meta);

          var metaText = document.createElement("div");
          metaText.className = "np-meta-text hd-text";
          metaText.setAttribute("data-align", "center");
          metaText.setAttribute("data-text", item.subtitle);
          meta.appendChild(metaText);

          var svgId = "custom_np_preview_" + idx;
          if (generateBarcode && item.main) {
            var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            svg.id = svgId;
            npBarcode.appendChild(svg);
          }

          var numDiv = document.createElement("div");
          numDiv.className = "np-number";
          var mainParts = item.main.split(/\s+/).filter(Boolean);
          mainParts.forEach(function(part) {
            var span = document.createElement("span");
            span.textContent = part;
            numDiv.appendChild(span);
            numDiv.appendChild(document.createTextNode(" "));
          });
          npBarcode.appendChild(numDiv);

          var qrWrap = document.createElement("div");
          qrWrap.className = "np-qr-wrap";
          body.appendChild(qrWrap);

          if (item.qr) {
            var qrLabel = document.createElement("div");
            qrLabel.className = "np-qr-label hd-text";
            qrLabel.setAttribute("data-align", "right");
            qrLabel.setAttribute("data-text", "Garansi 5 Tahun");
            qrWrap.appendChild(qrLabel);

            var qrSvg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            qrSvg.setAttribute("class", "np-qr");
            qrSvg.setAttribute("data-qr", item.qr);
            qrWrap.appendChild(qrSvg);
          }

          grid.appendChild(card);

          if (generateBarcode && item.main) {
            try {
              JsBarcode("#" + svgId, String(item.main), {
                format: "CODE128",
                displayValue: false,
                height: 42,
                width: 3.0,
                margin: 0
              });
            } catch (e) {
              console.error("Barcode error:", e);
            }
          }
        });

        if (window.QRCodeRuntime && typeof window.QRCodeRuntime.renderAll === "function") {
          window.QRCodeRuntime.renderAll(container, ".np-qr[data-qr]");
        } else if (window.OfflineQRCode && typeof window.OfflineQRCode.renderAll === "function") {
          window.OfflineQRCode.renderAll(".np-qr[data-qr]");
        }
      }

      if (window.OfflineTextRenderer && typeof window.OfflineTextRenderer.renderAll === "function") {
        window.OfflineTextRenderer.renderAll(container, ".hd-text[data-text]");
      }
    }

    function updateCopyCountConstraint() {
      var layout = document.getElementById("customLabelType").value;
      var copyInput = document.getElementById("customCopyCount");
      var helpText = document.getElementById("customCopyHelp");
      if (!copyInput) return;
      
      var val = parseInt(copyInput.value);
      
      if (layout === 'pcb') {
        copyInput.max = "9";
        if (val > 9) {
          copyInput.value = 9;
          val = 9;
        }
        if (helpText) {
          helpText.textContent = "Maksimal 9 copy.";
        }
      } else if (layout === 'nameplate') {
        copyInput.removeAttribute("max");
        if (helpText) {
          helpText.textContent = "Bebas/unlimited.";
        }
      } else if (layout === 'ke_modem') {
        copyInput.removeAttribute("max");
        if (helpText) {
          helpText.textContent = "1 copy = 6 sub-label (1 lembar nameplate). Bebas/unlimited.";
        }
      } else {
        copyInput.removeAttribute("max");
        if (helpText) {
          helpText.textContent = "Bebas/unlimited.";
        }
      }
      
      updateCustomPreview();
    }

    function initSidebarDropdowns() {
      document.querySelectorAll(".nav-dropdown-toggle").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          var parent = btn.closest(".nav-dropdown");
          if (parent) {
            parent.classList.toggle("open");
          }
        });
      });
    }

    function initEvSnPrint() {
      var tabBtnRange = document.getElementById("tabBtnRange");
      var tabBtnManual = document.getElementById("tabBtnManual");
      var panelRange = document.getElementById("panelRangeMode");
      var panelManual = document.getElementById("panelManualMode");

      var snPrefix1 = document.getElementById("snPrefix1");
      var snPrefix2 = document.getElementById("snPrefix2");
      var snStartNum = document.getElementById("snStartNum");
      var snEndNum = document.getElementById("snEndNum");
      var snDigits = document.getElementById("snDigits");
      var btnApplyRange = document.getElementById("btnApplyRange");
      var btnLoadExample = document.getElementById("btnLoadExample");

      var snManualTextarea = document.getElementById("snManualTextarea");
      var snMonth = document.getElementById("snMonth");
      var snYear = document.getElementById("snYear");
      var snCopies = document.getElementById("snCopies");
      var snOffsetY = document.getElementById("snOffsetY");
      var snPrinterMode = document.getElementById("snPrinterMode");
      var btnPrintSn = document.getElementById("btnPrintSn");

      var snPreviewScrollArea = document.getElementById("snPreviewScrollArea");
      var snCountLabel = document.getElementById("snCountLabel");

      if (!btnPrintSn || !snPreviewScrollArea) {
        return;
      }

      var currentMode = "range";

      if (tabBtnRange && tabBtnManual) {
        tabBtnRange.addEventListener("click", function () {
          currentMode = "range";
          tabBtnRange.className = "primary";
          tabBtnRange.style.background = "";
          tabBtnRange.style.border = "";
          tabBtnRange.style.color = "";
          tabBtnManual.className = "";
          tabBtnManual.style.background = "var(--content-panel-soft)";
          tabBtnManual.style.border = "1px solid var(--content-line-strong)";
          tabBtnManual.style.color = "var(--content-text)";
          if (panelRange) panelRange.style.display = "block";
          if (panelManual) panelManual.style.display = "none";
          updateSnPreview();
        });

        tabBtnManual.addEventListener("click", function () {
          currentMode = "manual";
          tabBtnManual.className = "primary";
          tabBtnManual.style.background = "";
          tabBtnManual.style.border = "";
          tabBtnManual.style.color = "";
          tabBtnRange.className = "";
          tabBtnRange.style.background = "var(--content-panel-soft)";
          tabBtnRange.style.border = "1px solid var(--content-line-strong)";
          tabBtnRange.style.color = "var(--content-text)";
          if (panelRange) panelRange.style.display = "none";
          if (panelManual) panelManual.style.display = "block";
          updateSnPreview();
        });
      }

      function padZero(num, size) {
        var s = String(num);
        while (s.length < size) s = "0" + s;
        return s;
      }

      function parseSnItem(raw) {
        var clean = String(raw || "").trim();
        if (!clean) return null;

        var line1 = "";
        var line2 = "";
        var qrPayload = clean.replace(/[\s|]+/g, "");

        if (/[\s|]/.test(clean)) {
          var parts = clean.split(/[\s|]+/).filter(Boolean);
          if (parts.length >= 2) {
            line1 = parts[0];
            line2 = parts[1];
          } else {
            line1 = parts[0];
            line2 = "";
          }
        } else if (clean.length === 16) {
          line1 = clean.substring(0, 8);
          line2 = clean.substring(8);
        } else if (clean.length > 8) {
          line1 = clean.substring(0, 8);
          line2 = clean.substring(8);
        } else {
          line1 = clean;
          line2 = "";
        }

        return {
          raw: clean,
          line1: line1,
          line2: line2,
          qr: qrPayload
        };
      }

      function getSnItems() {
        var items = [];
        if (currentMode === "range") {
          var p1 = (snPrefix1 ? snPrefix1.value.trim() : "") || "M06012EF";
          var p2 = (snPrefix2 ? snPrefix2.value.trim() : "") || "OHDE";
          var sNum = parseInt(snStartNum ? snStartNum.value : 0, 10);
          var eNum = parseInt(snEndNum ? snEndNum.value : 0, 10);
          var digits = parseInt(snDigits ? snDigits.value : 4, 10) || 4;

          if (isNaN(sNum)) sNum = 0;
          if (isNaN(eNum)) eNum = 0;
          if (sNum > eNum) {
            var temp = sNum; sNum = eNum; eNum = temp;
          }

          var limit = Math.min(eNum - sNum + 1, 500);
          for (var i = 0; i < limit; i++) {
            var counter = sNum + i;
            var counterStr = padZero(counter, digits);
            var fullRaw = p1 + p2 + counterStr;
            items.push({
              raw: fullRaw,
              line1: p1,
              line2: p2 + counterStr,
              qr: fullRaw
            });
          }
        } else {
          var text = snManualTextarea ? snManualTextarea.value : "";
          var lines = text.split("\n");
          for (var j = 0; j < lines.length; j++) {
            var parsed = parseSnItem(lines[j]);
            if (parsed) {
              items.push(parsed);
            }
          }
        }
        return items;
      }

      function updateSnPreview() {
        var items = getSnItems();
        var copies = parseInt(snCopies ? snCopies.value : 1, 10) || 1;
        var month = snMonth ? snMonth.value : "Agustus";
        var year = snYear ? snYear.value : "2026";
        var prodText = "Produksi : " + month + " - " + year;
        var offsetY = parseFloat(snOffsetY ? snOffsetY.value : 5);
        if (isNaN(offsetY)) offsetY = 5;

        if (snCountLabel) {
          snCountLabel.textContent = items.length * copies;
        }

        if (items.length === 0) {
          snPreviewScrollArea.innerHTML = '<div style="color: #9ca3af; font-size: 14px; padding: 30px; text-align: center;">Tidak ada data Serial Number untuk ditampilkan.</div>';
          return;
        }

        var html = '';
        var previewLimit = Math.min(items.length, 50);
        for (var idx = 0; idx < previewLimit; idx++) {
          var item = items[idx];
          for (var c = 0; c < Math.min(copies, 2); c++) {
            html += '<div class="ev-sn-preview-item" style="width: 80mm; height: 28mm; background: #ffffff; border-radius: 3.5mm; padding: 0 3.2mm; display: flex; flex-direction: row; align-items: flex-start; box-sizing: border-box; overflow: hidden; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.3); flex-shrink: 0;">';
            html += '  <div class="ev-sn-content-row" style="display: flex; flex-direction: row; align-items: center; width: 100%; height: 21mm; margin-top: ' + offsetY + 'mm;">';
            html += '    <div class="ev-sn-qr-box" style="width: 20mm; height: 20mm; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">';
            html += '      <svg class="np-qr" data-qr="' + escapeHtml(item.qr) + '" style="width:100%; height:100%; display:block;"></svg>';
            html += '    </div>';
            html += '    <div style="flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; padding-left: 3.5mm; overflow: hidden; box-sizing: border-box;">';
            html += '      <div style="font-family: Arial, sans-serif; font-size: 8pt; font-weight: 700; color: #111; line-height: 1; margin: 0;">Nomor Serial:</div>';
            html += '      <div style="display: flex; flex-direction: column; justify-content: center; line-height: 0.92; margin: 0.4mm 0;">';
            html += '        <div style="font-family: Arial, \'Arial Black\', sans-serif; font-size: 18pt; font-weight: 900; color: #000; letter-spacing: 0.025em; white-space: nowrap;">' + escapeHtml(item.line1) + '</div>';
            html += '        <div style="font-family: Arial, \'Arial Black\', sans-serif; font-size: 18pt; font-weight: 900; color: #000; letter-spacing: 0.025em; white-space: nowrap;">' + escapeHtml(item.line2) + '</div>';
            html += '      </div>';
            html += '      <div style="font-family: Arial, sans-serif; font-size: 8.5pt; font-weight: 700; color: #111; line-height: 1; margin: 0;">' + escapeHtml(prodText) + '</div>';
            html += '    </div>';
            html += '  </div>';
            html += '</div>';
          }
        }
        if (items.length > previewLimit) {
          html += '<div style="color: #cbd5e1; font-size: 12px; margin-top: 8px;">(Menampilkan preview ' + previewLimit + ' dari ' + items.length + ' nomor serial)</div>';
        }

        snPreviewScrollArea.innerHTML = html;

        if (window.QRCodeRuntime && typeof window.QRCodeRuntime.renderAll === "function") {
          window.QRCodeRuntime.renderAll(snPreviewScrollArea, ".np-qr[data-qr]");
        }
      }

      if (btnApplyRange) {
        btnApplyRange.addEventListener("click", updateSnPreview);
      }
      if (btnLoadExample) {
        btnLoadExample.addEventListener("click", function () {
          currentMode = "manual";
          if (tabBtnManual) tabBtnManual.click();
          if (snManualTextarea) {
            snManualTextarea.value = "M06012EFOHDE0005\nM06012EFOHDE0009\nM06012EFOHDE0010";
          }
          if (snMonth) snMonth.value = "Agustus";
          if (snYear) snYear.value = "2026";
          if (snOffsetY) snOffsetY.value = "5";
          updateSnPreview();
        });
      }

      [snPrefix1, snPrefix2, snStartNum, snEndNum, snDigits, snMonth, snYear, snCopies, snOffsetY].forEach(function (el) {
        if (el) el.addEventListener("input", updateSnPreview);
      });
      if (snManualTextarea) {
        snManualTextarea.addEventListener("input", updateSnPreview);
      }

      function printSnLabels() {
        var items = getSnItems();
        if (items.length === 0) {
          alert("Tidak ada nomor serial untuk dicetak.");
          return;
        }

        var copies = parseInt(snCopies ? snCopies.value : 1, 10) || 1;
        var month = snMonth ? snMonth.value : "Agustus";
        var year = snYear ? snYear.value : "2026";
        var prodText = "Produksi : " + month + " - " + year;
        var mode = snPrinterMode ? snPrinterMode.value : "gap_sensor";
        var offsetY = parseFloat(snOffsetY ? snOffsetY.value : 5);
        if (isNaN(offsetY)) offsetY = 5;

        var pageHeight = (mode === "continuous") ? "32mm" : "28mm";
        var totalPrinted = items.length * copies;

        // AJAX log save
        try {
          var fd = new FormData();
          fd.append("action", "save_ev_sn_log");
          fd.append("total_printed", totalPrinted);
          fd.append("start_sn", items[0].raw);
          fd.append("end_sn", items[items.length - 1].raw);
          fd.append("prod_date", prodText);
          fetch(window.location.href, { method: "POST", body: fd });
        } catch (e) {}

        var w = window.open("", "_blank");
        if (!w) {
          alert("Pop-up window diblokir oleh browser. Harap izinkan pop-up untuk mencetak.");
          return;
        }

        w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Print SN EV Charge</title>');
        w.document.write('<style>');
        w.document.write('@page { size: 80mm ' + pageHeight + '; margin: 0; }');
        w.document.write('* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
        w.document.write('html, body { margin: 0; padding: 0; background: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; }');
        w.document.write('@media screen { body { background: #55657c; padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 4mm; } .ev-sn-sticker { box-shadow: 0 4px 12px rgba(0,0,0,0.3); } .screen-btn-bar { position: fixed; top: 12px; right: 12px; z-index: 999; } .screen-btn { background: #2563eb; color: #fff; border: none; padding: 10px 20px; font-size: 14px; font-weight: bold; border-radius: 6px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.25); } }');
        w.document.write('@media print { body { background: #fff !important; width: 80mm; } .ev-sn-sticker { page-break-after: always; break-after: page; box-shadow: none !important; border: none !important; } .screen-btn-bar { display: none !important; } }');
        w.document.write('.ev-sn-sticker { width: 80mm; height: 28mm; background: #ffffff; padding: 0 3.2mm; display: flex; flex-direction: row; align-items: flex-start; overflow: hidden; box-sizing: border-box; }');
        w.document.write('.ev-sn-content-row { display: flex; flex-direction: row; align-items: center; width: 100%; height: 21mm; margin-top: ' + offsetY + 'mm; }');
        w.document.write('.ev-sn-qr { width: 20mm; height: 20mm; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }');
        w.document.write('.ev-sn-qr svg { width: 100%; height: 100%; display: block; }');
        w.document.write('.ev-sn-info { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; padding-left: 3.5mm; overflow: hidden; box-sizing: border-box; }');
        w.document.write('.ev-sn-title { font-size: 8pt; font-weight: 700; color: #111; line-height: 1; margin: 0; }');
        w.document.write('.ev-sn-code-box { display: flex; flex-direction: column; justify-content: center; line-height: 0.92; margin: 0.4mm 0; }');
        w.document.write('.ev-sn-code-line { font-family: Arial, "Arial Black", sans-serif; font-size: 18pt; font-weight: 900; color: #000; letter-spacing: 0.025em; white-space: nowrap; }');
        w.document.write('.ev-sn-date { font-size: 8.5pt; font-weight: 700; color: #111; line-height: 1; margin: 0; }');
        w.document.write('</style></head><body>');
        w.document.write('<div class="screen-btn-bar"><button class="screen-btn" onclick="window.print()">&#128438; Cetak Sekarang (Print)</button></div>');

        for (var i = 0; i < items.length; i++) {
          var it = items[i];
          for (var c = 0; c < copies; c++) {
            w.document.write('<div class="ev-sn-sticker">');
            w.document.write('  <div class="ev-sn-content-row">');
            w.document.write('    <div class="ev-sn-qr">');
            w.document.write('      <svg class="np-qr" data-qr="' + escapeHtml(it.qr) + '"></svg>');
            w.document.write('    </div>');
            w.document.write('    <div class="ev-sn-info">');
            w.document.write('      <div class="ev-sn-title">Nomor Serial:</div>');
            w.document.write('      <div class="ev-sn-code-box">');
            w.document.write('        <div class="ev-sn-code-line">' + escapeHtml(it.line1) + '</div>');
            w.document.write('        <div class="ev-sn-code-line">' + escapeHtml(it.line2) + '</div>');
            w.document.write('      </div>');
            w.document.write('      <div class="ev-sn-date">' + escapeHtml(prodText) + '</div>');
            w.document.write('    </div>');
            w.document.write('  </div>');
            w.document.write('</div>');
          }
        }

        w.document.write('<script src="JS2/qrcode-generator.min.js"><\/script>');
        w.document.write('<script src="JS2/qrcode-offline.js"><\/script>');
        w.document.write('<script>');
        w.document.write('if (window.opener && window.opener.QRCodeRuntime) { window.opener.QRCodeRuntime.renderAll(document, ".np-qr[data-qr]"); }');
        w.document.write('setTimeout(function() { window.print(); }, 250);');
        w.document.write('<\/script>');
        w.document.write('</body></html>');
        w.document.close();
      }

      if (btnPrintSn) {
        btnPrintSn.addEventListener("click", printSnLabels);
      }

      updateSnPreview();
    }

    function initCustomPrint() {
      var printBtn = document.getElementById("customPrintBtn");
      if (printBtn) {
        printBtn.addEventListener("click", printCustomLabels);
      }
      
      var copyInput = document.getElementById("customCopyCount");
      if (copyInput) {
        copyInput.addEventListener("wheel", function(e) {
          e.preventDefault();
          var step = parseInt(copyInput.step) || 1;
          var val = parseInt(copyInput.value) || 1;
          if (e.deltaY < 0) {
            var maxVal = copyInput.max ? parseInt(copyInput.max) : Infinity;
            if (val + step <= maxVal) {
              copyInput.value = val + step;
              copyInput.dispatchEvent(new Event("input"));
            }
          } else {
            var minVal = copyInput.min ? parseInt(copyInput.min) : 1;
            if (val - step >= minVal) {
              copyInput.value = val - step;
              copyInput.dispatchEvent(new Event("input"));
            }
          }
        }, { passive: false });
      }
      
      updateCopyCountConstraint();
    }
    function initAllModules() {
      initSidebarDropdowns();
      initEvSnPrint();
      initCustomPrint();
    }
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initAllModules);
    } else {
      initAllModules();
    }
  </script>
</body>
</html>




