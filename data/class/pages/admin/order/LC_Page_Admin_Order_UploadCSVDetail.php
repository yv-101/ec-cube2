<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2011 LOCKON CO.,LTD. All Rights Reserved.
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

// {{{ requires
require_once CLASS_EX_REALDIR . 'page_extends/admin/LC_Page_Admin_Ex.php';
require_once CLASS_EX_REALDIR . 'helper_extends/SC_Helper_CSV_Ex.php';

/**
 * 受注登録CSVのページクラス.
 *
 * @package Page
 * @author LOCKON CO.,LTD.
 *
 */
class LC_Page_Admin_Order_UploadCSVDetail extends LC_Page_Admin_Ex {

    // }}}
    // {{{ functions

    /** TAGエラーチェックフィールド情報 */
    var $arrTagCheckItem;

    /** 受注テーブルカラム情報 (登録処理用) **/
    var $arrOrderColumn;

    /** 登録フォームカラム情報 **/
    var $arrFormKeyList;

    var $arrRowErr;

    var $arrRowResult;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->tpl_mainpage = 'order/upload_csv_detail.tpl';
        $this->tpl_subnavi = 'order/subnavi.tpl';
        $this->tpl_mainno = 'order';
        $this->tpl_subno = 'upload_csv';
        $this->tpl_subtitle = '受注明細追加登録CSV';
        $this->csv_id = '10';

        $masterData = new SC_DB_MasterData_Ex();
        $this->arrDISP = $masterData->getMasterData("mtb_disp");
        $this->arrSTATUS = $masterData->getMasterData("mtb_status");
        $this->arrOrderSTATUS = $masterData->getMasterData("mtb_order_status");
        $this->arrDELIVERYDATE = $masterData->getMasterData("mtb_delivery_date");
        $this->arrPayments = SC_Helper_DB_Ex::sfGetIDValueList("dtb_payment", "payment_id", "payment_method");
        $this->arrInfo = SC_Helper_DB_Ex::sfGetBasisData();
        $this->arrAllowedTag = $masterData->getMasterData("mtb_allowed_tag");
        $this->arrTagCheckItem = array();
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $this->action();
        $this->sendResponse();
    }

    /**
     * Page のアクション.
     *
     * @return void
     */
    function action() {
        $this->objDb = new SC_Helper_DB_Ex();

        // CSV管理ヘルパー
        $objCSV = new SC_Helper_CSV_Ex();
        // CSV構造読み込み
        $arrCSVFrame = $objCSV->sfGetCsvOutput($this->csv_id);

        // CSV構造がインポート可能かのチェック
        if(!$objCSV->sfIsImportCSVFrame($arrCSVFrame) ) {
            // 無効なフォーマットなので初期状態に強制変更
            $arrCSVFrame = $objCSV->sfGetCsvOutput($this->csv_id, '', array(), 'no');
            $this->tpl_is_format_default = true;
        }
        // CSV構造は更新可能なフォーマットかのフラグ取得
        $this->tpl_is_update = $objCSV->sfIsUpdateCSVFrame($arrCSVFrame);

        // CSVファイルアップロード情報の初期化
        $objUpFile = new SC_UploadFile_Ex(IMAGE_TEMP_REALDIR, IMAGE_SAVE_REALDIR);
        $this->lfInitFile($objUpFile);

        // パラメータ情報の初期化
        $objFormParam = new SC_FormParam_Ex();
        $this->lfInitParam($objFormParam, $arrCSVFrame);

        $objFormParam->setHtmlDispNameArray();
        $this->arrTitle = $objFormParam->getHtmlDispNameArray();

        switch($this->getMode()) {
        case 'csv_upload':
            $this->doUploadCsv($objFormParam, $objUpFile);
            break;
        default:
            break;
        }
    }

    /**
     * 登録/編集結果のメッセージをプロパティへ追加する
     *
     * @param integer $line_count 行数
     * @param stirng $message メッセージ
     * @return void
     */
    function addRowResult($line_count, $message) {
        $this->arrRowResult[] = $line_count . "行目：" . $message;
    }

    /**
     * 登録/編集結果のエラーメッセージをプロパティへ追加する
     *
     * @param integer $line_count 行数
     * @param stirng $message メッセージ
     * @return void
     */
    function addRowErr($line_count, $message) {
        $this->arrRowErr[] = $line_count . "行目：" . $message;
    }

    /**
     * CSVアップロードを実行します.
     *
     * @return void
     */
    function doUploadCsv(&$objFormParam, &$objUpFile) {
        // ファイルアップロードのチェック
        $objUpFile->makeTempFile('csv_file');
        $arrErr = $objUpFile->checkExists();
        if (count($arrErr) > 0) {
            $this->arrErr = $arrErr;
            return;
        }
        // 一時ファイル名の取得
        $filepath = $objUpFile->getTempFilePath('csv_file');
        // CSVファイルの文字コード変換
        $enc_filepath = SC_Utils_Ex::sfEncodeFile($filepath, CHAR_CODE, CSV_TEMP_REALDIR);
        // CSVファイルのオープン
        $fp = fopen($enc_filepath, 'r');
        // 失敗した場合はエラー表示
        if (!$fp) {
             SC_Utils_Ex::sfDispError("");
        }

        // 登録先テーブル カラム情報の初期化
        $this->lfInitTableInfo();

        // 登録フォーム カラム情報
        $this->arrFormKeyList = $objFormParam->getKeyList();

        // 登録対象の列数
        $col_max_count = $objFormParam->getCount();
        // 行数
        $line_count = 0;

        $objQuery =& SC_Query_Ex::getSingletonInstance();
        $objQuery->begin();

        $errFlag = false;
        $all_line_checked = false;

        while (!feof($fp)) {
            $arrCSV = fgetcsv($fp, CSV_LINE_MAX);

            // 全行入力チェック後に、ファイルポインターを先頭に戻す
            if (feof($fp) && !$all_line_checked) {
                rewind($fp);
                $line_count = 0;
                $all_line_checked = true;
                continue;
            }

            // 行カウント
            $line_count++;
            // ヘッダ行はスキップ
            if ($line_count == 1) {
                continue;
            }
            // 空行はスキップ
            if (empty($arrCSV)) {
                continue;
            }
            // 列数が異なる場合はエラー
            $col_count = count($arrCSV);
            if ($col_max_count != $col_count) {
                $this->addRowErr($line_count, "※ 項目数が" . $col_count . "個検出されました。項目数は" . $col_max_count . "個になります。");
                $errFlag = true;
                break;
            }
            // シーケンス配列を格納する。
            $objFormParam->setParam($arrCSV, true);
            $arrRet = $objFormParam->getHashArray();
            $objFormParam->setParam($arrRet);
            // 入力値の変換
            $objFormParam->convParam();
            // <br>なしでエラー取得する。
            $arrCSVErr = $this->lfCheckError($objFormParam);

            // 入力エラーチェック
            if (count($arrCSVErr) > 0) {
                foreach ($arrCSVErr as $err) {
                    $this->addRowErr($line_count, $err);
                }
                $errFlag = true;
                break;
            }
            
            $product_class_id = $this->lfGetProductClassId($objQuery,$objFormParam);

            if(!is_numeric($product_class_id)){
                $this->addRowErr($line_count, '規格が存在しません。');
                $errFlag = true;
                break;
            }
            
            if ($all_line_checked) {
                $this->lfRegistOrder($objQuery, $line_count, $objFormParam, $product_class_id);
                $arrParam = $objFormParam->getHashArray();

                $this->addRowResult($line_count, "受注ID：".$arrParam['order_id'] ."/商品ID：". $arrParam['product_id']);
            }
        }

        // 実行結果画面を表示
        $this->tpl_mainpage = 'order/upload_csv_detail_complete.tpl';

        fclose($fp);

        if ($errFlag) {
            $objQuery->rollback();
            return;
        }

        $objQuery->commit();
        return;
    }

    /**
     * デストラクタ.
     *
     * @return void
     */
    function destroy() {
        parent::destroy();
    }

    /**
     * ファイル情報の初期化を行う.
     *
     * @return void
     */
    function lfInitFile(&$objUpFile) {
        $objUpFile->addFile("CSVファイル", 'csv_file', array('csv'), CSV_SIZE, true, 0, 0, false);
    }

    /**
     * 入力情報の初期化を行う.
     *
     * @param array CSV構造設定配列
     * @return void
     */
    function lfInitParam(&$objFormParam, &$arrCSVFrame) {
        // 固有の初期値調整
        $arrCSVFrame = $this->lfSetParamDefaultValue($arrCSVFrame);
        // CSV項目毎の処理
        foreach($arrCSVFrame as $item) {
            if($item['status'] == CSV_COLUMN_STATUS_FLG_DISABLE) continue;
            //サブクエリ構造の場合は AS名 を使用
            if(preg_match_all('/\(.+\)[ ]*as[ ]*(.+)$/i', $item['col'], $match, PREG_SET_ORDER)) {
                $col = $match[0][1];
            }else{
                $col = $item['col'];
            }
            // HTML_TAG_CHECKは別途実行なので除去し、別保存しておく
            if(strpos(strtoupper($item['error_check_types']), 'HTML_TAG_CHECK') !== FALSE) {
                $this->arrTagCheckItem[] = $item;
                $error_check_types = str_replace('HTML_TAG_CHECK', '', $item['error_check_types']);
            }else{
                $error_check_types = $item['error_check_types'];
            }
            $arrErrorCheckTypes = explode(',', $error_check_types);
            foreach($arrErrorCheckTypes as $key => $val) {
                if(trim($val) == "") {
                    unset($arrErrorCheckTypes[$key]);
                }else{
                    $arrErrorCheckTypes[$key] = trim($val);
                }
            }
            // パラメーター登録
            $objFormParam->addParam(
                    $item['disp_name']
                    , $col
                    , constant($item['size_const_type'])
                    , $item['mb_convert_kana_option']
                    , $arrErrorCheckTypes
                    , $item['default']
                    , ($item['rw_flg'] != CSV_COLUMN_RW_FLG_READ_ONLY) ? true : false
                    );
        }
    }

    /**
     * 入力チェックを行う.
     *
     * @return void
     */
    function lfCheckError(&$objFormParam) {
        // 入力データを渡す。
        $arrRet =  $objFormParam->getHashArray();
        $objErr = new SC_CheckError_Ex($arrRet);
        $objErr->arrErr = $objFormParam->checkError(false);
        // HTMLタグチェックの実行
        foreach($this->arrTagCheckItem as $item) {
            $objErr->doFunc(array( $item['disp_name'], $item['col'], $this->arrAllowedTag), array("HTML_TAG_CHECK"));
        }
        // このフォーム特有の複雑系のエラーチェックを行う
        if(count($objErr->arrErr) == 0) {
            $objErr->arrErr = $this->lfCheckErrorDetail($arrRet, $objErr->arrErr);
        }
        return $objErr->arrErr;
    }

    /**
     * 保存先テーブル情報の初期化を行う.
     *
     * @return void
     */
    function lfInitTableInfo() {
        $objQuery =& SC_Query_Ex::getSingletonInstance();
        $this->arrOrderColumn = $objQuery->listTableFields('dtb_order_detail');
    }
    
    /**
     * 規格名称等からproduct_class_idを出す.
     *
     *
     * @param SC_Query $objQuery SC_Queryインスタンス
     * @return product_class_id 規格ID
     */
    function lfGetProductClassId($objQuery,$objFormParam){
        // 登録データ対象取得
        $arrList = $objFormParam->getHashArray();
        //規格ある場合
        if($arrList['parent_class_name'] != "" && $arrList['parent_classcategory_name'] != ""){
        $parent_class_id = $objQuery->getOne("SELECT class_id FROM dtb_class WHERE name = ?", array($arrList['parent_class_name']));
        $classcategory_id1 = $objQuery->getOne("SELECT classcategory_id FROM dtb_classcategory WHERE class_id =? AND name = ?", array($parent_class_id,$arrList['parent_classcategory_name']));

        $class_combination_id = $objQuery->select("class_combination_id", "dtb_class_combination", "classcategory_id = ?", array($classcategory_id1));
        // where文作成
        $where = "";
        $arrval = array();
        foreach($class_combination_id as $val){
            if($where == ""){
                $where = "( parent_class_combination_id = ?";
            }else{
                $where .= " OR parent_class_combination_id = ?";
            }
                $arrval[] = $val['class_combination_id'];
        }
        $where .= " )";
        if($arrList['class_name'] != "" && $arrList['classcategory_name'] != ""){
            $class_id = $objQuery->getOne("SELECT class_id FROM dtb_class WHERE name = ?", array($arrList['class_name']));
            $classcategory_id2 = $objQuery->getOne("SELECT classcategory_id FROM dtb_classcategory WHERE class_id =? AND name = ?", array($class_id,$arrList['classcategory_name']));
            $where .= " AND classcategory_id = ?";
            $arrval[] = $classcategory_id2;
            
            $class_combination_id = $objQuery->getOne("SELECT class_combination_id FROM dtb_class_combination WHERE ".$where, $arrval);
            $product_class_id = $objQuery->getOne("SELECT product_class_id FROM dtb_products_class WHERE product_id = ? AND class_combination_id = ?", array($arrList['product_id'], $class_combination_id));
        }else{
            $where .= " AND product_id = ?";
            $arrval[] = $arrList['product_id'];
            $product_class_id = $objQuery->getOne("SELECT product_class_id FROM dtb_products_class WHERE ".$where, $arrval);
        }

        //規格無い場合
        }else{
            $product_class_id = $objQuery->getOne("SELECT product_class_id FROM dtb_products_class WHERE product_id = ? AND class_combination_id IS NULL", array($arrList['product_id']));
        }
        /*
        if($arrList['parent_classcategory_name'] != ""){
            $sqlval['classcategory_name1'] = $arrList['parent_classcategory_name'];
        }else{
            $sqlval['classcategory_name1'] = NULL;
        }
        
        if($arrList['classcategory_name'] != ""){
            $sqlval['classcategory_name2'] = $arrList['classcategory_name'];
        }else{
            $sqlval['classcategory_name2'] = NULL;
        }
        */
        return $product_class_id;
    }
    
    
    /**
     * 新規受注登録を行う.
     *
     *
     * @param SC_Query $objQuery SC_Queryインスタンス
     * @param string|integer $line 処理中の行数
     * @return void
     */
    function lfRegistOrder($objQuery, $line = "", &$objFormParam, $product_class_id) {
        // 登録データ対象取得
        $arrList = $objFormParam->getHashArray();

        // 商品テーブルのカラムに存在しているもののうち、Form投入設定されていないデータは上書きしない。
        $sqlval = SC_Utils_Ex::sfArrayIntersectKeys($arrList, $this->arrOrderColumn);
        
        $sqlval['product_class_id'] = $product_class_id;

        // 必須入力では無い項目だが、空文字では問題のある特殊なカラム値の初期値設定
        $sqlval = $this->lfSetOrderDefaultData($sqlval);
        $sqlval['order_detail_id'] = $objQuery->nextVal('dtb_order_detail_order_detail_id');
        // INSERTの実行
        $objQuery->insert("dtb_order_detail", $sqlval);
            
    }

    /**
     * 初期値の設定
     *
     * @param array $arrCSVFrame CSV構造配列
     * @return array $arrCSVFrame CSV構造配列
     */
    function lfSetParamDefaultValue(&$arrCSVFrame) {
        foreach($arrCSVFrame as $key => $val) {
            switch($val['col']) {
                default:
                    break;
            }
        }
        return $arrCSVFrame;
    }

    /**
     * 受注データ登録前に特殊な値の持ち方をする部分のデータ部分の初期値補正を行う
     *
     * @param array $sqlval 受注登録情報配列
     * @return $sqlval 登録情報配列
     */
    function lfSetOrderDefaultData(&$sqlval) {
        return $sqlval;
    }

    /**
     * このフォーム特有の複雑な入力チェックを行う.
     *
     * @param array 確認対象データ
     * @param array エラー配列
     * @return array エラー配列
     */
    function lfCheckErrorDetail($item, $arrErr) {
        // 受注IDの存在チェック
        if(!$this->lfIsDbRecord('dtb_order', 'order_id', $item)) {
            $arrErr['order_id'] = "※ 指定の受注IDは、登録されていません。";
        }
        // 商品IDの存在チェック
        if(!$this->lfIsDbRecord('dtb_products', 'product_id', $item)) {
            $arrErr['order_id'] = "※ 指定の受注IDは、登録されていません。";
        }
        $objQuery =& SC_Query_Ex::getSingletonInstance();
        return $arrErr;
    }

    // TODO: ここから下のルーチンは汎用ルーチンとして移動が望ましい

    /**
     * 指定された行番号をmicrotimeに付与してDB保存用の時間を生成する。
     * トランザクション内のnow()は全てcommit()時の時間に統一されてしまう為。
     *
     * @param string $line_no 行番号
     * @return string $time DB保存用の時間文字列
     */
    function lfGetDbFormatTimeWithLine($line_no = '') {
        $time = date("Y-m-d H:i:s");
        // 秒以下を生成
        if($line_no != '') {
            $microtime = sprintf("%06d", $line_no);
            $time .= ".$microtime";
        }
        return $time;
    }

    /**
     * 指定されたキーと複数値の有効性の配列内確認
     *
     * @param string $arr チェック対象配列
     * @param string $keyname フォームキー名
     * @param array  $item 入力データ配列
     * @param string $delimiter 分割文字
     * @return boolean true:有効なデータがある false:有効ではない
     */
    function lfIsArrayRecordMulti($arr, $keyname, $item, $delimiter = ',') {
        if(array_search($keyname, $this->arrFormKeyList) === FALSE) {
            return true;
        }
        if($item[$keyname] == "") {
            return true;
        }
        $arrItems = explode($delimiter, $item[$keyname]);
        //空項目のチェック 1つでも空指定があったら不正とする。
        if(array_search("", $arrItems) !== FALSE) {
            return false;
        }
        //キー項目への存在チェック
        foreach($arrItems as $item) {
            if(!array_key_exists($item, $arr)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 指定されたキーと複数値の有効性のDB確認
     *
     * @param string $table テーブル名
     * @param string $tblkey テーブルキー名
     * @param string $keyname フォームキー名
     * @param array  $item 入力データ配列
     * @param string $delimiter 分割文字
     * @return boolean true:有効なデータがある false:有効ではない
     */
    function lfIsDbRecordMulti($table, $tblkey, $keyname, $item, $delimiter = ',') {
        if(array_search($keyname, $this->arrFormKeyList) === FALSE) {
            return true;
        }
        if($item[$keyname] == "") {
            return true;
        }
        $arrItems = explode($delimiter, $item[$keyname]);
        //空項目のチェック 1つでも空指定があったら不正とする。
        if(array_search("", $arrItems) !== FALSE) {
            return false;
        }
        $count = count($arrItems);
        $where = $tblkey ." IN (" . implode(",", array_fill(0, $count, "?")) . ")";

        $objQuery =& SC_Query_Ex::getSingletonInstance();
        $db_count = $objQuery->count($table, $where, $arrItems);
        if($count != $db_count) {
            return false;
        }
        return true;
    }

    /**
     * 指定されたキーと値の有効性のDB確認
     *
     * @param string $table テーブル名
     * @param string $keyname キー名
     * @param array  $item 入力データ配列
     * @return boolean true:有効なデータがある false:有効ではない
     */
    function lfIsDbRecord($table, $keyname, $item) {
        if(array_search($keyname, $this->arrFormKeyList) !== FALSE  //入力対象である
                and $item[$keyname] != ""   // 空ではない
                and !$this->objDb->sfIsRecord($table, $keyname, (array)$item[$keyname]) //DBに存在するか
                ) {
            return false;
        }
        return true;
    }

    /**
     * 指定されたキーと値の有効性の配列内確認
     *
     * @param string $arr チェック対象配列
     * @param string $keyname キー名
     * @param array  $item 入力データ配列
     * @return boolean true:有効なデータがある false:有効ではない
     */
    function lfIsArrayRecord($arr, $keyname, $item) {
        if(array_search($keyname, $this->arrFormKeyList) !== FALSE //入力対象である
                and $item[$keyname] != "" // 空ではない
                and !array_key_exists($item[$keyname], $arr) //配列に存在するか
                ) {
            return false;
        }
        return true;
    }
}
