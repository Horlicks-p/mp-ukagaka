<?php

/**
 * LLM 設定頁面（本機 LLM - Ollama）
 * 
 * @package MP_Ukagaka
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit();
}
?>

<style>
    /* LLM 設定頁面專用樣式 */
    .mpu-llm-card {
        background: #f5f5f5;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px 24px;
        margin: 20px 0;
    }

    .mpu-llm-card h4 {
        color: var(--claude-text-heading, #1d8ac3);
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }

    /* 卡片內的 code 樣式 - 提高對比度 */
    .mpu-llm-card code {
        background: #e8e8e8;
        color: #333;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: "Courier New", Consolas, monospace;
        font-size: 12px;
        border: 1px solid #d0d0d0;
    }

    .mpu-beta-badge {
        display: inline-block;
        background: linear-gradient(135deg, #ff9800, #f57c00);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 4px;
        margin-left: 10px;
        vertical-align: middle;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .mpu-llm-card .mpu-field-group {
        margin-bottom: 16px;
    }

    .mpu-llm-card .mpu-field-group:last-child {
        margin-bottom: 0;
    }

    .mpu-test-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 12px;
    }

    .mpu-test-row .button {
        flex-shrink: 0;
    }

    #ollama_test_result {
        font-size: 13px;
    }

    /* Loading 動畫 */
    .mpu-loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #ccc;
        border-top-color: var(--claude-text-heading, #1d8ac3);
        border-radius: 50%;
        animation: mpu-spin 0.8s linear infinite;
        vertical-align: middle;
        margin-right: 6px;
    }

    @keyframes mpu-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div>
    <h3>
        <?php _e('LLM 設定 (Ollama)', 'mp-ukagaka'); ?>
        <span class="mpu-beta-badge">BETA</span>
    </h3>
    <form method="post" name="llm_setting" id="llm_setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=6'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <p>
            <label>
                <input type="checkbox" id="llm_enabled" name="llm_enabled" value="1" <?php echo (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] == 'ollama') ? 'checked="checked"' : ''; ?> />
                <?php _e('啟用 LLM (Ollama)', 'mp-ukagaka'); ?>
            </label>
            <br />
            <small><?php _e('啟用後將使用 Ollama LLM 生成對話，支援本地或遠程連接，無需 API Key，完全免費。', 'mp-ukagaka'); ?></small>
        </p>

        <div id="llm_settings" style="<?php
                                        $should_show = (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] == 'ollama');
                                        echo $should_show ? '' : 'display:none;';
                                        ?>">

            <!-- 連接設定卡片 -->
            <div class="mpu-llm-card">
                <h4><?php _e('🔌 連接設定', 'mp-ukagaka'); ?></h4>

                <div class="mpu-field-group">
                    <label for="ollama_endpoint"><?php _e('Ollama 端點：', 'mp-ukagaka'); ?></label>
                    <input type="text" id="ollama_endpoint" name="ollama_endpoint"
                        value="<?php echo isset($mpu_opt['ollama_endpoint']) ? esc_attr($mpu_opt['ollama_endpoint']) : 'http://localhost:11434'; ?>"
                        style="width: 100%; max-width: 400px;" placeholder="http://localhost:11434" />
                    <br />
                    <small>
                        <?php _e('本地：', 'mp-ukagaka'); ?> <code>http://localhost:11434</code>
                        <?php _e('｜ 遠程：', 'mp-ukagaka'); ?> <code>https://your-domain.com</code>
                    </small>
                </div>

                <div class="mpu-field-group">
                    <label for="ollama_model"><?php _e('模型名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" id="ollama_model" name="ollama_model"
                        value="<?php echo isset($mpu_opt['ollama_model']) ? esc_attr($mpu_opt['ollama_model']) : 'qwen3:8b'; ?>"
                        style="width: 100%; max-width: 300px;" placeholder="<?php _e('例如：gemma3:12b, qwen3:8b', 'mp-ukagaka'); ?>" />
                    <br />
                    <small><?php _e('使用', 'mp-ukagaka'); ?> <code>ollama list</code> <?php _e('查看已下載的模型', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-test-row">
                    <button type="button" id="test_ollama_connection" class="button"><?php _e('測試連接', 'mp-ukagaka'); ?></button>
                    <span id="ollama_test_result"></span>
                </div>
            </div>

            <!-- 對話設定卡片 -->
            <div class="mpu-llm-card">
                <h4><?php _e('💬 對話設定', 'mp-ukagaka'); ?></h4>

                <div class="mpu-field-group">
                    <label>
                        <input type="checkbox" id="ollama_replace_dialogue" name="ollama_replace_dialogue" value="1" <?php echo isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] ? 'checked="checked"' : ''; ?> />
                        <?php _e('使用 LLM 取代內建對話', 'mp-ukagaka'); ?>
                    </label>
                    <br />
                    <small><?php _e('啟用後，春菜對話將由 LLM 實時生成，不使用靜態對話列表。', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label>
                        <input type="checkbox" id="ollama_disable_thinking" name="ollama_disable_thinking" value="1" <?php echo isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking'] ? 'checked="checked"' : ''; ?> />
                        <?php _e('關閉思考模式（Qwen3、DeepSeek 等模型）', 'mp-ukagaka'); ?>
                    </label>
                    <br />
                    <small><?php _e('部分模型會輸出「思考過程」而非實際對話。啟用此選項可避免此問題。建議啟用。', 'mp-ukagaka'); ?></small>
                </div>

            </div>

        </div>

        <p><input name="submit_llm" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>

<script>
    (function($) {
        'use strict';

        if (typeof jQuery === 'undefined') {
            console.error('jQuery is not loaded!');
            return;
        }

        $(document).ready(function() {
            // 初始化：檢查 LLM 是否啟用
            var llmEnabled = $('#llm_enabled').is(':checked');
            if (llmEnabled) {
                $('#llm_settings').show();
            } else {
                $('#llm_settings').hide();
            }

            // LLM 啟用切換
            $('#llm_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    var aiProviderRadio = $('input[name="ai_provider"][value="ollama"]');
                    if (aiProviderRadio.length > 0) {
                        aiProviderRadio.prop('checked', true);
                    }
                    $('#llm_settings').slideDown(200);
                } else {
                    $('#llm_settings').slideUp(200);
                }
            });

            // Ollama 連接測試
            $('#test_ollama_connection').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $btn = $(this);
                var endpoint = $('#ollama_endpoint').val();
                var model = $('#ollama_model').val();

                // 顯示 loading 動畫
                $btn.prop('disabled', true);
                $('#ollama_test_result').html('<span class="mpu-loading"></span><?php _e("測試中...", "mp-ukagaka"); ?>');

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'mpu_test_ollama_connection',
                        endpoint: endpoint,
                        model: model,
                        nonce: '<?php echo wp_create_nonce("mpu_test_ollama"); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $('#ollama_test_result').html('<span style="color: green;">✓ ' + response.data + '</span>');
                        } else {
                            $('#ollama_test_result').html('<span style="color: red;">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $('#ollama_test_result').html('<span style="color: red;">✗ <?php _e("測試失敗，請檢查網絡連接", "mp-ukagaka"); ?> (' + error + ')</span>');
                    }
                });

                return false;
            });
        });
    })(jQuery);
</script>