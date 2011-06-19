<?php

// Checks
if(php_sapi_name() != 'cli')
  exit('This script requires PHP cli binary.' . PHP_EOL);
  
if(version_compare(PHP_VERSION, '5.1.0', '<'))
  exit('Please install PHP 5.1 or higher.' . PHP_EOL);


// Constants
define('DS', DIRECTORY_SEPARATOR);