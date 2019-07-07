<?php
namespace doq\data\mysql;

class Scripter {
  public $plan;

  public static function create() {
    return new Scripter();
  }

  public function buildSelectScript(&$planEntry) {
    $this->tableAliases=[];
    $this->tableAliasNo=1;
    $this->columnList=[];
    $this->joins=[];
    $this->datasourceName=$planEntry['#dataSource'];
    $noParent=NULL;
    if (!$this->collectJoinsRecursive($planEntry)) {
      return false;
    }
    $s='';
    foreach($this->columnList as $icol=>&$col) {
      $s.=(($s!=='')?',':'').$col[0].'.'.$col[1];
    }
    $s='SELECT '.$s." FROM \n";
    if(count($this->joins)){
      $joinstr='';
      foreach($this->joins as $ijoin=>&$join) {
        list($jtype,$ltab,$lfield,$rtab,$rfield)=$join;
        if($ijoin===0) {
          $js=$this->tableAliases[$ltab].' AS '.$ltab.' '.$jtype.' JOIN '.$this->tableAliases[$rtab].' AS '.$rtab.' ON '.$ltab.'.'.$lfield.'='.$rtab.'.'.$rfield;
        } else {
          $js=$jtype.' JOIN '.$this->tableAliases[$rtab].' AS '.$rtab.' ON '.$ltab.'.'.$lfield.'='.$rtab.'.'.$rfield;
        }
        if($ijoin){
          $joinstr="($joinstr)\n ";
        }
        $joinstr.=$js;
      }
      $s.=$joinstr;
    } else {
      $s.=$this->tableAliases['ta1'].' AS ta1';
    }
    return $s;
  }

  private function collectJoinsRecursive(&$entry,$parentAlias='',$parentField=false) {
    $datasetDef=&$entry['@dataset'];
    $schemaName=&$datasetDef['#schema'];
    $datasetName=&$datasetDef['#datasetName'];
    $tableAlias='ta'.$this->tableAliasNo;
    $dataset['#tableAlias']=$tableAlias;


    if(isset($datasetDef['#keyField'])){
      $keyField=$datasetDef['#keyField'];
      $datasetDef['#keyFieldScriptName']=$tableAlias.'.'.$keyField;
    } else {
      unset ($keyField);
    }

    $this->tableAliases[$tableAlias]=strtolower($datasetName);
    $this->tableAliasNo++;

    if($parentField!==false) {
      $this->joins[]=['LEFT',$parentAlias,$parentField,$tableAlias,$keyField];
    }

    foreach($datasetDef['@fields'] as $i=>&$field) {
      $originField=$field['#originField'];
      $field['#scriptField']=$tableAlias.'.'.$originField;
      if(isset($field['#kind'])){
        if($field['#kind']=='lookup'){
          $ref=$field['#ref'];
          list ($RdatasourceName,$RschemaName,$RdatasetName,$isROtherDatasource)
               =\doq\data\Scripter::getDatasetPathElements($ref,$this->datasourceName,
               $schemaName,$datasetName);
          if(isset($field['#refType']) && $field['#refType']=='join') {
            if($isROtherDatasource) {
              trigger_error(\doq\t('Strange join to the other Datasource %s:%s/%s. Cancel join',$RdatasourceName,$RschemaName,$RdatasetName),E_USER_ERROR);
              return false;
            }
            $this->columnList[]=[$tableAlias,$originField];
            $this->collectJoinsRecursive($field,$tableAlias,$originField);
          } else {
            # not the join
            $this->columnList[]=[$tableAlias,$originField];
          }
        }
      } else {
        # plain field
        $this->columnList[]=[$tableAlias,$originField];
      }
    }
    return true;
  }
}


class Scope extends \doq\data\Scope {
  //inherited public $path;
  //inherited public $dataNode;
  /** @var array|null Текущий индекс */
  public $curIndex;
  /** @var array|null Текущий массив агрегата индекса, именно по нему производится обход через seek */
  public $curIndexAggregate;
  /** @var integer позиция в индексе, если он есть или позиция в агрегате индекса, или позиция в dataset->rows */
  public $curTupleNo;
  /** @var array|null ссылка на ту запись данных, на которую указывает позиция */
  public $curTuple;
  /** @var int одно из значений констант SW_ */
  public $curType;
  /** @var int длина выборки индекса по которому перемещаем указатель через seek*/
  public $curIndexLen;

  public function __construct(\doq\data\DataNode $dataNode,$indexName='',$indexKey=NULL,$datasetScope=NULL,$path='') {
    $this->dataNode=$dataNode;
    $this->path=$path;
    $this->curType='';
    $this->curTupleNo=0;
    $this->curIndexLen=0;
    if($indexName!='') {
      $this->curIndex=&$dataNode->dataObject->resultIndexes[$indexName];
      $this->curIndexAggregate=NULL;
      switch($this->curIndex['#type']) {
        case 'unique':
          if($indexKey!==NULL) {
            $this->curType=self::SW_ONE_INDEX_RECORD;
            $this->curIndexAggregate=&$this->curIndex['@indexedTuples'][$indexKey];
            $this->curTuple=&$this->curIndexAggregate;
          } else {
            $this->curType=self::SW_INDEX_RECORDS;
            $this->curIndexAggregate=&$this->curIndex['@indexedTuples'];
            reset($this->curIndexAggregate);
            $this->curIndexLen=count($this->curIndexAggregate);
          }
          break;
        case 'nonunique':
          $this->curType=self::SW_AGGREGATED_INDEX_RECORDS;
          if($indexKey!==NULL) {
            if(!isset($this->curIndex['@indexedTuples'][$indexKey])) {
              #TODO: надо будет проверять может ли вызывать ошибку попытка получить ссылочное значение по неправильному значению ключа
              $this->curIndexAggregate=NULL;
              $this->curIndexLen=0;
            } else {
              $this->curIndexAggregate=&$this->curIndex['@indexedTuples'][$indexKey];
              $this->curIndexLen=count($this->curIndexAggregate);
            }

          } else {
            trigger_error(\doq\t('FATAL ERROR! Do not use aggregated index without master value defining scope window'),E_USER_ERROR);
          }
          break;
        default:
          trigger_error(\doq\t('FATAL ERROR! Unknown index type [%s]',$index['#type']),E_USER_ERROR);
      }
    } else {
      $this->curIndex=NULL;
      if($this->dataNode->type==\doq\data\DataNode::NT_COLUMN) {
        $this->curType=self::SW_ONE_FIELD;
        $this->curTuple=&$datasetScope->curTuple;
      } else {
        $this->curType=self::SW_ALL_RECORDS;
        $this->curIndexLen=count($this->dataNode->dataObject->tuples);
      }
    }
  }


  public function seek($to=self::SEEK_TO_NEXT){
    $EOT=false;
    switch ($this->curType) {
      # Nothing to do, don't move parent dataset scope
      case self::SW_ONE_FIELD:
        $EOT=true;
        break;
      case self::SW_INDEX_RECORDS:
      case self::SW_AGGREGATED_INDEX_RECORDS:
        #TODO reset,next,end создают копию массива, которая нам не нужна. Надо избавиться от таких функций
        if (!is_array($this->curIndexAggregate)) {
          $this->curTuple=NULL;
          #trigger_error(\doq\t('scope::seek called to move inside aggregated index but curIndexAggregate is not an array'),E_USER_ERROR);
          return true;
        }
        switch ($to) {
          case self::SEEK_TO_START:
            reset($this->curIndexAggregate);
            $this->curTupleNo=0;
            break;
          case self::SEEK_TO_NEXT:
            if(next($this->curIndexAggregate)!==false) {
              $position=$this->curTupleNo+1;
            } else $EOT=true;
            break;
          case self::SEEK_TO_END:
            end($this->curIndexAggregate);
            $this->curTupleNo=$this->curIndexLen-1;
            break;
        }
        $k=key($this->curIndexAggregate);
        if(isset($this->curTuple)) unset($this->curTuple);
        $this->curTuple=&$this->curIndexAggregate[$k];
        break;
      case self::SW_ALL_RECORDS:
        if($this->curIndexLen) {
          switch($to) {
            case self::SEEK_TO_START:
              $position=0;
              break;
            case self::SEEK_TO_NEXT:
              $position=$this->curTupleNo+1;
              break;
            case self::SEEK_TO_END:
              $position=$this->curIndexLen-1;
              break;
            default:
              trigger_error('Unknown seeking type '.$origin,E_USER_ERROR);
              return false;
          }
          if($position >= $this->curIndexLen) {
            $position=$this->curIndexLen-1;
            $EOT=true;
          }
          $this->curTupleNo=$position;
        }
        if(isset($this->curTuple)) unset($this->curTuple);
        $this->curTuple=&$this->dataNode->dataObject->tuples[$this->curTupleNo];
        break;
    }
    return $EOT;
  }

  /** @return \doq\data\Scope */
  public function makeDetailScope($path,$masterFieldName) {
    $masterDataNode=$this->dataNode;
    $masterDataset=$masterDataNode->dataObject;
    $detailDataNode=$masterDataNode->childNodes[$masterFieldName];
    $masterFieldNo=$detailDataNode->dataObject->planEntry['#masterFieldNo'];
    $masterTupleFieldNo=$masterDataset->planEntry['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
    $masterValue=$this->curTuple[$masterTupleFieldNo];
    $detailIndexName=$masterDataset->planEntry['@detailIndexByFieldNo'][$masterFieldNo];
    return $detailDataNode->dataObject->makeScope($detailDataNode,$detailIndexName,$masterValue,$path.'/'.$masterFieldName);
  }

  public function asString() {
    switch($this->dataNode->type) {
      case \doq\data\DataNode::NT_COLUMN:
        $fieldDef=&$this->dataNode->parameters;
        if(!isset($fieldDef['#tupleFieldNo'])) {
          trigger_error('Unknown #tupleFieldNo in dataset for path '.$this->path,E_USER_ERROR);
          return '{ERROR}';
        }
        $fieldType=isset($fieldDef['#type'])?$fieldDef['#type']:'string';
        $fieldLabel=isset($fieldDef['#label'])?$fieldDef['#label']:$fieldDef['#field'];
        $tupleFieldNo=$fieldDef['#tupleFieldNo'];
        if($tupleFieldNo>=count($this->curTuple)) {
          trigger_error(\doq\t('Column index %s is out of data columns range %s',$tupleFieldNo,count($this->curTuple)),E_USER_ERROR);
        }
        $value=$this->curTuple[$tupleFieldNo];
        return $value;
      break;
    }
  }

  public function value() {
    if($this->dataNode->type===\doq\data\DataNode::NT_COLUMN) {
      return $this->curTuple[$this->dataNode->parameters['#tupleFieldNo']];
    }
  }
}

class Dataset extends \doq\data\Dataset{
  public $params;
  public $connection;
  public $tuples;
  public $resultIndexes;
  public static $useFetchAll;

  public function __construct(&$planEntry,$id) {
    $this->planEntry=&$planEntry;
    $this->id=$id;
  }

  public function makeScope(\doq\data\DataNode $dataNode, $indexName='',$indexKey=NULL,$datasetScope=NULL, $path='') {
    return new Scope($dataNode,$indexName,$indexKey,$datasetScope,$path);
  }

  public function connect(){
    list($ok,$this->connection)=\doq\data\Connection::getDataConnection($this->planEntry['#dataConnection']);
    return $ok;
  }

  public function dumpIndexes() {
    foreach($this->resultIndexes as $indexName=>&$index) {
      print '<table border=1><tr><td colspan=20>Index name: "' .$indexName.'", type:'.$index['#type'].'</td></tr>';
      $recordVectors=&$index['@indexedTuples'];
      $indexByTupleFieldNo=$index['#indexByTupleFieldNo'];
      switch($index['#type']) {
        case 'unique':
          foreach($recordVectors as $value=>&$data) {
            print '<tr><td bgcolor="#ffff80">'.$value.'</td>';
            foreach($data as $col=>&$value) {
              if ($col!=$indexByTupleFieldNo) $bgColor='#a0ffa0'; else $bgColor='#a0a0a0';
              print '<td bgcolor="'.$bgColor.'">'.$value.'</td>';
            }
            print '</tr>';
          }
          break;
        case 'nonunique':
          foreach($recordVectors as $value=>&$portions) {
            $count=sizeof($portions);
            print '<tr><td bgcolor="#ffaa80" rowspan='.$count.'>'.$value.'</td>';;
            foreach($portions as $i=>&$data) {
              if($i>0) print '<tr>';
              foreach($data as $col=>&$value) {
                if ($col!=$indexByTupleFieldNo) $bgColor='#a0ffa0'; else $bgColor='#a0a0a0';
                print '<td bgcolor="'.$bgColor.'">'.$value.'</td>';
              }
              print '</tr>';
            }
          }
          break;
      }
      print '</table>';
    }
  }


  public function read(&$params) {
    $s=$this->planEntry['#readScript'];
    $where=[];
    if (isset($params['@filter'])) {
      foreach ($params['@filter'] as $i=>&$param) {
        switch($param['#operand']) {
          case 'IN':
            $columnId=$param['#columnId'];
            #  ЭТОТ $fieldName=$this->planEntry['@dataset']['@fields'][$columnNo]['#scriptField'];
            $res=\doq\data\View::getFieldByColumnId($columnId,$this->planEntry);
            if(!$res[0]) {
              trigger_error(\doq\t('Column [# %d] not found in %s',$columnId,'dataset'),E_USER_ERROR);
            }
            $fieldDef=&$res[1];
            $fieldName=$fieldDef['#scriptField'];
            $where[]=$fieldName.' IN ('.implode($param['@values'],',').')';
            break;
        }
      }
    }
    if(count($where)) {
      $s.=' WHERE ('.implode($where,') AND (').')';
    }
    $s.=';';
    print "<table border=1><tr><td>$s</td></tr></table>";
    $this->mysqlresult=$this->connection->mysqli->query($s);
    if($this->mysqlresult!==false){
      if(self::$useFetchAll) {
        $this->tuples=$this->mysqlresult->fetch_all(MYSQLI_NUM);
      } else {
        $this->tuples=[];
        while ($tuple=$this->mysqlresult->fetch_row()) {
          $this->tuples[]=&$tuple;
          unset($tuple);
        }
      }
      $this->mysqlresult->close();
      unset($this->mysqlresult);
      if (isset($this->planEntry['@resultIndexes'])) {
        print '<table><tr><td><pre>';
        print_r($this->planEntry['@resultIndexes']);
        print '</pre></td></tr></table>';
        foreach($this->planEntry['@resultIndexes'] as $i=>&$resultIndexDef) {
          $indexName=$resultIndexDef['#name'];
          $indexByTupleFieldNo=$resultIndexDef['#byTupleFieldNo'];
          $indexType=$resultIndexDef['#type'];
          switch($indexType) {
            case 'unique':
              $indexedTuples=[];
              # тупо проходим по всем данным. Возможно есть способ более скоростного обхода
              # когда индекс имеет тип 'unique' тогда каждый вектор - это ссылка на строку
              # с уникальным значением
              foreach($this->tuples as $tupleNo=>&$tuple) {
                $value=$tuple[$indexByTupleFieldNo];
                if(!is_null($value)) {
                  if(isset($indexedTuples[$value])) {
                    trigger_error(\doq\t('Repeating unique value %s in index %s',$value,$indexName),E_USER_ERROR);
                  } else $indexedTuples[$value]=&$tuple;
                }
              }
              $this->resultIndexes[$indexName]=[
                '#type'=>$indexType,
                '#indexByTupleFieldNo'=>$indexByTupleFieldNo,
                '@indexedTuples'=>&$indexedTuples
                ];
              break;
            case 'nonunique':
              $indexedTuples=[];
              # когда индекс имеет тип 'nonunique' тогда каждый вектор - это
              # набор ссылок на строки
              # с найденными значениями индекса
              foreach($this->tuples as $tupleNo=>&$tuple) {
                $value=$tuple[$indexByTupleFieldNo];
                if(!is_null($value)) {
                  if(!isset($indexedTuples[$value])) {
                    $indexedTuples[$value]=[&$tuple];
                  } else $indexedTuples[$value][]=&$tuple;
                }
              }
              $this->resultIndexes[$indexName]=[
                '#type'=>$indexType,
                '#indexByTupleFieldNo'=>$indexByTupleFieldNo,
                '@indexedTuples'=>&$indexedTuples
                ];
              break;
          }
        }
        $this->dumpIndexes();
      }
    } else {
      $this->tuples=false;
    }
  }

  public function uniqueValuesOfTupleSetField($tupleFieldNo) {
    if (isset($this->tuples)) {
        $valueSet=[];
        foreach($this->tuples as $tupleNo => &$tuple) {
          $v=&$tuple[$tupleFieldNo];
          if(!is_null($v)) $valueSet[$v]=1;
        }
        return [true,array_keys($valueSet)];
    } else {
      return [false,NULL];
    }
  }


  public function dumpData(){
    $fieldList=[];
    \doq\data\View::collectFieldList($this->planEntry,$fieldList);
    $s='';
    foreach($fieldList as $i=>$field) {
      if (!isset($field['#tupleFieldNo'])) {continue;}

      $s.='<td>#id:'.$field['#columnId']
        .'<br/>#tupleFieldNo:'.$field['#tupleFieldNo']
        .'<br/>#field:['.$field['#field'].']'
        .'<br>#originField:['.$field['#originField'].']'
        .'<br>#scriptField:['.$field['#scriptField'].']'
        .(isset($column['#label'])?'<br/>#label:'.$field['#label']:'').'</td>';
    }
    print '<table class="dpd" border=1><tr valign="top" bgcolor="#ffffa0">'.$s.'</tr>';
    foreach($this->tuples as $tupleNo=>&$tuple) {
      $s='';
      foreach($tuple as $j=>&$v) {
        $s.='<td>'.$v.'</td>';
      }
      print '<tr>'.$s.'</tr>';
    }
    print '</table>';
  }
}



class Connection {
  public $isConnected;
  public $mysqli;

  public function __construct($connectionName, &$cfgConnection) {
    $dbCfg=&$cfgConnection['@params'];
    $host=$dbCfg['host'];
    $this->provider='mysql';
    $this->mysqli=new \Mysqli($host,$dbCfg['login'],$dbCfg['password'],$dbCfg['dbase'],$dbCfg['port']);
    if ($this->mysqli->connect_error) {
      trigger_error(\doq\t('dataset_error_connect_dbconnection',$connectionName),E_USER_ERROR);
      $this->isConnected=false;
      unset($this->mysqli);
    } else {
      $this->isConnected=true;
      $this->mysqli->set_charset('utf8');
    }
  }
}

Dataset::$useFetchAll=method_exists('\mysqli_result','fetch_all');

?>