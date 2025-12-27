<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Generate hashes from passwords for testing
 */
class HashGenerator
{
    public static function generate(string $password, string $type, ?string $salt = null): ?string
    {
        return match ($type) {
            'md5' => md5($password),
            'sha1' => sha1($password),
            'sha256' => hash('sha256', $password),
            'sha512' => hash('sha512', $password),
            'bcrypt' => password_hash($password, PASSWORD_BCRYPT),
            'ntlm' => self::ntlmHash($password),
            'mysql' => self::mysqlOldPassword($password),
            'mysql5' => self::mysql5Password($password),
            'md5_salted' => $salt ? md5($password . $salt) . ':' . $salt : null,
            'sha256_salted' => $salt ? hash('sha256', $password . $salt) . ':' . $salt : null,
            'sha512_salted' => $salt ? hash('sha512', $password . $salt) . ':' . $salt : null,
            default => null,
        };
    }
    
    public static function generateAll(string $password): array
    {
        $hashes = [];
        $types = ['md5', 'sha1', 'sha256', 'sha512', 'bcrypt', 'ntlm', 'mysql', 'mysql5'];
        
        foreach ($types as $type) {
            $hashes[$type] = self::generate($password, $type);
        }
        
        return $hashes;
    }
    
    public static function generateFromFile(string $file, string $type, ?string $salt = null): array
    {
        $results = [];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            return $results;
        }
        
        while (($line = fgets($handle)) !== false) {
            $password = trim($line);
            if (empty($password)) continue;
            
            $hash = self::generate($password, $type, $salt);
            if ($hash) {
                $results[] = [
                    'password' => $password,
                    'hash' => $hash,
                    'type' => $type,
                ];
            }
        }
        
        fclose($handle);
        return $results;
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
    
    public static function getSupportedTypes(): array
    {
        return ['md5', 'sha1', 'sha256', 'sha512', 'bcrypt', 'ntlm', 'mysql', 'mysql5'];
    }
}
