<?php

require_once __DIR__ . '/../config.php';

class ImageGenerator
{
    private string $apiToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiToken = HF_API_TOKEN;
        $this->apiUrl = 'https://router.huggingface.co/hf-inference/models/' . HF_MODEL;
    }

    public function generate(string $prompt): string
    {
        if (!is_dir(ASSETS_PATH)) {
            mkdir(ASSETS_PATH, 0755, true);
        }

        $imageData = $this->callHuggingFace($prompt);

        $ext = $this->detectImageExtension($imageData);
        $filename = 'post_' . date('Y-m-d') . '_' . time() . '.' . $ext;
        $filepath = ASSETS_PATH . '/' . $filename;

        file_put_contents($filepath, $imageData);

        if (!file_exists($filepath)) {
            throw new Exception("Error al guardar la imagen en {$filepath}");
        }

        return $filepath;
    }

    private function detectImageExtension(string $data): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $data);
        finfo_close($finfo);

        switch ($mime) {
            case 'image/png': return 'png';
            case 'image/jpeg':
            case 'image/jpg': return 'jpg';
            case 'image/webp': return 'webp';
            default: return 'png';
        }
    }

    private function callHuggingFace(string $prompt): string
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiToken}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $prompt]),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            $message = $error['error'] ?? $response;
            throw new Exception("Hugging Face API error (HTTP {$httpCode}): {$message}");
        }

        if ($contentType && strpos($contentType, 'image') === false && strpos($contentType, 'octet-stream') === false) {
            $error = json_decode($response, true);
            $message = $error['error'] ?? 'Respuesta inesperada: ' . $contentType;
            throw new Exception("Hugging Face: {$message}");
        }

        return $response;
    }
}
