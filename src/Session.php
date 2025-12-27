<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Session manager for resume support
 */
class Session
{
    private string $sessionFile;
    private array $data = [];

    public function __construct(string $hashFile, string $wordlistFile)
    {
        $hash = md5($hashFile . $wordlistFile);
        $this->sessionFile = sys_get_temp_dir() . "/brutyf_session_{$hash}.json";
        $this->load();
    }

    private function load(): void
    {
        if (file_exists($this->sessionFile)) {
            $content = file_get_contents($this->sessionFile);
            if ($content !== false) {
                $this->data = json_decode($content, true) ?? [];
            }
        }
    }

    public function save(): void
    {
        file_put_contents($this->sessionFile, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    public function getProgress(string $hash): int
    {
        return $this->data['progress'][$hash] ?? 0;
    }

    public function setProgress(string $hash, int $position): void
    {
        $this->data['progress'][$hash] = $position;
    }

    public function getFound(): array
    {
        return $this->data['found'] ?? [];
    }

    public function addFound(string $hash, string $password): void
    {
        $this->data['found'][$hash] = $password;
    }

    public function isHashCracked(string $hash): bool
    {
        return isset($this->data['found'][$hash]);
    }

    public function clear(): void
    {
        $this->data = [];
        if (file_exists($this->sessionFile)) {
            unlink($this->sessionFile);
        }
    }

    public function exists(): bool
    {
        return file_exists($this->sessionFile) && !empty($this->data);
    }

    public function getSessionFile(): string
    {
        return $this->sessionFile;
    }
}
