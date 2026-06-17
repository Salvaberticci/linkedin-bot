<?php

require_once __DIR__ . '/../config.php';

class GroqClient
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = GROQ_API_KEY;
        $this->model = GROQ_MODEL;
    }

    public function generatePost(string $topic): array
    {
        $systemPrompt = COPY_SYSTEM_PROMPT ?: 'Eres un copywriter experto en tecnología y marketing B2B. 
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

        $context = '';
        if (defined('ABOUT_ME') && ABOUT_ME) {
            $context .= "\n\nInformación del autor (DEBES usarla para personalizar el post):\n" . ABOUT_ME;
        }
        if (defined('SUCCESS_STORIES') && SUCCESS_STORIES) {
            $context .= "\n\nCasos de éxito (DEBES mencionar al menos uno relevante al tema):\n" . SUCCESS_STORIES;
        }
        if ($context) {
            $systemPrompt .= "\n\n---\n" . $context;
        }

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => "Genera un post de LinkedIn sobre: {$topic}. 
El post debe estar orientado a conseguir clientes para un desarrollador de software a medida e integración de IA.
Incluye un CTA claro al final.
LOS HASHTAGS VAN AL FINAL después de una línea en blanco.
Debes seguir las instrucciones del sistema al pie de la letra. Si hay información del autor o casos de éxito, incorpóralos obligatoriamente en el post."
                ]
            ],
            'temperature' => 0.4,
            'max_tokens' => 800,
        ];

        $response = $this->callApi($data);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Error: No se pudo generar el copy desde Groq');
        }

        $fullContent = $response['choices'][0]['message']['content'];

        $hashtags = $this->extractHashtags($fullContent);

        $cleanText = preg_replace('/#\w+/', '', $fullContent);
        $cleanText = trim(preg_replace('/\n{3,}/', "\n\n", $cleanText));

        return [
            'full_content' => $fullContent,
            'text' => $cleanText,
            'hashtags' => $hashtags,
        ];
    }

    public function generateImagePrompt(string $copy): string
    {
        $stylePrompt = IMAGE_STYLE_PROMPT ?: 'fotografía profesional, iluminación cinematográfica, fondo oscuro con neones azules y morados';
        $systemPrompt = "Eres un experto en generar prompts para generación de imágenes.
Recibes el texto de un post de LinkedIn sobre tecnología y debes crear un prompt que represente visualmente el CONTENIDO EXACTO del post.
Reglas:
- El prompt debe ilustrar directamente lo que el post describe
- Máximo 150 caracteres
- Prompt en inglés
- Estilo: {$stylePrompt}
- Solo responde con el prompt, nada más.";

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => "Crea un prompt de imagen que represente EXACTAMENTE este contenido:\n\n" . substr($copy, 0, 500)
                ]
            ],
            'temperature' => 0.5,
            'max_tokens' => 150,
        ];

        $response = $this->callApi($data);

        if (!isset($response['choices'][0]['message']['content'])) {
            return "Modern server room with cloud computing infrastructure, blue LED lights, professional tech environment";
        }

        return trim($response['choices'][0]['message']['content']);
    }

    private function buildPrompt(string $topic): string
    {
        return "Genera un post de LinkedIn sobre: {$topic}.";
    }

    private function extractHashtags(string $text): array
    {
        preg_match_all('/#(\w+)/', $text, $matches);
        return $matches[0] ?? [];
    }

    private function callApi(array $data): array
    {
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error en Groq API: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Groq API respondió con código {$httpCode}: {$response}");
        }

        return json_decode($response, true) ?? [];
    }
}
