#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace BrutyF;

class BrutyF
{
    private const VERSION = '2.0';
    private const AUTHOR = 'farisc0de';
    private const EMAIL = 'farisksa79@gmail.com';
    
    private string $hashFile;
    private string $wordlistFile;
    private bool $verbose;
    private int $threads;
    private array $found = [];
    private bool $pcntlAvailable;
    
    public function __construct(
        string $hashFile = '',
        string $wordlistFile = '',
        bool $verbose = false,
        int $threads = 1
    ) {
        $this->hashFile = $hashFile;
        $this->wordlistFile = $wordlistFile;
        $this->verbose = $verbose;
        $this->threads = max(1, min($threads, 16)); // Limit threads between 1-16
        
        // Check if pcntl extension is available for multithreading
        $this->pcntlAvailable = extension_loaded('pcntl') && function_exists('pcntl_fork');
        if ($this->threads > 1 && !$this->pcntlAvailable) {
            echo "Warning: pcntl extension not available. Multithreading disabled.\n";
            $this->threads = 1;
        }
    }
    
    public function run(): void
    {
        $this->printBanner();
        
        if (empty($this->hashFile) || empty($this->wordlistFile)) {
            $this->help();
            return;
        }
        
        if (!file_exists($this->hashFile)) {
            $this->printError("Hash file not found: {$this->hashFile}");
            return;
        }
        
        if (!file_exists($this->wordlistFile)) {
            $this->printError("Wordlist file not found: {$this->wordlistFile}");
            return;
        }
        
        $this->crackHashes();
        $this->handleResults();
    }
    
    private function crackHashes(): void
    {
        $hashes = $this->readHashFile();
        $totalHashes = count($hashes);
        $totalWords = $this->countLines($this->wordlistFile);
        
        echo "\nStarting attack with {$totalHashes} hash(es) and {$totalWords} password(s)\n";
        echo "Using {$this->threads} thread(s)\n\n";
        
        if ($this->threads > 1 && $this->pcntlAvailable) {
            // Multithreaded approach
            $this->crackHashesMultithreaded($hashes, $totalWords);
        } else {
            // Single-threaded approach
            foreach ($hashes as $hash) {
                $hash = trim($hash);
                if (empty($hash)) continue;
                
                echo "\nAttacking: {$hash}\n";
                echo "----------------------------\n";
                
                $password = $this->crackHash($hash, $totalWords);
                
                if ($password !== null) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                } else {
                    $this->printError("Password not found");
                }
            }
        }
    }
    
    /**
     * Crack hashes using multiple processes (multithreading)
     */
    private function crackHashesMultithreaded(array $hashes, int $totalWords): void
    {
        // Determine the best multithreading strategy based on the number of hashes and wordlist size
        if (count($hashes) >= $this->threads) {
            // If we have enough hashes, divide them among threads
            $this->crackHashesMultithreadedByHash($hashes, $totalWords);
        } else {
            // If we have fewer hashes than threads, divide the wordlist among threads
            $this->crackHashesMultithreadedByWordlist($hashes, $totalWords);
        }
    }
    
    /**
     * Multithreading strategy 1: Divide hashes among threads
     * Best for many hashes with a smaller wordlist
     */
    private function crackHashesMultithreadedByHash(array $hashes, int $totalWords): void
    {
        // Create a temporary file for inter-process communication
        $tmpFile = tempnam(sys_get_temp_dir(), 'brutyf_');
        file_put_contents($tmpFile, serialize([]));
        
        // Distribute hashes among threads
        $hashChunks = array_chunk($hashes, (int)ceil(count($hashes) / $this->threads));
        $childPids = [];
        
        // Create child processes
        foreach ($hashChunks as $chunkIndex => $hashChunk) {
            // Fork a child process
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                // Fork failed
                $this->printError("Failed to create process for thread {$chunkIndex}");
                continue;
            } elseif ($pid == 0) {
                // Child process
                $childResults = [];
                
                foreach ($hashChunk as $hash) {
                    $hash = trim($hash);
                    if (empty($hash)) continue;
                    
                    echo "\n[Thread {$chunkIndex}] Attacking: {$hash}\n";
                    echo "----------------------------\n";
                    
                    $password = $this->crackHash($hash, $totalWords);
                    
                    if ($password !== null) {
                        echo "\n[Thread {$chunkIndex}] ";
                        $this->printSuccess("Password Found: {$password}");
                        $childResults[] = [$hash => $password];
                    } else {
                        echo "\n[Thread {$chunkIndex}] ";
                        $this->printError("Password not found");
                    }
                }
                
                // Write results to the temporary file
                $this->updateSharedResults($tmpFile, $childResults);
                
                // Exit child process
                exit(0);
            } else {
                // Parent process
                $childPids[] = $pid;
            }
        }
        
        // Wait for all child processes to complete
        foreach ($childPids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        // Read results from all child processes
        $allResults = unserialize(file_get_contents($tmpFile));
        foreach ($allResults as $result) {
            $this->found = array_merge($this->found, $result);
        }
        
        // Clean up
        unlink($tmpFile);
    }
    
    /**
     * Multithreading strategy 2: Divide wordlist among threads
     * Best for fewer hashes with a large wordlist
     */
    private function crackHashesMultithreadedByWordlist(array $hashes, int $totalWords): void
    {
        // Create a temporary file for inter-process communication
        $tmpFile = tempnam(sys_get_temp_dir(), 'brutyf_');
        file_put_contents($tmpFile, serialize([]));
        
        $childPids = [];
        $wordsPerThread = (int)ceil($totalWords / $this->threads);
        
        // Create child processes
        for ($threadId = 0; $threadId < $this->threads; $threadId++) {
            // Fork a child process
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                // Fork failed
                $this->printError("Failed to create process for thread {$threadId}");
                continue;
            } elseif ($pid == 0) {
                // Child process
                $childResults = [];
                $startPos = $threadId * $wordsPerThread;
                $endPos = min($startPos + $wordsPerThread, $totalWords);
                
                echo "\n[Thread {$threadId}] Processing passwords {$startPos} to {$endPos}\n";
                
                foreach ($hashes as $hash) {
                    $hash = trim($hash);
                    if (empty($hash)) continue;
                    
                    echo "\n[Thread {$threadId}] Attacking: {$hash}\n";
                    echo "----------------------------\n";
                    
                    $password = $this->crackHashWithRange($hash, $startPos, $endPos);
                    
                    if ($password !== null) {
                        echo "\n[Thread {$threadId}] ";
                        $this->printSuccess("Password Found: {$password}");
                        $childResults[] = [$hash => $password];
                    } else {
                        echo "\n[Thread {$threadId}] ";
                        $this->printError("Password not found in range {$startPos}-{$endPos}");
                    }
                }
                
                // Write results to the temporary file
                $this->updateSharedResults($tmpFile, $childResults);
                
                // Exit child process
                exit(0);
            } else {
                // Parent process
                $childPids[] = $pid;
            }
        }
        
        // Wait for all child processes to complete
        foreach ($childPids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        // Read results from all child processes
        $allResults = unserialize(file_get_contents($tmpFile));
        foreach ($allResults as $result) {
            $this->found = array_merge($this->found, $result);
        }
        
        // Clean up
        unlink($tmpFile);
    }
    
    /**
     * Update shared results in the temporary file (for IPC)
     */
    private function updateSharedResults(string $tmpFile, array $results): void
    {
        // File locking to prevent race conditions
        $fp = fopen($tmpFile, 'r+');
        if (flock($fp, LOCK_EX)) {
            $data = unserialize(file_get_contents($tmpFile));
            $data[] = $results;
            file_put_contents($tmpFile, serialize($data));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
    
    /**
     * Crack a hash by checking the entire wordlist
     */
    private function crackHash(string $hash, int $totalWords): ?string
    {
        $handle = fopen($this->wordlistFile, 'r');
        if (!$handle) {
            $this->printError("Failed to open wordlist file");
            return null;
        }
        
        $i = 0;
        $batchSize = 1000; // Process passwords in batches for better performance
        $passwords = [];
        
        while (!feof($handle)) {
            // Read a batch of passwords
            $passwords = [];
            for ($j = 0; $j < $batchSize && !feof($handle); $j++) {
                $line = fgets($handle);
                if ($line !== false) {
                    $passwords[] = trim($line);
                }
            }
            
            // Process the batch
            foreach ($passwords as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}/{$totalWords}]: {$password}\n";
                    // Add a small delay in verbose mode to make output readable
                    usleep(10000);
                }
                
                if (password_verify($password, $hash)) {
                    fclose($handle);
                    return $password;
                }
                
                // Give the CPU a small break every 10,000 attempts
                if ($i % 10000 === 0) {
                    usleep(1); // Microsecond pause
                }
            }
            
            // Update progress percentage
            $percentage = min(100, round(($i / $totalWords) * 100, 2));
            echo "Progress: {$percentage}% ({$i}/{$totalWords})\r";
        }
        
        fclose($handle);
        return null;
    }
    
    /**
     * Crack a hash by checking only a specific range of passwords in the wordlist
     * Used by the multithreaded wordlist division approach
     */
    private function crackHashWithRange(string $hash, int $startPos, int $endPos): ?string
    {
        $handle = fopen($this->wordlistFile, 'r');
        if (!$handle) {
            $this->printError("Failed to open wordlist file");
            return null;
        }
        
        // Skip to the starting position
        $i = 0;
        while ($i < $startPos && ($line = fgets($handle)) !== false) {
            $i++;
        }
        
        // Process the assigned range
        $batchSize = 1000;
        $passwords = [];
        
        while ($i < $endPos && !feof($handle)) {
            // Read a batch of passwords
            $passwords = [];
            for ($j = 0; $j < $batchSize && $i < $endPos && !feof($handle); $j++) {
                $line = fgets($handle);
                if ($line !== false) {
                    $passwords[] = trim($line);
                    $i++;
                }
            }
            
            // Process the batch
            foreach ($passwords as $password) {
                if ($this->verbose) {
                    echo "Trying [{$i}/{$endPos}]: {$password}\n";
                    // Add a small delay in verbose mode to make output readable
                    usleep(10000);
                }
                
                if (password_verify($password, $hash)) {
                    fclose($handle);
                    return $password;
                }
                
                // Give the CPU a small break every 10,000 attempts
                if ($i % 10000 === 0) {
                    usleep(1); // Microsecond pause
                }
            }
            
            // Update progress percentage
            $rangeSize = $endPos - $startPos;
            $currentPos = $i - $startPos;
            $percentage = min(100, round(($currentPos / $rangeSize) * 100, 2));
            echo "Progress: {$percentage}% ({$currentPos}/{$rangeSize})\r";
        }
        
        fclose($handle);
        return null;
    }
    
    private function handleResults(): void
    {
        if (empty($this->found)) {
            echo "\nNo passwords were found.\n";
            return;
        }
        
        echo "\n" . count($this->found) . " password(s) found!\n";
        
        $option = readline("Do you want to export results? (Y/n): ");
        
        if (strtolower($option) === 'y' || strtolower($option) === 'yes' || $option === '') {
            $filename = "brutyf_result_" . date("Y-m-d_H-i-s") . ".txt";
            $content = "[ BrutyF Result ]\n";
            $content .= "------------------------\n";
            
            foreach ($this->found as $item) {
                foreach ($item as $hash => $password) {
                    $content .= "{$hash}:{$password}\n";
                }
            }
            
            $content .= "------------------------\n";
            
            if (file_put_contents($filename, $content)) {
                echo "Results saved to {$filename}\n";
            } else {
                $this->printError("Failed to save results");
            }
        }
    }
    
    private function readHashFile(): array
    {
        if (!file_exists($this->hashFile)) {
            return [];
        }
        
        $content = file_get_contents($this->hashFile);
        if ($content === false) {
            return [];
        }
        
        return explode(PHP_EOL, trim($content));
    }
    
    private function countLines(string $file): int
    {
        $lineCount = 0;
        $handle = fopen($file, 'rb');
        
        if (!$handle) {
            return 0;
        }
        
        while (!feof($handle)) {
            $lineCount += substr_count(fread($handle, 8192), "\n");
        }
        
        fclose($handle);
        return $lineCount + 1; // +1 for the last line without newline
    }
    
    private function printBanner(): void
    {
        echo "\e[1;31m
-------------------------------------
  ____             _         ______ 
 |  _ \           | |       |  ____|
 | |_) |_ __ _   _| |_ _   _| |__   
 |  _ <| '__| | | | __| | | |  __|  
 | |_) | |  | |_| | |_| |_| | |     
 |____/|_|   \__,_|\__|\__, |_|     
                        __/ |       
                       |___/ v" . self::VERSION . "
-------------------------------------\e[0m
";
    }
    
    private function help(): void
    {
        global $argv;
        echo "Usage:\n";
        echo basename($argv[0]) . " -f=<hashfile> -w=<passwordlist> [-v] [-t=<threads>]\n";
        echo "Options:\n";
        echo "  -f, --hashfile=FILE    File containing hashed passwords\n";
        echo "  -w, --wordlist=FILE    Wordlist file with passwords to try\n";
        echo "  -v, --verbose          Show detailed progress\n";
        echo "  -t, --threads=NUM      Number of threads (1-16, default: 1)\n";
        echo "  -a, --about            Show information about BrutyF\n";
        echo "Example:\n";
        echo basename($argv[0]) . " -f=hash.txt -w=passwords.txt -t=4\n";
        echo "-----------------------------------------------------\n";
    }
    
    private function about(): void
    {
        echo "Name: BrutyF\n";
        echo "Version: " . self::VERSION . "\n";
        echo "Developed by: " . self::AUTHOR . "\n";
        echo "Email: " . self::EMAIL . "\n";
    }
    
    private function printSuccess(string $message): void
    {
        echo "\e[1;32m";
        echo "-------------------------------\n";
        echo "{$message}\n";
        echo "-------------------------------\n";
        echo "\e[0m";
    }
    
    private function printError(string $message): void
    {
        echo "\e[1;31m";
        echo "-------------------------------\n";
        echo "{$message}\n";
        echo "-------------------------------\n";
        echo "\e[0m";
    }
}

// CLI entry point
(function() {
    $short_options = "f:w:vat:";
    $long_options = ["wordlist:", "hashfile:", "verbose", "about", "threads:"];
    $options = getopt($short_options, $long_options);
    
    if (isset($options["about"]) || isset($options["a"])) {
        $brutyf = new BrutyF();
        $brutyf->run();
        exit(0);
    }
    
    $hashFile = $options["hashfile"] ?? $options["f"] ?? '';
    $wordlist = $options["wordlist"] ?? $options["w"] ?? '';
    $verbose = isset($options["verbose"]) || isset($options["v"]);
    $threads = (int)($options["threads"] ?? $options["t"] ?? 1);
    
    $brutyf = new BrutyF($hashFile, $wordlist, $verbose, $threads);
    $brutyf->run();
})();
