<?php
namespace doq;

class Database{
    public $connection;
    public $serverEncoding='utf-8';
    public $clientEncoding='utf-8';
    public $filePath;
    
    static function create($databaseAlias='primary') {
        global $doq;
        if(!isset($doq['config']['databases'])){
            error_log("Базы данных не сконфигурированы",1);
            return NULL;
        }
        if (!isset($doq['config']['databases'][$databaseAlias])){
            error_log(sprintf("База данных %s не сконфигурирована",$databaseAlias),1);
            return NULL;
        }
        $dba=&$doq['config']['databases'][$databaseAlias];
        if($dba['type']=='accdb'){
            $dbase=new Database();
            $dbase->serverEncoding=$dba['encoding'];

            if(!$dbase->connect_ODBC($dba['path'], '', '')) {
                print "<br>".$dbase->errorMsg();
                return NULL;
            }
            $doq['databases'][$databaseAlias]=&$dbase;
            $doq['dbase']=&$dbase;
            return $dbase;
        } else {
            return NULL;
        }
    }

    function connect_ODBC($filePath,$login,$pass){
        $this->filePath=$filePath;
        if($this->serverEncoding!=$this->clientEncoding){
            $filePath=mb_convert_encoding($filePath, $this->serverEncoding, $this->clientEncoding);
        }
        @$this->connection=odbc_pconnect ('Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq='.$filePath,'','');
        return $this->connection;
    }
    function stringFromServer($s){
        if($this->serverEncoding!==$this->clientEncoding){
           return mb_convert_encoding($s,$this->clientEncoding,$this->serverEncoding);
        } else return $s;
    }
    function errorMsg(){
        return $this->stringFromServer(odbc_errormsg());
    }
    function error(){
        return odbc_error($this->connection);
    }
    function createQuery($sqlText, array $fieldDefs=NULL){
        return new Query($this,$sqlText,$fieldDefs);
    }
}

class Query{
    public $doqBase;
    public $dbprepared,$fields,$fieldsByNum,$fieldDefs,$rowLoaded=0;
   
    function __construct(&$doqBase, $sqltext , $fieldDefs=NULL){
        $this->doqBase=&$doqBase;
        $this->fieldDefs=$fieldDefs;
        $this->dbprepared=odbc_exec($this->doqBase->connection,$sqltext);
        if($this->dbprepared===false) {
            return false;
        }
        $this->fields=array();
        $this->fieldsByNum=array();
        $this->fieldCount=0;
        $this->fieldCount=odbc_num_fields($this->dbprepared);
        for($i = 1;$i <= $this->fieldCount ;$i++){
            $s=odbc_field_name($this->dbprepared,$i);
            $this->fieldsByNum[$i] = $s= odbc_field_name($this->dbprepared,$i);
            $this->fields[$s] = array(
                'fieldNum'=>$i, 
                'fieldType'=> odbc_field_type($this->dbprepared, $i), 
                'scale'=> odbc_field_scale($this->dbprepared, $i), 
                'len'=> odbc_field_len($this->dbprepared,$i)
            );
        }
        return true;
    }
    function load(){
        $this->rows=array();
        $rowNo=1;
        $pkFieldNum=0;
        if(isset($this->fieldDefs['pkField'])){
            $pkFieldNum=$this->fields[$this->fieldDefs['pkField']]['fieldNum'];
        }
        
        while (odbc_fetch_row($this->dbprepared)) { 
            $row=array();
            for($i=1; $i <= $this->fieldCount; $i++){
                $v=$this->doqBase->stringFromServer(odbc_result($this->dbprepared, $i));
                $row[$i]=$v;
            }            
            if($pkFieldNum!==0) {
                $pk=$row[$pkFieldNum];
            } else $pk=$rowNo;
            $this->rows[$pk]=$row;
            $rowNo++;
        }
        $this->rowLoaded=$rowNo-1;
    }
    function renderRowsToString(){
        $s2='';
        foreach ($this->rows as &$row){
            $s3='';
            for($i=1;$i<=$this->fieldCount;$i++){
                $s3.='<td>'.$row[$i].'</td>';
            }
            $s2.='<tr>'.$s3.'</tr>';
        }
                
        $s1='';
        for($i = 1;$i <= $this->fieldCount ;$i++){
            $name=$this->fieldsByNum[$i];
            $s1.='<td>'.$name.'</td>';
        }
        $s1='<tr>'.$s1.'</tr>';
        
        if($s2){
            return '<table border=1>'.$s1.$s2.'</table>';
        } else {
            return 'Данных нет';
        }
    }

}


?>