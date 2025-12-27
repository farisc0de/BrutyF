# BrutyF

<p align="center">
  <img src="https://img.shields.io/badge/version-3.0-blue.svg" alt="Version 3.0">
  <img src="https://img.shields.io/badge/php-%3E%3D8.0-green.svg" alt="PHP >= 8.0">
  <img src="https://img.shields.io/badge/license-MIT-orange.svg" alt="License: MIT">
</p>

## 🔒 Overview

BrutyF is a powerful PHP-based password hash cracking tool designed to efficiently test wordlists against hashed passwords. It supports multiple hash types, multithreading, mask-based brute-force attacks, rule-based mutations, and provides detailed progress reporting with statistics.

## ✨ Features

- **Multi-Hash Support**: MD5, SHA1, SHA256, SHA512, bcrypt, NTLM, MySQL (old & new)
- **Auto Hash Detection**: Automatically identifies hash type based on format
- **Multiple Attack Modes**:
  - **Wordlist Attack**: Traditional dictionary attack
  - **Mask Attack**: Brute-force with customizable character masks
  - **Hybrid Attack**: Wordlist + mask combination (e.g., `password?d?d?d`)
  - **Combinator Attack**: Combine two wordlists (word1 + word2)
- **Potfile Support**: Automatically stores cracked hashes to avoid re-cracking
- **Benchmark Mode**: Test hash speeds on your system
- **Multithreading Support**: Utilize multiple CPU cores for faster cracking (Unix/Linux only)
- **Rule-Based Mutations**: Apply transformations like leet speak, case changes, appending numbers
- **Resume Support**: Continue interrupted attacks from where they left off
- **Compressed Wordlists**: Support for .gz and .bz2 compressed wordlists
- **Multiple Output Formats**: Export results as text, JSON, or CSV
- **Detailed Statistics**: Speed, ETA, memory usage, and comprehensive reports
- **Configuration Files**: Save default settings in config files
- **Logging**: Optional file-based logging with configurable levels
- **Quiet Mode**: Silent operation for scripting and automation
- **Stdin Support**: Read hashes from stdin for piping

## 📋 Requirements

- PHP 8.0 or higher
- For multithreading: PHP with pcntl extension (Unix/Linux systems only)
- For compressed wordlists: PHP with zlib/bz2 extensions

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
| `-f`   | `--hashfile=FILE` | File containing hashed passwords (use `-` for stdin) |
| `-w`   | `--wordlist=FILE` | Wordlist file (supports .gz, .bz2) |
| `-m`   | `--mask=MASK` | Mask for brute-force mode |
| `-t`   | `--threads=NUM` | Number of threads (1-16, default: 1) |
| `-H`   | `--hash-type=TYPE` | Hash type (auto, md5, sha1, sha256, sha512, bcrypt, ntlm, mysql, mysql5) |
| `-r`   | `--resume` | Resume previous session |
| `-v`   | `--verbose` | Show detailed progress of each attempt |
| `-q`   | `--quiet` | Suppress output (for scripting) |
| `-o`   | `--output=FILE` | Output file for results |
|        | `--format=FORMAT` | Output format (text, json, csv) |
|        | `--no-color` | Disable colored output |
|        | `--rules=RULES` | Comma-separated mutation rules |
|        | `--log=FILE` | Log file path |
|        | `--log-level=LEVEL` | Log level (debug, info, warning, error) |
|        | `--attack-mode=MODE` | Attack mode: wordlist, mask, hybrid, combinator |
|        | `--wordlist2=FILE` | Second wordlist for combinator attack |
|        | `--potfile=FILE` | Custom potfile path |
|        | `--no-potfile` | Disable potfile |
|        | `--show-pot` | Show cracked hashes from potfile |
| `-b`   | `--benchmark` | Run hash speed benchmark |
| `-a`   | `--about` | Show information about BrutyF |
| `-h`   | `--help` | Show help message |

### Advanced Usage

#### Using Multithreading

```bash
./brutyf.php -f=hash.txt -w=passwords.txt -t=4
```

#### Specifying Hash Type

```bash
./brutyf.php -f=hash.txt -w=passwords.txt -H=md5
```

#### Mask-Based Brute Force

Generate passwords using masks:

```bash
./brutyf.php -f=hash.txt -m='?l?l?l?l?d?d' -t=8
```

**Mask Characters:**
- `?l` - Lowercase letters (a-z)
- `?u` - Uppercase letters (A-Z)
- `?d` - Digits (0-9)
- `?s` - Special characters
- `?a` - All printable characters

#### Using Rules

Apply password mutations:

```bash
./brutyf.php -f=hash.txt -w=passwords.txt --rules=leet,append_123,capitalize
```

**Available Rules:**
- `none` - Original password
- `lowercase` - all lowercase
- `uppercase` - ALL UPPERCASE
- `capitalize` - Capitalize first letter
- `reverse` - Reverse the password
- `leet` - l33t speak (a→4, e→3, etc.)
- `append_123` - Append "123"
- `append_1` - Append "1"
- `append_!` - Append "!"
- `prepend_123` - Prepend "123"
- `duplicate` - Duplicate password
- `toggle_case` - Toggle case (pAsSwOrD)

#### Resume Interrupted Attack

```bash
./brutyf.php -f=hash.txt -w=passwords.txt -r
```

#### Export Results

```bash
./brutyf.php -f=hash.txt -w=passwords.txt -o=results.json --format=json
```

#### Piping Hashes

```bash
cat hashes.txt | ./brutyf.php -f=- -w=passwords.txt -q -o=results.txt
```

#### Using Compressed Wordlists

```bash
./brutyf.php -f=hash.txt -w=rockyou.txt.gz -t=4
```

#### Hybrid Attack (Wordlist + Mask)

Combine words with generated suffixes/prefixes:

```bash
./brutyf.php -f=hash.txt -w=words.txt -m='?d?d?d'
```

This tries each word followed by 3 digits (e.g., `password000`, `password001`, ..., `admin999`).

#### Combinator Attack

Combine two wordlists:

```bash
./brutyf.php -f=hash.txt -w=first.txt --wordlist2=last.txt
```

This concatenates each word from the first list with each word from the second (e.g., `johnsmith`, `johndoe`, `marysmith`).

#### Benchmark Mode

Test hash speeds on your system:

```bash
./brutyf.php --benchmark
```

#### Potfile Management

View previously cracked hashes:

```bash
./brutyf.php --show-pot
```

Disable potfile for a session:

```bash
./brutyf.php -f=hash.txt -w=passwords.txt --no-potfile
```

## 📁 File Formats

### Hash File Format

The hash file should contain one hash per line. Supports multiple formats:

**Plain hashes:**
```
5f4dcc3b5aa765d61d8327deb882cf99
e10adc3949ba59abbe56e057f20f883e
```

**With usernames (username:hash):**
```
admin:5f4dcc3b5aa765d61d8327deb882cf99
user:e10adc3949ba59abbe56e057f20f883e
```

**With salt (hash:salt):**
```
5f4dcc3b5aa765d61d8327deb882cf99:randomsalt
```

### Supported Hash Types

| Type | Example |
|------|---------|
| MD5 | `5f4dcc3b5aa765d61d8327deb882cf99` |
| SHA1 | `5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8` |
| SHA256 | `5e884898da28047d9169e1850...` (64 chars) |
| SHA512 | `b109f3bbbc244eb8...` (128 chars) |
| bcrypt | `$2y$10$abcdefghijklmnopqrstuO...` |
| NTLM | `32ED87BDB5FDC5E9CBA88547376818D4` |
| MySQL (old) | `565491d704013245` |
| MySQL5 | `*2470C0C06DEE42FD1618BB9900DFG1E6Y89F4` |

## ⚙️ Configuration

Create a `brutyf.conf` or `.brutyfrc` file in your home directory or project folder:

```json
{
    "threads": 4,
    "verbose": false,
    "quiet": false,
    "color": true,
    "output_format": "text",
    "hash_type": "auto",
    "rules": [],
    "log_file": null,
    "log_level": "info"
}
```

## 📊 Performance Tips

1. **Optimize Thread Count**: Set threads to match your CPU core count
2. **Use Compressed Wordlists**: Save disk space with .gz files
3. **Avoid Verbose Mode**: Only use for debugging
4. **Use Rules Wisely**: More rules = more combinations = slower
5. **Resume Large Attacks**: Use `-r` flag for long-running attacks

## 📈 Statistics

After each attack, BrutyF displays:
- Total time elapsed
- Passwords tried
- Passwords found
- Speed (passwords/second)
- Peak memory usage

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 👨‍💻 Author

- **farisc0de** - [GitHub](https://github.com/farisc0de)
- Email: farisksa79@gmail.com

## 📜 Changelog

See the [CHANGELOG.md](CHANGELOG.md) file for details on version history and updates.
