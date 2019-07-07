<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq\elements;

class Field {
  public static function put($scopeStack,&$template,&$block,&$render){
    if(isset($block['params']['path'])) {
      $path=$block['params']['path'];
    } else $path='';

    if($path!=='') {
      $scopeStack->push($path);
    }
    $scope=$scopeStack->top;
    $render->out[]='<input type="text" value="'.$scope->value().'"><br/><span style="font-size:10px;">{'.$scope->path.'['.$scope->curTupleNo.']}</span>';
    if($path!=='') {
      $scopeStack->pop();
    }

    return true;
  }
}