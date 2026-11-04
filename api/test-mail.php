<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

$to = $_GET['to'] ?? 'lucaslobato17@gmail.com';
$subject = "Teste de Envio PHP Mail Hostinger - " . date("H:i:s");
$message = "Este e um teste direto do servidor Hostinger para verificar o funcionamento do PHP mail().";
$headers = "From: contato@" . ($_SERVER['HTTP_HOST'] ?? 'supercontribuidor.com.br') . "\r\n" .
           "Reply-To: contato@" . ($_SERVER['HTTP_HOST'] ?? 'supercontribuidor.com.br') . "\r\n" .
           "X-Mailer: PHP/" . phpversion();

$sent = mail($to, $subject, $message, $headers);
$lastError = error_get_last();

echo json_encode([
    "sent" => $sent,
    "to" => $to,
    "last_error" => $lastError,
    "sendmail_path" => ini_get('sendmail_path'),
    "disabled_functions" => ini_get('disable_functions')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
