<?php

define('GROQ_API_KEY', 'gsk-tu-key-aqui');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

define('LINKEDIN_CLIENT_ID', 'tu-client-id');
define('LINKEDIN_CLIENT_SECRET', 'tu-client-secret');
define('LINKEDIN_REDIRECT_URI', 'https://tudominio.com/linkedin-bot/auth/linkedin-callback.php');

define('HF_API_TOKEN', 'hf_tu-token-de-huggingface');
define('HF_MODEL', 'stabilityai/stable-diffusion-xl-base-1.0');

define('LOGS_PATH', __DIR__ . '/posts/log.json');
define('TOKENS_PATH', __DIR__ . '/auth/tokens.json');
define('ASSETS_PATH', __DIR__ . '/assets');

define('TIMEZONE', 'America/Mexico_City');

date_default_timezone_set(TIMEZONE);
