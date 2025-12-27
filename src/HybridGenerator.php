<?php
declare(strict_types=1);

namespace BrutyF;

class HybridGenerator implements \Iterator
{
    private array $words = [];
    private string $mask;
    private array $charsets;
    private int $wordIndex = 0;
    private int $maskIndex = 0;
    private int $totalMaskCombinations;
    private array $maskPositions = [];
    private bool $prepend;
    
    private const CHARSETS = [
        'l' => 'abcdefghijklmnopqrstuvwxyz',
        'u' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'd' => '0123456789',
        's' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
        'a' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?',
    ];
    
    public function __construct(array $words, string $mask, bool $prepend = false)
    {
        $this->words = $words;
        $this->mask = $mask;
        $this->prepend = $prepend;
        $this->charsets = $this->parseMask($mask);
        $this->totalMaskCombinations = $this->calculateTotalCombinations();
    }
    
    private function parseMask(string $mask): array
    {
        $charsets = [];
        $i = 0;
        while ($i < strlen($mask)) {
            if ($mask[$i] === '?' && isset($mask[$i + 1])) {
                $type = $mask[$i + 1];
                if (isset(self::CHARSETS[$type])) {
                    $charsets[] = self::CHARSETS[$type];
                } else {
                    $charsets[] = $type;
                }
                $i += 2;
            } else {
                $charsets[] = $mask[$i];
                $i++;
            }
        }
        return $charsets;
    }
    
    private function calculateTotalCombinations(): int
    {
        if (empty($this->charsets)) {
            return 1;
        }
        $total = 1;
        foreach ($this->charsets as $charset) {
            $total *= strlen($charset);
        }
        return $total;
    }
    
    public function getTotalCombinations(): int
    {
        return count($this->words) * $this->totalMaskCombinations;
    }
    
    private function getMaskString(int $index): string
    {
        if (empty($this->charsets)) {
            return '';
        }
        
        $result = '';
        foreach ($this->charsets as $charset) {
            $charsetLen = strlen($charset);
            $charIndex = $index % $charsetLen;
            $result .= $charset[$charIndex];
            $index = intdiv($index, $charsetLen);
        }
        return $result;
    }
    
    public function current(): string
    {
        $word = $this->words[$this->wordIndex];
        $maskStr = $this->getMaskString($this->maskIndex);
        
        if ($this->prepend) {
            return $maskStr . $word;
        }
        return $word . $maskStr;
    }
    
    public function key(): int
    {
        return $this->wordIndex * $this->totalMaskCombinations + $this->maskIndex;
    }
    
    public function next(): void
    {
        $this->maskIndex++;
        if ($this->maskIndex >= $this->totalMaskCombinations) {
            $this->maskIndex = 0;
            $this->wordIndex++;
        }
    }
    
    public function rewind(): void
    {
        $this->wordIndex = 0;
        $this->maskIndex = 0;
    }
    
    public function valid(): bool
    {
        return $this->wordIndex < count($this->words);
    }
    
    public static function fromWordlistFile(string $file, string $mask, bool $prepend = false): self
    {
        $words = [];
        $handle = fopen($file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $words[] = trim($line);
            }
            fclose($handle);
        }
        return new self($words, $mask, $prepend);
    }
}
