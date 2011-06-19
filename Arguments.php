<?php
final class Arguments {
    
    static $args = null;
    static $options = null;
        
    static function parse()
    {
        $argc = $_SERVER['argc'];
        $argv = $_SERVER['argv'];
    
        self::$args = self::$options = array();
        for($i = 1; $i < $argc; $i++)
        {
          if(preg_match("/^--/", $argv[$i]))
          {
              $option = preg_replace("/^--/", "", $argv[$i]);
              $value = null;
              if(strpos($option, '=') !== FALSE)
                  list($option, $value) = explode("=", $option, 2);
              
              self::$options[$i] = array('name' => $option, 'value' => $value, 'num' => $i);
          }
          elseif(preg_match("/^-./", $argv[$i]))
          {
              $option = preg_replace("/^-/", "", $argv[$i]);
              
              self::$options[$i] = array('name' => $option, 'value' => null, 'num' => $i);
          }
          else
          {
              self::$args[$i] = array('name' => null, 'value' => $argv[$i], 'num' => $i);
          }
        }
    
        return true;
    }
    
    static function getArgnum()
    {
        return count(self::$args);
    }
    
    static function hasOption($requiredOption)
    {
        foreach(self::$options as $option)
            if($option['name'] == $requiredOption)
                return TRUE;
        
        return FALSE;
    }
}