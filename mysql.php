<html>
<head> 
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />


<?

$mysqli = new mysqli("h906166802.mysql", "h906166802_mysql", "Hq7xGp_P", "h906166802_db");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
} else {
  echo "Подключение к серверу есть";
}
echo "<br>";
printf("Initial character set: %s\n", $mysqli->character_set_name());
$mysqli->set_charset("utf8");
$res = $mysqli->query("SELECT * FROM test1");
$row = $res->fetch_assoc();
var_dump($row);

?>