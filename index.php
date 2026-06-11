<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/groq.php';
require_once __DIR__ . '/lib/image.php';
require_once __DIR__ . '/lib/linkedin.php';

class LinkedInBot
{
    private GroqClient $groq;
    private ImageGenerator $imageGen;
    private LinkedInClient $linkedin;

    private array $topics = [
        1 => 'Automatización de procesos empresariales con software a medida',
        2 => 'Seguridad informática: protege tu negocio con soluciones personalizadas',
        3 => 'Transformación digital para pequeñas y medianas empresas',
        4 => 'Integración de inteligencia artificial en sistemas legacy',
        5 => 'Casos de éxito: cómo el software a medida impulsa resultados',
        6 => 'Tendencias tecnológicas que todo negocio debería conocer',
        0 => 'Consejos para optimizar procesos con tecnología',
    ];

    public function __construct()
    {
        $this->groq = new GroqClient();
        $this->imageGen = new ImageGenerator();
        $this->linkedin = new LinkedInClient();
    }

    public function run(bool $force = false): void
    {
        $today = new DateTime();
        $dayOfWeek = (int)$today->format('w');

        $topic = $this->topics[$dayOfWeek];
        $log = $this->loadLog();
        $todayStr = $today->format('Y-m-d');

        if (!$force && $this->alreadyPostedToday($log, $todayStr)) {
            echo "[INFO] Ya se publicó un post hoy ({$todayStr}). Saliendo.\n";
            return;
        }

        if ($force) {
            echo "[INFO] Modo forzado: generando post aunque ya haya uno hoy.\n";
        }

        if (!$this->topicAvailableToday($log, $topic)) {
            echo "[INFO] El tema de hoy ya fue usado recientemente. Usando tema alternativo.\n";
            $topic = $this->getAlternativeTopic($log);
        }

        echo "[INFO] Tema de hoy: {$topic}\n";

        try {
            echo "[INFO] Generando copy con Groq...\n";
            $post = $this->groq->generatePost($topic);
            echo "[OK] Copy generado.\n";

            echo "[INFO] Generando prompt para imagen...\n";
            $imagePrompt = $this->groq->generateImagePrompt($post['text']);
            echo "[OK] Prompt: {$imagePrompt}\n";

            echo "[INFO] Generando imagen con Hugging Face...\n";
            $imagePath = $this->imageGen->generate($imagePrompt);
            echo "[OK] Imagen guardada en: {$imagePath}\n";

            echo "[INFO] Publicando en LinkedIn...\n";
            $result = $this->linkedin->postWithImage(
                $post['text'],
                $imagePath,
                $post['hashtags']
            );
            echo "[OK] Post publicado: {$result['post_url']}\n";

            $this->saveLog([
                'date' => $todayStr,
                'time' => $today->format('H:i:s'),
                'topic' => $topic,
                'copy_preview' => substr($post['text'], 0, 150) . '...',
                'hashtags' => $post['hashtags'],
                'image_path' => $imagePath,
                'linkedin_post_id' => $result['post_id'],
                'linkedin_url' => $result['post_url'],
                'status' => 'success',
            ]);

            echo "[OK] Post publicado exitosamente!\n";

        } catch (Exception $e) {
            echo "[ERROR] {$e->getMessage()}\n";

            $this->saveLog([
                'date' => $todayStr,
                'time' => $today->format('H:i:s'),
                'topic' => $topic,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function alreadyPostedToday(array $log, string $date): bool
    {
        foreach ($log as $entry) {
            if ($entry['date'] === $date && $entry['status'] === 'success') {
                return true;
            }
        }
        return false;
    }

    private function topicAvailableToday(array $log, string $topic): bool
    {
        $recentTopics = [];
        foreach (array_slice($log, -7) as $entry) {
            if (isset($entry['topic'])) {
                $recentTopics[] = $entry['topic'];
            }
        }
        return !in_array($topic, $recentTopics);
    }

    private function getAlternativeTopic(array $log): string
    {
        $usedTopics = [];
        foreach (array_slice($log, -14) as $entry) {
            if (isset($entry['topic'])) {
                $usedTopics[] = $entry['topic'];
            }
        }

        $alternatives = [
            'Desarrollo de software a medida vs soluciones genéricas',
            'Por qué tu negocio necesita una app personalizada',
            'IA generativa aplicada a procesos de negocio',
            'Modernización de sistemas: migración a la nube',
            'Cómo reducir costos operativos con automatización inteligente',
            'MVP: cómo validar tu idea de negocio con software',
            'Integración de APIs para potenciar tu negocio',
            'Bases de datos: optimización y rendimiento empresarial',
            'Chatbots con IA para servicio al cliente',
            'Paneles de control y dashboards para toma de decisiones',
        ];

        $available = array_diff($alternatives, $usedTopics);
        if (empty($available)) {
            return $alternatives[array_rand($alternatives)];
        }

        return $available[array_rand($available)];
    }

    private function loadLog(): array
    {
        if (!file_exists(LOGS_PATH)) {
            return [];
        }
        $log = json_decode(file_get_contents(LOGS_PATH), true);
        return is_array($log) ? $log : [];
    }

    private function saveLog(array $entry): void
    {
        $log = $this->loadLog();
        $log[] = $entry;
        $log = array_slice($log, -100);

        $dir = dirname(LOGS_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(LOGS_PATH, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

$force = in_array('--force', $argv ?? []);
$bot = new LinkedInBot();
$bot->run($force);
