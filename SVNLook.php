<?php
final class SVNLook {
    
    static function info($path)
    {
        $command = "svnlook info {$path}";
        
        $retval = null;
        $output = array();
        exec($command, $output, $retval);
        
        if($retval != 0)
            return null;
            
        $info = array();
        $info['author'] = $output[0];
        $date = strptime($output[1], "%Y-%m-%d %H:%M:%S %z");
        $info['date'] = mktime($date['tm_hour'], $date['tm_min'], $date['tm_sec'], $date['tm_mon']+1, $date['tm_mday'], $date['tm_year']+1900);
        $info['revision'] = (int)$output[2];
        $info['comment'] = $output[3];
        
        return $info;
    }
}