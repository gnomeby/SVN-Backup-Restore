<?php

/**
 * @author Andrey Niakhaichyk <andrey@niakhaichyk.org>
 * 
 * @name svnbackup
 * @version 2.0.0
 * 
 */

require_once 'configure.php';
define('DEFAULT_MAX_DEPTH', 2);
$optionsMap = array(
    'r' => 'recursive',
    'R' => 'recursive'
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
}
elseif(Arguments::getArgnum() == 1)
{
    $directory = Arguments::getFirstArg();
    if(!FileSystem::isDirectoryReadable($directory))
        Output::displayErrorAndExit("The $directory is not readable directory.");

    var_dump(SVNAdminExtended::findRepositories($directory, Arguments::hasOption('recursive') ? 10 : 1));
}

function help()
{
  return <<<HELP
@name @version 
The tool for backuping subversion repositories on server side.

@name REPOSITORIES_PATH
  * scan repositories and show info about them 

HELP;
}
