<?php
declare(strict_types=1);
require __DIR__.'/common.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');fail(405,'Please submit the consultation form.');}
if((int)($_SERVER['CONTENT_LENGTH']??0)>24000)fail(413,'Your brief is too long. Please shorten it and try again.');
rate_limit('submit',10);
$challenge=$_SESSION['challenge']??null;
$token=field('token',64,true);
if(!$challenge || !hash_equals($challenge['token'],$token))fail(403,'Your security check has expired. Please enter the new code and try again.');
if(time()-$challenge['created']>600){unset($_SESSION['challenge']);fail(422,'Your security code has expired. Please enter the new code and try again.');}
if(time()-$challenge['created']<1 || field('website',200)!==''){unset($_SESSION['challenge']);fail(422,'We could not verify this submission. Please try again or email hello@toktales.com.');}
$captcha=strtoupper(field('captcha',6,true));
unset($_SESSION['challenge']); // Every code can be used only once, including failed attempts.
if(!hash_equals($challenge['hash'],hash('sha256',$captcha)))fail(422,'The security code was incorrect. Please enter the new code and try again.');
$name=field('name',100,true);$email=field('email',150,true);$phone=field('phone',40,true);$segment=field('segment',50,true);
$segments=['Tourism','Hotel / Restaurant','Corporate','School','Parent','Cinema','Others'];
if(!in_array($segment,$segments,true) || !filter_var($email,FILTER_VALIDATE_EMAIL))fail(422,'Please check your email address and choose a segment.');
$digits=preg_replace('/\D/','',$phone);
if(!preg_match('/^[+0-9().\-\s]+$/',$phone) || strlen($digits)<7 || strlen($digits)>20)fail(422,'Please enter a valid telephone number, including your country code.');
if(field('consent',3)!=='yes')fail(422,'Please agree to being contacted about your enquiry.');
$organisation=field('organisation',150);$format=field('format',100);$event=field('event',200);$message=field('message',3000,true,false);
$formats=['','Film & digital story','Live storytelling','School programme','Festival programme','Family legacy story'];
if(!in_array($format,$formats,true))fail(422,'Please choose an interest from the list.');
$body="TOKTALES CONSULTATION ENQUIRY\n\nName: $name\nEmail: $email\nTelephone: $phone\nSegment: $segment\nOrganisation: ".($organisation?:'Not specified')."\nInterest: ".($format?:'Help me choose')."\nPreferred date / location: ".($event?:'To be discussed')."\n\nQuestion / brief:\n$message\n\nConsent: Agreed to contact about this enquiry\nSubmitted: ".gmdate('Y-m-d H:i:s')." UTC\n";
$subject='TokTales consultation - '.$segment;
$from=getenv('TOKTALES_MAIL_FROM') ?: 'hello@toktales.com';
if(!filter_var($from,FILTER_VALIDATE_EMAIL))fail(503,'We could not send your enquiry. Please email hello@toktales.com directly.');
try {
    if(getenv('TOKTALES_SMTP_HOST')){
        require __DIR__.'/vendor/PHPMailer/Exception.php';require __DIR__.'/vendor/PHPMailer/PHPMailer.php';require __DIR__.'/vendor/PHPMailer/SMTP.php';
        $mail=new PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=getenv('TOKTALES_SMTP_HOST');
        $mail->Port=(int)(getenv('TOKTALES_SMTP_PORT') ?: 587);
        $mail->SMTPAuth=(bool)getenv('TOKTALES_SMTP_USER');$mail->Username=getenv('TOKTALES_SMTP_USER') ?: '';$mail->Password=getenv('TOKTALES_SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure=getenv('TOKTALES_SMTP_SECURITY') ?: 'tls';$mail->Timeout=15;$mail->CharSet='UTF-8';
        $mail->setFrom($from,'TokTales Website');$mail->addAddress('hello@toktales.com');$mail->addReplyTo($email,$name);
        $mail->Subject=$subject;$mail->Body=$body;$sent=$mail->send();
    }else{
        $sent=@mail('hello@toktales.com',$subject,$body,['From'=>'TokTales Website <'.$from.'>','Reply-To'=>$email,'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8']);
    }
    if(!$sent)throw new RuntimeException('Mail rejected');
}catch(Throwable $error){error_log('TokTales consultation: mail delivery handoff failed.');fail(503,'We could not send your enquiry. Please try again later or email hello@toktales.com directly.');}
reply(200,['ok'=>true,'message'=>'Thank you for your interest, we hope to get back to you shortly.']);
