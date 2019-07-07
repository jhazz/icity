<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq;

function t($s, $arg1=NULL,$arg2=NULL,$arg3=NULL,$arg4=NULL,$arg5=NULL,$arg6=NULL) {
    $lang='ru';
    if (isset($GLOBALS['doqLang'][$lang][$s])) {
        if ($arg1===NULL) {
            return $GLOBALS['doqLang'][$lang][$s];
        } else {
            return sprintf($GLOBALS['doqLang'][$lang][$s],$arg1,$arg2,$arg3,$arg4,$arg5,$arg6);
        }
    } else {
        return sprintf($s,$arg1,$arg2,$arg3,$arg4,$arg5,$arg6);
    }
}


class Translate {
    public $langData;
    public static $single;

    static function init(){
        self::$single=new Translate();
        self::$single->langData=array();
    }

    function loadLanguage($lang){
        if(!isset(self::$single)){
            self::init();
        }
        $file=basename($lang);
        $file='./lang/lang_'.$lang.'.php';
        require_once ($file);
    }
}

?>