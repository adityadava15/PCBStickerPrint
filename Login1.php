<!-- login.php -->
<?php
session_start();
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: view.php");
    } else {
        header("Location: userview.php");
    }
    exit;
}

$koneksi = new mysqli("localhost", "root", "", "test");
if ($koneksi->connect_errno) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

$error = '';
if (isset($_POST['login'])) {
    $username = $koneksi->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $koneksi->query("SELECT * FROM users WHERE username='$username'");
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'admin') {
            header("Location: view.php");
        } else {
            header("Location: userview.php");
        }
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login - PCB Barcode Print</title>
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

    .register-link {
      text-align: center;
      margin-top: 15px;
    }
  </style>
</head>
<body>
  <header>
    <h1>PCB Barcode Print</h1>
    <p class="subtitle">Login to access the dashboard</p>
  </header>

  <div class="card">
    <?php if ($error): ?>
      <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST">
      <label>Username</label>
      <input type="text" name="username" placeholder="Enter username" required>
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter password" required>
      <button type="submit" name="login">Login</button>
    </form>
    <div class="register-link">
      <a href="Register1.php">Register here</a>
    </div>
  </div>
</body>
</html>