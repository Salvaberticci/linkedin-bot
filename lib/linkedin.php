<?php

require_once __DIR__ . '/../config.php';

class LinkedInClient
{
    private string $accessToken;
    private string $memberId;
    private string $apiVersion = '202601';

    public function __construct()
    {
        $this->loadTokens();
    }

    public function postWithImage(string $commentary, string $imagePath, array $hashtags = []): array
    {
        if ($hashtags) {
            $commentary .= "\n\n" . implode(' ', $hashtags);
        }

        echo "[INFO] Subiendo imagen a LinkedIn...\n";
        $mediaUrn = $this->uploadImage($imagePath);

        echo "[INFO] Publicando post en LinkedIn...\n";
        $postId = $this->createPost($commentary, $mediaUrn);

        return [
            'success' => true,
            'post_id' => $postId,
            'post_url' => "https://www.linkedin.com/feed/update/{$postId}",
        ];
    }

    private function uploadImage(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            throw new Exception("El archivo de imagen no existe: {$imagePath}");
        }

        $registerResponse = $this->callApi(
            'POST',
            'https://api.linkedin.com/rest/images?action=initializeUpload',
            [
                'initializeUploadRequest' => [
                    'owner' => "urn:li:person:{$this->memberId}",
                ],
            ]
        );

        $uploadUrl = $registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $imageUrn = $registerResponse['value']['image'] ?? null;

        if (!$uploadUrl || !$imageUrn) {
            throw new Exception("Error al registrar la imagen: " . json_encode($registerResponse));
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception("Error al leer la imagen: {$imagePath}");
        }

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $imageData,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->accessToken}",
                'Content-Type: image/jpeg',
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $uploadResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 200) {
            throw new Exception("Error al subir imagen a LinkedIn (HTTP {$httpCode}): {$uploadResponse}");
        }

        return $imageUrn;
    }

    private function createPost(string $commentary, string $mediaUrn): string
    {
        $postData = [
            'author' => "urn:li:person:{$this->memberId}",
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
            'content' => [
                'media' => [
                    'id' => $mediaUrn,
                    'mediaType' => 'image/jpeg',
                ],
            ],
        ];

        $response = $this->callApi(
            'POST',
            'https://api.linkedin.com/rest/posts',
            $postData
        );

        return $response['id'] ?? 'unknown';
    }

    private function loadTokens(): void
    {
        if (!file_exists(TOKENS_PATH)) {
            throw new Exception(
                "No se encontró auth/tokens.json.\n" .
                "Abre auth/linkedin-callback.php en tu navegador para autenticarte."
            );
        }

        $content = file_get_contents(TOKENS_PATH);
        $tokens = json_decode($content, true);

        $this->accessToken = $tokens['access_token'] ?? '';

        if (empty($this->accessToken)) {
            throw new Exception(
                "auth/tokens.json no contiene access_token.\n" .
                "Abre auth/linkedin-callback.php en tu navegador para generar el token."
            );
        }

        $this->memberId = $tokens['member_id'] ?? '';

        if (empty($this->memberId)) {
            throw new Exception(
                "member_id no encontrado en tokens.json.\n" .
                "Re-autentica en auth/linkedin-callback.php"
            );
        }
    }

    private function callApi(string $method, string $url, ?array $data = null): array
    {
        $ch = curl_init($url);
        $headers = [
            "Authorization: Bearer {$this->accessToken}",
            'Content-Type: application/json',
            "LinkedIn-Version: {$this->apiVersion}",
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error en LinkedIn API: {$error}");
        }

        if ($httpCode === 401) {
            throw new Exception(
                "Token de LinkedIn expirado. Renueva el token abriendo auth/linkedin-callback.php en tu navegador."
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $decoded = json_decode($response, true);
            $message = $decoded['message'] ?? $response;
            throw new Exception("LinkedIn API error (HTTP {$httpCode}): {$message}");
        }

        $decoded = json_decode($response, true);
        return $decoded ?? [];
    }
}
