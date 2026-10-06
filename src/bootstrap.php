<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/config.php';
date_default_timezone_set($config['app']['timezone']);
$dsn="mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
$pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false
]);
session_start();
function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function verify_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Geçersiz CSRF token.');}}
function require_login():void{if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}}
function encrypt_secret(string $plain,string $key):string{$key=hash('sha256',$key,true);$iv=random_bytes(16);$c=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);return base64_encode($iv.$c);}
function decrypt_secret(string $encoded,string $key):string{$raw=base64_decode($encoded,true);if(!$raw)return ''; $key=hash('sha256',$key,true);return openssl_decrypt(substr($raw,16),'AES-256-CBC',$key,OPENSSL_RAW_DATA,substr($raw,0,16))?:'';}
function normalize_phone(string $p):string{
 $p=preg_replace('/\D+/','',$p);
 if(str_starts_with($p,'0') && strlen($p)===11)$p='90'.substr($p,1);
 if(strlen($p)===10 && str_starts_with($p,'5'))$p='90'.$p;
 return $p;
}
function sms_segments(string $text):int{
 $len=mb_strlen($text,'UTF-8');
 $gsmSpecial=preg_match('/[çğıöşüÇĞİÖŞÜ€^{}\\\\\[\]~|]/u',$text);
 $limit=$gsmSpecial?70:160;
 $multi=$gsmSpecial?67:153;
 return $len<= $limit ? 1 : (int)ceil($len/$multi);
}
function render_sms(string $tpl,array $c):string{
 return strtr($tpl,['{AD}'=>$c['first_name']??'','{SOYAD}'=>$c['last_name']??'','{TELEFON}'=>$c['phone']??'']);
}
