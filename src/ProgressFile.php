<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Write progress to file for external monitoring
 */
class ProgressFile
{
    private ?string $path;
    private float $lastUpdate = 0;
    private float $updateInterval;
    
    public function __construct(?string $path = null, float $updateInterval = 1.0)
    {
        $this->path = $path;
        $this->updateInterval = $updateInterval;
    }
    
    public function update(array $data): void
    {
        if ($this->path === null) {
            return;
        }
        
        $now = microtime(true);
        if ($now - $this->lastUpdate < $this->updateInterval) {
            return;
        }
        
        $this->lastUpdate = $now;
        $this->write($data);
    }
    
    public function write(array $data): void
    {
        if ($this->path === null) {
            return;
        }
        
        $data['timestamp'] = date('c');
        $data['unix_timestamp'] = time();
        
        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    }
    
    public function clear(): void
    {
        if ($this->path !== null && file_exists($this->path)) {
            unlink($this->path);
        }
    }
    
    public function getPath(): ?string
    {
        return $this->path;
    }
    
    public static function read(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        
        return json_decode($content, true);
    }
}
