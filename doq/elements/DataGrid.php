<?php
namespace doq\elements;

class DataGrid {
  public static function begin($scopeStack,&$template,&$block,&$render){
    # sdfsdf

    $render->out[]='[It is a begin of DataGrid "'.$block['params']['id'].'" ]';
    $cnt=count($block['@']);
    $properties=[];
    $columns=[];
    $cellBlocks=[];

    if(!$cnt) {
      return false;
    }

    for($blockNo=0;$blockNo<$cnt;$blockNo++){
      $subBlock=&$block['@'][$blockNo];
      if (is_array($subBlock)) {
        if ($subBlock['tag']=='column') {
          $columns[]=&$subBlock['params'];
        } elseif ($subBlock['tag']=='begin') {
          if ($subBlock['params']['element']=='cell') {
            if(isset($subBlock['params']['forPath'])) {
              $cellBlocks[$subBlock['params']['forPath']]=&$subBlock;
            }
          }
        }
      }
    }

    $id=$block['params']['id'];
    if(isset($block['params']['path'])) {
      $path=$block['params']['path'];
    } else {
      $path='';
    }
    list($ok,$scope)=$scopeStack->push($path);
    if(!$ok) {
      return false;
    }
    $dataNode=$scope->dataNode;
    if($dataNode->type!==\doq\data\DataNode::NT_DATASET) {
      trigger_error(\doq\t('Wrong path to Dataset - %s is not a Dataset!',$scope->path));
    }

    $columnCount=count($columns);
    if($scope->seek(\doq\data\Scope::SEEK_TO_START)) {
      $render->out[]='Dataset is empty';
      $scopeStack->pop();
      return true;

    }
    $render->out[]='<table border=1 cellspacing=0><tr>';
    for($i=0;$i<$columnCount;$i++) {
      $render->out[]='<td bgcolor="#a0f0a0">'.$columns[$i]['path'].'</td>';
    }
    $render->out[]='</tr>';

    $i=0;

    while(true) {
      $render->out[]='<tr>';
      for($j=0;$j<$columnCount;$j++) {
        $cellPath=$columns[$j]['path'];
        $render->out[]='<td>';
              # Проблема: КАК ПОЛУЧИТЬ ИСХОДНЫЙ master value  и как получить indexName
              # думаю, когда делается лукап - не надо входить во вложенный датасет,
              # чтобы не терять value, а для агрегации надо по идее передавать родительский key_id
        list($ok,$scope)=$scopeStack->push($cellPath);
        if($ok) {
          if(isset($cellBlocks[$cellPath])) {
            $render->fromTemplate($scopeStack,$template,$cellBlocks[$cellPath]);
          } elseif(isset($cellBlocks['*'])) {
            $render->fromTemplate($scopeStack,$template,$cellBlocks['*']);
          } else {
            if($scope->path=="/PARAMETERS") {
              $detailToMasteridxName='idx_agg_main:store/PRODUCTS_2_PRODUCT_ID';
              $render->out[]="Aggregation-".$scope->dataNode->dataObject->resultIndexes[$detailToMasteridxName];
            }
            if($scope->path=="/THE_PRODUCT_TYPE/TYPE_NAME") {
              # вот здесь мы уже потеряли value лукапа и провалились в подобъект
              $render->out[]="Heello";
            }
            $render->out[]=$scope->asString().'<br/><span style="font-size:10px;">{'.$scope->path.'['.$scope->curTupleNo.']}</span>';
          }
          $render->out[]='</td>';
          $scopeStack->pop();
        }
      }
      $render->out[]='</tr>';
      $i++;
      $scope=$scopeStack->top;
      if (($scope->seek()) || ($i>100)) {
        break;
      }
    }
    $render->out[]='</table>';
    $scopeStack->pop();
    return true;
  }
}