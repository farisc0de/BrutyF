# BrutyF Changelog

## Version 3.0 (2025-12-27)

### Major New Features

- **Multi-Hash Algorithm Support**
  - Added support for MD5, SHA1, SHA256, SHA512, NTLM, MySQL (old), MySQL5
  - Automatic hash type detection based on format and length
  - Manual hash type override with `-H` / `--hash-type` option
  - Support for salted hashes (hash:salt format)

- **Mask-Based Brute Force Mode**
  - Generate passwords using customizable masks (`-m` / `--mask`)
  - Built-in charsets: `?l` (lowercase), `?u` (uppercase), `?d` (digits), `?s` (special), `?a` (all)
  - Efficient iterator-based generation for memory efficiency

- **Rule-Based Password Mutations**
  - 12 built-in mutation rules: none, lowercase, uppercase, capitalize, reverse, leet, append_123, append_1, append_!, prepend_123, duplicate, toggle_case
  - Custom rule support with `^` (prepend), `$` (append), `s` (substitute) operators
  - Apply multiple rules with `--rules=rule1,rule2,rule3`

- **Resume/Checkpoint Support**
  - Save progress automatically during attacks
  - Resume interrupted attacks with `-r` / `--resume` flag
  - Session files stored in system temp directory

- **Multiple Output Formats**
  - Export results as text, JSON, or CSV
  - Specify output file with `-o` / `--output`
  - Specify format with `--format=text|json|csv`

- **Compressed Wordlist Support**
  - Read `.gz` (gzip) compressed wordlists directly
  - Read `.bz2` (bzip2) compressed wordlists directly

- **Configuration File Support**
  - Load defaults from `brutyf.conf` or `.brutyfrc`
  - Supports JSON and INI formats
  - Search paths: current directory, home directory, `~/.config/brutyf/`

- **Logging System**
  - Optional file-based logging with `--log=FILE`
  - Configurable log levels: debug, info, warning, error
  - Set level with `--log-level=LEVEL`

- **Enhanced Statistics & Reporting**
  - Real-time speed (passwords/second)
  - ETA (estimated time of arrival)
  - Peak memory usage tracking
  - Comprehensive end-of-attack summary

### New Command Line Options

| Option | Description |
|--------|-------------|
| `-m, --mask` | Mask for brute-force mode |
| `-H, --hash-type` | Specify hash type (auto, md5, sha1, sha256, sha512, bcrypt, ntlm, mysql, mysql5) |
| `-r, --resume` | Resume previous session |
| `-q, --quiet` | Suppress output for scripting |
| `-o, --output` | Output file for results |
| `--format` | Output format (text, json, csv) |
| `--no-color` | Disable colored output |
| `--rules` | Comma-separated mutation rules |
| `--log` | Log file path |
| `--log-level` | Log level (debug, info, warning, error) |
| `-h, --help` | Show help message |

### Architecture Changes

- **Modular Class Structure**
  - `HashIdentifier` - Hash detection and verification
  - `RuleEngine` - Password mutation rules
  - `MaskGenerator` - Brute-force mask iterator
  - `Statistics` - Performance tracking
  - `Logger` - File logging
  - `Config` - Configuration management
  - `Session` - Resume/checkpoint support
  - `BrutyF` - Main orchestrator class

- **PSR-4 Style Autoloading**
  - Classes organized in `src/` directory
  - Automatic class loading via `spl_autoload_register`

### Performance Improvements

- Early termination when password found in multithreaded mode
- Shared memory file for inter-process communication of found passwords
- Optimized progress display with speed and ETA

### Input Flexibility

- Read hashes from stdin with `-f=-` or `-f=stdin`
- Support for `username:hash` format (preserves usernames in output)
- Support for `hash:salt` format for salted hashes

### Requirements

- PHP 8.0 or higher (for match expressions and named arguments)
- pcntl extension (optional, for multithreading)
- zlib extension (optional, for .gz wordlists)
- bz2 extension (optional, for .bz2 wordlists)

---

## Version 2.0 (2025-04-11)

### Major Improvements

- **Complete Code Restructuring**
  - Refactored into a proper OOP structure with classes and namespaces
  - Added strict typing for better code quality and error prevention
  - Improved code organization with smaller, focused methods

- **Performance Enhancements**
  - Implemented batch processing for passwords to reduce memory usage
  - Added progress percentage display for better visibility
  - Optimized file reading with buffer sizes
  - Added small CPU pauses to prevent system overload during intensive operations

- **Multithreading Support**
  - Added parallel processing capabilities using PHP's pcntl extension
  - Implemented two multithreading strategies:
    - Hash-based distribution (divides hashes among threads)
    - Wordlist-based distribution (divides wordlist among threads)
  - Automatic selection of the best strategy based on input
  - Inter-Process Communication (IPC) for result sharing between threads

- **Better Error Handling**
  - Added comprehensive file existence checks
  - Improved error reporting with colored output
  - Better handling of file operations with proper resource management

- **Enhanced User Experience**
  - More detailed command-line options
  - Improved verbose mode with better output formatting
  - Better progress reporting during password cracking
  - Improved result export format

### New Features

- **Thread Control**
  - Added `-t` or `--threads` parameter to control the number of parallel processes
  - Automatic detection of pcntl extension availability
  - Graceful fallback to single-threaded mode when multithreading is not available

- **Improved Verbose Mode**
  - Enhanced verbose output with better formatting
  - Added controlled delays to make verbose output readable

- **Smart Resource Management**
  - Automatic memory optimization based on wordlist size
  - Intelligent distribution of workload across available threads

### Technical Improvements

- Added proper PHP namespace (`BrutyF`)
- Implemented proper class constants for version and author information
- Better file handling with proper resource cleanup
- Improved command-line argument parsing
- Added proper exit codes for different scenarios

### Requirements

- PHP 7.4 or higher (for typed properties)
- pcntl extension (optional, for multithreading support)

### Notes

- Multithreading is only available on Linux/Unix systems with the pcntl extension
- Windows users need to use WSL (Windows Subsystem for Linux) to benefit from multithreading
