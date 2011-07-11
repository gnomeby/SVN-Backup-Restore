<?php

/**
 * @author Andrey Niakhaichyk <andrey@niakhaichyk.org>
 * 
 * @name svnrestore
 * @version 2.0.0
 * 
 */

require_once 'configure.php';
require_once 'winsupport.php';

require_once 'FileSystem.php';
require_once 'Output.php';
require_once 'SVNAdminExtended.php';
require_once 'SVNLook.php';

require_once 'Arguments.php';
Arguments::parse();

if(Arguments::hasOption('help') || Arguments::getArgnum() == 0)
{
    echo help();
    commonChecks();
}
elseif(Arguments::getArgnum() == 1) // list
{
    commonChecks();
    
    $directory = Arguments::getFirstArg();
    $directory = rtrim($directory, DS);
    if(!FileSystem::isDirectoryReadable($directory))
        Output::displayErrorAndExit("The $directory is not readable directory.");
        
        
    // Main part
    if(Arguments::hasOption('quiet'))
        ob_start();
        
    echo "Base directory: " . $directory . PHP_EOL . PHP_EOL;

    
    $data = array();
    $files = glob($directory . DS . "*.svndump");
    foreach($files as $file)
    {
        $repository = array();
        $repository['backupName'] = substr($file, strlen($directory . DS)); 
        $repository['path'] = SVNAdminExtended::backupNameToPath($repository['backupName']);

        $data[] = $repository;
    }
    
    if(count($data))
    {
        $headers = array(
            "Repository" => array('bind' => 'backupName'), 
            "Restore path" => array('bind' => 'path'),
        );
        $tableInfo = Output::displayTable($headers, $data);
        echo str_repeat('-', $tableInfo['width']) . PHP_EOL;
    }
          
    printf("%d %s", 
        count($data), 
        count($data) > 1 ? "backups were found." : "backup was found.");
    echo PHP_EOL;

    if(Arguments::hasOption('quiet'))
      $content = ob_get_clean();
}

// --- END

function help()
{
  return <<<HELP
@name @version 
The tool for restoring subversion repositories on server side.

HELP;
}

function commonChecks()
{
    $svnadminVersion = SVNAdminExtended::getVersion();
    if(!$svnadminVersion)
        Output::displayErrorAndExit("Cannnot detect svnadmin version.");
}