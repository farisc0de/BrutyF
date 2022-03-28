#!/usr/bin/php
<?php
echo "\e[1;31m
-------------------------------------
  ____             _         ______ 
 |  _ \           | |       |  ____|
 | |_) |_ __ _   _| |_ _   _| |__   
 |  _ <| '__| | | | __| | | |  __|  
 | |_) | |  | |_| | |_| |_| | |     
 |____/|_|   \__,_|\__|\__, |_|     
                        __/ |       
                       |___/ v1.0
-------------------------------------\e[0m
";

$short_options = "f:w:va";
$long_options = ["wordlist:", "hash:", "verbose", "about"];
$options = getopt($short_options, $long_options);

if (isset($options["about"]) || isset($options["a"])) {
    about();
} elseif ((isset($options["wordlist"]) || isset($options["w"])) && (isset($options["f"]) || isset($options["hash"]))) {

    $hashfile = isset($options["hashfile"]) ? $options["hashfile"] : $options["f"];
    $wordlist = isset($options["wordlist"]) ? $options["wordlist"] : $options["w"];

    $lines = getLines($wordlist);
    $found = [];
    $i = 1;

    if ($file = fopen($hashfile, "r")) {
        $text = fread($file, filesize($hashfile));
        $hashes = explode(PHP_EOL, $text);
        foreach ($hashes as $hash) {
            if (!is_null(trim($hash)) && !empty(trim($hash)) && trim($hash) !== "\n" && trim($hash) !== "\r") {
                echo "\n";
                echo "Attacking: " . $hash . "\n";
                echo "----------------------------" . "\n";
                if ($passwords = fopen($wordlist, "r")) {
                    while (($password = fgets($passwords)) !== false) {
                        if (password_verify(trim($password), trim($hash))) {
                            echo "\e[1;32m";
                            echo "-------------------------------\n";
                            echo "Password Found: " . trim($password) . "\n";
                            echo "-------------------------------\n";
                            echo "\e[0m";
                            $found[] = [$hash => $password];
                            break;
                        } else {
                            if (isset($options["verbose"]) || isset($options["v"])) {
                                echo "Check Password: " . trim($password) . "\n";
                            }

                            if ($i == $lines) {
                                echo "\e[1;31m";
                                echo "-------------------------------\n";
                                echo "Password not found ):\n";
                                echo "-------------------------------\n";
                                echo "\e[0m";
                                $i = 1;
                                break;
                            }
                        }
                        $i++;
                    }
                }
                fclose($passwords);
            }
        }
    }
    fclose($file);

    if (!empty($found)) {

        echo "Do you want to export result (Y/N): ";
        $option = readline("");
        if ($option == "Y" || $option == "y" || $option == "yes") {
            $filename = "result - " . date("Y-m-d h_m_s") . ".txt";
            $myfile = fopen($filename, "w+");
            fwrite($myfile, "[ BrutyF Result ]\n");
            fwrite($myfile, "------------------------\n");
            foreach ($found as $fph) {
                foreach ($fph as $hash => $password) {
                    fwrite($myfile, $hash . ":" . $password);
                }
            }
            fwrite($myfile, "------------------------\n");
            fclose($myfile);
            echo "Result Saved (: \n";
        } else {
            exit();
        }
    }
} else {
    help();
}

function getLines($file)
{
    $f = fopen($file, 'rb');
    $lines = 0;

    while (!feof($f)) {
        $lines += substr_count(fread($f, 8192), "\n");
    }

    fclose($f);

    return $lines;
}

function help()
{
    global $argv;
    echo "Usage:\n";
    echo basename($argv[0]) . " -f=<hashfile> -w=<passwordlist>\n";
    echo "Example:\n";
    echo basename($argv[0]) . " -f=hash.txt -w=passwords.txt\n";
    echo "-----------------------------------------------------\n";
}

function about()
{
    echo "Name: BrutyF\n";
    echo "Version: 1.0\n";
    echo "Developed by: farisc0de\n";
    echo "Email: farisksa79@gmail.com\n";
}
?>
