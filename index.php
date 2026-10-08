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
}

elseif ($u['role'] !== $role) {
      $error = $role === 'admin'
        ? 'This account is registered as a member, not an administrator.'
        : 'This account is an administrator. Choose “Administrator” to sign in.';
    } else {
      $sessionUser = [
  'id' => $u['id'],
  'name' => $u['name'],
  'email' => $u['email'],
  'role' => $u['role'],
  // --- ADD OR VERIFY THIS EXACT LINE IS PRESENT ---
  'is_super' => (int)($u['is_super'] ?? 0), 
  'blood_group' => $u['blood_group'],
  'profile_picture' => $u['profile_picture'] ?? null
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

/* Main Top Grid: About on left, Login on right */
.landing .hero-public{display:grid;grid-template-columns:1.25fr .75fr;gap:45px;align-items:start;padding:25px 0 45px}

/* About Block Adjustments */
.about-block h1{font-size:clamp(32px,4.5vw,48px);line-height:1.1;margin:0 0 15px}
.about-block h2{font-size:22px;color:#d71920;margin:0 0 15px}
.about-block p{font-size:16px;line-height:1.6;color:#dbe4e8;margin-bottom:15px}

/* Founder Row inside the About column */
.founder-row {display:flex;align-items:center;gap:20px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:15px;margin-top:20px}
.founder-img {width:70px;height:70px;object-fit:cover;border-radius:50%;border:2px solid #d71920}
.founder-info strong {display:block;font-size:18px;color:#fff}
.founder-info span {font-size:14px;color:#a2b4be}

/* Bottom Grid for Cards */
.ad-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding:35px 0 75px;border-top:1px solid rgba(255,255,255,.08)}
.ad-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:22px}
.ad-card b{display:block;font-size:18px;margin-bottom:8px;color:#fff}
.ad-card span{font-size:14px;line-height:1.5;color:#dbe4e8}

/* Login Card Keepers */
.login-card{background:#fff;color:#14212b;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.3);position:sticky;top:20px}
.login-card h2{margin-top:0;font-size:24px}
.role-switch{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0 20px}
.role-switch label{border:1px solid #ddd;border-radius:10px;padding:12px;text-align:center;cursor:pointer;font-weight:700;font-size:14px}
.role-switch input{margin-right:7px}
.login-error{background:#fff0f1;color:#9e1c25;padding:11px;border-radius:8px;margin-bottom:14px}
.signup-note{font-size:13px;text-align:center;margin-top:15px}
.signup-note a{color:#d71920;font-weight:700}

@media(max-width:950px){
  .landing .hero-public{grid-template-columns:1fr;gap:35px}
  .login-card{max-width:100%;position:static}
  .ad-grid{grid-template-columns:1fr;padding-bottom:45px}
}
</style>
</head>
<body class="landing">
<div class="container topbar">
  <div class="brandline"><img src="<?=base_url('assets/images/logo.svg')?>" alt="Odynasties"><span>Odynasties</span></div>
</div>

<main class="container">
  <!-- Top Section: About next to Login -->
  <div class="hero-public">
    
    <!-- Left Column: About & History -->
    <section class="about-block">
      <h1>One Blood Group.<br>One Community.<br>One Dynasty.</h1>
      <h2>Welcome to Odynasties</h2>
      
      <p>
        The name <strong>Odynasties</strong> represents the lineage, unity, and strength shared by those with the Type O blood group. Often recognized as universal blood donors, Type O individuals possess a unique biological connection that enables them to sustain lives across the globe. Our name celebrates this shared legacy as a global family—a dynasty built on compassion and shared responsibility.
      </p>
      
      <p>
        Founded with a vision to transform a shared biological trait into a support engine, Odynasties began as a network of dedicated individuals. We recognized that while Type O blood is always in high demand, finding reliable, local donor connections during unexpected emergencies presented constant challenges. 
      </p>

      <!-- Inline Founder Card -->
      <div class="founder-row">
        <!-- UPDATE PATH: Replace 'assets/images/founder.jpg' with your real filename -->
        <img src="<?=base_url('assets/images/founder.jpg')?>" alt="Founder of Odynasties" class="founder-img" onerror="this.src='https://placeholder.com';">
        <div class="founder-info">
          <strong>[Founder's Full Name]</strong>
          <span>Founder, Odynasties Dynasty & History</span>
        </div>
      </div>
    </section>

    <!-- Right Column: Login Card (Pinned at the Top) -->
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

  </div>

  <!-- Bottom Section: Rest of the Content (Features / Value Props) -->
  <section class="ad-grid">
    <div class="ad-card"><b>🩸 Donate & Save Lives</b><span>Support blood donation and help connect donors when needed.</span></div>
    <div class="ad-card"><b>🤝 Community Support</b><span>Share experiences and build a stronger O blood group community.</span></div>
    <div class="ad-card"><b>🌍 One Global Community</b><span>Connect with people who share the same blood group and values.</span></div>
  </section>
</main>

</body>
</html>
