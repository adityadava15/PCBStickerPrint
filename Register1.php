<!-- register.php -->
<?php
session_start();
if (isset($_SESSION['username'])) {
    if ($_SESSION['role']==='admin'){
        header("Location: view.php");
    }else{
    header("Location: userview.php");
    }
    exit;
}

$koneksi = new mysqli("192.168.88.254:3307", "root", "", "test");
if ($koneksi->connect_errno) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

$error = '';
$success = '';
if (isset($_POST['register'])) {
    $rawUsername = trim($_POST['username'] ?? '');
    $rawPassword = (string) ($_POST['password'] ?? '');
    $username = $koneksi->real_escape_string($rawUsername);
    $password = password_hash($rawPassword, PASSWORD_DEFAULT);
    $plainPassword = $koneksi->real_escape_string($rawPassword);
    $role = 'user'; // Default role user, admin bisa di-set manual di DB

    $check = $koneksi->query("SELECT * FROM users WHERE username='$username'");
    if ($check->num_rows > 0) {
        $error = "Username sudah ada!";
    } else {
        $koneksi->query("INSERT INTO users (username, password, role, plain_password) VALUES ('$username', '$password', '$role', '$plainPassword')");
        $success = "Registrasi berhasil! Silakan login.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Register - PCB Barcode Print</title>
  <link rel="icon" type="image/png" sizes="32x32" href="IMG/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="IMG/favicon-16x16.png">
  <link rel="shortcut icon" href="IMG/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="IMG/apple-touch-icon.png">
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
      margin: 0 auto;
      max-width: 400px;
    }

    label {
      display: block;
      margin-bottom: 5px;
      color: #495057;
      font-size: 0.9em;
    }

    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ced4da;
      border-radius: 5px;
      margin-bottom: 15px;
      box-sizing: border-box;
      background-color: #f8f9fa;
    }

    button {
      width: 100%;
      padding: 10px;
      background-color: #6c757d;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .error {
      color: #dc3545;
      margin-bottom: 15px;
    }

    .success {
      color: #28a745;
      margin-bottom: 15px;
    }

    .login-link {
      text-align: center;
      margin-top: 15px;
    }
  </style>
</head>
<body>
  <header>
    <h1>PCB Barcode Print</h1>
    <p class="subtitle">Register a new account</p>
  </header>

  <div class="card">
    <?php if ($error): ?>
      <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
      <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>
    <form method="POST">
      <label>Username</label>
      <input type="text" name="username" placeholder="Enter username" required>
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter password" required>
      <button type="submit" name="register">Register</button>
    </form>
    <div class="login-link">
      <a href="Login1.php">Already have an account? Login here</a>
    </div>
  </div>
</body>
</html>