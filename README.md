# BrutyF

<p align="center">
  <img src="https://img.shields.io/badge/version-2.0-blue.svg" alt="Version 2.0">
  <img src="https://img.shields.io/badge/php-%3E%3D7.4-green.svg" alt="PHP >= 7.4">
  <img src="https://img.shields.io/badge/license-MIT-orange.svg" alt="License: MIT">
</p>

## 🔒 Overview

BrutyF is a powerful PHP-based password hash cracking tool designed to efficiently test wordlists against hashed passwords. It supports multithreading for improved performance and provides detailed progress reporting.

## ✨ Features

- **Fast Password Cracking**: Efficiently test wordlists against hashed passwords
- **Multithreading Support**: Utilize multiple CPU cores for faster cracking (Unix/Linux only)
- **Smart Resource Management**: Automatically optimize memory usage based on input size
- **Detailed Progress Reporting**: Real-time progress percentage and status updates
- **Verbose Mode**: Detailed output of each password attempt
- **Result Export**: Save successful cracks to a formatted output file

## 📋 Requirements

- PHP 7.4 or higher
- For multithreading: PHP with pcntl extension (Unix/Linux systems only)

## 🚀 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/farisc0de/BrutyF.git
   cd BrutyF
   ```

2. Make the script executable:
   ```bash
   chmod +x brutyf.php
   ```

## 🔧 Usage

### Basic Usage

```bash
./brutyf.php -f=<hashfile> -w=<wordlist>
```

Example:
```bash
./brutyf.php -f=hash.txt -w=passwords.txt
```

### Command Line Options

| Option | Long Option | Description |
|--------|-------------|--------------|
| `-f`   | `--hashfile=FILE` | File containing hashed passwords |
| `-w`   | `--wordlist=FILE` | Wordlist file with passwords to try |
| `-v`   | `--verbose` | Show detailed progress of each attempt |
| `-t`   | `--threads=NUM` | Number of threads (1-16, default: 1) |
| `-a`   | `--about` | Show information about BrutyF |

### Advanced Usage

#### Using Multithreading

To utilize multiple CPU cores (Unix/Linux systems only):

```bash
./brutyf.php -f=hash.txt -w=passwords.txt -t=4
```

This will distribute the workload across 4 threads.

#### Verbose Mode

To see each password attempt in real-time:

```bash
./brutyf.php -f=hash.txt -w=passwords.txt -v
```

## 📁 File Formats

### Hash File Format

The hash file should contain one hash per line:

```
$2y$10$abcdefghijklmnopqrstuOQsvMFYKFTyuiLQpjqEO8Jgm4fYEFGhi
$2y$10$123456789abcdefghijklOPqrstuvwxyzABCDEFGHIJKLMNOPQRS
```

### Wordlist Format

The wordlist file should contain one password per line:

```
password123
admin
secret
12345678
```

## 📊 Performance Tips

1. **Optimize Thread Count**: For best performance, set threads to match your CPU core count
2. **Wordlist Size**: Large wordlists benefit more from multithreading
3. **Avoid Verbose Mode**: For maximum speed, only use verbose mode for debugging

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 👨‍💻 Author

- **farisc0de** - [GitHub](https://github.com/farisc0de)
- Email: farisksa79@gmail.com

## 📜 Changelog

See the [CHANGELOG.md](CHANGELOG.md) file for details on version history and updates.
