<?php
require_once './doq/Logger.php';
require_once './doq/data/BaseProvider.php';
require_once './config.php';
require_once './doq/Translate.php';
require_once './lang/lang_ru.php';
require_once './doq/Session.php';

doq\Logger::init();
if (!doq\Session::init()) {
  exit;
}
?>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<link rel="stylesheet" href="css/bootstrap.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>body {
  padding-top: 40px;
  padding-bottom: 40px;
  background-color: #eee;
}

.form-signin {
  max-width: 330px;
  padding: 15px;
  margin: 0 auto;
}
.form-signin .form-signin-heading,
.form-signin .checkbox {
  margin-bottom: 10px;
}
.form-signin .checkbox {
  font-weight: normal;
}
.form-signin .form-control {
  position: relative;
  height: auto;
  -webkit-box-sizing: border-box;
     -moz-box-sizing: border-box;
          box-sizing: border-box;
  padding: 13px;
  font-size: 16px;
}
.form-signin .form-control:focus {
  z-index: 2;
}

#info {
 padding-top:30px;
 padding-bottom:30px;
}
</style>
<script>
var nonce;
function message(msg,level,focusTo) {
  if (!level) level=0;
  var cls=(['alert-info','alert-warning','alert-danger','alert-success'])[level];
  //$('#info').css('background-color',(['none','#a0a080','red'])[level]);
  $('#info').removeClass();
  $('#info').addClass('alert '+cls);
  $('#info').html(msg);
  if(focusTo) {
    $(focusTo).focus();
  }

}

function signIn(e) {
  e.preventDefault();
  var pass =$('#inputPassword').val();
  var login=$('#inputLogin').val();
  var remember=$('#inputRememberMe').val();

  if(pass.length<3) {
    message ('Пароль слишком короткий!',1,'#inputPassword');
    return;
  }

  if(login.length<3) {
    message ('Логин слишком короткий!');
    return;
  }
  var data={
    login:login,
    nonce:nonce,
    remember:remember
  };
  lockForm(true);
  message('Отправка данных...',1);
  $.post("login_actions.php?action=getLoginNonces",data,function (keyData) {
    if (keyData.cnoncesnonce) {
      //message('Проверяю sha1(pass:'+pass+'<br>cnoncenonce:'+keyData.cnoncesnonce+')='+hex_sha1(pass+keyData.cnoncesnonce)+'<br>nonce:'+nonce);
      data.passHash=hex_sha1(hex_sha1(pass+keyData.cnoncesnonce)+nonce);
      $.post("login_actions.php?action=signIn",data,function (results) {
        if(results.error) {
          message('Получил ошибку '+results.error);
        } else if(results.result) {
          if (results.result=='ok') {
            message('Авторизация прошла успешно! ',3);
            location.href='/';
          } else {
            message('Неправильно введены логин или пароль!',2);
            lockForm(false);
          }
        }
      },'json').fail(function(errData){
        message('Error '+errData.error,2);
        lockForm(false);
      });
    } else {
      message('Не смог получить ключ для логина. '+keyData.error,2);
      lockForm(false);
    }
  },'json').fail(function(errData){message('Error '+errData.error,2)});

}

function lockForm(disabled) {
  $('#btnLogin').prop('disabled', disabled);
  $('#btnRegister').prop('disabled', disabled);
}

function signupNewUser(e) {
  e.preventDefault();
  if (!checkPasswordEquality(false)) {
    message('Пароль не совпадает или пустой!');
    return;
  }
  var inputNewLogin=$('#inputNewLogin').val();
  var inputNewPassword=$('#inputNewPassword').val()

  if(inputNewPassword.length<3) {
    message('Пароль слишком короткий!');
    return;
  }

  if (inputNewLogin.length<3) {
    message('Логин слишком короткий!');
    return;
  }

  if (!nonce) {
    message('Сервер не предоставил токен отправки формы. Не могу отправить данные');
    return;
  }
  var cnonce  =hex_sha1('a'+Math.random());
  var mysecret=hex_sha1('b'+Math.random());
  var snonce=nonce.split(':')[0];
  var data={
    inputNewLogin:inputNewLogin,
    inputNewEmail:$('#inputNewEmail').val(),
    inputNewPhone:$('#inputNewPhone').val(),
    inputNewOrg:$('#inputNewOrg').val(),
    inputNewFirstName:$('#inputNewFirstName').val(),
    inputNewLastName:$('#inputNewLastName').val(),
    hash:nonce+'|'+hex_sha1(inputNewPassword+hex_sha1(cnonce+snonce)),
    ctoken: cnonce+'!'+hex_sha1(mysecret+cnonce)
  };

  message('Отправка данных...');
  lockForm(true);
  $.post("login_actions.php?action=signupNewUser",data,
    function (retData) {
      lockForm(false);
      if(retData.result) {
        if (retData.result=='Registered') {
          message ('Вы зарегистрированы. Для входа в систему обратитесь к администратору!',3);
          lockForm(false);
        } else if (retData.result=='Approved') {
          message ('Вы зарегистрированы. Попробуйте войти!',3);
          $('#inputLogin').val($('#inputNewLogin').val());
          lockForm(false);
        }
      } else if (retData.error)  {
        if (retData.error=='Bad symbols in login') {
          message('Вы использовали недопустимые символы в логине',2,'#inputNewLogin');
        } else if (retData.error=='User already registered') {
          message('Логин '+inputNewLogin+' уже используется. Попробуйте ввести другой логин',2,'#inputNewLogin');
        } else if (retData.error=='User is registering') {
          message('Логин '+inputNewLogin+' уже в процессе регистрации',1,'#inputNewLogin');
        } else {
          message(retData.error,1,'#inputNewLogin');
        }
        lockForm(false);
      }
    },'json')
    .fail(function(errData){
      console.log(errData);
      message('Ошибка связи',2);
      lockForm(false);
    });
}

function checkPasswordEquality(isBlurrer) {
  var ok=false,p1=$('#inputNewPassword').val(), p2=$('#inputNewPassword2').val();
  if (isBlurrer) {
    if((!p1)||(!p2)) {
      ok=true;
    } else {
      ok=(p1==p2);
    }
  } else {
    if((!p1)||(!p2)) {
      ok=false;
    } else {
      ok=(p1==p2);
    }
  }
  $('#pass1').removeClass('has-success');
  $('#pass1').removeClass('has-warning');

  if(ok) {
    $('#pass1').addClass('has-success');
  } else {
    $('#pass1').addClass('has-warning');
  }
  return ok;
}
function switchPanel() {
  $('#signInForm').toggleClass('hidden');
  $('#signUpForm').toggleClass('hidden');
}
function getNonce() {
  message('Запрос допуска к авторизации. Можете пока вводить свои данные',1);
  $.getJSON("login_actions.php",{action:'getNonce'})
  .done (function(json){
    if(json.error) {
      message('Error: '+json.error,2);
    }
    nonce=json.nonce;
    message('Допуск к авторизации получен. Вводите логин и пароль, либо зарегистрируйтесь',3);
    lockForm(false);
  })
  .fail(function(hr,textStatus,error){
    message('Ошибка получения допуска к авторизации: '+'<br>'+hr+'<br>'+textStatus+'<br>'+error,2);
  });
}
</script>
</head>

<body onload="getNonce()">
<div id="signInForm" class="container">
	<form class="form-signin" onSubmit="signIn(event)" action="#">
		<h2 class="form-signin-heading">Вход в iCity</h2>
		<label for="inputLogin" class="sr-only">Введите логин</label>
		<input type="text" id="inputLogin" class="form-control" placeholder="Введите логин" required autofocus>
		<label for="inputPassword" class="sr-only">Пароль</label>
		<input type="password" id="inputPassword" class="form-control" placeholder="Введите пароль" required>
		<div class="checkbox">
		  <label>
			<input type="checkbox" id="inputRememberMe" value="1"> Запомнить меня
		  </label>
		</div>
		<br/>
		<button id='btnLogin' class="btn btn-lg btn-primary btn-block" type="submit" disabled>Вход</button>
		<br/>
		<a href='javascript:;' onclick="switchPanel();">Зарегистрироваться</a>
		<br/>
	</form>
</div>

<div id='signUpForm' class="container hidden">
	<form class="form-signin" onSubmit="signupNewUser(event)" action="#!/login">
		<h2 class="form-signin-heading">Регистрация нового пользователя</h2>
		<label for="inputNewLogin" class="sr-only">Введите новый логин</label>
		<input type="text" id="inputNewLogin" class="form-control" placeholder="Введите новый логин" required autofocus>

		<label for="inputNewPassword" class="sr-only">Введите новый пароль</label>
		<input type="password" id="inputNewPassword" class="form-control" placeholder="Введите новый пароль" required>

    <div class="form-group" id="pass1">
  		<label for="inputNewPassword2" class="sr-only has-warning">Повторите пароль</label>
  		<input type="password" id="inputNewPassword2" onblur="checkPasswordEquality(true)" class="form-control form-control-danger" placeholder="Повторите пароль" required>
    </div>
		<label for="inputNewOrg" class="sr-only">Ваш экспертный профиль по доступной среде</label>
		<input type="text" id="inputNewOrg" class="form-control" placeholder="Профиль эксперта" required>


		<label for="inputNewFirstdName" class="sr-only">Ваше имя</label>
		<input type="text" id="inputNewFirstName" class="form-control" placeholder="Ваше имя" required>

		<label for="inputNewLastName" class="sr-only">Ваша фамилия</label>
		<input type="text" id="inputNewLastName" class="form-control" placeholder="Ваша фамилия" required>

		<label for="inputNewPhone" class="sr-only">Ваш рабочий телефон</label>
		<input type="text" id="inputNewPhone" class="form-control" placeholder="Рабочий телефон (необязательно)">

		<label for="inputNewEmail" class="sr-only">Email (необязательно)</label>
		<input type="email" id="inputNewEmail" class="form-control" placeholder="Email (необязательно)">
		<br/>
		<div class="btn-group">
      <button class="btn btn-lg btn-default" type="button" onclick='switchPanel();'>Назад</button>
      <button id="btnRegister" class="btn btn-lg btn-primary" type="submit" disabled>Зарегистрироваться</button>
    </div>

	</form>
</div>

<div class="row">
<div class='col-sm-4 col-sm-offset-4' style='text-align:center'>
  <div id='info'></div>
</div>


</div>

<div style='text-align:center'>

  <?php
  /*
  print "SESSION_KEY: ".$_COOKIE['SESSION_KEY']."</br>";
  print "sessionID: ".\doq\Session::$sessionId."</br>";
  print "CLIENT_KEY: ".$_COOKIE['CLIENT_KEY']."</br>";
  print "clientID: ".\doq\Session::$clientId."</br>";
  print "UserID: ".\doq\Session::$userId."</br>";
   
   */
  ?>
   
</div>
<!-- jQuery (necessary for Bootstrap's JavaScript plugins) --> 
<script src="js/jquery-1.11.3.min.js"></script> 
<!-- Include all compiled plugins (below), or include individual files as needed --> 
<script src="js/bootstrap.js"></script>

<script src="js/sha1.js"></script>

</body

