<?php
require_once './doq/Logger.php';
require_once './doq/data/BaseProvider.php';
require_once './config.php';
require_once './doq/Translate.php';
require_once './lang/lang_ru.php';
require_once './doq/Session.php';
doq\Logger::init();
doq\Session::init();

?>
<html>
<head>	
<meta charset="utf-8" />
<title>iCity - город без барьеров</title>
<script>

function getSelectedPosition(e){
  var p=e.latlng;
  setSelectedPosition(p);
  map.off('click',getSelectedPosition);
  $('#reqPos').val(p.lat+','+p.lng);
}

function setSelectedPosition(p){
  if(reqMarker!==undefined){
    reqMarker.setLatLng(p);
  }else {
    reqMarker=L.marker(p).addTo(map);
  }
}

function enterPosition(){
  map.on('click',getSelectedPosition);
}

function sendForm(){
  data={
    'reqId':$('#reqId').val()||0,
    'reqUserId':$('#userId').val(), // TODO: Надо брать из сессии!
    'reqType':$('#reqType').val(),
    'reqDesc':$('#reqDesc').val(),
    'reqPos':$('#reqPos').val()
    };
   $.post("form_request_post.php",data,function(results){
     if(results.result=='ok'){
       map.removeLayer(reqMarker);
       reqMarker=undefined;
       closePopupForm();
     }
   });
}
</script>


<?php
$userCaption="";
if(doq\Session::$userId) {
  if(doq\Session::$activePersonFirstName!=''){
    $userCaption= doq\Session::$activePersonFirstName.'&nbsp;'.doq\Session::$activePersonLastName;
  } else {
    $userCaption= doq\Session::$login;
  }
}


print "<h2>Заявить о проблеме</h2>";
if($userCaption===""){
  print "Вам необходимо сначала зарегистрироваться<br/><br/>";
  print '<button type="submit" onclick="location.href=\'login.php\'" class="btn btn-default">Вход в систему</button>';
  exit();
} else {
  // Читаем базу
  $req=['reqType'=>"2", 'reqDesc'=>"Что что в utf8 с кривыми &amp; <символами" , 'reqId'=>"123",'reqDate'=>"12.12.2018"];

  print '<form onsubmit="sendForm();return false;"><div id="reqForm">';
  print '<input type="hidden" id="userId" value="'.doq\Session::$userId.'">';
  print '<input type="hidden" id="reqId" value="'.$req['reqId'].'">';
  if(!$req['reqDate']){
    $req['reqDate']=date ("j.m.Y");
  }
  print "<h4>Заявка ".$userCaption." от ".$req['reqDate']."</h4>"; 
  
  $reqTypes=[ //TODO: Классификатор в базу
    "Требуется сопряжение тротуаров",
    "Нужна тактильная плитка",
    "Нужна латеральная разметка",
    "Нужны поручни",
    "Нужны поручни для колясочников",
    "Нужна своевременная очистка",
    "Нужен крытый проход",
    "Неправильное освещение",
    "Нужны пандусы",
    "Требуется подъемник",
    "Неправильное устройство лестницы",
    "Неправильное устройство пандуса"
  ];
  
  print '<label for="reqType">Укажите тип проблемы</label>';

  print "<select class='form-control'  id='reqType'>";
  foreach($reqTypes as $k=>$v){
    print "<option value='$k' ".(intval($req['reqType'])==$k).">$v</option>";
  }
  print "</select>";
  
  print '<label for="reqType">Опишите ситуацию и что надо сделать</label>';
  print "<textarea rows=7 class='form-control' id='reqDesc'>".$req['reqDesc']."</textarea>";
  print '<input type="hidden" id="reqPos" value="">';
  print "<br/><a href='#' onClick='enterPosition()'>Выбрать позицию на карте</a>";
}

  
?>
<br/>
		<div>
      <button class="btn btn-lg btn-primary " type="button" onclick='closePopupForm();'>Закрыть</button>
      <button style="float:right" id="btnsend" class="btnAccept btn btn-lg btn-primary btn-default" type="submit">Отправить</button>
    </div>
</div>
</form>

