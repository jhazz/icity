<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq;

class Render {
  public $out;
  public $errors;
  public $cssStyles;
  public $jsIncludes;

  public static function create(){
    return new Render();
  }

  public function Render() {
    $blockNo=0;
    $this->errors=array();
    $this->out=[];
    $this->cssStyles=array();
    $this->jsIncludes=array();
    $this->fragments=array();
  }

  public function build($dataNode,&$template) {
    $scopeStack=\doq\data\ScopeStack::create($dataNode);
    return $this->fromTemplate($scopeStack,$template,$template->rootBlock);
  }

  public function fromTemplate($scopeStack,&$template,&$block) {
    if(!isset($block['@'])) {
      return false;
    }
    $cnt=count($block['@']);
    $blockNo=0;
    while($blockNo<$cnt) {
      $childBlock=&$block['@'][$blockNo];
      if(is_string($childBlock)) {

        $this->out[]=$childBlock;
      } else {
        $tagName=$childBlock['tag'];
        switch($tagName) {
          case 'put':
          case 'begin':
            $elementName=$childBlock['params']['element'];
            $elementFile='doq/elements/'.$elementName.".php";
            $elementClassName='doq\\elements\\'.$elementName;
            if (!method_exists($elementClassName,$tagName)) {
              if(file_exists($elementFile)){
                require_once($elementFile);
              } else {
                trigger_error(\doq\t('tpl_error_load_element',$elementFile),E_USER_ERROR);
                return -1;
              }
              if (!method_exists($elementClassName,$tagName)) {
                trigger_error(\doq\t('tpl_error_element_no_method',$elementFile,$elementClassName,$tagName),E_USER_ERROR);
                return -1;
              }
            }
            if($tagName=='begin') {
              $result=$elementClassName::begin($scopeStack,$template,$childBlock,$this);
            } else if($tagName=='put'){
              $result=$elementClassName::put($scopeStack,$template,$childBlock,$this);
            }
            break;
          case 'fragment':
            if(!isset($childBlock['params']['id'])) {
                trigger_error(\doq\t('tpl_err_frgm_tag',$elementFile),E_USER_ERROR);
            }
            $this->fragments[$childBlock['params']['id']]=&$childBlock;
            break;
          default:
            $s=$tagName;
            if(isset($childBlock['params'])) {
              foreach($childBlock['params'] as $paramName=>$paramValue){
                $s.=" $paramName=$paramValue";
              }
            }
            $this->out[]="{{?$s}}";
        }
      }
      $blockNo++;
    }
    return true;
  }
}