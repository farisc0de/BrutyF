<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Hash type detection and verification class
 */
class HashIdentifier
{
    public const TYPE_BCRYPT = 'bcrypt';
    public const TYPE_MD5 = 'md5';
    public const TYPE_SHA1 = 'sha1';
    public const TYPE_SHA256 = 'sha256';
    public const TYPE_SHA512 = 'sha512';
    public const TYPE_NTLM = 'ntlm';
    public const TYPE_MYSQL = 'mysql';
    public const TYPE_MYSQL5 = 'mysql5';
    public const TYPE_MD5_SALTED = 'md5_salted';
    public const TYPE_SHA256_SALTED = 'sha256_salted';
    public const TYPE_SHA512_SALTED = 'sha512_salted';
    public const TYPE_UNKNOWN = 'unknown';

    private static array $patterns = [
        self::TYPE_BCRYPT => '/^\$2[ayb]\$[0-9]{2}\$[A-Za-z0-9\.\/]{53}$/',
        self::TYPE_MD5 => '/^[a-f0-9]{32}$/i',
        self::TYPE_SHA1 => '/^[a-f0-9]{40}$/i',
        self::TYPE_SHA256 => '/^[a-f0-9]{64}$/i',
        self::TYPE_SHA512 => '/^[a-f0-9]{128}$/i',
        self::TYPE_NTLM => '/^[a-f0-9]{32}$/i',
        self::TYPE_MYSQL => '/^[a-f0-9]{16}$/i',
        self::TYPE_MYSQL5 => '/^\*[A-F0-9]{40}$/i',
    ];

    public static function identify(string $hash): string
    {
        $hash = trim($hash);
        
        // Check for salted formats (hash:salt)
        if (strpos($hash, ':') !== false) {
            [$hashPart, $salt] = explode(':', $hash, 2);
            $baseType = self::identifyBase($hashPart);
            if ($baseType === self::TYPE_MD5) return self::TYPE_MD5_SALTED;
            if ($baseType === self::TYPE_SHA256) return self::TYPE_SHA256_SALTED;
            if ($baseType === self::TYPE_SHA512) return self::TYPE_SHA512_SALTED;
        }

        // Bcrypt first (most specific)
        if (preg_match(self::$patterns[self::TYPE_BCRYPT], $hash)) {
            return self::TYPE_BCRYPT;
        }

        // MySQL5 (starts with *)
        if (preg_match(self::$patterns[self::TYPE_MYSQL5], $hash)) {
            return self::TYPE_MYSQL5;
        }

        return self::identifyBase($hash);
    }

    private static function identifyBase(string $hash): string
    {
        $length = strlen($hash);

        return match ($length) {
            16 => self::TYPE_MYSQL,
            32 => self::TYPE_MD5,
            40 => self::TYPE_SHA1,
            64 => self::TYPE_SHA256,
            128 => self::TYPE_SHA512,
            default => self::TYPE_UNKNOWN,
        };
    }

    public static function verify(string $password, string $hash, string $type, ?string $salt = null): bool
    {
        return match ($type) {
            self::TYPE_BCRYPT => password_verify($password, $hash),
            self::TYPE_MD5 => hash('md5', $password) === strtolower($hash),
            self::TYPE_SHA1 => hash('sha1', $password) === strtolower($hash),
            self::TYPE_SHA256 => hash('sha256', $password) === strtolower($hash),
            self::TYPE_SHA512 => hash('sha512', $password) === strtolower($hash),
            self::TYPE_NTLM => self::ntlmHash($password) === strtoupper($hash),
            self::TYPE_MYSQL => self::mysqlOldPassword($password) === strtolower($hash),
            self::TYPE_MYSQL5 => self::mysql5Password($password) === strtoupper($hash),
            self::TYPE_MD5_SALTED => hash('md5', $password . $salt) === strtolower($hash) || 
                                     hash('md5', $salt . $password) === strtolower($hash),
            self::TYPE_SHA256_SALTED => hash('sha256', $password . $salt) === strtolower($hash) || 
                                        hash('sha256', $salt . $password) === strtolower($hash),
            self::TYPE_SHA512_SALTED => hash('sha512', $password . $salt) === strtolower($hash) || 
                                        hash('sha512', $salt . $password) === strtolower($hash),
            default => false,
        };
    }

    private static function ntlmHash(string $password): string
    {
        $password = iconv('UTF-8', 'UTF-16LE', $password);
        return strtoupper(hash('md4', $password));
    }

    private static function mysqlOldPassword(string $password): string
    {
        $nr = 1345345333;
        $add = 7;
        $nr2 = 0x12345671;
        $tmp = 0;

        for ($i = 0; $i < strlen($password); $i++) {
            $c = ord($password[$i]);
            if ($c === ord(' ') || $c === ord("\t")) continue;
            $tmp = $c;
            $nr ^= ((($nr & 63) + $add) * $tmp) + (($nr << 8) & 0xFFFFFFFF);
            $nr &= 0x7FFFFFFF;
            $nr2 += (($nr2 << 8) & 0xFFFFFFFF) ^ $nr;
            $nr2 &= 0x7FFFFFFF;
            $add += $tmp;
        }

        return sprintf('%08x%08x', $nr & 0x7FFFFFFF, $nr2 & 0x7FFFFFFF);
    }

    private static function mysql5Password(string $password): string
    {
        return '*' . strtoupper(sha1(sha1($password, true)));
    }

    public static function getAllTypes(): array
    {
        return [
            self::TYPE_BCRYPT,
            self::TYPE_MD5,
            self::TYPE_SHA1,
            self::TYPE_SHA256,
            self::TYPE_SHA512,
            self::TYPE_NTLM,
            self::TYPE_MYSQL,
            self::TYPE_MYSQL5,
        ];
    }
    
    public static function getTypeInfo(string $type): array
    {
        return match ($type) {
            self::TYPE_BCRYPT => [
                'name' => 'bcrypt',
                'description' => 'Blowfish-based adaptive hash',
                'length' => 60,
                'example' => '$2y$10$abcdefghijklmnopqrstuO...',
                'security' => 'Strong (slow, salted)',
            ],
            self::TYPE_MD5 => [
                'name' => 'MD5',
                'description' => 'Message Digest 5',
                'length' => 32,
                'example' => '5f4dcc3b5aa765d61d8327deb882cf99',
                'security' => 'Weak (fast, unsalted)',
            ],
            self::TYPE_SHA1 => [
                'name' => 'SHA-1',
                'description' => 'Secure Hash Algorithm 1',
                'length' => 40,
                'example' => '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8',
                'security' => 'Weak (fast, unsalted)',
            ],
            self::TYPE_SHA256 => [
                'name' => 'SHA-256',
                'description' => 'Secure Hash Algorithm 256-bit',
                'length' => 64,
                'example' => '5e884898da28047d9169e1850...',
                'security' => 'Medium (fast, unsalted)',
            ],
            self::TYPE_SHA512 => [
                'name' => 'SHA-512',
                'description' => 'Secure Hash Algorithm 512-bit',
                'length' => 128,
                'example' => 'b109f3bbbc244eb8...',
                'security' => 'Medium (fast, unsalted)',
            ],
            self::TYPE_NTLM => [
                'name' => 'NTLM',
                'description' => 'Windows NT LAN Manager',
                'length' => 32,
                'example' => '32ED87BDB5FDC5E9CBA88547376818D4',
                'security' => 'Weak (fast, unsalted)',
            ],
            self::TYPE_MYSQL => [
                'name' => 'MySQL (old)',
                'description' => 'MySQL pre-4.1 password hash',
                'length' => 16,
                'example' => '565491d704013245',
                'security' => 'Very Weak',
            ],
            self::TYPE_MYSQL5 => [
                'name' => 'MySQL5',
                'description' => 'MySQL 4.1+ password hash',
                'length' => 41,
                'example' => '*2470C0C06DEE42FD1618BB99...',
                'security' => 'Weak (double SHA1)',
            ],
            self::TYPE_MD5_SALTED => [
                'name' => 'MD5 (salted)',
                'description' => 'MD5 with salt appended/prepended',
                'length' => 32,
                'example' => '5f4dcc3b5aa765d61d8327deb882cf99:salt',
                'security' => 'Medium',
            ],
            self::TYPE_SHA256_SALTED => [
                'name' => 'SHA-256 (salted)',
                'description' => 'SHA-256 with salt',
                'length' => 64,
                'example' => '5e884898da28047d9169e1850...:salt',
                'security' => 'Medium',
            ],
            self::TYPE_SHA512_SALTED => [
                'name' => 'SHA-512 (salted)',
                'description' => 'SHA-512 with salt',
                'length' => 128,
                'example' => 'b109f3bbbc244eb8...:salt',
                'security' => 'Medium',
            ],
            default => [
                'name' => 'Unknown',
                'description' => 'Unrecognized hash format',
                'length' => 0,
                'example' => '',
                'security' => 'Unknown',
            ],
        };
    }
    
    public static function identifyWithConfidence(string $hash): array
    {
        $hash = trim($hash);
        $possibleTypes = [];
        
        // Check bcrypt first
        if (preg_match(self::$patterns[self::TYPE_BCRYPT], $hash)) {
            return [[
                'type' => self::TYPE_BCRYPT,
                'confidence' => 100,
                'info' => self::getTypeInfo(self::TYPE_BCRYPT),
            ]];
        }
        
        // Check MySQL5
        if (preg_match(self::$patterns[self::TYPE_MYSQL5], $hash)) {
            return [[
                'type' => self::TYPE_MYSQL5,
                'confidence' => 100,
                'info' => self::getTypeInfo(self::TYPE_MYSQL5),
            ]];
        }
        
        // Check salted formats
        if (strpos($hash, ':') !== false) {
            [$hashPart, $salt] = explode(':', $hash, 2);
            $len = strlen($hashPart);
            
            if ($len === 32 && preg_match('/^[a-f0-9]+$/i', $hashPart)) {
                $possibleTypes[] = ['type' => self::TYPE_MD5_SALTED, 'confidence' => 90];
            }
            if ($len === 64 && preg_match('/^[a-f0-9]+$/i', $hashPart)) {
                $possibleTypes[] = ['type' => self::TYPE_SHA256_SALTED, 'confidence' => 90];
            }
            if ($len === 128 && preg_match('/^[a-f0-9]+$/i', $hashPart)) {
                $possibleTypes[] = ['type' => self::TYPE_SHA512_SALTED, 'confidence' => 90];
            }
        }
        
        $length = strlen($hash);
        
        // Length-based detection with multiple possibilities
        if ($length === 16 && preg_match('/^[a-f0-9]+$/i', $hash)) {
            $possibleTypes[] = ['type' => self::TYPE_MYSQL, 'confidence' => 80];
        }
        
        if ($length === 32 && preg_match('/^[a-f0-9]+$/i', $hash)) {
            // Could be MD5 or NTLM - both are 32 hex chars
            $possibleTypes[] = ['type' => self::TYPE_MD5, 'confidence' => 60];
            $possibleTypes[] = ['type' => self::TYPE_NTLM, 'confidence' => 40];
        }
        
        if ($length === 40 && preg_match('/^[a-f0-9]+$/i', $hash)) {
            $possibleTypes[] = ['type' => self::TYPE_SHA1, 'confidence' => 90];
        }
        
        if ($length === 64 && preg_match('/^[a-f0-9]+$/i', $hash)) {
            $possibleTypes[] = ['type' => self::TYPE_SHA256, 'confidence' => 90];
        }
        
        if ($length === 128 && preg_match('/^[a-f0-9]+$/i', $hash)) {
            $possibleTypes[] = ['type' => self::TYPE_SHA512, 'confidence' => 90];
        }
        
        if (empty($possibleTypes)) {
            return [[
                'type' => self::TYPE_UNKNOWN,
                'confidence' => 0,
                'info' => self::getTypeInfo(self::TYPE_UNKNOWN),
            ]];
        }
        
        // Sort by confidence
        usort($possibleTypes, fn($a, $b) => $b['confidence'] - $a['confidence']);
        
        // Add info to each
        return array_map(fn($t) => [
            'type' => $t['type'],
            'confidence' => $t['confidence'],
            'info' => self::getTypeInfo($t['type']),
        ], $possibleTypes);
    }
}
