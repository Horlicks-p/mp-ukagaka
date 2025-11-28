<div>
    <h3><?php _e('AI 設定 (Context Awareness)', 'mp-ukagaka'); ?></h3>
    <form method="post" name="ai_setting" id="ai_setting" action="<?php echo site_url(); ?>/wp-admin/options-general.php?page=<?php echo plugin_basename('mp-ukagaka/options.php'); ?>&cur_page=5">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <p>
            <label for="ai_enabled">
                <input id="ai_enabled" name="ai_enabled" type="checkbox" value="true"<?php if (isset($mpu_opt['ai_enabled']) && $mpu_opt['ai_enabled']) { echo ' checked="checked"'; } ?> />
                <?php _e('啟用頁面感知功能（需要 AI API Key）', 'mp-ukagaka'); ?>
            </label>
        </p>

        <p>
            <label><?php _e('AI 提供商：', 'mp-ukagaka'); ?></label><br/>
            <label><input name="ai_provider" type="radio" value="gemini" <?php if (!isset($mpu_opt['ai_provider']) || $mpu_opt['ai_provider'] == 'gemini') { echo ' checked="checked"'; } ?> /><?php _e('Google Gemini', 'mp-ukagaka'); ?></label>
            <label><input name="ai_provider" type="radio" value="openai" <?php if (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] == 'openai') { echo ' checked="checked"'; } ?> /><?php _e('OpenAI', 'mp-ukagaka'); ?></label>
            <label><input name="ai_provider" type="radio" value="claude" <?php if (isset($mpu_opt['ai_provider']) && $mpu_opt['ai_provider'] == 'claude') { echo ' checked="checked"'; } ?> /><?php _e('Claude (Anthropic)', 'mp-ukagaka'); ?></label>
        </p>

        <?php
        // 【安全性強化】解密 API Key 用於表單顯示
        $gemini_key_decrypted = isset($mpu_opt['ai_api_key']) ? mpu_decrypt_api_key($mpu_opt['ai_api_key']) : '';
        $openai_key_decrypted = isset($mpu_opt['openai_api_key']) ? mpu_decrypt_api_key($mpu_opt['openai_api_key']) : '';
        $claude_key_decrypted = isset($mpu_opt['claude_api_key']) ? mpu_decrypt_api_key($mpu_opt['claude_api_key']) : '';
        ?>
        <p>
            <label for="ai_api_key"><?php _e('Gemini API Key：', 'mp-ukagaka'); ?></label><br/>
            <input type="password" id="ai_api_key" name="ai_api_key" value="<?php echo esc_attr($gemini_key_decrypted); ?>" placeholder="<?php _e('請輸入 Google Gemini API Key', 'mp-ukagaka'); ?>" style="width: 400px;" autocomplete="off" /><br/>
            <small><?php _e('請前往 <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if (!empty($gemini_key_decrypted)) { echo '<span style="color: green;">✓ ' . __('已設定', 'mp-ukagaka') . '</span>'; } ?></small>
        </p>

        <p>
            <label for="openai_api_key"><?php _e('OpenAI API Key：', 'mp-ukagaka'); ?></label><br/>
            <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr($openai_key_decrypted); ?>" placeholder="<?php _e('請輸入 OpenAI API Key', 'mp-ukagaka'); ?>" style="width: 400px;" autocomplete="off" /><br/>
            <small><?php _e('請前往 <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if (!empty($openai_key_decrypted)) { echo '<span style="color: green;">✓ ' . __('已設定', 'mp-ukagaka') . '</span>'; } ?></small>
        </p>

        <p>
            <label for="openai_model"><?php _e('OpenAI 模型：', 'mp-ukagaka'); ?></label>
            <select id="openai_model" name="openai_model">
                <option value="gpt-4o-mini"<?php if (!isset($mpu_opt['openai_model']) || $mpu_opt['openai_model'] == 'gpt-4o-mini') { echo ' selected="selected"'; } ?>>gpt-4o-mini (推薦，速度快成本低)</option>
                <option value="gpt-4o"<?php if (isset($mpu_opt['openai_model']) && $mpu_opt['openai_model'] == 'gpt-4o') { echo ' selected="selected"'; } ?>>gpt-4o (更聰明)</option>
                <option value="gpt-3.5-turbo"<?php if (isset($mpu_opt['openai_model']) && $mpu_opt['openai_model'] == 'gpt-3.5-turbo') { echo ' selected="selected"'; } ?>>gpt-3.5-turbo (經濟實惠)</option>
            </select>
        </p>

        <p>
            <label for="claude_api_key"><?php _e('Claude API Key：', 'mp-ukagaka'); ?></label><br/>
            <input type="password" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr($claude_key_decrypted); ?>" placeholder="<?php _e('請輸入 Claude API Key', 'mp-ukagaka'); ?>" style="width: 400px;" autocomplete="off" /><br/>
            <small><?php _e('請前往 <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if (!empty($claude_key_decrypted)) { echo '<span style="color: green;">✓ ' . __('已設定', 'mp-ukagaka') . '</span>'; } ?></small>
        </p>

        <p>
            <label for="claude_model"><?php _e('Claude 模型：', 'mp-ukagaka'); ?></label>
            <select id="claude_model" name="claude_model">
                <option value="claude-sonnet-4-5-20250929"<?php if (!isset($mpu_opt['claude_model']) || $mpu_opt['claude_model'] == 'claude-sonnet-4-5-20250929') { echo ' selected="selected"'; } ?>>claude-sonnet-4-5-20250929</option>
                <option value="claude-opus-4-5-20251101"<?php if (isset($mpu_opt['claude_model']) && $mpu_opt['claude_model'] == 'claude-opus-4-5-20251101') { echo ' selected="selected"'; } ?>>claude-opus-4-5-20251101</option>
            </select>
        </p>

        <p>
            <label for="ai_language"><?php _e('語言設定：', 'mp-ukagaka'); ?></label>
            <select id="ai_language" name="ai_language">
                <option value="zh-TW"<?php if (!isset($mpu_opt['ai_language']) || $mpu_opt['ai_language'] == 'zh-TW') { echo ' selected="selected"'; } ?>><?php _e('繁體中文', 'mp-ukagaka'); ?></option>
                <option value="ja"<?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'ja') { echo ' selected="selected"'; } ?>><?php _e('日本語', 'mp-ukagaka'); ?></option>
                <option value="en"<?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'en') { echo ' selected="selected"'; } ?>><?php _e('English', 'mp-ukagaka'); ?></option>
            </select>
        </p>

        <p>
            <label for="ai_system_prompt"><?php _e('人格設定 (System Prompt)：', 'mp-ukagaka'); ?></label><br/>
            <textarea cols="60" rows="4" id="ai_system_prompt" name="ai_system_prompt" class="resizable" style="line-height:130%;"><?php echo isset($mpu_opt['ai_system_prompt']) ? esc_textarea($mpu_opt['ai_system_prompt']) : '你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。'; ?></textarea>
        </p>

        <p>
            <label for="ai_probability"><?php _e('AI 回應機率 (%)：', 'mp-ukagaka'); ?></label>
            <input type="number" id="ai_probability" name="ai_probability" value="<?php echo isset($mpu_opt['ai_probability']) ? intval($mpu_opt['ai_probability']) : 10; ?>" min="1" max="100" style="width: 80px;" />
            <br/>
            <small><?php _e('設定使用 AI 的機率（1-100%）。建議 10% 以控制成本。', 'mp-ukagaka'); ?></small>
        </p>

        <p>
            <label for="ai_trigger_pages"><?php _e('觸發頁面：', 'mp-ukagaka'); ?></label><br/>
            <input type="text" id="ai_trigger_pages" name="ai_trigger_pages" value="<?php echo isset($mpu_opt['ai_trigger_pages']) ? esc_attr($mpu_opt['ai_trigger_pages']) : 'is_single'; ?>" style="width: 400px;" /><br/>
            <small><?php _e('WordPress 條件標籤，逗號分隔（如：is_single,is_page）', 'mp-ukagaka'); ?></small>
        </p>

        <p>
            <label for="ai_text_color"><?php _e('AI 對話文字顏色：', 'mp-ukagaka'); ?></label><br/>
            <input type="color" id="ai_text_color" name="ai_text_color" value="<?php echo isset($mpu_opt['ai_text_color']) ? esc_attr($mpu_opt['ai_text_color']) : '#000000'; ?>" style="width: 100px; height: 30px; vertical-align: middle;" />
            <span id="ai_text_color_display" style="margin-left: 10px; vertical-align: middle; font-family: monospace;"><?php echo isset($mpu_opt['ai_text_color']) ? esc_html($mpu_opt['ai_text_color']) : '#000000'; ?></span><br/>
            <small><?php _e('設定 AI 對話載入訊息（…ふむ。この記事か。どれ…）的文字顏色', 'mp-ukagaka'); ?></small>
        </p>

        <p>
            <label for="ai_display_duration"><?php _e('AI 對話顯示時間（秒）：', 'mp-ukagaka'); ?></label>
            <input type="number" id="ai_display_duration" name="ai_display_duration" value="<?php echo isset($mpu_opt['ai_display_duration']) ? intval($mpu_opt['ai_display_duration']) : 8; ?>" min="1" max="60" style="width: 80px;" />
            <br/>
            <small><?php _e('設定 AI 對話顯示的時間長度。在此期間，自動對話會暫停，避免 AI 對話被覆蓋（建議 5-10 秒）', 'mp-ukagaka'); ?></small>
        </p>

        <p>
            <label>
                <input type="checkbox" id="ai_greet_first_visit" name="ai_greet_first_visit" value="1" <?php echo isset($mpu_opt['ai_greet_first_visit']) && $mpu_opt['ai_greet_first_visit'] ? 'checked="checked"' : ''; ?> />
                <?php _e('啟用首次訪客打招呼', 'mp-ukagaka'); ?>
            </label>
            <br/>
            <small><?php _e('當訪客第一次訪問網站時，根據訪客來源（使用 Slimstat API）用 AI 生成個性化打招呼訊息。每個訪客只會看到一次。', 'mp-ukagaka'); ?></small>
        </p>

        <p id="ai_greet_prompt_container" style="<?php echo (isset($mpu_opt['ai_greet_first_visit']) && $mpu_opt['ai_greet_first_visit']) ? '' : 'display:none;'; ?>">
            <label for="ai_greet_prompt"><?php _e('首次訪客打招呼提示詞：', 'mp-ukagaka'); ?></label><br/>
            <textarea cols="60" rows="3" id="ai_greet_prompt" name="ai_greet_prompt" class="resizable" style="line-height:130%;"><?php echo isset($mpu_opt['ai_greet_prompt']) ? esc_textarea($mpu_opt['ai_greet_prompt']) : '你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。'; ?></textarea>
            <br/>
            <small><?php _e('定義春菜對首次訪客的打招呼風格和語氣', 'mp-ukagaka'); ?></small>
        </p>

        <script>
        jQuery(document).ready(function($) {
            $('#ai_greet_first_visit').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ai_greet_prompt_container').slideDown();
                } else {
                    $('#ai_greet_prompt_container').slideUp();
                }
            });
            $('#ai_text_color').on('change', function() {
                $('#ai_text_color_display').text($(this).val());
            });
        });
        </script>

        <p><input name="submit_ai" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>

