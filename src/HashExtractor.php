<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Extract hashes from common file formats
 */
class HashExtractor
{
    public const FORMAT_SHADOW = 'shadow';
    public const FORMAT_PASSWD = 'passwd';
    public const FORMAT_HTPASSWD = 'htpasswd';
    public const FORMAT_PWDUMP = 'pwdump';
    public const FORMAT_SAM = 'sam';
    public const FORMAT_HASHCAT = 'hashcat';
    public const FORMAT_JOHN = 'john';
    public const FORMAT_CSV = 'csv';
    public const FORMAT_AUTO = 'auto';
    
    private array $extracted = [];
    private array $errors = [];
    
    public function extractFromFile(string $file, string $format = self::FORMAT_AUTO): array
    {
        if (!file_exists($file)) {
            $this->errors[] = "File not found: {$file}";
            return [];
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            $this->errors[] = "Failed to read file: {$file}";
            return [];
        }
        
        return $this->extractFromString($content, $format);
    }
    
    public function extractFromString(string $content, string $format = self::FORMAT_AUTO): array
    {
        $this->extracted = [];
        $lines = explode("\n", $content);
        
        if ($format === self::FORMAT_AUTO) {
            $format = $this->detectFormat($lines);
        }
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            $result = match ($format) {
                self::FORMAT_SHADOW => $this->parseShadowLine($line),
                self::FORMAT_PASSWD => $this->parsePasswdLine($line),
                self::FORMAT_HTPASSWD => $this->parseHtpasswdLine($line),
                self::FORMAT_PWDUMP => $this->parsePwdumpLine($line),
                self::FORMAT_SAM => $this->parseSamLine($line),
                self::FORMAT_HASHCAT => $this->parseHashcatLine($line),
                self::FORMAT_JOHN => $this->parseJohnLine($line),
                self::FORMAT_CSV => $this->parseCsvLine($line),
                default => $this->parseGenericLine($line),
            };
            
            if ($result !== null) {
                $this->extracted[] = $result;
            }
        }
        
        return $this->extracted;
    }
    
    private function detectFormat(array $lines): string
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            // Shadow format: user:$type$salt$hash:...
            if (preg_match('/^[^:]+:\$[0-9a-z]+\$[^:]+:[0-9]*:[0-9]*:[0-9]*:[0-9]*:[0-9]*:?$/', $line)) {
                return self::FORMAT_SHADOW;
            }
            
            // Passwd format: user:x:uid:gid:...
            if (preg_match('/^[^:]+:x:[0-9]+:[0-9]+:/', $line)) {
                return self::FORMAT_PASSWD;
            }
            
            // htpasswd format: user:hash or user:{SHA}base64
            if (preg_match('/^[^:]+:(\{[A-Z0-9]+\})?[A-Za-z0-9+\/=.$]+$/', $line)) {
                return self::FORMAT_HTPASSWD;
            }
            
            // pwdump format: user:rid:lmhash:nthash:::
            if (preg_match('/^[^:]+:[0-9]+:[a-f0-9]{32}:[a-f0-9]{32}:::/i', $line)) {
                return self::FORMAT_PWDUMP;
            }
            
            // CSV with header
            if (str_contains(strtolower($line), 'hash') && str_contains($line, ',')) {
                return self::FORMAT_CSV;
            }
        }
        
        return self::FORMAT_HASHCAT;
    }
    
    private function parseShadowLine(string $line): ?array
    {
        $parts = explode(':', $line);
        if (count($parts) < 2) {
            return null;
        }
        
        $username = $parts[0];
        $hash = $parts[1];
        
        // Skip locked/disabled accounts
        if (in_array($hash, ['*', '!', '!!', 'x', ''])) {
            return null;
        }
        
        // Parse hash format ($type$salt$hash)
        if (preg_match('/^\$([0-9a-z]+)\$([^$]+)\$(.+)$/i', $hash, $matches)) {
            $hashType = match ($matches[1]) {
                '1' => 'md5crypt',
                '5' => 'sha256crypt',
                '6' => 'sha512crypt',
                '2a', '2b', '2y' => 'bcrypt',
                'y' => 'yescrypt',
                default => $matches[1],
            };
            
            return [
                'username' => $username,
                'hash' => $hash,
                'hash_type' => $hashType,
                'salt' => $matches[2],
                'format' => 'shadow',
            ];
        }
        
        return [
            'username' => $username,
            'hash' => $hash,
            'hash_type' => 'unknown',
            'format' => 'shadow',
        ];
    }
    
    private function parsePasswdLine(string $line): ?array
    {
        $parts = explode(':', $line);
        if (count($parts) < 7) {
            return null;
        }
        
        $username = $parts[0];
        $hash = $parts[1];
        
        // Modern systems use 'x' to indicate hash is in shadow file
        if ($hash === 'x' || $hash === '*') {
            return null;
        }
        
        return [
            'username' => $username,
            'hash' => $hash,
            'hash_type' => 'descrypt',
            'format' => 'passwd',
        ];
    }
    
    private function parseHtpasswdLine(string $line): ?array
    {
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            return null;
        }
        
        $username = $parts[0];
        $hash = $parts[1];
        
        // Detect hash type
        $hashType = 'unknown';
        if (str_starts_with($hash, '{SHA}')) {
            $hashType = 'sha1_base64';
            $hash = substr($hash, 5);
        } elseif (str_starts_with($hash, '$apr1$')) {
            $hashType = 'apr1_md5';
        } elseif (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$')) {
            $hashType = 'bcrypt';
        } elseif (strlen($hash) === 13) {
            $hashType = 'descrypt';
        }
        
        return [
            'username' => $username,
            'hash' => $hash,
            'hash_type' => $hashType,
            'format' => 'htpasswd',
        ];
    }
    
    private function parsePwdumpLine(string $line): ?array
    {
        // Format: username:rid:lmhash:nthash:::
        $parts = explode(':', $line);
        if (count($parts) < 4) {
            return null;
        }
        
        $username = $parts[0];
        $lmHash = $parts[2];
        $ntHash = $parts[3];
        
        $results = [];
        
        // Skip empty LM hashes (aad3b435b51404eeaad3b435b51404ee)
        if ($lmHash !== 'aad3b435b51404eeaad3b435b51404ee' && strlen($lmHash) === 32) {
            $results[] = [
                'username' => $username,
                'hash' => $lmHash,
                'hash_type' => 'lm',
                'format' => 'pwdump',
            ];
        }
        
        if (strlen($ntHash) === 32 && $ntHash !== '31d6cfe0d16ae931b73c59d7e0c089c0') {
            return [
                'username' => $username,
                'hash' => $ntHash,
                'hash_type' => 'ntlm',
                'format' => 'pwdump',
            ];
        }
        
        return null;
    }
    
    private function parseSamLine(string $line): ?array
    {
        // SAM format is similar to pwdump
        return $this->parsePwdumpLine($line);
    }
    
    private function parseHashcatLine(string $line): ?array
    {
        // Hashcat format can be just hash or user:hash
        if (str_contains($line, ':')) {
            $parts = explode(':', $line, 2);
            return [
                'username' => $parts[0],
                'hash' => $parts[1],
                'hash_type' => HashIdentifier::identify($parts[1]),
                'format' => 'hashcat',
            ];
        }
        
        return [
            'hash' => $line,
            'hash_type' => HashIdentifier::identify($line),
            'format' => 'hashcat',
        ];
    }
    
    private function parseJohnLine(string $line): ?array
    {
        // John format: user:hash or user:$format$params$hash
        return $this->parseHashcatLine($line);
    }
    
    private function parseCsvLine(string $line): ?array
    {
        $parts = str_getcsv($line);
        if (count($parts) < 1) {
            return null;
        }
        
        // Try to find hash-like values
        foreach ($parts as $i => $value) {
            $value = trim($value);
            $type = HashIdentifier::identify($value);
            if ($type !== HashIdentifier::TYPE_UNKNOWN) {
                return [
                    'hash' => $value,
                    'hash_type' => $type,
                    'format' => 'csv',
                    'column' => $i,
                ];
            }
        }
        
        return null;
    }
    
    private function parseGenericLine(string $line): ?array
    {
        // Try to extract any hash-like value
        $type = HashIdentifier::identify($line);
        if ($type !== HashIdentifier::TYPE_UNKNOWN) {
            return [
                'hash' => $line,
                'hash_type' => $type,
                'format' => 'generic',
            ];
        }
        
        return null;
    }
    
    public function getExtracted(): array
    {
        return $this->extracted;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getHashesOnly(): array
    {
        return array_map(fn($e) => $e['hash'], $this->extracted);
    }
    
    public function getFormattedOutput(): array
    {
        $output = [];
        foreach ($this->extracted as $entry) {
            if (isset($entry['username'])) {
                $output[] = "{$entry['username']}:{$entry['hash']}";
            } else {
                $output[] = $entry['hash'];
            }
        }
        return $output;
    }
    
    public static function getSupportedFormats(): array
    {
        return [
            self::FORMAT_AUTO => 'Auto-detect format',
            self::FORMAT_SHADOW => 'Linux /etc/shadow',
            self::FORMAT_PASSWD => 'Unix /etc/passwd (old style)',
            self::FORMAT_HTPASSWD => 'Apache .htpasswd',
            self::FORMAT_PWDUMP => 'Windows pwdump output',
            self::FORMAT_SAM => 'Windows SAM dump',
            self::FORMAT_HASHCAT => 'Hashcat format (hash or user:hash)',
            self::FORMAT_JOHN => 'John the Ripper format',
            self::FORMAT_CSV => 'CSV with hash column',
        ];
    }
}
