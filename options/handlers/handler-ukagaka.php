<?php
/**
 * Handler: 偽春菜管理（刪除、修改、新建）
 * 
 * @param array $mpu_opt 當前設定
 * @return string 處理結果訊息
 */
function mpu_handle_ukagaka(&$mpu_opt) {
    $text = '';

    // 處理刪除偽春菜的請求 (del)
    if (isset($_GET['del']) && $_GET['del'] != '') {
        $del = $_GET['del'];
        if ($del == str_replace('default', '', $del)) { // 檢查是否為預設偽春菜
            if (isset($mpu_opt['ukagakas'][$del])) {
                $name = $mpu_opt['ukagakas'][$del]['name'];
                unset($mpu_opt['ukagakas'][$del]); // 刪除指定的偽春菜
                update_option('mp_ukagaka', $mpu_opt); // 更新選項
                $message = (($name == '') ? __('偽春菜', 'mp-ukagaka') : $name) . __('已離你而去…', 'mp-ukagaka');
                return '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
            } else {
                return '<div class="error"><p><strong>' . __('不存在此偽春菜喲', 'mp-ukagaka') . '</strong></p></div>';
            }
        } else {
            return '<div class="error"><p><strong>' . __('不允許趕走預設偽春菜喲', 'mp-ukagaka') . '</strong></p></div>';
        }
    }

    // 處理偽春菜的更改 (submit2)
    if (isset($_POST['submit2'])) {
        $ukagakas = $_POST['ukagakas'];
        foreach ($ukagakas as $key => $value) {
            $ukagakas[$key]['msg'] = mpu_str2array($ukagakas[$key]['msg']);
            $ukagakas[$key]['name'] = mpu_input_filter($ukagakas[$key]['name']);
            $ukagakas[$key]['shell'] = mpu_input_filter($ukagakas[$key]['shell']);
            $ukagakas[$key]['show'] = isset($ukagakas[$key]['show']) && $ukagakas[$key]['show'] ? true : false;

            // 檢查是否需要生成對話檔案
            if (isset($_POST['generate_dialog_file'][$key]) && $_POST['generate_dialog_file'][$key] == 'true') {
                // 獲取對話檔案名稱
                $dialog_filename = isset($ukagakas[$key]['dialog_filename']) ? sanitize_file_name($ukagakas[$key]['dialog_filename']) : sanitize_file_name($key);

                // 獲取檔案格式
                $ext = isset($mpu_opt['external_file_format']) ? $mpu_opt['external_file_format'] : 'txt';

                // 【安全性強化】使用安全文件生成函數
                mpu_generate_dialog_file($dialog_filename, $ukagakas[$key]['msg'], $ext);
            }
        }
        $mpu_opt['ukagakas'] = $ukagakas;
        update_option('mp_ukagaka', $mpu_opt);
        $message = __('偽春菜們已經煥然一新啦', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file'])) {
            $message .= __('，對話檔案已生成', 'mp-ukagaka');
        }
        return '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    }

    // 處理新偽春菜的創建 (submit3)
    if (isset($_POST['submit3'])) {
        $ukagaka = $_POST['ukagaka'];
        $ukagaka['msg'] = mpu_str2array($ukagaka['msg']);
        $ukagaka['name'] = mpu_input_filter($ukagaka['name']);
        $ukagaka['shell'] = mpu_input_filter($ukagaka['shell']);
        $ukagaka['show'] = isset($ukagaka['show']) && $ukagaka['show'] ? true : false;

        // 處理對話檔案
        if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true' && !empty($ukagaka['dialog_filename'])) {
            // 獲取檔案格式
            $ext = isset($mpu_opt['external_file_format']) ? $mpu_opt['external_file_format'] : 'txt';

            // 【安全性強化】使用安全文件生成函數
            $dialog_filename = sanitize_file_name($ukagaka['dialog_filename']);
            mpu_generate_dialog_file($dialog_filename, $ukagaka['msg'], $ext);
        }

        $mpu_opt['ukagakas'][] = $ukagaka;

        // 處理鍵名為 0 的情況
        if (isset($mpu_opt['ukagakas'][0]) && is_array($mpu_opt['ukagakas'][0])) {
            $mpu_opt['ukagakas'][] = $mpu_opt['ukagakas'][0];
            unset($mpu_opt['ukagakas'][0]);
        }
        update_option('mp_ukagaka', $mpu_opt);
        $message = __('偽春菜創建成功～', 'mp-ukagaka');
        if (isset($_POST['generate_dialog_file_new']) && $_POST['generate_dialog_file_new'] == 'true') {
            $message .= __('，對話檔案已生成', 'mp-ukagaka');
        }
        return '<div class="updated"><p><strong>' . $message . '</strong></p></div>';
    }

    return $text;
}
