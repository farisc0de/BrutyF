<?php
declare(strict_types=1);

namespace BrutyF;

class Benchmark
{
    private const TEST_PASSWORD = 'BenchmarkTest123!';
    private const ITERATIONS = 1000;
    
    private array $results = [];
    
    public function run(bool $color = true): void
    {
        $this->printHeader($color);
        
        $hashTypes = [
            'md5' => fn($p) => md5($p),
            'sha1' => fn($p) => sha1($p),
            'sha256' => fn($p) => hash('sha256', $p),
            'sha512' => fn($p) => hash('sha512', $p),
            'ntlm' => fn($p) => strtoupper(hash('md4', mb_convert_encoding($p, 'UTF-16LE', 'UTF-8'))),
            'mysql5' => fn($p) => '*' . strtoupper(sha1(sha1($p, true))),
            'bcrypt (cost 10)' => fn($p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => 10]),
            'bcrypt (cost 12)' => fn($p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]),
        ];
        
        foreach ($hashTypes as $name => $hashFunc) {
            $this->benchmarkHash($name, $hashFunc, $color);
        }
        
        $this->printSummary($color);
    }
    
    private function benchmarkHash(string $name, callable $hashFunc, bool $color): void
    {
        $iterations = str_contains($name, 'bcrypt') ? 10 : self::ITERATIONS;
        
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $hashFunc(self::TEST_PASSWORD . $i);
        }
        $elapsed = microtime(true) - $start;
        
        $speed = $iterations / $elapsed;
        $this->results[$name] = [
            'iterations' => $iterations,
            'time' => $elapsed,
            'speed' => $speed,
        ];
        
        $speedStr = $this->formatSpeed($speed);
        $timeStr = sprintf('%.4fs', $elapsed);
        
        if ($color) {
            echo sprintf(
                "  \e[1;36m%-20s\e[0m %10s iterations in %10s = \e[1;32m%s\e[0m\n",
                $name,
                number_format($iterations),
                $timeStr,
                $speedStr
            );
        } else {
            echo sprintf(
                "  %-20s %10s iterations in %10s = %s\n",
                $name,
                number_format($iterations),
                $timeStr,
                $speedStr
            );
        }
    }
    
    private function formatSpeed(float $speed): string
    {
        if ($speed >= 1000000) {
            return sprintf('%.2f MH/s', $speed / 1000000);
        } elseif ($speed >= 1000) {
            return sprintf('%.2f kH/s', $speed / 1000);
        } else {
            return sprintf('%.2f H/s', $speed);
        }
    }
    
    private function printHeader(bool $color): void
    {
        if ($color) {
            echo "\n\e[1;33m=== BrutyF Benchmark ===\e[0m\n\n";
            echo "\e[1mTesting hash speeds...\e[0m\n\n";
        } else {
            echo "\n=== BrutyF Benchmark ===\n\n";
            echo "Testing hash speeds...\n\n";
        }
    }
    
    private function printSummary(bool $color): void
    {
        // Find fastest and slowest
        $speeds = array_column($this->results, 'speed');
        $names = array_keys($this->results);
        
        $fastestIdx = array_search(max($speeds), $speeds);
        $slowestIdx = array_search(min($speeds), $speeds);
        
        if ($color) {
            echo "\n\e[1;33m=== Summary ===\e[0m\n";
            echo "  Fastest: \e[1;32m{$names[$fastestIdx]}\e[0m ({$this->formatSpeed($speeds[$fastestIdx])})\n";
            echo "  Slowest: \e[1;31m{$names[$slowestIdx]}\e[0m ({$this->formatSpeed($speeds[$slowestIdx])})\n";
            echo "\n\e[1mNote:\e[0m bcrypt is intentionally slow for security.\n";
            echo "Fast hashes (MD5, SHA1) are easier to crack.\n\n";
        } else {
            echo "\n=== Summary ===\n";
            echo "  Fastest: {$names[$fastestIdx]} ({$this->formatSpeed($speeds[$fastestIdx])})\n";
            echo "  Slowest: {$names[$slowestIdx]} ({$this->formatSpeed($speeds[$slowestIdx])})\n";
            echo "\nNote: bcrypt is intentionally slow for security.\n";
            echo "Fast hashes (MD5, SHA1) are easier to crack.\n\n";
        }
    }
    
    public function getResults(): array
    {
        return $this->results;
    }
}
