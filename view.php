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
<!DOCTYPE html>
<html>
<head>	
<title>iCity - город без барьеров</title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="css/bootstrap.css">
<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin=""/>

<script src="js/jquery-1.11.3.min.js"></script> 
<script src="js/bootstrap.js"></script>
<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>
<script src="js/leaflet-heat.js"></script>

<style>
  html, body {
    height: 100%;
    margin: 0;
  }
  #map {
    width: 600px;
    height: 400px;
  }
  .leaflet-top .leaflet-control-zoom {   
    transform: translateY(100px);
  }
  .btnAccept{
    background-color:orange;
  }
  #topNav {
    z-index:2; 
    padding:0px; 
    opacity: 0.8; 
    vertical-align:bottom; 
    background:#ffffff;
    position:fixed; 
    left:0px; 
    top:0px;
    width:100%; 
    height:60px;
  }
  #menuPopupBtn{
    width:35px; height:35px;
    background: url("images/menu-popup-btn.png");
    float:left; 
    margin-right:20px;
    margin-left:10px;
  }
  #topNavContainer{
    position:absolute;
    bottom:0px;
    padding:10px;
  }
  #topNavRMenu{
    padding:10px;
    margin:10px;
    position:absolute;
    bottom:0px;
    right:0px;
  }
  .menuitem1{
    padding:0px 30px 0px 10px;
  }
  .menuitem2{
    padding:15px 20px 15px 30px;
    font-size:18px;
  }
  .menuitemPopupMenu{
    padding:10px 10px 10px 10px;
    font-size:14px;
  }
  a {
    color:#000000 !important;
  }
  #popupForm{
    z-index:3;
    position:absolute;
    top:100px; right:20px;
    width:400px;
    background-color:#ffffff;
    border-radius:10px;
    padding:10px;
    height:500px;
    box-shadow:  5px 5px 5px #cccccc;
    display:none;
  }
  #popupFormContent{
    overflow:auto;
    height:460px;
  }
  #popupMiniMenu{
    z-index:3;
    position:absolute;
    top:100px; 
    left:20px;
    width:200px;
    background-color:#ffffff;
    border-radius:10px;
    padding:10px;
    box-shadow:  5px 5px 5px #cccccc;
  }
  
</style>

	<style>body { padding: 0; margin: 0; } #map { height: 100%; width: 100vw; }</style>
</head>
<body>

<?php 
  $userBtnText="";
  $userCaption="";
  if(doq\Session::$userId) {
    if(doq\Session::$activePersonFirstName!=''){
      $userCaption= doq\Session::$activePersonFirstName.'&nbsp;'.doq\Session::$activePersonLastName;
    } else {
      $userCaption= doq\Session::$login;
    }
    $userBtnText= '<a href="#cabinet">'.$userCaption.'&nbsp;<img src="images/user.png"></a>';
  } else {
    $userBtnText= '<button type="submit" onclick="location.href=\'login.php\'" class="btn btn-default" style="background:url(images/)">Вход в систему</button>';
  }
?>



<div id="topNav">
  <div id="topNavContainer">
    <div style="margin-left:10px" onClick="$('#miniNav').toggle()" id="menuPopupBtn"></div>
    <img src="images/icity_logo.png"/>
  </div>
  <div> 
  <div id="topNavRMenu">
     <table><tr><td id="topNavRMenuElements">
     <table><tr><td class="menuitem1">
        <a href='#'>Главная</a>
      </td><td class="menuitem1">
        <a href='#about'>О проекте</a>
      </td><td class="menuitem1">
        <a href='#exp'>Для экспертов</a>
      </td><td class="menuitem1">
        <? print $userBtnText; ?>
      </td></tr></table>
      </td><td>
        <a href="#changecity"><img src="images/balloon-city.png">&nbsp;Новосибирск</a>
      </td></tr></table>
  </div>
</div>


<div id="miniNav" style="z-index:1; position:absolute; top:60px; left:0; background-color:#ffffff; width:300px; display:none">
  <table>
    <tr><td class="menuitem2"><a href='#'>Главная </a></td></tr>
    <tr><td class="menuitem2"><a href='/'>О проекте</a></td></tr>
    <tr><td class="menuitem2"><a href='#exp'>Для экспертов</a></td></tr>
    <tr><td class="menuitem2"><hr/></td></tr>
    <tr><td class="menuitem2"><a href='#makeRequest' onclick='$("#miniNav").hide();'>Заявить о проблеме</a></td></tr>
    <tr><td class="menuitem2"><a href='#listRequests'>Мои завки</a></td></tr>
    <tr><td class="menuitem2"><?=$userBtnText?></td></tr>
  </table>
</div>
</div> <!-- #topNav -->

<div id='map' style="z-index:1; "></div>

<div id='popupForm'>
  <div onclick="closePopupForm()" style='cursor:pointer;text-align:right'>X</div>
  <div id="popupFormContent">Подождите, идет загрузка...</div>
</div>

<div id='popupMiniMenu'>
  <table>
    <tr><td class="menuitemPopupMenu"><a href='#showAllRequestsCity'>Все заявки по городу</a></td></tr>
    <tr><td class="menuitemPopupMenu"><a href='#listRequests'>Все заявки по региону</a></td></tr>
    <tr><td class="menuitemPopupMenu"><a href='#listRequests'>Прогноз роста заявок</a></td></tr>
  </table>
</div>



<script>
	var map = L.map('map',{zoomControl: false}).fitWorld();
  var sourceMarker, reqMarker;
	
  
  
  L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
		maxZoom: 18,
		attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
			'<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
		id: 'mapbox.streets',
    
	}).addTo(map);
  
  L.control.zoom({
    position: 'bottomleft'
  }).addTo(map);
  
  function closePopupForm(){
    $('#popupForm').popupFormContent="";
    $('#popupForm').hide();
  }
	function onLocationFound(e) {
		var radius = e.accuracy / 2;
		sourceMarker=L.marker(e.latlng);
    sourceMarker.addTo(map)
			.bindPopup("Вы находитесь в радиусе " + radius + " вокруг этой точки").openPopup();
      L.circle(e.latlng, radius).addTo(map);
	}
  
	function onLocationError(e) {
		//map.setView([54.9870216, 82.8645435]);
    sourceMarker=L.marker([54.9870216, 82.8645435]);
    sourceMarker.addTo(map)
			.bindPopup("Вы где-то здесь").openPopup();
	}

  /*function onMapResize(e){
     console.log("Resized:",e);
     if(e.newSize.B.x<500){
       consol
     }
  }*/
  //map.on('resize',onMapResize);
  
  function doOnResize(){
    var w=document.body.clientWidth, h=document.body.clientHeight;
    $("#miniNav").hide();
    if(w<700){
      $("#topNavRMenuElements").hide();
      $("#popupMiniMenu").hide();
      //$("#miniNav").show();
      $("#topNav").height(60);
      $("#menuPopupBtn").show();
      $("#topNavContainer").css("bottom","0px");
    } else {
      $("#topNavRMenuElements").show();
      $("#popupMiniMenu").show();
      $("#topNavRMenuElements").show();
      $("#topNav").height(80);
      $("#menuPopupBtn").hide();
      $("#topNavContainer").css("bottom","10px");
    }
  }
  
  function doOnReady(){
    doOnResize();
    $(window).on('hashchange', function(e) {
      //console.log(location.hash);
      var s=location.hash.substr(1);
      commandRouter(s);
    });
  }
  function dropCommand(){
    location.hash='';// TODO! Возможно есть другие изящное решение. Вызывает рекурсивно commandRouter
  }
  
  function commandRouter(cmd){
    var sepPos=cmd.indexOf('!');
    if(sepPos>=0) {
      args=cmd.substr(sepPos+1);
      cmd=cmd.substr(0,sepPos);
    }
    
    switch(cmd){
      case 'showAllRequestsCity':
        dropCommand();
        $.get("get_my_requests.php",{},function(results){
        var r,req  ,marker,lat,lng,c2,c1,c,p;
          for (r in results){
            req=results[r];
            p=req.pos;
            console.log(results[req]);
            
            if(!p) continue;
            c=p.indexOf('(');
            c1=p.indexOf(' ');
            c2=p.indexOf(')');
            lat=parseFloat(p.substr(c+1,c1-c-1));
            lng=parseFloat(p.substr(c1+1,c2-c1-1));
            marker=L.marker({lat:lat,lng:lng});
            marker.addTo(map)
          }
        });
        break;
    }
    
  }
  $(window).on('resize', doOnResize);
  $( document ).ready(doOnReady());
  
	map.on('locationfound', onLocationFound);
	map.on('locationerror', onLocationError);
	map.locate({setView: true, maxZoom: 16});  
</script>

</body>
</html>
