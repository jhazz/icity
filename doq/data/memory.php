<?php
namespace doq\data\memory;

class Dataset extends \doq\data\Dataset{
  public $name;
  public $model;
  public $mapFieldToColumn;
  public $mapFieldToKey;
  public $records;
  public $localAutoIncValue;
  public $cfgDatasource;
  public $cfgSchema;
  public $cfgDataset;
  public $cfgFields;
  public $cfgKeys;
  public $error;
  public $rowNo;
  public $indexes; # keyTypeNo, array of vectors
  public $hasPrimaryKey;
  #  public $autoIncFieldName;
  #  public $autoIncFieldColNo;
  #  public $localAutoIncValue;

  const KT_PRIMARY=1;
  const KT_UNIQUE=2;
  const KT_NONUNIQUE=3;
  const MAX_KEY_LENGTH=80;
  public static $KEY_TYPES=['primary'=>self::KT_PRIMARY,'unique'=>self::KT_UNIQUE,'nonunique'=>self::KT_NONUNIQUE];

/**
*
* @param mixed $model
* @param mixed $cfgDatasource
* @param mixed $cfgSchema
* @param mixed $datasetName
*
* @return
*/
  public static function createFromSchema(&$model,&$cfgDatasource,&$cfgSchema,$datasetName) {
    # Existance already checked in caller
    $cfgDataset=&$cfgSchema['@datasets'][$datasetName];
    if (!isset($cfgDataset['@fields'])) {
      trigger_error(\doq\t('dataset_no_fields_in_cfg',$datasetName),E_USER_ERROR);
      return [false,NULL];
    }
    return [true,new Dataset($model,$cfgDatasource,$cfgSchema,$cfgDataset,$datasetName)];
  }

  /**
  * Constructs Dataset in memory
  * @param mixed[] $model
  * @param mixed[] $cfgDatasource reference to datasource configuration
  * @param mixed[] $cfgSchema reference to schema configuration
  * @param mixed[] $cfgDataset reference to dataset configuration
  * @param mixed[] $datasetName
  *
  * @return
  */
  public function __construct(&$model,&$cfgDatasource,&$cfgSchema,&$cfgDataset,$datasetName) {
    $this->error=false;
    $this->name=$datasetName;
    $this->model=&$model;
    $this->cfgDatasource=&$cfgDatasource;
    $this->cfgSchema=&$cfgSchema;
    $this->cfgDataset=&$cfgDataset;
    $this->cfgFields=$cfgDataset['@fields']; #copy fields defined in schema
    $this->cfgKeys=(isset($cfgDataset['@keys']))?$cfgDataset['@keys']:NULL;
    $this->mapFieldToKey=[]; # массив, который содержит массивы полей со ссылой на название ключа
    $this->mapFieldToColumn=[];
    $this->hasPrimaryKey=false;


    $columnNo=0;
    foreach ($this->cfgFields as $fieldName=>&$fieldDef) {
#      if(isset($fieldDefs['#isAutoInc'])) {
#        if($this->autoIncField) {
          # TODO Надо перенести в билдер createFromSchema()
#          $this->error=\doq\t('memtable_error_more_than_one_autoinc',$this->name,$this->autoIncField,$fieldName);
#          trigger_error($this->error,E_USER_ERROR);
#        }
#        $this->autoIncFieldName=$fieldName;
#        $this->autoIncFieldColNo=$columnNo;
#        $this->localAutoIncValue=0;
#      } else {
#        $this->autoIncFieldName='';
#      }
      $this->mapFieldToColumn[$fieldName]=$columnNo++;
    }

    if ($this->cfgKeys!==NULL){
      $this->indexes=[];
      foreach($this->cfgKeys as $indexName=>&$keyDefs) {
        if(!isset($keyDefs['@fields'])) {
          trigger_error(\doq\t('dataset_no_fields_in_index',$datasetName,$indexName),E_USER_WARNING);
        }
        foreach ($keyDefs['@fields'] as $i=>$fieldName) {
          if(!isset($this->cfgFields[$fieldName])) {
            # TODO Надо перенести в билдер createFromSchema()
            trigger_error(\doq\t('dataset_key_ref_to_unknown_field',$datasetName,$indexName,$fieldName),E_USER_WARNING);
          } else {
            if (!isset($this->mapFieldToKey[$fieldName])) {
              $this->mapFieldToKey[$fieldName]=[];
            }
            $keyType=(isset($keyDefs['#type']))?$keyDefs['#type']:'unique';
            if(isset(self::$KEY_TYPES[$keyType])) {
              $keyTypeNo=self::$KEY_TYPES[$keyType];
            } else {
              trigger_error(\doq\t('Unknown index type \'%s\' in %s.%s',$keyDefs['#type'],$datasetName,$indexName),E_USER_WARNING);
              $keyTypeNo=self::KT_UNIQUE;
            }
            if($keyTypeNo==self::KT_PRIMARY) $this->hasPrimaryKey=true;
            # indexes[] массив элементов, которые содержат 0-й элемент - номер типа индекса, 1-й элемент - массив векторов
            $this->indexes[$indexName]=[$keyTypeNo,[]];
            $this->mapFieldToKey[$fieldName][]=$indexName;
          }
        }
      }
    }
    $this->recordHolders=array();
    $this->rowNo=0;
  }

  public function put($keyValuePairs){
#    if ($this->autoIncFieldName!=='') {
#      if(isset($keyValuePairs[$this->autoIncFieldName])) {
#        trigger_error(\doq\t('memtable_error_try_insert_autoincfield',$this->name,$this->autoIncFieldName),E_USER_WARNING);
#      }
      # Нельзя хранить автоинкрементный номер без 100% уверенности, что отметка о том
      # что этот временный автоинкремент не будет посчитан за обычное значение
      #$newLocalAutoIncValue=$this->localAutoIncValue+1; # do not autoincrement till don't over the insert
      #$keyValuePairs[$this->autoIncFieldName]=$newAutoIncValue;
#    }
    $keyCombination=[];
    $newRecordColumns=[];
    foreach ($keyValuePairs as $fieldName=>&$fieldValue) {
      if(!isset($this->mapFieldToColumn[$fieldName])) {
        $this->error=\doq\t('memtable_error_inserting_unknown_field',$this->name,$fieldName);
        trigger_error($this->error,E_USER_ERROR);
        return false;
      }
      $colNo=$this->mapFieldToColumn[$fieldName];
      $newRecordColumns[$colNo]=$fieldValue; # Copy field value from reference
    }

    # генерируем составные ключи из значений полей в $keyCombination[$indexName]
    foreach($this->mapFieldToKey as $fieldName=>&$refsToindexes) {
      foreach ($refsToindexes as $i=>&$indexName) {
        $index=&$this->indexes[$indexName];
        $keyTypeNo=$index[0];
        if(!$keyTypeNo) {
          trigger_error('NULL keyTypeNo!!!',E_USER_ERROR);
          return false;
        }
        if((!isset($keyValuePairs[$fieldName])||($keyValuePairs[$fieldName])==='')) {
          switch ($keyTypeNo) {
            case self::KT_PRIMARY:
              $this->error=\doq\t('dataset_try_insert_empty_pkey',$this->name,$indexName);
              trigger_error($this->error,E_USER_ERROR);
              return false;
            case self::KT_UNIQUE:
              $this->error=\doq\t('dataset_try_insert_empty_unique_index_val',$this->name,$indexName);
              trigger_error($this->error,E_USER_ERROR);
              return false;
            }
          } else {
            # Составные ключи и одинарные всегда хранятся в строках с отсечением по маскимальному кол-ву симв
            # в виде [keyval1][keyval2]...
            $value=$keyValuePairs[$fieldName].'';
            if(strlen($value)>self::MAX_KEY_LENGTH) {
              $value=substr($value,0,self::MAX_KEY_LENGTH);
            }
            if(!isset($keyCombination[$indexName])){
              $keyCombination[$indexName]='['.$value.']';
            } else {
              $keyCombination[$indexName].='['.$value.']';
            }
          }
        }
      }

      # Проверка дублирования ключа
      foreach ($this->indexes as $indexName=>&$index) {
        $keyTypeNo=$index[0];
        if($keyTypeNo==self::KT_PRIMARY) {
          $vectors=&$index[1];
          $keyStr=&$keyCombination[$indexName];
          if(isset($vectors[$keyStr])) {
            $this->error=\doq\t('dataset_try_insert_dub_pkey',$this->name,$keyStr);
            trigger_error($this->error,E_USER_WARNING);
            return false;
          }
        }
      }

      # Каждая record состоит из массива элементов.
      # 0й - это сами колонки данных
      # 1й - это признак того, что autoinc поле сгенерировано не базой данных
      # потом можно будет еще что-то добавить
      $myRowNo=$this->rowNo;
      # TODO ВОТ ТУТ НАДО ПЕРЕДЕЛЫВАТЬ!!!
      $this->records[$myRowNo]=[&$newRecordColumns,false];
      $this->rowNo++;

      # Сохраняем ссылку на номер моей строки в вектор ключей
      foreach ($this->indexes as $indexName=>&$index) {
        $keyTypeNo=$index[0];
        $vectors=&$index[1];
        $keyStr=&$keyCombination[$indexName];
        switch($keyTypeNo) {
          case self::KT_PRIMARY:
          case self::KT_UNIQUE:
            $vectors[$keyStr]=$myRowNo;
            break;
          case self::KT_NONUNIQUE:
            if (!isset($vectors[$keyStr])) {
              $vectors[$keyStr]=[$myRowNo];
            } else {
              array_push($vectors[$keyStr],$myRowNo);
            }
            break;
        }
    }
    return true;
  }
  # THEMODELNAME:fields/ANY_FIELD_NAME
  public function getItemsByPath($path){
   if (!is_array($path)){
      $path = explode('/', $path);
    }

    $current =& $this->data;
    foreach ($path as $part) {
        $current =& $current[$part];
    }
    return $current;
  }

}
?>