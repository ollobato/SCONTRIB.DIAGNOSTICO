<?php
/**
 * Painel Administrativo de Leads e Diagnósticos
 * Fuso horário: Brasília (America/Sao_Paulo)
 */

date_default_timezone_set('America/Sao_Paulo');

$tokenConfig = "SCONTRIB2025";
$tokenEnviado = $_GET['token'] ?? '';

if ($tokenEnviado !== $tokenConfig) {
    http_response_code(403);
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><title>Acesso Negado</title>";
    echo "<style>body{background:#040510;color:#fff;font-family:sans-serif;display:grid;place-items:center;height:90vh;}</style></head><body>";
    echo "<div style='text-align:center;'><h2>Acesso Restrito</h2><p>Forneça o token de acesso na URL (?token=...).</p></div>";
    echo "</body></html>";
    exit;
}

$leadsFile = __DIR__ . '/../data/leads.jsonl';
$records = [];
if (file_exists($leadsFile)) {
    $lines = file($leadsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $json = json_decode($line, true);
        if ($json) {
            // Ajusta fuso horário de registros antigos se estiver em UTC (3 horas a mais)
            if (isset($json['data_hora_iso'])) {
                $dt = new DateTime($json['data_hora_iso']);
                $json['data_hora_br'] = $dt->format('d/m/Y H:i:s');
            } elseif (isset($json['data_hora'])) {
                // Se estiver no formato antigo Y-m-d H:i:s (UTC), ajusta -3 horas
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $json['data_hora'])) {
                    $dt = new DateTime($json['data_hora'], new DateTimeZone('UTC'));
                    $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                    $json['data_hora_br'] = $dt->format('d/m/Y H:i:s');
                } else {
                    $json['data_hora_br'] = $json['data_hora'];
                }
            } else {
                $json['data_hora_br'] = '-';
            }
            $records[] = $json;
        }
    }
}

// Exportar CSV formatado
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="leads_diagnostico_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID', 'Data/Hora (Brasília)', 'Status/Evento', 'Nome', 'E-mail', 'WhatsApp', 'WhatsApp Destino (Solicitado)', 'Cargo', 'Empresa', 'Desafio', 'Pontos', 'Nivel'], ';');
    foreach ($records as $r) {
        fputcsv($out, [
            $r['id'] ?? '',
            $r['data_hora_br'] ?? '',
            $r['evento'] ?? '',
            $r['lead']['nome'] ?? '',
            $r['lead']['email'] ?? '',
            $r['lead']['whatsapp'] ?? '',
            $r['whatsapp_dest'] ?? '',
            $r['lead']['cargo'] ?? '',
            $r['lead']['empresa'] ?? '',
            $r['lead']['desafio'] ?? '',
            $r['result']['total'] ?? 'N/A',
            $r['result']['level']['name'] ?? 'N/A'
        ], ';');
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Leads — Diagnóstico Supercontribuidor IA</title>
  <style>
    body { background: #070919; color: #e1e7f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 24px; }
    h1 { color: #23f7ff; margin-bottom: 8px; font-size: 24px; }
    .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    .btn { background: #23f7ff; color: #040510; font-weight: bold; text-decoration: none; padding: 10px 18px; border-radius: 6px; }
    .btn:hover { background: #93ff4a; }
    table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.03); border-radius: 8px; overflow: hidden; font-size: 14px; }
    th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); }
    th { background: rgba(35, 247, 255, 0.1); color: #23f7ff; }
    tr:hover { background: rgba(255,255,255,0.05); }
    .badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .badge-concluido { background: rgba(147, 255, 74, 0.2); color: #93ff4a; }
    .badge-iniciado { background: rgba(255, 212, 71, 0.2); color: #ffd447; }
    .badge-whatsapp { background: #25d366; color: #040510; box-shadow: 0 0 10px rgba(37, 211, 102, 0.5); }
    .zap-link { color: #25d366; text-decoration: none; font-weight: bold; }
    .zap-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="header-bar">
    <div>
      <h1>Painel de Leads & Diagnósticos</h1>
      <p style="color:#8f9cb5;margin:0;">Horário oficial de Brasília (America/Sao_Paulo) | Total de envios: <?= count($records) ?></p>
    </div>
    <a href="?token=<?= urlencode($tokenConfig) ?>&export=csv" class="btn">📥 Exportar para CSV</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>Data/Hora (Brasília)</th>
        <th>Status / Evento</th>
        <th>Nome</th>
        <th>E-mail</th>
        <th>WhatsApp</th>
        <th>Cargo / Empresa</th>
        <th>Pontuação</th>
        <th>Nível</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($records)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:#8f9cb5;">Nenhum lead registrado ainda.</td></tr>
      <?php else: ?>
        <?php foreach (array_reverse($records) as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['data_hora_br'] ?? '-') ?></strong></td>
            <td>
              <?php if (($r['evento'] ?? '') === 'solicitou_relatorio_whatsapp'): ?>
                <span class="badge badge-whatsapp">📲 WhatsApp Solicitado</span>
                <?php if (!empty($r['whatsapp_dest'])): ?>
                  <div style="margin-top:4px;"><a class="zap-link" href="https://wa.me/<?= preg_replace('/\D/', '', $r['whatsapp_dest']) ?>" target="_blank"><?= htmlspecialchars($r['whatsapp_dest']) ?> ↗</a></div>
                <?php endif; ?>
              <?php elseif (($r['evento'] ?? '') === 'diagnostico_concluido'): ?>
                <span class="badge badge-concluido">Concluído</span>
              <?php else: ?>
                <span class="badge badge-iniciado">Iniciado</span>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars($r['lead']['nome'] ?? '-') ?></strong></td>
            <td><a href="mailto:<?= htmlspecialchars($r['lead']['email'] ?? '') ?>" style="color:#66a6ff;"><?= htmlspecialchars($r['lead']['email'] ?? '-') ?></a></td>
            <td>
              <?php if (!empty($r['lead']['whatsapp'])): ?>
                <a class="zap-link" href="https://wa.me/<?= preg_replace('/\D/', '', $r['lead']['whatsapp']) ?>" target="_blank"><?= htmlspecialchars($r['lead']['whatsapp']) ?> ↗</a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars(($r['lead']['cargo'] ?? '-') . ' / ' . ($r['lead']['empresa'] ?? '-')) ?></td>
            <td>
              <?php if (isset($r['result']['total'])): ?>
                <strong style="color:#23f7ff;"><?= $r['result']['total'] ?> / 150</strong>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['result']['level']['name'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
