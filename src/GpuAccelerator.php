<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * GPU Acceleration for hash cracking
 * Uses hashcat as backend when available, falls back to CPU
 */
class GpuAccelerator
{
    private bool $available = false;
    private string $hashcatPath = '';
    private array $devices = [];
    private bool $useColor;
    private string $tempDir;
    
    // Hashcat hash type mappings
    private const HASH_MODES = [
        'md5' => 0,
        'sha1' => 100,
        'sha256' => 1400,
        'sha512' => 1700,
        'ntlm' => 1000,
        'mysql' => 200,
        'mysql5' => 300,
        'bcrypt' => 3200,
        'md5crypt' => 500,
        'sha256crypt' => 7400,
        'sha512crypt' => 1800,
        'phpass' => 400,
        'wordpress' => 400,
        'drupal7' => 7900,
        'sha1_salt' => 110,
        'md5_salt' => 10,
        'wpa' => 22000,
        'descrypt' => 1500,
    ];
    
    public function __construct(bool $useColor = true)
    {
        $this->useColor = $useColor;
        $this->tempDir = sys_get_temp_dir();
        $this->detectHashcat();
        if ($this->available) {
            $this->detectDevices();
        }
    }
    
    private function detectHashcat(): void
    {
        // Check common locations
        $paths = [
            'hashcat',
            '/usr/bin/hashcat',
            '/usr/local/bin/hashcat',
            '/opt/hashcat/hashcat',
            getenv('HOME') . '/hashcat/hashcat',
        ];
        
        foreach ($paths as $path) {
            $output = [];
            $returnCode = 0;
            @exec("$path --version 2>/dev/null", $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                $this->hashcatPath = $path;
                $this->available = true;
                return;
            }
        }
    }
    
    private function detectDevices(): void
    {
        if (!$this->available) {
            return;
        }
        
        $output = [];
        exec("{$this->hashcatPath} -I 2>/dev/null", $output);
        
        $currentDevice = null;
        foreach ($output as $line) {
            if (preg_match('/^Backend Device #(\d+)/', $line, $matches)) {
                $currentDevice = (int)$matches[1];
                $this->devices[$currentDevice] = [
                    'id' => $currentDevice,
                    'name' => '',
                    'type' => '',
                    'memory' => 0,
                ];
            } elseif ($currentDevice !== null) {
                if (preg_match('/Name\.+:\s*(.+)/', $line, $matches)) {
                    $this->devices[$currentDevice]['name'] = trim($matches[1]);
                } elseif (preg_match('/Type\.+:\s*(.+)/', $line, $matches)) {
                    $this->devices[$currentDevice]['type'] = trim($matches[1]);
                } elseif (preg_match('/Memory\.+:\s*(\d+)/', $line, $matches)) {
                    $this->devices[$currentDevice]['memory'] = (int)$matches[1];
                }
            }
        }
    }
    
    public function isAvailable(): bool
    {
        return $this->available;
    }
    
    public function getDevices(): array
    {
        return $this->devices;
    }
    
    public function getHashcatPath(): string
    {
        return $this->hashcatPath;
    }
    
    public function getHashMode(string $hashType): ?int
    {
        return self::HASH_MODES[strtolower($hashType)] ?? null;
    }
    
    public function getSupportedTypes(): array
    {
        return array_keys(self::HASH_MODES);
    }
    
    /**
     * Run GPU-accelerated attack
     */
    public function crack(array $options): array
    {
        if (!$this->available) {
            return [
                'success' => false,
                'error' => 'Hashcat not available',
                'found' => [],
            ];
        }
        
        $hashes = $options['hashes'] ?? [];
        $hashType = $options['hash_type'] ?? 'md5';
        $wordlist = $options['wordlist'] ?? null;
        $mask = $options['mask'] ?? null;
        $rules = $options['rules'] ?? null;
        $devices = $options['devices'] ?? null;
        $workload = $options['workload'] ?? 3; // 1=low, 2=default, 3=high, 4=nightmare
        
        $hashMode = $this->getHashMode($hashType);
        if ($hashMode === null) {
            return [
                'success' => false,
                'error' => "Unsupported hash type for GPU: $hashType",
                'found' => [],
            ];
        }
        
        // Create temp files
        $hashFile = $this->tempDir . '/brutyf_gpu_hashes_' . uniqid() . '.txt';
        $potFile = $this->tempDir . '/brutyf_gpu_pot_' . uniqid() . '.pot';
        $outFile = $this->tempDir . '/brutyf_gpu_out_' . uniqid() . '.txt';
        
        file_put_contents($hashFile, implode("\n", $hashes));
        
        // Build command - hashcat format: hashcat [options] hashfile [mask|wordlist]
        $cmdParts = [
            $this->hashcatPath,
            '-m', (string)$hashMode,
            '-a', $mask ? '3' : '0',
            '--potfile-disable',
            '-o', $outFile,
            '--outfile-format=1,2',
            '-w', (string)(int)$workload,
            '--quiet',
            '--force',
        ];
        
        if ($devices !== null) {
            $cmdParts[] = '-d';
            $cmdParts[] = $devices;
        }
        
        $cmdParts[] = $hashFile;
        
        if ($mask) {
            $cmdParts[] = $this->convertMask($mask);
        } elseif ($wordlist) {
            $cmdParts[] = $wordlist;
            if ($rules) {
                $cmdParts[] = '-r';
                $cmdParts[] = $rules;
            }
        } else {
            return [
                'success' => false,
                'error' => 'Wordlist or mask required',
                'found' => [],
            ];
        }
        
        // Escape each part properly
        $cmdStr = implode(' ', array_map('escapeshellarg', $cmdParts)) . ' 2>&1';
        
        $output = [];
        $returnCode = 0;
        exec($cmdStr, $output, $returnCode);
        
        // Parse results
        $found = [];
        if (file_exists($outFile)) {
            $results = file($outFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($results as $line) {
                if (strpos($line, ':') !== false) {
                    [$hash, $password] = explode(':', $line, 2);
                    $found[$hash] = $password;
                }
            }
        }
        
        // Cleanup
        @unlink($hashFile);
        @unlink($potFile);
        @unlink($outFile);
        
        return [
            'success' => true,
            'found' => $found,
            'output' => $output,
            'return_code' => $returnCode,
        ];
    }
    
    /**
     * Convert BrutyF mask to hashcat mask
     */
    private function convertMask(string $mask): string
    {
        // BrutyF and hashcat use same mask format
        // ?l=lower, ?u=upper, ?d=digit, ?s=special, ?a=all
        return $mask;
    }
    
    /**
     * Run benchmark on GPU
     */
    public function benchmark(?string $hashType = null): array
    {
        if (!$this->available) {
            return ['error' => 'Hashcat not available'];
        }
        
        $cmd = escapeshellarg($this->hashcatPath) . ' -b --quiet';
        
        if ($hashType) {
            $mode = $this->getHashMode($hashType);
            if ($mode !== null) {
                $cmd .= " -m $mode";
            }
        }
        
        $output = [];
        exec($cmd . ' 2>&1', $output);
        
        $results = [];
        foreach ($output as $line) {
            // Parse benchmark output
            if (preg_match('/Hashmode:\s*(\d+)\s*-\s*(.+)/', $line, $matches)) {
                $currentMode = $matches[1];
                $currentName = trim($matches[2]);
            } elseif (preg_match('/Speed\.#\*\.+:\s*([\d.]+)\s*(\w+)/', $line, $matches)) {
                $speed = (float)$matches[1];
                $unit = $matches[2];
                
                // Convert to H/s
                $multipliers = [
                    'H/s' => 1,
                    'kH/s' => 1000,
                    'MH/s' => 1000000,
                    'GH/s' => 1000000000,
                ];
                
                $speedHs = $speed * ($multipliers[$unit] ?? 1);
                $results[$currentName ?? 'unknown'] = $speedHs;
            }
        }
        
        return $results;
    }
    
    /**
     * Get GPU info for display
     */
    public function getInfo(): array
    {
        if (!$this->available) {
            return [
                'available' => false,
                'message' => 'GPU acceleration not available (hashcat not found)',
            ];
        }
        
        return [
            'available' => true,
            'hashcat_path' => $this->hashcatPath,
            'devices' => $this->devices,
            'supported_types' => $this->getSupportedTypes(),
        ];
    }
    
    /**
     * Print GPU status
     */
    public function printStatus(): void
    {
        $green = $this->useColor ? "\e[1;32m" : "";
        $red = $this->useColor ? "\e[1;31m" : "";
        $cyan = $this->useColor ? "\e[1;36m" : "";
        $yellow = $this->useColor ? "\e[1;33m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "\n{$cyan}=== GPU Acceleration Status ==={$reset}\n\n";
        
        if (!$this->available) {
            echo "{$red}✗ GPU acceleration not available{$reset}\n";
            echo "  Install hashcat for GPU support: https://hashcat.net/hashcat/\n\n";
            return;
        }
        
        echo "{$green}✓ GPU acceleration available{$reset}\n";
        echo "  Hashcat: {$this->hashcatPath}\n\n";
        
        if (!empty($this->devices)) {
            echo "{$yellow}Detected Devices:{$reset}\n";
            foreach ($this->devices as $device) {
                $type = $device['type'] ?? 'Unknown';
                $name = $device['name'] ?? 'Unknown';
                $memory = $device['memory'] ?? 0;
                
                $icon = stripos($type, 'GPU') !== false ? '🎮' : '💻';
                echo "  {$icon} #{$device['id']}: {$name}\n";
                if ($memory > 0) {
                    echo "     Memory: " . round($memory / 1024 / 1024) . " MB\n";
                }
            }
        }
        
        echo "\n{$yellow}Supported Hash Types:{$reset}\n";
        $types = array_chunk($this->getSupportedTypes(), 6);
        foreach ($types as $row) {
            echo "  " . implode(', ', $row) . "\n";
        }
        echo "\n";
    }
}
