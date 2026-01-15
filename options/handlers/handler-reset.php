<?php
/**
 * Handler: 重置設定
 * 
 * @param array $mpu_opt 當前設定
 * @return string 處理結果訊息
 */
function mpu_handle_reset(&$mpu_opt) {
    $text = '';

    // 處理重置設定的提交 (submit_reset)
    if (isset($_POST['submit_reset'])) {
        if ($_POST['reset_mpu']) {
            unset($mpu_opt);
            update_option('mp_ukagaka', $mpu_opt);
            mpu_default_opt(); // 重置為預設選項
            return '<div class="updated"><p><strong>' . __('設定已重置', 'mp-ukagaka') . '</strong></p></div>';
        } else {
            return '<div class="error"><p><strong>' . __('設定未被重置', 'mp-ukagaka') . '</strong></p></div>';
        }
    }

    return $text;
}
