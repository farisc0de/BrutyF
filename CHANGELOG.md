# BrutyF Changelog

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
