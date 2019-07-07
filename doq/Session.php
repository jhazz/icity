<?php
/**
Use it your own risk. Ничего не гарантирую!
GPL 
(c) JhAZZ 2017-2018
https://github.com/jhazz
*/

namespace doq;
require_once './doq/data/BaseProvider.php';


class Session {
  static public $sessionId;
  static public $refreshTime;
  static public $databaseHandler;
  static public $data;
  static public $isDataSaved;
  static public $isDataChanged;
  static public $inited;
  static public $clientId;
  static public $userId;
  static public $isDisabled;
  static public $activePersonId;
  static public $activePerson;
  static public $login;
  static public $activePersonFirstName;
  static public $activePersonLastName;

  const TIMEOUT_CLIENT=31536000; # one year

  static public function set($name,$value) {
    if (!self::$inited) {
      trigger_error(\doq\t('Session has not been initialized!'),E_USER_ERROR);
      return false;
    }
    if (!is_array(self::$data)) {
      self:$data=[];
    }
    if (isset(self::$data[$name])) {
      if (self::$data[$name]===$value) {
        return true;
      }
    }
    self::$data[$name]=$value;
    self::$isDataChanged=true;
    return true;
  }

  static public function get($name,$value) {
    if (!self::$inited) {
      trigger_error(\doq\t('Session has not been initialized!'),E_USER_ERROR);
      return false;
    }
    if (!is_array(self::$data)) {
      return false;
    } else {
      return self::$data[$name];
    }
  }

  static public function save($forced=false) {
    if ((!$forced)&&(!self::$isDataChanged)) {return true;}
    self::$isDataSaved=false;
    if ((!self::$sessionId)||(!self::$databaseHandler)) {return false;}
    $mysql=self::$databaseHandler;
    $stmt1=$mysql->mysqli->prepare('UPDATE sys_sessions SET SERIALIZED_DATA=? WHERE SESSION_ID=?');
    if (!$stmt1) {trigger_error(\doq\t('Failed session data update'),E_USER_ERROR);return false;}
    $sdata=serialize(self::$data);
    $stmt1->bind_param('ss',$sdata,self::$sessionId);
    if(!$stmt1->execute()) {trigger_error(\doq\t('Update session data request is invalid'),E_USER_ERROR);return false;}
    $stmt1->close();
    self::$isDataSaved=true;
    self::$isDataChanged=false;
    return true;
  }


  static public function init() {
    self::$sessionId=0;
    self::$userId=0;
    self::$clientId=0;
    self::$isDisabled=0;
    self::$activePersonId=0;
    self::$activePerson='';
    self::$login='';

    \doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);
    $sessionDataConnection = $GLOBALS['doq']['env']['@session']['#sessionDataConnection'];
    list($ok,$mysql)=\doq\data\Connection::getDataConnection($sessionDataConnection);
    if(!$ok){
      trigger_error(\doq\t('Unable to connect to the authotization dataconnection %s',$sessionDataConnection),E_USER_ERROR);
      return false;
    }
    if(!$mysql->isConnected){
      trigger_error(\doq\t('Unable to connect to the authotization dataconnection %s',$sessionDataConnection),E_USER_ERROR);
      return false;
    }
    self::$databaseHandler=$mysql;

    # Step1. Check for long-term ClientKey
    if (isset($_COOKIE['CLIENT_KEY'])) {
      $givenClientKey=$_COOKIE['CLIENT_KEY'];
      $stmt1=$mysql->mysqli->prepare('SELECT u.ACTIVE_PERSON_ID, u.LOGIN, c.CLIENT_ID, c.USER_ID, u.IS_DISABLED FROM sys_clients c, sys_users u WHERE u.USER_ID=c.USER_ID AND c.CLIENT_KEY=? AND c.REFRESH_TIME>(now()-INTERVAL '.self::TIMEOUT_CLIENT.' SECOND) ');
      if (!$stmt1) {trigger_error(\doq\t('SQL in client select query invalid'),E_USER_ERROR); }
      if (!$stmt1->bind_param('s',$givenClientKey)) {trigger_error(\doq\t('SQL in client select invalid'),E_USER_ERROR);$stmt1->close();return false;}
      if ($stmt1->execute() && $stmt1->bind_result($activePersonId, $login, $clientId, $userId, $isDisabled) && $stmt1->fetch()) {
        $stmt1->close();
        if ($clientId) {
          self::$userId=$userId;
          self::$clientId=$clientId;
          self::$isDisabled=$isDisabled;
          self::$login=$login;
          self::$activePersonId=$activePersonId;
          
          $stmt1=$mysql->mysqli->prepare('UPDATE sys_clients SET REFRESH_TIME=now() WHERE CLIENT_ID=?');
          if(!$stmt1) {trigger_error(\doq\t('Clients refreshener query to the database is invalid'),E_USER_ERROR);return false;}
          $stmt1->bind_param('i',$clientId);
          if (!$stmt1->execute()) {trigger_error(\doq\t('Alarm! Table Client did not updated'),E_USER_ERROR);return false;}
          $stmt1->close();

          if($activePersonId){
            $stmt1=$mysql->mysqli->prepare('SELECT p.FIRST_NAME, p.LAST_NAME FROM sys_persons p WHERE p.PERSON_ID=?');
            if (!$stmt1->bind_param('i',$activePersonId)) {trigger_error(\doq\t('SQL in Person selection is invalid'),E_USER_ERROR);$stmt1->close();return false;}
            if ($stmt1->execute() && $stmt1->bind_result($firstName, $lastName) && $stmt1->fetch()) {
              $stmt1->close();
              self::$activePersonFirstName=$firstName;
              self::$activePersonLastName=$lastName;
            }
          }
        }
      } else {$stmt1->close();}
    }

    if(!self::$clientId) {
      $newClientKey=sha1(mt_rand()); # 40 for hex, 32 for base64
      $ok=false;
      for($j=0;$j<2;$j++) {
        $stmt1=$mysql->mysqli->prepare('INSERT INTO sys_clients (CLIENT_KEY,USER_ID,OPEN_TIME,REFRESH_TIME) VALUES (?,0,now(),now())');
        if (!$stmt1) {trigger_error(\doq\t('Looks like a client table schema was changed!'),E_USER_ERROR);return false;}
        $stmt1->bind_param('s',$newClientKey);
        $stmt1->execute();
        if($stmt1->affected_rows===1) {
          $ok=true;
          break;
        }
        $stmt1->close();
      }
      if($ok) {
        self::$clientId=$stmt1->insert_id;
        $stmt1->close();
        setcookie('CLIENT_KEY',$newClientKey,time()+self::TIMEOUT_CLIENT);
      } else {
        trigger_error(\doq\t('Client table do not accepts new clients!'),E_USER_ERROR);
        return false;
      }
      $shouldDropSession=true;
    } else {
      $shouldDropSession=false;
      setcookie('CLIENT_KEY',$givenClientKey,time()+self::TIMEOUT_CLIENT);
    }

    # Step2. Check for short-term sessionKey
    if (isset($_COOKIE['SESSION_KEY'])&&(!$shouldDropSession)) {
      $givenSessionKey=$_COOKIE['SESSION_KEY'];
      $stmt1=$mysql->mysqli->prepare('DELETE FROM sys_sessions WHERE REFRESH_TIME<(now()-INTERVAL 24 HOUR)');
      if(!$stmt1) {trigger_error(\doq\t('Sessions cleanup request to the database is invalid'),E_USER_ERROR);return false;}
      $stmt1->execute();
      $stmt1->close();

      $stmt1=$mysql->mysqli->prepare('SELECT SESSION_ID, REFRESH_TIME, SERIALIZED_DATA FROM sys_sessions WHERE SESSION_KEY=?');
      if(!$stmt1) {trigger_error(\doq\t('Session refreshener query to the database is invalid'),E_USER_ERROR);return false;}
      $stmt1->bind_param('s',$givenSessionKey);
      if(!$stmt1->execute()) {trigger_error(\doq\t('Unable to get registered session data'),E_USER_ERROR);return false;}
      if(!$stmt1->bind_result($sessionId,$refreshTime,$sdata)) {trigger_error(\doq\t('Unable to get registered session data'),E_USER_ERROR);return false;}
      if($stmt1->fetch()) {
        self::$sessionId=$sessionId;
        if($sdata!=='') {
          self::$data=unserialize($sdata);
        } else {
          self::$data=false;
        }
        $stmt1->close();
        self::$inited=true;
        self::$isDataChanged=false;
        self::$isDataSaved=false;

        $stmt1=$mysql->mysqli->prepare('UPDATE sys_sessions SET CLIENT_ID=?,REFRESH_TIME=now() WHERE SESSION_ID=?');
        if(!$stmt1) {trigger_error(\doq\t('Sessions refreshing request to the database is invalid'),E_USER_ERROR);return false;}
        $stmt1->bind_param('ii',self::$clientId,$sessionId);
        if(!$stmt1->execute()) {trigger_error(\doq\t('Problem with refreshing sessions table'),E_USER_ERROR);return false;}
        $stmt1->close();

      } else {
        $stmt1->close();
      }
    }

    if (!self::$sessionId) {
      $ok=false;
      $newSessionKey=sha1(mt_rand()); # 40 for hex, 32 for base64
      for($j=0;$j<2;$j++) {
        $stmt1=$mysql->mysqli->prepare('INSERT INTO sys_sessions (SESSION_KEY,CLIENT_IP_ADDR,CLIENT_ID,OPEN_TIME,REFRESH_TIME) VALUES (?,?,?,now(),now())');
        if (!$stmt1) {trigger_error(\doq\t('Looks like a session data schema was changed!'),E_USER_ERROR);return false;}
        $stmt1->bind_param('sss',$newSessionKey,$_SERVER['REMOTE_ADDR'],self::$clientId);
        $stmt1->execute();
        if($stmt1->affected_rows===1) {
          $ok=true;
          break;
        }
      }
      if(!$ok) {
        $stmt1->close();
        return false;
      }
      self::$sessionId=$stmt1->insert_id;
      $stmt1->close();
      setcookie('SESSION_KEY',$newSessionKey); # until browser is open
    }
    self::$inited=true;
    self::$isDataChanged=false;
    self::$isDataSaved=false;
    return true;
  }

}

?>