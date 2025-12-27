<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Statistics collector
 */
class Statistics
{
    private float $startTime;
    private float $endTime = 0;
    private int $hashesProcessed = 0;
    private int $passwordsTried = 0;
    private int $passwordsFound = 0;
    private int $peakMemory = 0;

    public function start(): void
    {
        $this->startTime = microtime(true);
    }

    public function stop(): void
    {
        $this->endTime = microtime(true);
        $this->peakMemory = memory_get_peak_usage(true);
    }

    public function incrementHashes(): void
    {
        $this->hashesProcessed++;
    }

    public function incrementPasswords(int $count = 1): void
    {
        $this->passwordsTried += $count;
    }

    public function incrementFound(): void
    {
        $this->passwordsFound++;
    }

    public function getElapsedTime(): float
    {
        $end = $this->endTime > 0 ? $this->endTime : microtime(true);
        return $end - $this->startTime;
    }

    public function getSpeed(): float
    {
        $elapsed = $this->getElapsedTime();
        return $elapsed > 0 ? $this->passwordsTried / $elapsed : 0;
    }

    public function getETA(int $totalPasswords): string
    {
        $speed = $this->getSpeed();
        if ($speed <= 0) return 'N/A';

        $remaining = $totalPasswords - $this->passwordsTried;
        $seconds = (int)($remaining / $speed);

        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return sprintf('%dm %ds', $seconds / 60, $seconds % 60);
        if ($seconds < 86400) return sprintf('%dh %dm', $seconds / 3600, ($seconds % 3600) / 60);
        return sprintf('%dd %dh', $seconds / 86400, ($seconds % 86400) / 3600);
    }

    public function getReport(): array
    {
        return [
            'elapsed_time' => $this->getElapsedTime(),
            'elapsed_time_formatted' => $this->formatTime($this->getElapsedTime()),
            'hashes_processed' => $this->hashesProcessed,
            'passwords_tried' => $this->passwordsTried,
            'passwords_found' => $this->passwordsFound,
            'speed' => $this->getSpeed(),
            'speed_formatted' => number_format($this->getSpeed(), 2) . ' p/s',
            'peak_memory' => $this->peakMemory,
            'peak_memory_formatted' => $this->formatBytes($this->peakMemory),
        ];
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds < 60) return sprintf('%.2fs', $seconds);
        if ($seconds < 3600) return sprintf('%dm %.2fs', $seconds / 60, fmod($seconds, 60));
        return sprintf('%dh %dm %.2fs', $seconds / 3600, fmod($seconds, 3600) / 60, fmod($seconds, 60));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return sprintf('%.2f %s', $bytes, $units[$i]);
    }
}
