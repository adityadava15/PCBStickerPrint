<?php
require 'koneksi.php';
require 'session.php';

// Jika sudah login, langsung ke halaman utama
if (!empty($_SESSION['user_id'])) {
    header("Location: Backup.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $con->prepare("SELECT id, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['last_activity'] = time();
            header("Location: Backup.php");
            exit;
        } else {
            $error = "Password salah";
        }
    } else {
        $error = "User tidak ditemukan";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body>
<h2>Login</h2>
<form method="POST">
    <label>Username:</label><br>
    <input type="text" name="username" required><br><br>
    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>
    <button type="submit">Login</button>
</form>
<p style="color:red;"><?= htmlspecialchars($error) ?></p>
</body>
