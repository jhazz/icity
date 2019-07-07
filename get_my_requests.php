<?php
header('Content-Type: text/json; charset=utf-8');
#require_once './doq/Auth.php';
require_once './doq/data/BaseProvider.php';
require_once './doq/data/View.php';
require_once './config.php';
require_once './lang/lang_ru.php';
doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);

$dataConnection=$GLOBALS['doq']['env']['@session']['#sessionDataConnection'];
list($ok,$mysql)=\doq\data\Connection::getDataConnection($dataConnection);


//$reqId=$_GET['qUserId'];


  $stmt=$mysql->mysqli->prepare('SELECT reqId,reqType,reqDesc,ST_AsText(pos) FROM requests');
        //$stmt->bind_param('isddi',$reqType,$reqDesc,$reqPosLat,$reqPosLng,$reqUserId);
  $stmt->execute();
  $stmt->bind_result($reqId,$reqType,$reqDesc,$pos);
  
  $s="";
  
  while($stmt->fetch()){
    if($s!=="") $s.=",";
    $s.= '"id'.$reqId.'":{"reqType":'.$reqType.',"pos":"'. $pos.'"}';
  }
  $s="{".$s."}";
  print $s;
  $stmt->close();

?>