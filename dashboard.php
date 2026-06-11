<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/settings.php';

if (!defined('AUTO_POST_TIME')) {
    define('AUTO_POST_TIME', Settings::get('AUTO_POST_TIME', '09:00'));
}
if (!defined('COPY_SYSTEM_PROMPT')) {
    define('COPY_SYSTEM_PROMPT', Settings::get('COPY_SYSTEM_PROMPT', ''));
}
if (!defined('IMAGE_STYLE_PROMPT')) {
    define('IMAGE_STYLE_PROMPT', Settings::get('IMAGE_STYLE_PROMPT', ''));
}

$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'dashboard';

// Handle generate or retry — capture output and show styled result
if ($action === 'generate' || ($action === 'retry' && !empty($_GET['topic']))) {
    ob_start();
    putenv('FORCE_GENERATE=1');
    $label = 'Generando post forzado';
    if ($action === 'retry') {
        putenv('TOPIC_OVERRIDE=' . $_GET['topic']);
        $label = 'Reintentando post fallido — ' . $_GET['topic'];
    }
    require __DIR__ . '/index.php';
    $output = ob_get_clean();

    $success = strpos($output, 'Post publicado exitosamente') !== false;
    $postUrl = '';
    if (preg_match('/Post publicado: (https?:\/\/[^\s]+)/', $output, $m)) {
        $postUrl = $m[1];
    }
    $errorMsg = '';
    if (!$success && preg_match('/\[ERROR\] (.+)/', $output, $m)) {
        $errorMsg = $m[1];
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LinkedIn Bot — Resultado</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
.container{max-width:780px;width:100%}
.terminal{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px 24px;font-family:'SF Mono','Fira Code','Cascadia Code',monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;max-height:60vh;overflow-y:auto;margin-bottom:24px}
.terminal .ok{color:#3fb950}
.terminal .info{color:#58a6ff}
.terminal .error{color:#f85149}
.notification{position:fixed;top:24px;right:24px;padding:16px 24px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,.4);z-index:1000;animation:slideIn .4s;display:flex;align-items:center;gap:10px}
.notification.success{background:#1a3a2a;color:#3fb950;border:1px solid #238636}
.notification.error{background:#3a1a1a;color:#f85149;border:1px solid #da3633}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}}
.notification .icon{font-size:20px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:.15s}
.btn-primary{background:#238636;color:#fff}
.btn-primary:hover{background:#2ea043}
.btn-outline{background:transparent;border:1px solid #30363d;color:#e6edf3}
.btn-outline:hover{background:#21262d}
.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
</style>
</head>
<body>
<div class="container">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <span style="font-size:24px"><?= $success ? '✅' : '❌' ?></span>
    <h2 style="font-size:18px;font-weight:600"><?= $success ? 'Post publicado' : 'Error al publicar' ?></h2>
  </div>
  <div class="terminal"><?php
    $lines = explode("\n", htmlspecialchars($output));
    foreach ($lines as $line) {
        if (preg_match('/^\[OK\]/', $line)) echo '<span class="ok">' . $line . "</span>\n";
        elseif (preg_match('/^\[ERROR\]/', $line)) echo '<span class="error">' . $line . "</span>\n";
        elseif (preg_match('/^\[INFO\]/', $line)) echo '<span class="info">' . $line . "</span>\n";
        else echo $line . "\n";
    }
  ?></div>
  <div class="actions">
    <a href="?tab=dashboard" class="btn btn-primary">← Volver al Dashboard</a>
    <?php if ($postUrl): ?>
    <a href="<?= htmlspecialchars($postUrl) ?>" target="_blank" class="btn btn-outline">🔗 Ver en LinkedIn</a>
    <?php endif; ?>
  </div>
</div>

<div class="notification <?= $success ? 'success' : 'error' ?>" id="notif">
  <span class="icon"><?= $success ? '✅' : '❌' ?></span>
  <span><?= $success ? 'Post publicado correctamente' : htmlspecialchars($errorMsg ?: 'Error al publicar') ?></span>
</div>

<script>
setTimeout(function(){
  var n = document.getElementById('notif');
  n.style.transition = 'transform .4s, opacity .4s';
  n.style.transform = 'translateX(120%)';
  n.style.opacity = '0';
  setTimeout(function(){ n.remove(); }, 500);
}, 5000);
</script>
</body>
</html>
    <?php
    exit;
}

// Handle save settings
if ($action === 'save-settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed = [
        'GROQ_API_KEY', 'GROQ_MODEL',
        'LINKEDIN_CLIENT_ID', 'LINKEDIN_CLIENT_SECRET', 'LINKEDIN_REDIRECT_URI',
        'HF_API_TOKEN', 'HF_MODEL',
        'TIMEZONE', 'AUTO_POST_TIME',
        'COPY_SYSTEM_PROMPT', 'IMAGE_STYLE_PROMPT',
        'ABOUT_ME', 'SUCCESS_STORIES',
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

// Load log
$log = [];
if (file_exists(LOGS_PATH)) {
    $log = json_decode(file_get_contents(LOGS_PATH), true) ?? [];
}
$logReversed = array_reverse($log);

// Stats
$todayStr = date('Y-m-d');
$postedToday = false;
$lastPost = null;
$totalPosts = 0;
$failedPosts = 0;
foreach ($log as $e) {
    $s = $e['status'] ?? '';
    if ($s === 'success') $totalPosts++;
    if ($s === 'failed') $failedPosts++;
    if ($e['date'] === $todayStr && $s === 'success') $postedToday = true;
    if ($lastPost === null && $s === 'success') $lastPost = $e;
}

$nextPostTime = date('Y-m-d') . ' ' . AUTO_POST_TIME . ':00';
$nextPost = new DateTime($nextPostTime);
$now = new DateTime();
if ($nextPost <= $now) $nextPost->modify('+1 day');
$interval = $now->diff($nextPost);
$nextPostStr = sprintf('%dh %dm', $interval->h + ($interval->d * 24), $interval->i);

$settings = Settings::all();
$assetsUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/assets';

// Default prompts
$defaultCopyPrompt = 'Eres un copywriter experto en tecnología y marketing B2B. 
Escribes posts para LinkedIn de un desarrollador de software a medida que también integra IA en negocios.
Tu objetivo es generar leads y conseguir clientes.
Cada post debe:
- Tener un gancho atractivo en las primeras 2 líneas
- Mostrar autoridad en el tema
- Incluir un problema real que resuelve el software a medida o la IA
- Terminar con un Call to Action (ej: "¿Necesitas algo similar? Escríbeme")
- NO usar emojis
- Entre 200 y 400 palabras
- Ser profesional pero cercano
- Incluir 5 hashtags relevantes al final';

$defaultImageStyle = 'fotografía profesional, iluminación cinematográfica, fondo oscuro con neones azules y morados';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LinkedIn Bot — Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f4f6f9;
  --surface:#fff;
  --primary:#0a66c2;
  --primary-dark:#084e96;
  --green:#2e7d32;
  --red:#c62828;
  --amber:#f57f17;
  --text:#1a1a2e;
  --text-secondary:#5a5a7a;
  --border:#e2e5ea;
  --radius:12px;
  --shadow:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --shadow-lg:0 10px 40px rgba(0,0,0,.12);
  --font:'Inter','SF Pro',-apple-system,BlinkMacSystemFont,sans-serif;
  --radius-sm:8px;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh}
.app{display:flex;flex-direction:column;min-height:100vh}

/* ─── HEADER ─── */
.header{background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.header-inner{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:64px;gap:32px}
.header h1{font-size:20px;font-weight:700;color:var(--primary);letter-spacing:-.3px;display:flex;align-items:center;gap:8px}
.header h1 svg{width:28px;height:28px}
.nav{display:flex;gap:4px;margin-left:auto}
.nav a{padding:8px 16px;border-radius:var(--radius-sm);text-decoration:none;font-size:14px;font-weight:500;color:var(--text-secondary);transition:.15s}
.nav a:hover{background:#f0f2f5;color:var(--text)}
.nav a.active{background:var(--primary);color:#fff}

/* ─── MAIN ─── */
.main{flex:1;max-width:1200px;margin:0 auto;padding:24px;width:100%}

/* ─── STATS GRID ─── */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;top:0;left:0;width:4px;height:100%;border-radius:0 4px 4px 0}
.stat-card.green::after{background:var(--green)}
.stat-card.red::after{background:var(--red)}
.stat-card.blue::after{background:var(--primary)}
.stat-card.amber::after{background:var(--amber)}
.stat-label{font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
.stat-value{font-size:28px;font-weight:700;letter-spacing:-.5px}
.stat-value.green{color:var(--green)}
.stat-value.red{color:var(--red)}
.stat-value.blue{color:var(--primary)}
.stat-sub{font-size:12px;color:var(--text-secondary);margin-top:4px}

/* ─── ACTIONS ─── */
.actions-card{background:var(--surface);border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.actions-card h2{font-size:16px;font-weight:600}
.actions-card p{font-size:13px;color:var(--text-secondary);margin-top:2px}
.btn-group{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:var(--radius-sm);font-size:14px;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:.15s}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-danger{background:var(--red);color:#fff}
.btn-danger:hover{background:#9e1e1e}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-outline:hover{background:#f0f2f5;border-color:#ccc}
.btn-sm{padding:6px 14px;font-size:12px}

/* ─── POST CARDS GRID ─── */
.section-title{font-size:18px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.posts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-bottom:32px}
@media(max-width:720px){.posts-grid{grid-template-columns:1fr}}

.post-card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;cursor:pointer;transition:.2s;display:flex;flex-direction:column}
.post-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px)}
.post-card-img{width:100%;height:180px;object-fit:cover;background:#e8ecf0}
.post-card-body{padding:16px;flex:1;display:flex;flex-direction:column;gap:8px}
.post-card-date{font-size:11px;color:var(--text-secondary);font-weight:500;display:flex;align-items:center;gap:6px}
.post-card-topic{font-size:15px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-card-preview{font-size:13px;color:var(--text-secondary);line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.post-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:8px;border-top:1px solid var(--border)}
.post-card-tags{display:flex;gap:4px;flex-wrap:wrap}
.post-card-status{font-size:11px;font-weight:600;padding:2px 10px;border-radius:10px}
.status-success{background:#e8f5e9;color:var(--green)}
.status-failed{background:#fbe9e7;color:var(--red)}

.empty-state{text-align:center;padding:60px 20px;color:var(--text-secondary)}
.empty-state svg{width:64px;height:64px;margin-bottom:16px;opacity:.3}
.empty-state h3{font-size:18px;margin-bottom:8px;color:var(--text)}
.empty-state p{font-size:14px}

/* ─── MODAL ─── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:1000;justify-content:center;align-items:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border-radius:var(--radius);max-width:720px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);animation:modalIn .2s}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(10px)}}
.modal-img{width:100%;max-height:360px;object-fit:cover;background:#e8ecf0}
.modal-body{padding:24px}
.modal-close{float:right;background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-secondary);padding:4px}
.modal-close:hover{color:var(--text)}
.modal h2{font-size:20px;font-weight:600;margin-bottom:12px;padding-right:32px}
.modal-meta{display:flex;gap:16px;font-size:12px;color:var(--text-secondary);margin-bottom:16px;flex-wrap:wrap}
.modal-meta span{display:flex;align-items:center;gap:4px}
.modal-text{font-size:14px;line-height:1.7;color:var(--text);white-space:pre-wrap;margin-bottom:16px}
.modal-hashtags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.modal-hashtags span{font-size:12px;padding:3px 10px;background:#e3f2fd;color:var(--primary);border-radius:12px}
.modal-actions{display:flex;gap:8px;margin-top:8px}

/* ─── HISTORY TABLE ─── */
.table-wrap{overflow-x:auto;background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow)}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:600px}
th,td{padding:12px 16px;text-align:left;border-bottom:1px solid var(--border)}
th{background:#f8f9fb;font-weight:600;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.4px}
tr:hover td{background:#fafbfc}
tr:last-child td{border-bottom:none}
.copy-cell{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tag{padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600}
.tag-success{background:#e8f5e9;color:var(--green)}
.tag-failed{background:#fbe9e7;color:var(--red)}

/* ─── SETTINGS ─── */
.settings-wrap{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:800px){.settings-wrap{grid-template-columns:1fr}}
.settings-section{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px}
.settings-section h3{font-size:15px;font-weight:600;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:4px}
.form-group input,.form-group textarea{width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:var(--font);transition:.15s;background:#fafbfc}
.form-group input:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(10,102,194,.12);background:#fff}
.form-group textarea{min-height:80px;resize:vertical;font-family:'SF Mono','Fira Code','Cascadia Code',monospace;font-size:12px;line-height:1.5}
.form-group .char-count{font-size:11px;color:var(--text-secondary);text-align:right;margin-top:2px}

/* ─── TOKEN STATUS ─── */
.token-section{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-top:20px}
.token-section h3{font-size:15px;font-weight:600;margin-bottom:12px}
.token-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;font-size:13px}
.token-info dt{color:var(--text-secondary);font-size:11px;font-weight:600;text-transform:uppercase}
.token-info dd{margin-bottom:8px}
.token-valid{color:var(--green);font-weight:600}
.token-expired{color:var(--red);font-weight:600}
</style>
</head>
<body>
<div class="app">
<header class="header">
  <div class="header-inner">
    <h1>
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>
      LinkedIn Bot
    </h1>
    <nav class="nav">
      <a href="?tab=dashboard" class="<?= $tab==='dashboard'?'active':''?>">Dashboard</a>
      <a href="?tab=history" class="<?= $tab==='history'?'active':''?>">Historial</a>
      <a href="?tab=settings" class="<?= $tab==='settings'?'active':''?>">Configuración</a>
    </nav>
  </div>
</header>

<main class="main">
<?php if (isset($saved)): ?>
<script>
Swal.fire({ icon:'success', title:'Configuración guardada', text:'Los cambios se aplican inmediatamente.', toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
</script>
<?php endif; ?>

<!-- ═══════════ DASHBOARD ═══════════ -->
<?php if ($tab === 'dashboard'): ?>

<div class="stats">
  <div class="stat-card green">
    <div class="stat-label">Posts publicados</div>
    <div class="stat-value green"><?= $totalPosts ?></div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Fallos</div>
    <div class="stat-value red"><?= $failedPosts ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Último post</div>
    <div class="stat-value blue" style="font-size:20px"><?= $lastPost ? date('d/m/Y',strtotime($lastPost['date'])):'—' ?></div>
  </div>
  <div class="stat-card amber">
    <div class="stat-label">Próximo post</div>
    <div class="stat-value" style="font-size:22px;color:var(--amber)"><?= $nextPostStr ?></div>
    <div class="stat-sub">a las <?= AUTO_POST_TIME ?> hs</div>
  </div>
</div>

<div class="actions-card">
  <div>
    <h2>Acciones</h2>
    <p><?= $postedToday ? 'Ya hay un post hoy. El botón forzará otro.' : 'Genera un nuevo post ahora mismo.' ?></p>
  </div>
  <div class="btn-group">
    <a href="?action=generate" class="btn btn-primary" data-confirm="¿Generar post ahora?">
      <?= $postedToday ? '🔄 Forzar otro post' : '🚀 Generar post' ?>
    </a>
    <a href="<?= LINKEDIN_REDIRECT_URI ?>" class="btn btn-outline" target="_blank">🔑 Renovar token</a>
  </div>
</div>

<?php if (!empty($logReversed)): ?>
<h3 class="section-title">📰 Últimos posts</h3>
<div class="posts-grid" id="postGrid">
<?php foreach ($logReversed as $entry): 
  $imgFile = basename($entry['image_path'] ?? '');
  $imgUrl = $imgFile ? "{$assetsUrl}/{$imgFile}" : '';
  $status = $entry['status'] ?? '';
  $hasImg = $imgUrl && file_exists(ASSETS_PATH.'/'.$imgFile);
?>
<div class="post-card" onclick="openModal(<?= htmlspecialchars(json_encode($entry)) ?>, '<?= $hasImg ? $imgUrl : '' ?>')">
  <?php if ($hasImg): ?>
  <img class="post-card-img" src="<?= htmlspecialchars($imgUrl) ?>" alt="" loading="lazy">
  <?php else: ?>
  <div class="post-card-img" style="display:flex;align-items:center;justify-content:center;color:#bbb;font-size:14px">Sin imagen</div>
  <?php endif; ?>
  <div class="post-card-body">
    <div class="post-card-date">
      <span><?= htmlspecialchars($entry['date'] ?? '') ?> <?= htmlspecialchars($entry['time'] ?? '') ?></span>
      <span class="post-card-status status-<?= $status ?>"><?= $status ?></span>
    </div>
    <div class="post-card-topic"><?= htmlspecialchars($entry['topic'] ?? 'Post') ?></div>
    <div class="post-card-preview"><?= htmlspecialchars($entry['copy_preview'] ?? '') ?></div>
    <div class="post-card-footer">
      <div class="post-card-tags">
        <?php if (!empty($entry['hashtags'])): ?>
          <?php foreach (array_slice($entry['hashtags'], 0, 3) as $h): ?>
          <span style="font-size:11px;color:var(--primary)"><?= htmlspecialchars($h) ?></span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <?php if ($status === 'failed' && !empty($entry['topic'])): ?>
        <a href="?action=retry&topic=<?= urlencode($entry['topic']) ?>" class="btn btn-danger btn-sm" data-confirm="¿Reintentar este post fallido?" data-stop-prop="1">Reintentar</a>
        <?php endif; ?>
        <span style="font-size:12px;color:var(--primary);font-weight:500">Ver más →</span>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
  <h3>No hay posts aún</h3>
  <p>Genera tu primer post desde el botón de arriba.</p>
</div>
<?php endif; ?>

<!-- ═══════════ HISTORY ═══════════ -->
<?php elseif ($tab === 'history'): ?>

<h3 class="section-title">📋 Historial completo</h3>
<div class="table-wrap">
<table>
<thead><tr>
  <th>#</th><th>Fecha</th><th>Tema</th><th>Copy</th><th>Estado</th><th>Error</th><th>Link</th><th>Acción</th>
</tr></thead>
<tbody>
<?php foreach ($logReversed as $i=>$entry): $status=$entry['status']??''; ?>
<tr>
  <td><?= $i+1 ?></td>
  <td><?= htmlspecialchars($entry['date']??'') ?><br><small style="color:var(--text-secondary)"><?= htmlspecialchars($entry['time']??'') ?></small></td>
  <td><?= htmlspecialchars($entry['topic']??'—') ?></td>
  <td class="copy-cell" title="<?= htmlspecialchars($entry['copy_preview']??'') ?>"><?= htmlspecialchars(substr($entry['copy_preview']??'',0,120)) ?></td>
  <td><span class="tag tag-<?= $status ?>"><?= $status ?></span></td>
  <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:var(--red)"><?= htmlspecialchars(substr($entry['error']??'',0,80)) ?></td>
  <td><?php if(!empty($entry['linkedin_url'])&&$entry['linkedin_url']!=='https://www.linkedin.com/feed/update/unknown'):?><a href="<?= htmlspecialchars($entry['linkedin_url'])?>" target="_blank" style="font-size:13px">Abrir</a><?php else:?>—<?php endif;?></td>
  <td><?php if ($status === 'failed' && !empty($entry['topic'])):?><a href="?action=retry&topic=<?= urlencode($entry['topic']) ?>" class="btn btn-danger btn-sm" data-confirm="¿Reintentar este post?">Reintentar</a><?php else:?>—<?php endif;?></td>
</tr>
<?php endforeach; if(empty($logReversed)):?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-secondary)">No hay posts en el historial.</td></tr><?php endif;?>
</tbody>
</table>
</div>

<!-- ═══════════ SETTINGS ═══════════ -->
<?php elseif ($tab === 'settings'): ?>

<h3 class="section-title">⚙️ Configuración</h3>
<form method="post" action="?action=save-settings">
<div class="settings-wrap">

  <div class="settings-section">
    <h3>🤖 Groq API</h3>
    <div class="form-group">
      <label>API Key</label>
      <input type="text" name="GROQ_API_KEY" value="<?= htmlspecialchars($settings['GROQ_API_KEY'] ?? GROQ_API_KEY) ?>">
    </div>
    <div class="form-group">
      <label>Modelo</label>
      <input type="text" name="GROQ_MODEL" value="<?= htmlspecialchars($settings['GROQ_MODEL'] ?? GROQ_MODEL) ?>">
    </div>
  </div>

  <div class="settings-section">
    <h3>🖼️ Hugging Face</h3>
    <div class="form-group">
      <label>API Token</label>
      <input type="text" name="HF_API_TOKEN" value="<?= htmlspecialchars($settings['HF_API_TOKEN'] ?? HF_API_TOKEN) ?>">
    </div>
    <div class="form-group">
      <label>Modelo</label>
      <input type="text" name="HF_MODEL" value="<?= htmlspecialchars($settings['HF_MODEL'] ?? HF_MODEL) ?>">
    </div>
  </div>

  <div class="settings-section">
    <h3>🔗 LinkedIn API</h3>
    <div class="form-group">
      <label>Client ID</label>
      <input type="text" name="LINKEDIN_CLIENT_ID" value="<?= htmlspecialchars($settings['LINKEDIN_CLIENT_ID'] ?? LINKEDIN_CLIENT_ID) ?>">
    </div>
    <div class="form-group">
      <label>Client Secret</label>
      <input type="text" name="LINKEDIN_CLIENT_SECRET" value="<?= htmlspecialchars($settings['LINKEDIN_CLIENT_SECRET'] ?? LINKEDIN_CLIENT_SECRET) ?>">
    </div>
    <div class="form-group">
      <label>Redirect URI</label>
      <input type="text" name="LINKEDIN_REDIRECT_URI" value="<?= htmlspecialchars($settings['LINKEDIN_REDIRECT_URI'] ?? LINKEDIN_REDIRECT_URI) ?>">
    </div>
  </div>

  <div class="settings-section">
    <h3>⏰ Horario</h3>
    <div class="form-group">
      <label>Zona horaria (Timezone)</label>
      <input type="text" name="TIMEZONE" value="<?= htmlspecialchars($settings['TIMEZONE'] ?? TIMEZONE) ?>">
      <small style="color:var(--text-secondary)">Ej: America/Caracas, America/Mexico_City, etc.</small>
    </div>
    <div class="form-group">
      <label>Hora de publicación (HH:MM)</label>
      <input type="text" name="AUTO_POST_TIME" value="<?= htmlspecialchars($settings['AUTO_POST_TIME'] ?? AUTO_POST_TIME) ?>">
    </div>
  </div>

  <div class="settings-section" style="grid-column:1/-1">
    <h3>✍️ Prompt del copy (sistema)</h3>
    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Controla el tono, estructura y reglas del texto que genera Groq.</p>
    <div class="form-group">
      <textarea name="COPY_SYSTEM_PROMPT" rows="10" oninput="this.nextElementSibling.textContent=this.value.length"><?= htmlspecialchars($settings['COPY_SYSTEM_PROMPT'] ?: $defaultCopyPrompt) ?></textarea>
      <div class="char-count"><?= strlen($settings['COPY_SYSTEM_PROMPT'] ?: $defaultCopyPrompt) ?> caracteres</div>
    </div>
  </div>

  <div class="settings-section" style="grid-column:1/-1">
    <h3>🎨 Estilo de imagen</h3>
    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Define el estilo visual. El contenido de la imagen seguirá basándose en el copy del post.</p>
    <div class="form-group">
      <textarea name="IMAGE_STYLE_PROMPT" rows="4" oninput="this.nextElementSibling.textContent=this.value.length"><?= htmlspecialchars($settings['IMAGE_STYLE_PROMPT'] ?: $defaultImageStyle) ?></textarea>
      <div class="char-count"><?= strlen($settings['IMAGE_STYLE_PROMPT'] ?: $defaultImageStyle) ?> caracteres</div>
    </div>
  </div>

  <div class="settings-section" style="grid-column:1/-1">
    <h3>👤 Sobre mí</h3>
    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Describe quién eres, tu experiencia y qué servicios ofreces. Groq usará esta información para personalizar los posts.</p>
    <div class="form-group">
      <textarea name="ABOUT_ME" rows="6" oninput="this.nextElementSibling.textContent=this.value.length"><?= htmlspecialchars($settings['ABOUT_ME'] ?? '') ?></textarea>
      <div class="char-count"><?= strlen($settings['ABOUT_ME'] ?? '') ?> caracteres</div>
    </div>
  </div>

  <div class="settings-section" style="grid-column:1/-1">
    <h3>🏆 Casos de éxito</h3>
    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Describe clientes reales, proyectos y resultados. Groq los usará como referencia en los posts cuando aplique al tema.</p>
    <div class="form-group">
      <textarea name="SUCCESS_STORIES" rows="6" oninput="this.nextElementSibling.textContent=this.value.length"><?= htmlspecialchars($settings['SUCCESS_STORIES'] ?? '') ?></textarea>
      <div class="char-count"><?= strlen($settings['SUCCESS_STORIES'] ?? '') ?> caracteres</div>
    </div>
  </div>

</div>
<div style="margin-top:20px;text-align:right">
  <button type="submit" class="btn btn-primary">💾 Guardar configuración</button>
</div>
</form>

<div class="token-section">
  <h3>🔑 Token de LinkedIn</h3>
  <?php $tokenInfo=file_exists(TOKENS_PATH)?(json_decode(file_get_contents(TOKENS_PATH),true)??[]):[]; ?>
  <dl class="token-info">
    <div><dt>Member ID</dt><dd><code style="font-size:13px"><?= htmlspecialchars($tokenInfo['member_id']??'—') ?></code></dd></div>
    <div><dt>Expira</dt><dd><?= isset($tokenInfo['expires_at'])?date('d/m/Y H:i:s',$tokenInfo['expires_at']):'—' ?></dd></div>
    <div>
      <dt>Estado</dt>
      <dd><?php if(isset($tokenInfo['expires_at'])):?><span class="<?= $tokenInfo['expires_at']>time()?'token-valid':'token-expired'?>"><?= $tokenInfo['expires_at']>time()?'✓ Válido':'✗ Expirado'?></span><?php else:?><span style="color:var(--text-secondary)">No hay token</span><?php endif;?></dd>
    </div>
  </dl>
  <a href="<?= LINKEDIN_REDIRECT_URI ?>" class="btn btn-outline btn-sm" style="margin-top:8px" target="_blank">Renovar token</a>
</div>

<?php endif; ?>
</main>
</div>

<!-- ═══════════ MODAL ═══════════ -->
<div class="modal-overlay" id="postModal" onclick="if(event.target===this)closeModal()">
<div class="modal">
  <button class="modal-close" onclick="closeModal()">&times;</button>
  <img class="modal-img" id="modalImg" src="" alt="" style="display:none">
  <div class="modal-body">
    <h2 id="modalTitle"></h2>
    <div class="modal-meta" id="modalMeta"></div>
    <div class="modal-text" id="modalText"></div>
    <div class="modal-hashtags" id="modalTags"></div>
    <div class="modal-actions" id="modalActions"></div>
  </div>
</div>
</div>

<script>
const modal=document.getElementById('postModal');
const modalImg=document.getElementById('modalImg');
const modalTitle=document.getElementById('modalTitle');
const modalMeta=document.getElementById('modalMeta');
const modalText=document.getElementById('modalText');
const modalTags=document.getElementById('modalTags');
const modalActions=document.getElementById('modalActions');

function openModal(entry,imgUrl){
  modalTitle.textContent=entry.topic||'Post';
  modalMeta.innerHTML=`
    <span>📅 ${entry.date||''} ${entry.time||''}</span>
    <span>📊 ${entry.status||'?'}</span>
  `;
  modalText.textContent=entry.copy_preview||'Sin contenido';
  if(entry.error) modalText.textContent+='\n\n❌ Error: '+entry.error;

  if(imgUrl){modalImg.src=imgUrl;modalImg.style.display='block'}
  else modalImg.style.display='none';

  const tags=entry.hashtags;
  modalTags.innerHTML='';
  if(tags&&tags.length) tags.forEach(t=>{
    const s=document.createElement('span');s.textContent=t;modalTags.appendChild(s)
  });

  modalActions.innerHTML='';
  if(entry.linkedin_url&&entry.linkedin_url!=='https://www.linkedin.com/feed/update/unknown'){
    const a=document.createElement('a');
    a.href=entry.linkedin_url;a.target='_blank';
    a.className='btn btn-outline btn-sm';a.textContent='🔗 Ver en LinkedIn';
    modalActions.appendChild(a);
  }

  modal.classList.add('open');
}
function closeModal(){modal.classList.remove('open')}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal()});

document.querySelectorAll('[data-confirm]').forEach(function(el){
  el.addEventListener('click',function(e){
    e.preventDefault();
    if(el.getAttribute('data-stop-prop')) e.stopPropagation();
    var url=this.href;
    Swal.fire({
      title:el.getAttribute('data-confirm'),
      icon:'question',
      showCancelButton:true,
      confirmButtonText:'Sí',
      cancelButtonText:'Cancelar',
      confirmButtonColor:'#0a66c2',
    }).then(function(r){if(r.isConfirmed) window.location.href=url});
  });
});
</script>
</body>
</html>
