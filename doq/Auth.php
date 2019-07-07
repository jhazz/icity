<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/
require_once 'Session.php';

class Auth {
  public static function getDatabase() {
    $dataConnection=$GLOBALS['doq']['env']['@session']['#sessionDataConnection'];
    list($ok,$mysql)=\doq\data\Connection::getDataConnection($dataConnection);
    if(!$ok){
      return ['error'=>'No database connection'];
    }
    return ['mysql'=>$mysql];
  }

  public static function getFormNonce() {
    $r=self::getDatabase();
    if(isset($r['error'])) {return $r;}
    $mysql=$r['mysql'];

    $salt=$GLOBALS['doq']['env']['@session']['#formNoncesSalt'];
    $serverSecret=md5(uniqid($salt));
    $nonce=sha1(mt_rand());
    $returningNonce=$nonce.':'.sha1($serverSecret.$nonce);
    $sessionKey=$_COOKIE['SESSION_KEY'];

    $stmt1=$mysql->mysqli->prepare('DELETE FROM sys_form_nonces WHERE OPEN_TIME<(now()-INTERVAL 5 MINUTE)');
    if(!$stmt1) {
      trigger_error(\doq\t('Sessions cleanup request to the database is invalid'),E_USER_ERROR);
      return false;
    }
    $stmt1->execute();
    $stmt1->close();


    $stmt1=$mysql->mysqli->prepare('INSERT INTO sys_form_nonces (SERVER_SECRET, SESSION_KEY, NONCE, OPEN_TIME) VALUES (?,?,?,now())');
    if (!$stmt1) {
      trigger_error(\doq\t('Error inserting form nonce to the database'),E_USER_ERROR);
      return false;
    }
    $stmt1->bind_param('sss',$serverSecret,$sessionKey,$nonce);
    $stmt1->execute();
    if($stmt1->affected_rows!==1) {
      $stmt1->close();
      return false;
    }
    $stmt1->close();
    return $returningNonce;
  }

  public static function signupNewUser() {
    $r=self::getDatabase();
    if(isset($r['error'])) {return $r;}
    $mysql=$r['mysql'];
    $newLogin=mb_strtolower($_POST['inputNewLogin']);

    if (preg_match('/[\\"\\\'\\:<>\\\\]/', $newLogin)>0) {
      return ['error'=>'Bad symbols in login'];
    }

    
    $a=explode ('!',$_POST['ctoken']);
    if(count($a)!==2) {return ['error'=>'Wrong client token'];}
    $cnonce=$a[0];

    $b=explode ('|',$_POST['hash']);
    if(count($b)!==2) {return ['error'=>'Wrong hash'];}

    $nonceFromClient=$b[0];
    $passHash=$b[1];
    $r=self::checkClientNonce($mysql,$nonceFromClient);
    if (isset($r['error'])) {return $r;}
    $snonce=$r['snonce'];

    $stmt1=$mysql->mysqli->prepare('SELECT COUNT(LOGIN) FROM sys_users WHERE LOGIN=?');
    $stmt1->bind_param('s',$newLogin);
    if (!$stmt1->execute()) {return ['error'=>'Error in SQL users'];}
    if (! ($stmt1->bind_result($countOfLogin) && $stmt1->fetch())){return ['error'=>'Error in SQL users'];}
    $stmt1->close();
    if($countOfLogin>0) {return ['error'=>'User already registered'];}

    $stmt1=$mysql->mysqli->prepare('SELECT COUNT(LOGIN) FROM sys_signups WHERE LOGIN=?');
    $stmt1->bind_param('s',$newLogin);
    if (!$stmt1->execute()) {return ['error'=>'Error in SQL user signups'];}
    if (! ($stmt1->bind_result($countOfLogin) && $stmt1->fetch())){return ['error'=>'Error in SQL user signups'];}
    $stmt1->close();
    
    if($countOfLogin>0) {return ['error'=>'User is registering'];}
    
    $cnoncesnonce=sha1($cnonce.$snonce);
    $newEmail=mb_strtolower($_POST['inputNewEmail']);
    $newOrg=$_POST['inputNewOrg'];
    $newFirstName=$_POST['inputNewFirstName'];
    $newLastName=$_POST['inputNewLastName'];
    $newPhone=$_POST['inputNewPhone'];
    
    if ($GLOBALS['doq']['env']['@session']['#autoApprove']){
      $stmt1=$mysql->mysqli->prepare('INSERT INTO sys_users(CNONCENONCE, PASS_HASH, LOGIN, USER_EMAIL) VALUES (?,?,?,?)');
      $stmt1->bind_param('ssss',$cnoncesnonce,$passHash,$newLogin,$newEmail);
      if (!$stmt1->execute()) {return ['error'=>'User not signupped to database'];}
      $newUserID=$stmt1->insert_id;
      $stmt1->close();
      if(!$newUserID){return ['error'=>'User not added to database. No user id returns by insert request'];}
              
      $stmt1=$mysql->mysqli->prepare('INSERT INTO sys_persons (USER_ID, CONTACT_EMAIL, ORG_NAME, FIRST_NAME, LAST_NAME, PHONE) VALUES (?,?,?,?,?,?)');
      $stmt1->bind_param('isssss',$newUserID,$newEmail,$newOrg,$newFirstName,$newLastName,$newPhone);
      if (!$stmt1->execute()) {return ['error'=>'User not signupped to database'];}
      $stmt1->close();
      return ['result'=>'Approved'];
    } else {
      $stmt1=$mysql->mysqli->prepare('INSERT INTO sys_signups (CNONCENONCE,PASS_HASH,LOGIN,USER_EMAIL,ORG,FIRST_NAME,LAST_NAME,PHONE) VALUES (?,?,?,?,?,?,?,?)');
      $stmt1->bind_param('sssssss',$cnoncesnonce,$passHash,$newLogin,$newEmail,$newOrg,$newFirstName,$newLastName,$newPhone);
      if (!$stmt1->execute()) {return ['error'=>'User not signupped to database'];}
      $stmt1->close();
      return ['result'=>'Registered'];      
    }
  }

  public static function checkClientNonce($mysql,$nonceFromClient) {
    $r=explode(':',$nonceFromClient);
    if(count($r)!==2) {return ['error'=>'Incorrect nonce given from client'];}
    list($snonce,$snoncehash)=$r;
    $stmt1=$mysql->mysqli->prepare('SELECT SERVER_SECRET, SESSION_KEY FROM sys_form_nonces WHERE NONCE=?');
    if (!$stmt1) {return ['error'=>'Wrong SQL in the checkClientNonce'];}
    $stmt1->bind_param('s',$snonce);
    if (!$stmt1->execute()) {return ['error'=>'Wrong SQL'];}
    if (! ($stmt1->bind_result($mustServerSecret,$mustSessionKey) && $stmt1->fetch())){
      $stmt1->close();
      return ['error'=>'expired'];
      }
    $a=sha1($mustServerSecret.$snonce);
    $stmt1->close();
    if ($a!==$snoncehash) {return ['error'=>'Server nonce is different than given by a client'];}

    return ['snonce'=>$snonce,'sessionKey'=>$mustSessionKey];
  }

  public static function getLoginNonces() {
    $r=self::getDatabase();
    if(isset($r['error'])) {return $r;}
    $mysql=$r['mysql'];
    $login=mb_strtolower($_POST['login']);

    $r=self::checkClientNonce($mysql,$_POST['nonce']);
    if(isset($r['error'])) {
      return $r;
    }

    $stmt1=$mysql->mysqli->prepare('SELECT CNONCENONCE FROM sys_users WHERE LOGIN=?');
    if (!$stmt1) {return ['error'=>'Wrong SQL selecting cnoncesnonce'];}
    $stmt1->bind_param('s',$login);
    $stmt1->execute();
    $stmt1->bind_result($cnoncesnonce);
    if ($stmt1->fetch()) {
      $stmt1->close();
      return ['cnoncesnonce'=>$cnoncesnonce];
    } else {
      # Login not found, generate fake cnonce#snonce based on login
      return ['cnoncesnonce'=>sha1($login.'lgGHdfslyt234978')];
    }
  }

  public static function signIn() {
    $r=self::getDatabase();
    if(isset($r['error'])) {return $r;}
    $mysql=$r['mysql'];
    $login=$_POST['login'];
    $nonce=$_POST['nonce'];
    $remember=intval($_POST['remember']);
    $passHash=$_POST['passHash'];
    $r=self::checkClientNonce($mysql,$nonce);
    if(isset($r['error'])) {return $r;}
    $sessionKey=$r['sessionKey'];
    $stmt1=$mysql->mysqli->prepare('SELECT USER_ID,CNONCENONCE,PASS_HASH FROM sys_users WHERE LOGIN=?');
    if (!$stmt1) {
      return ['error'=>'Неправильный запрос на выбор данных о парольных ключах'];
    }
    $stmt1->bind_param('s',$login);
    $stmt1->execute();
    $stmt1->bind_result($userId,$cnoncesnonce,$mustPassHash);
    if ($stmt1->fetch()) {
      $stmt1->close();
      $must=sha1($mustPassHash.$nonce);
      if ($must===$passHash) {
        $stmt1=$mysql->mysqli->prepare('SELECT CLIENT_ID FROM sys_sessions WHERE SESSION_KEY=?');
        if (!$stmt1) {
          return ['error'=>'Неправильный запрос на выбор данных о парольных ключах'];
        }
        $stmt1->bind_param('s',$sessionKey);
        $stmt1->execute();
        $clientId=0;
        $stmt1->bind_result($clientId);
        $stmt1->fetch();
        $stmt1->close();
        if ($clientId) {
          $stmt1=$mysql->mysqli->prepare('UPDATE sys_clients SET USER_ID=?,USER_ASK_REMEMBER=? WHERE CLIENT_ID=?');
          if (!$stmt1) {
            trigger_error(\doq\t('Problem with client data schema. Failed to authorize client'),E_USER_ERROR);
            return false;
          }
          $stmt1->bind_param('iii',$userId,$remember,$clientId);
          if(!$stmt1->execute()) {
            $stmt1->close();
            return ['result'=>'wrong'];
          }
          $stmt1->close();
        }
        return ['result'=>'ok'];
      } else {
        return ['result'=>'wrong'];
      }
    } else {
      return ['result'=>'wrong'];
    }
  }
}


?>

