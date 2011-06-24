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
}