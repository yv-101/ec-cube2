<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2007 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

/**
 * SQLの構築・実行を行う
 *
 * @author LOCKON CO.,LTD.
 * @version $Id$
 */
class SC_Query {
    var $option;
    var $where;
    var $conn;
    var $groupby;
    var $order;

    /**
     * コンストラクタ.
     *
     * @param $dsn
     * @param boolean $err_disp エラー表示を行うかどうか
     * @param boolean $new 新規に接続を行うかどうか
     * @return SC_Query
     */
    function SC_Query($dsn = "", $err_disp = true, $new = false) {
        $this->conn = new SC_DBconn($dsn, $err_disp, $new);
        $this->where = "";
    }

    /**
     *  エラー判定を行う.
     *
     * @return boolean
     */
    function isError() {
        if(PEAR::isError($this->conn->conn)) {
            return true;
        }
        return false;
    }

    /**
     * COUNT文を実行する.
     *
     * @param string $table テーブル名
     * @param string $where where句
     * @param array $arrval プレースホルダ
     * @return integer 件数
     */
    function count($table, $where = "", $arrval = array()) {
        if(strlen($where) <= 0) {
            $sqlse = "SELECT COUNT(*) FROM $table";
        } else {
            $sqlse = "SELECT COUNT(*) FROM $table WHERE $where";
        }
        // カウント文の実行
        $ret = $this->conn->getOne($sqlse, $arrval);
        return $ret;
    }

    /**
     * SELECT文を実行する.
     *
     * @param string $col カラム名. 複数カラムの場合はカンマ区切りで書く
     * @param string $table テーブル名
     * @param string $where WHERE句
     * @param array $arrval プレースホルダ
     * @param integer $fetchmode 使用するフェッチモード。デフォルトは DB_FETCHMODE_ASSOC。
     * @return array|null
     */
    function select($col, $table, $where = "", $arrval = array(), $fetchmode = DB_FETCHMODE_ASSOC) {
        $sqlse = $this->getsql($col, $table, $where);
        $ret = $this->conn->getAll($sqlse, $arrval, $fetchmode);
        return $ret;
    }

    /**
     * 直前に実行されたSQL文を取得する.
     * SC_DBconn::getLastQuery() を利用.
     *
     * @param boolean $disp trueの場合、画面出力を行う.
     * @return string SQL文
     */
    function getLastQuery($disp = true) {
        return $this->conn->getLastQuery($disp);
    }

    function commit() {
        $this->conn->query("COMMIT");
    }

    function begin() {
        $this->conn->query("BEGIN");
    }

    function rollback() {
        $this->conn->query("ROLLBACK");
    }

    function exec($str, $arrval = array()) {
        $this->conn->query($str, $arrval);
    }

    function autoselect($col, $table, $arrwhere = array(), $arrcon = array()) {
        $strw = "";
        $find = false;
        foreach ($arrwhere as $key => $val) {
            if(strlen($val) > 0) {
                if(strlen($strw) <= 0) {
                    $strw .= $key ." LIKE ?";
                } else if(strlen($arrcon[$key]) > 0) {
                    $strw .= " ". $arrcon[$key]. " " . $key ." LIKE ?";
                } else {
                    $strw .= " AND " . $key ." LIKE ?";
                }

                $arrval[] = $val;
            }
        }

        if(strlen($strw) > 0) {
            $sqlse = "SELECT $col FROM $table WHERE $strw ".$this->option;
        } else {
            $sqlse = "SELECT $col FROM $table ".$this->option;
        }
        $ret = $this->conn->getAll($sqlse, $arrval);
        return $ret;
    }

    /**
     * クエリを実行し、全ての行を返す
     *
     * @param string $sql SQL クエリ
     * @param array $arrVal プリペアドステートメントの実行時に使用される配列。配列の要素数は、クエリ内のプレースホルダの数と同じでなければなりません。 
     * @param integer $fetchmode 使用するフェッチモード。デフォルトは DB_FETCHMODE_ASSOC。
     * @return array データを含む2次元配列。失敗した場合に 0 または DB_Error オブジェクトを返します。
     */
    function getall($sql, $arrval = array(), $fetchmode = DB_FETCHMODE_ASSOC) {
        $ret = $this->conn->getAll($sql, $arrval, $fetchmode);
        return $ret;
    }

    function getsql($col, $table, $where) {
        if($where != "") {
            // 引数の$whereを優先して実行する。
            $sqlse = "SELECT $col FROM $table WHERE $where " . $this->groupby . " " . $this->order . " " . $this->option;
        } else {
            if($this->where != "") {
                    $sqlse = "SELECT $col FROM $table WHERE $this->where " . $this->groupby . " " . $this->order . " " . $this->option;
                } else {
                    $sqlse = "SELECT $col FROM $table " . $this->groupby . " " . $this->order . " " . $this->option;
            }
        }
        return $sqlse;
    }

    function setoption($str) {
        $this->option = $str;
    }

    function setlimitoffset($limit, $offset = 0, $return = false) {
        if (is_numeric($limit) && is_numeric($offset)){

            $option = " LIMIT " . $limit;
            $option.= " OFFSET " . $offset;

            if($return){
                return $option;
            }else{
                $this->option.= $option;
            }
        }
    }

    function setgroupby($str) {
        if (strlen($str) == 0) {
            $this->groupby = '';
        } else {
            $this->groupby = "GROUP BY " . $str;
        }
    }

    function andwhere($str) {
        if($this->where != "") {
            $this->where .= " AND " . $str;
        } else {
            $this->where = $str;
        }
    }

    function orwhere($str) {
        if($this->where != "") {
            $this->where .= " OR " . $str;
        } else {
            $this->where = $str;
        }
    }

    function setwhere($str) {
        $this->where = $str;
    }

    function setorder($str) {
        if (strlen($str) == 0) {
            $this->order = '';
        } else {
            $this->order = "ORDER BY " . $str;
        }
    }


    function setlimit($limit){
        if ( is_numeric($limit)){
            $this->option = " LIMIT " .$limit;
        }
    }

    function setoffset($offset) {
        if ( is_numeric($offset)){
            $this->offset = " OFFSET " .$offset;
        }
    }

    /**
     * INSERT文を実行する.
     *
     * @param string $table テーブル名
     * @param array $sqlval array('カラム名' => '値',...)の連想配列
     * @return
     */
    function insert($table, $sqlval) {
        $strcol = '';
        $strval = '';
        $find = false;

        if(count($sqlval) <= 0 ) return false;

        foreach ($sqlval as $key => $val) {
            $strcol .= $key . ',';
            if(eregi("^Now\(\)$", $val)) {
                $strval .= 'Now(),';
            } else {
                $strval .= '?,';
                $arrval[] = $val;
            }
            $find = true;
        }
        if(!$find) {
            return false;
        }
        // 文末の","を削除
        $strcol = ereg_replace(",$","",$strcol);
        // 文末の","を削除
        $strval = ereg_replace(",$","",$strval);
        $sqlin = "INSERT INTO $table(" . $strcol. ") VALUES (" . $strval . ")";
        // INSERT文の実行
        $ret = $this->conn->query($sqlin, $arrval);

        return $ret;
    }

    /**
     * UPDATE文を実行する.
     *
     * @param string $table テーブル名
     * @param array $sqlval array('カラム名' => '値',...)の連想配列
     * @param string $where WHERE句
     * @param array $arrValIn WHERE句用のプレースホルダ配列 (従来は追加カラム用も兼ねていた)
     * @param array $arrRawSql 追加カラム
     * @param array $arrRawSqlVal 追加カラム用のプレースホルダ配列
     * @return
     */
    function update($table, $sqlval, $where = "", $arrValIn = array(), $arrRawSql = array(), $arrRawSqlVal = array()) {
        $arrCol = array();
        $arrVal = array();
        $find = false;
        foreach ($sqlval as $key => $val) {
            if (eregi("^Now\(\)$", $val)) {
                $arrCol[] = $key . '= Now()';
            } else {
                $arrCol[] = $key . '= ?';
                $arrVal[] = $val;
            }
            $find = true;
        }

        if ($arrRawSql != "") {
            foreach($arrRawSql as $key => $val) {
                $arrCol[] = "$key = $val";
            }
        }
        
        $arrVal = array_merge($arrVal, $arrRawSqlVal);
        
        if (empty($arrCol)) {
            return false;
        }

        // 文末の","を削除
        $strcol = implode(', ', $arrCol);

        if (is_array($arrValIn)) { // 旧版との互換用
            // プレースホルダー用に配列を追加
            $arrVal = array_merge($arrVal, $arrValIn);
        }

        $sqlup = "UPDATE $table SET $strcol";
        if (strlen($where) >= 1) {
            $sqlup .= " WHERE $where";
        }

        // UPDATE文の実行
        return $this->conn->query($sqlup, $arrVal);
    }

    // MAX文の実行
    function max($table, $col, $where = "", $arrval = array()) {
        if(strlen($where) <= 0) {
            $sqlse = "SELECT MAX($col) FROM $table";
        } else {
            $sqlse = "SELECT MAX($col) FROM $table WHERE $where";
        }
        // MAX文の実行
        $ret = $this->conn->getOne($sqlse, $arrval);
        return $ret;
    }

    // MIN文の実行
    function min($table, $col, $where = "", $arrval = array()) {
        if(strlen($where) <= 0) {
            $sqlse = "SELECT MIN($col) FROM $table";
        } else {
            $sqlse = "SELECT MIN($col) FROM $table WHERE $where";
        }
        // MIN文の実行
        $ret = $this->conn->getOne($sqlse, $arrval);
        return $ret;
    }

    // 特定のカラムの値を取得
    function get($table, $col, $where = "", $arrval = array()) {
        if(strlen($where) <= 0) {
            $sqlse = "SELECT $col FROM $table";
        } else {
            $sqlse = "SELECT $col FROM $table WHERE $where";
        }
        // SQL文の実行
        $ret = $this->conn->getOne($sqlse, $arrval);
        return $ret;
    }

    function getone($sql, $arrval = array()) {
        // SQL文の実行
        $ret = $this->conn->getOne($sql, $arrval);
        return $ret;

    }

    /**
     * 一行をカラム名をキーとした連想配列として取得
     *
     * @param string $table テーブル名
     * @param string $col カラム名
     * @param string $where WHERE句
     * @param array $arrVal プレースホルダ配列
     * @param integer $fetchmode 使用するフェッチモード。デフォルトは DB_FETCHMODE_ASSOC。
     * @return array array('カラム名' => '値', ...)の連想配列
     */
    function getRow($table, $col, $where = "", $arrVal = array(), $fetchmode = DB_FETCHMODE_ASSOC) {
        $sqlse = "SELECT $col FROM $table";
        
        if (strlen($where) >= 1) {
            $sqlse .= " WHERE $where";
        }
        // SQL文の実行
        return $this->conn->getRow($sqlse, $arrVal ,$fetchmode);
    }

    // 1列取得
    function getCol($table, $col, $where = "", $arrval = array()) {
        if (strlen($where) <= 0) {
            $sqlse = "SELECT $col FROM $table";
        } else {
            $sqlse = "SELECT $col FROM $table WHERE $where";
        }
        // SQL文の実行
        return $this->conn->getCol($sqlse, 0, $arrval);
    }

    /**
     * レコードの削除
     *
     * @param string $table テーブル名
     * @param string $where WHERE句
     * @param array $arrval プレースホルダ
     * @return
     */
    function delete($table, $where = "", $arrval = array()) {
        if(strlen($where) <= 0) {
            $sqlde = "DELETE FROM $table";
        } else {
            $sqlde = "DELETE FROM $table WHERE $where";
        }
        $ret = $this->conn->query($sqlde, $arrval);
        return $ret;
    }

    function nextval($table, $colname) {
        $sql = "";
        // postgresqlとmysqlとで処理を分ける
        if (DB_TYPE == "pgsql") {
            $seqtable = $table . "_" . $colname . "_seq";
            $sql = "SELECT NEXTVAL('$seqtable')";
        }else if (DB_TYPE == "mysql") {
            $sql = "SELECT last_insert_id();";
        }
        $ret = $this->conn->getOne($sql);

        return $ret;
    }

    function currval($table, $colname) {
        $sql = "";
        if (DB_TYPE == "pgsql") {
            $seqtable = $table . "_" . $colname . "_seq";
            $sql = "SELECT CURRVAL('$seqtable')";
        }else if (DB_TYPE == "mysql") {
            $sql = "SELECT last_insert_id();";
        }
        $ret = $this->conn->getOne($sql);

        return $ret;
    }

    function setval($table, $colname, $data) {
        $sql = "";
        if (DB_TYPE == "pgsql") {
            $seqtable = $table . "_" . $colname . "_seq";
            $sql = "SELECT SETVAL('$seqtable', $data)";
            $ret = $this->conn->getOne($sql);
        }else if (DB_TYPE == "mysql") {
            $sql = "ALTER TABLE $table AUTO_INCREMENT=$data";
            $ret = $this->conn->query($sql);
        }

        return $ret;
    }

    function query($n ,$arr = "", $ignore_err = false){
        $result = $this->conn->query($n, $arr, $ignore_err);
        return $result;
    }

    /**
     * auto_incrementを取得する.
     *
     * @param string $table_name テーブル名
     * @return integer
     */
    function get_auto_increment($table_name){
        // ロックする
        $this->query("LOCK TABLES $table_name WRITE");

        // 次のIncrementを取得
        $arrRet = $this->getAll("SHOW TABLE STATUS LIKE ?", array($table_name));
        $auto_inc_no = $arrRet[0]["Auto_increment"];

        // 値をカウントアップしておく
        $this->conn->query("ALTER TABLE $table_name AUTO_INCREMENT=?" , $auto_inc_no + 1);

        // 解除する
        $this->query('UNLOCK TABLES');

        return $auto_inc_no;
    }
}

?>
