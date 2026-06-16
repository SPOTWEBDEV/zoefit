<?php
require_once __DIR__ . '/database.php';

define('ZF_V1_MODE',         true);
define('ZF_SHOW_VENDOR_NAV', false);
define('ZF_SHOW_TRANSFER',   false);

define('APP_NAME',    'ZoeFeeds');
define('APP_VERSION', '1.0.0');
define('APP_TAGLINE', 'Loyalty Reward Platform');

function zf_build_app_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
  $scheme = $https ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
  foreach (['/user','/admin','/vendor','/ajax','/api','/config','/docs','/includes'] as $sub) {
    if (($p = strrpos($script, $sub)) !== false) { $script = substr($script, 0, $p); break; }
  }
  return $scheme.'://'.rtrim($host.rtrim($script,'/'),'/');
}
define('APP_URL', zf_build_app_url());

date_default_timezone_set('Africa/Lagos');

define('SESSION_NAME',          'zf_session');
define('SESSION_LIFETIME',      7200);
define('CSRF_TOKEN_NAME',       '_zf_csrf');
define('LOGIN_MAX_ATTEMPTS',    5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('COOKIE_SECURE',         ZF_ENV === 'production');
define('CODES_PER_PAGE',        20);
define('LOGS_PER_PAGE',         25);
define('CODE_LENGTH',           15);
define('UPLOAD_PATH',           realpath(__DIR__.'/../uploads').DIRECTORY_SEPARATOR);
define('UPLOAD_URL',            APP_URL.'/uploads/');

function startAppSession(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
      'lifetime'=>SESSION_LIFETIME,'path'=>'/','secure'=>COOKIE_SECURE,'httponly'=>true,'samesite'=>'Strict',
    ]);
    session_start();
  }
}
function generateCsrf(): string {
  startAppSession();
  if (empty($_SESSION[CSRF_TOKEN_NAME])) $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
  return $_SESSION[CSRF_TOKEN_NAME];
}
function verifyCsrf(string $t): bool {
  startAppSession();
  return !empty($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $t);
}
function csrfField(): string {
  return '<input type="hidden" name="'.CSRF_TOKEN_NAME.'" value="'.htmlspecialchars(generateCsrf(),ENT_QUOTES,'UTF-8').'">';
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
function jsonResponse(array $d, int $c=200): void {
  http_response_code($c); header('Content-Type: application/json; charset=utf-8');
  echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function redirect(string $url): void { header('Location: '.$url, true, 302); exit; }
function isAjax(): bool { return strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'') === 'xmlhttprequest'; }
function isPost(): bool { return ($_SERVER['REQUEST_METHOD']??'') === 'POST'; }
function normalizePhone(string $p): string {
  $p = preg_replace('/\D/','',$p);
  if (str_starts_with($p,'234')&&strlen($p)===13) return $p;
  if (str_starts_with($p,'0')&&strlen($p)===11)   return '234'.substr($p,1);
  if (!str_starts_with($p,'0')&&!str_starts_with($p,'234')&&strlen($p)===10) return '234'.$p;
  return $p;
}
function formatPhone(string $p): string {
  return (str_starts_with($p,'234')&&strlen($p)===13) ? '0'.substr($p,3) : $p;
}
function auditLog(string $at,int $ai,string $act,string $desc='',string $et='',int $eid=0): void {
  try {
    getDB()->prepare("INSERT INTO audit_logs(actor_type,actor_id,action,description,entity_type,entity_id,ip_address,user_agent)VALUES(?,?,?,?,?,?,?,?)")
    ->execute([$at,$ai,$act,$desc,$et?:null,$eid>0?$eid:null,$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
  } catch(\Exception $e){}
}
function createNotification(int $uid,string $title,string $msg,string $type='info'): void {
  try { getDB()->prepare("INSERT INTO notifications(user_id,title,message,type)VALUES(?,?,?,?)")->execute([$uid,$title,$msg,$type]); }
  catch(\Exception $e){}
}
function requireUser(): array {
  startAppSession();
  if (empty($_SESSION['user_id'])) {
    if (isAjax()) jsonResponse(['error'=>'Session expired'],401);
    $back = urlencode($_SERVER['REQUEST_URI']??'');
    redirect(APP_URL.'/user/login.php'.($back ? '?redirect='.urlencode($_SERVER['REQUEST_URI']??'') : ''));
  }
  return ['id'=>(int)$_SESSION['user_id'],'name'=>$_SESSION['user_name']??''];
}
function requireAdmin(): array {
  startAppSession();
  if (empty($_SESSION['admin_id'])) {
    if (isAjax()) jsonResponse(['error'=>'Unauthorized'],401);
    redirect(APP_URL.'/admin/login.php');
  }
  return ['id'=>(int)$_SESSION['admin_id'],'name'=>$_SESSION['admin_name']??''];
}
function requireVendor(): array {
  startAppSession();
  if (empty($_SESSION['vendor_id'])) {
    if (isAjax()) jsonResponse(['error'=>'Unauthorized'],401);
    redirect(APP_URL.'/vendor/login.php');
  }
  return ['id'=>(int)$_SESSION['vendor_id'],'name'=>$_SESSION['vendor_name']??''];
}
function requireSuperAdmin(): array {
  startAppSession();
  if (empty($_SESSION['super_admin_id'])) {
    if (isAjax()) jsonResponse(['error'=>'Unauthorized'],401);
    redirect(APP_URL.'/admin/super-login.php');
  }
  return ['id'=>(int)$_SESSION['super_admin_id'],'name'=>$_SESSION['super_admin_name']??''];
}