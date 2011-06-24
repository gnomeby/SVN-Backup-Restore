<?php
final class SVNLook {
    
    static function info($path)
    {
        $command = "svnlook info {$path}";
        
        $retval = NULL;
        $output = array();
        exec($command, $output, $retval);
        
        if($retval != 0)
            return NULL;
            
        $info = array();
        $info['author'] = $output[0];
        $date = strptime($output[1], "%Y-%m-%d %H:%M:%S %z");
        $info['date'] = mktime($date['tm_hour'], $date['tm_min'], $date['tm_sec'], $date['tm_mon']+1, $date['tm_mday'], $date['tm_year']+1900);
        $info['revision'] = (int)$output[2];
        $info['comment'] = $output[3];
        
        return $info;
    }
    
    static function getVersion()
    {
        static $version = null;
        if($version)
            return $version;
        
        $command = "svnlook --version";
        
        $retval = NULL;
        $output = array();
        exec($command, $output, $retval);
        
        if($retval != 0)
            return NULL;
            
        $m = array();
        if(!preg_match("/svnlook[^\w]+version[^\w]+([0-9.]+)/", $output[0], $m))
            return NULL;
        $version = $m[1]; 
            
        return $version;
    }
}