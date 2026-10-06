<?php
require __DIR__.'/config/config.php';

$setupToken = envv('ADMIN_SETUP_TOKEN', '');
if ($setupToken === '') { http_response_code(404); exit('Not found'); }

if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0) {
  http_response_code(409);
  exit('An administrator already exists. Remove ADMIN_SETUP_TOKEN and use the admin panel.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['token'] ?? '');
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (!hash_equals($setupToken, $token)) {
    http_response_code(403); exit('Invalid setup token.');
  }
  if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    exit('Use a valid name/email and a password of at least 12 characters.');
  }

  $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,'admin','active')");
  $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
  echo 'Administrator created. Remove ADMIN_SETUP_TOKEN from Render now.';
  exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Setup</title>
<style>body{font-family:Arial,sans-serif;background:#f4f7f9;padding:30px}.box{max-width:520px;margin:30px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px #0001}input{width:100%;padding:12px;margin:7px 0 16px;box-sizing:border-box}button{padding:12px 18px;background:#d71920;color:#fff;border:0;border-radius:7px;font-weight:700}</style></head>
<body><div class="box"><h1>Create first administrator</h1><p>This one-time setup is enabled only when <code>ADMIN_SETUP_TOKEN</code> is set.</p>
<form method="post"><label>Setup token</label><input type="password" name="token" required><label>Name</label><input name="name" required><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" minlength="12" required><button>Create administrator</button></form></div></body></html>
