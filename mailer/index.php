<?php

require "PHPMailer/PHPMailerAutoload.php";


function smtpmailer($to, $subject, $body)
{
        global $host , $emailpassword;
         $mail = new PHPMailer();
         $mail->IsSMTP();
         $mail->SMTPAuth = true;

         $mail->SMTPSecure = 'ssl'; // Using 'ssl' with port 465 as per your original configuration
         $mail->Host = 'mail.zoefeeds.com';
         $mail->Port = 465; // Or 587 if using 'tls'
         $mail->Username = 'support@zoefeeds.com';
         $mail->Password = 'support@zoefeeds.com'; // Use your actual email password

         $mail->IsHTML(true);
         $mail->From = 'support@zoefeeds.com';
         $mail->FromName = 'ZoeFeeds';
         $mail->Sender = 'support@zoefeeds.com';
         $mail->AddReplyTo('support@zoefeeds.com', 'ZoeFeeds');
         $mail->Subject = $subject;
         $mail->Body = $body;
         $mail->AddAddress($to);

         // Enable SMTP debugging
        //  $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
        //  $mail->Debugoutput = 'html'; // Output format for debugging

         if (!$mail->Send()) {
                  // Log error or handle failure
                  error_log('Email sending failed: ' . $mail->ErrorInfo);
                  return false;
         }

         return true;
}



?>