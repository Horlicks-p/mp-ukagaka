<style>
    /* 動漫風格：統一設定頁面樣式 */
    .mpu-settings-card {
        background: #E8F4F8;
        border: 1px solid #B8E6E6;
        border-radius: 10px;
        padding: 20px 24px;
        margin: 20px 0;
        box-shadow: 0 2px 8px rgba(168, 216, 234, 0.15);
    }

    .mpu-settings-card h4 {
        color: #4A9EBD;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #A8D8EA;
    }

    .mpu-settings-card .mpu-field-group {
        margin-bottom: 16px;
    }

    .mpu-settings-card .mpu-field-group:last-child {
        margin-bottom: 0;
    }

    .mpu-settings-card label {
        font-weight: 500;
    }

    .mpu-settings-card small {
        color: #5A7A8C;
        display: block;
        margin-top: 4px;
    }

    .mpu-settings-card label {
        color: #2C3E50;
    }

    /* 動漫風格：textarea 滾動條樣式 */
    .mpu-settings-card textarea::-webkit-scrollbar {
        width: 12px;
    }

    .mpu-settings-card textarea::-webkit-scrollbar-track {
        background: #E8F4F8;
        border-radius: 6px;
        border: 1px solid #B8E6E6;
    }

    .mpu-settings-card textarea::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #A8D8EA 0%, #B8E6E6 100%);
        border-radius: 6px;
        border: 2px solid #E8F4F8;
    }

    .mpu-settings-card textarea::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #4A9EBD 0%, #5FB3A1 100%);
    }

    /* Firefox 滾動條樣式 */
    .mpu-settings-card textarea {
        scrollbar-width: thin;
        scrollbar-color: #A8D8EA #E8F4F8;
    }
</style>

<div>
    <h3><?php _e('AI 設定 (Context Awareness)', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('此頁面用於設定「頁面感知 AI」功能的行為參數。AI 提供商選擇和模型設定請前往「LLM 設定」頁面。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="ai_setting" id="ai_setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=5'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <!-- 語言與角色設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('🌐 語言與角色設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="ai_language"><?php _e('語言設定：', 'mp-ukagaka'); ?></label>
                <select id="ai_language" name="ai_language" style="width: 100%; max-width: 300px;">
                    <option value="zh-TW" <?php if (!isset($mpu_opt['ai_language']) || $mpu_opt['ai_language'] == 'zh-TW') {
                                                echo ' selected="selected"';
                                            } ?>><?php _e('繁體中文', 'mp-ukagaka'); ?></option>
                    <option value="ja" <?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'ja') {
                                            echo ' selected="selected"';
                                        } ?>><?php _e('日本語', 'mp-ukagaka'); ?></option>
                    <option value="en" <?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'en') {
                                            echo ' selected="selected"';
                                        } ?>><?php _e('English', 'mp-ukagaka'); ?></option>
                </select>
            </div>
            <div class="mpu-field-group">
                <label for="ai_system_prompt"><?php _e('人格設定 (System Prompt)：', 'mp-ukagaka'); ?></label>
                <textarea cols="60" rows="10" id="ai_system_prompt" name="ai_system_prompt" class="resizable" style="line-height:130%; width: 100%; max-width: 850px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace;"><?php echo isset($mpu_opt['ai_system_prompt']) ? esc_textarea($mpu_opt['ai_system_prompt']) : '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。'; ?></textarea>
                <small>
                    <?php _e('提示：支援 Markdown 和 XML 標籤格式，可直接使用結構化格式增強模型理解。<br>可使用 {{變數名}} 進行變數替換（如：{{ukagaka_display_name}}、{{time_context}}、{{language}} 等）。', 'mp-ukagaka'); ?>
                </small>
            </div>
        </div>

        <!-- 觸發與機率設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('⚙️ 觸發與機率設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="ai_probability"><?php _e('頁面感知確率 (%)：', 'mp-ukagaka'); ?></label>
                <input type="number" id="ai_probability" name="ai_probability" value="<?php echo isset($mpu_opt['ai_probability']) ? intval($mpu_opt['ai_probability']) : 10; ?>" min="1" max="100" style="width: 80px;" />
                <small><?php _e('設定使用 AI 的機率（1-100%）。建議 10% 以控制成本。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group">
                <label for="ai_trigger_pages"><?php _e('觸發頁面：', 'mp-ukagaka'); ?></label>
                <input type="text" id="ai_trigger_pages" name="ai_trigger_pages" value="<?php echo isset($mpu_opt['ai_trigger_pages']) ? esc_attr($mpu_opt['ai_trigger_pages']) : 'is_single'; ?>" style="width: 100%; max-width: 400px;" />
                <small><?php _e('WordPress 條件標籤，逗號分隔（如：is_single,is_page）', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 顯示設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('🎨 顯示設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label for="ai_text_color"><?php _e('頁面感知時 AI 對話文字顏色：', 'mp-ukagaka'); ?></label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="color" id="ai_text_color" name="ai_text_color" value="<?php echo isset($mpu_opt['ai_text_color']) ? esc_attr($mpu_opt['ai_text_color']) : '#000000'; ?>" style="width: 100px; height: 30px; vertical-align: middle;" />
                    <span id="ai_text_color_display" style="font-family: monospace; font-size: 14px;"><?php echo isset($mpu_opt['ai_text_color']) ? esc_html($mpu_opt['ai_text_color']) : '#000000'; ?></span>
                </div>
                <small><?php _e('設定 AI 對話載入訊息（…ふむ。この記事か。どれ…）的文字顏色', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group">
                <label for="ai_display_duration"><?php _e('AI 對話顯示時間（秒）：', 'mp-ukagaka'); ?></label>
                <input type="number" id="ai_display_duration" name="ai_display_duration" value="<?php echo isset($mpu_opt['ai_display_duration']) ? intval($mpu_opt['ai_display_duration']) : 8; ?>" min="1" max="60" style="width: 80px;" />
                <small><?php _e('設定 AI 對話顯示的時間長度。在此期間，自動對話會暫停，避免 AI 對話被覆蓋（建議 5-10 秒）', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 首次訪客打招呼 -->
        <div class="mpu-settings-card">
            <h4><?php _e('👋 首次訪客打招呼', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label>
                    <input type="checkbox" id="ai_greet_first_visit" name="ai_greet_first_visit" value="1" <?php echo isset($mpu_opt['ai_greet_first_visit']) && $mpu_opt['ai_greet_first_visit'] ? 'checked="checked"' : ''; ?> />
                    <?php _e('啟用首次訪客打招呼', 'mp-ukagaka'); ?>
                </label>
                <small><?php _e('當訪客第一次訪問網站時，根據訪客來源（使用 Slimstat API）用 AI 生成個性化打招呼訊息。每個訪客只會看到一次。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group" id="ai_greet_prompt_container" style="<?php echo (isset($mpu_opt['ai_greet_first_visit']) && $mpu_opt['ai_greet_first_visit']) ? '' : 'display:none;'; ?>">
                <label for="ai_greet_prompt"><?php _e('首次訪客打招呼提示詞：', 'mp-ukagaka'); ?></label>
                <textarea cols="60" rows="8" id="ai_greet_prompt" name="ai_greet_prompt" class="resizable" style="line-height:130%; width: 100%; max-width: 850px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace;"><?php echo isset($mpu_opt['ai_greet_prompt']) ? esc_textarea($mpu_opt['ai_greet_prompt']) : '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。'; ?></textarea>
                <small>
                    <?php _e('提示：支援 Markdown 和 XML 標籤格式，可直接使用結構化格式增強模型理解。<br>可使用 {{變數名}} 進行變數替換（如：{{ukagaka_display_name}}、{{time_context}}、{{language}} 等）。', 'mp-ukagaka'); ?>
                </small>
            </div>
        </div>

        <script>
            (function($) {
                'use strict';

                /**
                 * MP Ukagaka AI 設定頁面 JavaScript
                 * 處理表單互動和 UI 更新
                 */

                // 檢查 jQuery 依賴
                if (typeof jQuery === 'undefined') {
                    console.error('MP Ukagaka: jQuery is not loaded!');
                    return;
                }

                var jQueryVersion = parseFloat($.fn.jquery);
                if (jQueryVersion < 1.7) {
                    console.error('MP Ukagaka: jQuery version is too old. .on() requires jQuery 1.7+. Current version:', $.fn.jquery);
                    return;
                }

                /**
                 * 初始化函數
                 */
                function initAISettings() {
                    // 首次訪客打招呼切換
                    $('#ai_greet_first_visit').on('change', function() {
                        var $container = $('#ai_greet_prompt_container');
                        if ($(this).is(':checked')) {
                            $container.slideDown();
                        } else {
                            $container.slideUp();
                        }
                    });

                    // AI 文字顏色即時預覽
                    $('#ai_text_color').on('change', function() {
                        $('#ai_text_color_display').text($(this).val());
                    });
                }

                // DOM 就緒後初始化
                $(document).ready(function() {
                    initAISettings();
                });
            })(jQuery);
        </script>

        <p><input name="submit_ai" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>