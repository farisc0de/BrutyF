<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Configuration manager
 */
class Config
{
    private array $config = [];
    private static array $defaults = [
        'threads' => 1,
        'verbose' => false,
        'quiet' => false,
        'color' => true,
        'output_format' => 'text',
        'hash_type' => 'auto',
        'rules' => [],
        'log_file' => null,
        'log_level' => 'info',
    ];

    public function __construct()
    {
        $this->config = self::$defaults;
    }

    public function loadFromFile(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }

        // Support both JSON and INI-style config
        if (str_starts_with(trim($content), '{')) {
            $parsed = json_decode($content, true);
        } else {
            $parsed = parse_ini_string($content, false, INI_SCANNER_TYPED);
        }

        if (is_array($parsed)) {
            $this->config = array_merge($this->config, $parsed);
            return true;
        }

        return false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    public static function getConfigPaths(): array
    {
        $paths = [];
        
        // Current directory
        $paths[] = getcwd() . '/brutyf.conf';
        $paths[] = getcwd() . '/.brutyfrc';
        
        // Home directory
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if ($home) {
            $paths[] = $home . '/.brutyfrc';
            $paths[] = $home . '/.config/brutyf/config';
        }

        return $paths;
    }
}
