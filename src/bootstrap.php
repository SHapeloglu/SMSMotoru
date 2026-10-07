<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/config.php';
date_default_timezone_set($config['app']['timezone']);
$dsn="mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
$pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES=>false
]);
require_once __DIR__.'/helpers.php';
session_start();
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function verify_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Geçersiz CSRF token.');}}
function require_login():void{if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}}
function encrypt_secret(string $plain,string $key):string{$key=hash('sha256',$key,true);$iv=random_bytes(16);$c=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);return base64_encode($iv.$c);}
function decrypt_secret(string $encoded,string $key):string{$raw=base64_decode($encoded,true);if(!$raw)return ''; $key=hash('sha256',$key,true);return openssl_decrypt(substr($raw,16),'AES-256-CBC',$key,OPENSSL_RAW_DATA,substr($raw,0,16))?:'';}
