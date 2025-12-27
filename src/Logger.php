<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Logger class
 */
class Logger
{
    private ?string $logFile = null;
    private string $level = 'info';
    
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public function __construct(?string $logFile = null, string $level = 'info')
    {
        $this->logFile = $logFile;
        $this->level = $level;
    }

    public function log(string $level, string $message): void
    {
        if (!isset(self::LEVELS[$level]) || self::LEVELS[$level] < self::LEVELS[$this->level]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] [{$level}] {$message}\n";

        if ($this->logFile !== null) {
            file_put_contents($this->logFile, $formatted, FILE_APPEND);
        }
    }

    public function debug(string $message): void
    {
        $this->log('debug', $message);
    }

    public function info(string $message): void
    {
        $this->log('info', $message);
    }

    public function warning(string $message): void
    {
        $this->log('warning', $message);
    }

    public function error(string $message): void
    {
        $this->log('error', $message);
    }
}
