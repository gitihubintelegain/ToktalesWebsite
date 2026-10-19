<?php
declare(strict_types=1);
require __DIR__.'/common.php';
if($_SERVER['REQUEST_METHOD']!=='GET') {header('Allow: GET');fail(405,'Please request a new security code.');}
rate_limit('captcha',30);
if(!function_exists('imagecreatetruecolor'))fail(503,'The security check is unavailable. Please email hello@toktales.com.');
$chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';$code='';for($i=0;$i<6;$i++)$code.=$chars[random_int(0,strlen($chars)-1)];
$token=bin2hex(random_bytes(32));
$_SESSION['challenge']=['hash'=>hash('sha256',$code),'token'=>$token,'created'=>time()];
$image=imagecreatetruecolor(240,80);$bg=imagecolorallocate($image,242,246,243);imagefill($image,0,0,$bg);
for($i=0;$i<7;$i++){ $noise=imagecolorallocate($image,random_int(170,215),random_int(180,220),random_int(180,220));imageline($image,random_int(0,240),random_int(0,80),random_int(0,240),random_int(0,80),$noise); }
for($i=0;$i<6;$i++){
    $tile=imagecreatetruecolor(30,36);imagefill($tile,0,0,imagecolorallocate($tile,242,246,243));
    $ink=imagecolorallocate($tile,random_int(10,45),random_int(50,90),random_int(60,100));
    imagestring($tile,5,10,10,$code[$i],$ink);
    $rotated=imagerotate($tile,random_int(-17,17),$bg);
    imagecopyresampled($image,$rotated,12+$i*36,random_int(14,20),0,0,38,48,imagesx($rotated),imagesy($rotated));
}
ob_start();imagepng($image);$png=ob_get_clean();
reply(200,['ok'=>true,'token'=>$token,'image'=>'data:image/png;base64,'.base64_encode($png)]);
