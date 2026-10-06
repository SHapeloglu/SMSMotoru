<?php
require_once __DIR__ . '/../src/bootstrap.php';
$count=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count>0) exit('Kurulum zaten tamamlanmış. Güvenlik için install.php dosyasını silin.');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $user=trim($_POST['username']??'');
    $pass=$_POST['password']??'';
    if(strlen($user)<3 || strlen($pass)<10) $error='Kullanıcı en az 3, şifre en az 10 karakter olmalı.';
    else {
        $stmt=$pdo->prepare('INSERT INTO users(username,password_hash) VALUES(?,?)');
        $stmt->execute([$user,password_hash($pass,PASSWORD_DEFAULT)]);
        header('Location: login.php'); exit;
    }
}
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Kurulum</title>
<body style="font-family:Arial;max-width:500px;margin:60px auto">
<h2>İlk Yönetici Hesabı</h2>
<?php if(isset($error)):?><p style="color:red"><?=e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p><input name="username" required placeholder="Kullanıcı adı"></p>
<p><input name="password" required type="password" placeholder="En az 10 karakter şifre"></p>
<button>Kur</button></form></body></html>