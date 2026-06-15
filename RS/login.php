<?php
// login.php
require_once 'config/auth.php';
require_once 'config/db.php';

// If already logged in, go straight to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

// POST — attempt login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare(
            'SELECT user_id, password, full_name, role, is_active FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['username']   = $username;
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['user_role']  = $user['role'];

            // Update last_login timestamp
            $conn->query("UPDATE users SET last_login = NOW() WHERE user_id = {$user['user_id']}");
            $conn->close();

            $redirect = $_GET['redirect'] ?? 'index.php';
            // Safety: only allow relative redirects
            if (!preg_match('/^\//', $redirect)) $redirect = 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid username or password.';
            if ($conn) $conn->close();
        }
    }
}

// GET message (e.g. after logout)
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Caba Cloud Analytics — Login</title>
<link rel="stylesheet" href="style.css">
<style>
  /* ── Modern Premium Theme Design ── */
  :root {
    --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    --card-bg: rgba(30, 41, 59, 0.7);
    --border-color: rgba(255, 255, 255, 0.08);
    --text-main: #f8fafc;
    --text-muted: #94a3b8;
    --accent-emerald: #10b981;
    --accent-glow: rgba(16, 185, 129, 0.2);
    --input-bg: rgba(15, 23, 42, 0.6);
  }

  * {
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
  }

  body { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    min-height: 100vh; 
    background: var(--bistro-bg, var(--bg-gradient)); 
    margin: 0;
    padding: 20px;
    color: var(--bistro-text, var(--text-main));
    overflow-x: hidden;
  }

  /* Soft ambient background glow effects */
  body::before {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    background: var(--accent-glow);
    border-radius: 50%;
    filter: blur(80px);
    top: 20%;
    left: 25%;
    z-index: -1;
  }

  .login-card {
    background: var(--bistro-card, var(--card-bg));
    border: 1px solid var(--bistro-border, var(--border-color));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 16px;
    padding: 48px 40px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
    transition: transform 0.3s ease;
  }

  .login-logo {
    text-align: center;
    margin-bottom: 32px;
  }
  
  .login-logo .brand-logo { 
    font-size: 1.65rem; 
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
  }

  .login-logo .brand-logo span {
    filter: drop-shadow(0 2px 8px rgba(16, 185, 129, 0.4));
  }

  .login-logo p { 
    color: var(--bistro-muted, var(--text-muted)); 
    font-size: .9rem; 
    margin-top: 8px;
    font-weight: 400;
  }

  .form-group { 
    margin-bottom: 24px; 
    position: relative;
  }
  
  .form-group label { 
    display: block; 
    margin-bottom: 8px; 
    font-size: .85rem; 
    font-weight: 500;
    color: var(--bistro-muted, var(--text-muted)); 
    letter-spacing: 0.01em;
  }
  
  .form-group input {
    width: 100%;
    background: var(--bistro-input, var(--input-bg));
    border: 1px solid var(--bistro-border, var(--border-color));
    border-radius: 8px;
    padding: 12px 16px;
    color: var(--bistro-text, var(--text-main));
    font-size: .95rem;
    transition: all .25s ease;
  }
  
  .form-group input:focus { 
    outline: none; 
    border-color: var(--bistro-emerald, var(--accent-emerald)); 
    box-shadow: 0 0 0 4px var(--accent-glow);
    background: rgba(15, 23, 42, 0.8);
  }

  .form-group input::placeholder {
    color: rgba(148, 163, 184, 0.4);
  }

  .btn-login {
    width: 100%;
    padding: 14px;
    background: var(--bistro-emerald, var(--accent-emerald));
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s ease;
    margin-top: 8px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
  }
  
  .btn-login:hover { 
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
  }

  .btn-login:active {
    transform: translateY(1px);
  }

  /* Styled Alert system */
  .alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: .88rem;
    margin-bottom: 24px;
    line-height: 1.4;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: fadeIn 0.3s ease-out;
  }
  
  .alert-error { 
    background: rgba(153, 27, 27, 0.4); 
    border: 1px solid #ef4444; 
    color: #fca5a5; 
  }
  
  .alert-success { 
    background: rgba(6, 78, 59, 0.4); 
    border: 1px solid #10b981; 
    color: #a7f3d0; 
  }

  .login-footer { 
    text-align: center; 
    margin-top: 32px; 
    font-size: .8rem; 
    color: var(--bistro-muted, var(--text-muted));
    opacity: 0.8;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Responsive fixes */
  @media (max-width: 480px) {
    .login-card {
      padding: 32px 24px;
      box-shadow: none;
      background: transparent;
      border: none;
    }
    body {
      background: #0f172a;
    }
  }
</style>
</head>
<body>

<div class="login-card">
  <div class="login-logo">
    <span class="brand-logo"><span>🍽️</span> Caba Cloud Analytics</span>
    <p>Restaurant Management System</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>">
    <div class="form-group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             placeholder="Enter your username" autocomplete="username" required>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="Enter your password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn-login">Sign In</button>
  </form>

  <div class="login-footer">
    &copy; <?= date('Y') ?> Caba Cloud Analytics &mdash; All rights reserved.
  </div>
</div>

</body>
</html>