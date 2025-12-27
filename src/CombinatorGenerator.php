<?php
declare(strict_types=1);

namespace BrutyF;

class CombinatorGenerator implements \Iterator
{
    private array $words1 = [];
    private array $words2 = [];
    private int $index1 = 0;
    private int $index2 = 0;
    private string $separator;
    
    public function __construct(array $words1, array $words2, string $separator = '')
    {
        $this->words1 = $words1;
        $this->words2 = $words2;
        $this->separator = $separator;
    }
    
    public function getTotalCombinations(): int
    {
        return count($this->words1) * count($this->words2);
    }
    
    public function current(): string
    {
        return $this->words1[$this->index1] . $this->separator . $this->words2[$this->index2];
    }
    
    public function key(): int
    {
        return $this->index1 * count($this->words2) + $this->index2;
    }
    
    public function next(): void
    {
        $this->index2++;
        if ($this->index2 >= count($this->words2)) {
            $this->index2 = 0;
            $this->index1++;
        }
    }
    
    public function rewind(): void
    {
        $this->index1 = 0;
        $this->index2 = 0;
    }
    
    public function valid(): bool
    {
        return $this->index1 < count($this->words1);
    }
    
    public static function fromFiles(string $file1, string $file2, string $separator = ''): self
    {
        $words1 = self::loadWordlist($file1);
        $words2 = self::loadWordlist($file2);
        return new self($words1, $words2, $separator);
    }
    
    private static function loadWordlist(string $file): array
    {
        $words = [];
        
        // Handle compressed files
        if (str_ends_with($file, '.gz')) {
            $handle = gzopen($file, 'r');
        } elseif (str_ends_with($file, '.bz2')) {
            $handle = bzopen($file, 'r');
        } else {
            $handle = fopen($file, 'r');
        }
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $words[] = trim($line);
            }
            fclose($handle);
        }
        
        return $words;
    }
}
