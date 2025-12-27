<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Generate custom wordlists from base words with various transformations
 */
class DictionaryGenerator
{
    private array $baseWords = [];
    private array $rules = [];
    private array $appendList = [];
    private array $prependList = [];
    private bool $includeLeet = true;
    private bool $includeYears = true;
    private bool $includeNumbers = true;
    private bool $includeCasing = true;
    private int $yearStart = 1970;
    private int $yearEnd = 2030;
    
    public function __construct(array $baseWords = [])
    {
        $this->baseWords = $baseWords;
        $this->initDefaults();
    }
    
    private function initDefaults(): void
    {
        // Common number suffixes
        $this->appendList = [
            '', '1', '2', '3', '12', '123', '1234', '12345', '123456',
            '!', '!!', '@', '#', '$', '*',
            '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
            '11', '22', '33', '44', '55', '66', '77', '88', '99', '00',
            '69', '420', '007', '666', '777', '888', '999',
            '!1', '@2', '#3', '$4', '%5',
            '_1', '_123', '.1', '.123',
        ];
        
        // Common prefixes
        $this->prependList = [
            '', '1', '123', '@', '#', '$', 'the', 'my', 'i', 'a',
        ];
    }
    
    public function addBaseWords(array $words): self
    {
        $this->baseWords = array_merge($this->baseWords, $words);
        return $this;
    }
    
    public function addBaseWordsFromFile(string $file): self
    {
        if (!file_exists($file)) {
            return $this;
        }
        
        $words = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($words) {
            $this->baseWords = array_merge($this->baseWords, $words);
        }
        return $this;
    }
    
    public function setAppendList(array $list): self
    {
        $this->appendList = $list;
        return $this;
    }
    
    public function setPrependList(array $list): self
    {
        $this->prependList = $list;
        return $this;
    }
    
    public function setYearRange(int $start, int $end): self
    {
        $this->yearStart = $start;
        $this->yearEnd = $end;
        return $this;
    }
    
    public function includeLeet(bool $include): self
    {
        $this->includeLeet = $include;
        return $this;
    }
    
    public function includeYears(bool $include): self
    {
        $this->includeYears = $include;
        return $this;
    }
    
    public function includeNumbers(bool $include): self
    {
        $this->includeNumbers = $include;
        return $this;
    }
    
    public function includeCasing(bool $include): self
    {
        $this->includeCasing = $include;
        return $this;
    }
    
    public function generate(): array
    {
        $passwords = [];
        
        foreach ($this->baseWords as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            
            // Get all variations of the base word
            $variations = $this->getWordVariations($word);
            
            foreach ($variations as $variant) {
                // Add with prepends and appends
                foreach ($this->prependList as $prefix) {
                    foreach ($this->appendList as $suffix) {
                        $passwords[] = $prefix . $variant . $suffix;
                    }
                }
                
                // Add with years
                if ($this->includeYears) {
                    for ($year = $this->yearStart; $year <= $this->yearEnd; $year++) {
                        $passwords[] = $variant . $year;
                        $passwords[] = $variant . substr((string)$year, -2);
                    }
                }
            }
        }
        
        // Remove duplicates and empty strings
        $passwords = array_unique(array_filter($passwords));
        
        return array_values($passwords);
    }
    
    private function getWordVariations(string $word): array
    {
        $variations = [$word];
        
        // Casing variations
        if ($this->includeCasing) {
            $variations[] = strtolower($word);
            $variations[] = strtoupper($word);
            $variations[] = ucfirst(strtolower($word));
            $variations[] = lcfirst($word);
            $variations[] = $this->toggleCase($word);
        }
        
        // Leet speak variations
        if ($this->includeLeet) {
            $leetVariations = $this->getLeetVariations($word);
            $variations = array_merge($variations, $leetVariations);
        }
        
        // Number substitutions
        if ($this->includeNumbers) {
            $variations[] = $this->replaceVowelsWithNumbers($word);
        }
        
        return array_unique($variations);
    }
    
    private function toggleCase(string $word): string
    {
        $result = '';
        for ($i = 0; $i < strlen($word); $i++) {
            $char = $word[$i];
            $result .= $i % 2 === 0 ? strtoupper($char) : strtolower($char);
        }
        return $result;
    }
    
    private function getLeetVariations(string $word): array
    {
        $leetMap = [
            'a' => ['4', '@'],
            'e' => ['3'],
            'i' => ['1', '!'],
            'o' => ['0'],
            's' => ['5', '$'],
            't' => ['7'],
            'l' => ['1'],
            'b' => ['8'],
            'g' => ['9'],
        ];
        
        $variations = [];
        $word = strtolower($word);
        
        // Simple leet (replace all)
        $leet = $word;
        foreach ($leetMap as $char => $replacements) {
            $leet = str_replace($char, $replacements[0], $leet);
        }
        $variations[] = $leet;
        
        // Partial leet (replace some)
        foreach ($leetMap as $char => $replacements) {
            foreach ($replacements as $replacement) {
                $variations[] = str_replace($char, $replacement, $word);
            }
        }
        
        return $variations;
    }
    
    private function replaceVowelsWithNumbers(string $word): string
    {
        $map = ['a' => '4', 'e' => '3', 'i' => '1', 'o' => '0', 'u' => 'v'];
        return str_replace(array_keys($map), array_values($map), strtolower($word));
    }
    
    public function generateToFile(string $outputFile): int
    {
        $passwords = $this->generate();
        $content = implode("\n", $passwords) . "\n";
        file_put_contents($outputFile, $content);
        return count($passwords);
    }
    
    public function getEstimatedCount(): int
    {
        $baseCount = count($this->baseWords);
        $variationMultiplier = 1;
        
        if ($this->includeCasing) $variationMultiplier += 5;
        if ($this->includeLeet) $variationMultiplier += 10;
        if ($this->includeNumbers) $variationMultiplier += 1;
        
        $appendCount = count($this->appendList);
        $prependCount = count($this->prependList);
        $yearCount = $this->includeYears ? ($this->yearEnd - $this->yearStart + 1) * 2 : 0;
        
        return $baseCount * $variationMultiplier * ($appendCount * $prependCount + $yearCount);
    }
    
    public static function fromTheme(string $theme): self
    {
        $generator = new self();
        
        $themes = [
            'sports' => [
                'football', 'soccer', 'baseball', 'basketball', 'hockey', 'tennis',
                'golf', 'boxing', 'wrestling', 'swimming', 'running', 'cycling',
                'champion', 'winner', 'player', 'team', 'coach', 'game', 'score',
            ],
            'animals' => [
                'dog', 'cat', 'bird', 'fish', 'lion', 'tiger', 'bear', 'wolf',
                'eagle', 'shark', 'dragon', 'horse', 'monkey', 'snake', 'rabbit',
                'puppy', 'kitty', 'buddy', 'max', 'bella', 'charlie', 'lucy',
            ],
            'colors' => [
                'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink',
                'black', 'white', 'silver', 'gold', 'bronze', 'rainbow',
            ],
            'tech' => [
                'computer', 'laptop', 'phone', 'tablet', 'internet', 'wifi',
                'password', 'admin', 'root', 'user', 'login', 'access',
                'hacker', 'cyber', 'digital', 'system', 'network', 'server',
            ],
            'love' => [
                'love', 'heart', 'baby', 'honey', 'sweet', 'angel', 'princess',
                'prince', 'king', 'queen', 'forever', 'always', 'together',
                'iloveyou', 'loveyou', 'mylove', 'truelove', 'firstlove',
            ],
            'music' => [
                'music', 'song', 'rock', 'metal', 'jazz', 'blues', 'hiphop',
                'guitar', 'piano', 'drums', 'bass', 'singer', 'band', 'concert',
            ],
            'gaming' => [
                'game', 'gamer', 'player', 'xbox', 'playstation', 'nintendo',
                'mario', 'zelda', 'pokemon', 'minecraft', 'fortnite', 'cod',
                'noob', 'pro', 'elite', 'legend', 'master', 'boss', 'level',
            ],
            'names' => [
                'john', 'mike', 'david', 'james', 'robert', 'william', 'richard',
                'mary', 'jennifer', 'linda', 'elizabeth', 'barbara', 'susan',
                'michael', 'christopher', 'matthew', 'daniel', 'anthony', 'mark',
            ],
        ];
        
        if (isset($themes[$theme])) {
            $generator->addBaseWords($themes[$theme]);
        }
        
        return $generator;
    }
    
    public static function getAvailableThemes(): array
    {
        return ['sports', 'animals', 'colors', 'tech', 'love', 'music', 'gaming', 'names'];
    }
}
