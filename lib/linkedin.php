<?php

require_once __DIR__ . '/../config.php';

class LinkedInClient
{
    private string $accessToken;
    private string $memberId;

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
        $postId = $this->createUgcPost($commentary, $mediaUrn);

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
            'https://api.linkedin.com/v2/assets?action=registerUpload',
            [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => "urn:li:member:{$this->memberId}",
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent',
                        ],
                    ],
                ],
            ]
        );

        $uploadUrl = $registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $assetUrn = $registerResponse['value']['asset'] ?? null;

        if (!$uploadUrl || !$assetUrn) {
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
                'Content-Type: image/png',
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

        return $assetUrn;
    }

    private function createUgcPost(string $commentary, string $mediaUrn): string
    {
        $postData = [
            'author' => "urn:li:member:{$this->memberId}",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $commentary,
                    ],
                    'shareMediaCategory' => 'IMAGE',
                    'media' => [
                        [
                            'status' => 'READY',
                            'description' => [
                                'text' => 'Imagen generada por IA',
                            ],
                            'media' => $mediaUrn,
                            'title' => [
                                'text' => 'Post',
                            ],
                        ],
                    ],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = $this->callApi(
            'POST',
            'https://api.linkedin.com/v2/ugcPosts',
            $postData
        );

        return $response['id'] ?? 'unknown';
    }

    public function fetchMemberId(): string
    {
        $response = $this->callApi(
            'GET',
            'https://api.linkedin.com/v2/me'
        );

        if (isset($response['sub'])) {
            return $response['sub'];
        }

        throw new Exception(
            "No se pudo obtener tu member ID automáticamente. " .
            "El token no tiene permisos de lectura (r_liteprofile).\n" .
            "Agrega manualmente tu member ID en auth/tokens.json como 'member_id'.\n" .
            "Para obtenerlo: ve a tu perfil de LinkedIn → Ver código fuente → busca 'memberId'"
        );
    }

    private function loadTokens(): void
    {
        if (!file_exists(TOKENS_PATH)) {
            throw new Exception(
                "No se encontró auth/tokens.json.\n" .
                "Abre en tu navegador: auth/linkedin-callback.php para autenticarte."
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

        $this->memberId = $tokens['member_id'] ?? $tokens['person_id'] ?? '';

        if (empty($this->memberId)) {
            $this->memberId = $this->fetchMemberId();
            $tokens['member_id'] = $this->memberId;
            file_put_contents(TOKENS_PATH, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function callApi(string $method, string $url, ?array $data = null): array
    {
        $ch = curl_init($url);
        $headers = [
            "Authorization: Bearer {$this->accessToken}",
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
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
