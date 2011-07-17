<?php
final class Output {
    
    static function outputError($error)
    {
        fwrite(STDERR, "[\033[1;31m"."Error"."\033[0m] $error".PHP_EOL);
    }
    
    static function outputWarning($warning)
    {
        fwrite(STDERR, "[\033[1;33m"."Warn"."\033[0m] $warning".PHP_EOL);
    }
    
    static function displayErrorAndExit($error)
    {
        self::outputError($error);
        exit;
    }
    
    static function displayTable($headers, $data)
    {
        $tableInfo = array('width' => 0, 'height' => 0, 'headers' => $headers);
        
        foreach($headers as $name => $cell)
        {
            $min_width = max(strlen($name), array_key_exists("min_width", $cell) ? $cell["min_width"] : 0);
            foreach($data as $key => $row)
            {
                if($cell['bind'] == '__key')
                    $min_width = max($min_width, strlen($key));
                else
                    $min_width = max($min_width, strlen($row[$cell['bind']]));
            }

            $tableInfo['headers'][$name]['min_width'] = $min_width;  
        }
        
        // Show header names
        $first = TRUE;
        foreach($tableInfo['headers'] as $name => $cell)
        {
            if(!$first)
                echo "  ";
            printf("%-${cell['min_width']}s", $name);
            $first = FALSE;
        }
        echo PHP_EOL;
        $tableInfo['height']++;

        // Show lines        
        $first = TRUE;
        foreach($tableInfo['headers'] as $name => $cell)
        {
            if(!$first)
            {
                echo "  ";
                $tableInfo['width'] += 2;
            }
            echo str_repeat("-", $cell['min_width']);
            $tableInfo['width'] += $cell['min_width'];
            $first = FALSE;
        }
        echo PHP_EOL;
        $tableInfo['height']++;
        
        // Show data
        $tableInfo = self::displayTableBody($tableInfo, $data);

        return $tableInfo;
    }
    
    static function displayTableBody($tableInfo, $data)
    {
        foreach($data as $key => $row)
        {
            $first = TRUE;
            foreach($tableInfo['headers'] as $name => $cell)
            {
                if(!$first)
                    echo "  ";
                    
                $min_width = $cell['min_width'];
                $align_modifier = (!empty($cell['align']) && $cell['align'] == 'right') ? "" : "-";
                if($cell['bind'] == '__key')
                    $data = $key;
                else
                    $data = $row[$cell['bind']];
                    
                printf("%{$align_modifier}{$min_width}s", $data);
                $first = FALSE;
            }
            echo PHP_EOL;
            $tableInfo['height']++;
        }
        
        return $tableInfo;
    }    
}