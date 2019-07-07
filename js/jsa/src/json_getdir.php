<?php
function collectFiles($localPath,&$dirRecords,&$recId,$parentId=0,$level=0){
    $recCount=0;
    $localFileRecords=array();
    if (is_dir($localPath)) {
        if ($dh = opendir($localPath)) {
            while (($fileName = readdir($dh)) !== false) {
                if(($fileName=='.')||($fileName=='..')) continue;
                $fullPath=$localPath .'/'. $fileName;
                $fileType=filetype($fullPath);
                if($fileType=='dir'){
                    $localFileRecords[$fileName]=array($fileName,$fullPath,$fileType,$level);
                }
            }
            closedir($dh);
        }
    }
    ksort($localFileRecords,SORT_NATURAL | SORT_FLAG_CASE);
    while($rawRec=array_shift($localFileRecords)) {
        $dirRecords[$recId]=$rawRec;
        $myId=$recId;
        $rec=&$dirRecords[$recId++];
        $rec[4]=$parentId;
        if ($rec[2]=='dir'){
            $rec[5]=collectFiles($rec[1],$dirRecords,$recId,$myId,$level+1);
        }
        $recCount++;
        
        
    }
    // $dirRec[id]=>( 0:filename, 1:full path with filename, 
    //   2:file type, 3:level, 4:parent id, 5: count of child records)
    return $recCount;
}

$recArray=array();
$recId=1;
collectFiles(getcwd(),$recArray,$recId);

if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml") ) {
   header("Content-type: application/xhtml+xml;charset=utf-8");
} else {
   header("Content-type: text/xml;charset=utf-8");
}
echo "<"."?xml version='1.0' encoding='utf-8'?".">\n";
echo "<rows>";
echo "<page>1</page>";
echo "<total>1</total>";
echo "<records>1</records>";

foreach($recArray as $k=>$v){
   echo "<row>";         
      echo '<cell>'.$k.'</cell>';
      echo '<cell>'.$v[0].'</cell>';
      echo '<cell>'.$v[1].'</cell>'; 
      echo '<cell>'.$v[2].'</cell>'; // type
      echo "<cell>".$v[3]."</cell>"; // level
      echo "<cell><![CDATA[".($v[4]?$v[4]:'NULL')."]]></cell>";
      echo "<cell>".((!$v[5])?'true':'false')."</cell>";
      echo "<cell>false</cell>"; // expanded field
    echo "</row>\n";
    
}

print "</rows>";
?>
