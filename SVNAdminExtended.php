<?php
final class SVNAdminExtended {
    
    static function findRepositories($path, $maxRecursionDepth = 1)
    {
        $folder = realpath($path);
        $repositories = array();
        
        if(file_exists($folder.DS.'format'))
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
        $info['schemaVersion'] = (int)file_get_contents($path . DS . 'format');
        return $info;
    }
}