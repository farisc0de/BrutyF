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
    $short_options = "f:w:m:t:H:o:vqrha";
    $long_options = [
        "wordlist:",
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
    ];
    $options = getopt($short_options, $long_options);
    
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
    
    // Parse rules
    $rules = [];
    if (isset($options["rules"])) {
        $rules = explode(',', $options["rules"]);
    }
    
    // Build options array
    $config = [
        'hashfile' => $options["hashfile"] ?? $options["f"] ?? '',
        'wordlist' => $options["wordlist"] ?? $options["w"] ?? '',
        'mask' => $options["mask"] ?? $options["m"] ?? '',
        'verbose' => isset($options["verbose"]) || isset($options["v"]),
        'quiet' => isset($options["quiet"]) || isset($options["q"]),
        'color' => !isset($options["no-color"]),
        'threads' => (int)($options["threads"] ?? $options["t"] ?? 1),
        'hash_type' => $options["hash-type"] ?? $options["H"] ?? 'auto',
        'resume' => isset($options["resume"]) || isset($options["r"]),
        'rules' => $rules,
        'output_file' => $options["output"] ?? $options["o"] ?? null,
        'output_format' => $options["format"] ?? 'text',
        'log_file' => $options["log"] ?? null,
        'log_level' => $options["log-level"] ?? 'info',
    ];
    
    $brutyf = new BrutyF($config);
    $brutyf->run();
})();
