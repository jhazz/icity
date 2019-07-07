<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq;

class Template {
  public $templateSource;
  public $templateSplitted;
  public $blocks;
  public $filename;
  public $templatesPath;
  public $cachePath;
  public $cacher;

  #static public $defaultTemplatesPath;
  #static public $defaultCacher;

  public static function create(){
    return new Template();
  }

  public function setTemplatePath($path){
    $this->templatesPath=$path;
  }
  public function setCachePath($path){
    $this->cachePath=$path;
  }

  public function readTemplate($from){
    $filenameBase=basename($from,'html');
    $this->filename=$this->templatesPath.'/'.$filenameBase.'.html';
    $filenameParsed=$this->cachePath.'/'.$filenameBase.'.parsed';
    $mustReparsed=true;
    if(!file_exists($this->filename)) {
      trigger_error (\doq\t('tpl_not_found',$this->filename),E_USER_ERROR);
      return false;
    } else {
      $timeSource=filemtime($this->filename);
      if(file_exists($filenameParsed)) {
        $timeParsed=filemtime($filenameParsed);
        if(($timeSource) && ($timeParsed) && ($timeSource===$timeParsed)) {
          $mustReparsed=false;
        }
      }
    }
    # ВРЕМЕННО! Для отладки работы с шаблонами - всегда перекомпилирует шаблон
    $mustReparsed=true;

    if ($mustReparsed) {
      try {
        $this->templateText=file_get_contents($this->filename);
      }  catch (Exception $e) {
        trigger_error(\doq\t('tpl_not_readable',$this->filename),E_USER_ERROR);
        return false;
      }
      $this->templateSplitted=preg_split('/\{\%(.*?)\%\}/', $this->templateText,-1,PREG_SPLIT_DELIM_CAPTURE);
      $this->rootBlock=array('tag'=>'root');
      $this->parse(0,$this->rootBlock);
      file_put_contents($filenameParsed, serialize($this->rootBlock));
      touch($filenameParsed,$timeSource);
    } else {
      $this->rootBlock=unserialize(file_get_contents($filenameParsed));
      print "reused saved template from $filenameParsed<br>";
    }
    return $this;
  }

  private function parse($srcBlockNo=0,&$parentBlock) {
    $cnt=count($this->templateSplitted);
    for($blockNo=$srcBlockNo; $blockNo<$cnt; $blockNo++){
      $block=&$this->templateSplitted[$blockNo];
      if(!($blockNo & 1)) {
        # текстовый блок просто добавляется
        $s=trim($block);
        #if($parentBlock['tag']=='fragment'){
        #  print 'adding '.$s;
        #}
        if($s!='') {
          if(!isset($parentBlock['@'])) {
            $parentBlock['@']=[];
          }
          array_push($parentBlock['@'],$s);
        }
      } else {
        $arr=array();
        $strBlock=$block;
        $count=preg_match_all('(((\w*?)\s*=\s*(["\'])(.*?)\3\s*?)|\w+)',$block,$arr);
        $block=array('tag'=>strtolower($arr[0][0]));
        for($j=1;$j<$count;$j++){
          $paramName=$arr[2][$j];
          if (!$paramName) {
            trigger_error(\doq\t('tpl_error_in_line',$this->filename,$strBlock),E_USER_ERROR);
            return -1;
          }
          $paramValue=$arr[4][$j];
          $block['params'][$paramName]=$paramValue;
        }
        if(isset($block['tag'])) {
          if(($block['tag']=='begin')||($block['tag']=='fragment')){
            $block['@']=array();
            $newPos=$this->parse($blockNo+1,$block);
            if($newPos==-1) {
              break;
            } else {
              $blockNo=$newPos;
            }
          }
          if($block['tag']=='end'){
            return $blockNo;
          }
        }
        if(!isset($parentBlock['@'])) {
          $parentBlock['@']=array();
        }
        array_push($parentBlock['@'],$block);
      }
    }
    return $blockNo;
  }

}
?>
