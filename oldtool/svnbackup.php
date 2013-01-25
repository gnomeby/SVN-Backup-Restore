#!/usr/bin/php
<?php
if(php_sapi_name() != 'cli')
  exit("This script requeres PHP cli binary\n");
define('DS', DIRECTORY_SEPARATOR);

SvnBackupConfig::$toolname = "svnbackup tool";
SvnBackupConfig::$version = "2.1.2";

// TODO: Detect free space (inform, stop backup)
// TODO: Output "test backup" caption
// TODO: Implement cross-platform logic

// Process requests
$options = SvnBackupConfig::parseOptions();
if(array_key_exists('help', $options))
{
  outputHelp();
}
elseif(array_key_exists('directory', $options))
{
  processDirectory();
}
else
{
  outputHelp();
}

exit;

/*******************************/

/* Functions */
function outputHelp()
{
  echo SvnBackupConfig::$toolname . ' ' . SvnBackupConfig::$version . "
  
Usage:
svnbackup REPOSITORIES_PATH BACKUP_FOLDER
  * scan repositories and backup them into folder 
svnbackup REPOSITORIES_PATH
  * scan repositories and show info about them 

REPOSITORIES_PATH         Directory of SVN repository/repositories
BACKUP_FOLDER             Directory for back-up files
General options:
--debug                   Run in debug mode, don't dump repositories
--quiet                   Quiet mode (no output).

Additional options
--split-semaphore-bytes=SIZE    When destination file will be bigger, tool start new file. Use small values for compressed files. ".makeHumarReadableSizeKib(SvnBackupConfig::$split_semaphore_kbytes)." - by default. 0 - unlimited. Use suffixes K, M, G. 
--split-detector=REVS           Test destination file each REVS for --split-semaphore-bytes. ".SvnBackupConfig::$split_detector." - by default. 0 - test based on previous file.
--max-depth=number              Recursive repository search, [1,..]. ".SvnBackupConfig::$max_depth." - by default.
--compress-rate=number          Compress level for gzip, [0,..,9]. ".SvnBackupConfig::$compress_rate." - by default, 0 - no compression.
--email-report=addresses        List of e-mail addresses for sending report.
--skip-gzip-test                Don't test ready gzip files

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


function scanDirectory($dirName, $maxDepth)
{
  // Support direct point to repository
  if(is_file($dirName.DS.'db'.DS.'current'))
  {
    $repositoryName = basename($dirName);
    $info = file_get_contents($dirName.DS.'db'.DS.'current');
    $version = file_get_contents($dirName.DS.'db'.DS.'format');
    
    return array($repositoryName => array('directory' => $dirName, 'revision' => (int)$info, 'version' => $version));
  }
  
  $repositories = array();
  
  $levels = array();  
  $dirPointer = dir($dirName);
  $levels[1] = array('pointer' => $dirPointer, 'dirname' => $dirName);
  for($i = 1; $i <= $maxDepth; $i++)
  {
    $pointer = $levels[$i]['pointer']; 
    $dirname = $levels[$i]['dirname'];
    while(($entry = $pointer->read()) !== FALSE)
    {
      if($entry == '.' || $entry == '..')
        continue;
      
      if(!is_dir($dirname . DS . $entry))
        continue;
      
      $currentFile = $dirname . DS . $entry . DS . 'db' . DS . 'current';
      if(is_file($currentFile))
      {
        $repositoryPrefix = "";
        for($j = 2; $j <= $i; $j++)
          $repositoryPrefix .= basename($levels[$j]['dirname']) . "."; 
        $repositories[$repositoryPrefix . $entry] = array('directory' => $dirname . DS . $entry);
        
        // Calculate repository number
        $info = file_get_contents($currentFile);
        $repositories[$repositoryPrefix . $entry]['revision'] = (int)$info;
        $repositories[$repositoryPrefix . $entry]['version'] = file_get_contents($dirname . DS . $entry . DS . 'db' . DS . 'format');
      }
      elseif($i + 1 <= $maxDepth)
      {
        $levels[$i + 1] = array('pointer' => dir($dirname . DS . $entry), 'dirname' => $dirname . DS . $entry);
        continue 2;      
      }
    }
    
    if($i == 1)
      break;
    $i-=2;
  }
  
  return $repositories;
}


function makeHumarReadableSizeKib($size)
{
  $abr = array('KiB', 'MiB', 'GiB');
  
  $index = 0;
  while($size > 1024)
  {
    $size = (float)$size / 1024;
    $index++;
  }
  
  return sprintf('%.1f %-3s', $size, $abr[$index]);
}

function makeHumarReadableDBVersion($version)
{
  static $svnadminVersion;
  if(!$svnadminVersion)
  {
    $versionInformation = `svnadmin --version`;
    $matches = array();
    preg_match('/version ([\d\.]+)/', $versionInformation, $matches);
    $svnadminVersion = $matches[1];
  }
  
  switch($version)
  {
    case 4:
      $version = '1.6';
      break;
    case 3:
      $version = '1.5';
      break;
    case 2:
      $version = '1.4';
      break;
    default:
      $version = '1.3';
      break;
  }
  if(version_compare(preg_replace('/(\d\.\d).*/', '$1', $svnadminVersion), $version, '>'))
    $version = '! ' . $version;
    
  return $version;
}


function makeHumarReadableTime($seconds)
{
  $abr = array('sec', 'min');
  
  $index = 0;
  while($seconds > 60)
  {
    $seconds = (int)($seconds / 60);
    $index++;
    break;
  }
  
  return sprintf('%d %-3s', $seconds, $abr[$index]);  
}

function filesizeKib($filename)
{
  $output = '';
  $retval = 0;
  exec("ls --size --dereference --block-size=1024 {$filename}", $output, $retval);        
  return $retval > 0 ? FALSE : (int)$output[0];
}
function checkGzipFile($filename)
{
  $output = '';
  $retval = 0;
  exec("gzip --test {$filename}", $output, $retval);        
  return $retval > 0 ? FALSE : TRUE;
}

function processDirectory()
{
  $options = SvnBackupConfig::parseOptions();
  $retval = SvnBackupConfig::applyConfiguration($options);
  checkErrors($retval);
  
  $retval = checkDirectory($options['directory']);
  checkErrors($retval);
  
  $directory = realpath($options['directory']);
  $directories = scanDirectory($directory, SvnBackupConfig::$max_depth);
  ksort($directories, SORT_STRING);
  
  $debug_mode = array_key_exists('debug', $options);
  
  if(array_key_exists('list', $options))
  {
    $content = '';
    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      ob_start();
    else 
      echo SvnBackupConfig::$toolname . ' ' . SvnBackupConfig::$version . PHP_EOL . PHP_EOL;
    
    printf("Base directory: " . $options['directory'] . PHP_EOL.PHP_EOL);
    
    printf("%-30s %-10s %-9s %s%s", "Repository name", "Revision", "Version", "Path", PHP_EOL);
    printf("%-30s %-10s %-9s %s%s", "---------------", "--------", "-------", "----", PHP_EOL);
    foreach($directories as $repositoryName => $info)
    {
      $repository_relative_path = ltrim(str_replace(realpath($options['directory']), '', $info['directory']), '/');
      // If pointed to single repository
      if(!$repository_relative_path)
        $repository_relative_path = ltrim(str_replace(dirname(realpath($options['directory'])), '', $info['directory']), '/');
      
      printf("%-30s %8d %9s   %s%s", $repository_relative_path, $info['revision'], makeHumarReadableDBVersion($info['version']), $info['directory'], PHP_EOL);
    }
    printf("%-30s%s", "---------------", PHP_EOL);      
    printf("%d %s%s", count($directories), "repositories were found.", PHP_EOL);    

    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      $content = ob_get_clean();
      
    if(array_key_exists('email-report', $options))
      sendEmail(SvnBackupConfig::$toolname . ' ' . SvnBackupConfig::$version . " - list", $content);

    if(!array_key_exists('quiet', $options))
      echo $content;
  }
    
  if(array_key_exists('destination', $options))
  {
    if(SvnBackupConfig::$compress_rate)
    {    
      $retval = checkGzip();
      checkErrors($retval);
    }
    
    $retval = checkOutputDirectory($options['destination']);
    checkErrors($retval);
  
    $content = '';
    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      ob_start();
    else 
      echo SvnBackupConfig::$toolname . ' ' . SvnBackupConfig::$version . PHP_EOL . PHP_EOL;
    
    printf("Base directory: " . $options['directory'] . PHP_EOL);  
    
    $outputdir = realpath($options['destination']);

    // Scan backup folder
    printf("Scan backup directory: " . $options['destination'] . PHP_EOL);
    $startPoint = microtime(TRUE);
    $existentBackups = array();
    $dirReference = dir($outputdir);
    while(($entry = $dirReference->read()) != FALSE)
    {
      if($entry[0] == '.')
        continue;
        
      $matches = array();
      $regexp = SvnBackupConfig::$compress_rate ? '/(.*?)\.subversion\.(\d+)-(\d+)\.dump\.gz$/' : '/(.*?)\.subversion\.(\d+)-(\d+)\.dump$/';
      if(preg_match($regexp, $entry, $matches))
      {
        $repository = $matches[1];
        $firstRevision = (int)$matches[2];
        $lastRevision = (int)$matches[3];

        if(!array_key_exists($repository, $existentBackups))
          $existentBackups[$repository] = array('ranges' => array());
        
        $existentBackups[$repository]['ranges'][$firstRevision] = array('first' => $firstRevision, 'last' => $lastRevision, 'file' => $outputdir . DS . $entry); 
      }
    }
    // Recalculate pieces
    foreach($existentBackups as $repository => $info)
    {
      // Highest revision, total size and latest file
      $revs = array_keys($info['ranges']);
      sort($revs, SORT_NUMERIC);
      $revision = -1;
      $size = 0;
      $existentBackups[$repository]['files'] = array();
      foreach($revs as $first)
      {
        $file = $existentBackups[$repository]['ranges'][$first]['file'];
        $last = $existentBackups[$repository]['ranges'][$first]['last'];
        // TODO: Add check for plaintext files
        $checkFile = (SvnBackupConfig::$compress_rate && !$options['skip-gzip-test']) ? checkGzipFile($file) : TRUE;
            
        if(($revision + 1) == $first && $checkFile)
        {
          $revision = $last;
          $existentBackups[$repository]['file'] = $file;
          $existentBackups[$repository]['files'][] = $file;
          $size += filesizeKib($file);
        }
        else
          unlink($file);
      }
      $existentBackups[$repository]['revision'] = $revision;
      $existentBackups[$repository]['size'] = $size;
      
      // Rename files
      $n = !empty($directories[$repository]['revision']) ? $directories[$repository]['revision'] : $existentBackups[$repository]['revision'];
      settype($n, 'string');
      $n = strlen($n);
      
      foreach($existentBackups[$repository]['files'] as $file)
      {
        $matches = array();
        $regexp = '/(.*?)\.subversion\.(\d+)-(\d+)\.dump/';
        preg_match($regexp, $file, $matches);
        $newfile = str_replace('subversion.'.$matches[2].'-'.$matches[3], sprintf("subversion.%0{$n}d-%0{$n}d", $matches[2], $matches[3]), $file);
        if($newfile != $file)
          rename($file, $newfile);
        $existentBackups[$repository]['file'] = $newfile;          
      }      
    }
    $endPoint = microtime(TRUE);
    printf("* Total time: " .  makeHumarReadableTime($endPoint - $startPoint) . PHP_EOL . PHP_EOL);
    
    printf("%-30s %-7s  %-8s  %-10s  %-9s%s", "Repository name", "Version", "Revision", "Size", "Changed", PHP_EOL);
    printf("%-30s %-7s  %-8s  %-10s  %-9s%s", "---------------", "-------", "--------", "----------", "---------", PHP_EOL);
    
    // Process found directories
    foreach($directories as $repositoryName => $info)
    {
      clearstatcache();
      
      $repository_relative_path = ltrim(str_replace(realpath($options['directory']), '', $info['directory']), '/');
      // If pointed to single repository
      if(!$repository_relative_path)
        $repository_relative_path = ltrim(str_replace(dirname(realpath($options['directory'])), '', $info['directory']), '/');
         
      if(!array_key_exists($repositoryName, $existentBackups) && $debug_mode)
      {
        $revision = 0;
        $currentSize = 0;
        $pieces = SvnBackupConfig::$split_detector ? ceil((float)$info['revision'] / SvnBackupConfig::$split_detector) : '-unknown-';
        $changed = '- debug mode - new one, waiting +' . $info['revision'] . ' revisions, up to ' . $pieces . ' piece' . ($pieces > 1 ? 's': '');
      }
      elseif(!array_key_exists($repositoryName, $existentBackups))
      {
        // New repository
        $startPoint = microtime(TRUE);
        $dumpinfo = dump(0, $info['revision'], $info['directory'], $repositoryName);
        $endPoint = microtime(TRUE);
        
        $revision = $info['revision'];
        $currentSize = makeHumarReadableSizeKib($dumpinfo['size']);
        $changed = 'new one' . ', ' . makeHumarReadableTime($endPoint - $startPoint) . ', ' . $dumpinfo['pieces'] . ' piece' . ($dumpinfo['pieces'] > 1 ? 's': '');
      }
      elseif($existentBackups[$repositoryName]['revision'] == $info['revision'])
      {
        $revision = $existentBackups[$repositoryName]['revision'];
        $currentSize = makeHumarReadableSizeKib($existentBackups[$repositoryName]['size']);
        $changed = '';
      }
      elseif($debug_mode)
      {
        $revision = $existentBackups[$repositoryName]['revision'];
        $currentSize = makeHumarReadableSizeKib($existentBackups[$repositoryName]['size']);
        $pieces = SvnBackupConfig::$split_detector ? ceil((float)($info['revision'] - $existentBackups[$repositoryName]['revision']) / SvnBackupConfig::$split_detector) : '-unknown-';
        $changed = '- debug mode - waiting +' . ($info['revision'] - $existentBackups[$repositoryName]['revision']) . ' revisions, up to ' . $pieces . ' piece' . ($pieces > 1 ? 's': '');        
      }
      else
      {
        $startPoint = microtime(TRUE);
        $dumpinfo = dump($existentBackups[$repositoryName]['revision'] + 1, $info['revision'], $info['directory'], $repositoryName, $existentBackups[$repositoryName]['file'], $existentBackups[$repositoryName]['size']);
        $endPoint = microtime(TRUE);
        
        $revision = $info['revision'];
        $currentSize = $dumpinfo['size'];
        $changed = '+' . makeHumarReadableSizeKib($currentSize - $existentBackups[$repositoryName]['size']) . ', ' . makeHumarReadableTime($endPoint - $startPoint) . ', ' . $dumpinfo['pieces'] . ' new piece' . ($dumpinfo['pieces'] > 1 ? 's': '');
        $currentSize = makeHumarReadableSizeKib($currentSize);
      }
      
      printf("%-30s %7s  %8d  %10s  %s%s", $repository_relative_path, makeHumarReadableDBVersion($info['version']), $revision, $currentSize, $changed, PHP_EOL);
      flush();
    }
    printf("%-30s%s", "---------------", PHP_EOL);      
    printf("%d %s%s", count($directories), "repositories were processed.", PHP_EOL);

    if(array_key_exists('quiet', $options) || array_key_exists('email-report', $options))
      $content = ob_get_clean();
      
    if(array_key_exists('email-report', $options))
      sendEmail(SvnBackupConfig::$toolname . ' ' . SvnBackupConfig::$version . " - backup", $content);

    if(!array_key_exists('quiet', $options))
      echo $content;    
  }
}

function sendEmail($subject, $content)
{
  $options = SvnBackupConfig::parseOptions();
  
  $addresses = explode(";", $options['email-report']);
  $recipient = $addresses[0];
  $headers = '';
  if(count($addresses) > 1)
  {
    array_shift($addresses);
    $headers = "Cc: ".join("; ", $addresses);
  }
  mail($recipient, $subject, $content, $headers);      
}

function dump($start, $end, $repository, $name, $file = null, $size = 0)
{
  $options = SvnBackupConfig::parseOptions();
  $outputdir = realpath($options['destination']);
  
  $pieces = 0;

  if($file)
  {
    if(filesizeKib($file) < SvnBackupConfig::$split_semaphore_kbytes)
    {
      $size -= filesizeKib($file);
      $matches = array();
      $regexp = '/(.*?)\.subversion\.(\d+)-(\d+)\.dump/';
      preg_match($regexp, $file, $matches);
      $start = $matches[2];
      unlink($file);
      $pieces--;
    }
    
    $dumpinfo = dump($start, $end, $repository, $name);    
    $dumpinfo['size'] += $size;
    $dumpinfo['pieces'] += $pieces;
    return $dumpinfo;      
  }  
  
  $len = strlen((string)$end);
  
  $detector = SvnBackupConfig::$split_detector ? SvnBackupConfig::$split_detector : $end + 1; 
  
  
  $lastOutputFile = '';
  for(; $start <= $end; $start += $detector)
  {
    $currentEnd = min($start + $detector - 1, $end);
    
    $outputfile = "{$outputdir}" . DS . "{$name}.subversion.".sprintf("%0{$len}d-%0{$len}d", $start, $currentEnd).".dump";
    if(SvnBackupConfig::$compress_rate)
      $outputfile .= ".gz";
      
    $command = "svnadmin dump {$repository} -r {$start}:{$currentEnd} ".($start ? '--incremental' : '')." --quiet";
    if(SvnBackupConfig::$compress_rate)
      $command .= " | "."nice gzip -c -" . SvnBackupConfig::$compress_rate;
    $command .= " > " . $outputfile;
    `$command`;
    
    $pieces++;
    $size += filesizeKib($outputfile);
    
    if($lastOutputFile && filesizeKib($lastOutputFile) < SvnBackupConfig::$split_semaphore_kbytes && filesizeKib($outputfile) < SvnBackupConfig::$split_semaphore_kbytes)
    {
      $command = "cat {$outputfile} >> {$lastOutputFile} && rm {$outputfile}";
      `$command`;
      
      $matches = array();
      $regexp = '/(.*?)\.subversion\.(\d+)-(\d+)\.dump/';
      preg_match($regexp, $lastOutputFile, $matches);
      $firstRevision = $matches[2];
      $lastRevision = sprintf("%0{$len}d", $currentEnd); 
      $outputfile = "{$outputdir}" . DS . "{$name}.subversion.{$firstRevision}-{$lastRevision}.dump";
      if(SvnBackupConfig::$compress_rate)
        $outputfile .= ".gz";
      rename($lastOutputFile, $outputfile);
      
      $pieces--;
    }
    
    if(filesizeKib($outputfile) < SvnBackupConfig::$split_semaphore_kbytes)
      $lastOutputFile = $outputfile;
    else
      $lastOutputFile = '';    
  }
  
  return array('size' => $size, 'pieces' => $pieces);
}

/* Classes */
final class SvnBackupConfig
{
  static $toolname = "svnbackup tool";
  static $version;
  
  static $max_depth = 2;
  static $compress_rate = 9;
  
  static $split_semaphore_kbytes = 1048576; // Kibs 
  static $split_detector = 1000; // Revs
  
  static function parseOptions()
  {
    $argc = $_SERVER['argc'];
    $argv = $_SERVER['argv'];
    
    $options = array();
    for($i = 1; $i < $argc; $i++)
    {
      if(preg_match("/^--/", $argv[$i]))
        $argv[$i] = preg_replace("/^--/", "", $argv[$i]);
      elseif($argv[$i] == 'help')
        $argv[$i] = 'help=true';
      elseif(empty($options['directory']))
        $argv[$i] = 'directory='.$argv[$i];
      elseif(empty($options['destination']))
        $argv[$i] = 'destination='.$argv[$i];
      
      list($key, $value) = explode("=", $argv[$i], 2);
      $options[$key] = $value;
    }

    if(!empty($options['directory']) && empty($options['destination']))
      $options['list'] = 'true';

    if(empty($options['skip-gzip-test']))
      $options['skip-gzip-test'] = NULL;
    
    return $options;
  }
  
  static function applyConfiguration($options)
  {
    $errors = array();
    
    if(array_key_exists('max-depth', $options))
    {
      $value = $options['max-depth'];
      if($value >= 1)
        self::$max_depth = (int)$value;
      else
        $errors[] = 'max-depth value is incorrect.';
    }
    
    if(array_key_exists('compress-rate', $options))
    {
      $value = $options['compress-rate'];
      if($value >= 0 && $value <= 9)
        self::$compress_rate = (int)$value;
      else
        $errors[] = 'compress-rate value is incorrect.';
    }

    if(array_key_exists('split-semaphore-bytes', $options))
    {
      $value = $options['split-semaphore-bytes'];
      $m = array();
      if(preg_match('/^([\d\.]+)(K|M|G)?$/', $value, $m) && (float)$value == $m[1])
      {
        self::$split_semaphore_kbytes = (float)$value;
        if(empty($m[2]))
          $m[2] = 'b';
          
        if($m[2] == 'K')
        {}
        elseif($m[2] == 'M')
          self::$split_semaphore_kbytes *= 1024;
        elseif($m[2] == 'G')
          self::$split_semaphore_kbytes *= 1024 * 1024;
        else
          self::$split_semaphore_kbytes /= 1024;
      }
      else
        $errors[] = 'split-semaphore-bytes value is incorrect.';
    }
    
    if(array_key_exists('split-detector', $options))
    {
      $value = $options['split-detector'];
      if((int)$value == $value)
        self::$split_detector = (int)$value;
      else
        $errors[] = 'split-detector value is incorrect.';
    }
    
    if(count($errors))
      return $errors;
    
    return TRUE;
  }
} 
?>