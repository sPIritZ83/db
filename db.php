<?php

class DB
{

    private $db_name        = '';
    private $hostname       = '';
    private $user           = '';
    private $password       = '';

    private $charset        = 'utf8';
    private $isConnected    = false;

    private $result;
    private $Log            = array();

    public function __construct($db, $charset = "") {

        $this->db_name = $db['db_name'];
        $this->hostname = $db['hostname'];
        $this->user = $db['user'];
        $this->password = $db['password'];

        if($charset) $this->charset = $charset;

    }

    public function connect()
    {

        if (!$this->isConnected) {

            $this->isConnected = mysql_connect($this->hostname, $this->user, $this->password)
                or Core::fatalError("DB connect error [".mysql_error()."]");

            mysql_select_db($this->db_name, $this->isConnected)
                or Core::fatalError("DB not exists [".mysql_error()."]");

            mysql_set_charset($this->charset);

        }

    }

    function select( $table, $where = "", $order = "", $limit = "" , $group = "", $having = "")
    {
        $parse_table = explode(">", $table);

        if(trim($parse_table[1])){

            $table = trim($parse_table[0]);
            $select = trim($parse_table[1]);

        }else $select = "*";

        if(!preg_match("~join~si",$table)) $table = "`".$table."`";

        $query = "select ". $select ." from ". $table;

        if($where) $query .= " where".$this->_makeCond($where);
        if($group) $query .= " group by ".$group;
        if($having) $query .= " having".$this->_makeCond($having);
        if($order) $query .= " order by ".$order;
        if($limit) $query .= " limit ".$limit;

        $this->query($query);

        return $this->numRows();
    }

    function update($table, $data, $where){

        $query = "update `".$table."`";

        if($data) $query .= " set".$this->_makeSet($data);
        if($where) $query .= " where".$this->_makeCond($where);

        $this->query($query);

        return $this->affRows();
    }

    function insert($table, $data){

        $query = "insert into `".$table."`";

        if($data) $query .= " set".$this->_makeSet($data);

        $this->query($query);

        return $this->insertId();
    }

    function delete($table, $where){

        $query = "delete from `".$table."`";

        if($where) $query .= " where".$this->_makeCond($where);

        $this->query($query);

        return $this->affRows();
    }

    function selectRow(){
        $args = func_get_args();
        call_user_func_array(array($this, 'select'), $args);
        return $this->getRow();
    }

    function query( $query, $ignoreError = 0 )
    {

        $this->connect();

        $start = microtime(1);
        $this->result = mysql_query($query);
        $end = microtime(1);

        $this->Log[] = ["query" => $query, "time" => round($end - $start, 6), "error" => (!$this->result ? mysql_error() : "")];

        if (!$this->result && !$ignoreError) {

            $q = preg_replace("![\n\r\t]!usi", '', $query);
            $q = preg_replace("![ ]+!usi", ' ', $q);

            $serror = mysql_error() . "\t\t\t" . '[' . $q . "]";

            $this->errorLog('mysql', $serror);

        }

        return $this->result;

    }

    function getRow()
    {
        if($this->result)
            return mysql_fetch_assoc($this->result);
        else return false;
    }

    function getAllRows( $key = "" )
    {
        if($this->result)
        {
            $return = array();
            while($row = $this->getRow()) if($key) $return[$row[$key]] = $row; else $return[] = $row;
            return $return;
        }
        else return false;
    }

    function getLog()
    {
        return $this->Log;
    }

    function errorLog($type, $error)
    {
        Core::Error($error);
    }

    function escape($str)
    {
        $this->connect();
        return mysql_real_escape_string($str);
    }

    function affRows(){
        if($this->result)
            return mysql_affected_rows();
        else return false;
    }

    function numRows(){
        if($this->result)
            return mysql_num_rows($this->result);
        else return false;
    }

    function insertId(){
        if($this->result)
            return mysql_insert_id();
        else return false;
    }

    private function _makeCond($data){

        if(!is_array($data)) return " ".$data;

        $return = "";

        foreach($data as $k=>$val){

            //todo: экранирование полей `

            if(preg_match("~^!~",$k)) {
                if(!preg_match("~^(\>|\<|not|in|\!)~si",trim($val))) $val = " = ".$val;
                $return .= " ".preg_replace("~^([\&\|])~", "$1$1 ", preg_replace("~^!~", "", $k)) . $val;
            }else{
                $return .= " ".preg_replace("~^([\&\|])~", "$1$1 ", $k) . "='" . $this->escape($val) . "'";
            }

        }

        return $return;

    }

    private function _makeSet($data){

        if(!is_array($data)) return " ".$data;

        $return = array();

        foreach($data as $k=>$val){

            $k = explode(".", $k); $k = implode("`.`", $k);

            if(preg_match("~^!~",$k)) {
                $return[] = "`".preg_replace("~^!~", "", $k) . "`=" . $val;
            }else{
                $return[] = "`".$k . "`='" . $this->escape($val) . "'";
            }
        }

        return " ".implode(", ", $return);

    }

} 
