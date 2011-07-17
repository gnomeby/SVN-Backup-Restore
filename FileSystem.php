<?php
final class FileSystem {
    static function isDirectoryReadable($directory)
    {
        if(!is_dir($directory))
            return FALSE;
        if(!is_readable($directory))
            return FALSE;
            
        return TRUE;
    }
    
    static function isDirectoryWritable($directory)
    {
        if(!is_dir($directory))
            return FALSE;
        if(!is_writable($directory))
            return FALSE;
            
        return TRUE;
    }
    
    static function filesize($filename)
    {
        if(PHP_INT_SIZE > 4)
            return filesize($filename);
        else // Support 32 bit systems
        {
            $output = '';
            $retval = 0;
            exec("ls --size --dereference --block-size=1000 {$filename}", $output, $retval);        
            return $retval > 0 ? FALSE : ("".(int)$output[0]."000");            
        }
    }
}