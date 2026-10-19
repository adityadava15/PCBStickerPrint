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

$koneksi = new mysqli("192.168.88.254:3307", "root", "", "test");
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - PCB Barcode Print</title>
  <style>
    :root {
      --bg: #081a32;
      --card: #f5f8fd;
      --text: #102847;
      --muted: #5d7597;
      --line: #c8d7eb;
      --line-strong: #9fb5d4;
      --accent-a: #0a1c35;
      --accent-b: #1a4b84;
      --button: #123968;
      --button-hover: #1a4b84;
      --danger-bg: #fcecef;
      --danger-text: #9f2643;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: "Trebuchet MS", "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at top left, rgba(59, 110, 184, 0.38), transparent 30%),
        radial-gradient(circle at bottom right, rgba(22, 61, 115, 0.42), transparent 34%),
        linear-gradient(160deg, #061426 0%, #081a32 45%, #10315a 100%);
      color: #f3f7fd;
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    a {
      color: inherit;
    }

    .login-shell {
      width: 100%;
      max-width: 430px;
    }

    .login-frame {
      background: linear-gradient(135deg, var(--accent-a), var(--accent-b));
      border-radius: 24px;
      padding: 2px;
      box-shadow: 0 24px 60px rgba(3, 10, 22, 0.42);
    }

    .card {
      background:
        linear-gradient(180deg, rgba(248, 251, 255, 0.98) 0%, rgba(239, 246, 255, 0.96) 100%);
      border-radius: 22px;
      padding: 34px 30px 28px;
      backdrop-filter: blur(6px);
    }

    .brand {
      text-align: center;
      margin-bottom: 26px;
    }

    .brand-mark {
      display: inline-block;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.24em;
      text-transform: uppercase;
      color: #4e6a92;
      margin-bottom: 14px;
    }

    h1 {
      font-size: 2.25rem;
      line-height: 1.05;
      margin: 0 0 10px;
      font-weight: 700;
      color: var(--text);
    }

    .subtitle {
      color: var(--muted);
      font-size: 0.98rem;
      margin: 0;
    }

    .subtitle a {
      color: var(--text);
      font-weight: 600;
      text-underline-offset: 3px;
    }

    .error {
      background: var(--danger-bg);
      color: var(--danger-text);
      border: 1px solid #f3c4cf;
      border-radius: 14px;
      padding: 12px 14px;
      margin-bottom: 18px;
      font-size: 0.95rem;
    }

    label {
      display: block;
      margin-bottom: 8px;
      color: #6c646a;
      font-size: 0.98rem;
      font-weight: 500;
    }

    form {
      margin: 0;
    }

    .field {
      margin-bottom: 18px;
    }

    .input-wrap {
      position: relative;
    }

    input[type="text"], input[type="password"] {
      width: 100%;
      height: 56px;
      padding: 0 18px;
      border: 1px solid var(--line-strong);
      border-radius: 14px;
      background-color: #ffffff;
      color: var(--text);
      font-size: 1rem;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    input[type="password"] {
      padding-right: 88px;
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: #4c78b1;
      box-shadow: 0 0 0 4px rgba(26, 75, 132, 0.16);
    }

    .toggle-password {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: #5c7191;
      font-size: 0.95rem;
      cursor: pointer;
      padding: 6px 8px;
      border-radius: 999px;
    }

    .toggle-password:hover {
      background: rgba(18, 57, 104, 0.08);
    }

    .form-note {
      text-align: right;
      margin: -2px 0 18px;
      font-size: 0.95rem;
    }

    .form-note a {
      color: var(--text);
      text-decoration: underline;
      text-underline-offset: 3px;
      font-weight: 600;
    }

    .login-button {
      width: 100%;
      height: 56px;
      background-color: var(--button);
      color: #fff;
      border: none;
      border-radius: 999px;
      cursor: pointer;
      font-size: 1.15rem;
      font-weight: 700;
      letter-spacing: 0.01em;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }

    .login-button:hover {
      background-color: var(--button-hover);
      transform: translateY(-1px);
    }

    .register-link {
      text-align: center;
      margin-top: 18px;
      color: var(--muted);
      font-size: 0.96rem;
    }

    .register-link a {
      color: var(--text);
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    @media (max-width: 480px) {
      body {
        padding: 16px;
      }

      .card {
        padding: 28px 22px 24px;
      }

      h1 {
        font-size: 1.9rem;
      }
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <div class="login-frame">
      <div class="card">
        <div class="brand">
          <div class="brand-mark">PCB Barcode Print</div>
          <h1>Log in</h1>
          <p class="subtitle">Belum punya akun? <a href="Register1.php">Daftar di sini</a></p>
        </div>

        <?php if ($error): ?>
          <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST">
          <div class="field">
            <label for="username">Username</label>
            <div class="input-wrap">
              <input type="text" id="username" name="username" placeholder="Masukkan username" autocomplete="username" required>
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
              <button type="button" class="toggle-password" id="togglePassword" aria-label="Tampilkan password">Show</button>
            </div>
          </div>

          <div class="form-note">
            <a href="Register1.php">Butuh akun?</a>
          </div>

          <button class="login-button" type="submit" name="login">Log in</button>
        </form>

        <div class="register-link">
          Halaman ini hanya memakai login <strong>username</strong> dan <strong>password</strong>.
        </div>
      </div>
    </div>
  </main>

  <script>
    (function () {
      var toggle = document.getElementById("togglePassword");
      var input = document.getElementById("password");

      if (!toggle || !input) {
        return;
      }

      toggle.addEventListener("click", function () {
        var isHidden = input.type === "password";
        input.type = isHidden ? "text" : "password";
        toggle.textContent = isHidden ? "Hide" : "Show";
        toggle.setAttribute("aria-label", isHidden ? "Sembunyikan password" : "Tampilkan password");
      });
    })();
  </script>
</body>
</html>
