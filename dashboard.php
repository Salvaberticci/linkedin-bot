<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/settings.php';

$action = $_GET['action'] ?? '';
$logFile = LOGS_PATH;

// Handle force post
if ($action === 'generate') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== Generando post forzado ===\n\n";
    putenv('FORCE_GENERATE=1');
    require __DIR__ . '/index.php';
    exit;
}

// Handle save settings
if ($action === 'save-settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed = [
        'GROQ_API_KEY', 'GROQ_MODEL',
        'LINKEDIN_CLIENT_ID', 'LINKEDIN_CLIENT_SECRET', 'LINKEDIN_REDIRECT_URI',
        'HF_API_TOKEN', 'HF_MODEL',
        'TIMEZONE', 'AUTO_POST_TIME',
    ];

    $data = [];
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $data[$key] = trim($_POST[$key]);
        }
    }

    Settings::save($data);
    $saved = true;
}

$log = [];
if (file_exists($logFile)) {
    $log = json_decode(file_get_contents($logFile), true) ?? [];
}
$log = array_reverse(array_slice($log, 0, 50));

$todayStr = date('Y-m-d');
$postedToday = false;
$lastPost = null;
foreach ($log as $entry) {
    if ($entry['date'] === $todayStr && ($entry['status'] ?? '') === 'success') {
        $postedToday = true;
    }
    if ($lastPost === null && ($entry['status'] ?? '') === 'success') {
        $lastPost = $entry;
    }
}

$totalPosts = 0;
$failedPosts = 0;
foreach ($log as $e) {
    $s = $e['status'] ?? '';
    if ($s === 'success') $totalPosts++;
    if ($s === 'failed') $failedPosts++;
}

$nextPostTime = date('Y-m-d') . ' ' . AUTO_POST_TIME . ':00';
$nextPost = new DateTime($nextPostTime);
$now = new DateTime();
if ($nextPost <= $now) {
    $nextPost->modify('+1 day');
}
$interval = $now->diff($nextPost);
$nextPostStr = sprintf('%dh %dm', $interval->h + ($interval->d * 24), $interval->i);

$settings = Settings::all();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Bot - Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #333; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 24px; color: #0A66C2; margin-bottom: 20px; }
        h2 { font-size: 18px; margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h3 { font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .card .value { font-size: 28px; font-weight: 700; }
        .card .value.green { color: #2e7d32; }
        .card .value.red { color: #c62828; }
        .card .value.blue { color: #0A66C2; }
        .tag { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .tag.success { background: #e8f5e9; color: #2e7d32; }
        .tag.failed { background: #fbe9e7; color: #c62828; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #0A66C2; color: #fff; }
        .btn-primary:hover { background: #084e96; }
        .btn-danger { background: #c62828; color: #fff; }
        .btn-danger:hover { background: #9e1e1e; }
        .btn-outline { background: transparent; border: 1px solid #0A66C2; color: #0A66C2; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .mb-20 { margin-bottom: 20px; }
        .mt-20 { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f5f5f5; font-weight: 600; color: #555; }
        tr:hover { background: #fafafa; }
        .copy-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        form label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-top: 12px; margin-bottom: 4px; }
        form input { width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; font-family: monospace; }
        form input:focus { outline: none; border-color: #0A66C2; box-shadow: 0 0 0 2px rgba(10,102,194,0.15); }
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        .output-box { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 6px; font-family: monospace; font-size: 13px; white-space: pre-wrap; max-height: 400px; overflow-y: auto; margin-top: 15px; }
        .nav { display: flex; gap: 10px; margin-bottom: 20px; }
        .nav a { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .nav a.active { background: #0A66C2; color: #fff; }
        .nav a:not(.active) { color: #555; }
        .nav a:not(.active):hover { background: #e3f2fd; }
        .hidden { display: none; }
        .token-status { font-size: 12px; color: #666; }
        @media (max-width: 600px) { .settings-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="?tab=dashboard" class="<?= (!isset($_GET['tab']) || $_GET['tab'] === 'dashboard') ? 'active' : '' ?>">Dashboard</a>
        <a href="?tab=history" class="<?= ($_GET['tab'] ?? '') === 'history' ? 'active' : '' ?>">Historial</a>
        <a href="?tab=settings" class="<?= ($_GET['tab'] ?? '') === 'settings' ? 'active' : '' ?>">Configuración</a>
        <a href="?tab=output" class="<?= ($_GET['tab'] ?? '') === 'output' ? 'active' : '' ?>">Salida en vivo</a>
    </div>

    <?php if (isset($saved) && $saved): ?>
    <div class="alert alert-success">Configuración guardada correctamente.</div>
    <?php endif; ?>

    <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'dashboard'): ?>

    <!-- Stats -->
    <div class="grid">
        <div class="card">
            <h3>Posts publicados</h3>
            <div class="value green"><?= $totalPosts ?></div>
        </div>
        <div class="card">
            <h3>Fallos</h3>
            <div class="value red"><?= $failedPosts ?></div>
        </div>
        <div class="card">
            <h3>Último post</h3>
            <div class="value blue" style="font-size:16px;"><?= $lastPost ? date('d/m/Y', strtotime($lastPost['date'])) : 'Nunca' ?></div>
        </div>
        <div class="card">
            <h3>Próximo post en</h3>
            <div class="value blue" style="font-size:22px;"><?= $nextPostStr ?></div>
            <div class="token-status">Próximo a las <?= AUTO_POST_TIME ?> hs</div>
        </div>
    </div>

    <!-- Actions -->
    <div class="card mb-20">
        <h2>Acciones</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
            <a href="?action=generate" class="btn btn-primary" onclick="return confirm('¿Generar un post ahora mismo?')">
                <?= $postedToday ? '🔄 Generar otro post (hoy ya hay uno)' : '🚀 Generar post ahora' ?>
            </a>
            <a href="<?= LINKEDIN_REDIRECT_URI ?>" class="btn btn-outline" target="_blank">🔑 Renovar token LinkedIn</a>
        </div>
        <?php if ($postedToday): ?>
        <p style="margin-top:10px;font-size:13px;color:#c62828;">⚠️ Ya hay un post publicado hoy. El botón forzará la creación de otro.</p>
        <?php endif; ?>
    </div>

    <!-- Last posts preview -->
    <div class="card">
        <h2>Últimos posts</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tema</th>
                    <th>Estado</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($log, 0, 10) as $entry): ?>
                <tr>
                    <td><?= htmlspecialchars($entry['date'] ?? '') ?></td>
                    <td class="copy-preview"><?= htmlspecialchars($entry['topic'] ?? substr($entry['copy_preview'] ?? '', 0, 80)) ?></td>
                    <td><span class="tag <?= ($entry['status'] ?? '') === 'success' ? 'success' : 'failed' ?>"><?= $entry['status'] ?? '?' ?></span></td>
                    <td>
                        <?php if (!empty($entry['linkedin_url']) && $entry['linkedin_url'] !== 'https://www.linkedin.com/feed/update/unknown'): ?>
                        <a href="<?= htmlspecialchars($entry['linkedin_url']) ?>" target="_blank">Ver</a>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($log)): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">No hay posts aún. Genera el primero.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($_GET['tab'] === 'history'): ?>

    <!-- Full history -->
    <div class="card">
        <h2>Historial completo de posts</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Tema</th>
                    <th>Copy</th>
                    <th>Estado</th>
                    <th>Error</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $i => $entry): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($entry['date'] ?? '') ?><br><small><?= htmlspecialchars($entry['time'] ?? '') ?></small></td>
                    <td><?= htmlspecialchars($entry['topic'] ?? '—') ?></td>
                    <td class="copy-preview" title="<?= htmlspecialchars($entry['copy_preview'] ?? '') ?>"><?= htmlspecialchars(substr($entry['copy_preview'] ?? '', 0, 120)) ?></td>
                    <td><span class="tag <?= ($entry['status'] ?? '') === 'success' ? 'success' : 'failed' ?>"><?= $entry['status'] ?? '?' ?></span></td>
                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;font-size:11px;color:#c62828;"><?= htmlspecialchars(substr($entry['error'] ?? '', 0, 80)) ?></td>
                    <td>
                        <?php if (!empty($entry['linkedin_url']) && $entry['linkedin_url'] !== 'https://www.linkedin.com/feed/update/unknown'): ?>
                        <a href="<?= htmlspecialchars($entry['linkedin_url']) ?>" target="_blank">Abrir</a>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($log)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:20px;">No hay posts en el historial.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($_GET['tab'] === 'settings'): ?>

    <!-- Settings -->
    <div class="card">
        <h2>Configuración de APIs y credenciales</h2>
        <p style="font-size:13px;color:#666;margin-bottom:15px;">Los cambios se guardan en <code>settings.json</code> y se aplican inmediatamente.</p>
        <form method="post" action="?action=save-settings">
            <div class="settings-grid">
                <div>
                    <h3 style="margin-top:0;">Groq API</h3>
                    <label>GROQ_API_KEY</label>
                    <input type="text" name="GROQ_API_KEY" value="<?= htmlspecialchars($settings['GROQ_API_KEY'] ?? '') ?>">
                    <label>GROQ_MODEL</label>
                    <input type="text" name="GROQ_MODEL" value="<?= htmlspecialchars($settings['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile') ?>">
                </div>
                <div>
                    <h3 style="margin-top:0;">Hugging Face</h3>
                    <label>HF_API_TOKEN</label>
                    <input type="text" name="HF_API_TOKEN" value="<?= htmlspecialchars($settings['HF_API_TOKEN'] ?? '') ?>">
                    <label>HF_MODEL</label>
                    <input type="text" name="HF_MODEL" value="<?= htmlspecialchars($settings['HF_MODEL'] ?? 'black-forest-labs/FLUX.1-schnell') ?>">
                </div>
                <div>
                    <h3>LinkedIn API</h3>
                    <label>LINKEDIN_CLIENT_ID</label>
                    <input type="text" name="LINKEDIN_CLIENT_ID" value="<?= htmlspecialchars($settings['LINKEDIN_CLIENT_ID'] ?? '') ?>">
                    <label>LINKEDIN_CLIENT_SECRET</label>
                    <input type="text" name="LINKEDIN_CLIENT_SECRET" value="<?= htmlspecialchars($settings['LINKEDIN_CLIENT_SECRET'] ?? '') ?>">
                    <label>LINKEDIN_REDIRECT_URI</label>
                    <input type="text" name="LINKEDIN_REDIRECT_URI" value="<?= htmlspecialchars($settings['LINKEDIN_REDIRECT_URI'] ?? '') ?>">
                </div>
                <div>
                    <h3>Horario</h3>
                    <label>TIMEZONE</label>
                    <input type="text" name="TIMEZONE" value="<?= htmlspecialchars($settings['TIMEZONE'] ?? 'America/Mexico_City') ?>">
                    <label>AUTO_POST_TIME (HH:MM)</label>
                    <input type="text" name="AUTO_POST_TIME" value="<?= htmlspecialchars($settings['AUTO_POST_TIME'] ?? '09:00') ?>">
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Guardar configuración</button>
            </div>
        </form>

        <div style="margin-top:30px;padding-top:20px;border-top:1px solid #e0e0e0;">
            <h3>Token de LinkedIn</h3>
            <?php
            $tokenInfo = [];
            if (file_exists(TOKENS_PATH)) {
                $tokenInfo = json_decode(file_get_contents(TOKENS_PATH), true) ?? [];
            }
            ?>
            <p style="font-size:13px;">
                <strong>Member ID:</strong> <code><?= htmlspecialchars($tokenInfo['member_id'] ?? '—') ?></code><br>
                <strong>Expira:</strong> <?= isset($tokenInfo['expires_at']) ? date('d/m/Y H:i:s', $tokenInfo['expires_at']) : '—' ?><br>
                <strong>Estado:</strong> 
                <?php if (isset($tokenInfo['expires_at'])): ?>
                    <?= $tokenInfo['expires_at'] > time() ? '<span style="color:#2e7d32;">Válido</span>' : '<span style="color:#c62828;">Expirado</span>' ?>
                <?php else: ?>
                    <span style="color:#999;">No hay token</span>
                <?php endif; ?>
            </p>
            <a href="<?= LINKEDIN_REDIRECT_URI ?>" class="btn btn-outline" style="margin-top:8px;" target="_blank">Renovar token</a>
        </div>
    </div>

    <?php elseif ($_GET['tab'] === 'output'): ?>

    <!-- Live output / trigger -->
    <div class="card">
        <h2>Generar post - Salida en vivo</h2>
        <p style="font-size:13px;color:#666;margin-bottom:15px;">Ejecuta el bot y ve la salida en tiempo real.</p>
        <a href="?action=generate" class="btn btn-danger" onclick="document.getElementById('output').classList.remove('hidden');this.textContent='Generando...';this.disabled=true;">
            <?= $postedToday ? '🔄 Forzar otro post' : '🚀 Generar post ahora' ?>
        </a>
        <div id="output" class="output-box hidden">
            <em>Cargando... la salida aparecerá aquí.</em>
        </div>
    </div>

    <script>
    document.querySelector('a[href="?action=generate"]')?.addEventListener('click', function(e) {
        e.preventDefault();
        const out = document.getElementById('output');
        out.classList.remove('hidden');
        out.textContent = 'Generando post...\n\n';
        this.disabled = true;
        this.textContent = 'Generando...';

        fetch(this.href)
            .then(r => r.text())
            .then(text => { out.textContent = text; this.disabled = false; this.textContent = '✅ Generar otro'; })
            .catch(err => { out.textContent = 'ERROR: ' + err.message; this.disabled = false; this.textContent = '❌ Reintentar'; });
    });
    </script>

    <?php endif; ?>
</div>
</body>
</html>
