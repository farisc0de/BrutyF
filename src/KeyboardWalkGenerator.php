<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Generate passwords based on keyboard walk patterns
 * e.g., qwerty, asdfgh, zxcvbn, 1qaz2wsx
 */
class KeyboardWalkGenerator implements \Iterator
{
    private array $keyboards = [];
    private int $minLength;
    private int $maxLength;
    private array $patterns = [];
    private int $currentIndex = 0;
    private bool $includeShifted;
    
    public function __construct(int $minLength = 4, int $maxLength = 12, bool $includeShifted = true)
    {
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->includeShifted = $includeShifted;
        $this->initKeyboards();
        $this->generatePatterns();
    }
    
    private function initKeyboards(): void
    {
        // QWERTY keyboard layout
        $this->keyboards['qwerty'] = [
            ['`', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '-', '='],
            ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p', '[', ']', '\\'],
            ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', ';', "'"],
            ['z', 'x', 'c', 'v', 'b', 'n', 'm', ',', '.', '/'],
        ];
        
        // Shifted QWERTY
        $this->keyboards['qwerty_shifted'] = [
            ['~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '+'],
            ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P', '{', '}', '|'],
            ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', ':', '"'],
            ['Z', 'X', 'C', 'V', 'B', 'N', 'M', '<', '>', '?'],
        ];
        
        // Numpad
        $this->keyboards['numpad'] = [
            ['7', '8', '9'],
            ['4', '5', '6'],
            ['1', '2', '3'],
            ['0'],
        ];
    }
    
    private function generatePatterns(): void
    {
        $this->patterns = [];
        
        // Horizontal walks (rows)
        foreach ($this->keyboards['qwerty'] as $row) {
            $this->addRowPatterns($row);
        }
        
        if ($this->includeShifted) {
            foreach ($this->keyboards['qwerty_shifted'] as $row) {
                $this->addRowPatterns($row);
            }
        }
        
        // Vertical walks (columns)
        $this->addColumnPatterns($this->keyboards['qwerty']);
        
        // Diagonal walks
        $this->addDiagonalPatterns($this->keyboards['qwerty']);
        
        // Common keyboard patterns
        $this->addCommonPatterns();
        
        // Numpad patterns
        $this->addNumpadPatterns();
        
        // Filter by length
        $this->patterns = array_filter($this->patterns, function($p) {
            $len = strlen($p);
            return $len >= $this->minLength && $len <= $this->maxLength;
        });
        
        // Remove duplicates and sort
        $this->patterns = array_unique($this->patterns);
        sort($this->patterns);
    }
    
    private function addRowPatterns(array $row): void
    {
        $rowStr = implode('', $row);
        
        // Forward walks of various lengths
        for ($start = 0; $start < count($row); $start++) {
            for ($len = $this->minLength; $len <= min($this->maxLength, count($row) - $start); $len++) {
                $this->patterns[] = substr($rowStr, $start, $len);
            }
        }
        
        // Reverse walks
        $reversed = strrev($rowStr);
        for ($start = 0; $start < strlen($reversed); $start++) {
            for ($len = $this->minLength; $len <= min($this->maxLength, strlen($reversed) - $start); $len++) {
                $this->patterns[] = substr($reversed, $start, $len);
            }
        }
    }
    
    private function addColumnPatterns(array $keyboard): void
    {
        $maxCols = max(array_map('count', $keyboard));
        
        for ($col = 0; $col < $maxCols; $col++) {
            $column = '';
            foreach ($keyboard as $row) {
                if (isset($row[$col])) {
                    $column .= $row[$col];
                }
            }
            
            if (strlen($column) >= $this->minLength) {
                $this->patterns[] = $column;
                $this->patterns[] = strrev($column);
            }
        }
    }
    
    private function addDiagonalPatterns(array $keyboard): void
    {
        // Down-right diagonals
        for ($startRow = 0; $startRow < count($keyboard); $startRow++) {
            for ($startCol = 0; $startCol < count($keyboard[$startRow]); $startCol++) {
                $pattern = '';
                $row = $startRow;
                $col = $startCol;
                
                while ($row < count($keyboard) && isset($keyboard[$row][$col])) {
                    $pattern .= $keyboard[$row][$col];
                    $row++;
                    $col++;
                }
                
                if (strlen($pattern) >= $this->minLength) {
                    $this->patterns[] = $pattern;
                    $this->patterns[] = strrev($pattern);
                }
            }
        }
        
        // Down-left diagonals
        for ($startRow = 0; $startRow < count($keyboard); $startRow++) {
            for ($startCol = count($keyboard[$startRow]) - 1; $startCol >= 0; $startCol--) {
                $pattern = '';
                $row = $startRow;
                $col = $startCol;
                
                while ($row < count($keyboard) && $col >= 0 && isset($keyboard[$row][$col])) {
                    $pattern .= $keyboard[$row][$col];
                    $row++;
                    $col--;
                }
                
                if (strlen($pattern) >= $this->minLength) {
                    $this->patterns[] = $pattern;
                    $this->patterns[] = strrev($pattern);
                }
            }
        }
    }
    
    private function addCommonPatterns(): void
    {
        $common = [
            // Classic keyboard walks
            'qwerty', 'qwert', 'qwer', 'asdf', 'asdfgh', 'zxcv', 'zxcvbn',
            'qwertyuiop', 'asdfghjkl', 'zxcvbnm',
            
            // Number rows
            '123456', '1234567890', '123456789', '12345678', '1234567', '12345',
            '0987654321', '987654321', '87654321', '7654321', '654321', '54321',
            
            // Mixed patterns
            '1qaz', '2wsx', '3edc', '4rfv', '5tgb', '6yhn', '7ujm', '8ik,', '9ol.', '0p;/',
            '1qaz2wsx', '2wsx3edc', '1qaz2wsx3edc', '1qaz2wsx3edc4rfv',
            'qazwsx', 'qazwsxedc', 'qazwsxedcrfv',
            'zaq1', 'xsw2', 'cde3', 'vfr4', 'bgt5', 'nhy6', 'mju7', ',ki8', '.lo9', '/;p0',
            'zaq12wsx', 'zaq1xsw2',
            
            // Alternating patterns
            'qw12', 'qwer1234', 'asdf1234', 'zxcv1234',
            '1q2w3e4r', '1q2w3e4r5t', '1q2w3e4r5t6y',
            'q1w2e3r4', 'a1s2d3f4',
            
            // Corner patterns
            '`1qa', 'p0-=', '[];\'', 'zaq!',
            
            // Common variations
            'qwerty123', 'asdf1234', 'zxcvbnm123',
            '123qwe', '123asd', '123zxc',
            'qwe123', 'asd123', 'zxc123',
            
            // Repeated patterns
            'qwerqwer', 'asdfasdf', '12341234', '123123',
        ];
        
        foreach ($common as $pattern) {
            if (strlen($pattern) >= $this->minLength && strlen($pattern) <= $this->maxLength) {
                $this->patterns[] = $pattern;
                $this->patterns[] = strtoupper($pattern);
                $this->patterns[] = ucfirst($pattern);
            }
        }
    }
    
    private function addNumpadPatterns(): void
    {
        $numpad = [
            // Rows
            '789', '456', '123', '147', '258', '369',
            '7894561230', '1234567890', '0987654321',
            
            // Columns
            '741', '852', '963', '147852', '258369', '147258369',
            
            // Diagonals
            '159', '753', '951', '357',
            
            // Common patterns
            '1379', '7913', '2468', '8642',
            '123456', '654321', '789456123', '321654987',
            
            // Box patterns
            '78945612', '12345678', '14789632', '32147896',
        ];
        
        foreach ($numpad as $pattern) {
            if (strlen($pattern) >= $this->minLength && strlen($pattern) <= $this->maxLength) {
                $this->patterns[] = $pattern;
            }
        }
    }
    
    public function getPatterns(): array
    {
        return $this->patterns;
    }
    
    public function getCount(): int
    {
        return count($this->patterns);
    }
    
    // Iterator implementation
    public function current(): string
    {
        return $this->patterns[$this->currentIndex];
    }
    
    public function key(): int
    {
        return $this->currentIndex;
    }
    
    public function next(): void
    {
        $this->currentIndex++;
    }
    
    public function rewind(): void
    {
        $this->currentIndex = 0;
    }
    
    public function valid(): bool
    {
        return isset($this->patterns[$this->currentIndex]);
    }
}
