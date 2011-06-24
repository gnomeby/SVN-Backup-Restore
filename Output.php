<?php
final class Output {
    static function displayErrorAndExit($error)
    {
        echo "[\033[1;31m"."Error"."\033[0m] $error".PHP_EOL;
        exit;
    }
}