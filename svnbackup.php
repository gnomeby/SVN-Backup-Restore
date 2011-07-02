<?php

/**
 * @author Andrey Niakhaichyk <andrey@niakhaichyk.org>
 * 
 * @name svnbackup
 * @version 2.0.0
 * 
 */

require_once 'configure.php';
require_once 'winsupport.php';
define('DEFAULT_MAX_DEPTH', 2);
$optionsMap = array(
    'r' => 'recursive',
    'R' => 'recursive',
	'q' => 'quiet',
    );

require_once 'FileSystem.php';
require_once 'Output.php';
require_once 'SVNAdminExtended.php';
require_once 'SVNLook.php';

require_once 'Arguments.php';
Arguments::parse($optionsMap);

if(Arguments::hasOption('help') || Arguments::getArgnum() == 0)
{
    echo help();
    commonChecks();
}
elseif(Arguments::getArgnum() == 1) // list
{
    commonChecks();
    
    $directory = Arguments::getFirstArg();
    if(!FileSystem::isDirectoryReadable($directory))
        Output::displayErrorAndExit("The $directory is not readable directory.");
        
        
    // Main part
    if(Arguments::hasOption('quiet'))
        ob_start();
        
    echo "Base directory: " . $directory . PHP_EOL . PHP_EOL;

    
    $data = SVNAdminExtended::findRepositories($directory, Arguments::hasOption('recursive') ? 10 : 1);
    $svnadminVersion = SVNAdminExtended::getVersion();
    foreach($data as $key => $info)
    {
        $data[$key]['backupName'] = SVNAdminExtended::pathToBackupName($key, $directory); 

        $recommendations = "";
        if(SVNAdminExtended::isRecommendedUpgrading($info['schemaVersion']))
            $recommendations .= "Upgrade repository to " . SVNAdminExtended::getShortVersion();
        $data[$key]['recommendations'] = $recommendations;
    }
    
    if(count($data))
    {
        $headers = array(
            "Repository" => array('bind' => 'backupName'), 
            "Revision"  => array('bind' => 'revision', 'align' => 'right'),
        	"Recommendations"  => array('bind' => 'recommendations'),
        );
        $tableInfo = Output::displayTable($headers, $data);
        echo str_repeat('-', $tableInfo['width']) . PHP_EOL;
    }
          
    printf("%d %s", 
        count($data), 
        count($data) > 1 ? "repositories were found." : "repository was found.");
    echo PHP_EOL;

    if(Arguments::hasOption('quiet'))
      $content = ob_get_clean();
}
elseif(Arguments::getArgnum() == 2) // backup
{
    commonChecks();
    
    $directory = Arguments::getFirstArg();
    if(!FileSystem::isDirectoryReadable($directory))
        Output::displayErrorAndExit("The $directory is not readable directory.");
        
    $backupFolder = rtrim(Arguments::getSecondArg(), DS);
    if(!FileSystem::isDirectoryWritable($backupFolder))
        Output::displayErrorAndExit("The $backupFolder is not writable directory.");
        
        
    // Main part
    if(Arguments::hasOption('quiet'))
        ob_start();
        
    echo "Base directory: " . $directory . PHP_EOL . PHP_EOL;
        
    
    $data = SVNAdminExtended::findRepositories($directory, Arguments::hasOption('recursive') ? 10 : 1);
    $svnadminVersion = SVNAdminExtended::getVersion();

    foreach($data as $key => $info)
    {
        $data[$key]['backupName'] = SVNAdminExtended::pathToBackupName($key, $directory); 

        $recommendations = "";
        if(SVNAdminExtended::isRecommendedUpgrading($info['schemaVersion']))
            $recommendations .= "Upgrade repository to " . SVNAdminExtended::getShortVersion();
        $data[$key]['recommendations'] = $recommendations;
    }
    
    if(count($data))
    {
        $headers = array(
            "Repository" => array('bind' => 'backupName'), 
            "Revision"  => array('bind' => 'revision', 'align' => 'right'),
        	"Size"  => array('bind' => 'size', 'align' => 'right'),
        	"Recommendations"  => array('bind' => 'recommendations'),
        );
        $tableInfo = Output::displayTable($headers, array());
        
        foreach($data as $path => &$repository)
        {
            $size = SVNAdminExtended::dumpToFile($path, $backupFolder . DS . $repository['backupName']);
            $repository['size'] = $size; 
            Output::displayTableBody($tableInfo, array($repository));
        }
        
        echo str_repeat('-', $tableInfo['width']) . PHP_EOL;
    }
    
    printf("%d %s", 
        count($data), 
        count($data) > 1 ? "repositories were processed." : "repository was processed.");
    echo PHP_EOL;

    if(Arguments::hasOption('quiet'))
      $content = ob_get_clean();
}

// --- END

function help()
{
  return <<<HELP
@name @version 
The tool for backuping subversion repositories on server side.

@name REPOSITORIES_PATH [parameters]
  * scan repositories and show info about them


ADDITONAL PARAMETERS
      --debug			Debug mode, all write operations will be emulated
-r -R --recursive		Recursive scanning of directories (10 is max level)
-q    --quiet			Don't output anything

HELP;
}

function commonChecks()
{
    $svnlookVersion = SVNLook::getVersion();
    if(!$svnlookVersion)
        Output::displayErrorAndExit("Cannnot detect svnlook version.");
    $svnadminVersion = SVNAdminExtended::getVersion();
    if(!$svnadminVersion)
        Output::displayErrorAndExit("Cannnot detect svnadmin version.");
}
