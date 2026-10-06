<?php
require_once __DIR__ . '/../src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $stmt=$pdo->prepare('SELECT * FROM users WHERE username=?');
    $stmt->execute([$_POST['username'] ?? '']);
    $u=$stmt->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']=$u['id'];
        header('Location: index.php'); exit;
    }
    $error='Kullanıcı adı veya şifre hatalı.';
}
?>
<!doctype html><html lang="tr"><meta charset="utf-8"><title>Giriş</title>
<body style="font-family:Arial;max-width:420px;margin:80px auto">
<h2>Toplu SMS Paneli</h2>
<?php if(!empty($error)):?><p style="color:#b91c1c"><?=e($error)?></p><?php endif;?>
<form method="post">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p><input name="username" placeholder="Kullanıcı adı" required style="width:100%;padding:10px"></p>
<p><input name="password" type="password" placeholder="Şifre" required style="width:100%;padding:10px"></p>
<button style="padding:10px 18px">Giriş</button>
</form></body></html>