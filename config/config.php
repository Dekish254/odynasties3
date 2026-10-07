<?php
/**
 * Odynasties production/local configuration.
 *
 * Render: set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS as environment variables.
 * Local XAMPP: the defaults below continue to work with MySQL root/no password.
 */
function envv($key, $default = '') {
  $value = getenv($key);
  return ($value === false || $value === '') ? $default : $value;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = rtrim(envv('APP_BASE_PATH', ''), '/');
if ($basePath === '' && preg_match('#^/odynasties(?:/|$)#', $requestPath)) {
  $basePath = '/odynasties'; // backward compatibility with XAMPP
}
define('APP_BASE_PATH', $basePath);

function base_url($path = '') {
  $base = APP_BASE_PATH;
  $path = ltrim((string)$path, '/');
  return $base . ($path !== '' ? '/' . $path : '/');
}

function ody_request_role(){
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
  $relative = APP_BASE_PATH !== '' && strpos($path, APP_BASE_PATH) === 0
    ? substr($path, strlen(APP_BASE_PATH))
    : $path;
  if (strpos($relative, '/admin/') === 0 || rtrim($relative,'/') === '/admin') return 'admin';
  if (basename($relative) === 'logout.php' && (($_GET['role'] ?? '') === 'admin')) return 'admin';
  return 'member';
}

function start_role_session($role){
  $role = $role === 'admin' ? 'admin' : 'member';
  if (session_status() === PHP_SESSION_ACTIVE) {
    if (session_name() === ($role === 'admin' ? 'ODY_ADMIN_SESSION' : 'ODY_MEMBER_SESSION')) return;
    session_write_close();
  }
  session_name($role === 'admin' ? 'ODY_ADMIN_SESSION' : 'ODY_MEMBER_SESSION');
  session_set_cookie_params([
    'lifetime'=>0,
    'path'=>APP_BASE_PATH === '' ? '/' : APP_BASE_PATH,
    'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly'=>true,
    'samesite'=>'Lax'
  ]);
  session_start();
}
start_role_session(ody_request_role());

$host = envv('DB_HOST', '127.0.0.1');
$port = envv('DB_PORT', '3306');
$db   = envv('DB_NAME', 'odynasties');
$user = envv('DB_USER', 'root');
$pass = envv('DB_PASS', '');
$donation_mpesa_number = envv('DONATION_MPESA_NUMBER', '');

try {
  $pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false
    ]
  );
} catch (PDOException $e) {
  error_log('Odynasties database connection failed: '.$e->getMessage());
  http_response_code(500);
  die('Database connection failed. Check the DB_* environment variables and make sure the database schema has been imported.');
}

function e($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
function is_admin(){
  return session_name()==='ODY_ADMIN_SESSION' && !empty($_SESSION['admin_user']) && $_SESSION['admin_user']['role']==='admin' && current_session_valid('admin');
}
function is_member(){
  return session_name()==='ODY_MEMBER_SESSION' && !empty($_SESSION['member_user']) && $_SESSION['member_user']['role']==='member' && current_session_valid('member');
}
function current_session_valid($role){
  global $pdo;
  if (($role==='admin' && session_name()!=='ODY_ADMIN_SESSION') || ($role==='member' && session_name()!=='ODY_MEMBER_SESSION')) return false;
  $token = $_SESSION[$role.'_session_token'] ?? '';
  if (!$token || empty($_SESSION[$role.'_user']['id'])) return false;
  $stmt=$pdo->prepare("SELECT ls.id,ls.revoked_at,u.status,u.role FROM login_sessions ls JOIN users u ON u.id=ls.user_id WHERE ls.session_token=? AND ls.user_id=? AND ls.role=? LIMIT 1");
  $stmt->execute([$token,(int)$_SESSION[$role.'_user']['id'],$role]); $row=$stmt->fetch();
  if (!$row || $row['revoked_at'] !== null || $row['role'] !== $role || ($row['status'] ?? 'active')!=='active') {
    unset($_SESSION[$role.'_user'],$_SESSION[$role.'_session_token']);
    return false;
  }
  $pdo->prepare('UPDATE login_sessions SET last_seen=NOW() WHERE id=?')->execute([(int)$row['id']]);
  return true;
}
function require_admin(){
  if(!current_session_valid('admin')){
    header('Location: '.base_url()); exit;
  }
  static $logged = false;
  if (!$logged && $_SERVER['REQUEST_METHOD']==='POST') {
    $action = trim((string)($_POST['action'] ?? 'POST'));
    $safe = [];
    foreach (['id','user_id','session_id','title','status','category'] as $k) {
      if (isset($_POST[$k])) $safe[$k] = is_array($_POST[$k]) ? '' : substr((string)$_POST[$k], 0, 180);
    }
    audit_admin_action('Admin action: '.$action, json_encode($safe, JSON_UNESCAPED_SLASHES));
    $logged = true;
  }
}
function require_member(){if(!current_session_valid('member')){header('Location: '.base_url('login.php'));exit;}}
function audit_admin_action($action, $details=''){
  global $pdo;
  if (!is_admin()) return;
  $admin = $_SESSION['admin_user'] ?? [];
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  try {
    $s = $pdo->prepare("INSERT INTO admin_activity_logs(admin_id,admin_name,action,details,ip_address) VALUES(?,?,?,?,?)");
    $s->execute([(int)($admin['id'] ?? 0), (string)($admin['name'] ?? ''), $action, $details, $ip]);
  } catch (Throwable $e) {}
}
function require_login(){
  if (session_name()==='ODY_ADMIN_SESSION') { require_admin(); return; }
  require_member();
}
function flash($type,$msg){$_SESSION['flash']=[$type,$msg];}
function show_flash(){if(!empty($_SESSION['flash'])){[$t,$m]=$_SESSION['flash'];unset($_SESSION['flash']);echo '<div class="flash '.e($t).'">'.e($m).'</div>';}}
function profile_image_url($filename){return $filename ? base_url('uploads/profiles/'.rawurlencode(basename($filename))) : base_url('assets/images/profile-placeholder.svg');}
current_session_valid(session_name()==='ODY_ADMIN_SESSION'?'admin':'member');
?>
