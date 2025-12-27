<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Markov chain-based password generator
 * Generates statistically likely passwords based on training data
 */
class MarkovGenerator implements \Iterator
{
    private array $chains = [];
    private int $order;
    private int $minLength;
    private int $maxLength;
    private int $maxPasswords;
    private int $generated = 0;
    private array $startChars = [];
    private ?string $current = null;
    
    public function __construct(int $order = 2, int $minLength = 4, int $maxLength = 16, int $maxPasswords = 100000)
    {
        $this->order = $order;
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->maxPasswords = $maxPasswords;
    }
    
    public function train(array $passwords): void
    {
        foreach ($passwords as $password) {
            $password = trim($password);
            if (strlen($password) < $this->order) continue;
            
            // Track starting characters
            $start = substr($password, 0, $this->order);
            $this->startChars[$start] = ($this->startChars[$start] ?? 0) + 1;
            
            // Build chain
            for ($i = 0; $i <= strlen($password) - $this->order; $i++) {
                $state = substr($password, $i, $this->order);
                $next = $i + $this->order < strlen($password) ? $password[$i + $this->order] : "\0";
                
                if (!isset($this->chains[$state])) {
                    $this->chains[$state] = [];
                }
                $this->chains[$state][$next] = ($this->chains[$state][$next] ?? 0) + 1;
            }
        }
    }
    
    public function trainFromFile(string $file, int $limit = 100000): void
    {
        $handle = $this->openFile($file);
        if (!$handle) return;
        
        $count = 0;
        while (($line = fgets($handle)) !== false && $count < $limit) {
            $password = trim($line);
            if (!empty($password)) {
                $this->train([$password]);
                $count++;
            }
        }
        
        fclose($handle);
    }
    
    private function openFile(string $file)
    {
        if (str_ends_with($file, '.gz')) {
            return gzopen($file, 'r');
        }
        if (str_ends_with($file, '.bz2')) {
            return bzopen($file, 'r');
        }
        return fopen($file, 'r');
    }
    
    public function generate(): ?string
    {
        if (empty($this->startChars) || empty($this->chains)) {
            return null;
        }
        
        // Pick a random starting state weighted by frequency
        $state = $this->weightedRandom($this->startChars);
        $password = $state;
        
        // Generate password
        $maxIterations = $this->maxLength * 2;
        for ($i = 0; $i < $maxIterations; $i++) {
            if (!isset($this->chains[$state])) {
                break;
            }
            
            $next = $this->weightedRandom($this->chains[$state]);
            
            if ($next === "\0" || strlen($password) >= $this->maxLength) {
                break;
            }
            
            $password .= $next;
            $state = substr($password, -$this->order);
        }
        
        // Validate length
        if (strlen($password) < $this->minLength || strlen($password) > $this->maxLength) {
            return null;
        }
        
        return $password;
    }
    
    public function generateMultiple(int $count): array
    {
        $passwords = [];
        $attempts = 0;
        $maxAttempts = $count * 10;
        
        while (count($passwords) < $count && $attempts < $maxAttempts) {
            $password = $this->generate();
            if ($password !== null && !in_array($password, $passwords)) {
                $passwords[] = $password;
            }
            $attempts++;
        }
        
        return $passwords;
    }
    
    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = mt_rand(1, $total);
        $cumulative = 0;
        
        foreach ($weights as $item => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return (string)$item;
            }
        }
        
        return (string)array_key_first($weights);
    }
    
    public function getChainCount(): int
    {
        return count($this->chains);
    }
    
    public function getStartCount(): int
    {
        return count($this->startChars);
    }
    
    public function save(string $file): bool
    {
        $data = [
            'order' => $this->order,
            'chains' => $this->chains,
            'startChars' => $this->startChars,
        ];
        return file_put_contents($file, json_encode($data)) !== false;
    }
    
    public function load(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return false;
        }
        
        $this->order = $data['order'] ?? $this->order;
        $this->chains = $data['chains'] ?? [];
        $this->startChars = $data['startChars'] ?? [];
        
        return true;
    }
    
    // Iterator implementation
    public function current(): ?string
    {
        return $this->current;
    }
    
    public function key(): int
    {
        return $this->generated;
    }
    
    public function next(): void
    {
        $this->generated++;
        $this->current = $this->generate();
        
        // Skip nulls
        $attempts = 0;
        while ($this->current === null && $attempts < 100 && $this->generated < $this->maxPasswords) {
            $this->current = $this->generate();
            $attempts++;
        }
    }
    
    public function rewind(): void
    {
        $this->generated = 0;
        $this->current = $this->generate();
    }
    
    public function valid(): bool
    {
        return $this->current !== null && $this->generated < $this->maxPasswords;
    }
}
