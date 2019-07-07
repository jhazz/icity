<?php
namespace doq\data;

/**
* View - is a data loading plan that creates Datasets when do read data by a parameters
*
*/
class View {
  static public $defaultCacher;
  public $viewId;
  public $cfgView;
  public $cfgModel;
  public $dataset;
  public $viewColumns;
  public $linkedDatasources;
  public $subDatasets;
  public $cacher;
  public $cfgConnections;

  public static function create(&$cfgModel,&$cfgView,&$cfgConnections,$viewId=false){
    $r=new View($cfgModel,$cfgView,$cfgConnections,$viewId);
    return[true,&$r];
  }

  public function __construct(&$cfgModel,&$cfgView,&$cfgConnections,$viewId=false) {
    $this->cfgModel=&$cfgModel;
    $this->cfgView=&$cfgView;
    $this->cfgConnections=&$cfgConnections;
    $this->viewId=$viewId;
    $this->isCacheable=false;
    if(isset(self::$defaultCacher)) {
      $this->cacher=self::$defaultCacher;
      $this->isCacheable=true;
    }
    if($viewId===false && isset($cfgView['#viewId'])) {
      $viewId=$cfgView['#viewId'];
    }
  }

  public function setCacher(&$cacher) {
    if($this->viewId===false) {
      trigger_error(\doq\t('You must set viewId to get ability for using cachers'), E_USER_ERROR);
      return false;
    }
    $this->cacher=&$cacher;
    $this->isCacheable=true;
    return true;
  }

  public function prepare($configMtime,$forceRebuild=false) {
    if ((!$this->isCacheable)||($forceRebuild)) {
      $this->makePlan();
    } else {
      list($ok,$data)=$this->cacher->get($configMtime,$this->viewId);
      if($ok) {
        $this->plan=&$data;
      } else {
        if ($this->makePlan()) {
          $this->cacher->put($configMtime,$this->viewId,$this->plan);
        }
      }
    }
  }

  public static function getFieldByColumnId ($findColumnId,&$entry) {
    foreach($entry['@dataset']['@fields'] as $i=>&$field) {
      if (isset($field['#columnId']) && $field['#columnId']==$findColumnId) {
        return [true,&$field];
      }
      if(isset($field['@dataset'])) {
        $r=self::getFieldByColumnId($findColumnId,$field);
        if($r[0]) return $r;
      }
    }
    return[false];
  }


  /**
  * Executes whole data reading plan
  * @param array $params
  */
  public function read(&$params,$datasetId) {
    $dataNode=new \doq\data\DataNode(\doq\data\DataNode::NT_DATASET,$datasetId);
    $ok=$this->readPlanEntry($this->plan,$dataNode,$params,$datasetId);
    return [$ok,$dataNode];
  }


  /**
  *
  */

  private function readPlanEntry(&$planEntry,$dataNode,&$params,$datasetId) {
    $providerName=$planEntry['#dataProvider'];
    list($ok,$dataset)=\doq\data\Dataset::create($providerName,$planEntry,$datasetId);
    if(!$ok) {
      return false;
    }
    $dataNode->dataObject=$dataset;
    if ($dataset->connect()) {
      $dataset->read($params);
    }

    $dataset->collectDataNodesRecursive($planEntry,$dataNode);

    if(isset($planEntry['@subPlan'])) {
      foreach($planEntry['@subPlan'] as $i=>&$subPlanEntry) {
        if (isset($subPlanEntry['#detailDatasetId'])) {
          $detailDatasetId=$subPlanEntry['#detailDatasetId'];
          if(!isset($planEntry['@dataset']['@fields'])){
            trigger_error(\doq\t('В planEntry[@subPlan] отсутствуют колонки'),E_USER_ERROR);
            return false;
          }
          $masterFieldNo=$subPlanEntry['#masterFieldNo'];

          # для виртуального aggregation $masterFieldNo должен указывать всегда на колонку данных
          # первичного ключа masterDataSet
          # для lookup это номер колонки данных, из которой идет ссылка на справочник
          #list($ok,$parentValueSet)=$dataset->uniqueDataOfColumn($masterFieldNo);

          # ПРИДУМЫВАЙ КАК ПОЛУЧИТЬ ColumnNo
          # FieldNo работает только в masterDataset,
          $masterColumnNo=$dataset->planEntry['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
          #$dataset-> self::getColumnByFieldNo($masterFieldNo);



          list($ok,$parentValueSet)=$dataset->uniqueValuesOfTupleSetField($masterColumnNo);

          if(!$ok) {
            return false;
          }
          $newParams=[];
          #$newParams['@keyValuesIn']=&$parentValueSet;
          # ОТСУТСТВУЕТ detailToMasterField!!!
          $newParams['@filter']=[
            [
            '#columnId'=>$subPlanEntry['#detailToMasterColumnId'],
            '#operand'=>'IN',
            '@values'=>&$parentValueSet
            ]
          ];
          /*
          $newParams['@createIndex']=[
            'type'=>'single',
            'indexId'=>$datasetId.'-'.$detailDatasetId,
            'masterColumnNo'=>$subPlanEntry['#masterColumnNo'],
            'childToMasterField'=>$subPlanEntry['#childToMasterField']  # ==PRODUCT_TYPES/PRODUCT_TYPE_ID
            ];
            */

          # =IF=TYPE AGGREGATION..
          #$newParams['@createIndex']=[
          #  'type'=>'multiple',
          #  'indexId'=>$datasetId.'-'.$detailDatasetId,
          #  'masterColumnNo'=>$subPlanEntry['#masterColumnNo'],  #==PRODUCT_ID
          #  'childToMasterField'=>$subPlanEntry['#childToMasterField']  #==PRODUCT_PARAMETERS/PRODUCT_ID
          #  ];
          #$newParams['@filter']=[
          #$newParams['@filter']=[
          #  ['field'=>$subPlanEntry['#childToMasterField'], 'operand'=>'IN', 'values'=>&$parentValueSet]
          #];
          #  ];


        } else {
          trigger_error('Unknown plan linking',E_USER_ERROR);
          return false;
        }

        $childNode=new \doq\data\DataNode(DataNode::NT_DATASET,$detailDatasetId,$dataNode);
        $ok=$this->readPlanEntry($subPlanEntry,$childNode,$newParams,$detailDatasetId);
        if(!$ok) {
          return false;
        }


      }
    }
    return true;
  }

  public function makePlan() {
    $this->plan=[];
    $this->lastPlanId=1;
    $viewColumns=NULL;
    return $this->makePlanRecursive($this->cfgView,$this->plan,$viewColumns);
  }

  private function makePlanRecursive(&$cfgView,&$plan,&$parentViewColumn,
                   $datasourceName='',$schemaName='',$datasetName='',$parentRef='',
                   $masterFieldNo=false,$detailDatasetId=false,$masterKind=false,
                   $isNewPlan=true,$isOtherDatasource=false) {

    $parentDatasetname=$datasetName;
    if (isset($cfgView['#dataset'])) {
      list ($datasourceName,$schemaName,$datasetName,$isOtherDatasource)=\doq\data\Scripter::getDatasetPathElements($cfgView['#dataset'],$datasourceName,$schemaName,$datasetName);
    }

    $datasetRef=$datasourceName.':'.$schemaName.'/'.$datasetName;

    $cfgModelDataset=&$this->cfgModel['@datasources'][$datasourceName]['@schemas'][$schemaName]['@datasets'][$datasetName];
    if(!$cfgModelDataset) {
      trigger_error(\doq\t('Cannot find model config %s',$datasetRef),E_USER_ERROR);
      return false;
    }
    if($isOtherDatasource) {
      $subPlan=[];
      if(!isset($plan['@subPlan'])) {
        $plan['@subPlan']=[&$subPlan];
      } else {
        $plan['@subPlan'][]=&$subPlan;
      }
      $masterPlan=&$plan;
      $plan=&$subPlan;
      $isNewPlan=true;
    }
    $dataset=['#schema'=>$schemaName,'#datasetName'=>$datasetName,'@fields'=>[]];

    if(isset($cfgModelDataset['@keyFields'])) {
      trigger_error('Unsupported multiple field primary keys',E_USER_ERROR);
      return false;
    } elseif (isset($cfgModelDataset['#keyField'])) {
      $dataset['#keyField']=$cfgModelDataset['#keyField'];
    }


    if($isNewPlan) {
      $plan['#lastColumnId']=0;
      $plan['#lasttupleFieldNo']=0;
      $plan['#planId']=$this->lastPlanId;
      $this->lastPlanId++;
      $plan['#dataSource']=$datasourceName;
      $cfgDatasource=&$this->cfgModel['@datasources'][$datasourceName];
      $dataConnectionName=$cfgDatasource['#dataConnection'];
      $plan['#dataConnection']=$dataConnectionName;
      $plan['#dataProvider']=$providerName=$this->cfgConnections[$dataConnectionName]['#provider'];
    } else {
      $parentViewColumn['@dataset']=&$dataset;
    }

    $foundDetailColumnForMaster=false;
    $foundKeyColumn=false;

    if(isset($dataset['#keyField'])) {
      $keyField=$dataset['#keyField'];
    } else $keyField=false;

    $fieldNo=0;
    foreach($cfgView as $localFieldName=>&$viewFieldDef) {
      $fc=$localFieldName[0];
      if(($fc!='#')&&($fc!='@')) {
        $newColumn=['#columnId'=>$plan['#lastColumnId'],'#field'=>$localFieldName,'#fieldNo'=>$fieldNo];
        $plan['#lastColumnId']++;
        $fieldNo++;

        unset($modelFieldDef);
        $originField=$localFieldName;
        if(isset($viewFieldDef['#field'])) {
          $originField=$viewFieldDef['#field'];
        }
        if($keyField && ($keyField===$originField)) {
          $foundKeyColumn=&$newColumn;
        }
        if(isset($cfgModelDataset['@fields'][$originField])){
          $modelFieldDef=&$cfgModelDataset['@fields'][$originField];
          $newColumn['#originField']=$originField;
          if (isset($modelFieldDef['#type'])) {
            $type=$newColumn['#type']=$modelFieldDef['#type'];
          } else {
            $type='';
          }
        } else {
          trigger_error(\doq\t('Model %s.%s.%s has no defined field [%s] that used by view %s',$datasourceName,$schemaName,$datasetName,$localFieldName,$this->viewId),E_USER_WARNING);
          continue;
        }
        if ($type!=='virtual') {
          $newColumn['#tupleFieldNo']=$plan['#lasttupleFieldNo'];
          $plan['#lasttupleFieldNo']++;
        }


        if(isset($viewFieldDef['#label'])) {
          $newColumn['#label']=$viewFieldDef['#label'];
        } elseif (isset($modelFieldDef['#label'])) {
          $newColumn['#label']=$modelFieldDef['#label'];
        }
        if (isset($modelFieldDef['#kind'])) {
          $newColumn['#kind']=$kind=$modelFieldDef['#kind'];
        } else {$kind='';}
        if (isset($modelFieldDef['#ref'])) {
          $newColumn['#ref']=$ref=$modelFieldDef['#ref'];
          if ($masterKind=='aggregation') {
            if($parentRef==$ref) {
              $foundDetailColumnForMaster=&$newColumn;
            }
          }
        }

        if (isset($viewFieldDef['@linked'])) {
          if($ref) {
            $subMasterFieldNo=isset($newColumn['#fieldNo'])?$newColumn['#fieldNo']:false;
            $subDatasetId=$newColumn['#field'];
            list ($RdatasourceName,$RschemaName,$RdatasetName,$isROtherDatasource)=\doq\data\Scripter::getDatasetPathElements($ref,$datasourceName,$schemaName,$datasetName);
            $newColumn['#refSchema']=$RschemaName;
            $newColumn['#refDataset']=$RdatasetName;
            if ($kind=='aggregation') {
              $isROtherDatasource=true;
            }

            if($isROtherDatasource){
              $newColumn['#refType']='linknext';
              $newColumn['#refDatasource']=$RdatasourceName;
              if ($kind=='aggregation') {
                if($type=='virtual') {
                  # virtual is a mostly common type of aggregation field
                  if(!$foundKeyColumn) {
                    trigger_error('Define primary key field in the View first!');
                    return false;
                  }
                  $subMasterFieldNo=$foundKeyColumn['#fieldNo'];
                }
              }
            } else {
              $newColumn['#refType']='join';
            }
            $this->makePlanRecursive($viewFieldDef['@linked'],$plan,$newColumn,
              $RdatasourceName,$RschemaName,$RdatasetName,$datasetRef,
              $subMasterFieldNo,$subDatasetId,$kind,
              false,$isROtherDatasource);
          } else {
            $newColumn['#error']='No #ref defined for linking column';
          }
        }  elseif (!isset($modelFieldDef)) {
          $newColumn['#error']='Unknown field '.$localFieldName;
        }
        $dataset['@fields'][]=&$newColumn;
        unset($newColumn);
      }
    }


    if($isNewPlan) {
      $plan['@dataset']=&$dataset;
      if($masterKind=='lookup') {
        if (!$foundKeyColumn) {
          if($keyField) {
            trigger_error(\doq\t('Not found key field %s in view from %s',$keyField,$datasetRef),E_USER_ERROR);
          } else {
            trigger_error(\doq\t('Key field is required for lookup in dataset %s',$datasetRef),E_USER_ERROR);
          }
          return false;
        }
        $newIdxName='idx_look_'.$parentRef.'--'.$dataset['#keyField'];
        if(!isset($plan['@resultIndexes'])) $plan['@resultIndexes']=[];
        if(isset($plan['@resultIndexes'][$newIdxName])) {
          for($i=0;$i<10;$i++) {
            $s=$newIdxName.'/'.$i;
            if(!isset($plan['@resultIndexes'][$s])) {
              $newIdxName=$s;
              break;
            }
          }
        }
        $plan['@resultIndexes'][$newIdxName]=[
          '#type'=>'unique',
          '#name'=>$newIdxName,
          '#byTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo']
        ];
        $parentViewColumn['#uniqueIndex']=$newIdxName;
        $plan['#detailToMasterColumnId']=$foundKeyColumn['#columnId'];
      } elseif ($masterKind=='aggregation') {
        # aggregation by the real multilookup field and by the virtual field as default
        if(!$foundDetailColumnForMaster) {
          trigger_error(\doq\t('Not found back referenced lookup to %s from %s',$parentRef,$datasetRef),E_USER_ERROR);
          return false;
        }
        $newIdxName='idx_agg_'.$parentRef.'_2_'.$foundDetailColumnForMaster['#originField'];
        if(!isset($plan['@resultIndexes'])) $plan['@resultIndexes']=[];
        if(isset($plan['@resultIndexes'][$newIdxName])) {
          for($i=0;$i<10;$i++) {
            $s=$newIdxName.'/'.$i;
            if(!isset($plan['@resultIndexes'][$s])) {
              $newIdxName=$s;
              break;
            }
          }
        }
        # Этот индекс, в отличие от лукапа, создает неуникальный индекс,
        # в котором ключами являются ID родителей, а внутри них группируются
        # ссылки на записи деток, которые в него входят
        $plan['@resultIndexes'][$newIdxName]=[
          '#type'=>'nonunique',
          '#name'=>$newIdxName,
          '#byTupleFieldNo'=>$foundDetailColumnForMaster['#tupleFieldNo']
        ];
        $parentViewColumn['#nonuniqueIndex']=$newIdxName;
        $plan['#detailToMasterColumnId']=$foundDetailColumnForMaster['#columnId']; # вслепую
      }
      if($masterFieldNo!==false) {
        $plan['#masterFieldNo']=$masterFieldNo;
        $plan['#detailDatasetId']=$detailDatasetId;
        if (!isset($masterPlan['@detailIndexByFieldNo'])) {
          $masterPlan['@detailIndexByFieldNo']=[];
        }
        $masterPlan['@detailIndexByFieldNo'][$masterFieldNo]=$newIdxName;
      }

      $scripter=\doq\data\Scripter::create($providerName);
      $selectScript=$scripter->buildSelectScript($plan);
      if($selectScript!==false) {
        $plan['#readScript']=$selectScript;
      }
    }
    return true;
  }

  # Routine that collects field names from planEntry dataset
  public static function collectFieldList(&$planEntry,&$fieldList) {
    $fields=&$planEntry['@dataset']['@fields'];
    foreach($fields as $i=>&$field) {
      $fieldList[]=&$field;
      if(isset($field['@dataset'])){
        self::collectFieldList($field,$fieldList);
      }
    }
  }

}


?>