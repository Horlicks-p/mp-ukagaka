<?php
/**
 * Handler: 擴展設定 + 會話設定
 * 
 * @param array $mpu_opt 當前設定
 * @return string 處理結果訊息
 */
function mpu_handle_general(&$mpu_opt) {
    $text = '';

    // 處理擴展設定的提交 (submit4)
    if (isset($_POST['submit4'])) {
        $extend = $_POST['extend'];
        $extend['js_area'] = mpu_input_filter($extend['js_area']);
        $mpu_opt['extend'] = $extend;
        update_option('mp_ukagaka', $mpu_opt);
        return '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }

    // 處理會話設定的提交 (submit5)
    if (isset($_POST['submit5'])) {
        $auto_msg = $_POST['auto_msg'];
        $common_msg = $_POST['common_msg'];
        $mpu_opt['auto_msg'] = mpu_input_filter($auto_msg);
        $mpu_opt['common_msg'] = mpu_input_filter($common_msg);
        update_option('mp_ukagaka', $mpu_opt);
        return '<div class="updated"><p><strong>' . __('設定已儲存', 'mp-ukagaka') . '</strong></p></div>';
    }

    return $text;
}
