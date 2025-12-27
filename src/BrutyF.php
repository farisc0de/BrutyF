<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Main BrutyF class
 */
class BrutyF
{
    private const VERSION = '3.4';
    private const AUTHOR = 'farisc0de';
    private const EMAIL = 'farisksa79@gmail.com';
    
    private string $hashFile = '';
    private string $wordlistFile = '';
    private string $wordlistFile2 = '';
    private string $mask = '';
    private bool $verbose = false;
    private bool $quiet = false;
    private bool $useColor = true;
    private int $threads = 1;
    private string $hashType = 'auto';
    private string $outputFormat = 'text';
    private ?string $outputFile = null;
    private array $rules = [];
    private bool $resume = false;
    private string $attackMode = 'wordlist';
    private bool $usePotfile = true;
    private ?string $potfilePath = null;
    private int $skip = 0;
    private int $limit = 0;
    private int $incrementMin = 1;
    private int $incrementMax = 8;
    private array $customCharsets = [];
    private ?string $statusFile = null;
    private bool $jsonOutput = false;
    private ?string $webhookUrl = null;
    private ?WebhookNotifier $webhook = null;
    private ?string $markovFile = null;
    private int $markovOrder = 2;
    private bool $useGpu = false;
    private ?string $gpuDevices = null;
    private int $gpuWorkload = 3;
    
    private array $found = [];
    private bool $pcntlAvailable;
    private Config $config;
    private ?Session $session = null;
    private Statistics $stats;
    private Logger $logger;
    private ?RuleEngine $ruleEngine = null;
    private ?Potfile $potfile = null;
    
    private ?string $sharedMemFile = null;
    
    public function __construct(array $options = [])
    {
        $this->config = new Config();
        $this->stats = new Statistics();
        
        foreach (Config::getConfigPaths() as $path) {
            if ($this->config->loadFromFile($path)) {
                break;
            }
        }
        
        $this->hashFile = $options['hashfile'] ?? '';
        $this->wordlistFile = $options['wordlist'] ?? '';
        $this->wordlistFile2 = $options['wordlist2'] ?? '';
        $this->mask = $options['mask'] ?? '';
        $this->verbose = $options['verbose'] ?? $this->config->get('verbose', false);
        $this->quiet = $options['quiet'] ?? $this->config->get('quiet', false);
        $this->useColor = $options['color'] ?? $this->config->get('color', true);
        $this->threads = max(1, min($options['threads'] ?? $this->config->get('threads', 1), 16));
        $this->hashType = $options['hash_type'] ?? $this->config->get('hash_type', 'auto');
        $this->outputFormat = $options['output_format'] ?? $this->config->get('output_format', 'text');
        $this->outputFile = $options['output_file'] ?? null;
        $this->rules = $options['rules'] ?? $this->config->get('rules', []);
        $this->resume = $options['resume'] ?? false;
        $this->attackMode = $options['attack_mode'] ?? 'wordlist';
        $this->usePotfile = $options['potfile'] ?? $this->config->get('potfile', true);
        $this->potfilePath = $options['potfile_path'] ?? $this->config->get('potfile_path');
        $this->skip = $options['skip'] ?? 0;
        $this->limit = $options['limit'] ?? 0;
        $this->incrementMin = $options['increment_min'] ?? 1;
        $this->incrementMax = $options['increment_max'] ?? 8;
        $this->customCharsets = $options['custom_charsets'] ?? [];
        $this->statusFile = $options['status_file'] ?? null;
        $this->jsonOutput = $options['json_output'] ?? false;
        $this->webhookUrl = $options['webhook'] ?? null;
        $this->markovFile = $options['markov_file'] ?? null;
        $this->markovOrder = $options['markov_order'] ?? 2;
        $this->useGpu = $options['gpu'] ?? false;
        $this->gpuDevices = $options['gpu_devices'] ?? null;
        $this->gpuWorkload = $options['gpu_workload'] ?? 3;
        
        if ($this->webhookUrl) {
            $this->webhook = new WebhookNotifier([$this->webhookUrl]);
        }
        
        $logFile = $options['log_file'] ?? $this->config->get('log_file');
        $logLevel = $options['log_level'] ?? $this->config->get('log_level', 'info');
        $this->logger = new Logger($logFile, $logLevel);
        
        if (!empty($this->rules)) {
            $this->ruleEngine = new RuleEngine($this->rules);
        }
        
        $this->pcntlAvailable = extension_loaded('pcntl') && function_exists('pcntl_fork');
        if ($this->threads > 1 && !$this->pcntlAvailable) {
            $this->printWarning("pcntl extension not available. Multithreading disabled.");
            $this->threads = 1;
        }
        
        if ($this->usePotfile) {
            $this->potfile = new Potfile($this->potfilePath);
        }
    }
    
    public function run(): void
    {
        $this->printBanner();
        
        // GPU mode - use hashcat backend
        if ($this->useGpu) {
            $this->runGpuMode();
            return;
        }
        
        if ($this->attackMode === 'incremental') {
            $this->runIncrementalMode();
            return;
        }
        
        if ($this->attackMode === 'markov') {
            $this->runMarkovMode();
            return;
        }
        
        if ($this->attackMode === 'keyboard-walk') {
            $this->runKeyboardWalkMode();
            return;
        }
        
        if ($this->attackMode === 'hybrid' && !empty($this->wordlistFile) && !empty($this->mask)) {
            $this->runHybridMode();
            return;
        }
        
        if ($this->attackMode === 'combinator' && !empty($this->wordlistFile) && !empty($this->wordlistFile2)) {
            $this->runCombinatorMode();
            return;
        }
        
        if (!empty($this->mask) && empty($this->wordlistFile)) {
            $this->runMaskMode();
            return;
        }
        
        if (empty($this->hashFile) || (empty($this->wordlistFile) && empty($this->mask))) {
            $this->help();
            return;
        }
        
        if (!file_exists($this->hashFile)) {
            $this->printError("Hash file not found: {$this->hashFile}");
            return;
        }
        
        if (!empty($this->wordlistFile) && !file_exists($this->wordlistFile)) {
            $this->printError("Wordlist file not found: {$this->wordlistFile}");
            return;
        }
        
        $this->session = new Session($this->hashFile, $this->wordlistFile);
        
        if ($this->resume && $this->session->exists()) {
            $this->printInfo("Resuming previous session...");
            $this->found = array_map(fn($h, $p) => [$h => $p], 
                array_keys($this->session->getFound()), 
                array_values($this->session->getFound())
            );
        }
        
        $this->stats->start();
        $this->crackHashes();
        $this->stats->stop();
        
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function runHybridMode(): void
    {
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for hybrid mode");
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
        
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        $generator = HybridGenerator::fromWordlistFile($this->wordlistFile, $this->mask);
        $totalCombinations = $generator->getTotalCombinations();
        
        $this->printInfo("Hybrid mode: wordlist + mask ({$this->mask})");
        $this->printInfo("Total combinations: " . number_format($totalCombinations));
        
        $this->stats->start();
        
        foreach ($hashes as $hashEntry) {
            $hashEntry = trim($hashEntry);
            if (empty($hashEntry)) continue;
            
            [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
            $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
            
            $this->printInfo("Attacking: {$hash} (Type: {$type})");
            
            $i = 0;
            foreach ($generator as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}/{$totalCombinations}]: {$password}\n";
                }
                
                if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                    $this->stats->incrementFound();
                    $this->addToPotfile($hash, $password);
                    break;
                }
                
                if ($i % 1000 === 0) {
                    $percentage = round(($i / $totalCombinations) * 100, 2);
                    $this->printProgress($percentage, $i, $totalCombinations);
                }
                
                $this->stats->incrementPasswords();
            }
            
            $generator->rewind();
        }
        
        $this->stats->stop();
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function runCombinatorMode(): void
    {
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for combinator mode");
            return;
        }
        
        if (!file_exists($this->hashFile)) {
            $this->printError("Hash file not found: {$this->hashFile}");
            return;
        }
        
        if (!file_exists($this->wordlistFile) || !file_exists($this->wordlistFile2)) {
            $this->printError("Both wordlist files are required for combinator mode");
            return;
        }
        
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        $generator = CombinatorGenerator::fromFiles($this->wordlistFile, $this->wordlistFile2);
        $totalCombinations = $generator->getTotalCombinations();
        
        $this->printInfo("Combinator mode: wordlist1 + wordlist2");
        $this->printInfo("Total combinations: " . number_format($totalCombinations));
        
        $this->stats->start();
        
        foreach ($hashes as $hashEntry) {
            $hashEntry = trim($hashEntry);
            if (empty($hashEntry)) continue;
            
            [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
            $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
            
            $this->printInfo("Attacking: {$hash} (Type: {$type})");
            
            $i = 0;
            foreach ($generator as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}/{$totalCombinations}]: {$password}\n";
                }
                
                if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                    $this->stats->incrementFound();
                    $this->addToPotfile($hash, $password);
                    break;
                }
                
                if ($i % 1000 === 0) {
                    $percentage = round(($i / $totalCombinations) * 100, 2);
                    $this->printProgress($percentage, $i, $totalCombinations);
                }
                
                $this->stats->incrementPasswords();
            }
            
            $generator->rewind();
        }
        
        $this->stats->stop();
        $this->handleResults();
        $this->printStatistics();
    }
    
    public function benchmark(): void
    {
        $benchmark = new Benchmark();
        $benchmark->run($this->useColor);
    }
    
    private function runIncrementalMode(): void
    {
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for incremental mode");
            return;
        }
        
        if (!file_exists($this->hashFile)) {
            $this->printError("Hash file not found: {$this->hashFile}");
            return;
        }
        
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        // Determine charset
        $charset = $this->customCharsets['default'] ?? 'abcdefghijklmnopqrstuvwxyz';
        
        $generator = new IncrementalGenerator(
            $this->incrementMin,
            $this->incrementMax,
            $charset
        );
        
        $totalCombinations = $generator->getTotalCombinations();
        
        $this->printInfo("Incremental mode: length {$this->incrementMin}-{$this->incrementMax}");
        $this->printInfo("Charset: " . (strlen($charset) > 20 ? substr($charset, 0, 20) . '...' : $charset) . " (" . strlen($charset) . " chars)");
        $this->printInfo("Total combinations: " . number_format($totalCombinations));
        
        $this->stats->start();
        $progressFile = $this->statusFile ? new ProgressFile($this->statusFile) : null;
        
        foreach ($hashes as $hashEntry) {
            $hashEntry = trim($hashEntry);
            if (empty($hashEntry)) continue;
            
            [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
            $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
            
            $this->printInfo("Attacking: {$hash} (Type: {$type})");
            
            $i = 0;
            $found = false;
            
            foreach ($generator as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}/{$totalCombinations}]: {$password}\n";
                }
                
                if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                    $this->stats->incrementFound();
                    $this->addToPotfile($hash, $password);
                    $found = true;
                    break;
                }
                
                $this->stats->incrementPasswords();
                
                if ($i % 10000 === 0) {
                    $percentage = min(100, round(($i / $totalCombinations) * 100, 4));
                    $this->printProgress($percentage, $i, $totalCombinations);
                    
                    if ($progressFile) {
                        $progressFile->update([
                            'hash' => $hash,
                            'progress' => $percentage,
                            'tried' => $i,
                            'total' => $totalCombinations,
                            'speed' => $this->stats->getSpeed(),
                            'current_length' => $generator->getCurrentLength(),
                        ]);
                    }
                }
                
                // Apply limit if set
                if ($this->limit > 0 && $i >= $this->limit) {
                    $this->printInfo("Limit reached ({$this->limit})");
                    break;
                }
            }
            
            if (!$found) {
                $this->printError("Password not found");
            }
            
            $generator->rewind();
        }
        
        $this->stats->stop();
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function runMarkovMode(): void
    {
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for markov mode");
            return;
        }
        
        if (empty($this->markovFile) && empty($this->wordlistFile)) {
            $this->printError("Markov model file (--markov-load) or training wordlist (-w) required");
            return;
        }
        
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        $generator = new MarkovGenerator(
            $this->markovOrder,
            $this->incrementMin,
            $this->incrementMax,
            $this->limit > 0 ? $this->limit : 100000
        );
        
        // Load or train
        if ($this->markovFile && file_exists($this->markovFile)) {
            $this->printInfo("Loading Markov model from: {$this->markovFile}");
            $generator->load($this->markovFile);
        } elseif ($this->wordlistFile) {
            $this->printInfo("Training Markov model from: {$this->wordlistFile}");
            $generator->trainFromFile($this->wordlistFile);
        }
        
        $this->printInfo("Markov mode: order {$this->markovOrder}, length {$this->incrementMin}-{$this->incrementMax}");
        $this->printInfo("Chains: " . $generator->getChainCount() . ", Start states: " . $generator->getStartCount());
        
        $this->stats->start();
        
        if ($this->webhook) {
            $this->webhook->notifyStart($hashes, 'markov', $this->limit > 0 ? $this->limit : 100000);
        }
        
        foreach ($hashes as $hashEntry) {
            $hashEntry = trim($hashEntry);
            if (empty($hashEntry)) continue;
            
            [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
            $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
            
            $this->printInfo("Attacking: {$hash} (Type: {$type})");
            
            $found = false;
            $i = 0;
            
            foreach ($generator as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}]: {$password}\n";
                }
                
                if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                    $this->stats->incrementFound();
                    $this->addToPotfile($hash, $password);
                    
                    if ($this->webhook) {
                        $this->webhook->notifyFound($hash, $password, $type);
                    }
                    
                    $found = true;
                    break;
                }
                
                $this->stats->incrementPasswords();
                
                if ($i % 10000 === 0) {
                    $this->printProgress(0, $i, 0);
                }
            }
            
            if (!$found) {
                $this->printError("Password not found");
            }
        }
        
        $this->stats->stop();
        
        if ($this->webhook) {
            $this->webhook->notifyComplete($this->found, $this->stats->getDuration(), $this->stats->getPasswordsTried());
        }
        
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function runKeyboardWalkMode(): void
    {
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for keyboard-walk mode");
            return;
        }
        
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        $generator = new KeyboardWalkGenerator($this->incrementMin, $this->incrementMax);
        $patterns = $generator->getPatterns();
        $totalPatterns = count($patterns);
        
        $this->printInfo("Keyboard walk mode: {$totalPatterns} patterns");
        $this->printInfo("Length range: {$this->incrementMin}-{$this->incrementMax}");
        
        $this->stats->start();
        
        if ($this->webhook) {
            $this->webhook->notifyStart($hashes, 'keyboard-walk', $totalPatterns);
        }
        
        foreach ($hashes as $hashEntry) {
            $hashEntry = trim($hashEntry);
            if (empty($hashEntry)) continue;
            
            [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
            $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
            
            $this->printInfo("Attacking: {$hash} (Type: {$type})");
            
            $found = false;
            $i = 0;
            
            foreach ($patterns as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}/{$totalPatterns}]: {$password}\n";
                }
                
                if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                    $this->stats->incrementFound();
                    $this->addToPotfile($hash, $password);
                    
                    if ($this->webhook) {
                        $this->webhook->notifyFound($hash, $password, $type);
                    }
                    
                    $found = true;
                    break;
                }
                
                $this->stats->incrementPasswords();
                
                if ($i % 100 === 0) {
                    $percentage = round(($i / $totalPatterns) * 100, 2);
                    $this->printProgress($percentage, $i, $totalPatterns);
                }
            }
            
            if (!$found) {
                $this->printError("Password not found");
            }
        }
        
        $this->stats->stop();
        
        if ($this->webhook) {
            $this->webhook->notifyComplete($this->found, $this->stats->getDuration(), $this->stats->getPasswordsTried());
        }
        
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function runGpuMode(): void
    {
        $gpu = new GpuAccelerator($this->useColor);
        
        if (!$gpu->isAvailable()) {
            $this->printError("GPU acceleration not available (hashcat not found)");
            $this->printInfo("Install hashcat for GPU support: https://hashcat.net/hashcat/");
            $this->printInfo("Falling back to CPU mode...\n");
            $this->useGpu = false;
            $this->run();
            return;
        }
        
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for GPU mode");
            return;
        }
        
        if (!file_exists($this->hashFile)) {
            $this->printError("Hash file not found: {$this->hashFile}");
            return;
        }
        
        $hashes = $this->readHashFile($this->hashFile);
        if (empty($hashes)) {
            $this->printError("No hashes found in file");
            return;
        }
        
        // Filter already cracked
        $hashes = $this->filterPotfileHashes($hashes);
        if (empty($hashes)) {
            $this->printSuccess("All hashes already cracked (found in potfile)");
            $this->handleResults();
            return;
        }
        
        $this->printInfo("GPU Mode: Using hashcat backend");
        $this->printInfo("Hashcat path: " . $gpu->getHashcatPath());
        $this->printInfo("Hash type: {$this->hashType}");
        $this->printInfo("Hashes to crack: " . count($hashes));
        
        if (!empty($this->wordlistFile)) {
            $this->printInfo("Wordlist: {$this->wordlistFile}");
        } elseif (!empty($this->mask)) {
            $this->printInfo("Mask: {$this->mask}");
        }
        
        echo "\n";
        $this->printInfo("Starting GPU attack...\n");
        
        $this->stats->start();
        
        if ($this->webhook) {
            $this->webhook->notifyStart(count($hashes), $this->attackMode);
        }
        
        $result = $gpu->crack([
            'hashes' => $hashes,
            'hash_type' => $this->hashType,
            'wordlist' => $this->wordlistFile ?: null,
            'mask' => $this->mask ?: null,
            'rules' => !empty($this->rules) ? $this->rules[0] : null,
            'devices' => $this->gpuDevices,
            'workload' => $this->gpuWorkload,
        ]);
        
        $this->stats->stop();
        
        if (!$result['success']) {
            $this->printError("GPU attack failed: " . ($result['error'] ?? 'Unknown error'));
            return;
        }
        
        // Process results
        foreach ($result['found'] as $hash => $password) {
            $this->found[] = [$hash => $password];
            $this->addToPotfile($hash, $password);
            $this->printSuccess("Found: {$hash} => {$password}");
            
            if ($this->webhook) {
                $this->webhook->notifyFound($hash, $password, $this->hashType);
            }
        }
        
        if ($this->webhook) {
            $this->webhook->notifyComplete($this->found, $this->stats->getDuration(), 0);
        }
        
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function filterPotfileHashes(array $hashes): array
    {
        if (!$this->potfile) {
            return $hashes;
        }
        
        $alreadyCracked = $this->potfile->getAlreadyCracked($hashes);
        if (!empty($alreadyCracked)) {
            $this->printInfo("Found " . count($alreadyCracked) . " hash(es) in potfile:");
            foreach ($alreadyCracked as $hash => $password) {
                $this->printInfo("  {$hash} => {$password}");
                $this->found[] = [$hash => $password];
            }
        }
        
        return $this->potfile->filterHashes($hashes);
    }
    
    private function addToPotfile(string $hash, string $password): void
    {
        if ($this->potfile) {
            $this->potfile->add($hash, $password);
        }
    }
    
    private function runMaskMode(): void
    {
        if (empty($this->hashFile)) {
            $this->printError("Hash file is required for mask mode");
            return;
        }
        
        if (!file_exists($this->hashFile)) {
            $this->printError("Hash file not found: {$this->hashFile}");
            return;
        }
        
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        $generator = new MaskGenerator($this->mask);
        $totalCombinations = $generator->getTotalCombinations();
        
        $this->printInfo("Mask mode: {$this->mask}");
        $this->printInfo("Total combinations: " . number_format($totalCombinations));
        
        $this->stats->start();
        
        foreach ($hashes as $hashEntry) {
            $hashEntry = trim($hashEntry);
            if (empty($hashEntry)) continue;
            
            [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
            $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
            
            $this->printInfo("Attacking: {$hash} (Type: {$type})");
            
            $i = 0;
            foreach ($generator as $password) {
                $i++;
                
                if ($this->verbose) {
                    echo "Trying [{$i}/{$totalCombinations}]: {$password}\n";
                }
                
                if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                    $this->printSuccess("Password Found: {$password}");
                    $this->found[] = [$hash => $password];
                    $this->stats->incrementFound();
                    $this->addToPotfile($hash, $password);
                    break;
                }
                
                if ($i % 1000 === 0) {
                    $percentage = round(($i / $totalCombinations) * 100, 2);
                    $this->printProgress($percentage, $i, $totalCombinations);
                }
                
                $this->stats->incrementPasswords();
            }
            
            $generator->rewind();
        }
        
        $this->stats->stop();
        $this->handleResults();
        $this->printStatistics();
    }
    
    private function crackHashes(): void
    {
        $hashes = $this->readHashFile();
        $hashes = $this->filterPotfileHashes($hashes);
        
        if (empty($hashes)) {
            $this->printInfo("All hashes already cracked (found in potfile)");
            return;
        }
        
        $totalHashes = count($hashes);
        $totalWords = $this->countLines($this->wordlistFile);
        
        $rulesMultiplier = empty($this->rules) ? 1 : count($this->rules);
        $effectiveTotal = $totalWords * $rulesMultiplier;
        
        // Apply skip/limit for distributed cracking
        $effectiveStart = $this->skip;
        $effectiveEnd = $this->limit > 0 ? min($this->skip + $this->limit, $totalWords) : $totalWords;
        $effectiveWords = $effectiveEnd - $effectiveStart;
        
        $this->printInfo("Starting attack with {$totalHashes} hash(es) and {$totalWords} password(s)");
        if ($this->skip > 0 || $this->limit > 0) {
            $this->printInfo("Range: {$effectiveStart} to {$effectiveEnd} ({$effectiveWords} passwords)");
        }
        if ($rulesMultiplier > 1) {
            $this->printInfo("Rules enabled: {$rulesMultiplier} mutations per password");
        }
        $this->printInfo("Using {$this->threads} thread(s)");
        
        $this->logger->info("Starting attack: {$totalHashes} hashes, {$totalWords} passwords, {$this->threads} threads");
        
        if ($this->threads > 1) {
            $this->sharedMemFile = tempnam(sys_get_temp_dir(), 'brutyf_found_');
            file_put_contents($this->sharedMemFile, json_encode([]));
        }
        
        if ($this->threads > 1 && $this->pcntlAvailable) {
            $this->crackHashesMultithreaded($hashes, $totalWords);
        } else {
            foreach ($hashes as $hashEntry) {
                $hashEntry = trim($hashEntry);
                if (empty($hashEntry)) continue;
                
                if ($this->session && $this->session->isHashCracked($hashEntry)) {
                    $this->printInfo("Skipping already cracked hash: {$hashEntry}");
                    continue;
                }
                
                [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
                $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
                
                $this->printInfo("Attacking: {$hash} (Type: {$type})");
                $this->logger->info("Attacking hash: {$hash}, type: {$type}");
                
                $startPosition = $this->session ? $this->session->getProgress($hashEntry) : $this->skip;
                $endPosition = $this->limit > 0 ? min($this->skip + $this->limit, $totalWords) : $totalWords;
                $password = $this->crackHashWithRange($hash, $type, $salt, $startPosition, $endPosition);
                
                if ($password !== null) {
                    $this->printSuccess("Password Found: {$password}");
                    $result = $username ? [$username => ['hash' => $hash, 'password' => $password]] : [$hash => $password];
                    $this->found[] = $result;
                    $this->stats->incrementFound();
                    $this->logger->info("Password found for {$hash}: {$password}");
                    $this->addToPotfile($hash, $password);
                    
                    if ($this->session) {
                        $this->session->addFound($hashEntry, $password);
                        $this->session->save();
                    }
                } else {
                    $this->printError("Password not found");
                    $this->logger->info("Password not found for {$hash}");
                }
                
                $this->stats->incrementHashes();
            }
        }
        
        if ($this->sharedMemFile && file_exists($this->sharedMemFile)) {
            unlink($this->sharedMemFile);
        }
    }
    
    private function parseHashEntry(string $entry): array
    {
        $hash = $entry;
        $salt = null;
        $username = null;
        
        if (preg_match('/^([^:]+):(.+)$/', $entry, $matches)) {
            $first = $matches[1];
            $second = $matches[2];
            
            if (HashIdentifier::identify($first) !== HashIdentifier::TYPE_UNKNOWN) {
                $hash = $first;
                $salt = $second;
            } else {
                $username = $first;
                
                if (strpos($second, ':') !== false) {
                    [$hash, $salt] = explode(':', $second, 2);
                } else {
                    $hash = $second;
                }
            }
        }
        
        return [$hash, $salt, $username];
    }
    
    private function crackHashesMultithreaded(array $hashes, int $totalWords): void
    {
        if (count($hashes) >= $this->threads) {
            $this->crackHashesMultithreadedByHash($hashes, $totalWords);
        } else {
            $this->crackHashesMultithreadedByWordlist($hashes, $totalWords);
        }
    }
    
    private function crackHashesMultithreadedByHash(array $hashes, int $totalWords): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'brutyf_');
        file_put_contents($tmpFile, serialize([]));
        
        $hashChunks = array_chunk($hashes, (int)ceil(count($hashes) / $this->threads));
        $childPids = [];
        
        foreach ($hashChunks as $chunkIndex => $hashChunk) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                $this->printError("Failed to create process for thread {$chunkIndex}");
                continue;
            } elseif ($pid == 0) {
                $childResults = [];
                
                foreach ($hashChunk as $hashEntry) {
                    $hashEntry = trim($hashEntry);
                    if (empty($hashEntry)) continue;
                    
                    if ($this->checkEarlyTermination($hashEntry)) {
                        continue;
                    }
                    
                    [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
                    $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
                    
                    echo "\n[Thread {$chunkIndex}] Attacking: {$hash} (Type: {$type})\n";
                    
                    $password = $this->crackHash($hash, $type, $salt, $totalWords);
                    
                    if ($password !== null) {
                        echo "\n[Thread {$chunkIndex}] ";
                        $this->printSuccess("Password Found: {$password}");
                        $childResults[] = [$hash => $password];
                        $this->markFoundInSharedMem($hashEntry);
                    } else {
                        echo "\n[Thread {$chunkIndex}] ";
                        $this->printError("Password not found");
                    }
                }
                
                $this->updateSharedResults($tmpFile, $childResults);
                exit(0);
            } else {
                $childPids[] = $pid;
            }
        }
        
        foreach ($childPids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        $allResults = unserialize(file_get_contents($tmpFile));
        foreach ($allResults as $result) {
            $this->found = array_merge($this->found, $result);
        }
        
        unlink($tmpFile);
    }
    
    private function crackHashesMultithreadedByWordlist(array $hashes, int $totalWords): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'brutyf_');
        file_put_contents($tmpFile, serialize([]));
        
        $childPids = [];
        $wordsPerThread = (int)ceil($totalWords / $this->threads);
        
        for ($threadId = 0; $threadId < $this->threads; $threadId++) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                $this->printError("Failed to create process for thread {$threadId}");
                continue;
            } elseif ($pid == 0) {
                $childResults = [];
                $startPos = $threadId * $wordsPerThread;
                $endPos = min($startPos + $wordsPerThread, $totalWords);
                
                echo "\n[Thread {$threadId}] Processing passwords {$startPos} to {$endPos}\n";
                
                foreach ($hashes as $hashEntry) {
                    $hashEntry = trim($hashEntry);
                    if (empty($hashEntry)) continue;
                    
                    if ($this->checkEarlyTermination($hashEntry)) {
                        continue;
                    }
                    
                    [$hash, $salt, $username] = $this->parseHashEntry($hashEntry);
                    $type = $this->hashType === 'auto' ? HashIdentifier::identify($hash) : $this->hashType;
                    
                    echo "\n[Thread {$threadId}] Attacking: {$hash}\n";
                    
                    $password = $this->crackHashWithRange($hash, $type, $salt, $startPos, $endPos);
                    
                    if ($password !== null) {
                        echo "\n[Thread {$threadId}] ";
                        $this->printSuccess("Password Found: {$password}");
                        $childResults[] = [$hash => $password];
                        $this->markFoundInSharedMem($hashEntry);
                    } else {
                        echo "\n[Thread {$threadId}] ";
                        $this->printError("Password not found in range {$startPos}-{$endPos}");
                    }
                }
                
                $this->updateSharedResults($tmpFile, $childResults);
                exit(0);
            } else {
                $childPids[] = $pid;
            }
        }
        
        foreach ($childPids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        $allResults = unserialize(file_get_contents($tmpFile));
        foreach ($allResults as $result) {
            $this->found = array_merge($this->found, $result);
        }
        
        unlink($tmpFile);
    }
    
    private function checkEarlyTermination(string $hash): bool
    {
        if ($this->sharedMemFile && file_exists($this->sharedMemFile)) {
            $found = json_decode(file_get_contents($this->sharedMemFile), true) ?? [];
            return in_array($hash, $found);
        }
        return false;
    }
    
    private function markFoundInSharedMem(string $hash): void
    {
        if ($this->sharedMemFile) {
            $fp = fopen($this->sharedMemFile, 'r+');
            if (flock($fp, LOCK_EX)) {
                $found = json_decode(file_get_contents($this->sharedMemFile), true) ?? [];
                $found[] = $hash;
                file_put_contents($this->sharedMemFile, json_encode($found));
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
    
    private function updateSharedResults(string $tmpFile, array $results): void
    {
        $fp = fopen($tmpFile, 'r+');
        if (flock($fp, LOCK_EX)) {
            $data = unserialize(file_get_contents($tmpFile));
            $data[] = $results;
            file_put_contents($tmpFile, serialize($data));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
    
    private function crackHash(string $hash, string $type, ?string $salt, int $totalWords, int $startPosition = 0): ?string
    {
        $handle = $this->openWordlist();
        if (!$handle) {
            $this->printError("Failed to open wordlist file");
            return null;
        }
        
        $i = 0;
        while ($i < $startPosition && fgets($handle) !== false) {
            $i++;
        }
        
        $batchSize = 1000;
        
        while (!feof($handle)) {
            if ($this->sharedMemFile && $this->checkEarlyTermination($hash)) {
                fclose($handle);
                return null;
            }
            
            $passwords = [];
            for ($j = 0; $j < $batchSize && !feof($handle); $j++) {
                $line = fgets($handle);
                if ($line !== false) {
                    $passwords[] = trim($line);
                }
            }
            
            foreach ($passwords as $basePassword) {
                $i++;
                
                $passwordsToTry = $this->ruleEngine 
                    ? $this->ruleEngine->applyRules($basePassword) 
                    : [$basePassword];
                
                foreach ($passwordsToTry as $password) {
                    if ($this->verbose) {
                        echo "Trying [{$i}/{$totalWords}]: {$password}\n";
                        usleep(10000);
                    }
                    
                    if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                        fclose($handle);
                        return $password;
                    }
                    
                    $this->stats->incrementPasswords();
                }
                
                if ($this->session && $i % 10000 === 0) {
                    $this->session->setProgress($hash, $i);
                    $this->session->save();
                }
                
                if ($i % 10000 === 0) {
                    usleep(1);
                }
            }
            
            $percentage = min(100, round(($i / $totalWords) * 100, 2));
            $this->printProgress($percentage, $i, $totalWords);
        }
        
        fclose($handle);
        return null;
    }
    
    private function crackHashWithRange(string $hash, string $type, ?string $salt, int $startPos, int $endPos): ?string
    {
        $handle = $this->openWordlist();
        if (!$handle) {
            $this->printError("Failed to open wordlist file");
            return null;
        }
        
        $i = 0;
        while ($i < $startPos && fgets($handle) !== false) {
            $i++;
        }
        
        $batchSize = 1000;
        
        while ($i < $endPos && !feof($handle)) {
            if ($this->sharedMemFile && $this->checkEarlyTermination($hash)) {
                fclose($handle);
                return null;
            }
            
            $passwords = [];
            for ($j = 0; $j < $batchSize && $i < $endPos && !feof($handle); $j++) {
                $line = fgets($handle);
                if ($line !== false) {
                    $passwords[] = trim($line);
                    $i++;
                }
            }
            
            foreach ($passwords as $basePassword) {
                $passwordsToTry = $this->ruleEngine 
                    ? $this->ruleEngine->applyRules($basePassword) 
                    : [$basePassword];
                
                foreach ($passwordsToTry as $password) {
                    if ($this->verbose) {
                        echo "Trying [{$i}/{$endPos}]: {$password}\n";
                        usleep(10000);
                    }
                    
                    if (HashIdentifier::verify($password, $hash, $type, $salt)) {
                        fclose($handle);
                        return $password;
                    }
                    
                    $this->stats->incrementPasswords();
                }
                
                if ($i % 10000 === 0) {
                    usleep(1);
                }
            }
            
            $rangeSize = $endPos - $startPos;
            $currentPos = $i - $startPos;
            $percentage = min(100, round(($currentPos / $rangeSize) * 100, 2));
            echo "Progress: {$percentage}% ({$currentPos}/{$rangeSize})\r";
        }
        
        fclose($handle);
        return null;
    }
    
    /**
     * @return resource|false
     */
    private function openWordlist()
    {
        $file = $this->wordlistFile;
        
        if (str_ends_with($file, '.gz')) {
            return gzopen($file, 'r');
        }
        
        if (str_ends_with($file, '.bz2')) {
            return bzopen($file, 'r');
        }
        
        return fopen($file, 'r');
    }
    
    private function handleResults(): void
    {
        if (empty($this->found)) {
            if (!$this->quiet) {
                echo "\nNo passwords were found.\n";
            }
            return;
        }
        
        if (!$this->quiet) {
            echo "\n" . count($this->found) . " password(s) found!\n";
        }
        
        if ($this->outputFile !== null) {
            $this->exportResults($this->outputFile, $this->outputFormat);
            return;
        }
        
        if ($this->quiet) {
            return;
        }
        
        $option = readline("Do you want to export results? (Y/n): ");
        
        if (strtolower($option) === 'y' || strtolower($option) === 'yes' || $option === '') {
            echo "Format (text/json/csv) [text]: ";
            $format = trim(readline()) ?: 'text';
            
            $filename = "brutyf_result_" . date("Y-m-d_H-i-s");
            $filename .= match($format) {
                'json' => '.json',
                'csv' => '.csv',
                default => '.txt',
            };
            
            $this->exportResults($filename, $format);
        }
        
        if ($this->session) {
            $this->session->clear();
        }
    }
    
    private function exportResults(string $filename, string $format): void
    {
        $content = match($format) {
            'json' => $this->formatResultsJson(),
            'csv' => $this->formatResultsCsv(),
            default => $this->formatResultsText(),
        };
        
        if (file_put_contents($filename, $content)) {
            $this->printInfo("Results saved to {$filename}");
            $this->logger->info("Results exported to {$filename}");
        } else {
            $this->printError("Failed to save results");
        }
    }
    
    private function formatResultsText(): string
    {
        $content = "[ BrutyF Result ]\n";
        $content .= "Generated: " . date("Y-m-d H:i:s") . "\n";
        $content .= "------------------------\n";
        
        foreach ($this->found as $item) {
            foreach ($item as $hash => $password) {
                if (is_array($password)) {
                    $content .= "{$hash}:{$password['hash']}:{$password['password']}\n";
                } else {
                    $content .= "{$hash}:{$password}\n";
                }
            }
        }
        
        $content .= "------------------------\n";
        $content .= "Total: " . count($this->found) . " password(s) found\n";
        
        return $content;
    }
    
    private function formatResultsJson(): string
    {
        $results = [
            'generated' => date('c'),
            'version' => self::VERSION,
            'total_found' => count($this->found),
            'results' => [],
        ];
        
        foreach ($this->found as $item) {
            foreach ($item as $hash => $password) {
                if (is_array($password)) {
                    $results['results'][] = [
                        'username' => $hash,
                        'hash' => $password['hash'],
                        'password' => $password['password'],
                    ];
                } else {
                    $results['results'][] = [
                        'hash' => $hash,
                        'password' => $password,
                    ];
                }
            }
        }
        
        return json_encode($results, JSON_PRETTY_PRINT);
    }
    
    private function formatResultsCsv(): string
    {
        $content = "hash,password,username\n";
        
        foreach ($this->found as $item) {
            foreach ($item as $hash => $password) {
                if (is_array($password)) {
                    $content .= "\"{$password['hash']}\",\"{$password['password']}\",\"{$hash}\"\n";
                } else {
                    $content .= "\"{$hash}\",\"{$password}\",\"\"\n";
                }
            }
        }
        
        return $content;
    }
    
    private function printStatistics(): void
    {
        if ($this->quiet) {
            return;
        }
        
        $report = $this->stats->getReport();
        
        echo "\n";
        echo "=== Statistics ===\n";
        echo "Time elapsed:     {$report['elapsed_time_formatted']}\n";
        echo "Passwords tried:  " . number_format($report['passwords_tried']) . "\n";
        echo "Passwords found:  {$report['passwords_found']}\n";
        echo "Speed:            {$report['speed_formatted']}\n";
        echo "Peak memory:      {$report['peak_memory_formatted']}\n";
        echo "==================\n";
        
        $this->logger->info("Attack completed. Stats: " . json_encode($report));
    }
    
    private function printProgress(float $percentage, int $current, int $total): void
    {
        if ($this->quiet) {
            return;
        }
        
        $speed = $this->stats->getSpeed();
        $eta = $this->stats->getETA($total);
        $speedStr = number_format($speed, 0);
        
        echo "Progress: {$percentage}% ({$current}/{$total}) | Speed: {$speedStr} p/s | ETA: {$eta}    \r";
    }
    
    private function readHashFile(): array
    {
        if ($this->hashFile === '-' || $this->hashFile === 'stdin') {
            $content = file_get_contents('php://stdin');
            return explode(PHP_EOL, trim($content));
        }
        
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
        if (str_ends_with($file, '.gz')) {
            $handle = gzopen($file, 'r');
            $count = 0;
            while (!gzeof($handle)) {
                $count += substr_count(gzread($handle, 8192), "\n");
            }
            gzclose($handle);
            return $count + 1;
        }
        
        $lineCount = 0;
        $handle = fopen($file, 'rb');
        
        if (!$handle) {
            return 0;
        }
        
        while (!feof($handle)) {
            $lineCount += substr_count(fread($handle, 8192), "\n");
        }
        
        fclose($handle);
        return $lineCount + 1;
    }
    
    private function printBanner(): void
    {
        if ($this->quiet) {
            return;
        }
        
        $color = $this->useColor ? "\e[1;31m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "{$color}
-------------------------------------
  ____             _         ______ 
 |  _ \           | |       |  ____|
 | |_) |_ __ _   _| |_ _   _| |__   
 |  _ <| '__| | | | __| | | |  __|  
 | |_) | |  | |_| | |_| |_| | |     
 |____/|_|   \__,_|\__|\__, |_|     
                        __/ |       
                       |___/ v" . self::VERSION . "
-------------------------------------{$reset}
";
    }
    
    public function help(): void
    {
        global $argv;
        $script = basename($argv[0]);
        
        echo "Usage:\n";
        echo "  {$script} -f=<hashfile> -w=<wordlist> [options]\n";
        echo "  {$script} -f=<hashfile> -m=<mask> [options]\n";
        echo "  {$script} -f=<hashfile> -w=<wordlist> -m=<mask> [options]  (hybrid)\n";
        echo "  {$script} -f=<hashfile> -w=<wordlist1> --wordlist2=<wordlist2> [options]  (combinator)\n\n";
        
        echo "Required:\n";
        echo "  -f, --hashfile=FILE     File containing hashed passwords (use '-' for stdin)\n";
        echo "  -w, --wordlist=FILE     Wordlist file (supports .gz, .bz2)\n";
        echo "  -m, --mask=MASK         Mask for brute-force mode\n\n";
        
        echo "Attack Modes:\n";
        echo "  --attack-mode=MODE      Attack mode: wordlist, mask, hybrid, combinator\n";
        echo "  --wordlist2=FILE        Second wordlist for combinator attack\n\n";
        
        echo "Options:\n";
        echo "  -t, --threads=NUM       Number of threads (1-16, default: 1)\n";
        echo "  -H, --hash-type=TYPE    Hash type (auto, md5, sha1, sha256, sha512, bcrypt, ntlm, mysql, mysql5)\n";
        echo "  -r, --resume            Resume previous session\n";
        echo "  -v, --verbose           Show detailed progress\n";
        echo "  -q, --quiet             Suppress output (for scripting)\n";
        echo "  --no-color              Disable colored output\n";
        echo "  --rules=RULES           Comma-separated mutation rules\n";
        echo "  -o, --output=FILE       Output file for results\n";
        echo "  --format=FORMAT         Output format (text, json, csv)\n";
        echo "  --log=FILE              Log file path\n";
        echo "  --log-level=LEVEL       Log level (debug, info, warning, error)\n\n";
        
        echo "Potfile Options:\n";
        echo "  --potfile=FILE          Custom potfile path (default: ~/.brutyf.pot)\n";
        echo "  --no-potfile            Disable potfile\n";
        echo "  --show-pot              Show cracked hashes from potfile\n";
        echo "  --analyze               Analyze cracked passwords from potfile\n\n";
        
        echo "Distributed Cracking:\n";
        echo "  --skip=N                Skip first N passwords in wordlist\n";
        echo "  --limit=N               Process only N passwords\n\n";
        
        echo "Utilities:\n";
        echo "  --identify              Identify hash types in file\n";
        echo "  --extract=FILE          Extract hashes from file (shadow, htpasswd, etc.)\n";
        echo "  --extract-format=FMT    Format: auto, shadow, passwd, htpasswd, pwdump, csv\n";
        echo "  --generate=PASS         Generate hash from password (or file of passwords)\n";
        echo "  --generate-type=TYPE    Hash type for generation (default: md5, use 'all' for all)\n";
        echo "  --wordlist-info=FILE    Show wordlist statistics\n\n";
        
        echo "Incremental Mode:\n";
        echo "  --incremental           Enable incremental (brute-force) mode\n";
        echo "  --increment-min=N       Minimum password length (default: 1)\n";
        echo "  --increment-max=N       Maximum password length (default: 8)\n";
        echo "  --charset=CHARS         Custom charset for incremental/mask mode\n";
        echo "  --charset1-4=CHARS      Custom charsets for ?1 ?2 ?3 ?4 in masks\n\n";
        
        echo "Monitoring:\n";
        echo "  --status-file=FILE      Write progress to file (JSON) for external monitoring\n";
        echo "  --json                  Output in JSON format (for utilities)\n\n";
        
        echo "REST API Server:\n";
        echo "  --server                Start REST API server\n";
        echo "  --server-host=HOST      Server host (default: 127.0.0.1)\n";
        echo "  --server-port=PORT      Server port (default: 8080)\n";
        echo "  --api-key=KEY           Require API key for requests\n\n";
        
        echo "Webhooks:\n";
        echo "  --webhook=URL           Send notifications to webhook URL\n";
        echo "                          (Supports Discord, Slack, and generic webhooks)\n\n";
        
        echo "Markov Attack:\n";
        echo "  --markov                Enable Markov chain attack mode\n";
        echo "  --markov-train=FILE     Train Markov model from wordlist\n";
        echo "  --markov-load=FILE      Load pre-trained Markov model\n";
        echo "  --markov-save=FILE      Save trained model to file\n";
        echo "  --markov-order=N        Markov chain order (default: 2)\n\n";
        
        echo "Keyboard Walk:\n";
        echo "  --keyboard-walk         Enable keyboard walk attack mode\n\n";
        
        echo "Dictionary Generator:\n";
        echo "  --dict-gen              Generate custom wordlist\n";
        echo "  --dict-theme=THEME      Use theme: sports, animals, colors, tech, love, music, gaming, names\n";
        echo "  --dict-words=FILE       Base words file\n";
        echo "  --dict-no-leet          Disable leet speak variations\n";
        echo "  --dict-no-years         Disable year suffixes\n";
        echo "  --dict-year-start=YEAR  Start year for suffixes (default: 1970)\n";
        echo "  --dict-year-end=YEAR    End year for suffixes (default: 2030)\n\n";
        
        echo "GPU Acceleration:\n";
        echo "  --gpu                   Enable GPU acceleration (requires hashcat)\n";
        echo "  --gpu-devices=IDS       GPU device IDs to use (e.g., 1,2)\n";
        echo "  --gpu-workload=N        Workload profile: 1=low, 2=default, 3=high, 4=nightmare\n";
        echo "  --gpu-info              Show GPU status and available devices\n";
        echo "  --gpu-benchmark         Run GPU benchmark\n\n";
        
        echo "Other:\n";
        echo "  --version               Show version number\n";
        echo "  --clear-pot             Clear the potfile\n";
        echo "  -b, --benchmark         Run hash speed benchmark\n";
        echo "  -a, --about             Show information about BrutyF\n";
        echo "  -h, --help              Show this help message\n\n";
        
        echo "Mask Characters:\n";
        echo "  ?l  Lowercase letters (a-z)\n";
        echo "  ?u  Uppercase letters (A-Z)\n";
        echo "  ?d  Digits (0-9)\n";
        echo "  ?s  Special characters\n";
        echo "  ?a  All printable characters\n\n";
        
        echo "Available Rules:\n";
        echo "  " . implode(', ', RuleEngine::getDefaultRules()) . "\n\n";
        
        echo "Hash Types:\n";
        echo "  " . implode(', ', HashIdentifier::getAllTypes()) . "\n\n";
        
        echo "Examples:\n";
        echo "  {$script} -f=hash.txt -w=passwords.txt -t=4\n";
        echo "  {$script} -f=hash.txt -w=rockyou.txt.gz -H=md5 --rules=leet,append_123\n";
        echo "  {$script} -f=hash.txt -m='?l?l?l?l?d?d' -t=8\n";
        echo "  {$script} -f=hash.txt -w=words.txt -m='?d?d?d' (hybrid: word + 3 digits)\n";
        echo "  {$script} -f=hash.txt -w=first.txt --wordlist2=last.txt (combinator)\n";
        echo "  {$script} -f=hash.txt --incremental --increment-min=4 --increment-max=6\n";
        echo "  {$script} --generate=password --generate-type=all\n";
        echo "  {$script} --wordlist-info=rockyou.txt\n";
        echo "  {$script} --benchmark\n";
        echo "  {$script} --identify -f=hash.txt\n";
        echo "  {$script} --analyze --json\n";
        echo "  {$script} -f=hash.txt -w=rockyou.txt --gpu (GPU accelerated)\n";
        echo "  {$script} --gpu-info (check GPU status)\n";
        echo "-----------------------------------------------------\n";
    }
    
    public function about(): void
    {
        echo "Name: BrutyF\n";
        echo "Version: " . self::VERSION . "\n";
        echo "Developed by: " . self::AUTHOR . "\n";
        echo "Email: " . self::EMAIL . "\n\n";
        echo "Supported Hash Types:\n";
        foreach (HashIdentifier::getAllTypes() as $type) {
            echo "  - {$type}\n";
        }
    }
    
    private function printSuccess(string $message): void
    {
        if ($this->quiet) return;
        
        $green = $this->useColor ? "\e[1;32m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "{$green}-------------------------------\n{$message}\n-------------------------------\n{$reset}";
    }
    
    private function printError(string $message): void
    {
        if ($this->quiet) return;
        
        $red = $this->useColor ? "\e[1;31m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "{$red}-------------------------------\n{$message}\n-------------------------------\n{$reset}";
        $this->logger->error($message);
    }
    
    private function printWarning(string $message): void
    {
        if ($this->quiet) return;
        
        $yellow = $this->useColor ? "\e[1;33m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "{$yellow}[WARNING] {$message}{$reset}\n";
        $this->logger->warning($message);
    }
    
    private function printInfo(string $message): void
    {
        if ($this->quiet) return;
        
        $cyan = $this->useColor ? "\e[1;36m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "{$cyan}[*] {$message}{$reset}\n";
    }
}
