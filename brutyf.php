#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace BrutyF;

// Autoload classes from src directory
spl_autoload_register(function ($class) {
    $prefix = 'BrutyF\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// CLI entry point
(function() {
    $short_options = "f:w:m:t:H:o:vqrhab";
    $long_options = [
        "wordlist:",
        "wordlist2:",
        "hashfile:",
        "mask:",
        "verbose",
        "quiet",
        "about",
        "threads:",
        "hash-type:",
        "resume",
        "no-color",
        "rules:",
        "output:",
        "format:",
        "log:",
        "log-level:",
        "help",
        "benchmark",
        "attack-mode:",
        "potfile:",
        "no-potfile",
        "show-pot",
        "clear-pot",
        "identify",
        "extract:",
        "extract-format:",
        "analyze",
        "skip:",
        "limit:",
        "version",
        "generate:",
        "generate-type:",
        "generate-salt:",
        "wordlist-info:",
        "incremental",
        "increment-min:",
        "increment-max:",
        "charset:",
        "charset1:",
        "charset2:",
        "charset3:",
        "charset4:",
        "status-file:",
        "json",
        // New features v3.4
        "server",
        "server-host:",
        "server-port:",
        "api-key:",
        "webhook:",
        "markov",
        "markov-file:",
        "markov-order:",
        "markov-train:",
        "markov-save:",
        "markov-load:",
        "keyboard-walk",
        "dict-gen",
        "dict-theme:",
        "dict-words:",
        "dict-no-leet",
        "dict-no-years",
        "dict-no-numbers",
        "dict-year-start:",
        "dict-year-end:",
    ];
    $options = getopt($short_options, $long_options);
    
    $color = !isset($options["no-color"]);
    $jsonOutput = isset($options["json"]);
    
    // Show version
    if (isset($options["version"])) {
        echo "BrutyF v3.4\n";
        exit(0);
    }
    
    // Show help
    if (isset($options["help"]) || isset($options["h"])) {
        $brutyf = new BrutyF();
        $brutyf->help();
        exit(0);
    }
    
    // Show about
    if (isset($options["about"]) || isset($options["a"])) {
        $brutyf = new BrutyF();
        $brutyf->about();
        exit(0);
    }
    
    // Run benchmark
    if (isset($options["benchmark"]) || isset($options["b"])) {
        $brutyf = new BrutyF(['color' => $color]);
        $brutyf->benchmark();
        exit(0);
    }
    
    // Start REST API server
    if (isset($options["server"])) {
        $host = $options["server-host"] ?? '127.0.0.1';
        $port = (int)($options["server-port"] ?? 8080);
        $apiKey = $options["api-key"] ?? null;
        
        $server = new ApiServer($host, $port, $color, $apiKey);
        $server->start();
        exit(0);
    }
    
    // Keyboard walk attack mode
    if (isset($options["keyboard-walk"])) {
        $hashFile = $options["hashfile"] ?? $options["f"] ?? '';
        $outputFile = $options["output"] ?? $options["o"] ?? null;
        
        if (empty($hashFile)) {
            // Just generate patterns
            $generator = new KeyboardWalkGenerator(
                (int)($options["increment-min"] ?? 4),
                (int)($options["increment-max"] ?? 12)
            );
            
            $patterns = $generator->getPatterns();
            
            if ($jsonOutput) {
                echo json_encode(['count' => count($patterns), 'patterns' => $patterns], JSON_PRETTY_PRINT) . "\n";
            } else {
                $cyan = $color ? "\e[1;36m" : "";
                $reset = $color ? "\e[0m" : "";
                echo "{$cyan}=== Keyboard Walk Patterns ==={$reset}\n";
                echo "Generated: " . count($patterns) . " patterns\n\n";
                foreach ($patterns as $p) {
                    echo "{$p}\n";
                }
            }
            
            if ($outputFile) {
                file_put_contents($outputFile, implode("\n", $patterns) . "\n");
                echo "\nSaved to: {$outputFile}\n";
            }
            exit(0);
        }
    }
    
    // Dictionary generator
    if (isset($options["dict-gen"])) {
        $outputFile = $options["output"] ?? $options["o"] ?? null;
        $theme = $options["dict-theme"] ?? null;
        $wordsFile = $options["dict-words"] ?? null;
        
        if ($theme) {
            $generator = DictionaryGenerator::fromTheme($theme);
        } else {
            $generator = new DictionaryGenerator();
        }
        
        if ($wordsFile && file_exists($wordsFile)) {
            $generator->addBaseWordsFromFile($wordsFile);
        }
        
        $generator->includeLeet(!isset($options["dict-no-leet"]));
        $generator->includeYears(!isset($options["dict-no-years"]));
        $generator->includeNumbers(!isset($options["dict-no-numbers"]));
        
        if (isset($options["dict-year-start"])) {
            $generator->setYearRange(
                (int)$options["dict-year-start"],
                (int)($options["dict-year-end"] ?? 2030)
            );
        }
        
        $passwords = $generator->generate();
        
        if ($jsonOutput) {
            echo json_encode(['count' => count($passwords), 'passwords' => $passwords], JSON_PRETTY_PRINT) . "\n";
        } else {
            $cyan = $color ? "\e[1;36m" : "";
            $reset = $color ? "\e[0m" : "";
            echo "{$cyan}=== Dictionary Generator ==={$reset}\n";
            echo "Generated: " . count($passwords) . " passwords\n\n";
            
            if (!$outputFile) {
                // Show first 50 if no output file
                $show = array_slice($passwords, 0, 50);
                foreach ($show as $p) {
                    echo "{$p}\n";
                }
                if (count($passwords) > 50) {
                    echo "... and " . (count($passwords) - 50) . " more\n";
                }
            }
        }
        
        if ($outputFile) {
            file_put_contents($outputFile, implode("\n", $passwords) . "\n");
            echo "Saved to: {$outputFile}\n";
        } elseif (!$jsonOutput) {
            echo "\nUse -o=<file> to save all passwords to a file.\n";
            echo "Available themes: " . implode(', ', DictionaryGenerator::getAvailableThemes()) . "\n";
        }
        exit(0);
    }
    
    // Markov chain training/generation
    if (isset($options["markov-train"])) {
        $trainFile = $options["markov-train"];
        $saveFile = $options["markov-save"] ?? null;
        $order = (int)($options["markov-order"] ?? 2);
        
        if (!file_exists($trainFile)) {
            echo "Training file not found: {$trainFile}\n";
            exit(1);
        }
        
        $cyan = $color ? "\e[1;36m" : "";
        $reset = $color ? "\e[0m" : "";
        
        echo "{$cyan}=== Markov Chain Training ==={$reset}\n";
        echo "Training from: {$trainFile}\n";
        echo "Order: {$order}\n";
        
        $generator = new MarkovGenerator($order);
        $generator->trainFromFile($trainFile);
        
        echo "Chains: " . $generator->getChainCount() . "\n";
        echo "Start states: " . $generator->getStartCount() . "\n";
        
        if ($saveFile) {
            $generator->save($saveFile);
            echo "Saved model to: {$saveFile}\n";
        }
        
        // Generate some samples
        echo "\nSample passwords:\n";
        $samples = $generator->generateMultiple(20);
        foreach ($samples as $s) {
            echo "  {$s}\n";
        }
        exit(0);
    }
    
    // Clear potfile
    if (isset($options["clear-pot"])) {
        $potfile = new Potfile($options["potfile"] ?? null);
        if ($potfile->clear()) {
            echo "Potfile cleared.\n";
        } else {
            echo "Failed to clear potfile.\n";
        }
        exit(0);
    }
    
    // Show potfile contents
    if (isset($options["show-pot"])) {
        $potfile = new Potfile($options["potfile"] ?? null);
        $cracked = $potfile->getAll();
        if (empty($cracked)) {
            echo "Potfile is empty.\n";
        } else {
            echo "Potfile: {$potfile->getPath()}\n";
            echo "Total: " . count($cracked) . " cracked hash(es)\n\n";
            foreach ($cracked as $hash => $password) {
                echo "{$hash}:{$password}\n";
            }
        }
        exit(0);
    }
    
    // Identify hashes
    if (isset($options["identify"])) {
        $hashFile = $options["hashfile"] ?? $options["f"] ?? '';
        $color = !isset($options["no-color"]);
        
        if (empty($hashFile)) {
            echo "Usage: brutyf.php --identify -f=<hashfile>\n";
            exit(1);
        }
        
        if (!file_exists($hashFile)) {
            echo "File not found: {$hashFile}\n";
            exit(1);
        }
        
        $hashes = file($hashFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cyan = $color ? "\e[1;36m" : "";
        $green = $color ? "\e[1;32m" : "";
        $yellow = $color ? "\e[1;33m" : "";
        $reset = $color ? "\e[0m" : "";
        
        echo "{$cyan}=== Hash Identification ==={$reset}\n\n";
        
        foreach ($hashes as $hash) {
            $hash = trim($hash);
            if (empty($hash)) continue;
            
            $results = HashIdentifier::identifyWithConfidence($hash);
            $display = strlen($hash) > 50 ? substr($hash, 0, 47) . '...' : $hash;
            
            echo "{$green}Hash:{$reset} {$display}\n";
            foreach ($results as $result) {
                $conf = $result['confidence'];
                $confColor = $conf >= 80 ? $green : ($conf >= 50 ? $yellow : $reset);
                echo "  {$confColor}[{$conf}%]{$reset} {$result['info']['name']} - {$result['info']['description']}\n";
                echo "       Security: {$result['info']['security']}\n";
            }
            echo "\n";
        }
        exit(0);
    }
    
    // Extract hashes from file
    if (isset($options["extract"])) {
        $inputFile = $options["extract"];
        $format = $options["extract-format"] ?? 'auto';
        $outputFile = $options["output"] ?? $options["o"] ?? null;
        $color = !isset($options["no-color"]);
        
        if (!file_exists($inputFile)) {
            echo "File not found: {$inputFile}\n";
            exit(1);
        }
        
        $extractor = new HashExtractor();
        $extracted = $extractor->extractFromFile($inputFile, $format);
        
        $cyan = $color ? "\e[1;36m" : "";
        $green = $color ? "\e[1;32m" : "";
        $reset = $color ? "\e[0m" : "";
        
        echo "{$cyan}=== Hash Extraction ==={$reset}\n";
        echo "File: {$inputFile}\n";
        echo "Format: {$format}\n";
        echo "Found: " . count($extracted) . " hash(es)\n\n";
        
        foreach ($extracted as $entry) {
            $user = $entry['username'] ?? '';
            $hash = $entry['hash'];
            $type = $entry['hash_type'];
            
            if ($user) {
                echo "{$green}{$user}{$reset}:{$hash} [{$type}]\n";
            } else {
                echo "{$hash} [{$type}]\n";
            }
        }
        
        if ($outputFile) {
            $output = implode("\n", $extractor->getFormattedOutput());
            file_put_contents($outputFile, $output . "\n");
            echo "\n{$cyan}Saved to: {$outputFile}{$reset}\n";
        }
        
        exit(0);
    }
    
    // Analyze passwords from potfile
    if (isset($options["analyze"])) {
        $potfile = new Potfile($options["potfile"] ?? null);
        $cracked = $potfile->getAll();
        
        if (empty($cracked)) {
            echo "No cracked passwords to analyze. Run some attacks first.\n";
            exit(1);
        }
        
        $analyzer = new PasswordAnalyzer(array_values($cracked));
        if ($jsonOutput) {
            echo json_encode($analyzer->analyze(), JSON_PRETTY_PRINT) . "\n";
        } else {
            echo $analyzer->getReport($color);
        }
        exit(0);
    }
    
    // Generate hashes
    if (isset($options["generate"])) {
        $input = $options["generate"];
        $type = $options["generate-type"] ?? 'md5';
        $salt = $options["generate-salt"] ?? null;
        $outputFile = $options["output"] ?? $options["o"] ?? null;
        
        $results = [];
        
        // Check if input is a file or a password
        if (file_exists($input)) {
            $passwords = file($input, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($passwords as $password) {
                $hash = HashGenerator::generate($password, $type, $salt);
                if ($hash) {
                    $results[] = ['password' => $password, 'hash' => $hash, 'type' => $type];
                }
            }
        } else {
            // Single password
            if ($type === 'all') {
                $hashes = HashGenerator::generateAll($input);
                foreach ($hashes as $t => $h) {
                    $results[] = ['password' => $input, 'hash' => $h, 'type' => $t];
                }
            } else {
                $hash = HashGenerator::generate($input, $type, $salt);
                if ($hash) {
                    $results[] = ['password' => $input, 'hash' => $hash, 'type' => $type];
                }
            }
        }
        
        if ($jsonOutput) {
            echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
        } else {
            $cyan = $color ? "\e[1;36m" : "";
            $green = $color ? "\e[1;32m" : "";
            $reset = $color ? "\e[0m" : "";
            
            echo "{$cyan}=== Hash Generator ==={$reset}\n\n";
            foreach ($results as $r) {
                echo "{$green}{$r['type']}:{$reset} {$r['hash']}\n";
            }
        }
        
        if ($outputFile) {
            $output = implode("\n", array_map(fn($r) => $r['hash'], $results));
            file_put_contents($outputFile, $output . "\n");
            if (!$jsonOutput) {
                echo "\nSaved to: {$outputFile}\n";
            }
        }
        
        exit(0);
    }
    
    // Wordlist info/stats
    if (isset($options["wordlist-info"])) {
        $file = $options["wordlist-info"];
        
        if (!file_exists($file)) {
            echo "File not found: {$file}\n";
            exit(1);
        }
        
        $analyzer = new WordlistAnalyzer($file);
        $stats = $analyzer->analyze();
        
        if ($jsonOutput) {
            echo json_encode($stats, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo $analyzer->getReport($color);
        }
        exit(0);
    }
    
    // Parse rules
    $rules = [];
    if (isset($options["rules"])) {
        $rules = explode(',', $options["rules"]);
    }
    
    // Determine attack mode
    $attackMode = $options["attack-mode"] ?? 'wordlist';
    $wordlist = $options["wordlist"] ?? $options["w"] ?? '';
    $wordlist2 = $options["wordlist2"] ?? '';
    $mask = $options["mask"] ?? $options["m"] ?? '';
    $incremental = isset($options["incremental"]);
    
    // Check for markov and keyboard-walk attack modes
    $markovMode = isset($options["markov"]);
    $keyboardWalkMode = isset($options["keyboard-walk"]);
    
    // Auto-detect attack mode if not specified
    if ($attackMode === 'wordlist') {
        if ($markovMode) {
            $attackMode = 'markov';
        } elseif ($keyboardWalkMode) {
            $attackMode = 'keyboard-walk';
        } elseif ($incremental) {
            $attackMode = 'incremental';
        } elseif (!empty($wordlist) && !empty($mask)) {
            $attackMode = 'hybrid';
        } elseif (!empty($wordlist) && !empty($wordlist2)) {
            $attackMode = 'combinator';
        } elseif (!empty($mask) && empty($wordlist)) {
            $attackMode = 'mask';
        }
    }
    
    // Parse custom charsets
    $customCharsets = [];
    if (isset($options["charset"])) {
        $customCharsets['default'] = $options["charset"];
    }
    for ($i = 1; $i <= 4; $i++) {
        if (isset($options["charset{$i}"])) {
            $customCharsets[$i] = $options["charset{$i}"];
        }
    }
    
    // Build options array
    $config = [
        'hashfile' => $options["hashfile"] ?? $options["f"] ?? '',
        'wordlist' => $wordlist,
        'wordlist2' => $wordlist2,
        'mask' => $mask,
        'verbose' => isset($options["verbose"]) || isset($options["v"]),
        'quiet' => isset($options["quiet"]) || isset($options["q"]),
        'color' => $color,
        'threads' => (int)($options["threads"] ?? $options["t"] ?? 1),
        'hash_type' => $options["hash-type"] ?? $options["H"] ?? 'auto',
        'resume' => isset($options["resume"]) || isset($options["r"]),
        'rules' => $rules,
        'output_file' => $options["output"] ?? $options["o"] ?? null,
        'output_format' => $options["format"] ?? 'text',
        'log_file' => $options["log"] ?? null,
        'log_level' => $options["log-level"] ?? 'info',
        'attack_mode' => $attackMode,
        'potfile' => !isset($options["no-potfile"]),
        'potfile_path' => $options["potfile"] ?? null,
        'skip' => isset($options["skip"]) ? (int)$options["skip"] : 0,
        'limit' => isset($options["limit"]) ? (int)$options["limit"] : 0,
        'increment_min' => isset($options["increment-min"]) ? (int)$options["increment-min"] : 1,
        'increment_max' => isset($options["increment-max"]) ? (int)$options["increment-max"] : 8,
        'custom_charsets' => $customCharsets,
        'status_file' => $options["status-file"] ?? null,
        'json_output' => $jsonOutput,
        'webhook' => $options["webhook"] ?? null,
        'markov_file' => $options["markov-file"] ?? $options["markov-load"] ?? null,
        'markov_order' => isset($options["markov-order"]) ? (int)$options["markov-order"] : 2,
    ];
    
    $brutyf = new BrutyF($config);
    $brutyf->run();
})();
