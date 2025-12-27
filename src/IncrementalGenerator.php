<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Generate passwords incrementally by length
 * aaa -> aab -> ... -> zzz -> aaaa -> ...
 */
class IncrementalGenerator implements \Iterator
{
    private string $charset;
    private int $minLength;
    private int $maxLength;
    private int $currentLength;
    private array $indices;
    private int $charsetLen;
    private bool $finished = false;
    
    public function __construct(
        int $minLength = 1,
        int $maxLength = 8,
        string $charset = 'abcdefghijklmnopqrstuvwxyz'
    ) {
        $this->minLength = max(1, $minLength);
        $this->maxLength = max($this->minLength, $maxLength);
        $this->charset = $charset;
        $this->charsetLen = strlen($charset);
        $this->currentLength = $this->minLength;
        $this->indices = array_fill(0, $this->minLength, 0);
    }
    
    public function getTotalCombinations(): int
    {
        $total = 0;
        for ($len = $this->minLength; $len <= $this->maxLength; $len++) {
            $total += pow($this->charsetLen, $len);
        }
        return $total;
    }
    
    public function getCombinationsForLength(int $length): int
    {
        return pow($this->charsetLen, $length);
    }
    
    public function current(): string
    {
        $password = '';
        foreach ($this->indices as $index) {
            $password .= $this->charset[$index];
        }
        return $password;
    }
    
    public function key(): string
    {
        return $this->current();
    }
    
    public function next(): void
    {
        // Increment from right to left (like counting)
        for ($i = count($this->indices) - 1; $i >= 0; $i--) {
            $this->indices[$i]++;
            if ($this->indices[$i] < $this->charsetLen) {
                return; // No overflow, done
            }
            $this->indices[$i] = 0; // Overflow, continue to next position
        }
        
        // All positions overflowed, increase length
        $this->currentLength++;
        if ($this->currentLength > $this->maxLength) {
            $this->finished = true;
            return;
        }
        
        $this->indices = array_fill(0, $this->currentLength, 0);
    }
    
    public function rewind(): void
    {
        $this->currentLength = $this->minLength;
        $this->indices = array_fill(0, $this->minLength, 0);
        $this->finished = false;
    }
    
    public function valid(): bool
    {
        return !$this->finished;
    }
    
    public function getCurrentLength(): int
    {
        return $this->currentLength;
    }
    
    public function skipToLength(int $length): void
    {
        if ($length < $this->minLength || $length > $this->maxLength) {
            return;
        }
        $this->currentLength = $length;
        $this->indices = array_fill(0, $length, 0);
    }
    
    public static function getPresetCharsets(): array
    {
        return [
            'lower' => 'abcdefghijklmnopqrstuvwxyz',
            'upper' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'digits' => '0123456789',
            'alnum' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
            'all' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?',
        ];
    }
}
