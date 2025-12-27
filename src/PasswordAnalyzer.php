<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Analyze cracked passwords for patterns and statistics
 */
class PasswordAnalyzer
{
    private array $passwords = [];
    private array $stats = [];
    
    public function __construct(array $passwords = [])
    {
        $this->passwords = $passwords;
    }
    
    public function addPassword(string $password): void
    {
        $this->passwords[] = $password;
    }
    
    public function addPasswords(array $passwords): void
    {
        $this->passwords = array_merge($this->passwords, $passwords);
    }
    
    public function analyze(): array
    {
        if (empty($this->passwords)) {
            return ['error' => 'No passwords to analyze'];
        }
        
        $this->stats = [
            'total' => count($this->passwords),
            'unique' => count(array_unique($this->passwords)),
            'length' => $this->analyzeLengths(),
            'charset' => $this->analyzeCharsets(),
            'patterns' => $this->analyzePatterns(),
            'common' => $this->findCommon(),
            'strength' => $this->analyzeStrength(),
        ];
        
        return $this->stats;
    }
    
    private function analyzeLengths(): array
    {
        $lengths = array_map('strlen', $this->passwords);
        
        $distribution = array_count_values($lengths);
        ksort($distribution);
        
        return [
            'min' => min($lengths),
            'max' => max($lengths),
            'avg' => round(array_sum($lengths) / count($lengths), 2),
            'median' => $this->median($lengths),
            'distribution' => $distribution,
        ];
    }
    
    private function analyzeCharsets(): array
    {
        $stats = [
            'lowercase_only' => 0,
            'uppercase_only' => 0,
            'digits_only' => 0,
            'alpha_only' => 0,
            'alphanumeric' => 0,
            'with_special' => 0,
            'mixed_case' => 0,
        ];
        
        foreach ($this->passwords as $password) {
            $hasLower = preg_match('/[a-z]/', $password);
            $hasUpper = preg_match('/[A-Z]/', $password);
            $hasDigit = preg_match('/[0-9]/', $password);
            $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);
            
            if ($hasLower && !$hasUpper && !$hasDigit && !$hasSpecial) {
                $stats['lowercase_only']++;
            }
            if ($hasUpper && !$hasLower && !$hasDigit && !$hasSpecial) {
                $stats['uppercase_only']++;
            }
            if ($hasDigit && !$hasLower && !$hasUpper && !$hasSpecial) {
                $stats['digits_only']++;
            }
            if (($hasLower || $hasUpper) && !$hasDigit && !$hasSpecial) {
                $stats['alpha_only']++;
            }
            if (($hasLower || $hasUpper) && $hasDigit && !$hasSpecial) {
                $stats['alphanumeric']++;
            }
            if ($hasSpecial) {
                $stats['with_special']++;
            }
            if ($hasLower && $hasUpper) {
                $stats['mixed_case']++;
            }
        }
        
        // Convert to percentages
        $total = count($this->passwords);
        $percentages = [];
        foreach ($stats as $key => $value) {
            $percentages[$key] = [
                'count' => $value,
                'percent' => round(($value / $total) * 100, 1),
            ];
        }
        
        return $percentages;
    }
    
    private function analyzePatterns(): array
    {
        $patterns = [
            'starts_uppercase' => 0,
            'ends_digit' => 0,
            'ends_special' => 0,
            'keyboard_pattern' => 0,
            'repeated_chars' => 0,
            'sequential_digits' => 0,
            'year_pattern' => 0,
            'common_suffix' => 0,
        ];
        
        $keyboardPatterns = [
            'qwerty', 'asdf', 'zxcv', '1234', '4321',
            'qwertyuiop', 'asdfghjkl', 'zxcvbnm',
        ];
        
        $commonSuffixes = [
            '123', '1234', '12345', '!', '1', '!@#',
            '2023', '2024', '2025', '69', '420',
        ];
        
        foreach ($this->passwords as $password) {
            // Starts with uppercase
            if (preg_match('/^[A-Z]/', $password)) {
                $patterns['starts_uppercase']++;
            }
            
            // Ends with digit
            if (preg_match('/[0-9]$/', $password)) {
                $patterns['ends_digit']++;
            }
            
            // Ends with special
            if (preg_match('/[^a-zA-Z0-9]$/', $password)) {
                $patterns['ends_special']++;
            }
            
            // Keyboard patterns
            $lower = strtolower($password);
            foreach ($keyboardPatterns as $pattern) {
                if (str_contains($lower, $pattern)) {
                    $patterns['keyboard_pattern']++;
                    break;
                }
            }
            
            // Repeated characters (3+)
            if (preg_match('/(.)\1{2,}/', $password)) {
                $patterns['repeated_chars']++;
            }
            
            // Sequential digits
            if (preg_match('/012|123|234|345|456|567|678|789|890/', $password)) {
                $patterns['sequential_digits']++;
            }
            
            // Year pattern (19xx or 20xx)
            if (preg_match('/19[0-9]{2}|20[0-2][0-9]/', $password)) {
                $patterns['year_pattern']++;
            }
            
            // Common suffixes
            foreach ($commonSuffixes as $suffix) {
                if (str_ends_with($password, $suffix)) {
                    $patterns['common_suffix']++;
                    break;
                }
            }
        }
        
        // Convert to percentages
        $total = count($this->passwords);
        $result = [];
        foreach ($patterns as $key => $value) {
            $result[$key] = [
                'count' => $value,
                'percent' => round(($value / $total) * 100, 1),
            ];
        }
        
        return $result;
    }
    
    private function findCommon(): array
    {
        $counts = array_count_values($this->passwords);
        arsort($counts);
        
        // Top 10 most common
        $top = array_slice($counts, 0, 10, true);
        
        // Base words (without trailing numbers/special)
        $baseWords = [];
        foreach ($this->passwords as $password) {
            $base = preg_replace('/[0-9!@#$%^&*()]+$/', '', strtolower($password));
            if (strlen($base) >= 3) {
                $baseWords[$base] = ($baseWords[$base] ?? 0) + 1;
            }
        }
        arsort($baseWords);
        $topBases = array_slice($baseWords, 0, 10, true);
        
        return [
            'passwords' => $top,
            'base_words' => $topBases,
        ];
    }
    
    private function analyzeStrength(): array
    {
        $strengths = [
            'very_weak' => 0,  // < 6 chars or digits only
            'weak' => 0,       // 6-7 chars, single charset
            'medium' => 0,     // 8+ chars, 2 charsets
            'strong' => 0,     // 10+ chars, 3+ charsets
            'very_strong' => 0, // 12+ chars, 4 charsets
        ];
        
        foreach ($this->passwords as $password) {
            $len = strlen($password);
            $charsets = 0;
            
            if (preg_match('/[a-z]/', $password)) $charsets++;
            if (preg_match('/[A-Z]/', $password)) $charsets++;
            if (preg_match('/[0-9]/', $password)) $charsets++;
            if (preg_match('/[^a-zA-Z0-9]/', $password)) $charsets++;
            
            if ($len < 6 || ($charsets === 1 && preg_match('/^[0-9]+$/', $password))) {
                $strengths['very_weak']++;
            } elseif ($len < 8 || $charsets === 1) {
                $strengths['weak']++;
            } elseif ($len < 10 || $charsets === 2) {
                $strengths['medium']++;
            } elseif ($len < 12 || $charsets === 3) {
                $strengths['strong']++;
            } else {
                $strengths['very_strong']++;
            }
        }
        
        // Convert to percentages
        $total = count($this->passwords);
        $result = [];
        foreach ($strengths as $key => $value) {
            $result[$key] = [
                'count' => $value,
                'percent' => round(($value / $total) * 100, 1),
            ];
        }
        
        return $result;
    }
    
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = (int)floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }
    
    public function getReport(bool $color = true): string
    {
        if (empty($this->stats)) {
            $this->analyze();
        }
        
        $cyan = $color ? "\e[1;36m" : "";
        $green = $color ? "\e[1;32m" : "";
        $yellow = $color ? "\e[1;33m" : "";
        $red = $color ? "\e[1;31m" : "";
        $reset = $color ? "\e[0m" : "";
        $bold = $color ? "\e[1m" : "";
        
        $report = "\n{$cyan}=== Password Analysis Report ==={$reset}\n\n";
        
        // Overview
        $report .= "{$bold}Overview:{$reset}\n";
        $report .= "  Total passwords:  {$this->stats['total']}\n";
        $report .= "  Unique passwords: {$this->stats['unique']}\n\n";
        
        // Length stats
        $report .= "{$bold}Length Statistics:{$reset}\n";
        $report .= "  Minimum: {$this->stats['length']['min']}\n";
        $report .= "  Maximum: {$this->stats['length']['max']}\n";
        $report .= "  Average: {$this->stats['length']['avg']}\n";
        $report .= "  Median:  {$this->stats['length']['median']}\n\n";
        
        // Charset analysis
        $report .= "{$bold}Character Set Usage:{$reset}\n";
        foreach ($this->stats['charset'] as $type => $data) {
            $label = str_replace('_', ' ', ucfirst($type));
            $bar = str_repeat('█', (int)($data['percent'] / 5));
            $report .= sprintf("  %-18s %5.1f%% %s\n", $label . ':', $data['percent'], $bar);
        }
        $report .= "\n";
        
        // Patterns
        $report .= "{$bold}Common Patterns:{$reset}\n";
        foreach ($this->stats['patterns'] as $type => $data) {
            if ($data['count'] > 0) {
                $label = str_replace('_', ' ', ucfirst($type));
                $report .= sprintf("  %-20s %5.1f%% (%d)\n", $label . ':', $data['percent'], $data['count']);
            }
        }
        $report .= "\n";
        
        // Strength distribution
        $report .= "{$bold}Password Strength:{$reset}\n";
        $strengthColors = [
            'very_weak' => $red,
            'weak' => $red,
            'medium' => $yellow,
            'strong' => $green,
            'very_strong' => $green,
        ];
        foreach ($this->stats['strength'] as $level => $data) {
            $label = str_replace('_', ' ', ucfirst($level));
            $c = $strengthColors[$level] ?? '';
            $bar = str_repeat('█', (int)($data['percent'] / 5));
            $report .= sprintf("  {$c}%-15s{$reset} %5.1f%% %s\n", $label . ':', $data['percent'], $bar);
        }
        $report .= "\n";
        
        // Top passwords
        if (!empty($this->stats['common']['passwords'])) {
            $report .= "{$bold}Top 10 Most Common:{$reset}\n";
            $i = 1;
            foreach ($this->stats['common']['passwords'] as $pass => $count) {
                $masked = strlen($pass) > 3 ? substr($pass, 0, 3) . str_repeat('*', strlen($pass) - 3) : $pass;
                $report .= sprintf("  %2d. %-20s (%d)\n", $i++, $masked, $count);
            }
            $report .= "\n";
        }
        
        // Top base words
        if (!empty($this->stats['common']['base_words'])) {
            $report .= "{$bold}Top 10 Base Words:{$reset}\n";
            $i = 1;
            foreach ($this->stats['common']['base_words'] as $word => $count) {
                $report .= sprintf("  %2d. %-20s (%d)\n", $i++, $word, $count);
            }
        }
        
        return $report;
    }
    
    public function getStats(): array
    {
        return $this->stats;
    }
}
