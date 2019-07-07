<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/
namespace doq\elements;

class Form {
  public static function begin($scope,&$template,&$block,&$render){
    $render->out[]='[It is a begin of form "'.$block['params']['id'].'"]';
    $cnt=count($block['@']);
    if($cnt) {
      #for($blockNo=0;$blockNo<$cnt;$blockNo++){
      #  $render->out.=" [[$blockNo]] ";
      #}
      $render->fromTemplate($scope,$template,$block);
    }

    return true;
  }
}