<?php
final class Output {
    
    static function outputError($error)
    {
        fwrite(STDERR, "[\033[1;31m"."Error"."\033[0m] $error".PHP_EOL);
    }
    
    static function displayErrorAndExit($error)
    {
        self::outputError($error);
        exit;
    }
    
    static function displayTable($headers, $data)
    {
        $tableInfo = array('width' => 0, 'height' => 0);
        
        foreach($headers as $name => $cell)
        {
            $min_width = strlen($name);
            foreach($data as $key => $row)
            {
                if($cell['bind'] == '__key')
                    $min_width = max($min_width, strlen($key));
                else
                    $min_width = max($min_width, strlen($row[$cell['bind']]));
            }

            $headers[$name]['min_width'] = $min_width;  
        }
        
        // Show header names
        $first = TRUE;
        foreach($headers as $name => $cell)
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
        foreach($headers as $name => $cell)
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
        foreach($data as $key => $row)
        {
            $first = TRUE;
            foreach($headers as $name => $cell)
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