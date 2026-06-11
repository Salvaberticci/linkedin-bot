<?php

// Este archivo es un template. Las credenciales reales se guardan en:
// 1. settings.json  (editable desde el Dashboard web)
// 2. config.php     (fallback hardcodeado)
//
// Cada opción en settings.json sobreescribe la constante en config.php.
//
// Ve al Dashboard: https://tudominio.com/linkedin-bot/dashboard.php
// para configurar todo desde la web.

define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

define('LINKEDIN_CLIENT_ID', '');
define('LINKEDIN_CLIENT_SECRET', '');
define('LINKEDIN_REDIRECT_URI', 'https://tudominio.com/linkedin-bot/auth/linkedin-callback.php');

define('HF_API_TOKEN', '');
define('HF_MODEL', 'black-forest-labs/FLUX.1-schnell');

define('TIMEZONE', 'America/Mexico_City');
define('AUTO_POST_TIME', '09:00');

define('LOGS_PATH', __DIR__ . '/posts/log.json');
define('TOKENS_PATH', __DIR__ . '/auth/tokens.json');
define('ASSETS_PATH', __DIR__ . '/assets');

date_default_timezone_set(TIMEZONE);
