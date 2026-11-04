<?php
/**
 * Painel Administrativo Simples e Exportador CSV de Leads
 * Acesso protegido via token na URL: ?token=SCONTRIB2025
 */

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
        if ($json) $records[] = $json;
    }
}

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="leads_diagnostico_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM UTF-8 para Excel abrir com acentuação correta
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID', 'Data/Hora', 'Evento', 'Nome', 'E-mail', 'WhatsApp', 'Cargo', 'Empresa', 'Desafio', 'Pontos', 'Nivel'], ';');
    foreach ($records as $r) {
        fputcsv($out, [
            $r['id'] ?? '',
            $r['data_hora'] ?? '',
            $r['evento'] ?? '',
            $r['lead']['nome'] ?? '',
            $r['lead']['email'] ?? '',
            $r['lead']['whatsapp'] ?? '',
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
    .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .btn { background: #23f7ff; color: #040510; font-weight: bold; text-decoration: none; padding: 10px 18px; border-radius: 6px; }
    .btn:hover { background: #93ff4a; }
    table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.03); border-radius: 8px; overflow: hidden; font-size: 14px; }
    th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); }
    th { background: rgba(35, 247, 255, 0.1); color: #23f7ff; }
    tr:hover { background: rgba(255,255,255,0.05); }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge-concluido { background: rgba(147, 255, 74, 0.2); color: #93ff4a; }
    .badge-iniciado { background: rgba(255, 212, 71, 0.2); color: #ffd447; }
  </style>
</head>
<body>
  <div class="header-bar">
    <div>
      <h1>Painel de Leads & Diagnósticos</h1>
      <p style="color:#8f9cb5;margin:0;">Total de registros salvos: <?= count($records) ?></p>
    </div>
    <a href="?token=<?= urlencode($tokenConfig) ?>&export=csv" class="btn">📥 Exportar para CSV</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>Data/Hora</th>
        <th>Status</th>
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
            <td><?= htmlspecialchars($r['data_hora'] ?? '') ?></td>
            <td>
              <?php if (($r['evento'] ?? '') === 'diagnostico_concluido'): ?>
                <span class="badge badge-concluido">Concluído</span>
              <?php else: ?>
                <span class="badge badge-iniciado">Iniciado</span>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars($r['lead']['nome'] ?? '-') ?></strong></td>
            <td><?= htmlspecialchars($r['lead']['email'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['lead']['whatsapp'] ?? '-') ?></td>
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
