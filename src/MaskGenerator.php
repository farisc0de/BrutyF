<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Mask-based password generator
 */
class MaskGenerator implements \Iterator
{
    private string $mask;
    private array $charsets;
    private array $positions = [];
    private array $currentIndices = [];
    private bool $finished = false;
    private int $currentPosition = 0;
    private int $totalCombinations = 0;

    private const DEFAULT_CHARSETS = [
        'l' => 'abcdefghijklmnopqrstuvwxyz',
        'u' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'd' => '0123456789',
        's' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
        'a' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?',
    ];

    public function __construct(string $mask, array $customCharsets = [])
    {
        $this->mask = $mask;
        $this->charsets = array_merge(self::DEFAULT_CHARSETS, $customCharsets);
        $this->parseMask();
        $this->calculateTotal();
    }

    private function parseMask(): void
    {
        $i = 0;
        while ($i < strlen($this->mask)) {
            if ($this->mask[$i] === '?' && $i + 1 < strlen($this->mask)) {
                $charsetKey = $this->mask[$i + 1];
                if (isset($this->charsets[$charsetKey])) {
                    $this->positions[] = [
                        'type' => 'charset',
                        'chars' => $this->charsets[$charsetKey],
                    ];
                    $this->currentIndices[] = 0;
                } else {
                    $this->positions[] = ['type' => 'literal', 'char' => $charsetKey];
                }
                $i += 2;
            } else {
                $this->positions[] = ['type' => 'literal', 'char' => $this->mask[$i]];
                $i++;
            }
        }
    }

    private function calculateTotal(): void
    {
        $total = 1;
        foreach ($this->positions as $pos) {
            if ($pos['type'] === 'charset') {
                $total *= strlen($pos['chars']);
            }
        }
        $this->totalCombinations = $total;
    }

    public function getTotalCombinations(): int
    {
        return $this->totalCombinations;
    }

    public function current(): string
    {
        $password = '';
        $charsetIndex = 0;

        foreach ($this->positions as $pos) {
            if ($pos['type'] === 'literal') {
                $password .= $pos['char'];
            } else {
                $password .= $pos['chars'][$this->currentIndices[$charsetIndex]];
                $charsetIndex++;
            }
        }

        return $password;
    }

    public function key(): int
    {
        return $this->currentPosition;
    }

    public function next(): void
    {
        $this->currentPosition++;
        
        // Increment indices like a counter (from right to left)
        for ($i = count($this->currentIndices) - 1; $i >= 0; $i--) {
            // Find the charset length for this index
            $charsetIdx = 0;
            $maxLen = 0;
            foreach ($this->positions as $pos) {
                if ($pos['type'] === 'charset') {
                    if ($charsetIdx === $i) {
                        $maxLen = strlen($pos['chars']);
                        break;
                    }
                    $charsetIdx++;
                }
            }

            if ($this->currentIndices[$i] < $maxLen - 1) {
                $this->currentIndices[$i]++;
                return;
            } else {
                $this->currentIndices[$i] = 0;
            }
        }

        $this->finished = true;
    }

    public function rewind(): void
    {
        $this->currentPosition = 0;
        $this->finished = false;
        $this->currentIndices = array_fill(0, count($this->currentIndices), 0);
    }

    public function valid(): bool
    {
        return !$this->finished;
    }
}
