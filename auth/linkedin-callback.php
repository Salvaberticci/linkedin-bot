<?php

require_once __DIR__ . '/../config.php';

/**
 * LinkedIn OAuth 2.0 + OpenID Connect Callback
 *
 * Usa OpenID Connect (scope "openid profile") para obtener el member_id
 * desde el ID token JWT, sin necesidad de r_liteprofile.
 *
 * PASOS:
 * 1. Agrega "http://localhost:8000/auth/linkedin-callback.php" como Redirect URL
 *    en https://www.linkedin.com/developers/apps → tu app → Auth
 * 2. Asegúrate de que config.php tenga LINKEDIN_REDIRECT_URI apuntando a esa URL
 * 3. Abre http://localhost:8000/auth/linkedin-callback.php en tu navegador
 * 4. Haz clic en "Autorizar"
 * 5. El token y member_id se guardarán automáticamente
 */

function decodeJwtPayload(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return [];
    }
    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    return json_decode($payload, true) ?? [];
}

$action = $_GET['action'] ?? '';
$code = $_GET['code'] ?? '';

// Step 1: Authorization page
if (!$code && $action !== 'authorize') {
    $currentToken = '';
    $hasToken = false;
    $expired = true;
    $daysLeft = 0;

    if (file_exists(TOKENS_PATH)) {
        $tokens = json_decode(file_get_contents(TOKENS_PATH), true);
        if ($tokens && !empty($tokens['access_token'])) {
            $hasToken = true;
            $expiresAt = $tokens['expires_at'] ?? 0;
            $expired = $expiresAt < time();
            if (!$expired) {
                $daysLeft = floor(($expiresAt - time()) / 86400);
            }
        }
    }

    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Bot - Autenticación</title>
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; line-height: 1.5; }
        h2 { color: #0A66C2; }
        .btn { display:inline-block; padding:12px 24px; background:#0A66C2; color:white; text-decoration:none; border-radius:6px; font-size:16px; border:none; cursor:pointer; }
        .btn:hover { background:#084e96; }
        .card { background:#f8f9fa; padding:15px; border-radius:6px; margin-top:15px; }
        .ok { color: green; }
        .err { color: red; }
        code { background:#eee; padding:2px 6px; border-radius:3px; font-size:13px; word-break:break-all; }
        input[type=text], input[type=url] { width:100%; padding:8px; margin:6px 0; box-sizing:border-box; border:1px solid #ccc; border-radius:4px; }
    </style>
</head>
<body>
    <h2>🔗 LinkedIn Bot - Autenticación</h2>

    <?php if ($hasToken): ?>
    <div class="card">
        <h3>Estado del token actual:</h3>
        <p class="<?= $expired ? 'err' : 'ok' ?>">
            <strong><?= $expired ? 'EXPIRADO' : 'VÁLIDO' ?></strong>
            <?php if (!$expired): ?>
            — Expira en <?= $daysLeft ?> días
            <?php endif; ?>
        </p>
        <p><small>Token guardado en <code><?= TOKENS_PATH ?></code></small></p>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>Opción 1: Autorización automática</h3>
        <p>Abre LinkedIn y autoriza la app:</p>
        <a class="btn" href="?action=authorize">Autorizar con LinkedIn</a>
        <p><small>Redirect URI: <code><?= LINKEDIN_REDIRECT_URI ?></code></small></p>
    </div>

    <div class="card">
        <h3>Opción 2: Ingreso manual del código</h3>
        <p>Si la redirección automática falla (ej: localhost no alcanzable):</p>
        <ol>
            <li>Copia esta URL y pégala en tu navegador:</li>
            <code style="display:block;padding:8px;margin:6px 0;font-size:12px;word-break:break-all;">
                https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=<?= LINKEDIN_CLIENT_ID ?>&redirect_uri=<?= urlencode(LINKEDIN_REDIRECT_URI) ?>&scope=<?= urlencode('openid profile email w_member_social') ?>
            </code>
            <li>Autoriza la app</li>
            <li>LinkedIn te redirigirá a una URL que contiene <code>?code=...</code></li>
            <li>Copia el valor de <code>code</code> de la URL y pégalo aquí:</li>
        </ol>
        <form method="get">
            <input type="text" name="code" placeholder="Pega el código aquí (authorization code)" required>
            <button type="submit" class="btn" style="margin-top:6px;">Canjear código</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
}

// Step 2: Redirect to LinkedIn authorization
if ($action === 'authorize') {
    $authUrl = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
        'response_type' => 'code',
        'client_id' => LINKEDIN_CLIENT_ID,
        'redirect_uri' => LINKEDIN_REDIRECT_URI,
        'scope' => 'openid profile email w_member_social',
    ]);

    header('Location: ' . $authUrl);
    exit;
}

// Step 3: Exchange authorization code for tokens
if ($code) {
    $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => LINKEDIN_CLIENT_ID,
            'client_secret' => LINKEDIN_CLIENT_SECRET,
            'redirect_uri' => LINKEDIN_REDIRECT_URI,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("<h2>Error al obtener token</h2><pre>Código: {$httpCode}\n" . htmlspecialchars($response) . "</pre>");
    }

    $tokenData = json_decode($response, true);

    if (!$tokenData || !isset($tokenData['access_token'])) {
        die("<h2>Error: respuesta inesperada</h2><pre>" . htmlspecialchars(print_r($tokenData, true)) . "</pre>");
    }

    // Extract member_id from the ID token (JWT) via OpenID Connect
    $memberId = '';
    if (!empty($tokenData['id_token'])) {
        $payload = decodeJwtPayload($tokenData['id_token']);
        $memberId = $payload['sub'] ?? '';
    }

    // Fallback: if no id_token, try /v2/me
    if (empty($memberId)) {
        $ch = curl_init('https://api.linkedin.com/v2/me');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $tokenData['access_token'],
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $profileResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $profile = json_decode($profileResponse, true);
            $memberId = $profile['sub'] ?? '';
        }
    }

    // Save tokens
    $tokens = [
        'access_token' => $tokenData['access_token'],
        'expires_in' => $tokenData['expires_in'] ?? 5184000,
        'expires_at' => time() + ($tokenData['expires_in'] ?? 5184000),
        'member_id' => $memberId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $dir = dirname(TOKENS_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(TOKENS_PATH, json_encode($tokens, JSON_PRETTY_PRINT));
    ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Bot - Autenticación Exitosa</title>
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 650px; margin: 40px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 6px; }
        .info { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 20px; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 14px; word-break: break-all; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="success">
        <h2>Autenticación Exitosa</h2>
        <p>Token de LinkedIn obtenido correctamente.</p>
    </div>

    <?php if (empty($memberId)): ?>
    <div class="warning">
        <h3>⚠️ No se pudo obtener el member_id</h3>
        <p>El token se guardó, pero no se pudo identificar tu member_id de LinkedIn.</p>
        <p>Para solucionarlo, edita manualmente <code><?= TOKENS_PATH ?></code> y agrega:</p>
        <code style="display:block;padding:10px;margin:10px 0;">"member_id": "80333060"</code>
        <p>Para obtener tu member_id: ve a tu perfil de LinkedIn → Ctrl+U (código fuente) → busca <code>memberId</code></p>
    </div>
    <?php endif; ?>

    <div class="info">
        <h3>Detalles:</h3>
        <p><strong>Member ID:</strong> <code><?= htmlspecialchars($memberId ?: '⚠️ No obtenido (ver advertencia arriba)') ?></code></p>
        <p><strong>Expira:</strong> <?= date('Y-m-d H:i:s', $tokens['expires_at']) ?></p>
        <p><strong>Días válido:</strong> <?= floor(($tokens['expires_at'] - time()) / 86400) ?> días</p>
        <p><strong>Token guardado en:</strong> <code><?= TOKENS_PATH ?></code></p>
    </div>

    <div class="info">
        <h3>Próximos pasos:</h3>
        <ol>
            <li><strong>Prueba el bot:</strong> Ejecuta en terminal:
                <code style="display:block;padding:6px;margin:6px 0;">php <?= realpath(__DIR__ . '/../index.php') ?></code>
            </li>
            <li><strong>En producción:</strong> Sube los archivos a tu hosting y cambia LINKEDIN_REDIRECT_URI en config.php</li>
            <li><strong>Renovar token:</strong> Cuando expire (~60 días), vuelve a esta página</li>
        </ol>
    </div>
</body>
</html>
    <?php
    exit;
}
