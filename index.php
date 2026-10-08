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
.landing .hero-public{min-height:calc(100vh - 90px);display:grid;grid-template-columns:1.25fr .75fr;gap:55px;align-items:start;padding:25px 0 45px}

/* Left Column Styling: Split into Founder Profile on left, Biography on right */
.about-grid-inner {display:grid;grid-template-columns:1fr 1.5fr;gap:40px;align-items:start;margin-top:20px}

/* Large Founder Profile Styles */
.founder-image-wrapper {text-align:center}
.founder-img-large {width:100%;max-width:320px;height:auto;aspect-ratio:1/1;object-fit:cover;border-radius:50%;border:4px solid #d71920;box-shadow:0 10px 30px rgba(0,0,0,.4)}
.founder-title {margin-top:15px;font-size:16px;color:#dbe4e8}
.founder-title strong {display:block;font-size:22px;color:#fff;margin-bottom:2px}

/* Connect with Us Module Styles */
.connect-container {margin-top:25px;padding-top:15px;border-top:1px solid rgba(255,255,255,.08)}
.connect-title {font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#a2b4be;margin-bottom:12px}
.social-links {display:flex;justify-content:center;flex-wrap:wrap;gap:10px}
.social-btn {display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;text-decoration:none;transition:all 0.2s ease}
.social-btn svg {width:20px;height:20px;fill:#fff;transition:fill 0.2s ease}
.social-btn:hover {background:#d71920;border-color:#d71920;transform:translateY(-2px)}

/* Text Content Container */
.about-content h1{font-size:clamp(32px,4vw,44px);line-height:1.1;margin:0 0 20px}
.about-content h3 {font-size:24px;margin:25px 0 10px;color:#fff;position:relative}
.about-content h3::after {content:'';display:block;width:40px;height:3px;background:#d71920;margin-top:8px}
.about-content p {font-size:16px;line-height:1.7;color:#dbe4e8;margin-bottom:15px}

.ad-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:28px}
.ad-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:18px}
.ad-card b{display:block;margin-bottom:6px}

.login-card{background:#fff;color:#14212b;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.3);position:sticky;top:20px}
.login-card h2{margin-top:0}
.role-switch{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0 20px}
.role-switch label{border:1px solid #ddd;border-radius:10px;padding:12px;text-align:center;cursor:pointer;font-weight:700}
.role-switch input {margin-right:7px}
.login-error{background:#fff0f1;color:#9e1c25;padding:11px;border-radius:8px;margin-bottom:14px}
.signup-note{font-size:13px;text-align:center;margin-top:15px}
.signup-note a{color:#d71920;font-weight:700}
@media(max-width:1100px){
  .about-grid-inner {grid-template-columns:1fr;gap:30px;text-align:center}
  .about-content h3::after {margin:8px auto 0}
  .founder-img-large {max-width:240px}
}
@media(max-width:850px){.landing .hero-public{grid-template-columns:1fr}.ad-grid{grid-template-columns:1fr}.landing .login-card{max-width:520px}}
</style>
</head>
<body class="landing">
<div class="container topbar">
  <div class="brandline"><img src="<?=base_url('assets/images/logo.svg')?>" alt="Odynasties"><span>Odynasties</span></div>
</div>
<main class="container hero-public">
  
  <!-- Left Column Container: Holds everything next to the Login Card -->
  <section class="about-column-wrapper">
    <div class="about-grid-inner">
      
      <!-- Left Side: Large Portrait & Name Details -->
      <div class="founder-image-wrapper">
        <img src="<?=base_url('assets/images/founder.jpg')?>" alt="Founder of Odynasties" class="founder-img-large" onerror="this.src='https://placeholder.com';">
        <div class="founder-title">
          <strong>[Founder's Full Name]</strong>
          Founder, Odynasties
        </div>

        <!-- Connect with Us Module with SVG Icons -->
        <div class="connect-container">
          <div class="connect-title">Connect with Us</div>
          <div class="social-links">
            <!-- WhatsApp Icon -->
            <a href="https://wa.me" class="social-btn" target="_blank" title="WhatsApp">
              <svg viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.411 0 11.983 0c3.184.001 6.177 1.24 8.43 3.496 2.254 2.256 3.491 5.253 3.487 8.437-.01 6.533-5.362 11.88-11.93 11.88-2.005-.002-3.975-.51-5.728-1.478L0 24zm6.152-3.568c1.644.975 3.25 1.484 4.833 1.488 5.41.002 9.814-4.383 9.822-9.775.004-2.612-1.012-5.066-2.863-6.92C16.141 3.37 13.699 2.353 11.1 2.351c-5.421 0-9.833 4.393-9.841 9.788-.002 1.674.453 3.31 1.317 4.733l-.99 3.613 3.693-.969zm13.14-5.467c-.29-.146-1.71-.844-1.973-.938-.264-.093-.456-.14-.648.147-.192.287-.743.938-.91 1.124-.167.187-.334.21-.624.065-.29-.145-1.224-.45-2.33-1.439-.862-.77-1.443-1.72-1.612-2.011-.168-.293-.018-.452.127-.597.13-.132.29-.34.436-.509.145-.17.192-.284.29-.475.097-.19.047-.356-.024-.5-.072-.146-.648-1.56-.887-2.132-.233-.56-.47-.484-.648-.493-.168-.008-.36-.01-.552-.01-.192 0-.504.071-.768.356-.264.288-1.008.985-1.008 2.404 0 1.42 1.032 2.788 1.177 2.98.145.195 2.03 3.093 4.916 4.34.687.296 1.224.474 1.643.607.69.219 1.319.19 1.816.115.553-.083 1.71-.699 1.952-1.374.24-.675.24-1.253.168-1.375-.071-.122-.264-.194-.555-.339z"/></svg>
            </a>
            
            <!-- Email Icon -->
            <a href="mailto:your-email@odynasties.com" class="social-btn" title="Email">
              <svg viewBox="0 0 24 24"><path d="M0 3v18h24v-18h-24zm21.518 2l-9.518 7.713-9.518-7.713h19.036zm-19.518 14v-11.817l10 8.104 10-8.104v11.817h-20z"/></svg>
            </a>
            
            <!-- Facebook Icon -->
            <a href="https://facebook.com" class="social-btn" target="_blank" title="Facebook">
              <svg viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.323-1.325z"/></svg>
            </a>
            
            <!-- TikTok Icon -->
            <a href="https://tiktok.com" class="social-btn" target="_blank" title="TikTok">
              <svg viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.05 1.62 4.2 1.22 1.22 2.87 1.81 4.54 1.97v3.89c-1.63-.05-3.23-.57-4.57-1.53-.16-.11-.3-.24-.45-.36-.02 2.69.01 5.38-.02 8.07-.07 2.05-.75 4.11-2.11 5.67-1.74 2.11-4.63 3.2-7.29 2.75-2.6-.33-4.99-2.22-5.74-4.75-.92-2.92.17-6.39 2.64-8.03 1.68-1.16 3.79-1.51 5.76-1.01v4c-1.1-.38-2.38-.19-3.29.56-.99.76-1.35 2.18-.89 3.37.4 1.12 1.56 1.89 2.76 1.87 1.37-.04 2.53-1.13 2.64-2.5.03-3.19.01-6.38.02-9.56zm0 0"/></svg>
            </a>
            
            <!-- Phone Icon -->
            <a href="tel:+YOURPHONENUMBER" class="social-btn" title="Phone">
