<?php
header('Content-Type: text/json; charset=utf-8');
require_once './doq/Auth.php';
require_once './doq/data/BaseProvider.php';
require_once './doq/data/View.php';
require_once './config.php';
require_once './lang/lang_ru.php';
doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);

$dataConnection=$GLOBALS['doq']['env']['@session']['#sessionDataConnection'];
list($ok,$mysql)=\doq\data\Connection::getDataConnection($dataConnection);


$reqId=$_POST['regId'];
$reqId=0;

$reqType=$_POST['reqType'];
$reqDesc=$_POST['reqDesc'];
$reqPoses=explode(',',$_POST['reqPos']);
$reqPosLat=doubleval($reqPoses[0]);
$reqPosLng=doubleval($reqPoses[1]);
$reqUserId=intval($_POST['reqUserId']);

if($reqDesc==''){
  print json_encode (['result' => 'error', 'text'=>"Пустое описание, $reqType, $reqPoses"]);
  exit();
}

if(!$reqId){
  
  $stmt=$mysql->mysqli->prepare('INSERT INTO requests (reqType,reqDesc,pos,reqUserId) VALUES( ?,?,POINT(?,?),?)');
  $stmt->bind_param('isddi',$reqType,$reqDesc,$reqPosLat,$reqPosLng,$reqUserId);
  $stmt->execute();
  print json_encode (['result' => 'ok']);
  exit();
}
print json_encode (['result' => 'error', 'id'=>$reqId]);


?>