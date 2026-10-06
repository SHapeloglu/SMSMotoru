<?php
require_once __DIR__.'/../src/bootstrap.php';require_login();require_once __DIR__.'/../src/xlsx.php';
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 if(isset($_FILES['file'])&&is_uploaded_file($_FILES['file']['tmp_name'])){
  try{
   $ext=strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
   $rows=$ext==='xlsx'?xlsx_rows($_FILES['file']['tmp_name']):array_map(fn($r)=>str_getcsv($r),file($_FILES['file']['tmp_name'],FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES));
   $header=array_map(fn($x)=>mb_strtolower(trim((string)$x),'UTF-8'),$rows[0]??[]);
   $map=[];foreach($header as $i=>$h)$map[$h]=$i;
   $count=0;
   for($i=1;$i<count($rows);$i++){
    $row=$rows[$i];$phone=normalize_phone((string)($row[$map['telefon']??0]??''));if(!$phone)continue;
    $first=$row[$map['ad']??-1]??null;$last=$row[$map['soyad']??-1]??null;$group=$row[$map['grup']??-1]??null;
    $stmt=$pdo->prepare('INSERT INTO contacts(first_name,last_name,phone,group_name) VALUES(?,?,?,?)
      ON DUPLICATE KEY UPDATE first_name=VALUES(first_name),last_name=VALUES(last_name)');
    $stmt->execute([$first,$last,$phone,$group]);$count++;
   }
   $msg="$count kişi içe aktarıldı.";
  }catch(Throwable $e){$msg='Hata: '.$e->getMessage();}
 }
}
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Kişiler</title>
<style>body{font-family:Arial;max-width:900px;margin:35px auto}.box{padding:20px;background:#f5f7fb;border-radius:12px}</style>
<a href="index.php">← Panel</a><h1>Kişiler</h1>
<div class="box"><p><b>Desteklenen:</b> .xlsx ve .csv</p><p>Kolonlar: <code>telefon, ad, soyad, grup</code></p>
<?php if($msg):?><p><?=e($msg)?></p><?php endif;?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="file" name="file" accept=".xlsx,.csv" required><button>İçe Aktar</button></form></div>
</html>