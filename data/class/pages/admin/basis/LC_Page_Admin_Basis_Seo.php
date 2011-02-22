<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2010 LOCKON CO.,LTD. All Rights Reserved.
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
require_once(CLASS_REALDIR . "pages/admin/LC_Page_Admin.php");

/**
 * SEO管理 のページクラス.
 *
 * @package Page
 * @author LOCKON CO.,LTD.
 * @version $Id$
 */
class LC_Page_Admin_Basis_Seo extends LC_Page_Admin {

    // {{{ properties

    /** エラー情報 */
    var $arrErr;

    // }}}
    // {{{ functions

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->tpl_mainpage = 'basis/seo.tpl';
        $this->tpl_subnavi = 'basis/subnavi.tpl';
        $this->tpl_subno = 'seo';
        $this->tpl_mainno = 'basis';
        $this->tpl_subtitle = 'SEO管理';
        $masterData = new SC_DB_MasterData_Ex();
        $this->arrPref = $masterData->getMasterData('mtb_pref');
        $this->arrTAXRULE = $masterData->getMasterData("mtb_taxrule");
        $this->arrDeviceTypeName[DEVICE_TYPE_PC] = 'PCサイト';
        $this->arrDeviceTypeName[DEVICE_TYPE_MOBILE] = 'モバイルサイト';
        $this->arrDeviceTypeName[DEVICE_TYPE_SMARTPHONE] = 'スマートフォン';
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
        // データの取得
        $this->arrPageData = $this->lfGetSeoPageData();

        $device_type_id = (isset($_POST['device_type_id'])) ? $_POST['device_type_id'] : '';
        $page_id = (isset($_POST['page_id'])) ? $_POST['page_id'] : '';

        switch ($this->getMode()) {
        case 'confirm':
            // エラーチェック
            $this->arrErr[$device_type_id][$page_id] = $this->lfErrorCheck($_POST['meta'][$device_type_id][$page_id]);

            // エラーがなければデータを更新
            if(count($this->arrErr[$device_type_id][$page_id]) == 0) {

                // 更新データの変換
                $arrMETA = $this->lfConvertParam($_POST['meta'][$device_type_id][$page_id]);

                // 更新データ配列生成
                $arrUpdData = array($arrMETA['author'], $arrMETA['description'], $arrMETA['keyword'], $device_type_id, $page_id);
                // データ更新
                $this->lfUpdPageData($arrUpdData);
            }else{
                // POSTのデータを再表示
                $arrPageData = $this->lfSetData($this->arrPageData, $_POST['meta']);
                $this->arrPageData = $arrPageData;
            }
            break;
        default:
            break;
        }

        // エラーがなければデータの取得
        if(count($this->arrErr[$device_type_id][$page_id]) == 0) {
            // データの取得
            $this->arrPageData = $this->lfGetSeoPageData();
        }
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
     * ページレイアウトテーブルにデータ更新を行う.
     *
     * @param array $arrUpdData 更新データ
     * @return integer 更新結果
     */
    function lfUpdPageData($arrUpdData = array()){
        $objQuery =& SC_Query::getSingletonInstance();
        $sql = "";

        // SQL生成
        $sql .= " UPDATE ";
        $sql .= "     dtb_pagelayout ";
        $sql .= " SET ";
        $sql .= "     author = ? , ";
        $sql .= "     description = ? , ";
        $sql .= "     keyword = ? ";
        $sql .= " WHERE ";
        $sql .= "     device_type_id = ? ";
        $sql .= "     AND page_id = ? ";
        $sql .= " ";

        // SQL実行
        $ret = $objQuery->query($sql, $arrUpdData);

        return $ret;
    }

    /**
     * 入力項目のエラーチェックを行う.
     *
     * @param array $array エラーチェック対象データ
     * @return array エラー内容
     */
    function lfErrorCheck($array) {
        $objErr = new SC_CheckError($array);

        $objErr->doFunc(array("メタタグ:Author", "author", STEXT_LEN), array("MAX_LENGTH_CHECK"));
        $objErr->doFunc(array("メタタグ:Description", "description", STEXT_LEN), array("MAX_LENGTH_CHECK"));
        $objErr->doFunc(array("メタタグ:Keywords", "keyword", STEXT_LEN), array("MAX_LENGTH_CHECK"));

        return $objErr->arrErr;
    }

    /**
     * テンプレート表示データに値をセットする.
     *
     * @param array 表示元データ
     * @param array 表示データ
     * @return array 表示データ
     */
    function lfSetData($arrPageData, $arrDispData){

        foreach($arrPageData as $device_key => $arrVal){
            foreach($arrVal as $key => $val) {
                $device_type_id = $val['device_type_id'];
                $page_id = $val['page_id'];
                $arrPageData[$device_key][$key]['author'] = $arrDispData[$device_type_id][$page_id]['author'];
                $arrPageData[$device_key][$key]['description'] = $arrDispData[$device_type_id][$page_id]['description'];
                $arrPageData[$device_key][$key]['keyword'] = $arrDispData[$device_type_id][$page_id]['keyword'];
            }
        }

        return $arrPageData;
    }

    /* 取得文字列の変換 */
    function lfConvertParam($array) {
        /*
         *	文字列の変換
         *	K :  「半角(ﾊﾝｶｸ)片仮名」を「全角片仮名」に変換
         *	C :  「全角ひら仮名」を「全角かた仮名」に変換
         *	V :  濁点付きの文字を一文字に変換。"K","H"と共に使用します
         *	n :  「全角」数字を「半角(ﾊﾝｶｸ)」に変換
         *  a :  全角英数字を半角英数字に変換する
         */
        // 人物基本情報

        // スポット商品
        $arrConvList['author'] = "KVa";
        $arrConvList['description'] = "KVa";
        $arrConvList['keyword'] = "KVa";

        // 文字変換
        foreach ($arrConvList as $key => $val) {
            // POSTされてきた値のみ変換する。
            if(isset($array[$key])) {
                $array[$key] = mb_convert_kana($array[$key] ,$val);
            }
        }
        return $array;
    }

    /**
     * SEO管理で設定するページのデータを取得する
     *
     * @param void
     * @return array $arrRet ページデータ($arrRet[デバイスタイプID])
     */
    function lfGetSeoPageData() {
        $objLayout = new SC_Helper_PageLayout_Ex();
        $arrRet = array();

        $arrRet[DEVICE_TYPE_PC] = $objLayout->lfgetPageData('edit_flg = ? AND device_type_id = ?', array('2', DEVICE_TYPE_PC));
        $arrRet[DEVICE_TYPE_MOBILE] = $objLayout->lfgetPageData('edit_flg = ? AND device_type_id = ?', array('2', DEVICE_TYPE_MOBILE));
        $arrRet[DEVICE_TYPE_SMARTPHONE] = $objLayout->lfgetPageData('edit_flg = ? AND device_type_id = ?', array('2', DEVICE_TYPE_SMARTPHONE));

        return $arrRet;
    }
}
?>
