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

<div>
    <h3><?php _e('LLM 設定 (Ollama)', 'mp-ukagaka'); ?></h3>
    <form method="post" name="llm_setting" id="llm_setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=6'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <p>
            <label>
                <input type="checkbox" id="llm_enabled" name="llm_enabled" value="1" <?php echo (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] == 'ollama') ? 'checked="checked"' : ''; ?> />
                <?php _e('啟用 LLM (Ollama)', 'mp-ukagaka'); ?>
            </label>
            <br />
            <small><?php _e('啟用後將使用 Ollama LLM 生成對話，支援本地或遠程連接（如 Cloudflare Tunnel），無需 API Key，完全免費。', 'mp-ukagaka'); ?></small>
        </p>

        <div id="llm_settings" style="<?php
                                        // 如果 ai_provider 是 ollama，或者 llm_enabled 被勾選，則顯示
                                        $should_show = (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] == 'ollama');
                                        echo $should_show ? '' : 'display:none;';
                                        ?>">
            <p>
                <label for="ollama_endpoint"><?php _e('Ollama 端點：', 'mp-ukagaka'); ?></label><br />
                <input type="text" id="ollama_endpoint" name="ollama_endpoint"
                    value="<?php echo isset($mpu_opt['ollama_endpoint']) ? esc_attr($mpu_opt['ollama_endpoint']) : 'http://localhost:11434'; ?>"
                    style="width: 400px;" placeholder="http://localhost:11434 或 https://your-domain.com" />
                <br />
                <small>
                    <?php _e('本地連接：', 'mp-ukagaka'); ?> <code>http://localhost:11434</code> <?php _e('（默認）', 'mp-ukagaka'); ?><br />
                    <?php _e('遠程連接：', 'mp-ukagaka'); ?> <code>https://your-domain.com</code> <?php _e('（Cloudflare Tunnel、ngrok 等）', 'mp-ukagaka'); ?><br />
                    <?php _e('支援 HTTP 和 HTTPS 協議，插件會自動檢測連接類型並調整超時時間。', 'mp-ukagaka'); ?>
                </small>
            </p>

            <p>
                <label for="ollama_model"><?php _e('模型名稱：', 'mp-ukagaka'); ?></label><br />
                <input type="text" id="ollama_model" name="ollama_model"
                    value="<?php echo isset($mpu_opt['ollama_model']) ? esc_attr($mpu_opt['ollama_model']) : 'qwen3:8b'; ?>"
                    style="width: 300px;" placeholder="<?php _e('例如：qwen3:8b, llama3.2, mistral', 'mp-ukagaka'); ?>" />
                <br />
                <small><?php _e('您的 Ollama 模型名稱（使用', 'mp-ukagaka'); ?> <code>ollama list</code> <?php _e('查看已下載的模型）', 'mp-ukagaka'); ?></small>
            </p>

            <p>
                <label>
                    <input type="checkbox" id="ollama_replace_dialogue" name="ollama_replace_dialogue" value="1" <?php echo isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] ? 'checked="checked"' : ''; ?> />
                    <?php _e('使用 LLM 取代內建對話', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，所有春菜對話將由本機 LLM 實時生成，不再使用預設的靜態對話列表。頁面感知功能將自動關閉。', 'mp-ukagaka'); ?></small>
            </p>

            <p>
                <label>
                    <input type="checkbox" id="ollama_disable_thinking" name="ollama_disable_thinking" value="1" <?php echo isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking'] ? 'checked="checked"' : ''; ?> />
                    <?php _e('關閉思考模式（Qwen3 等模型）', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，將關閉 Qwen3 等支持思考模式的模型的思考行為，提高回應效率。建議啟用。', 'mp-ukagaka'); ?></small>
            </p>

            <p>
                <button type="button" id="test_ollama_connection" class="button"><?php _e('測試 Ollama 連接', 'mp-ukagaka'); ?></button>
                <span id="ollama_test_result"></span>
            </p>
        </div>

        <script>
            (function($) {
                'use strict';

                // 檢查 jQuery 是否正確載入
                if (typeof jQuery === 'undefined') {
                    console.error('jQuery is not loaded!');
                    return;
                }

                // 檢查 jQuery 版本（.on() 需要 jQuery 1.7+）
                var jQueryVersion = $.fn.jquery;
                console.log('jQuery version:', jQueryVersion);

                if (parseFloat(jQueryVersion) < 1.7) {
                    console.error('jQuery version is too old. .on() requires jQuery 1.7+. Current version:', jQueryVersion);
                    return;
                }

                console.log('MP Ukagaka LLM Settings JS Loaded');

                $(document).ready(function() {
                    console.log('jQuery Ready');
                    console.log('LLM Settings div exists:', $('#llm_settings').length);

                    // 初始化：檢查 LLM 是否啟用
                    var llmEnabled = $('#llm_enabled').is(':checked');
                    console.log('LLM Enabled:', llmEnabled);
                    if (llmEnabled) {
                        $('#llm_settings').show();
                        console.log('LLM Settings shown');
                    } else {
                        $('#llm_settings').hide();
                        console.log('LLM Settings hidden');
                    }

                    // LLM 啟用切換
                    $('#llm_enabled').on('change', function() {
                        if ($(this).is(':checked')) {
                            // 嘗試在 AI 設定頁面設置提供商為 ollama（如果存在）
                            var aiProviderRadio = $('input[name="ai_provider"][value="ollama"]');
                            if (aiProviderRadio.length > 0) {
                                aiProviderRadio.prop('checked', true);
                            }
                            $('#llm_settings').show();
                        } else {
                            $('#llm_settings').hide();
                        }
                    });

                    // Ollama 連接測試
                    $('#test_ollama_connection').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        console.log('測試 Ollama 連接按鈕被點擊');

                        var endpoint = $('#ollama_endpoint').val();
                        var model = $('#ollama_model').val();

                        console.log('端點:', endpoint);
                        console.log('模型:', model);

                        $('#ollama_test_result').html('<span style="color: #666;"><?php _e('測試中...', 'mp-ukagaka'); ?></span>');

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            method: 'POST',
                            data: {
                                action: 'mpu_test_ollama_connection',
                                endpoint: endpoint,
                                model: model,
                                nonce: '<?php echo wp_create_nonce("mpu_test_ollama"); ?>'
                            },
                            success: function(response) {
                                console.log('AJAX Response:', response);
                                if (response.success) {
                                    $('#ollama_test_result').html('<span style="color: green;">✓ ' + response.data + '</span>');
                                } else {
                                    $('#ollama_test_result').html('<span style="color: red;">✗ ' + response.data + '</span>');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', status, error);
                                console.error('XHR:', xhr);
                                $('#ollama_test_result').html('<span style="color: red;">✗ <?php _e('測試失敗，請檢查網絡連接', 'mp-ukagaka'); ?> (' + error + ')</span>');
                            }
                        });

                        return false;
                    });
                });
            })(jQuery);
        </script>

        <p><input name="submit_llm" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>