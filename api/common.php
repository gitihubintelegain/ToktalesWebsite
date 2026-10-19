<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
session_name('toktales_enquiry');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
session_start();
function reply(int $status, array $data): never {http_response_code($status);echo json_encode($data, JSON_UNESCAPED_SLASHES);exit;}
function fail(int $status, string $message): never {reply($status, ['ok'=>false,'message'=>$message]);}
function rate_limit(string $bucket, int $limit): void {
    $folder=sys_get_temp_dir().DIRECTORY_SEPARATOR.'toktales-'.hash('sha256',__DIR__);
    if (!is_dir($folder) && !@mkdir($folder,0700,true) && !is_dir($folder)) fail(503,'The security check is temporarily unavailable. Please email hello@toktales.com.');
    $filename=$folder.DIRECTORY_SEPARATOR.hash('sha256',$bucket.'|'.($_SERVER['REMOTE_ADDR']??'unknown')).'.json';
    $handle=@fopen($filename,'c+');
    if (!$handle || !flock($handle,LOCK_EX)) fail(503,'Please try again shortly or email hello@toktales.com.');
    $data=json_decode(stream_get_contents($handle),true) ?: [];
    $data=array_values(array_filter($data,fn($time)=>is_int($time) && $time>time()-600));
    if(count($data)>=$limit){flock($handle,LOCK_UN);fclose($handle);header('Retry-After: 600');fail(429,'Too many attempts. Please wait 10 minutes or email hello@toktales.com.');}
    $data[]=time();rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($data));flock($handle,LOCK_UN);fclose($handle);
    // Bound retention of temporary rate-limit records; no enquiry data is stored.
    if(random_int(1,20)===1){foreach(glob($folder.DIRECTORY_SEPARATOR.'*.json') ?: [] as $file){if(filemtime($file)<time()-86400) @unlink($file);}}
}
function field(string $name,int $max,bool $required=false,bool $single=true): string {
    $raw=$_POST[$name]??'';
    if(!is_string($raw))fail(422,'Please check the form fields and try again.');
    $value=trim($raw);
    if(preg_match('//u',$value)!==1 || strlen($value)>$max*4 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value) || ($single && preg_match('/[\r\n]/',$value)))fail(422,'Please check the form fields and try again.');
    $count=preg_match_all('/./us',$value);
    if($count>$max || ($required && $value===''))fail(422,'Please complete all required fields within the indicated limits.');
    return $value;
}
