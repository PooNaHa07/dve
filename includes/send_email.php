<?php
// sample using PHPMailer via composer
$vendor = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendor)) {
    echo 'Install dependencies with composer install to use email.';
    return;
}
require $vendor;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function send_simple_email($to_email, $to_name, $subject, $body_html){
    $mail = new PHPMailer(true);
    try {
        // Configure SMTP as needed
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'username';
        $mail->Password = 'password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('noreply@example.com', 'DVE System');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body_html;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>