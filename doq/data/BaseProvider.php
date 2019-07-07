<?php
namespace doq\data;

abstract class Scripter {
  abstract public function buildSelectScript($planEntry);

  public static function create($providerName) {
    switch ($providerName) {
      case 'mysql':
        if(!class_exists('Mysql\Scripter')) {
          require_once('Mysql.php');
        }
        return Mysql\Scripter::create();
      default:
        return new Scripter();
    }
  }

  public static function getDatasetPathElements(&$path,$datasourceName='',$schemaName='',$datasetName='') {
    $a=explode(':',$path,2);
    $isOtherDatasource=false;
    if(count($a)==2) {
      $isOtherDatasource=(($datasourceName!='')&&($a[0]!=$datasourceName));
      $datasourceName=$a[0];
      $datasetName=$a[1];
    }
    $a=explode('/',$datasetName,10);
    if (count($a)==2) {
      $schemaName=$a[0];
      $datasetName=$a[1];
    }
    return [$datasourceName,$schemaName,$datasetName,$isOtherDatasource];
  }

  public static function dumpPlan(&$planEntry) {
    $s=self::dumpDataset($planEntry);
    print '<style>.dpd{font-family:arial,sans;font-size:11px;}</style>';
    print '<table class="dpd" border=1><tr><td bgcolor="#ffff80" colspan="5">'.$planEntry['#dataConnection'].'(data provider='.$planEntry['#dataProvider'].', datasource='.$planEntry['#dataSource'].')</td></tr>'
      .$s
      .'<tr><td colspan="2">Select script:</td><td colspan="5" bgcolor="#e0ffe0"><pre>'.$planEntry['#readScript'].'</pre></td></tr>'
      .'</table>';

    if(isset($planEntry['@subPlan'])) {
      foreach($planEntry['@subPlan'] as $i=>&$subEntry) {
        print '<br/><hr/>Next plan entry:';
        self::dumpPlan($subEntry);
      }
    }
  }

  public static function dumpDataset(&$entry) {
    $dataset=&$entry['@dataset'];
    $row1=''; $row2='';$row3='';
    if(isset($entry['#refType'])) {
      $refType=$entry['#refType'];
      if($refType=='linknext') {
        return '<tr><td bgcolor="#ffffe0">Will be loaded by one of the next plan entry</td></tr>';
      }
    }

#    if(isset($entry['#filterDetailByColumn'])) {
#      $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#filterDetailByColumn: <b> '.$entry['#filterDetailByColumn'].'</b> #filterDetailField:'.$entry['#filterDetailField'].'</td></tr>';
#    }
    if(isset($entry['#mastertupleFieldNo'])) {
      $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#mastertupleFieldNo: <b>'.$entry['#mastertupleFieldNo'].'</b><br/>#detailDatasetId:'.$entry['#detailDatasetId'].'</td></tr>';
    }
    if(isset($entry['@resultIndexes'])) {
      foreach($entry['@resultIndexes'] as $i=>&$idx) {
        $row1.='<tr><td bgcolor="#eeffff" colspan="5">@index #type:'
        .$idx['#type']
        .', name:<b>'.$idx['#name']
        .'</b> (#byTupleFieldNo: '.$idx['#byTupleFieldNo'].' )</td></tr>';
      }
    }
    $row1.='<tr><td bgcolor="#ff8080" colspan="5">dataset are reading from <b>'.$dataset['#schema'].'/'.$dataset['#datasetName'].'</b></td></tr>';
    if(!$dataset['@fields']){
      trigger_error('пусто',E_USER_ERROR);
    }
    foreach($dataset['@fields'] as $i=>&$field) {
      $kind=(isset($field['#kind'])?$field['#kind']:'text');
      $row2.='<tr><td>id#'.$field['#columnId'].(isset($field['#tupleFieldNo'])?'<br/>['.$field['#tupleFieldNo'].']':'(virt)')
        .'</td><td>['.$field['#field'].']'
        .(((isset($field['#originField'])&&$field['#originField']!==$field['#field'])?':'.$field['#originField']:''))
        .'</td><td>'.$kind.'</td>'
        .'<td>'.(isset($field['#label'])?'<i>'.$field['#label'].'</i><br/>':'');

      # Если это лукап-справочник, то он может быть #refType='join' или #refType='linknext'
      if($kind=='lookup'){
        $refType=isset($field['#refType'])? $field['#refType'] : "";
        if($refType) {
          $row2.='Reference type:'.$refType.' ==> <b>'.$field['#ref'].'</b><br/>';
          $row2.='<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'this').'</b>:'
          .(isset($field['#refSchema'])?$field['#refSchema']:'.')
          .(isset($field['#refDataset'])?'/'.$field['#refDataset']:'/.');
        }
        if(isset($field['#uniqueIndex'])) {
          $row2.='<br/>'.(isset($field['#uniqueIndex'])?'#uniqueIndex:'.$field['#uniqueIndex']:'(Error! No #uniqueIndex!)');
        }
        if (isset($field['#refType'])) {
          $row2.='<table class="dpd" border=1>'.self::dumpDataset($field).'</table>';
        }
      # Если это агрегат, то ссылка может быть только удаленной
      } elseif ($kind=='aggregation') {
        $refType=isset($field['#refType'])? $field['#refType'] : "(NO REFTYPE!)";
        $row2.='Reference type:'.$refType.' ==> <b>'.$field['#ref'].'</b><br/>'
        .'<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'this').'</b>:'
        .$field['#refSchema'].'/'.$field['#refDataset']
        .'<br/>'.(isset($field['#nonuniqueIndex'])?'#nonuniqueIndex:'.$field['#nonuniqueIndex']:'(Error! No #nonuniqueIndex!)');;
        $row2.='<table class="dpd" border=1>'.self::dumpDataset($field).'</table>';
      }
      if(isset($field['#error'])) {
        $row2.='ERROR! '.$field['#error'].'</br>';
      }
      $row2.='</td></tr>';

    }
    return $row1.$row2;
  }
}

/**
 * Scope is a combination of dataNode (dataset/subcolumn/column) and indexing cursor referred to a dataObject
 * */
abstract class Scope {
  const SEEK_TO_START=0;
  const SEEK_TO_NEXT=1;
  const SEEK_TO_END=2;
  /** @var ScopeWindow указывает тип окна, по которому движется курсор*/
  const SW_ALL_RECORDS=0;
  const SW_INDEX_RECORDS=1;
  const SW_ONE_INDEX_RECORD=2;
  const SW_AGGREGATED_INDEX_RECORDS=3;
  const SW_ONE_FIELD=4;

  public $dataNode;
  public $path;
  abstract protected function seek($origin);
  abstract protected function makeDetailScope($path,$masterFieldName);
  abstract protected function __construct(DataNode $dataNode, $path='');
  abstract public function asString();
}


class ScopeStack {
  public $stack;
  public $top;

  public static function create(DataNode $dataNode,$indexName='',$indexKey=NULL) {
    $scopeStack=new ScopeStack();
    $scope=$dataNode->dataObject->makeScope($dataNode,$indexName,$indexKey);
    $scopeStack->pushScope($scope);
    return $scopeStack;
  }

  private function pushDataNode(DataNode $dataNode, $newPath) {
    $scope=$dataNode->dataObject->makeScope($dataNode);
    $scope->path=$newPath;
    $this->top = $this->stack[] = $scope;
    return $scope;
  }

  private function pushScope(Scope $scope) {
    $this->top = $this->stack[] = $scope;
    return $scope;
  }

  public function push($addPath) {
    $datasetScope=NULL;
    $apath=explode('/',$addPath);
    $apathLen=count($apath);
    $scopeStackLen=count($this->stack);

    if (($apathLen>1) && ($apath[0]=='') && ($scopeStackLen)) {
      # TODO: При переходе на корневой узел '/' внутри стека скопов возвращается ссылка на корневой скоп, а не его копия
      # надо быть осторожным с использованием циклов внутри такого перехода. Вплоть до блокировки
      $rootScope=$this->stack[0];
      $this->pushScope($rootScope);
      return [true,$rootScope];
    }

    $scope=$this->top;
    if ($scope->dataNode->type==DataNode::NT_DATASET) {
      $datasetScope=$scope;
    }
    $path=$scope->path;
    if($addPath=='') {
      $this->stack[]=$scope;
      return [true,$scope];
    }


    for($posInPath=0;$posInPath<$apathLen;$posInPath++){
      $pathElementName=$apath[$posInPath];
      if($pathElementName==='') break;
      switch($scope->dataNode->type) {
        case DataNode::NT_DATASET:
          $datasetScope=$scope;
          if(!isset($scope->dataNode->childNodes[$pathElementName])) {
            trigger_error(\doq\t('Dataset object has no name [%s] in the namespace',$pathElementName),E_USER_ERROR);
            return [false,'Undefined name '.$pathElementName];
          }
          $childNode=$scope->dataNode->childNodes[$pathElementName];
          if ($pathElementName=='THE_PRODUCT_TYPE') {
            $z=1;
          }
          if($childNode->type==DataNode::NT_DATASET) {
            $scope=$scope->makeDetailScope($scope->path,$pathElementName);
            $scope->seek(Scope::SEEK_TO_START);
          } else {
            $nextDataNode=$scope->dataNode->childNodes[$pathElementName];
            $path.='/'.$pathElementName;
            $scope=$nextDataNode->dataObject->makeScope($nextDataNode,'',NULL,$datasetScope,$path);
          }
          break;
        case DataNode::NT_SUBCOLUMNS:
          if(!isset($scope->dataNode->childNodes[$pathElementName])) {
            trigger_error(\doq\t('DataObject %s has no column %s',$scope->dataNode->dataObject->id,$pathElementName),E_USER_ERROR);
            return [false,'Undefined name '.$pathElementName];
          }
          $nextDataNode=$scope->dataNode->childNodes[$pathElementName];
          $path.='/'.$pathElementName;
          if(!isset($datasetScope)) {
            trigger_error(\doq\t('Column %s has no dataset in previous scopes of path %s',$pathElementName,$path),E_USER_ERROR);
            return [false,'Subcolumn should bethe next scope after any dataset scope'];
          }
          $scope=$nextDataNode->dataObject->makeScope($nextDataNode,'',NULL,$datasetScope,$path);
          break;
        case DataNode::NT_COLUMN:
          trigger_error(\doq\t('Column %s cannot not have any subnames like %s',$scope->path,$pathElementName),E_USER_ERROR);
          return [false,'Try to get sub name of scalar value'];
          break;
      }
    }

    $this->pushScope($scope);
    return [true,$scope];
  }

  public function pop(){
    $stackLen=count($this->stack);
    if($stackLen) {
      unset($this->top);
      array_pop($this->stack);
      if($stackLen>1) {
        $this->top=$this->stack[$stackLen-2];
      }
    } else {
      $scope=NULL;
      trigger_error('Scope stack reach emptyness. Seems you have made unusable pop()',E_USER_ERROR);
    }
    return true;
  }


}

class DataNode{
  const NT_COLUMN='! Column';
  const NT_SUBCOLUMNS='! Subcolumns';
  const NT_DATASET='! Dataset';

  public $type;
  public $parameters;
  public $dataObject;
  public $childNodes;

  public function __construct($type,$nodeId,$parendNode=NULL) {
    if($parendNode!==NULL) {
      $parendNode->childNodes[$nodeId]=$this;
    }
    $this->type=$type;
    if($this->type!==self::NT_COLUMN) {
      $this->childNodes=[];
    }
  }

}

abstract class DataObject {
  public $id;
  abstract protected function makeScope(DataNode $dataNode);
}

abstract class Dataset extends DataObject {
  public $planEntry;

  public static function create($providerName,&$planEntry,$id) {
    switch ($providerName) {
      case 'mysql':
        if(!class_exists('Mysql\Dataset')) {
          require_once('Mysql.php');
        }
        return [true,new Mysql\Dataset($planEntry,$id)];
      default:
        return [false,'Unknown provider '.$providerName];
    }
  }

  /**
   * @param array config from planEntry
   * @param DataNode the datanode collects data items
   */
  public function collectDataNodesRecursive (&$config,&$dataNode) {
    $fields=&$config['@dataset']['@fields'];
    foreach($fields as $i=>&$field) {
      $fieldName=$field['#field'];
      if(isset($dataNode->childNodes[$fieldName])){
        trigger_error(\doq\t('Field dublicate name %s is found in view config %s', $fieldName,$config['#schema'].'/'.$config['#dataset']),E_USER_ERROR);
        continue;
      }
      if(isset($field['@dataset'])){
        $node=new DataNode(DataNode::NT_SUBCOLUMNS,$fieldName,$dataNode);
        $node->dataObject=$this;
        # TODO: Некрасиво!
        $node->parameters=&$field['@dataset'];
        $this->collectDataNodesRecursive ($field,$node);
      } else {
        $node=new DataNode(DataNode::NT_COLUMN,$fieldName,$dataNode);
        $node->dataObject=$this;
        $node->parameters=&$field;
      }
    }
  }

  public function __construct(&$planEntry,&$params,$id) {
    trigger_error('Abstract Dataset class should not used to create itself!',E_USER_ERROR);
  }


}

class Connection {
  public static $cfgDataConnections;
  public static $dataConnections;

  public static function init(&$cfgDataConnections) {
    self::$dataConnections=[];
    self::$cfgDataConnections=&$cfgDataConnections;
  }

  public static function getDataConnection($connectionName) {
    if(!isset(self::$cfgDataConnections[$connectionName])) {
      trigger_error(\doq\t('Unknown connection name %s',$connectionName),E_USER_ERROR);
      return [false,NULL];
    }

    if(isset(self::$dataConnections[$connectionName])) {
      return [true,&self::$dataConnections[$connectionName]];
    } else {
     $cfgConnection=&self::$cfgDataConnections[$connectionName];
     $providerName=$cfgConnection['#provider'];

     switch($providerName) {
       case 'mysql':
          if(!class_exists('Mysql\Connection')) {
            require_once('Mysql.php');
          }
          $connection=new \doq\data\mysql\Connection($connectionName,$cfgConnection);
          self::$dataConnections[$connectionName]=&$connection;
          return [true,&$connection];
          break;
       default:
         trigger_error(\doq\t('Unknown data provider name %s',$providerName),E_USER_ERROR);
         return [false,NULL];
     }
    }
  }
}


?>