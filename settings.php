<?php
$config = require 'config.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $config['smtp']=array_merge($config['smtp'],$_POST['smtp']);
 file_put_contents('config.php',"<?php\nreturn ".var_export($config,true).";");
 header('Location: settings.php'); exit;
}
?><!doctype html><html><body>
<h2>SMTP Settings</h2>
<form method=post>
<?php foreach($config['smtp'] as $k=>$v): ?>
<label><?=$k?> <input name="smtp[<?=$k?>]" value="<?=htmlspecialchars($v)?>"></label><br>
<?php endforeach ?>
<button>Save</button>
</form>
</body></html>
