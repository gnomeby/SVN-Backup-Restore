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
elseif(Arguments::getArgnum() == 2) // restore
{
    commonChecks();
    
    $scanFolder = Arguments::getFirstArg();
    $scanFolder = rtrim($scanFolder, DS);
    if(!FileSystem::isDirectoryReadable($scanFolder))
        Output::displayErrorAndExit("The $scanFolder is not readable directory.");
        
    $restoreFolder = rtrim(Arguments::getSecondArg(), DS);
    $restoreFolder = rtrim($restoreFolder, DS);
    if(!FileSystem::isDirectoryWritable($restoreFolder))
        Output::displayErrorAndExit("The $restoreFolder is not writable directory.");
        
        
    // Main part
    if(Arguments::hasOption('quiet'))
        ob_start();
        
    echo "Base directory: " . $scanFolder . PHP_EOL;
    echo "Restore directory: " . $restoreFolder . PHP_EOL . PHP_EOL;

    $data = array();
    $files = glob($scanFolder . DS . "*.svndump");
    foreach($files as $file)
    {
        $repository = array();
        $repository['dumpFile'] = $file;
        $repository['backupName'] = substr($file, strlen($scanFolder . DS)); 
        $repository['path'] = SVNAdminExtended::backupNameToPath($repository['backupName'], $restoreFolder);
        $repository['shortpath'] = substr($repository['path'], strlen($restoreFolder . DS));

        $data[] = $repository;
    }
    
    if(count($data))
    {
        $headers = array(
            "Repository" => array('bind' => 'shortpath'),
        	"Revision"  => array('bind' => 'revision', 'align' => 'right'), 
        );
        $min_width = strlen("Repository");
        foreach ($data as $repository)
            $min_width = max($min_width, strlen($repository['shortpath']));
        $headers["Repository"]["min_width"] = $min_width;
        $tableInfo = Output::displayTable($headers, array());
        
        foreach ($data as $repository)
        {
            if(is_dir($repository['path']))
            {
                Output::outputWarning("The {$repository['path']} folder already exists. Skipped.");
                continue;
            }
            $retval = SVNAdminExtended::createRepository($repository['path']);
            if(!$retval)
            {
                Output::outputError("The {$repository['path']} folder cannot be created.");
                continue;
            }
            if(!Arguments::hasOption('debug'))
                $info = SVNAdminExtended::loadDump($repository['path'], $repository['dumpFile']);
            else
                $info = array('revision' => 0);
            $repository['revision'] = $info['revision'];
             
            Output::displayTableBody($tableInfo, array($repository));
        }
        
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