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

require_once 'Arguments.php';
Arguments::parse();

if(Arguments::hasOption('help') || Arguments::getArgnum() == 0)
{
    echo help();
}

function help()
{
  return <<<HELP
@name @version 
The tool for backuping subversion repositories on server side.
  

HELP;
}
