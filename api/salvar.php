<?php
/**
 * API de Captura e Armazenamento do Diagnóstico Supercontribuidor IA
 * Compatível com Hostinger (PHP 7.4+)
 * 
 * Funcionalidades:
 * 1. Recebe os dados em formato JSON (lead inicial ou diagnóstico completo).
 * 2. Salva em arquivo protegido data/leads.jsonl (thread-safe com LOCK_EX).
 * 3. Opcionalmente envia notificação por e-mail para o administrador.
 * 4. Responde em JSON com status 200/400.
 */

// Headers para permitir requisições e responder JSON
header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");

// Permite requisições da mesma origem
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Método não permitido. Use POST."]);
    exit;
}

// Ler o corpo da requisição (JSON)
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "JSON inválido ou vazio."]);
    exit;
}

// Diretório de armazenamento seguro
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
    // Cria proteção .htaccess para impedir download direto dos arquivos
    $htaccess = $dataDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }
}

// Prepara o registro enriquecido
$timestamp = date("Y-m-d H:i:s");
$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido';

$record = [
    "id" => uniqid("lead_", true),
    "data_hora" => $timestamp,
    "ip" => $ip,
    "user_agent" => $userAgent,
    "evento" => $data['evento'] ?? ($data['result'] ? 'diagnostico_concluido' : 'lead_iniciado'),
    "lead_id" => $data['lead_id'] ?? null,
    "lead" => $data['lead'] ?? [
        "nome" => $data['nome'] ?? $data['name'] ?? null,
        "email" => $data['email'] ?? null,
        "whatsapp" => $data['whatsapp'] ?? null,
        "cargo" => $data['cargo'] ?? $data['role'] ?? null,
        "empresa" => $data['empresa'] ?? $data['company'] ?? null,
        "desafio" => $data['desafio'] ?? $data['challenge'] ?? null,
        "consentimento" => $data['consentimento'] ?? true,
    ],
    "result" => $data['result'] ?? null,
    "answers" => $data['answers'] ?? null,
];

// Salva em formato JSON Lines (1 linha por envio) com bloqueio seguro
$filePath = $dataDir . '/leads.jsonl';
$line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$saved = file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);

if ($saved === false) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Falha ao gravar os dados no servidor."]);
    exit;
}

// (Opcional) Envio de E-mail de Notificação se o diagnóstico estiver completo
$adminEmail = "lucaslobato17@gmail.com"; // E-mail padrão configurável
if ($adminEmail && !empty($record['result']) && function_exists('mail')) {
    $nome = htmlspecialchars($record['lead']['nome'] ?? 'Lead');
    $email = htmlspecialchars($record['lead']['email'] ?? '');
    $zap = htmlspecialchars($record['lead']['whatsapp'] ?? '');
    $cargo = htmlspecialchars($record['lead']['cargo'] ?? '');
    $empresa = htmlspecialchars($record['lead']['empresa'] ?? '-');
    $pontos = htmlspecialchars($record['result']['total'] ?? '0');
    $nivel = htmlspecialchars($record['result']['level']['name'] ?? 'N/A');

    $assunto = "=?UTF-8?B?" . base64_encode("Novo Diagnóstico IA: {$nome} - {$pontos}/150 pts ({$nivel})") . "?=";
    $corpo = "<h2>Novo Diagnóstico de Maturidade IA Recebido!</h2>" .
             "<p><strong>Nome:</strong> {$nome}<br>" .
             "<strong>E-mail:</strong> {$email}<br>" .
             "<strong>WhatsApp:</strong> {$zap}<br>" .
             "<strong>Cargo:</strong> {$cargo} (Empresa: {$empresa})</p>" .
             "<hr>" .
             "<h3>Resultado:</h3>" .
             "<p><strong>Pontuação:</strong> {$pontos}/150 pontos<br>" .
             "<strong>Nível:</strong> {$nivel}</p>" .
             "<p><em>Registro salvo em: {$timestamp}</em></p>";

    $headers = "MIME-Version: 1.0\r\n" .
               "Content-type: text/html; charset=UTF-8\r\n" .
               "From: Diagnóstico IA <nao-responda@" . ($_SERVER['HTTP_HOST'] ?? 'scontrib.ia') . ">\r\n";

    @mail($adminEmail, $assunto, $corpo, $headers);
}

// Resposta de Sucesso
echo json_encode([
    "ok" => true,
    "id" => $record['id'],
    "evento" => $record['evento'],
    "timestamp" => $timestamp,
    "mensagem" => "Dados salvos com sucesso!"
], JSON_UNESCAPED_UNICODE);
