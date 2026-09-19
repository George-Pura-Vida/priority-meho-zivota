<?php
declare(strict_types=1);
session_start();
const EMAIL='jgjanousek@gmail.com';
const PASS='/home/sites/1a/1/1f8017897e/.priority_htpasswd';
const RESET='/home/sites/1a/1/1f8017897e/.priority_reset.json';
function hashpass(){if(!is_file(PASS))return ''; $p=explode(':',trim((string)file_get_contents(PASS)),2);return $p[1]??'';}
function shell(string $body,string $title='Priority mého života'):never{header('Content-Type:text/html;charset=utf-8');echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#10264b"><link rel="icon" href="favicon.svg"><title>'.htmlspecialchars($title).'</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;font-family:Inter,system-ui,sans-serif;background:linear-gradient(145deg,#edf5ff,#f8fbff);color:#10264b}.c{width:min(430px,100%);background:#fff;border-radius:26px;padding:32px;box-shadow:0 18px 55px #173a6820}.r{width:76px;height:76px;border-radius:22px;background:#10264b;display:grid;place-items:center;font-size:43px;margin:0 auto 18px}h1{text-align:center;margin:0 0 8px}.s{text-align:center;color:#718096;margin:0 0 24px}label{display:block;font-size:13px;font-weight:700;margin:14px 0 7px}input{width:100%;padding:14px;border:1px solid #d8e2ee;border-radius:13px;font-size:16px}button,.b{display:block;width:100%;padding:14px;border:0;border-radius:13px;background:#0877e8;color:white;font-size:16px;font-weight:750;margin-top:18px;text-align:center;text-decoration:none;cursor:pointer}.l{display:block;text-align:center;margin-top:18px;color:#0877e8;text-decoration:none;font-weight:650}.e{background:#fff0f0;color:#a12b2b;padding:12px;border-radius:12px;margin:12px 0}</style></head><body><main class="c">'.$body.'</main></body></html>';exit;}
function form(string $err=''):never{$e=$err?'<div class="e">'.htmlspecialchars($err).'</div>':'';shell('<div class="r">🚀</div><h1>Priority mého života</h1><p class="s">Soukromý přístup</p>'.$e.'<form method="post"><input type="hidden" name="a" value="login"><label>E-mail</label><input type="email" name="email" value="'.EMAIL.'" autocomplete="username" required><label>Heslo</label><input type="password" name="password" autocomplete="current-password" required><button>Přihlásit se</button></form><a class="l" href="?forgot=1">Zapomněl jsem heslo</a>');}
if(isset($_GET['logout'])){session_destroy();header('Location:/');exit;}
if(isset($_GET['forgot'])){
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(strtolower(trim((string)($_POST['email']??'')))===strtolower(EMAIL)){
   $t=bin2hex(random_bytes(32));file_put_contents(RESET,json_encode(['h'=>hash('sha256',$t),'e'=>time()+1800]),LOCK_EX);
   $link='https://priority.jirijanousek.cz/?reset='.urlencode($t);
   @mail(EMAIL,'Obnova hesla - Priority meho zivota',"Odkaz pro nastaveni noveho hesla (plati 30 minut):\n\n".$link,"From: Priority <noreply@jirijanousek.cz>\r\nContent-Type: text/plain; charset=UTF-8");
  }
  shell('<div class="r">🚀</div><h1>Zkontrolujte e-mail</h1><p class="s">Odkaz pro nové heslo jsme odeslali na '.EMAIL.'. Platí 30 minut.</p><a class="b" href="/">Zpět</a>');
 }
 shell('<div class="r">🚀</div><h1>Obnova hesla</h1><p class="s">Pošleme bezpečný odkaz pro změnu hesla.</p><form method="post"><label>E-mail</label><input type="email" name="email" value="'.EMAIL.'" required><button>Poslat odkaz</button></form><a class="l" href="/">Zpět</a>');
}
if(isset($_GET['reset'])){
 $t=(string)$_GET['reset'];$d=is_file(RESET)?json_decode((string)file_get_contents(RESET),true):null;
 $ok=is_array($d)&&($d['e']??0)>=time()&&hash_equals((string)($d['h']??''),hash('sha256',$t));
 if(!$ok)shell('<div class="r">🚀</div><h1>Odkaz už neplatí</h1><a class="b" href="?forgot=1">Poslat nový</a>');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $p=(string)($_POST['p']??'');$p2=(string)($_POST['p2']??'');
  if(strlen($p)<10||$p!==$p2)shell('<div class="r">🚀</div><h1>Nové heslo</h1><div class="e">Hesla se musí shodovat a mít alespoň 10 znaků.</div><a class="b" href="?reset='.htmlspecialchars($t).'">Zkusit znovu</a>');
  file_put_contents(PASS,'Admin:'.password_hash($p,PASSWORD_BCRYPT).PHP_EOL,LOCK_EX);@unlink(RESET);
  shell('<div class="r">🚀</div><h1>Heslo změněno</h1><p class="s">Nové heslo je aktivní.</p><a class="b" href="/">Přihlásit se</a>');
 }
 shell('<div class="r">🚀</div><h1>Nastavit nové heslo</h1><form method="post"><label>Nové heslo</label><input type="password" name="p" minlength="10" required><label>Heslo znovu</label><input type="password" name="p2" minlength="10" required><button>Uložit nové heslo</button></form>');
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['a']??'')==='login'){
 $ok=strtolower(trim((string)($_POST['email']??'')))===strtolower(EMAIL)&&password_verify((string)($_POST['password']??''),hashpass());
 if($ok){session_regenerate_id(true);$_SESSION['priority_auth']=1;header('Location:/');exit;} form('Nesprávný e-mail nebo heslo.');
}
if(empty($_SESSION['priority_auth']))form();
header('Cache-Control:no-store');include __DIR__.'/index.html';
