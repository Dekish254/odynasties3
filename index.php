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
        'id' => $u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'role' => $u['role'],
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

/* Main Top Grid Layout */
.landing .hero-public{display:grid;grid-template-columns:1.25fr .75fr;gap:55px;align-items:start;padding:25px 0 45px}

/* Left Column Styling: Split into Founder Profile on left, Biography on right */
.about-grid-inner {display:grid;grid-template-columns:1fr 1.6fr;gap:35px;align-items:start;margin-top:10px}

/* Large Founder Profile Styles */
.founder-image-wrapper {text-align:center}
.founder-img-large {width:100%;max-width:260px;height:auto;aspect-ratio:1/1;object-fit:cover;border-radius:50%;border:4px solid #d71920;box-shadow:0 10px 30px rgba(0,0,0,.4)}
.founder-title {margin-top:12px;font-size:14px;color:#dbe4e8}
.founder-title strong {display:block;font-size:18px;color:#fff;margin-bottom:2px}

/* Connect with Us Badge Row Styles */
.connect-container {margin-top:20px;padding-top:15px;border-top:1px solid rgba(255,255,255,.08)}
.connect-title {font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#a2b4be;margin-bottom:10px}
.social-links {display:flex;justify-content:center;flex-wrap:wrap;gap:8px}
.social-btn {display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;text-decoration:none;font-size:15px;font-weight:bold;transition:all 0.2s ease}
.social-btn:hover {background:#d71920;border-color:#d71920;transform:translateY(-2px)}

/* Optimized Scaled Down Text Elements */
.about-content h1{font-size:clamp(24px,3.5vw,34px);line-height:1.2;margin:0 0 15px}
.about-content h3 {font-size:18px;margin:20px 0 8px;color:#fff;position:relative}
.about-content h3::after {content:'';display:block;width:30px;height:2px;background:#d71920;margin-top:6px}
.about-content p {font-size:14px;line-height:1.6;color:#dbe4e8;margin-bottom:12px}

/* Bottom Grid for Value Proposition Cards */
.ad-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding:35px 0 75px;border-top:1px solid rgba(255,255,255,.08)}
.ad-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:22px}
.ad-card b{display:block;font-size:18px;margin-bottom:8px;color:#fff}
.ad-card span{font-size:14px;line-height:1.5;color:#dbe4e8}

/* Login Side-Card Container */
.login-card{background:#fff;color:#14212b;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.3);position:sticky;top:20px}
.login-card h2{margin-top:0;font-size:24px;color:#14212b}
.login-card .muted{color:#556675;font-size:14px;margin-bottom:15px}
.role-switch{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0 20px}
.role-switch label{border:1px solid #ddd;border-radius:10px;padding:12px;text-align:center;cursor:pointer;font-weight:700;font-size:14px;color:#14212b}
.role-switch input {margin-right:7px}
.login-error{background:#fff0f1;color:#9e1c25;padding:11px;border-radius:8px;margin-bottom:14px}
.signup-note{font-size:13px;text-align:center;margin-top:15px;color:#14212b}
.signup-note a{color:#d71920;font-weight:700;text-decoration:none}
.field {margin-bottom:15px}
.field label {display:block;margin-bottom:5px;font-weight:700;font-size:14px;color:#14212b}
.field input[type="email"], .field input[type="password"] {width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;background:#fff;color:#14212b}

@media(max-width:1100px){
  .about-grid-inner {grid-template-columns:1fr;gap:30px;text-align:center}
  .about-content h3::after {margin:6px auto 0}
  .founder-img-large {max-width:200px}
}
@media(max-width:950px){
  .landing .hero-public{grid-template-columns:1fr;gap:45px}
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
  <div class="hero-public">
    
    <!-- Left Column Container: Holds everything next to the Login Card -->
    <section class="about-column-wrapper">
      
      <!-- Inner Two-Column Layout for Founder Image (Left) & About Content (Right) -->
      <div class="about-grid-inner">
        
        <!-- Left Side: Large Portrait, Name Details & Social Channels -->
        <div class="founder-image-wrapper">
          <img src="<?=base_url('assets/images/founder.jpg')?>" alt="Founder of Odynasties" class="founder-img-large" onerror="this.src='https://placeholder.com';">
          
          <div class="founder-title">
            <strong>[Founder's Full Name]</strong>
            Founder, Odynasties
          </div>

          <!-- Connect with Us Block -->
          <div class="connect-container">
            <div class="connect-title">Connect with Us</div>
            <div class="social-links">
              <a href="https://wa.me" class="social-btn" target="_blank" title="WhatsApp">WA</a>
              <a href="mailto:your-email@odynasties.com" class="social-btn" title="Email">@</a>
              <a href="https://facebook.com" class="social-btn" target="_blank" title="Facebook">FB</a>
              <a href="https://tiktok.com" class="social-btn" target="_blank" title="TikTok">TT</a>
              <a href="tel:+YOURPHONENUMBER" class="social-btn" title="Phone">📞</a>
            </div>
          </div>
        </div>

        <!-- Right Side: The Narrative Text -->
        <div class="about-content">
          <h1>One Blood Group.<br>One Community.<br>One Dynasty.</h1>
          
          <h3>The Meaning & Origin</h3>
          <p>
            The name <strong>Odynasties</strong> represents the lineage, unity, and strength shared by those with the Type O blood group. Often recognized as universal blood donors, Type O individuals possess a unique biological connection that enables them to sustain lives across the globe. Our name celebrates this shared legacy as a global family—a dynasty built on compassion and shared responsibility.
          </p>
          
          <h3>Our Journey & History</h3>
          <p>
            Founded with a vision to transform a shared biological trait into a support engine, Odynasties began as a network of dedicated individuals. We recognized that while Type O blood is always in high demand, finding reliable, local donor connections during unexpected emergencies presented constant challenges. 
          </p>
          <p>
            What started as an urgent initiative has evolved into an integrated, interactive system. Today, Odynasties bridges modern web technology with grassroots healthcare outreach, ensuring that our collective strength is accessible to members whenever and wherever they need support.
          </p>
        </div>

      </div>
    </section>

    <!-- Right Column: Login Card Container -->
    <section class="login-card">
      <h2>Sign in to Odynasties</h2>
      <p class="muted">Choose how you are registered before signing in.</p>
      
