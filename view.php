<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login1.php");
    exit;
}

$allowedMenus = ['pcb', 'ev_charger', 'ev_charger_sn', 'nameplate', 'record', 'custom', 'setting'];
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
    $username = isset($_SESSION['username']) ? $koneksi->real_escape_string($_SESSION['username']) : 'system';

    if ($totalPrinted > 0) {
        $koneksi->query("INSERT INTO ev_charger_sn_log_print (total_printed, start_sn, end_sn, prod_date, printed_at, username)
                         VALUES ($totalPrinted, '$startSn', '$endSn', '$prodDate', NOW(), '$username')");
    }
    echo json_encode(['success' => true]);
    exit;
}

$pcbBatchTable = $isEvChargerMenu ? 'ev_charger_batch' : 'batch';
$pcbNumberTable = $isEvChargerMenu ? 'ev_charger_nomor_barcode' : 'nomor_barcode';

// ====== DOWNLOAD TXT HANDLER ======
if (isset($_GET['action']) && $_GET['action'] === 'download_wo_txt') {
    $downloadWo = trim((string) ($_GET['wo'] ?? ''));
    if ($downloadWo !== '') {
        $safeWo = $koneksi->real_escape_string($downloadWo);
        $res = $koneksi->query("SELECT nomor FROM {$pcbNumberTable} WHERE wo='$safeWo' ORDER BY id ASC");
        if ($res && $res->num_rows > 0) {
            $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $downloadWo) . '.txt';
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            while ($row = $res->fetch_assoc()) {
                echo $row['nomor'] . "\r\n";
            }
            exit;
        }
    }
}

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
$koneksi->query(
    "CREATE TABLE IF NOT EXISTS type_meter_option (
        id INT AUTO_INCREMENT PRIMARY KEY,
        option_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// ====== UPGRADE SCHEMA ======
$userColExists = $koneksi->query("SHOW COLUMNS FROM `users` LIKE 'plain_password'");
if ($userColExists && $userColExists->num_rows === 0) {
    $koneksi->query("ALTER TABLE `users` ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL");
}
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

function saveEvChargerTypeOptions($filePath, array $options)
{
    $cleanOptions = [];
    foreach ($options as $option) {
        $optionName = trim((string) $option);
        if ($optionName !== '') {
            $cleanOptions[$optionName] = true;
        }
    }

    $finalOptions = array_keys($cleanOptions);
    natcasesort($finalOptions);
    $payload = json_encode(array_values($finalOptions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $payload !== false && file_put_contents($filePath, $payload) !== false;
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
    $hasDbRows = false;
    while ($res && $row = $res->fetch_assoc()) {
        $hasDbRows = true;
        $optionName = trim((string) $row['option_name']);
        if ($optionName !== '') {
            $optionsMap[$optionName] = true;
        }
    }

    if (empty($optionsMap)) {
        foreach ($defaultOptions as $def) {
            $optionsMap[$def] = true;
            $safe = $koneksi->real_escape_string($def);
            $koneksi->query("INSERT IGNORE INTO type_meter_option (option_name) VALUES ('$safe')");
        }
        $payload = json_encode($defaultOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($payload !== false) {
            @file_put_contents($filePath, $payload);
        }
    } elseif (!$hasDbRows) {
        foreach (array_keys($optionsMap) as $opt) {
            $safe = $koneksi->real_escape_string($opt);
            $koneksi->query("INSERT IGNORE INTO type_meter_option (option_name) VALUES ('$safe')");
        }
    }

    $options = array_keys($optionsMap);
    natcasesort($options);
    return array_values($options);
}

function saveTypeMeterOptions($filePath, array $options)
{
    $cleanOptions = [];
    foreach ($options as $option) {
        $optionName = trim((string) $option);
        if ($optionName !== '') {
            $cleanOptions[$optionName] = true;
        }
    }

    $finalOptions = array_keys($cleanOptions);
    natcasesort($finalOptions);
    $payload = json_encode(array_values($finalOptions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $payload !== false && file_put_contents($filePath, $payload) !== false;
}

$typeMeterOptionsFile = __DIR__ . DIRECTORY_SEPARATOR . 'type_meter_options.json';

// Handle setting actions
if ($menu === 'setting' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1. Tambah Akun
    if (isset($_POST['add_user'])) {
        $newUsername = trim((string) ($_POST['new_username'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newRole = in_array($_POST['new_role'] ?? '', ['admin', 'user'], true) ? $_POST['new_role'] : 'user';

        if ($newUsername === '' || $newPassword === '') {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Username dan password tidak boleh kosong.'];
        } else {
            $safeUsername = $koneksi->real_escape_string($newUsername);
            $checkUser = $koneksi->query("SELECT id FROM users WHERE username = '$safeUsername'");
            if ($checkUser && $checkUser->num_rows > 0) {
                $_SESSION['setting_msg'] = ['type' => 'error', 'text' => "Username '$newUsername' sudah terdaftar! Gunakan username lain."];
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $safePlain = $koneksi->real_escape_string($newPassword);
                $ins = $koneksi->query("INSERT INTO users (username, password, role, plain_password) VALUES ('$safeUsername', '$hash', '$newRole', '$safePlain')");
                if ($ins) {
                    $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Akun '$newUsername' berhasil ditambahkan."];
                } else {
                    $_SESSION['setting_msg'] = ['type' => 'error', 'text' => "Gagal menambahkan akun: " . $koneksi->error];
                }
            }
        }
        header("Location: view.php?menu=setting");
        exit;
    }

    // 2. Edit Akun
    if (isset($_POST['edit_user'])) {
        $editId = (int) ($_POST['user_id'] ?? 0);
        $editUsername = trim((string) ($_POST['edit_username'] ?? ''));
        $editPassword = (string) ($_POST['edit_password'] ?? '');
        $editRole = in_array($_POST['edit_role'] ?? '', ['admin', 'user'], true) ? $_POST['edit_role'] : 'user';

        if ($editId <= 0 || $editUsername === '') {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Data akun tidak valid.'];
        } else {
            $safeUsername = $koneksi->real_escape_string($editUsername);
            $checkExisting = $koneksi->query("SELECT * FROM users WHERE id = $editId");
            $currUser = $checkExisting ? $checkExisting->fetch_assoc() : null;

            if (!$currUser) {
                $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Akun tidak ditemukan.'];
            } else {
                $checkDup = $koneksi->query("SELECT id FROM users WHERE username = '$safeUsername' AND id != $editId");
                if ($checkDup && $checkDup->num_rows > 0) {
                    $_SESSION['setting_msg'] = ['type' => 'error', 'text' => "Username '$editUsername' sudah digunakan oleh akun lain."];
                } else {
                    $canChangeRole = true;
                    if ($currUser['role'] === 'admin' && $editRole !== 'admin') {
                        $adminCountRes = $koneksi->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND id != $editId");
                        $adminCountRow = $adminCountRes ? $adminCountRes->fetch_assoc() : ['total' => 0];
                        if ((int) $adminCountRow['total'] === 0) {
                            $canChangeRole = false;
                            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Tidak dapat mengubah role ke user karena ini adalah satu-satunya akun admin yang tersisa.'];
                        }
                    }

                    if ($canChangeRole) {
                        if ($editPassword !== '') {
                            $hash = password_hash($editPassword, PASSWORD_DEFAULT);
                            $safePlain = $koneksi->real_escape_string($editPassword);
                            $upd = $koneksi->query("UPDATE users SET username = '$safeUsername', password = '$hash', role = '$editRole', plain_password = '$safePlain' WHERE id = $editId");
                        } else {
                            $upd = $koneksi->query("UPDATE users SET username = '$safeUsername', role = '$editRole' WHERE id = $editId");
                        }

                        if ($upd) {
                            if ($currUser['username'] === $_SESSION['username']) {
                                $_SESSION['username'] = $editUsername;
                                $_SESSION['role'] = $editRole;
                            }
                            $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Akun '$editUsername' berhasil diperbarui."];
                        } else {
                            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => "Gagal memperbarui akun: " . $koneksi->error];
                        }
                    }
                }
            }
        }
        header("Location: view.php?menu=setting");
        exit;
    }

    // 3. Hapus Akun
    if (isset($_POST['delete_user'])) {
        $delId = (int) ($_POST['user_id'] ?? 0);
        $checkExisting = $koneksi->query("SELECT * FROM users WHERE id = $delId");
        $targetUser = $checkExisting ? $checkExisting->fetch_assoc() : null;

        if (!$targetUser) {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Akun tidak ditemukan.'];
        } elseif ($targetUser['username'] === $_SESSION['username']) {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Anda tidak dapat menghapus akun yang sedang Anda gunakan untuk login saat ini.'];
        } elseif ($targetUser['role'] === 'admin') {
            $adminCountRes = $koneksi->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND id != $delId");
            $adminCountRow = $adminCountRes ? $adminCountRes->fetch_assoc() : ['total' => 0];
            if ((int) $adminCountRow['total'] === 0) {
                $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Tidak dapat menghapus satu-satunya akun admin yang tersisa.'];
            } else {
                $koneksi->query("DELETE FROM users WHERE id = $delId");
                $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Akun '{$targetUser['username']}' berhasil dihapus."];
            }
        } else {
            $koneksi->query("DELETE FROM users WHERE id = $delId");
            $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Akun '{$targetUser['username']}' berhasil dihapus."];
        }
        header("Location: view.php?menu=setting");
        exit;
    }

    // 4. Tambah Type Meter
    if (isset($_POST['add_type_meter'])) {
        $typeMeterName = trim((string) ($_POST['type_meter_name'] ?? ''));
        if ($typeMeterName === '') {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Nama Type Meter tidak boleh kosong.'];
        } else {
            $currentOptions = loadTypeMeterOptions($koneksi, $typeMeterOptionsFile);
            $alreadyExists = false;
            foreach ($currentOptions as $existingOpt) {
                if (strcasecmp($existingOpt, $typeMeterName) === 0) {
                    $alreadyExists = true;
                    break;
                }
            }

            if ($alreadyExists) {
                $_SESSION['setting_msg'] = ['type' => 'error', 'text' => "Type Meter '$typeMeterName' sudah ada dalam daftar."];
            } else {
                $currentOptions[] = $typeMeterName;
                saveTypeMeterOptions($typeMeterOptionsFile, $currentOptions);
                $safeName = $koneksi->real_escape_string($typeMeterName);
                $koneksi->query("INSERT IGNORE INTO type_meter_option (option_name) VALUES ('$safeName')");
                $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Type Meter '$typeMeterName' berhasil ditambahkan."];
            }
        }
        header("Location: view.php?menu=setting");
        exit;
    }

    // 5. Hapus Type Meter
    if (isset($_POST['delete_type_meter'])) {
        $typeMeterName = trim((string) ($_POST['type_meter_name'] ?? ''));
        if ($typeMeterName === '') {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Nama Type Meter tidak valid.'];
        } else {
            $currentOptions = loadTypeMeterOptions($koneksi, $typeMeterOptionsFile);
            $filtered = [];
            foreach ($currentOptions as $existingOpt) {
                if (strcasecmp($existingOpt, $typeMeterName) !== 0) {
                    $filtered[] = $existingOpt;
                }
            }
            saveTypeMeterOptions($typeMeterOptionsFile, $filtered);
            $safeName = $koneksi->real_escape_string($typeMeterName);
            $koneksi->query("DELETE FROM type_meter_option WHERE option_name = '$safeName'");
            $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Type Meter '$typeMeterName' berhasil dihapus."];
        }
        header("Location: view.php?menu=setting");
        exit;
    }

    // 6. Tambah Custom Text EV Charger
    if (isset($_POST['add_ev_type_option'])) {
        $evTypeOptionName = trim((string) ($_POST['ev_type_option_name'] ?? ''));
        if ($evTypeOptionName === '') {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Nama Custom Text EV Charger tidak boleh kosong.'];
        } else {
            $currentEvOptions = loadEvChargerTypeOptions($koneksi, $evTypeOptionsFile);
            $alreadyExists = false;
            foreach ($currentEvOptions as $existingOpt) {
                if (strcasecmp($existingOpt, $evTypeOptionName) === 0) {
                    $alreadyExists = true;
                    break;
                }
            }

            if ($alreadyExists) {
                $_SESSION['setting_msg'] = ['type' => 'error', 'text' => "Custom Text EV Charger '$evTypeOptionName' sudah ada dalam daftar."];
            } else {
                $currentEvOptions[] = $evTypeOptionName;
                saveEvChargerTypeOptions($evTypeOptionsFile, $currentEvOptions);
                $safeName = $koneksi->real_escape_string($evTypeOptionName);
                $koneksi->query("INSERT IGNORE INTO ev_charger_type_option (option_name) VALUES ('$safeName')");
                $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Custom Text EV Charger '$evTypeOptionName' berhasil ditambahkan."];
            }
        }
        header("Location: view.php?menu=setting");
        exit;
    }

    // 7. Hapus Custom Text EV Charger
    if (isset($_POST['delete_ev_type_option'])) {
        $evTypeOptionName = trim((string) ($_POST['ev_type_option_name'] ?? ''));
        if ($evTypeOptionName === '') {
            $_SESSION['setting_msg'] = ['type' => 'error', 'text' => 'Nama Custom Text EV Charger tidak valid.'];
        } else {
            $currentEvOptions = loadEvChargerTypeOptions($koneksi, $evTypeOptionsFile);
            $filtered = [];
            foreach ($currentEvOptions as $existingOpt) {
                if (strcasecmp(trim($existingOpt), trim($evTypeOptionName)) !== 0) {
                    $filtered[] = $existingOpt;
                }
            }
            saveEvChargerTypeOptions($evTypeOptionsFile, $filtered);
            $safeName = $koneksi->real_escape_string($evTypeOptionName);
            $koneksi->query("DELETE FROM ev_charger_type_option WHERE LOWER(TRIM(option_name)) = LOWER(TRIM('$safeName'))");
            $_SESSION['setting_msg'] = ['type' => 'success', 'text' => "Custom Text EV Charger '$evTypeOptionName' berhasil dihapus."];
        }
        header("Location: view.php?menu=setting");
        exit;
    }
}

$typeMeterOptions = loadTypeMeterOptions($koneksi, $typeMeterOptionsFile);
$evTypeOptions = loadEvChargerTypeOptions($koneksi, $evTypeOptionsFile);
$settingMsg = $_SESSION['setting_msg'] ?? null;
unset($_SESSION['setting_msg']);

// Ambil daftar WO unik dari batch aktif
$woList = mysqli_query($koneksi, "SELECT DISTINCT wo FROM {$pcbBatchTable} ORDER BY wo ASC");
$selectedWO = isset($_REQUEST['wo']) ? (string) $_REQUEST['wo'] : '';
$selectedWONameplate = isset($_GET['wo_np']) ? $_GET['wo_np'] : '';
$selectedBatchIdNameplate = isset($_GET['batch_id_np']) && $_GET['batch_id_np'] !== '' ? (int) $_GET['batch_id_np'] : null;
$productionDateNameplate = isset($_REQUEST['prod_date_np']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_REQUEST['prod_date_np'])
    ? $_REQUEST['prod_date_np']
    : date('Y-m-d');
$recordType = isset($_GET['record_type']) ? $_GET['record_type'] : 'pcb';
if (!in_array($recordType, ['pcb', 'nameplate', 'ev_charger'], true)) {
    $recordType = 'pcb';
}
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

function readNumbersFromXlsxFile($filePath)
{
    $numbers = [];
    if (!class_exists('ZipArchive')) {
        return $numbers;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return $numbers;
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sharedDoc = @simplexml_load_string($sharedXml);
        if ($sharedDoc) {
            $siNodes = $sharedDoc->xpath('//*[local-name()="si"]');
            if ($siNodes) {
                foreach ($siNodes as $si) {
                    $textNodes = $si->xpath('.//*[local-name()="t"]');
                    $textValue = '';
                    if ($textNodes) {
                        foreach ($textNodes as $textNode) {
                            $textValue .= (string) $textNode;
                        }
                    }
                    $sharedStrings[] = trim($textValue);
                }
            }
        }
    }

    $sheetFiles = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('/^xl\/worksheets\/sheet\d+\.xml$/', $name)) {
            $sheetFiles[] = $name;
        }
    }
    sort($sheetFiles, SORT_NATURAL);

    foreach ($sheetFiles as $sheetFile) {
        $sheetXml = $zip->getFromName($sheetFile);
        if ($sheetXml === false) {
            continue;
        }
        $sheetDoc = @simplexml_load_string($sheetXml);
        if (!$sheetDoc) {
            continue;
        }

        $rows = $sheetDoc->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if (!$rows) {
            continue;
        }

        foreach ($rows as $row) {
            $cells = $row->xpath('./*[local-name()="c"]');
            if (!$cells) {
                continue;
            }

            $selectedValue = '';
            foreach ($cells as $cell) {
                $cellRef = (string) $cell['r'];
                if ($cellRef !== '' && !preg_match('/^A\d+$/i', $cellRef)) {
                    continue;
                }

                $cellType = (string) $cell['t'];
                $value = '';
                if (isset($cell->v)) {
                    $value = (string) $cell->v;
                } elseif (isset($cell->is)) {
                    $inlineTextNodes = $cell->is->xpath('.//*[local-name()="t"]');
                    if ($inlineTextNodes) {
                        foreach ($inlineTextNodes as $inlineText) {
                            $value .= (string) $inlineText;
                        }
                    }
                }

                if ($cellType === 's') {
                    $idx = (int) $value;
                    $value = isset($sharedStrings[$idx]) ? $sharedStrings[$idx] : '';
                }

                $value = trim((string) $value);
                if ($value !== '') {
                    $selectedValue = $value;
                    break;
                }
            }

            if ($selectedValue !== '') {
                $numbers[] = $selectedValue;
            }
        }
    }

    $zip->close();
    return $numbers;
}

function readNameplateUploadNumbers($tmpPath, $originalName)
{
    $numbers = [];
    $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

    if ($ext === 'txt') {
        $lines = @file($tmpPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $value = trim((string) $line);
                if ($value !== '') {
                    $numbers[] = $value;
                }
            }
        }
        return $numbers;
    }

    if ($ext === 'csv') {
        $handle = @fopen($tmpPath, 'r');
        if ($handle) {
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if (!isset($data[0])) {
                    continue;
                }
                $value = trim((string) $data[0]);
                if ($value !== '') {
                    $numbers[] = $value;
                }
            }
            fclose($handle);
        }
        return $numbers;
    }

    if ($ext === 'xlsx') {
        return readNumbersFromXlsxFile($tmpPath);
    }

    if ($ext === 'xls') {
        $handle = @fopen($tmpPath, 'r');
        if ($handle) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                if (!isset($data[0])) {
                    continue;
                }
                $value = trim((string) $data[0]);
                if ($value !== '') {
                    $numbers[] = $value;
                }
            }
            fclose($handle);
        }
        return $numbers;
    }

    return $numbers;
}

function parseBarcodeRange($startStr, $endStr)
{
    $startStr = trim((string) $startStr);
    $endStr = trim((string) $endStr);

    if ($startStr === '' || $endStr === '') {
        return ['success' => false, 'error' => 'Nomor awal dan nomor akhir tidak boleh kosong.'];
    }

    if (!preg_match('/^(.*?)(\d+)$/', $startStr, $m1)) {
        return ['success' => false, 'error' => 'Nomor awal harus memiliki bagian angka di akhirnya.'];
    }

    if (!preg_match('/^(.*?)(\d+)$/', $endStr, $m2)) {
        return ['success' => false, 'error' => 'Nomor akhir harus memiliki bagian angka di akhirnya.'];
    }

    $prefix1 = $m1[1];
    $numStr1 = $m1[2];
    $prefix2 = $m2[1];
    $numStr2 = $m2[2];

    if ($prefix1 !== $prefix2) {
        return ['success' => false, 'error' => 'Awalan (prefix) nomor awal dan nomor akhir harus sama (\'' . $prefix1 . '\' vs \'' . $prefix2 . '\').'];
    }

    if (bccomp($numStr1, $numStr2) === 1) {
        return ['success' => false, 'error' => 'Nomor akhir harus lebih besar atau sama dengan nomor awal.'];
    }

    $count = bcadd(bcsub($numStr2, $numStr1), '1');
    $padLen = max(strlen($numStr1), strlen($numStr2));

    return [
        'success' => true,
        'prefix' => $prefix1,
        'start_num' => $numStr1,
        'end_num' => $numStr2,
        'count' => $count,
        'pad_len' => $padLen
    ];
}

// ====== UPLOAD WO (RANGE NOMOR & FILE) ======
$msgUpload = '';
$lastWoInput = '';
$lastStartInput = '';
$lastEndInput = '';

if (isset($_POST['upload_range'])) {
    $rawWo = trim((string) ($_POST['wo'] ?? ''));
    $startNumber = trim((string) ($_POST['start_number'] ?? ''));
    $endNumber = trim((string) ($_POST['end_number'] ?? ''));
    $lastWoInput = $rawWo;
    $lastStartInput = $startNumber;
    $lastEndInput = $endNumber;

    if ($rawWo === '' || $startNumber === '' || $endNumber === '') {
        $msgUpload = "WO Number, Nomor Awal, dan Nomor Akhir wajib diisi.";
    } else {
        $rangeInfo = parseBarcodeRange($startNumber, $endNumber);
        if (!$rangeInfo['success']) {
            $msgUpload = $rangeInfo['error'];
        } else {
            $prefix = $rangeInfo['prefix'];
            $curr = $rangeInfo['start_num'];
            $end = $rangeInfo['end_num'];
            $padLen = $rangeInfo['pad_len'];
            $totalCount = $rangeInfo['count'];

            $safeWo = $koneksi->real_escape_string($rawWo);
            $batchValues = [];
            $inserted = 0;

            // Generate and insert in batches of 1000
            while (bccomp($curr, $end) <= 0) {
                $numStr = $prefix . str_pad($curr, $padLen, '0', STR_PAD_LEFT);
                $safeNum = $koneksi->real_escape_string($numStr);
                $batchValues[] = "('$safeNum', '$safeWo')";
                $inserted++;

                if (count($batchValues) >= 1000) {
                    $sql = "INSERT INTO {$pcbNumberTable} (nomor, wo) VALUES " . implode(',', $batchValues);
                    $koneksi->query($sql);
                    $batchValues = [];
                }

                $curr = bcadd($curr, '1');
            }

            if (!empty($batchValues)) {
                $sql = "INSERT INTO {$pcbNumberTable} (nomor, wo) VALUES " . implode(',', $batchValues);
                $koneksi->query($sql);
            }

            $downloadUrl = "view.php?menu=" . urlencode($menu) . "&action=download_wo_txt&wo=" . urlencode($rawWo);
            $msgUpload = "Upload range nomor berhasil untuk WO: <strong>" . htmlspecialchars($rawWo) . "</strong> (" . number_format((float) $totalCount, 0, ',', '.') . " nomor dimasukkan ke database). <a href=\"" . htmlspecialchars($downloadUrl) . "\" class=\"btn-download-link\" style=\"display: inline-block; margin-left: 10px; padding: 4px 12px; background: #123968; color: #fff; border-radius: 999px; text-decoration: none; font-size: 12px; font-weight: bold;\">&#128190; Download TXT</a>";
        }
    }
} elseif (isset($_POST['upload'])) {
    $wo = $koneksi->real_escape_string(trim($_POST['wo']));
    $file = $_FILES['file']['tmp_name'] ?? null;
    if ($file && is_uploaded_file($file)) {
        $handle = fopen($file, "r");
        if ($handle) {
            $batchValues = [];
            $fileCount = 0;
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if (!isset($data[0])) {
                    continue;
                }
                $nomor = trim($data[0]);
                if ($nomor === '') {
                    continue;
                }
                $safeNomor = $koneksi->real_escape_string($nomor);
                $batchValues[] = "('$safeNomor', '$wo')";
                $fileCount++;
                if (count($batchValues) >= 1000) {
                    $koneksi->query("INSERT INTO {$pcbNumberTable} (nomor, wo) VALUES " . implode(',', $batchValues));
                    $batchValues = [];
                }
            }
            if (!empty($batchValues)) {
                $koneksi->query("INSERT INTO {$pcbNumberTable} (nomor, wo) VALUES " . implode(',', $batchValues));
            }
            fclose($handle);
            $downloadUrl = "view.php?menu=" . urlencode($menu) . "&action=download_wo_txt&wo=" . urlencode(trim($_POST['wo']));
            $msgUpload = "Upload file berhasil untuk WO: <strong>" . htmlspecialchars(trim($_POST['wo'])) . "</strong> (" . number_format($fileCount, 0, ',', '.') . " nomor). <a href=\"" . htmlspecialchars($downloadUrl) . "\" class=\"btn-download-link\" style=\"display: inline-block; margin-left: 10px; padding: 4px 12px; background: #123968; color: #fff; border-radius: 999px; text-decoration: none; font-size: 12px; font-weight: bold;\">&#128190; Download TXT</a>";
        } else {
            $msgUpload = "Gagal membuka file upload.";
        }
    } else {
        $msgUpload = "File tidak terdeteksi.";
    }
}

// ====== SPLIT PER WO (999) ======
$msgSplit = '';
if (isset($_POST['split']) && !empty($_POST['wo_selected'])) {
    $wo = $koneksi->real_escape_string($_POST['wo_selected']);
    $ids = [];
    $result = $koneksi->query("SELECT id FROM {$pcbNumberTable} WHERE wo='$wo' AND batch_id IS NULL ORDER BY id ASC");
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }

    $batchSize = $isEvChargerMenu ? 50000 : 999;
    $batchNum = 1;
    for ($i = 0; $i < count($ids); $i += $batchSize) {
        $nama_batch = "Batch $batchNum - $wo";
        $koneksi->query("INSERT INTO {$pcbBatchTable} (nama_batch, wo) VALUES ('$nama_batch', '$wo')");
        $batch_id = $koneksi->insert_id;
        if ($batch_id > 0) {
            $chunk = array_slice($ids, $i, $batchSize);
            if (!empty($chunk)) {
                $in = implode(',', array_map('intval', $chunk));
                $koneksi->query("UPDATE {$pcbNumberTable} SET batch_id=$batch_id WHERE id IN ($in)");
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
    $koneksi->query("DELETE FROM {$pcbNumberTable} WHERE wo='$wo'");
    $koneksi->query("DELETE FROM {$pcbBatchTable} WHERE wo='$wo'");
    $msgFinish = "WO $wo sudah dihapus!";
}

// ====== NAMEPLATE UPLOAD + SPLIT (ADMIN) ======
$msgNameplateUpload = '';
if (isset($_POST['upload_nameplate'])) {
    $woNameplate = $koneksi->real_escape_string(trim($_POST['wo_nameplate']));
    $fileNameplateTmp = isset($_FILES['file_nameplate']['tmp_name']) ? $_FILES['file_nameplate']['tmp_name'] : '';
    $fileNameplateOriginal = isset($_FILES['file_nameplate']['name']) ? $_FILES['file_nameplate']['name'] : '';
    $fileExtNameplate = strtolower(pathinfo((string) $fileNameplateOriginal, PATHINFO_EXTENSION));

    if ($fileNameplateTmp && is_uploaded_file($fileNameplateTmp)) {
        if (!in_array($fileExtNameplate, ['xlsx', 'xls', 'txt', 'csv'], true)) {
            $msgNameplateUpload = "Format file tidak didukung. Gunakan .xlsx, .xls, .txt, atau .csv.";
        } else {
            $numbersFromFile = readNameplateUploadNumbers($fileNameplateTmp, $fileNameplateOriginal);
            $insertedCount = 0;
            foreach ($numbersFromFile as $nomorRaw) {
                $nomor = trim((string) $nomorRaw);
                if ($nomor === '') {
                    continue;
                }
                $safeNomor = $koneksi->real_escape_string($nomor);
                if ($koneksi->query("INSERT INTO nameplate_nomor_barcode (nomor, wo) VALUES ('$safeNomor', '$woNameplate')")) {
                    $insertedCount++;
                }
            }

            if ($insertedCount > 0) {
                $msgNameplateUpload = "Upload nameplate berhasil untuk WO: $woNameplate ($insertedCount nomor).";
            } else {
                $msgNameplateUpload = "Tidak ada data nomor yang terbaca dari file nameplate.";
            }
        }
    } else {
        $msgNameplateUpload = "File nameplate tidak terdeteksi.";
    }
}

$msgNameplateSplit = '';
if (isset($_POST['split_nameplate']) && !empty($_POST['wo_selected_np'])) {
    $woNp = $koneksi->real_escape_string($_POST['wo_selected_np']);
    $idsNp = [];
    $resultNp = $koneksi->query("SELECT id FROM nameplate_nomor_barcode WHERE wo='$woNp' AND batch_id IS NULL ORDER BY id ASC");
    while ($resultNp && $rowNp = $resultNp->fetch_assoc()) {
        $idsNp[] = (int) $rowNp['id'];
    }

    $batchSizeNp = 1000;
    $batchNumNp = 1;
    for ($i = 0; $i < count($idsNp); $i += $batchSizeNp) {
        $namaBatchNp = "Batch $batchNumNp - $woNp";
        $koneksi->query("INSERT INTO nameplate_batch (nama_batch, wo) VALUES ('$namaBatchNp', '$woNp')");
        $batchIdNp = $koneksi->insert_id;
        if ($batchIdNp > 0) {
            $chunkNp = array_slice($idsNp, $i, $batchSizeNp);
            if (!empty($chunkNp)) {
                $inNp = implode(',', array_map('intval', $chunkNp));
                $koneksi->query("UPDATE nameplate_nomor_barcode SET batch_id=$batchIdNp WHERE id IN ($inNp)");
            }
        }
        $batchNumNp++;
    }
    $msgNameplateSplit = "WO Nameplate $woNp berhasil di-split per 1000 nomor.";
}

$msgNameplateFinish = '';
if (isset($_POST['finish_nameplate']) && !empty($_POST['wo_selected_np'])) {
    $woNp = $koneksi->real_escape_string($_POST['wo_selected_np']);
    $koneksi->query("DELETE FROM nameplate_nomor_barcode WHERE wo='$woNp'");
    $koneksi->query("DELETE FROM nameplate_batch WHERE wo='$woNp'");
    $msgNameplateFinish = "WO Nameplate $woNp sudah dihapus.";
}

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
            // Admin is allowed to print duplicates
            $selectedRows = array_slice($allBatchRows, $startIndex, ($endIndex - $startIndex + 1));
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
                echo "w.document.write('JsBarcode(\"#{$id}\",\"{$num}\",{format:\"CODE128\",displayValue:false,fontSize:11,height:51,width:3});');\n";
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
		    transform: translate(-0.5mm, 0.3mm);
		  }
		  .sheet .sticker + .sticker {
		    margin-left: 4mm;
		    transform: translate(2mm, 0.3mm);
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
				    transform: translateY(-0.3mm);
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
        $defaultEvQty = $unprintedCount > 0 ? $unprintedCount : $totalBatchCount;
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
$pcbRecordRows = [];
$nameplateRecordRows = [];
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

$pcbRecordRes = $koneksi->query("SELECT nama_batch, printed_at, start_number, end_number, username FROM log_print ORDER BY printed_at DESC LIMIT 200");
while ($pcbRecordRes && $recordRow = $pcbRecordRes->fetch_assoc()) {
    $pcbRecordRows[] = $recordRow;
}

$nameplateRecordRes = $koneksi->query("SELECT nama_batch, printed_at, start_number, end_number, username FROM nameplate_log_print ORDER BY printed_at DESC LIMIT 200");
while ($nameplateRecordRes && $recordRowNp = $nameplateRecordRes->fetch_assoc()) {
    $nameplateRecordRows[] = $recordRowNp;
}

$evChargerRecordRes = $koneksi->query("SELECT nama_batch, printed_at, start_number, end_number, username FROM ev_charger_log_print ORDER BY printed_at DESC LIMIT 200");
$evChargerRecordRows = [];
while ($evChargerRecordRes && $recordRowEv = $evChargerRecordRes->fetch_assoc()) {
    $evChargerRecordRows[] = $recordRowEv;
}

$evSnRecordRes = $koneksi->query("SELECT total_printed, start_sn, end_sn, prod_date, printed_at, username FROM ev_charger_sn_log_print ORDER BY printed_at DESC LIMIT 200");
$evSnRecordRows = [];
while ($evSnRecordRes && $recordRowSn = $evSnRecordRes->fetch_assoc()) {
    $evSnRecordRows[] = [
        'nama_batch' => "SN EV ({$recordRowSn['total_printed']} pcs - {$recordRowSn['prod_date']})",
        'printed_at' => $recordRowSn['printed_at'],
        'start_number' => $recordRowSn['start_sn'],
        'end_number' => $recordRowSn['end_sn'],
        'username' => $recordRowSn['username']
    ];
}

if ($recordType === 'nameplate') {
    $activeRecordRows = $nameplateRecordRows;
    $activeRecordLabel = 'Nameplate';
} elseif ($recordType === 'ev_charger') {
    $activeRecordRows = $evChargerRecordRows;
    $activeRecordLabel = 'EV Charger';
} elseif ($recordType === 'ev_charger_sn') {
    $activeRecordRows = $evSnRecordRows;
    $activeRecordLabel = 'SN EV Charger';
} else {
    $activeRecordRows = $pcbRecordRows;
    $activeRecordLabel = 'PCB';
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

    /* Badge & Button Utilities */
    .badge-role {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .badge-role-admin {
      background: var(--badge-admin-bg);
      color: var(--badge-admin-text);
      border: 1px solid var(--badge-admin-border);
    }
    .badge-role-user {
      background: var(--badge-user-bg);
      color: var(--badge-user-text);
      border: 1px solid var(--badge-user-border);
    }

    .btn-action-sm {
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 8px;
      background: var(--content-panel-soft);
      border: 1px solid var(--content-line-strong);
      color: var(--content-text);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-action-sm:hover {
      background: var(--content-button-hover);
      color: #ffffff;
      border-color: var(--content-button-hover);
    }

    .btn-danger-sm {
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 8px;
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
      border: 1px solid rgba(239, 68, 68, 0.35);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-danger-sm:hover {
      background: rgba(239, 68, 68, 0.22);
      color: #ffffff;
      border-color: rgba(239, 68, 68, 0.55);
    }
    body.light-theme .btn-danger-sm {
      background: rgba(220, 38, 38, 0.08);
      color: #dc2626;
      border-color: rgba(220, 38, 38, 0.3);
    }
    body.light-theme .btn-danger-sm:hover {
      background: rgba(220, 38, 38, 0.16);
      color: #991b1b;
      border-color: rgba(220, 38, 38, 0.5);
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

    .required {
      margin-left: auto;
      border: 1px solid var(--content-line);
      padding: 2px 8px;
      font-size: 11px;
      color: var(--content-muted);
      border-radius: 999px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-size: 13px;
      color: var(--content-muted);
    }

    input[type="text"],
    input[type="file"],
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
    input[type="file"],
    input[type="date"],
    select,
    button {
      transition: border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease, background-color 0.22s ease;
    }

    input[type="text"]:focus,
    input[type="file"]:focus,
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

    .file-input {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .file-input input[type="file"] {
      margin-bottom: 0;
      flex: 1;
    }

    .button-group {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .custom-fields .custom-select,
    .record-filter .custom-select {
      margin-bottom: 0;
    }

    .custom-fields .custom-select {
      flex: 1;
      min-width: 150px;
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

    .status-msg {
      margin: 8px 0 0;
      padding: 10px;
      border: 1px solid var(--content-line-strong);
      background: var(--content-panel-soft);
      color: var(--content-text);
      font-size: 13px;
      border-radius: 14px;
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

    .record-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      margin-top: 12px;
    }

    .record-group h3 {
      margin: 0 0 8px;
      font-size: 14px;
      letter-spacing: 0.03em;
      color: var(--content-muted);
      text-transform: uppercase;
    }

    .record-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--content-panel);
      border: 1px solid var(--table-border);
      color: var(--content-text);
      border-radius: 12px;
      overflow: hidden;
    }

    .record-table th,
    .record-table td {
      border: 1px solid var(--table-border);
      padding: 9px 10px;
      font-size: 12.5px;
      text-align: left;
      vertical-align: middle;
    }

    .record-table th {
      background: var(--table-header-bg);
      color: var(--table-header-text);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .record-table tbody tr:nth-child(even) td {
      background: var(--table-row-even);
    }

    .record-table tbody tr:hover td {
      background: var(--table-row-hover);
    }

    .record-empty {
      border: 1px dashed var(--content-line-strong);
      padding: 10px;
      background: var(--content-panel-soft);
      font-size: 12px;
      color: var(--content-muted);
      border-radius: 14px;
    }

    .record-filter {
      display: flex;
      align-items: end;
      gap: 10px;
      margin-bottom: 12px;
      max-width: 340px;
    }

    .record-filter select {
      margin-bottom: 0;
    }

    .record-toolbar {
      display: flex;
      align-items: end;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }

    .record-search {
      flex: 1 1 320px;
      max-width: 420px;
    }

    .record-search input {
      margin-bottom: 0;
    }

    .record-date-filter {
      display: flex;
      align-items: end;
      gap: 8px;
      flex-wrap: wrap;
      max-height: 0;
      opacity: 0;
      overflow: hidden;
      transform: translateY(-8px);
      pointer-events: none;
      transition: max-height 0.24s ease, opacity 0.24s ease, transform 0.24s ease;
    }

    .record-date-filter.active {
      max-height: 120px;
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }

    .record-date-filter input {
      margin-bottom: 0;
      min-width: 170px;
    }

    .record-date-toggle,
    .record-date-clear {
      align-self: end;
      white-space: nowrap;
    }

    .record-pagination {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .record-pagination-info {
      font-size: 12px;
      color: var(--content-muted);
    }

    .record-pagination-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .record-pagination button[disabled] {
      cursor: not-allowed;
      opacity: 0.5;
    }

    .record-page-jump {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: var(--content-muted);
    }

    .record-page-jump input {
      width: 72px;
      margin-bottom: 0;
      padding: 8px 10px;
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

      .file-input {
        flex-direction: column;
        align-items: stretch;
      }

      .nameplate-controls {
        grid-template-columns: 1fr;
      }

      .nameplate-preview-grid {
        grid-template-columns: 1fr;
      }

      .record-grid {
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
	        <a class="nav-link <?= $menu === 'pcb' ? 'active' : '' ?>" href="view.php?menu=pcb">
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
	            <a class="nav-sub-link <?= $menu === 'ev_charger' ? 'active' : '' ?>" href="view.php?menu=ev_charger">
	              <svg class="nav-icon sub-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
	              <span class="nav-link-text">Print PCB EV Charge</span>
	            </a>
	            <a class="nav-sub-link <?= $menu === 'ev_charger_sn' ? 'active' : '' ?>" href="view.php?menu=ev_charger_sn">
	              <svg class="nav-icon sub-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="6" height="6" rx="1"></rect><rect x="15" y="3" width="6" height="6" rx="1"></rect><rect x="3" y="15" width="6" height="6" rx="1"></rect><path d="M14 14h3v3h-3z"></path><path d="M20 14v6h-6"></path><path d="M17 17v3"></path></svg>
	              <span class="nav-link-text">Print SN EV Charge</span>
	            </a>
	          </div>
	        </div>
	        <a class="nav-link <?= $menu === 'nameplate' ? 'active' : '' ?>" href="view.php?menu=nameplate">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
	          <span class="nav-link-text">Print Nameplate Barcode</span>
	        </a>
	        <a class="nav-link <?= $menu === 'record' ? 'active' : '' ?>" href="view.php?menu=record">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
	          <span class="nav-link-text">Record Print</span>
	        </a>
	        <a class="nav-link <?= $menu === 'custom' ? 'active' : '' ?>" href="view.php?menu=custom">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
	          <span class="nav-link-text">Custom Print</span>
	        </a>
	        <a class="nav-link <?= $menu === 'setting' ? 'active' : '' ?>" href="view.php?menu=setting">
	          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
	          <span class="nav-link-text">Setting</span>
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
	          <p><?= $isEvChargerMenu ? 'Halaman admin untuk upload, split, preview, dan print barcode PCB EV Chager.' : 'Halaman utama setelah login untuk upload, split, preview, dan print barcode PCB.' ?></p>
	        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">1</span>
            Input Range Nomor WO
            <span class="required">Required</span>
          </div>

          <!-- Mode 1: Input Range Nomor (Utama) -->
          <div id="rangeInputMode">
            <form method="POST" id="rangeForm">
              <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
              
              <label for="rangeWo">WO Number</label>
              <input type="text" id="rangeWo" name="wo" placeholder="Enter work order number" required value="<?= htmlspecialchars($lastWoInput ?? '') ?>">

              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                  <label for="rangeStartNumber">Nomor Awal (Start Number)</label>
                  <input type="text" id="rangeStartNumber" name="start_number" placeholder="Contoh: 6003156549 atau HXE0001" required value="<?= htmlspecialchars($lastStartInput ?? '') ?>" style="margin-bottom: 0;">
                </div>
                <div>
                  <label for="rangeEndNumber">Nomor Akhir (End Number)</label>
                  <input type="text" id="rangeEndNumber" name="end_number" placeholder="Contoh: 6003157548 atau HXE1000" required value="<?= htmlspecialchars($lastEndInput ?? '') ?>" style="margin-bottom: 0;">
                </div>
              </div>

              <!-- Baris Aksi: Count, Display Quantity, Upload, Download TXT -->
              <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 14px;">
                <button type="button" id="btnCountRange" style="height: 42px; padding: 0 18px; border-radius: 999px; background: var(--content-panel-soft); color: var(--content-text); border: 1px solid var(--content-line-strong); font-weight: 600; cursor: pointer;">
                  Count
                </button>

                <div id="rangeCountResult" style="height: 42px; display: inline-flex; align-items: center; padding: 0 16px; border-radius: 999px; background: var(--content-panel-soft); border: 1px dashed var(--content-line-strong); font-size: 13px; color: var(--content-text);">
                  Quantity:&nbsp;<strong id="rangeCountValue" style="color: var(--content-button-hover); font-size: 15px;">-</strong>&nbsp;nomor
                </div>

                <button type="submit" name="upload_range" id="btnUploadRange" class="primary" style="height: 42px; padding: 0 22px;">
                  Upload
                </button>

                <button type="button" id="btnDownloadTxt" style="height: 42px; padding: 0 18px; border-radius: 999px; background: var(--content-panel-soft); color: var(--content-text); border: 1px solid var(--content-line-strong); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                  &#128190; Download TXT
                </button>
              </div>

              <div id="rangeErrorMsg" style="display: none; margin-top: 10px; padding: 8px 12px; border-radius: 10px; font-size: 13px; color: #dc2626; background: rgba(220, 38, 38, 0.08); border: 1px solid rgba(220, 38, 38, 0.2);"></div>

              <div style="margin-top: 14px; font-size: 12px; color: var(--content-muted);">
                <a href="javascript:void(0)" id="toggleToUploadFile" style="color: inherit; text-decoration: underline;">
                  Atau beralih ke upload file CSV/TXT manual
                </a>
              </div>
            </form>
          </div>

          <!-- Mode 2: Upload File Manual (Fallback Checkpoint) -->
          <div id="fileUploadMode" style="display: none;">
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
              <label>WO Number</label>
              <input type="text" name="wo" placeholder="Enter work order number" required>
              <label>Upload File (.csv / .txt)</label>
              <div class="file-input">
                <input type="file" name="file" required>
                <button type="submit" name="upload">Upload</button>
              </div>
              <div style="margin-top: 12px; font-size: 12px; color: var(--content-muted);">
                <a href="javascript:void(0)" id="toggleToRangeInput" style="color: inherit; text-decoration: underline;">
                  &larr; Kembali ke Input Range Nomor
                </a>
              </div>
            </form>
          </div>

          <?php if (!empty($msgUpload)): ?>
            <div class="status-msg" style="margin-top: 12px;"><?= $msgUpload ?></div>
          <?php endif; ?>
        </div>

        <script>
          (function () {
            var rangeForm = document.getElementById("rangeForm");
            var startInput = document.getElementById("rangeStartNumber");
            var endInput = document.getElementById("rangeEndNumber");
            var woInput = document.getElementById("rangeWo");
            var countBtn = document.getElementById("btnCountRange");
            var countVal = document.getElementById("rangeCountValue");
            var countBox = document.getElementById("rangeCountResult");
            var downloadBtn = document.getElementById("btnDownloadTxt");
            var errorBox = document.getElementById("rangeErrorMsg");
            var toggleToFile = document.getElementById("toggleToUploadFile");
            var toggleToRange = document.getElementById("toggleToRangeInput");
            var rangeMode = document.getElementById("rangeInputMode");
            var fileMode = document.getElementById("fileUploadMode");

            if (!rangeForm || !startInput || !endInput || !countBtn || !countVal) {
              return;
            }

            function parseClientRange() {
              var startVal = startInput.value.trim();
              var endVal = endInput.value.trim();
              if (!startVal || !endVal) {
                return { success: false, error: "Nomor awal dan nomor akhir wajib diisi." };
              }
              var m1 = startVal.match(/^(.*?)(\d+)$/);
              var m2 = endVal.match(/^(.*?)(\d+)$/);
              if (!m1 || !m2) {
                return { success: false, error: "Nomor awal dan nomor akhir harus memiliki bagian angka di belakangnya." };
              }
              if (m1[1] !== m2[1]) {
                return { success: false, error: "Awalan (prefix) nomor awal dan nomor akhir harus sama ('" + m1[1] + "' vs '" + m2[1] + "')." };
              }
              try {
                var n1 = BigInt(m1[2]);
                var n2 = BigInt(m2[2]);
                if (n2 < n1) {
                  return { success: false, error: "Nomor akhir harus lebih besar atau sama dengan nomor awal." };
                }
                var count = n2 - n1 + 1n;
                var padLen = Math.max(m1[2].length, m2[2].length);
                return {
                  success: true,
                  prefix: m1[1],
                  n1: n1,
                  n2: n2,
                  count: count,
                  padLen: padLen
                };
              } catch (e) {
                return { success: false, error: "Angka range tidak valid." };
              }
            }

            function doCount() {
              var info = parseClientRange();
              if (!info.success) {
                countVal.textContent = "-";
                if (errorBox) {
                  errorBox.textContent = info.error;
                  errorBox.style.display = "block";
                }
                return null;
              }
              if (errorBox) {
                errorBox.style.display = "none";
              }
              countVal.textContent = info.count.toLocaleString("id-ID");
              if (countBox) {
                countBox.style.borderColor = "#10b981";
                countBox.style.background = "rgba(16, 185, 129, 0.08)";
              }
              return info;
            }

            countBtn.addEventListener("click", function () {
              doCount();
            });

            startInput.addEventListener("input", function () {
              if (startInput.value && endInput.value) {
                doCount();
              }
            });

            endInput.addEventListener("input", function () {
              if (startInput.value && endInput.value) {
                doCount();
              }
            });

            if (downloadBtn) {
              downloadBtn.addEventListener("click", function () {
                var info = doCount();
                if (!info) return;

                if (info.count > 200000n) {
                  if (!confirm("Range nomor ini sangat besar (" + info.count.toLocaleString("id-ID") + " baris). Lanjutkan pembuatan file TXT di browser?")) {
                    return;
                  }
                }

                var lines = [];
                var n1 = info.n1;
                var n2 = info.n2;
                var prefix = info.prefix;
                var padLen = info.padLen;

                for (var i = n1; i <= n2; i++) {
                  var numStr = i.toString();
                  while (numStr.length < padLen) {
                    numStr = "0" + numStr;
                  }
                  lines.push(prefix + numStr);
                }

                var blob = new Blob([lines.join("\r\n") + "\r\n"], { type: "text/plain;charset=utf-8" });
                var link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                var wo = (woInput ? woInput.value.trim() : "") || "barcode_range";
                var safeName = wo.replace(/[^a-zA-Z0-9_\-]/g, "_");
                link.download = safeName + ".txt";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
              });
            }

            rangeForm.addEventListener("submit", function (e) {
              var info = parseClientRange();
              if (!info.success) {
                e.preventDefault();
                if (errorBox) {
                  errorBox.textContent = info.error;
                  errorBox.style.display = "block";
                }
                return false;
              }
            });

            if (toggleToFile && toggleToRange && rangeMode && fileMode) {
              toggleToFile.addEventListener("click", function () {
                rangeMode.style.display = "none";
                fileMode.style.display = "block";
              });
              toggleToRange.addEventListener("click", function () {
                fileMode.style.display = "none";
                rangeMode.style.display = "block";
              });
            }

            if (startInput.value && endInput.value) {
              doCount();
            }
          })();
        </script>

	        <div class="card">
          <div class="step-title">
            <span class="icon">2</span>
            Split Data WO
          </div>
          <p>Process up to 999 items per work order.</p>
          <form method="POST">
            <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
            <label>Select Work Order</label>
            <select name="wo_selected" required>
              <option value="">Choose a work order</option>
              <?php
              $woRes = $koneksi->query("SELECT wo, COUNT(*) jml FROM {$pcbNumberTable} WHERE wo IS NOT NULL GROUP BY wo ORDER BY wo");
              while ($w = $woRes->fetch_assoc()) {
                  $woVal = htmlspecialchars($w['wo']);
                  echo "<option value='{$woVal}'>{$woVal} ({$w['jml']})</option>";
              }
              ?>
            </select>
            <div class="button-group">
              <button type="submit" name="split">Split</button>
              <button type="submit" name="finish" onclick="return confirm('Yakin hapus WO ini?')">Finish</button>
            </div>
            <?php if (!empty($msgSplit)): ?>
              <p class="status-msg"><?= htmlspecialchars($msgSplit) ?></p>
            <?php endif; ?>
            <?php if (!empty($msgFinish)): ?>
              <p class="status-msg"><?= htmlspecialchars($msgFinish) ?></p>
            <?php endif; ?>
	          </form>
	        </div>

	        <div class="card">
	          <div class="step-title">
	            <span class="icon">3</span>
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
	            <span class="icon">4</span>
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
                              $label .= ' (Sudah di-print)';
                          }
                      }
                      echo "<option value='{$id}' {$sel}>{$label}</option>";
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
	                      <span style="color: #10b981; font-weight: 600;">&#10003; Tercetak:</span> <?= htmlspecialchars($printedNumbers[0]) ?> s/d <?= htmlspecialchars(end($printedNumbers)) ?> (<?= number_format($printedCount, 0, ',', '.') ?> nomor tercetak, Admin dapat cetak ulang)
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
	                <div class="warning-msg" style="<?= $isEvChargerMenu ? 'background: rgba(59, 130, 246, 0.1); border-color: #3b82f6; color: #2563eb;' : '' ?>">
	                  <?= $isEvChargerMenu ? 'Batch ini sudah selesai di-print seluruhnya (' . number_format($totalBatchCount, 0, ',', '.') . ' nomor sudah tercetak). Sebagai Admin, Anda dapat melakukan print duplikat jika diperlukan.' : 'Batch ini sudah di-print. Admin masih bisa print ulang jika diperlukan.' ?>
	                </div>
	              <?php endif; ?>
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
	                        <input type="number" name="ev_print_qty" id="evPrintQty" min="1" max="<?= max(1, $totalBatchCount) ?>" value="<?= $defaultEvQty ?>" required style="margin-bottom: 0;">
	                      </div>
	                    </div>
	                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
	                      <div style="font-size: 12px; color: var(--content-muted);">
	                        Estimasi: <strong id="evSheetsEstimate" style="color: var(--content-button-hover);"><?= (int) ceil($defaultEvQty / 9) ?> Lembar</strong> (3x3 = 9 barcode/lembar)
	                      </div>
	                      <div style="display: flex; gap: 6px; flex-wrap: wrap;">
	                        <?php if ($unprintedCount > 0): ?>
	                          <button type="button" id="btnEvQuickAll" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">Cetak Sisa (<?= $unprintedCount ?>)</button>
	                        <?php endif; ?>
	                        <button type="button" id="btnEvQuickFullBatch" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">Semua Batch (<?= $totalBatchCount ?>)</button>
	                        <button type="button" id="btnEvQuick9" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">1 Lembar (9)</button>
	                        <button type="button" id="btnEvQuick18" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: var(--content-panel); border: 1px solid var(--content-line-strong); cursor: pointer; color: var(--content-text);">2 Lembar (18)</button>
	                      </div>
	                    </div>
	                    <div id="evRangeError" style="display: none; margin-top: 8px; padding: 6px 12px; border-radius: 8px; font-size: 12px; border: 1px solid;"></div>
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
	                  <p class="status-msg" style="margin-top: 6px;">Pilih custom text EV Charger dan range nomor barcode yang akan dicetak. Sebagai Admin, Anda diizinkan melakukan print duplikat jika diperlukan.</p>
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
                  var btnQuickFullBatch = document.getElementById("btnEvQuickFullBatch");
                  var btnQuick9 = document.getElementById("btnEvQuick9");
                  var btnQuick18 = document.getElementById("btnEvQuick18");

                  var allBatchList = <?= json_encode(array_values($allNumbers ?? [])) ?>;
                  var unprintedList = <?= json_encode(array_values($unprintedNumbers ?? [])) ?>;
                  var printedSet = new Set(<?= json_encode(array_values($printedNumbers ?? [])) ?>);
                  var allMap = {};
                  for (var i = 0; i < allBatchList.length; i++) {
                    allMap[allBatchList[i]] = i;
                  }

                  var hasEvDuplicates = false;
                  var evDuplicateCount = 0;

                  function showEvNotice(msg, isError) {
                    if (evRangeError) {
                      evRangeError.textContent = msg;
                      evRangeError.style.display = "block";
                      if (isError) {
                        evRangeError.style.background = "rgba(239, 68, 68, 0.12)";
                        evRangeError.style.borderColor = "#ef4444";
                        evRangeError.style.color = "#dc2626";
                      } else {
                        evRangeError.style.background = "rgba(245, 158, 11, 0.12)";
                        evRangeError.style.borderColor = "#f59e0b";
                        evRangeError.style.color = "#b45309";
                      }
                    }
                    if (printBtn) {
                      printBtn.disabled = isError;
                      printBtn.style.opacity = isError ? "0.5" : "1";
                    }
                  }

                  function clearEvNotice() {
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
                    if (allBatchList.length === 0) {
                      showEvNotice("Tidak ada data nomor barcode dalam batch ini.", true);
                      return false;
                    }

                    var sVal = evStartInput.value.trim();
                    var eVal = evEndInput.value.trim();

                    if (!sVal || !eVal) {
                      showEvNotice("Nomor awal dan nomor akhir wajib diisi.", true);
                      return false;
                    }

                    if (allMap[sVal] === undefined) {
                      showEvNotice("Nomor awal (" + sVal + ") tidak ditemukan dalam data batch ini.", true);
                      return false;
                    }
                    if (allMap[eVal] === undefined) {
                      showEvNotice("Nomor akhir (" + eVal + ") tidak ditemukan dalam data batch ini.", true);
                      return false;
                    }

                    var sIdx = allMap[sVal];
                    var eIdx = allMap[eVal];
                    if (sIdx > eIdx) {
                      showEvNotice("Nomor akhir harus berada setelah atau sama dengan nomor awal.", true);
                      return false;
                    }

                    var count = eIdx - sIdx + 1;
                    evQtyInput.value = count;
                    var sheets = Math.ceil(count / 9);
                    if (evSheetsEstimate) {
                      var lastPageCount = count % 9 === 0 ? 9 : (count % 9);
                      evSheetsEstimate.textContent = sheets + " Lembar (" + count + " barcode" + (sheets > 1 ? ", lembar terakhir: " + lastPageCount + " barcode" : "") + ")";
                    }

                    hasEvDuplicates = false;
                    evDuplicateCount = 0;
                    for (var idx = sIdx; idx <= eIdx; idx++) {
                      var num = allBatchList[idx];
                      if (printedSet.has(num)) {
                        hasEvDuplicates = true;
                        evDuplicateCount++;
                      }
                    }

                    if (hasEvDuplicates) {
                      showEvNotice("⚠️ Perhatian: Range ini mengandung " + evDuplicateCount + " nomor yang sudah pernah dicetak (Print Duplikat). Sebagai Admin, Anda dapat mencetak ulang nomor ini.", false);
                    } else {
                      clearEvNotice();
                    }
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
                        } else {
                          evEndInput.value = allBatchList[allBatchList.length - 1];
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

                    if (btnQuickFullBatch && allBatchList.length > 0) {
                      btnQuickFullBatch.addEventListener("click", function () {
                        evStartInput.value = allBatchList[0];
                        evEndInput.value = allBatchList[allBatchList.length - 1];
                        validateEvRange();
                      });
                    }

                    var baseList = unprintedList.length > 0 ? unprintedList : allBatchList;
                    if (btnQuick9 && baseList.length > 0) {
                      btnQuick9.addEventListener("click", function () {
                        evStartInput.value = baseList[0];
                        var count = Math.min(9, baseList.length);
                        evEndInput.value = baseList[count - 1];
                        validateEvRange();
                      });
                    }
                    if (btnQuick18 && baseList.length > 0) {
                      btnQuick18.addEventListener("click", function () {
                        evStartInput.value = baseList[0];
                        var count = Math.min(18, baseList.length);
                        evEndInput.value = baseList[count - 1];
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

                    var isPrinted = printForm.dataset.isPrinted === "1";
                    if (isEvMode) {
                      var sVal = evStartInput ? evStartInput.value.trim() : "";
                      var eVal = evEndInput ? evEndInput.value.trim() : "";
                      var qVal = evQtyInput ? evQtyInput.value.trim() : "";
                      if (hasEvDuplicates) {
                        modalTitle.textContent = "Konfirmasi Print Duplikat EV Charger";
                        modalMessage.textContent = "Terdapat " + evDuplicateCount + " nomor yang sudah pernah dicetak dalam range " + sVal + " s/d " + eVal + " (Total " + qVal + " barcode).\n\nSebagai Admin, Anda dapat mencetak ulang (duplikat). Lanjutkan cetak duplikat?";
                        yesBtn.textContent = "Ya, Cetak Duplikat";
                        noBtn.textContent = "Batal";
                      } else {
                        modalTitle.textContent = "Konfirmasi Record EV Charger";
                        modalMessage.textContent = "Cetak dan record " + qVal + " barcode (" + sVal + " s/d " + eVal + ") ke database? Nomor yang direcord tidak dapat dicetak ulang oleh operator.";
                        yesBtn.textContent = "Ya, Record & Cetak";
                        noBtn.textContent = "Hanya Cetak (Tanpa Record)";
                      }
                    } else if (isPrinted) {
                      modalTitle.textContent = "Konfirmasi Print Ulang";
                      modalMessage.textContent = "Batch ini sudah print, yakin print ulang?";
                      yesBtn.textContent = "Ya";
                      noBtn.textContent = "Tidak";
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
                    if (isEvMode) {
                      recordField.value = "1";
                    } else {
                      var isPrinted = printForm.dataset.isPrinted === "1";
                      recordField.value = isPrinted ? "0" : "1";
                    }
                    printForm.dataset.confirmed = "1";
                    printForm.submit();
                  });

                  noBtn.addEventListener("click", function () {
                    if (isEvMode) {
                      if (hasEvDuplicates) {
                        recordModal.style.display = "none";
                        return;
                      }
                      recordField.value = "0";
                      printForm.dataset.confirmed = "1";
                      printForm.submit();
                      return;
                    }

                    var isPrinted = printForm.dataset.isPrinted === "1";
                    if (isPrinted) {
                      recordModal.style.display = "none";
                      return;
                    }
                    recordField.value = "0";
                    printForm.dataset.confirmed = "1";
                    printForm.submit();
                  });
                })();
              </script>

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
          <p>Data WO Nameplate dipisah dari PCB. Upload dan split hanya tersedia untuk admin.</p>
        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">1</span>
            Upload File Nameplate
          </div>
          <form method="POST" enctype="multipart/form-data">
            <label>WO Nameplate</label>
            <input type="text" name="wo_nameplate" placeholder="Masukkan nomor WO Nameplate" required>
            <label>File Number</label>
            <div class="file-input">
              <input type="file" name="file_nameplate" accept=".xlsx,.xls,.txt,.csv" required>
              <button type="submit" name="upload_nameplate">Upload</button>
            </div>
            <?php if (!empty($msgNameplateUpload)): ?>
              <p class="status-msg"><?= htmlspecialchars($msgNameplateUpload) ?></p>
            <?php endif; ?>
          </form>
        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">2</span>
            Split Data Nameplate (1000 / batch)
          </div>
          <form method="POST">
            <label>Select WO Nameplate</label>
            <select name="wo_selected_np" required>
              <option value="">Choose a work order</option>
              <?php
              $woNpRes = $koneksi->query("SELECT wo, COUNT(*) jml FROM nameplate_nomor_barcode WHERE wo IS NOT NULL GROUP BY wo ORDER BY wo");
              while ($woNpRow = $woNpRes->fetch_assoc()) {
                  $woNpVal = htmlspecialchars($woNpRow['wo']);
                  echo "<option value='{$woNpVal}'>{$woNpVal} ({$woNpRow['jml']})</option>";
              }
              ?>
            </select>
            <div class="button-group">
              <button type="submit" name="split_nameplate">Split 1000</button>
              <button type="submit" name="finish_nameplate" onclick="return confirm('Yakin hapus WO Nameplate ini?')">Finish</button>
            </div>
            <?php if (!empty($msgNameplateSplit)): ?>
              <p class="status-msg"><?= htmlspecialchars($msgNameplateSplit) ?></p>
            <?php endif; ?>
            <?php if (!empty($msgNameplateFinish)): ?>
              <p class="status-msg"><?= htmlspecialchars($msgNameplateFinish) ?></p>
            <?php endif; ?>
          </form>
        </div>

        <div class="card dropdown-overlay-card">
          <div class="step-title">
            <span class="icon">3</span>
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
                          $checkNpPrinted = $koneksi->query("SELECT COUNT(*) AS cnt FROM nameplate_log_print WHERE batch_id=$idNp");
                          $rowNpPrinted = $checkNpPrinted ? $checkNpPrinted->fetch_assoc() : ['cnt' => 0];
                          if ((int) $rowNpPrinted['cnt'] > 0) {
                              $labelNp .= ' (Sudah di-record)';
                          }
                          echo "<option value='{$idNp}' {$selNp}>{$labelNp}</option>";
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
              <span class="icon">4</span>
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
	                <div class="warning-msg">Batch nameplate ini sudah di-record. Admin masih bisa print ulang jika diperlukan.</div>
	              <?php endif; ?>
	                <form id="nameplatePrintForm" method="POST" style="margin-top:14px;" data-is-recorded="<?= $isNameplateRecorded ? '1' : '0' ?>">
	                  <input type="hidden" name="menu" value="<?= htmlspecialchars($menu) ?>">
	                  <input type="hidden" name="print_nameplate" value="1">
	                  <input type="hidden" name="batch_id_np" value="<?= (int) $selectedBatchIdNameplate ?>">
	                  <input type="hidden" name="prod_date_np" value="<?= htmlspecialchars($productionDateNameplate) ?>">
	                  <input type="hidden" name="record_nameplate" id="nameplateRecordChoice" value="0">
	                  <button class="primary" type="button" id="nameplatePrintBtn">Print Nameplate Barcode</button>
	                </form>
	                <div id="nameplateConfirmModal" class="modal">
	                  <div class="modal-content">
	                    <h3 id="nameplateConfirmTitle">Konfirmasi Record</h3>
	                    <p id="nameplateConfirmMessage">Record batch ini ke database sebelum cetak?</p>
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
	                    var modalTitle = document.getElementById("nameplateConfirmTitle");
	                    var modalMessage = document.getElementById("nameplateConfirmMessage");

	                    if (!form || !btn || !modal || !recordField || !yesBtn || !noBtn || !modalTitle || !modalMessage) {
	                      return;
	                    }

	                    function openModal() {
	                      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
	                        return false;
	                      }

	                      var isRecorded = form.dataset.isRecorded === "1";
	                      if (isRecorded) {
	                        modalTitle.textContent = "Konfirmasi Print Ulang";
	                        modalMessage.textContent = "Batch ini sudah print, yakin print ulang?";
	                      } else {
	                        modalTitle.textContent = "Konfirmasi Record";
	                        modalMessage.textContent = "Record batch ini ke database sebelum cetak?";
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
	                      var isRecorded = form.dataset.isRecorded === "1";
	                      recordField.value = isRecorded ? "0" : "1";
	                      form.dataset.confirmed = "1";
	                      form.submit();
	                    });

	                    noBtn.addEventListener("click", function () {
	                      var isRecorded = form.dataset.isRecorded === "1";
	                      if (isRecorded) {
	                        modal.style.display = "none";
	                        return;
	                      }
	                      recordField.value = "0";
	                      form.dataset.confirmed = "1";
	                      form.submit();
	                    });
	                  })();
	                </script>
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
      <?php elseif ($menu === 'record'): ?>
        <div class="topbar">
          <h1>Record Print</h1>
          <p>Pilih jenis record yang ingin dilihat: PCB atau Nameplate.</p>
        </div>

        <div class="card">
          <div class="step-title">
            <span class="icon">1</span>
            Filter Record
          </div>
          <form method="GET" class="record-filter">
            <input type="hidden" name="menu" value="record">
            <div style="flex:1;">
              <label>Jenis Record</label>
              <select name="record_type" onchange="this.form.submit()">
                <option value="pcb" <?= $recordType === 'pcb' ? 'selected' : '' ?>>Record PCB</option>
                <option value="nameplate" <?= $recordType === 'nameplate' ? 'selected' : '' ?>>Record Nameplate</option>
                <option value="ev_charger" <?= $recordType === 'ev_charger' ? 'selected' : '' ?>>Record EV Charger</option>
                <option value="ev_charger_sn" <?= $recordType === 'ev_charger_sn' ? 'selected' : '' ?>>Record SN EV Charger</option>
              </select>
            </div>
          </form>

          <div class="record-group">
            <h3>Tabel Record <?= htmlspecialchars($activeRecordLabel) ?></h3>
            <?php if (empty($activeRecordRows)): ?>
              <div class="record-empty">Belum ada record print <?= htmlspecialchars($activeRecordLabel) ?>.</div>
            <?php else: ?>
              <div class="record-toolbar">
                <div class="record-search">
                  <label for="recordNumberSearch">Search Number</label>
                  <input type="text" id="recordNumberSearch" placeholder="Ketik nomor awal/akhir, auto search...">
                </div>
                <button type="button" id="recordDateToggle" class="record-date-toggle">Filter Tanggal</button>
                <div id="recordDateFilterWrap" class="record-date-filter">
                  <div>
                    <label for="recordDateFilter">Tanggal Print</label>
                    <input type="date" id="recordDateFilter">
                  </div>
                  <button type="button" id="recordDateClear" class="record-date-clear">Reset</button>
                </div>
              </div>
              <table class="record-table">
                <thead>
                  <tr>
                    <th>Record Batch</th>
                    <th>Tanggal Print</th>
                    <th>Nomor Awal</th>
                    <th>Nomor Akhir</th>
                    <th>User</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activeRecordRows as $recordItem): ?>
                    <tr>
                      <td><?= htmlspecialchars($recordItem['nama_batch']) ?></td>
                      <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($recordItem['printed_at']))) ?></td>
                      <td><?= htmlspecialchars($recordItem['start_number']) ?></td>
                      <td><?= htmlspecialchars($recordItem['end_number']) ?></td>
                      <td><?= htmlspecialchars($recordItem['username'] ?? '-') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div id="recordSearchEmpty" class="record-empty" style="display:none; margin-top:10px;">Nomor tidak ditemukan.</div>
              <div id="recordPagination" class="record-pagination">
                <div id="recordPaginationInfo" class="record-pagination-info"></div>
                <div class="record-pagination-actions">
                  <button type="button" id="recordPrevPage">Prev</button>
                  <div class="record-page-jump">
                    <label for="recordJumpPage">Page</label>
                    <input type="number" id="recordJumpPage" min="1" step="1">
                    <button type="button" id="recordJumpBtn">Go</button>
                  </div>
                  <button type="button" id="recordNextPage">Next</button>
                </div>
              </div>
              <script>
                (function () {
                  var searchInput = document.getElementById("recordNumberSearch");
                  var dateToggle = document.getElementById("recordDateToggle");
                  var dateWrap = document.getElementById("recordDateFilterWrap");
                  var dateInput = document.getElementById("recordDateFilter");
                  var dateClear = document.getElementById("recordDateClear");
                  var table = document.querySelector(".record-table");
                  var emptyInfo = document.getElementById("recordSearchEmpty");
                  var paginationWrap = document.getElementById("recordPagination");
                  var paginationInfo = document.getElementById("recordPaginationInfo");
                  var prevBtn = document.getElementById("recordPrevPage");
                  var nextBtn = document.getElementById("recordNextPage");
                  var jumpInput = document.getElementById("recordJumpPage");
                  var jumpBtn = document.getElementById("recordJumpBtn");
                  if (!searchInput || !table) {
                    return;
                  }
                  var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr"));
                  var pageSize = 15;
                  var currentPage = 1;
                  var filteredRows = rows.slice();
                  var totalPages = 1;
                  function normalize(value) {
                    return String(value || "").replace(/\s+/g, "").toLowerCase();
                  }
                  function renderPage() {
                    var totalRows = filteredRows.length;
                    totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
                    if (currentPage > totalPages) {
                      currentPage = totalPages;
                    }
                    var startIndex = (currentPage - 1) * pageSize;
                    var endIndex = startIndex + pageSize;
                    rows.forEach(function (row) {
                      row.style.display = "none";
                    });
                    filteredRows.slice(startIndex, endIndex).forEach(function (row) {
                      row.style.display = "";
                    });
                    if (paginationInfo) {
                      if (totalRows === 0) {
                        paginationInfo.textContent = "";
                      } else {
                        paginationInfo.textContent = "Page " + currentPage + " / " + totalPages + " (" + totalRows + " record)";
                      }
                    }
                    if (prevBtn) {
                      prevBtn.disabled = currentPage <= 1 || totalRows === 0;
                    }
                    if (nextBtn) {
                      nextBtn.disabled = currentPage >= totalPages || totalRows === 0;
                    }
                    if (jumpInput) {
                      jumpInput.min = totalRows === 0 ? "0" : "1";
                      jumpInput.max = String(totalPages);
                      jumpInput.value = totalRows === 0 ? "" : String(currentPage);
                      jumpInput.disabled = totalRows === 0;
                    }
                    if (jumpBtn) {
                      jumpBtn.disabled = totalRows === 0;
                    }
                    if (paginationWrap) {
                      paginationWrap.style.display = totalRows > 0 ? "flex" : "none";
                    }
                    if (emptyInfo) {
                      emptyInfo.style.display = totalRows === 0 ? "block" : "none";
                    }
                  }
                  function applySearch(resetPage) {
                    var query = normalize(searchInput.value);
                    var dateQuery = dateInput ? normalize(dateInput.value) : "";
                    filteredRows = rows.filter(function (row) {
                      var cells = row.querySelectorAll("td");
                      var printedDate = cells[1] ? normalize(cells[1].textContent).slice(0, 10) : "";
                      var startNumber = cells[2] ? normalize(cells[2].textContent) : "";
                      var endNumber = cells[3] ? normalize(cells[3].textContent) : "";
                      var matchNumber = query === "" || startNumber.indexOf(query) !== -1 || endNumber.indexOf(query) !== -1;
                      var matchDate = dateQuery === "" || printedDate === dateQuery;
                      return matchNumber && matchDate;
                    });
                    if (resetPage !== false) {
                      currentPage = 1;
                    }
                    renderPage();
                  }
                  if (dateToggle && dateWrap) {
                    dateToggle.addEventListener("click", function () {
                      dateWrap.classList.toggle("active");
                      if (dateWrap.classList.contains("active") && dateInput) {
                        dateInput.focus();
                      }
                    });
                  }
                  if (dateInput) {
                    dateInput.addEventListener("input", function () {
                      applySearch(true);
                    });
                    dateInput.addEventListener("change", function () {
                      applySearch(true);
                    });
                  }
                  if (dateClear) {
                    dateClear.addEventListener("click", function () {
                      if (dateInput) {
                        dateInput.value = "";
                      }
                      applySearch(true);
                    });
                  }
                  if (prevBtn) {
                    prevBtn.addEventListener("click", function () {
                      if (currentPage > 1) {
                        currentPage -= 1;
                        renderPage();
                      }
                    });
                  }
                  if (nextBtn) {
                    nextBtn.addEventListener("click", function () {
                      if (currentPage < totalPages) {
                        currentPage += 1;
                        renderPage();
                      }
                    });
                  }
                  function jumpToPage() {
                    if (!jumpInput) {
                      return;
                    }
                    var targetPage = parseInt(jumpInput.value, 10);
                    if (!targetPage || targetPage < 1) {
                      targetPage = 1;
                    }
                    if (targetPage > totalPages) {
                      targetPage = totalPages;
                    }
                    currentPage = targetPage;
                    renderPage();
                  }
                  if (jumpBtn) {
                    jumpBtn.addEventListener("click", jumpToPage);
                  }
                  if (jumpInput) {
                    jumpInput.addEventListener("keydown", function (event) {
                      if (event.key === "Enter") {
                        event.preventDefault();
                        jumpToPage();
                      }
                    });
                  }
                  searchInput.addEventListener("input", function () {
                    applySearch(true);
                  });
                  applySearch(true);
                })();
              </script>
            <?php endif; ?>
          </div>
        </div>
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
      <?php elseif ($menu === 'setting'): ?>
        <?php
        $usersList = $koneksi->query("SELECT * FROM users ORDER BY id ASC");
        $allUsers = [];
        while ($usersList && $uRow = $usersList->fetch_assoc()) {
            $allUsers[] = $uRow;
        }
        ?>
        <div class="topbar">
          <h1>Setting Sistem</h1>
          <p>Pengaturan akun login pengguna, manajemen Type Meter (Print PCB Barcode), dan manajemen Custom Text EV Charger.</p>
        </div>

        <?php if (!empty($settingMsg)): ?>
          <div class="status-msg" style="margin-bottom: 18px; font-weight: 600; padding: 14px 18px; <?= $settingMsg['type'] === 'error' ? 'border-color: #ef4444; color: #b91c1c; background: rgba(239, 68, 68, 0.08);' : 'border-color: #10b981; color: #047857; background: rgba(16, 185, 129, 0.08);' ?>">
            <?= $settingMsg['type'] === 'error' ? '&#9888; ' : '&#10004; ' ?><?= htmlspecialchars($settingMsg['text']) ?>
          </div>
        <?php endif; ?>

        <!-- CARD 1: MANAJEMEN AKUN -->
        <div class="card">
          <div class="step-title">
            <span class="icon">&#128100;</span>
            Manajemen Akun Login
          </div>
          <p style="margin-top: 0; margin-bottom: 14px; font-size: 13px; color: var(--content-muted);">
            Daftar akun yang dapat login ke sistem PCB Barcode Print beserta username dan passwordnya.
          </p>

          <div style="overflow-x: auto; margin-bottom: 20px;">
            <table class="record-table">
              <thead>
                <tr>
                  <th style="width: 45px; text-align: center;">No</th>
                  <th>Username</th>
                  <th>Password</th>
                  <th style="width: 100px; text-align: center;">Role</th>
                  <th style="width: 150px; text-align: center;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($allUsers)): ?>
                  <tr>
                    <td colspan="5" style="text-align: center; padding: 14px; color: var(--content-muted);">Belum ada akun terdaftar.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($allUsers as $idx => $userItem): ?>
                    <tr>
                      <td style="text-align: center; font-weight: bold;"><?= $idx + 1 ?></td>
                      <td style="font-weight: 600;"><?= htmlspecialchars($userItem['username']) ?></td>
                      <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                          <?php if (!empty($userItem['plain_password'])): ?>
                            <span class="pwd-val" style="font-family: Consolas, monospace; font-size: 13px; font-weight: bold; background: var(--content-panel); padding: 3px 8px; border-radius: 6px; border: 1px solid var(--content-line);">
                              <?= htmlspecialchars($userItem['plain_password']) ?>
                            </span>
                            <button type="button" class="btn-copy-pwd" onclick="copyPassword('<?= htmlspecialchars(addslashes($userItem['plain_password'])) ?>', this)" title="Salin password" style="padding: 2px 8px; font-size: 11px; border-radius: 6px; height: 26px; line-height: 1;">Copy</button>
                          <?php else: ?>
                            <span style="color: var(--content-muted); font-size: 12px; font-style: italic;">(Tersimpan enkripsi hash)</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <span class="badge-role <?= ($userItem['role'] ?? 'user') === 'admin' ? 'badge-role-admin' : 'badge-role-user' ?>">
                          <?= htmlspecialchars($userItem['role'] ?? 'user') ?>
                        </span>
                      </td>
                      <td style="text-align: center;">
                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                          <button type="button" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                              'id' => (int) $userItem['id'],
                              'username' => $userItem['username'],
                              'role' => $userItem['role'] ?? 'user',
                              'plain_password' => $userItem['plain_password'] ?? ''
                          ])) ?>)" style="padding: 4px 10px; font-size: 12px; border-radius: 8px;">Edit</button>

                          <?php if ($userItem['username'] === $_SESSION['username']): ?>
                            <span style="font-size: 11px; color: var(--content-muted); font-style: italic;">(Aktif)</span>
                          <?php else: ?>
                            <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun &quot;<?= htmlspecialchars(addslashes($userItem['username'])) ?>&quot;?');">
                              <input type="hidden" name="menu" value="setting">
                              <input type="hidden" name="user_id" value="<?= (int) $userItem['id'] ?>">
                              <button type="submit" name="delete_user" style="padding: 4px 10px; font-size: 12px; border-radius: 8px; background: rgba(220, 38, 38, 0.1); color: #dc2626; border-color: rgba(220, 38, 38, 0.3);">Hapus</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Form Tambah Akun -->
          <div style="background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); border-radius: 14px; padding: 16px;">
            <h4 style="margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--content-text);">+ Tambah Akun Baru</h4>
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="menu" value="setting">
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                  <label for="new_username">Username</label>
                  <input type="text" id="new_username" name="new_username" placeholder="Masukkan username" required style="margin-bottom: 0;">
                </div>
                <div>
                  <label for="new_password">Password</label>
                  <input type="text" id="new_password" name="new_password" placeholder="Masukkan password" required style="margin-bottom: 0;">
                </div>
                <div>
                  <label for="new_role">Role</label>
                  <select id="new_role" name="new_role" required style="margin-bottom: 0;">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
              </div>
              <button type="submit" name="add_user" class="primary" style="display: inline-flex; align-items: center; gap: 6px;">
                + Simpan Akun Baru
              </button>
            </form>
          </div>
        </div>

        <!-- CARD 2: MANAJEMEN TYPE METER -->
        <div class="card">
          <div class="step-title">
            <span class="icon">&#9881;</span>
            Manajemen Type Meter (Print PCB Barcode)
          </div>
          <p style="margin-top: 0; margin-bottom: 14px; font-size: 13px; color: var(--content-muted);">
            Daftar Type Meter yang tersedia di pilihan dropdown halaman <strong>Print PCB Barcode</strong>. Anda dapat menambah dan menghapus type meter di sini.
          </p>

          <div style="overflow-x: auto; margin-bottom: 20px;">
            <table class="record-table">
              <thead>
                <tr>
                  <th style="width: 45px; text-align: center;">No</th>
                  <th>Nama Type Meter</th>
                  <th style="width: 140px; text-align: center;">Status Dropdown</th>
                  <th style="width: 100px; text-align: center;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($typeMeterOptions)): ?>
                  <tr>
                    <td colspan="4" style="text-align: center; padding: 14px; color: var(--content-muted);">Belum ada pilihan Type Meter.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($typeMeterOptions as $mIdx => $tmOption): ?>
                    <tr>
                      <td style="text-align: center; font-weight: bold;"><?= $mIdx + 1 ?></td>
                      <td style="font-weight: 600; font-family: Consolas, monospace; font-size: 13px;"><?= htmlspecialchars($tmOption) ?></td>
                      <td style="text-align: center;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: bold; background: rgba(16, 185, 129, 0.15); color: #047857; border: 1px solid rgba(16, 185, 129, 0.3);">
                          Aktif di PCB
                        </span>
                      </td>
                      <td style="text-align: center;">
                        <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Type Meter &quot;<?= htmlspecialchars(addslashes($tmOption)) ?>&quot; dari pilihan print barcode?');">
                          <input type="hidden" name="menu" value="setting">
                          <input type="hidden" name="type_meter_name" value="<?= htmlspecialchars($tmOption) ?>">
                          <button type="submit" name="delete_type_meter" style="padding: 4px 10px; font-size: 12px; border-radius: 8px; background: rgba(220, 38, 38, 0.1); color: #dc2626; border-color: rgba(220, 38, 38, 0.3);">Hapus</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Form Tambah Type Meter -->
          <div style="background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); border-radius: 14px; padding: 16px;">
            <h4 style="margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--content-text);">+ Tambah Type Meter Baru</h4>
            <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
              <input type="hidden" name="menu" value="setting">
              <div style="flex: 1; min-width: 240px;">
                <label for="type_meter_name">Nama Type Meter</label>
                <input type="text" id="type_meter_name" name="type_meter_name" placeholder="Contoh: HXE310, HXE118-KP PM..." required style="margin-bottom: 0;">
              </div>
              <button type="submit" name="add_type_meter" class="primary" style="height: 44px; display: inline-flex; align-items: center; gap: 6px;">
                + Tambah Type Meter
              </button>
            </form>
          </div>
        </div>

        <!-- CARD 3: MANAJEMEN CUSTOM TEXT EV CHARGER -->
        <div class="card">
          <div class="step-title">
            <span class="icon">&#9889;</span>
            Manajemen Custom Text EV Charger
          </div>
          <p style="margin-top: 0; margin-bottom: 14px; font-size: 13px; color: var(--content-muted);">
            Daftar pilihan dropdown Custom Text yang tersedia di halaman <strong>Print PCB EV Charger</strong>. Anda dapat menambah dan menghapus pilihan custom text di sini.
          </p>

          <div style="overflow-x: auto; margin-bottom: 20px;">
            <table class="record-table">
              <thead>
                <tr>
                  <th style="width: 45px; text-align: center;">No</th>
                  <th>Nama Custom Text EV Charger</th>
                  <th style="width: 160px; text-align: center;">Status Dropdown</th>
                  <th style="width: 100px; text-align: center;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($evTypeOptions)): ?>
                  <tr>
                    <td colspan="4" style="text-align: center; padding: 14px; color: var(--content-muted);">Belum ada pilihan Custom Text EV Charger.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($evTypeOptions as $evIdx => $evOption): ?>
                    <tr>
                      <td style="text-align: center; font-weight: bold;"><?= $evIdx + 1 ?></td>
                      <td style="font-weight: 600; font-family: Consolas, monospace; font-size: 13px;"><?= htmlspecialchars($evOption) ?></td>
                      <td style="text-align: center;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: bold; background: rgba(14, 165, 233, 0.15); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.3);">
                          Aktif di EV Charger
                        </span>
                      </td>
                      <td style="text-align: center;">
                        <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pilihan Custom Text EV Charger &quot;<?= htmlspecialchars(addslashes($evOption)) ?>&quot;?');">
                          <input type="hidden" name="menu" value="setting">
                          <input type="hidden" name="ev_type_option_name" value="<?= htmlspecialchars($evOption) ?>">
                          <button type="submit" name="delete_ev_type_option" style="padding: 4px 10px; font-size: 12px; border-radius: 8px; background: rgba(220, 38, 38, 0.1); color: #dc2626; border-color: rgba(220, 38, 38, 0.3);">Hapus</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Form Tambah Custom Text EV Charger -->
          <div style="background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); border-radius: 14px; padding: 16px;">
            <h4 style="margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--content-text);">+ Tambah Custom Text EV Charger Baru</h4>
            <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
              <input type="hidden" name="menu" value="setting">
              <div style="flex: 1; min-width: 240px;">
                <label for="ev_type_option_name">Nama Custom Text EV Charger</label>
                <input type="text" id="ev_type_option_name" name="ev_type_option_name" placeholder="Contoh: 2150156800-Electronic lock board" required style="margin-bottom: 0;">
              </div>
              <button type="submit" name="add_ev_type_option" class="primary" style="height: 44px; display: inline-flex; align-items: center; gap: 6px;">
                + Tambah Custom Text
              </button>
            </form>
          </div>
        </div>

        <!-- MODAL EDIT USER -->
        <div id="editUserModal" class="modal" style="display: none;">
          <div class="modal-content" style="max-width: 440px; text-align: left;">
            <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: var(--content-text);">Edit Akun Pengguna</h3>
            <form method="POST" id="editUserForm">
              <input type="hidden" name="menu" value="setting">
              <input type="hidden" name="user_id" id="modalEditUserId">
              <div style="margin-bottom: 12px;">
                <label for="modalEditUsername">Username</label>
                <input type="text" id="modalEditUsername" name="edit_username" required style="width: 100%; box-sizing: border-box; margin-bottom: 0;">
              </div>
              <div style="margin-bottom: 12px;">
                <label for="modalEditPassword">Password Baru</label>
                <input type="text" id="modalEditPassword" name="edit_password" placeholder="Kosongkan jika tidak ingin mengubah password" style="width: 100%; box-sizing: border-box; margin-bottom: 4px;">
                <small style="color: var(--content-muted); font-size: 11px;">Biarkan kosong jika tetap menggunakan password lama.</small>
              </div>
              <div style="margin-bottom: 16px;">
                <label for="modalEditRole">Role</label>
                <select id="modalEditRole" name="edit_role" required style="width: 100%; box-sizing: border-box; margin-bottom: 0;">
                  <option value="user">User</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="modal-actions" style="justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closeEditUserModal()">Batal</button>
                <button type="submit" name="edit_user" class="primary">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>

        <script>
          function openEditUserModal(user) {
            var modal = document.getElementById("editUserModal");
            if (!modal) return;
            document.getElementById("modalEditUserId").value = user.id;
            document.getElementById("modalEditUsername").value = user.username;
            document.getElementById("modalEditPassword").value = user.plain_password || "";
            var roleSelect = document.getElementById("modalEditRole");
            if (roleSelect) {
              roleSelect.value = user.role || "user";
              roleSelect.dispatchEvent(new Event("change", { bubbles: true }));
            }
            modal.style.display = "block";
          }

          function closeEditUserModal() {
            var modal = document.getElementById("editUserModal");
            if (modal) modal.style.display = "none";
          }

          function copyPassword(text, btn) {
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(text).then(function () {
                var oldText = btn.textContent;
                btn.textContent = "Disalin!";
                btn.style.background = "#10b981";
                btn.style.color = "#fff";
                setTimeout(function () {
                  btn.textContent = oldText;
                  btn.style.background = "";
                  btn.style.color = "";
                }, 1500);
              });
            } else {
              var ta = document.createElement("textarea");
              ta.value = text;
              document.body.appendChild(ta);
              ta.select();
              document.execCommand("copy");
              document.body.removeChild(ta);
              var oldText = btn.textContent;
              btn.textContent = "Disalin!";
              setTimeout(function () {
                btn.textContent = oldText;
              }, 1500);
            }
          }

          window.addEventListener("click", function (e) {
            var modal = document.getElementById("editUserModal");
            if (modal && e.target === modal) {
              closeEditUserModal();
            }
          });
        </script>
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




