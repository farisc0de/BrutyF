# BrutyF
Simple PHP PasswordHash Function Attacker

I created this project for fun 😄 and to learn how to create PHP console applications

This project is for educational purposes only

```bash
-------------------------------------
  ____             _         ______ 
 |  _ \           | |       |  ____|
 | |_) |_ __ _   _| |_ _   _| |__   
 |  _ <| '__| | | | __| | | |  __|  
 | |_) | |  | |_| | |_| |_| | |     
 |____/|_|   \__,_|\__|\__, |_|     
                        __/ |       
                       |___/ v1.0
-------------------------------------
Usage:
brutyf.php -f=<hashfile> -w=<passwordlist>
Example:
brutyf.php -f=hash.txt -w=passwords.txt
-----------------------------------------------------
```

## Options

```
hashfile: For text file that contains the hashes
wordlist: The wordlist you want to test
verbose: Show the current password
about: About the script
```

## Example

```bash
-------------------------------------
  ____             _         ______ 
 |  _ \           | |       |  ____|
 | |_) |_ __ _   _| |_ _   _| |__   
 |  _ <| '__| | | | __| | | |  __|  
 | |_) | |  | |_| | |_| |_| | |     
 |____/|_|   \__,_|\__|\__, |_|     
                        __/ |       
                       |___/ v1.0
-------------------------------------

Attacking: $2y$10$ImJJdLh/Nn8mDwcC2FniMu0FHYLSA7rCeNHRu5qD4TShnDu2LmVkW
----------------------------
Check Password: 123456
Check Password: 12345
Check Password: 123456789
Check Password: password
Check Password: iloveyou
Check Password: princess
Check Password: 1234567
Check Password: 12345678
Check Password: abc123
Check Password: nicole
-------------------------------
Password Found: nicole
-------------------------------
Do you want to export result (Y/n): y
Result Saved (: 
```

## License

MIT
