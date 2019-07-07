<?php
#header('Content-Type: text/javascript; charset=utf-8');
require_once './doq/Auth.php';
require_once './doq/data/BaseProvider.php';
require_once './doq/data/View.php';
require_once './config.php';
require_once './lang/lang_ru.php';

doq\data\Connection::init($GLOBALS['doq']['env']['@dataConnections']);
switch($_GET['action']) {
  case 'getNonce':
    $nonce=Auth::getFormNonce();
    if ($nonce===false) {
      print json_encode (['error' => 'Could not acquire form nonce from database']);
    } else {
      print json_encode (['nonce' => $nonce]);
    }
    break;
  case 'signupNewUser':
    $r=Auth::signupNewUser();
    print json_encode ($r);
    break;
  case 'getLoginNonces':
    $r=Auth::getLoginNonces();
    print json_encode ($r);
    break;
  case 'signIn':
    $r=Auth::signIn();
    print json_encode ($r);
    break;
}
?>