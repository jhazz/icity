<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq;
class Cacher {
  public static function create(&$cacheParams) {
    $cacheType=$cacheParams['#type'];
    switch($cacheType) {
      case 'serialfile': 
        return new SerialFileCacher($cacheParams);
      case 'jsonfile':
        return new JSONFileCacher($cacheParams);
      case 'memcache':
        return new MemcacheCacher($cacheParams);
      default:
         trigger_error (\doq\t('Unknown cacher type [%s]',$cacheType),E_USER_ERROR);
         return false;
      end;
    }
  }
}

class SerialFileCacher {
  public $cacheFolder;
  public $filePrefix;
  public $fileSuffix;
  
  function __construct(&$cacheParams) {
    $s=$cacheParams['#cacheFolderPath'];
    if(!$s) {
      trigger_error(\doq\t('Undefined parameter #cacheFolderPath in cache config'),E_USER_ERROR);
      $s=getcwd();
    } else {
      if($s[0]=='/') {
        $s=pathinfo($s,PATHINFO_DIRNAME);
      } else {
        $s=getcwd().'/'.$s;
      }
    }
    
    $this->cacheFolder=$s;
    $this->filePrefix=(isset($cacheParams['#filePrefix'])?$cacheParams['#filePrefix']:'');
    $this->fileSuffix=(isset($cacheParams['#fileSuffix'])?$cacheParams['#fileSuffix']:'.txt');
    
    $tryUseAny=false; # try to use any folder for cache
    
    if(!file_exists($this->cacheFolder)) {
      if (isset($cacheParams['#forceCreateFolder'])) {
        if (mkdir($this->cacheFolder,0660,true)===false) {
          trigger_error(\doq\t('Unable to create cache folder [%s]. Use local or temporary instead'),E_USER_WARNING);
          $tryUseAny=true;
        }
      } else {
        trigger_error(\doq\t('Cache folder [%s] not found'),E_USER_WARNING);
        $tryUseAny=true;
      }
    }
    
    if ($tryUseAny) {
      if (function_exists('sys_get_temp_dir')) {
        $this->cacheFolder=sys_get_temp_dir();
      } else {
        $this->cacheFolder=getcwd();
      }
    }
    
  }
  function get($mustHaveTime,$objectId) {
    $fileName=$this->cacheFolder.'/'.$this->filePrefix.$objectId.$this->fileSuffix;
    if (file_exists($fileName) && (filemtime($fileName)===$mustHaveTime)) {
      $data=unserialize(file_get_contents($fileName));
      return [true,&$data];
    } else {
      return [false,NULL];
    }
  }
  
  function put ($setTime,$objectId,&$data) {
    $fileName=$this->cacheFolder.'/'.$this->filePrefix.$objectId.$this->fileSuffix;
    file_put_contents($fileName,serialize($data));
    touch($fileName,$setTime);
  }
  
}

?>