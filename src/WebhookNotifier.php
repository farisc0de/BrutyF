<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Send webhook notifications when passwords are found
 */
class WebhookNotifier
{
    private array $urls;
    private int $timeout;
    private array $headers;
    
    public function __construct(array $urls, int $timeout = 10, array $headers = [])
    {
        $this->urls = $urls;
        $this->timeout = $timeout;
        $this->headers = array_merge([
            'Content-Type: application/json',
            'User-Agent: BrutyF/3.4',
        ], $headers);
    }
    
    public function notifyFound(string $hash, string $password, string $type): bool
    {
        $payload = [
            'event' => 'password_found',
            'timestamp' => date('c'),
            'data' => [
                'hash' => $hash,
                'password' => $password,
                'hash_type' => $type,
            ],
        ];
        
        return $this->send($payload);
    }
    
    public function notifyStart(array $hashes, string $attackMode, int $totalPasswords): bool
    {
        $payload = [
            'event' => 'attack_started',
            'timestamp' => date('c'),
            'data' => [
                'hash_count' => count($hashes),
                'attack_mode' => $attackMode,
                'total_passwords' => $totalPasswords,
            ],
        ];
        
        return $this->send($payload);
    }
    
    public function notifyComplete(array $found, float $duration, int $totalTried): bool
    {
        $payload = [
            'event' => 'attack_complete',
            'timestamp' => date('c'),
            'data' => [
                'found_count' => count($found),
                'found' => $found,
                'duration_seconds' => round($duration, 2),
                'total_tried' => $totalTried,
            ],
        ];
        
        return $this->send($payload);
    }
    
    public function notifyProgress(float $percentage, int $tried, int $total, float $speed): bool
    {
        $payload = [
            'event' => 'progress',
            'timestamp' => date('c'),
            'data' => [
                'percentage' => round($percentage, 2),
                'tried' => $tried,
                'total' => $total,
                'speed' => round($speed, 2),
            ],
        ];
        
        return $this->send($payload);
    }
    
    public function notifyError(string $message, ?string $hash = null): bool
    {
        $payload = [
            'event' => 'error',
            'timestamp' => date('c'),
            'data' => [
                'message' => $message,
                'hash' => $hash,
            ],
        ];
        
        return $this->send($payload);
    }
    
    private function send(array $payload): bool
    {
        $json = json_encode($payload);
        $success = true;
        
        foreach ($this->urls as $url) {
            $result = $this->sendToUrl($url, $json);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    private function sendToUrl(string $url, string $json): bool
    {
        // Detect webhook type and format accordingly
        if (strpos($url, 'discord.com/api/webhooks') !== false) {
            return $this->sendDiscord($url, $json);
        }
        
        if (strpos($url, 'hooks.slack.com') !== false) {
            return $this->sendSlack($url, $json);
        }
        
        // Generic webhook
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    private function sendDiscord(string $url, string $json): bool
    {
        $data = json_decode($json, true);
        $event = $data['event'];
        $eventData = $data['data'];
        
        // Format for Discord
        $embed = [
            'title' => 'BrutyF: ' . ucfirst(str_replace('_', ' ', $event)),
            'timestamp' => $data['timestamp'],
            'color' => $this->getColorForEvent($event),
            'fields' => [],
        ];
        
        foreach ($eventData as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $embed['fields'][] = [
                'name' => ucfirst(str_replace('_', ' ', $key)),
                'value' => (string)$value,
                'inline' => true,
            ];
        }
        
        $discordPayload = json_encode(['embeds' => [$embed]]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $discordPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    private function sendSlack(string $url, string $json): bool
    {
        $data = json_decode($json, true);
        $event = $data['event'];
        $eventData = $data['data'];
        
        // Format for Slack
        $text = "*BrutyF: " . ucfirst(str_replace('_', ' ', $event)) . "*\n";
        foreach ($eventData as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $text .= "• *" . ucfirst(str_replace('_', ' ', $key)) . "*: {$value}\n";
        }
        
        $slackPayload = json_encode(['text' => $text]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $slackPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    private function getColorForEvent(string $event): int
    {
        return match ($event) {
            'password_found' => 0x00FF00,  // Green
            'attack_started' => 0x0099FF,  // Blue
            'attack_complete' => 0x9900FF, // Purple
            'progress' => 0xFFFF00,        // Yellow
            'error' => 0xFF0000,           // Red
            default => 0x808080,           // Gray
        };
    }
}
