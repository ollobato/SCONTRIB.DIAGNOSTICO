<?php
/**
 * API de Captura e Armazenamento do Diagnóstico Supercontribuidor IA
 * Compatível com Hostinger (PHP 7.4+)
 * 
 * Funcionalidades:
 * 1. Recebe os dados em formato JSON (lead inicial ou diagnóstico completo).
 * 2. Salva em arquivo protegido data/leads.jsonl (thread-safe com LOCK_EX).
 * 3. Envia notificação por e-mail para os administradores a cada diagnóstico concluído.
 * 4. Responde em JSON com status 200/400.
 */

header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Método não permitido. Use POST."]);
    exit;
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "JSON inválido ou vazio."]);
    exit;
}

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
    $htaccess = $dataDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }
}

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

// Salva no arquivo local leads.jsonl
$filePath = $dataDir . '/leads.jsonl';
$line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$saved = file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);

if ($saved === false) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Falha ao gravar os dados no servidor."]);
    exit;
}

// Destinatários dos e-mails de notificação
$adminEmails = [
    "lucaslobato17@gmail.com",
    "andre.caliari@gmail.com"
];

// Envia notificação por e-mail se o diagnóstico foi concluído
if (!empty($record['result']) && function_exists('mail')) {
    $nome = htmlspecialchars($record['lead']['nome'] ?? 'Novo Lead');
    $email = htmlspecialchars($record['lead']['email'] ?? '');
    $whatsapp = htmlspecialchars($record['lead']['whatsapp'] ?? '');
    $cargo = htmlspecialchars($record['lead']['cargo'] ?? 'Não informado');
    $empresa = htmlspecialchars($record['lead']['empresa'] ?? 'Não informada');
    $desafio = htmlspecialchars($record['lead']['desafio'] ?? 'Nenhum informado');
    
    $pontos = htmlspecialchars($record['result']['total'] ?? '0');
    $nivelNome = htmlspecialchars($record['result']['level']['name'] ?? 'N/A');
    $nivelFaixa = htmlspecialchars($record['result']['level']['range'] ?? '');
    $nivelFoco = htmlspecialchars($record['result']['level']['focus'] ?? '');

    // Limpeza de número para link direto do WhatsApp
    $rawPhone = preg_replace('/\D/', '', $whatsapp);
    if (strlen($rawPhone) >= 10 && !str_starts_with($rawPhone, '55')) {
        $cleanPhone = '55' . $rawPhone;
    } else {
        $cleanPhone = $rawPhone;
    }
    $whatsappLink = "https://wa.me/{$cleanPhone}";

    // Dimensões
    $dimensoesHtml = "";
    if (!empty($record['result']['dimensions'])) {
        $dimensoesLabels = [
            "contexto" => "Contexto & Alinhamento",
            "pesquisa" => "Conhecimento & Pesquisa",
            "raciocinio" => "Raciocínio & Estrutura",
            "execucao" => "Criação & Execução com IA",
            "qualidade" => "Qualidade & Rigor Humano",
            "impacto" => "Impacto & Influência"
        ];
        foreach ($record['result']['dimensions'] as $dimKey => $score) {
            $label = $dimensoesLabels[$dimKey] ?? ucfirst($dimKey);
            $pct = round(($score / 25) * 100);
            $dimensoesHtml .= "<tr>
                <td style='padding: 8px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px;'>{$label}</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; text-align: right;'><strong>{$score}/25</strong> ({$pct}%)</td>
            </tr>";
        }
    }

    $assunto = "=?UTF-8?B?" . base64_encode("🎯 Novo Diagnóstico IA: {$nome} — {$pontos}/150 pts ({$nivelNome})") . "?=";

    $corpo = "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background-color: #f7fafc; margin: 0; padding: 24px; color: #2d3748;'>
      <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.07); border: 1px solid #e2e8f0;'>
        <div style='background: linear-gradient(135deg, #08091a 0%, #151838 100%); padding: 28px 24px; text-align: center; color: #ffffff;'>
          <h1 style='margin: 0; font-size: 22px; color: #23f7ff;'>Supercontribuidor IA</h1>
          <p style='margin: 6px 0 0; font-size: 14px; color: #a0aec0;'>Novo Diagnóstico de Maturidade Concluído</p>
        </div>

        <div style='padding: 24px;'>
          <!-- Destaque da Pontuação -->
          <div style='background: #edf2f7; border-radius: 8px; padding: 18px; text-align: center; margin-bottom: 24px;'>
            <span style='font-size: 12px; text-transform: uppercase; color: #718096; font-weight: bold; letter-spacing: 1px;'>Índice de Prontidão IA</span>
            <div style='font-size: 38px; font-weight: 900; color: #2b6cb0; margin: 4px 0;'>{$pontos} <span style='font-size: 18px; color: #718096;'>/ 150</span></div>
            <div style='display: inline-block; background: #319795; color: #ffffff; font-size: 14px; font-weight: bold; padding: 4px 12px; border-radius: 20px;'>{$nivelNome} ({$nivelFaixa})</div>
            <p style='margin: 12px 0 0; font-size: 13px; color: #4a5568; line-height: 1.5;'><em>\"{$nivelFoco}\"</em></p>
          </div>

          <!-- Dados do Lead -->
          <h2 style='font-size: 16px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; margin-bottom: 14px; color: #1a202c;'>👤 Informações do Lead</h2>
          <table style='width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px;'>
            <tr><td style='padding: 6px 0; color: #718096; width: 110px;'>Nome:</td><td><strong>{$nome}</strong></td></tr>
            <tr><td style='padding: 6px 0; color: #718096;'>E-mail:</td><td><a href='mailto:{$email}' style='color: #3182ce; text-decoration: none;'>{$email}</a></td></tr>
            <tr><td style='padding: 6px 0; color: #718096;'>WhatsApp:</td><td><a href='{$whatsappLink}' target='_blank' style='color: #25d366; font-weight: bold; text-decoration: none;'>{$whatsapp} ↗ Abrir conversa</a></td></tr>
            <tr><td style='padding: 6px 0; color: #718096;'>Cargo / Empresa:</td><td>{$cargo} na <strong>{$empresa}</strong></td></tr>
            <tr><td style='padding: 6px 0; color: #718096;'>Desafio com IA:</td><td style='color: #4a5568;'>{$desafio}</td></tr>
          </table>

          <!-- Dimensões -->
          <h2 style='font-size: 16px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; margin-bottom: 14px; color: #1a202c;'>📊 Desempenho por Dimensão</h2>
          <table style='width: 100%; border-collapse: collapse; margin-bottom: 24px;'>
            {$dimensoesHtml}
          </table>

          <!-- Botão Painel -->
          <div style='text-align: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid #edf2f7;'>
            <a href='https://supercontribuidor.com.br/api/ver-leads.php?token=SCONTRIB2025' style='background: #3182ce; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;'>Acessar Painel de Leads & Exportar CSV</a>
            <p style='font-size: 11px; color: #a0aec0; margin-top: 10px;'>Registro gerado em {$timestamp}</p>
          </div>
        </div>
      </div>
    </body>
    </html>";

    $host = $_SERVER['HTTP_HOST'] ?? 'supercontribuidor.com.br';
    $headers = "MIME-Version: 1.0\r\n" .
               "Content-type: text/html; charset=UTF-8\r\n" .
               "From: Supercontribuidor IA <nao-responda@{$host}>\r\n" .
               "Reply-To: {$email}\r\n";

    // Dispara individualmente para cada destinatário
    foreach ($adminEmails as $destinatario) {
        @mail($destinatario, $assunto, $corpo, $headers);
    }
}

echo json_encode([
    "ok" => true,
    "id" => $record['id'],
    "evento" => $record['evento'],
    "timestamp" => $timestamp,
    "mensagem" => "Dados salvos com sucesso!"
], JSON_UNESCAPED_UNICODE);
