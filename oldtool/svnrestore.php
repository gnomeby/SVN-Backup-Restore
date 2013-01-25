#!/usr/bin/php
<?php
if(php_sapi_name() != 'cli')
  exit("This script requeres PHP cli binary\n");
define('DS', DIRECTORY_SEPARATOR);

$toolname = "svnrestore tool";
$version = "1.3.0";


// Parse options
$options = array();
for($i = 1; $i < $argc; $i++)
{
  $argv[$i] = preg_replace("/^--/", "", $argv[$i]);
  list($key, $value) = split("=", $argv[$i], 2);
  $options[$key] = $value;
}


// Process requests
if(array_key_exists('help', $options))
{
  outputHelp();
  exit;
}
elseif(array_key_exists('directory', $options))
{
  $retval = SvnRestoreConfig::applyConfiguration($options);
  checkErrors($retval);
  
  $retval = checkDirectory($options['directory']);
  checkErrors($retval);
  
  $directory = realpath($options['directory']);
  $repositories = scanRepositories($directory);
  ksort($repositories);
  
  if(array_key_exists('list', $options))
  {
    $content = '';
    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      ob_start();
    else 
      echo "{$toolname} {$version}" . PHP_EOL . PHP_EOL;
    
    printf("%-40s %-30s %-8s %2s %-8s%s", "Filename", "Repository path", "Revision", "", "Size", PHP_EOL);
    printf("%-40s %-30s %-8s %2s %8s%s", "---------------", "---------------", "--------", "", "--------", PHP_EOL);
    foreach($repositories as $info)
      printf("%-40s %-30s %8d %2s %8s%s", basename($info['file']), $info['path'], $info['rev'], "", $info['size'], PHP_EOL);
    printf("%-30s%s", "---------------", PHP_EOL);      
    printf("%d %s%s", count($repositories), "repositories were found.", PHP_EOL);    

    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      $content = ob_get_clean();
      
    if(array_key_exists('email-report', $options))
    {
      $addresses = split(";", $options['email-report']);
      $recipient = $addresses[0];
      $headers = '';
      if(count($addresses) > 1)
      {
        array_shift($addresses);
        $headers = "Cc: ".join("; ", $addresses);
      }      
      mail($recipient, "{$toolname} {$version} - list", $content, $headers);
    }

    if(!array_key_exists('quiet', $options))
      echo $content;
  }
    
  if(array_key_exists('output', $options))
  {    
    $retval = checkOutputDirectory($options['output']);
    checkErrors($retval);
    
    // Check match names
    $forProcessing = array();
    $needGzip = false;
    foreach($repositories as $repository)
    {
      $filename = basename($repository['file']);
      if($options['name'] == '*' || strpos(preg_replace('/\.subversion\.\d+\.dump(\.gz)?$/', '', $filename), $options['name']) !== false)
      {
        $forProcessing[] = $repository;
        if(preg_match("/\.gz$/", $filename))
          $needGzip = true;
      }
    }
    
    if($needGzip)
    {
      $retval = checkGzip();
      checkErrors($retval);
    }
    
    // Check paths
    $retval = null;
    foreach($forProcessing as $repository)
    {
      if(is_dir($options['output'] . DS . $repository['path']))
      {
        if(!is_array($retval))
          $retval = array();
        $retval[] = $options['output'] . DS . $repository['path'] . " directory already exists";
      }
    }
    checkErrors($retval);

    
    // Restore
    $content = '';
    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      ob_start();
    else 
      echo "{$toolname} {$version}" . PHP_EOL . PHP_EOL;
    
    printf("%-40s %-30s %-8s %-10s %s%s", "Filename", "Repository name", "Revision", "Size", "Time", PHP_EOL);
    printf("%-40s %-30s %-8s %-10s %-15s%s", "--------", "---------------", "--------", "----------", "---------------", PHP_EOL);
    

    foreach($forProcessing as $repository)
    {
      $path = $options['output'] . DS . $repository['path'];
      if(!array_key_exists('debug', $options))
        mkdir($path, 0777, TRUE);      

      $start = microtime(true);
      $command = "svnadmin create {$path}";
      `$command`;
      $command = "cat {$repository['file']}";
      if(preg_match("/\.gz$/", $repository['file']))
        $command .= " | gzip -d";
      $command .= " | svnadmin load {$path} --quiet";
      if(!array_key_exists('debug', $options))
        `$command`;
      $duration = microtime(true) - $start;
        
      printf("%-40s %-30s %8d %10s %15s%s", basename($repository['file']), $repository['path'], $repository['rev'], $repository['size'], sprintf("%d min %02d sec", $duration / 60, $duration%60), PHP_EOL);
    }
    printf("%-30s%s", "---------------", PHP_EOL);      
    printf("%d %s%s", count($forProcessing), "repositories were restored.", PHP_EOL);

    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      $content = ob_get_clean();
      
    if(array_key_exists('email-report', $options))
    {
      $addresses = split(";", $options['email-report']);
      $recipient = $addresses[0];
      $headers = '';
      if(count($addresses) > 1)
      {
        array_shift($addresses);
        $headers = "Cc: ".join("; ", $addresses);
      }      
      mail($recipient, "{$toolname} {$version} - backup", $content, $headers);
    }

    if(!array_key_exists('quiet', $options))
      echo $content;    
  }
}
else
{
  outputHelp();
  exit;
}

exit;

/*******************************/

/* Functions */
function outputHelp()
{
  echo "{$toolname} {$version}
  
Usage:
svnrestore --directory=DIRPATH --name=REPONAME --output=DIRPATH 
svnrestore --directory=DIRPATH --list

General options:
--debug									  Run in debug mode, don't dump repositories
--directory=DIRPATH       Directory of SVN repository/repositories
--name=REPONAME           Name of repository for resore. * all repositories.
--list									  Output list of found repositories 
--output=DIRPATH          Base directory for restore

Additinal options
--email-report=addresses  List of e-mail addresses for sending report.
--quiet                   Quiet mode (no output).


";
}


function checkErrors($retval)
{
  if(!is_array($retval))
    return;
  
  echo PHP_EOL . "\033[1;31m" . "Errors:" . PHP_EOL;
  foreach($retval as $errorMessage)
    echo "\033[1;31m".$errorMessage."\033[0m".PHP_EOL;
  
  exit;
}


function checkDirectory($dirName)
{
  $errors = array();
  if(!$dirName || !is_dir($dirName))
    $errors[] = "{$dirName} is not a directory.";
  elseif(!is_readable($dirName))
    $errors[] = "{$dirName} is not readably.";
    
  if(count($errors))
    return $errors;
  
  return TRUE;
}


function checkOutputDirectory($dirName)
{
  $errors = array();
  if(!$dirName || !is_dir($dirName))
    $errors[] = "{$dirName} is not a directory.";
  elseif(!is_writable($dirName))
    $errors[] = "{$dirName} is not writable.";
    
  if(count($errors))
    return $errors;
  
  return TRUE;
}


function checkGzip()
{
  $versionInformation = `gzip --version`;
  $errors = array();
  
  if(!preg_match("/^gzip 1\./", $versionInformation))
    $errors[] = "Can not find gzip version information.";

  if(count($errors))
    return $errors;
  
  return TRUE;
}


function scanRepositories($dirName)
{
  $repositories = array();
  
  $dirPointer = dir($dirName);
  while(($entry = $dirPointer->read()) !== FALSE)
  {
    if($entry == '.' || $entry == '..')
      continue;
    if(!is_file($dirName . DS . $entry))
      continue;
      
    $matches = array();
    if(!preg_match("/^(.+).subversion\.(\d+)\.dump(\.gz)?$/", $entry, $matches))
      continue;
      
    $repositories[] = array(
      'file' => $dirName . DS . $entry,
      'size' => makeHumarReadableSize(filesize($dirName . DS . $entry)),
      'path' => str_replace('.', DS, $matches[1]),
      'rev' => $matches[2],
    );
  }
  $dirPointer->close();      
    
  return $repositories;
}


function makeHumarReadableSize($size)
{
  $abr = array('b', 'KiB', 'MiB', 'GiB');
  
  $index = 0;
  while($size > 1024 && $index < 3)
  {
    $size = (float)$size / 1024;
    $index++;
  }
  
  if(gettype($size) == 'float')
    return sprintf('%.2f %-3s', $size, $abr[$index]);
  else
    return sprintf('%d %-3s', $size, $abr[$index]);  
}


/* Classes */
final class SvnRestoreConfig
{
  
  static function applyConfiguration($options)
  {
    $errors = array();
    
    if(count($errors))
      return $errors;
    
    return TRUE;
  }
} 
?>