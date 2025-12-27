<?php
declare(strict_types=1);

namespace BrutyF;

/**
 * Simple REST API server for BrutyF
 * Uses PHP's built-in server
 */
class ApiServer
{
    private string $host;
    private int $port;
    private bool $useColor;
    private ?string $apiKey;
    private array $jobs = [];
    private string $jobsFile;
    
    public function __construct(string $host = '127.0.0.1', int $port = 8080, bool $useColor = true, ?string $apiKey = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->useColor = $useColor;
        $this->apiKey = $apiKey;
        $this->jobsFile = sys_get_temp_dir() . '/brutyf_jobs.json';
        $this->loadJobs();
    }
    
    public function start(): void
    {
        $cyan = $this->useColor ? "\e[1;36m" : "";
        $green = $this->useColor ? "\e[1;32m" : "";
        $yellow = $this->useColor ? "\e[1;33m" : "";
        $reset = $this->useColor ? "\e[0m" : "";
        
        echo "{$cyan}=== BrutyF REST API Server ==={$reset}\n\n";
        echo "{$green}Server:{$reset} http://{$this->host}:{$this->port}\n";
        if ($this->apiKey) {
            echo "{$yellow}API Key:{$reset} Required (use X-API-Key header)\n";
        }
        echo "\n{$cyan}Endpoints:{$reset}\n";
        echo "  POST   /api/crack          Start a cracking job\n";
        echo "  GET    /api/jobs           List all jobs\n";
        echo "  GET    /api/jobs/{id}      Get job status\n";
        echo "  DELETE /api/jobs/{id}      Cancel a job\n";
        echo "  POST   /api/identify       Identify hash types\n";
        echo "  POST   /api/generate       Generate hashes\n";
        echo "  GET    /api/potfile        Get potfile contents\n";
        echo "  GET    /api/benchmark      Run benchmark\n";
        echo "  GET    /api/status         Server status\n";
        echo "\nPress Ctrl+C to stop the server.\n\n";
        
        // Create router script
        $routerScript = $this->createRouterScript();
        
        // Start PHP built-in server
        $command = sprintf(
            'php -S %s:%d %s',
            escapeshellarg($this->host),
            $this->port,
            escapeshellarg($routerScript)
        );
        
        passthru($command);
    }
    
    private function createRouterScript(): string
    {
        $scriptPath = sys_get_temp_dir() . '/brutyf_router.php';
        $baseDir = dirname(__DIR__);
        $apiKeyVal = $this->apiKey ? "'{$this->apiKey}'" : 'null';
        
        $script = '<?php
declare(strict_types=1);

// Autoload
spl_autoload_register(function ($class) {
    $prefix = \'BrutyF\\\\\';  
    $baseDir = \'' . $baseDir . '/src/\';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace(\'\\\\\', \'/\', $relativeClass) . \'.php\';
    
    if (file_exists($file)) {
        require $file;
    }
});

use BrutyF\\HashIdentifier;
use BrutyF\\HashGenerator;
use BrutyF\\Potfile;
use BrutyF\\Benchmark;

$apiKey = ' . $apiKeyVal . ';
$jobsFile = sys_get_temp_dir() . \'/brutyf_jobs.json\';
$brutyfPath = \'' . $baseDir . '/brutyf.php\';

// CORS headers
header(\'Access-Control-Allow-Origin: *\');
header(\'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS\');
header(\'Access-Control-Allow-Headers: Content-Type, X-API-Key\');
header(\'Content-Type: application/json\');

// Handle preflight
if ($_SERVER[\'REQUEST_METHOD\'] === \'OPTIONS\') {
    http_response_code(200);
    exit;
}

// API Key check
if ($apiKey !== null) {
    $providedKey = $_SERVER[\'HTTP_X_API_KEY\'] ?? \'\';
    if ($providedKey !== $apiKey) {
        http_response_code(401);
        echo json_encode([\'error\' => \'Invalid or missing API key\']);
        exit;
    }
}

$uri = parse_url($_SERVER[\'REQUEST_URI\'], PHP_URL_PATH);
$method = $_SERVER[\'REQUEST_METHOD\'];

// Load jobs
$jobs = [];
if (file_exists($jobsFile)) {
    $jobs = json_decode(file_get_contents($jobsFile), true) ?: [];
}

function saveJobs($jobs, $file) {
    file_put_contents($file, json_encode($jobs, JSON_PRETTY_PRINT));
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Routes
switch (true) {
    case $uri === \'/api/status\' && $method === \'GET\':
        jsonResponse([
            \'status\' => \'running\',
            \'version\' => \'3.4\',
            \'jobs_count\' => count($jobs),
            \'uptime\' => time(),
        ]);
        break;
    
    case $uri === \'/api/identify\' && $method === \'POST\':
        $input = json_decode(file_get_contents(\'php://input\'), true);
        $hashes = $input[\'hashes\'] ?? [];
        if (empty($hashes)) {
            jsonResponse([\'error\' => \'No hashes provided\'], 400);
        }
        $results = [];
        foreach ((array)$hashes as $hash) {
            $results[$hash] = HashIdentifier::identifyWithConfidence($hash);
        }
        jsonResponse([\'results\' => $results]);
        break;
    
    case $uri === \'/api/generate\' && $method === \'POST\':
        $input = json_decode(file_get_contents(\'php://input\'), true);
        $password = $input[\'password\'] ?? \'\';
        $type = $input[\'type\'] ?? \'all\';
        if (empty($password)) {
            jsonResponse([\'error\' => \'No password provided\'], 400);
        }
        if ($type === \'all\') {
            $hashes = HashGenerator::generateAll($password);
        } else {
            $hash = HashGenerator::generate($password, $type);
            $hashes = $hash ? [$type => $hash] : [];
        }
        jsonResponse([\'password\' => $password, \'hashes\' => $hashes]);
        break;
    
    case $uri === \'/api/potfile\' && $method === \'GET\':
        $potfile = new Potfile();
        jsonResponse([
            \'path\' => $potfile->getPath(),
            \'count\' => $potfile->count(),
            \'cracked\' => $potfile->getAll(),
        ]);
        break;
    
    case $uri === \'/api/benchmark\' && $method === \'GET\':
        $benchmark = new Benchmark();
        $results = $benchmark->runAndReturn();
        jsonResponse([\'results\' => $results]);
        break;
    
    case $uri === \'/api/jobs\' && $method === \'GET\':
        jsonResponse([\'jobs\' => array_values($jobs)]);
        break;
    
    case preg_match(\'#^/api/jobs/([a-f0-9]+)$#\', $uri, $matches) && $method === \'GET\':
        $jobId = $matches[1];
        if (!isset($jobs[$jobId])) {
            jsonResponse([\'error\' => \'Job not found\'], 404);
        }
        
        // Check if process is still running
        $pid = $jobs[$jobId][\'pid\'] ?? 0;
        $isRunning = $pid > 0 && file_exists("/proc/{$pid}");
        
        // Check potfile for cracked hashes
        $potfile = new Potfile();
        $found = [];
        foreach ($jobs[$jobId][\'hashes\'] as $hash) {
            $cracked = $potfile->get($hash);
            if ($cracked !== null) {
                $found[] = [$hash => $cracked];
            }
        }
        $jobs[$jobId][\'found\'] = $found;
        
        // Update status based on process state
        if (!$isRunning && $jobs[$jobId][\'status\'] === \'running\') {
            $jobs[$jobId][\'status\'] = count($found) > 0 ? \'completed\' : \'completed\';
            $jobs[$jobId][\'completed_at\'] = date(\'c\');
        }
        
        saveJobs($jobs, $jobsFile);
        jsonResponse($jobs[$jobId]);
        break;
    
    case preg_match(\'#^/api/jobs/([a-f0-9]+)$#\', $uri, $matches) && $method === \'DELETE\':
        $jobId = $matches[1];
        if (!isset($jobs[$jobId])) {
            jsonResponse([\'error\' => \'Job not found\'], 404);
        }
        if (isset($jobs[$jobId][\'pid\']) && function_exists(\'posix_kill\')) {
            @posix_kill($jobs[$jobId][\'pid\'], 15);
        }
        $jobs[$jobId][\'status\'] = \'cancelled\';
        saveJobs($jobs, $jobsFile);
        jsonResponse([\'message\' => \'Job cancelled\', \'job\' => $jobs[$jobId]]);
        break;
    
    case $uri === \'/api/crack\' && $method === \'POST\':
        $input = json_decode(file_get_contents(\'php://input\'), true);
        $hashes = $input[\'hashes\'] ?? [];
        $wordlist = $input[\'wordlist\'] ?? \'\';
        $mask = $input[\'mask\'] ?? \'\';
        $hashType = $input[\'hash_type\'] ?? \'auto\';
        $threads = $input[\'threads\'] ?? 1;
        
        if (empty($hashes)) {
            jsonResponse([\'error\' => \'No hashes provided\'], 400);
        }
        if (empty($wordlist) && empty($mask)) {
            jsonResponse([\'error\' => \'Wordlist or mask required\'], 400);
        }
        
        $jobId = bin2hex(random_bytes(8));
        $hashFile = sys_get_temp_dir() . "/brutyf_hashes_{$jobId}.txt";
        $statusFile = sys_get_temp_dir() . "/brutyf_job_{$jobId}.json";
        $outputFile = sys_get_temp_dir() . "/brutyf_output_{$jobId}.txt";
        
        file_put_contents($hashFile, implode("\\n", (array)$hashes));
        
        $job = [
            \'id\' => $jobId,
            \'status\' => \'pending\',
            \'hashes\' => (array)$hashes,
            \'wordlist\' => $wordlist,
            \'mask\' => $mask,
            \'hash_type\' => $hashType,
            \'threads\' => $threads,
            \'created_at\' => date(\'c\'),
            \'found\' => [],
        ];
        
        $jobs[$jobId] = $job;
        saveJobs($jobs, $jobsFile);
        
        $attackArg = $wordlist ? "-w=" . escapeshellarg($wordlist) : "-m=" . escapeshellarg($mask);
        $cmd = sprintf(
            \'nohup php %s -f=%s %s -H=%s -t=%d --status-file=%s -o=%s -q > /dev/null 2>&1 & echo $!\',
            escapeshellarg($brutyfPath),
            escapeshellarg($hashFile),
            $attackArg,
            escapeshellarg($hashType),
            (int)$threads,
            escapeshellarg($statusFile),
            escapeshellarg($outputFile)
        );
        
        $pid = trim(shell_exec($cmd));
        
        $jobs[$jobId][\'status\'] = \'running\';
        $jobs[$jobId][\'pid\'] = (int)$pid;
        saveJobs($jobs, $jobsFile);
        
        jsonResponse([\'message\' => \'Job started\', \'job\' => $jobs[$jobId]], 201);
        break;
    
    default:
        jsonResponse([\'error\' => \'Not found\', \'uri\' => $uri], 404);
}
';
        
        file_put_contents($scriptPath, $script);
        return $scriptPath;
    }
    
    private function loadJobs(): void
    {
        if (file_exists($this->jobsFile)) {
            $this->jobs = json_decode(file_get_contents($this->jobsFile), true) ?: [];
        }
    }
}
