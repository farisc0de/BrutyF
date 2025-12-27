<?php
declare(strict_types=1);

namespace BrutyF;

class Potfile
{
    private string $path;
    private array $cracked = [];
    
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? $this->getDefaultPath();
        $this->load();
    }
    
    private function getDefaultPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
        return $home . DIRECTORY_SEPARATOR . '.brutyf.pot';
    }
    
    public function load(): void
    {
        if (!file_exists($this->path)) {
            return;
        }
        
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $this->cracked[$parts[0]] = $parts[1];
            }
        }
    }
    
    public function save(): bool
    {
        $content = '';
        foreach ($this->cracked as $hash => $password) {
            $content .= "{$hash}:{$password}\n";
        }
        return file_put_contents($this->path, $content) !== false;
    }
    
    public function clear(): bool
    {
        $this->cracked = [];
        if (file_exists($this->path)) {
            return unlink($this->path);
        }
        return true;
    }
    
    public function add(string $hash, string $password): void
    {
        $this->cracked[$hash] = $password;
        // Append to file immediately
        file_put_contents($this->path, "{$hash}:{$password}\n", FILE_APPEND | LOCK_EX);
    }
    
    public function get(string $hash): ?string
    {
        return $this->cracked[$hash] ?? null;
    }
    
    public function has(string $hash): bool
    {
        return isset($this->cracked[$hash]);
    }
    
    public function getAll(): array
    {
        return $this->cracked;
    }
    
    public function count(): int
    {
        return count($this->cracked);
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function filterHashes(array $hashes): array
    {
        return array_filter($hashes, fn($hash) => !$this->has($hash));
    }
    
    public function getAlreadyCracked(array $hashes): array
    {
        $found = [];
        foreach ($hashes as $hash) {
            if ($this->has($hash)) {
                $found[$hash] = $this->cracked[$hash];
            }
        }
        return $found;
    }
}
