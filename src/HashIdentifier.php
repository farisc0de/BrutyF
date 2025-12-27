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
}
