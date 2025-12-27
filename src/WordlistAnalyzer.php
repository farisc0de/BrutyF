<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Analyze wordlist files for statistics
 */
class WordlistAnalyzer
{
    private string $file;
    private array $stats = [];
    
    public function __construct(string $file)
    {
        $this->file = $file;
    }
    
    public function analyze(int $sampleSize = 10000): array
    {
        if (!file_exists($this->file)) {
            return ['error' => 'File not found'];
        }
        
        $fileSize = filesize($this->file);
        $handle = $this->openFile();
        
        if (!$handle) {
            return ['error' => 'Failed to open file'];
        }
        
        $totalLines = 0;
        $totalChars = 0;
        $lengths = [];
        $charsets = [
            'lowercase' => 0,
            'uppercase' => 0,
            'digits' => 0,
            'special' => 0,
            'mixed' => 0,
        ];
        $duplicates = [];
        $sample = [];
        
        while (($line = fgets($handle)) !== false) {
            $word = trim($line);
            if (empty($word)) continue;
            
            $totalLines++;
            $len = strlen($word);
            $totalChars += $len;
            
            // Track lengths
            $lengths[$len] = ($lengths[$len] ?? 0) + 1;
            
            // Track duplicates (only for reasonable sized files)
            if ($totalLines <= 1000000) {
                $duplicates[$word] = ($duplicates[$word] ?? 0) + 1;
            }
            
            // Charset analysis (sample)
            if ($totalLines <= $sampleSize) {
                $hasLower = preg_match('/[a-z]/', $word);
                $hasUpper = preg_match('/[A-Z]/', $word);
                $hasDigit = preg_match('/[0-9]/', $word);
                $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $word);
                
                $types = ($hasLower ? 1 : 0) + ($hasUpper ? 1 : 0) + ($hasDigit ? 1 : 0) + ($hasSpecial ? 1 : 0);
                
                if ($types > 1) {
                    $charsets['mixed']++;
                } elseif ($hasLower) {
                    $charsets['lowercase']++;
                } elseif ($hasUpper) {
                    $charsets['uppercase']++;
                } elseif ($hasDigit) {
                    $charsets['digits']++;
                } elseif ($hasSpecial) {
                    $charsets['special']++;
                }
                
                $sample[] = $word;
            }
        }
        
        fclose($handle);
        
        // Calculate statistics
        ksort($lengths);
        $uniqueCount = count(array_filter($duplicates, fn($c) => $c === 1));
        $duplicateCount = $totalLines - $uniqueCount;
        
        $lengthValues = [];
        foreach ($lengths as $len => $count) {
            for ($i = 0; $i < $count; $i++) {
                $lengthValues[] = $len;
            }
        }
        
        $this->stats = [
            'file' => basename($this->file),
            'file_size' => $fileSize,
            'file_size_formatted' => $this->formatBytes($fileSize),
            'total_lines' => $totalLines,
            'unique_words' => $uniqueCount,
            'duplicates' => $duplicateCount,
            'duplicate_percent' => $totalLines > 0 ? round(($duplicateCount / $totalLines) * 100, 2) : 0,
            'avg_length' => $totalLines > 0 ? round($totalChars / $totalLines, 2) : 0,
            'min_length' => !empty($lengths) ? min(array_keys($lengths)) : 0,
            'max_length' => !empty($lengths) ? max(array_keys($lengths)) : 0,
            'median_length' => $this->median($lengthValues),
            'length_distribution' => $lengths,
            'charset_distribution' => $charsets,
            'sample_size' => count($sample),
            'compressed' => str_ends_with($this->file, '.gz') || str_ends_with($this->file, '.bz2'),
        ];
        
        return $this->stats;
    }
    
    private function openFile()
    {
        if (str_ends_with($this->file, '.gz')) {
            return gzopen($this->file, 'r');
        }
        if (str_ends_with($this->file, '.bz2')) {
            return bzopen($this->file, 'r');
        }
        return fopen($this->file, 'r');
    }
    
    private function median(array $values): float
    {
        if (empty($values)) return 0;
        
        sort($values);
        $count = count($values);
        $middle = (int)floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    public function getReport(bool $color = true): string
    {
        if (empty($this->stats)) {
            $this->analyze();
        }
        
        $cyan = $color ? "\e[1;36m" : "";
        $green = $color ? "\e[1;32m" : "";
        $yellow = $color ? "\e[1;33m" : "";
        $reset = $color ? "\e[0m" : "";
        $bold = $color ? "\e[1m" : "";
        
        $s = $this->stats;
        
        $report = "\n{$cyan}=== Wordlist Analysis ==={$reset}\n\n";
        
        $report .= "{$bold}File Information:{$reset}\n";
        $report .= "  File:        {$s['file']}\n";
        $report .= "  Size:        {$s['file_size_formatted']}\n";
        $report .= "  Compressed:  " . ($s['compressed'] ? 'Yes' : 'No') . "\n\n";
        
        $report .= "{$bold}Word Statistics:{$reset}\n";
        $report .= "  Total words:    " . number_format($s['total_lines']) . "\n";
        $report .= "  Unique words:   " . number_format($s['unique_words']) . "\n";
        $report .= "  Duplicates:     " . number_format($s['duplicates']) . " ({$s['duplicate_percent']}%)\n\n";
        
        $report .= "{$bold}Length Statistics:{$reset}\n";
        $report .= "  Minimum:  {$s['min_length']}\n";
        $report .= "  Maximum:  {$s['max_length']}\n";
        $report .= "  Average:  {$s['avg_length']}\n";
        $report .= "  Median:   {$s['median_length']}\n\n";
        
        $report .= "{$bold}Length Distribution (top 10):{$reset}\n";
        arsort($s['length_distribution']);
        $top = array_slice($s['length_distribution'], 0, 10, true);
        foreach ($top as $len => $count) {
            $percent = round(($count / $s['total_lines']) * 100, 1);
            $bar = str_repeat('█', (int)($percent / 2));
            $report .= sprintf("  %2d chars: %6.1f%% %s\n", $len, $percent, $bar);
        }
        $report .= "\n";
        
        if ($s['sample_size'] > 0) {
            $report .= "{$bold}Charset Distribution (sample of {$s['sample_size']}):{$reset}\n";
            foreach ($s['charset_distribution'] as $type => $count) {
                $percent = round(($count / $s['sample_size']) * 100, 1);
                $report .= sprintf("  %-12s %5.1f%%\n", ucfirst($type) . ':', $percent);
            }
        }
        
        return $report;
    }
    
    public function getStats(): array
    {
        return $this->stats;
    }
}
