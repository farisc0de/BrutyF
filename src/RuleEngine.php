<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Password mutation rules engine
 */
class RuleEngine
{
    private array $rules = [];

    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    public static function getDefaultRules(): array
    {
        return [
            'none',           // Original password
            'lowercase',      // all lowercase
            'uppercase',      // ALL UPPERCASE
            'capitalize',     // Capitalize first letter
            'reverse',        // esrever
            'leet',          // l33t speak
            'append_123',    // password123
            'append_1',      // password1
            'append_!',      // password!
            'prepend_123',   // 123password
            'duplicate',     // passwordpassword
            'toggle_case',   // pAsSwOrD
        ];
    }

    public function applyRules(string $password): array
    {
        if (empty($this->rules)) {
            return [$password];
        }

        $results = [];
        foreach ($this->rules as $rule) {
            $mutated = $this->applyRule($password, $rule);
            if ($mutated !== null && !in_array($mutated, $results)) {
                $results[] = $mutated;
            }
        }

        return $results;
    }

    private function applyRule(string $password, string $rule): ?string
    {
        return match ($rule) {
            'none' => $password,
            'lowercase' => strtolower($password),
            'uppercase' => strtoupper($password),
            'capitalize' => ucfirst(strtolower($password)),
            'reverse' => strrev($password),
            'leet' => $this->leetSpeak($password),
            'append_123' => $password . '123',
            'append_1' => $password . '1',
            'append_!' => $password . '!',
            'prepend_123' => '123' . $password,
            'duplicate' => $password . $password,
            'toggle_case' => $this->toggleCase($password),
            'append_year' => $password . date('Y'),
            'append_year_short' => $password . date('y'),
            default => $this->parseCustomRule($password, $rule),
        };
    }

    private function leetSpeak(string $password): string
    {
        $leet = [
            'a' => '4', 'A' => '4',
            'e' => '3', 'E' => '3',
            'i' => '1', 'I' => '1',
            'o' => '0', 'O' => '0',
            's' => '5', 'S' => '5',
            't' => '7', 'T' => '7',
        ];
        return strtr($password, $leet);
    }

    private function toggleCase(string $password): string
    {
        $result = '';
        for ($i = 0; $i < strlen($password); $i++) {
            $char = $password[$i];
            $result .= ($i % 2 === 0) ? strtolower($char) : strtoupper($char);
        }
        return $result;
    }

    private function parseCustomRule(string $password, string $rule): ?string
    {
        // Custom rule format: ^X (prepend X), $X (append X), sXY (substitute X with Y)
        if (strlen($rule) < 2) return null;

        $op = $rule[0];
        $arg = substr($rule, 1);

        return match ($op) {
            '^' => $arg . $password,
            '$' => $password . $arg,
            's' => strlen($arg) >= 2 ? str_replace($arg[0], $arg[1], $password) : null,
            default => null,
        };
    }
}
