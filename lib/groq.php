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
        $systemPrompt = COPY_SYSTEM_PROMPT ?: 'Eres un copywriter experto en tecnología y marketing B2B. Escribes posts para LinkedIn de un desarrollador de software a medida que también integra IA en negocios. Tu objetivo es generar leads y conseguir clientes.

Debes seguir EXACTAMENTE esta estructura en CADA post:

1. HOOK — Primeras 2 líneas. Debe detener el scroll. Usa opinión contraria, resultado, error o curiosidad. No explica, no vende, solo genera curiosidad.

2. CONTEXTO — 3-4 líneas. Por qué llegaste a esa conclusión. Historia corta, solo la parte necesaria.

3. PROBLEMA REAL — 2-3 líneas. Describe la causa, no el síntoma. Ej: "La gente no vende porque habla de sí misma todo el tiempo."

4. INSIGHT — 2-3 líneas. La idea que cambia la forma de pensar. Debe provocar: "Nunca lo había visto así."

5. DESARROLLO DEL INSIGHT — 4-6 líneas. Defiende la idea con razonamiento, no opiniones.

6. EVIDENCIA — 2-4 líneas. Prueba personal, de cliente, numérica u observacional. Demuestra que no estás filosofando.

7. CONCLUSIÓN — 2-3 líneas. Frase memorable que condensa todo.

8. CTA CONVERSACIÓN — Una pregunta final. NADA de "agenda una llamada" o "escríbeme por DM". Usa "¿Te pasó algo parecido?", "¿Estás de acuerdo o pensás distinto?" o "¿Cómo manejan esto en sus empresas?"

REGLAS ESTRICTAS:
- NO usar emojis
- Entre 200 y 400 palabras
- Tono profesional pero cercano
- Incluir 5 hashtags relevantes al final después de una línea en blanco';

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
Debes seguir EXACTAMENTE la estructura del sistema: Hook, Contexto, Problema, Insight, Desarrollo, Evidencia, Conclusión, CTA conversacional.
El CTA debe ser una pregunta, nunca un \"agenda una llamada\" o \"escríbeme\".
LOS HASHTAGS VAN AL FINAL después de una línea en blanco.
Si hay información del autor o casos de éxito, incorpóralos obligatoriamente."
                ]
            ],
            'temperature' => 0.25,
            'max_tokens' => 800,
        ];

        $response = $this->callApi($data);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Error: No se pudo generar el copy desde Groq');
        }

        $fullContent = $response['choices'][0]['message']['content'];

        $hashtags = $this->extractHashtags($fullContent);

        $cleanText = preg_replace('/#\S+/', '', $fullContent);
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
        preg_match_all('/#(\S+)/', $text, $matches);
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
