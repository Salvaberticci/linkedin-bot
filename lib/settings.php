<?php

class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = __DIR__ . '/../settings.json';
        if (!file_exists($path)) {
            self::$cache = [];
            return self::$cache;
        }

        $data = json_decode(file_get_contents($path), true);
        self::$cache = is_array($data) ? $data : [];
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        $settings = self::all();
        $settings[$key] = $value;
        self::$cache = $settings;

        $path = __DIR__ . '/../settings.json';
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function save(array $data): void
    {
        $settings = self::all();
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $settings[$key] = $value;
            }
        }
        self::$cache = $settings;

        $path = __DIR__ . '/../settings.json';
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
