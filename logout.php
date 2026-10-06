<?php
require 'config/config.php';
$role=$_GET['role'] ?? (session_name()==='ODY_ADMIN_SESSION' ? 'admin' : 'member');
start_role_session($role);
if($role==='admin'){
  audit_admin_action('Administrator logout', 'Administrator ended their session');
  $token=$_SESSION['admin_session_token']??'';
  if($token){$pdo->prepare('UPDATE login_sessions SET revoked_at=NOW() WHERE session_token=?')->execute([$token]);}
  $_SESSION=[]; session_destroy();
} elseif($role==='member'){
  $token=$_SESSION['member_session_token']??'';
  if($token){$pdo->prepare('UPDATE login_sessions SET revoked_at=NOW() WHERE session_token=?')->execute([$token]);}
  $_SESSION=[]; session_destroy();
}
header('Location: '.base_url()); exit;
