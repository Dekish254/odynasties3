<?php
$title='Odynasties — Welcome';
require 'config/config.php';

if (is_admin()) { header('Location: admin/'); exit; }
if (is_member()) { header('Location: member-home.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $role = $_POST['login_as'] ?? '';
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (!in_array($role, ['admin','member'], true)) {
    $error = 'Please choose whether you are signing in as an administrator or member.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $error = 'Enter a valid email address and password.';
  } else {
    $s = $pdo->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $s->execute([$email]);
    $u = $s->fetch();

    if (!$u) {
      $error = $role === 'member'
        ? 'No member account was found with that email. Please sign up as a member.'
        : 'No administrator account was found with that email. Ask an existing administrator to create your administrator account.';
    } elseif (($u['status'] ?? 'active') !== 'active' || !password_verify($password, $u['password_hash'])) {
      $error = 'The email, password or account status is not valid.';
    } elseif ($u['role'] !== $role) {
      $error = $role === 'admin'
        ? 'This account is registered as a member, not an administrator.'
        : 'This account is an administrator. Choose “Administrator” to sign in.';
    } else {
      $sessionUser = [
        'id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role'],
        'blood_group'=>$u['blood_group'],'profile_picture'=>$u['profile_picture']??null
      ];
      $token = bin2hex(random_bytes(32));
      $pdo->prepare('INSERT INTO login_sessions(user_id,session_token,role,last_seen) VALUES(?,?,?,NOW())')
          ->execute([$u['id'],$token,$u['role']]);

      if ($u['role']==='admin') {
        start_role_session('admin');
        $_SESSION['admin_user']=$sessionUser;
        $_SESSION['admin_session_token']=$token;
        audit_admin_action('Administrator login', 'Successful administrator login');
        header('Location: admin/'); exit;
      }

      start_role_session('member');
      $_SESSION['member_user']=$sessionUser;
      $_SESSION['member_session_token']=$token;
      header('Location: member-home.php'); exit;
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?></title>
<link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>">
<style>
.landing{min-height:100vh;background:#071722;color:#fff}
.landing .topbar{padding:20px 0}
.landing .brandline{display:flex;align-items:center;gap:12px;font-weight:800;font-size:25px}
.landing .brandline img{width:52px;height:52px}
.landing .hero-public{min-height:calc(100vh - 90px);display:grid;grid-template-columns:1.25fr .75fr;gap:55px;align-items:center;padding:45px 0 75px}
.landing .ad-copy h1{font-size:clamp(42px,6vw,72px);line-height:1;margin:0 0 15px}
.landing .ad-copy h2{font-size:25px;margin:0 0 15px}
.landing .ad-copy p{font-size:18px;line-height:1.7;color:#dbe4e8;max-width:680px}
.ad-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:28px}
.ad-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px}
.ad-card b{display:block;margin-bottom:6px}
.login-card{background:#fff;color:#14212b;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.3)}
.login-card h2{margin-top:0}
.role-switch{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0 20px}
.role-switch label{border:1px solid #ddd;border-radius:10px;padding:12px;text-align:center;cursor:pointer;font-weight:700}
.role-switch input{margin-right:7px}
.login-error{background:#fff0f1;color:#9e1c25;padding:11px;border-radius:8px;margin-bottom:14px}
.signup-note{font-size:13px;text-align:center;margin-top:15px}
.signup-note a{color:#d71920;font-weight:700}
@media(max-width:850px){.landing .hero-public{grid-template-columns:1fr}.ad-grid{grid-template-columns:1fr}.landing .login-card{max-width:520px}}
</style>
</head>
<body class="landing">
<div class="container topbar">
  <div class="brandline"><img src="<?=base_url('assets/images/logo.svg')?>" alt="Odynasties"><span>Odynasties</span></div>
</div>
<main class="container hero-public">
  <section class="ad-copy">
    <h1>One Blood Group.<br>One Community.<br>One Dynasty.</h1>
    <h2>Welcome to Odynasties</h2>
    <p>A community for people with blood group O to connect, support one another, share experiences, give back and grow together.</p>
    <div class="ad-grid">
      <div class="ad-card"><b>🩸 Donate & Save Lives</b><span>Support blood donation and help connect donors when needed.</span></div>
      <div class="ad-card"><b>🤝 Community Support</b><span>Share experiences and build a stronger O blood group community.</span></div>
      <div class="ad-card"><b>🌍 One Global Community</b><span>Connect with people who share the same blood group and values.</span></div>
    </div>
  </section>

  <section class="login-card">
    <h2>Sign in to Odynasties</h2>
    <p class="muted">Choose how you are registered before signing in.</p>
    <?php if($error): ?><div class="login-error"><?=e($error)?></div><?php endif; ?>
    <form method="post">
      <div class="field">
        <label>Login as</label>
        <div class="role-switch">
          <label><input type="radio" name="login_as" value="member" checked> Member</label>
          <label><input type="radio" name="login_as" value="admin"> Administrator</label>
        </div>
      </div>
      <div class="field"><label>Email</label><input type="email" name="email" required autocomplete="username"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn" style="width:100%">Sign In</button>
    </form>
    <div class="signup-note">Not registered as a member? <a href="register.php">Sign up here</a></div>
    <div class="signup-note">Administrator accounts are created or promoted by an existing administrator.</div>
  </section>
</main>
</body>
</html>
