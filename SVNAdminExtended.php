<?php
final class SVNAdminExtended {
    
    static function findRepositories($path, $maxRecursionDepth = 1)
    {
        $folder = realpath($path);
        $repositories = array();
        
        if(file_exists($folder.DS.'db'.DS.'format'))
        {
            $repositories[$folder] = self::getRepositoryInfo($folder);
            return $repositories;
        }
        
        if(!$maxRecursionDepth)
            return $repositories;
        
        $handler = opendir($folder);
        while (($item = readdir($handler)) !== FALSE) 
        {
            if($item == '.' || $item == '..')
                continue;
            if($maxRecursionDepth && is_dir($folder.DS.$item))
                $repositories = array_merge($repositories, self::findRepositories($folder.DS.$item, $maxRecursionDepth - 1));
        }
        closedir($handler);

        return $repositories;
    }
    
    static function getRepositoryInfo($path)
    {
        $info = SVNLook::info($path);
        if(!is_array($info))
            return NULL;
        $info['schemaVersion'] = (int)file_get_contents($path . DS . 'db' . DS . 'format');
        return $info;
    }
    
    static function pathToBackupName($path, $basePath = NULL)
    {
        $backupName = ltrim($path, DS);
        
        if($basePath)
        {
            $basePath = realpath($basePath);
            $basePath = trim($basePath, DS);
            foreach(explode(DS, $basePath) as $chunk)
                if(strpos($backupName, $chunk) === 0 && strlen($chunk) != strlen($backupName))
                {
                    $backupName = substr($backupName, strlen($chunk));
                    $backupName = ltrim($backupName, DS);
                }    
        }
        
        $backupName = str_replace(DS, ")", $backupName);
        
        return $backupName;
    }
    
    static function getVersion()
    {
        static $version = null;
        if($version)
            return $version;
            
        $command = "svnadmin --version";
        
        $retval = NULL;
        $output = array();
        exec($command, $output, $retval);
        
        if($retval != 0)
            return NULL;
            
        $m = array();
        if(!preg_match("/svnadmin[^\w]+version[^\w]+([0-9.]+)/", $output[0], $m))
            return NULL;
        $version = $m[1]; 
            
        return $version;
    }
    
    static function getShortVersion()
    {
        $svnadminVersion = self::getVersion();
        $svnadminVersion = preg_replace('/(\d\.\d).*/', '$1', $svnadminVersion);
        return $svnadminVersion;
    }
    
    static function isRecommendedUpgrading($schemaVersion)
    {
        $svnadminVersion = self::getVersion();
        $svnadminVersion = preg_replace('/(\d\.\d).*/', '$1', $svnadminVersion);
  
        switch($schemaVersion)
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
        if(version_compare($svnadminVersion, $version, '>'))
            return TRUE;
    
        return FALSE;
    }
}