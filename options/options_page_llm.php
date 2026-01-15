<?php

/**
 * LLM 設定頁面（通用 LLM - 支援多提供商）
 * 
 * @package MP_Ukagaka
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit();
}

// 獲取當前提供商（向後兼容：優先使用 llm_provider，否則使用 ai_provider）
$current_provider = isset($mpu_opt['llm_provider']) ? $mpu_opt['llm_provider'] : (isset($mpu_opt['ai_provider']) ? $mpu_opt['ai_provider'] : 'gemini');

// 檢查 API Key 是否存在（不解密顯示）
$gemini_key_exists = !empty($mpu_opt['llm_gemini_api_key']) || !empty($mpu_opt['ai_api_key']);
$openai_key_exists = !empty($mpu_opt['llm_openai_api_key']) || !empty($mpu_opt['openai_api_key']);
$claude_key_exists = !empty($mpu_opt['llm_claude_api_key']) || !empty($mpu_opt['claude_api_key']);

// 獲取模型設定（向後兼容）
$gemini_model = isset($mpu_opt['llm_gemini_model']) ? $mpu_opt['llm_gemini_model'] : (isset($mpu_opt['gemini_model']) ? $mpu_opt['gemini_model'] : 'gemini-2.5-flash');
$openai_model = isset($mpu_opt['llm_openai_model']) ? $mpu_opt['llm_openai_model'] : (isset($mpu_opt['openai_model']) ? $mpu_opt['openai_model'] : 'gpt-4.1-mini-2025-04-14');
$claude_model = isset($mpu_opt['llm_claude_model']) ? $mpu_opt['llm_claude_model'] : (isset($mpu_opt['claude_model']) ? $mpu_opt['claude_model'] : 'claude-sonnet-4-5-20250929');

// 檢查是否啟用頁面感知
$ai_enabled = isset($mpu_opt['ai_enabled']) && $mpu_opt['ai_enabled'];

// 檢查是否啟用LLM取代內建對話（支援所有提供商）
$llm_replace_dialogue = isset($mpu_opt['llm_replace_dialogue']) ? $mpu_opt['llm_replace_dialogue'] : (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] && $current_provider === 'ollama');
?>

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

    .mpu-settings-card code {
        background: #D4E8F0;
        color: #2C3E50;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: "Courier New", Consolas, monospace;
        font-size: 12px;
        border: 1px solid #B8E6E6;
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

    .mpu-settings-card small a {
        color: #3A9BC1;
        text-decoration: none;
        transition: color 0.2s;
    }

    .mpu-settings-card small a:hover {
        color: #5FB3A1;
        text-decoration: underline;
    }

    .mpu-test-row span {
        margin-left: 8px;
        font-size: 13px;
    }

    .mpu-key-set {
        color: #5FB3A1;
        font-weight: 500;
    }

    .mpu-test-success {
        color: #5FB3A1;
    }

    .mpu-test-error {
        color: #E57373;
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

    /* 向後兼容：保留 mpu-llm-card 別名 */
    .mpu-llm-card {
        background: #E8F4F8;
        border: 1px solid #B8E6E6;
        border-radius: 10px;
        padding: 20px 24px;
        margin: 20px 0;
        box-shadow: 0 2px 8px rgba(168, 216, 234, 0.15);
    }

    .mpu-llm-card h4 {
        color: #4A9EBD;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #A8D8EA;
    }

    .mpu-llm-card code {
        background: #D4E8F0;
        color: #2C3E50;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: "Courier New", Consolas, monospace;
        font-size: 12px;
        border: 1px solid #B8E6E6;
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
        margin-bottom: 20px;
    }

    .mpu-test-row .button {
        flex-shrink: 0;
        min-width: auto;
        width: auto;
        padding: 6px 12px;
        background: linear-gradient(135deg, #A8D8EA 0%, #B8E6E6 100%);
        border: 2px solid #B8E6E6;
        border-radius: 6px;
        color: #2C3E50;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
    }

    .mpu-test-row .button:hover {
        background: linear-gradient(135deg, #4A9EBD 0%, #5FB3A1 100%);
        color: white;
        border-color: #4A9EBD;
        box-shadow: 0 2px 4px rgba(74, 158, 189, 0.2);
    }

    .mpu-test-row .button:active {
        background: linear-gradient(135deg, #3A8CAD 0%, #4FA391 100%);
    }

    #ollama_test_result {
        font-size: 13px;
    }

    .mpu-loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #B8E6E6;
        border-top-color: #4A9EBD;
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

    /* 提供商選項卡樣式 */
    .mpu-provider-tabs {
        display: flex;
        gap: 8px;
        margin: 20px 0;
        flex-wrap: wrap;
    }

    .mpu-provider-tab {
        padding: 10px 20px;
        background: #D4E8F0;
        border: 2px solid #B8E6E6;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
        user-select: none;
        color: #2C3E50;
    }

    .mpu-provider-tab:hover {
        background: #C5E3F6;
        border-color: #A8D8EA;
    }

    .mpu-provider-tab.active {
        background: linear-gradient(135deg, #4A9EBD 0%, #5FB3A1 100%);
        color: white;
        border-color: #4A9EBD;
        box-shadow: 0 2px 4px rgba(74, 158, 189, 0.2);
    }

    .mpu-provider-content {
        display: none;
    }

    .mpu-provider-content.active {
        display: block;
    }
</style>

<div>
    <h3><?php _e('LLM 設定', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('此頁面用於設定 AI 提供商、模型選擇和 LLM 功能。頁面感知 AI 的行為參數請前往「AI 設定」頁面。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="llm_setting" id="llm_setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=6'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <!-- AI 提供商選擇 -->
        <div class="mpu-settings-card">
            <h4><?php _e('🤖 AI 提供商', 'mp-ukagaka'); ?></h4>

            <div class="mpu-provider-tabs">
                <div class="mpu-provider-tab <?php echo $current_provider === 'gemini' ? 'active' : ''; ?>" data-provider="gemini">
                    ✨ Gemini
                </div>
                <div class="mpu-provider-tab <?php echo $current_provider === 'openai' ? 'active' : ''; ?>" data-provider="openai">
                    🧠 OpenAI
                </div>
                <div class="mpu-provider-tab <?php echo $current_provider === 'claude' ? 'active' : ''; ?>" data-provider="claude">
                    🎯 Claude
                </div>
                <div class="mpu-provider-tab <?php echo $current_provider === 'ollama' ? 'active' : ''; ?>" data-provider="ollama">
                    🖥️ Ollama
                </div>
            </div>

            <input type="hidden" id="llm_provider" name="llm_provider" value="<?php echo esc_attr($current_provider); ?>" />

            <!-- Gemini 設定 -->
            <div class="mpu-provider-content <?php echo $current_provider === 'gemini' ? 'active' : ''; ?>" data-provider="gemini">
                <div class="mpu-field-group">
                    <label for="llm_gemini_api_key"><?php _e('Gemini API Key：', 'mp-ukagaka'); ?></label>
                    <input type="password" id="llm_gemini_api_key" name="llm_gemini_api_key" value="" placeholder="<?php echo $gemini_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 Google Gemini API Key', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="off" />
                    <br />
                    <small><?php _e('請前往 <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if ($gemini_key_exists) {
                                                                                                                                                                    echo '<span class="mpu-key-set">✓ ' . __('已設定', 'mp-ukagaka') . '</span>';
                                                                                                                                                                } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="llm_gemini_model"><?php _e('Gemini 模型：', 'mp-ukagaka'); ?></label>
                    <select id="llm_gemini_model" name="llm_gemini_model" style="width: 100%; max-width: 400px;">
                        <option value="gemini-2.5-flash" <?php echo $gemini_model === 'gemini-2.5-flash' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Gemini 2.5 Flash (推薦)', 'mp-ukagaka')); ?></option>
                        <option value="gemini-2.5-pro" <?php echo $gemini_model === 'gemini-2.5-pro' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Gemini 2.5 Pro (更聰明，適合複雜推理)', 'mp-ukagaka')); ?></option>
                    </select>
                </div>
                <div class="mpu-test-row">
                    <button type="button" id="test_gemini_connection" class="button"><?php _e('測試連接', 'mp-ukagaka'); ?></button>
                    <span id="gemini_test_result"></span>
                </div>
            </div>

            <!-- OpenAI 設定 -->
            <div class="mpu-provider-content <?php echo $current_provider === 'openai' ? 'active' : ''; ?>" data-provider="openai">
                <div class="mpu-field-group">
                    <label for="llm_openai_api_key"><?php _e('OpenAI API Key：', 'mp-ukagaka'); ?></label>
                    <input type="password" id="llm_openai_api_key" name="llm_openai_api_key" value="" placeholder="<?php echo $openai_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 OpenAI API Key', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="off" />
                    <br />
                    <small><?php _e('請前往 <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if ($openai_key_exists) {
                                                                                                                                                                echo '<span class="mpu-key-set">✓ ' . __('已設定', 'mp-ukagaka') . '</span>';
                                                                                                                                                            } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="llm_openai_model"><?php _e('OpenAI 模型：', 'mp-ukagaka'); ?></label>
                    <select id="llm_openai_model" name="llm_openai_model" style="width: 100%; max-width: 400px;">
                        <option value="gpt-4.1-mini-2025-04-14" <?php echo $openai_model === 'gpt-4.1-mini-2025-04-14' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4.1 Mini (推薦，速度快成本低)', 'mp-ukagaka')); ?></option>
                        <option value="gpt-4o-mini" <?php echo $openai_model === 'gpt-4o-mini' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4o Mini (快速且經濟)', 'mp-ukagaka')); ?></option>
                        <option value="gpt-4o" <?php echo $openai_model === 'gpt-4o' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4o (更聰明)', 'mp-ukagaka')); ?></option>
                    </select>
                </div>
                <div class="mpu-test-row">
                    <button type="button" id="test_openai_connection" class="button"><?php _e('測試連接', 'mp-ukagaka'); ?></button>
                    <span id="openai_test_result"></span>
                </div>
            </div>

            <!-- Claude 設定 -->
            <div class="mpu-provider-content <?php echo $current_provider === 'claude' ? 'active' : ''; ?>" data-provider="claude">
                <div class="mpu-field-group">
                    <label for="llm_claude_api_key"><?php _e('Claude API Key：', 'mp-ukagaka'); ?></label>
                    <input type="password" id="llm_claude_api_key" name="llm_claude_api_key" value="" placeholder="<?php echo $claude_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 Claude API Key', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="off" />
                    <br />
                    <small><?php _e('請前往 <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if ($claude_key_exists) {
                                                                                                                                                            echo '<span class="mpu-key-set">✓ ' . __('已設定', 'mp-ukagaka') . '</span>';
                                                                                                                                                        } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="llm_claude_model"><?php _e('Claude 模型：', 'mp-ukagaka'); ?></label>
                    <select id="llm_claude_model" name="llm_claude_model" style="width: 100%; max-width: 400px;">
                        <option value="claude-sonnet-4-5-20250929" <?php echo $claude_model === 'claude-sonnet-4-5-20250929' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Sonnet 4.5 (推薦)', 'mp-ukagaka')); ?></option>
                        <option value="claude-haiku-4-5-20251001" <?php echo $claude_model === 'claude-haiku-4-5-20251001' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Haiku 4.5 (快速)', 'mp-ukagaka')); ?></option>
                        <option value="claude-opus-4-5-20251101" <?php echo $claude_model === 'claude-opus-4-5-20251101' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Opus 4.5 (進階)', 'mp-ukagaka')); ?></option>
                    </select>
                </div>
                <div class="mpu-test-row">
                    <button type="button" id="test_claude_connection" class="button"><?php _e('測試連接', 'mp-ukagaka'); ?></button>
                    <span id="claude_test_result"></span>
                </div>
            </div>

            <!-- Ollama 設定 -->
            <div class="mpu-provider-content <?php echo $current_provider === 'ollama' ? 'active' : ''; ?>" data-provider="ollama">
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
                <div class="mpu-field-group">
                    <label>
                        <input type="checkbox" id="ollama_disable_thinking" name="ollama_disable_thinking" value="1" <?php echo isset($mpu_opt['ollama_disable_thinking']) && $mpu_opt['ollama_disable_thinking'] ? 'checked="checked"' : ''; ?> />
                        <?php _e('關閉思考模式（Qwen3、DeepSeek 等模型）', 'mp-ukagaka'); ?>
                    </label>
                    <br />
                    <small><?php _e('預設啟用思考模式，AI 會先思考再回答，提高回答品質。勾選此選項可關閉思考、加快回應速度。', 'mp-ukagaka'); ?></small>
                </div>
            </div>
        </div>

        <!-- 對話設定 -->
        <div class="mpu-settings-card">
            <h4><?php _e('💬 對話設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label>
                    <input type="checkbox" id="llm_replace_dialogue" name="llm_replace_dialogue" value="1" <?php echo $llm_replace_dialogue ? 'checked="checked"' : ''; ?> />
                    <?php _e('使用 LLM 取代內建對話', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，偽春菜對話將由 LLM 實時生成，不使用靜態對話列表。支援所有 AI 提供商（Gemini、OpenAI、Claude、Ollama）。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group" style="margin-top: 16px;">
                <label>
                    <input type="checkbox" id="enable_chat_mode" name="enable_chat_mode" value="1" <?php echo (isset($mpu_opt['enable_chat_mode']) && $mpu_opt['enable_chat_mode']) ? 'checked="checked"' : ''; ?> />
                    <?php _e('啟用互動對話功能', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，前台的「更換偽春菜」按鈕將變為「對話」按鈕，讓訪客可以直接與偽春菜對話。關閉則顯示原本的角色切換選單。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 頁面感知功能開關 -->
        <div class="mpu-settings-card">
            <h4><?php _e('📄 頁面感知功能', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label>
                    <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?php echo $ai_enabled ? 'checked="checked"' : ''; ?> />
                    <?php _e('啟用頁面感知功能', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，AI 會根據頁面內容生成相關對話。此功能的行為參數（語言、角色、機率等）請在「AI 設定」頁面配置。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 天氣設定 -->
        <?php
        $weather_enabled = isset($mpu_opt['weather_enabled']) && $mpu_opt['weather_enabled'];
        $weather_latitude = isset($mpu_opt['weather_latitude']) ? floatval($mpu_opt['weather_latitude']) : 25.0330;
        $weather_longitude = isset($mpu_opt['weather_longitude']) ? floatval($mpu_opt['weather_longitude']) : 121.5654;
        ?>
        <div class="mpu-settings-card">
            <h4><?php _e('🌤️ 天氣設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label>
                    <input type="checkbox" id="weather_enabled" name="weather_enabled" value="1" <?php echo $weather_enabled ? 'checked="checked"' : ''; ?> />
                    <?php _e('啟用天氣感知功能', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('讓角色知道當地天氣，並根據天氣發表看法。使用 Open-Meteo 免費 API，無需 API Key。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group" style="margin-top: 12px;">
                <label><?php _e('位置座標：', 'mp-ukagaka'); ?></label>
                <div style="display: flex; gap: 16px; align-items: center; margin-top: 8px;">
                    <div>
                        <label for="weather_latitude" style="font-weight: normal; margin-right: 4px;"><?php _e('緯度：', 'mp-ukagaka'); ?></label>
                        <input type="text" id="weather_latitude" name="weather_latitude" value="<?php echo esc_attr($weather_latitude); ?>" style="width: 120px;" placeholder="25.0330" />
                    </div>
                    <div>
                        <label for="weather_longitude" style="font-weight: normal; margin-right: 4px;"><?php _e('經度：', 'mp-ukagaka'); ?></label>
                        <input type="text" id="weather_longitude" name="weather_longitude" value="<?php echo esc_attr($weather_longitude); ?>" style="width: 120px;" placeholder="121.5654" />
                    </div>
                </div>
                <small style="display: block; margin-top: 8px;">
                    <?php _e('預設為台北（25.0330, 121.5654）。可使用', 'mp-ukagaka'); ?> 
                    <a href="https://www.google.com/maps" target="_blank">Google Maps</a> 
                    <?php _e('查詢座標（右鍵點擊地圖即可複製座標）。', 'mp-ukagaka'); ?>
                </small>
            </div>
            <div class="mpu-test-row" style="margin-top: 12px;">
                <button type="button" id="test_weather_api" class="button"><?php _e('測試天氣 API', 'mp-ukagaka'); ?></button>
                <span id="weather_test_result"></span>
            </div>
        </div>

        <!-- API 快取設定 -->
        <?php
        $api_cache_enabled = isset($mpu_opt['api_cache_enabled']) && $mpu_opt['api_cache_enabled'];
        $api_cache_ttl = isset($mpu_opt['api_cache_ttl']) ? intval($mpu_opt['api_cache_ttl']) : 3600;
        $cache_stats = function_exists('mpu_get_api_cache_stats') ? mpu_get_api_cache_stats() : ['count' => 0, 'size_kb' => 0];
        ?>
        <div class="mpu-settings-card">
            <h4><?php _e('💾 API 快取設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label>
                    <input type="checkbox" id="api_cache_enabled" name="api_cache_enabled" value="1" <?php echo $api_cache_enabled ? 'checked="checked"' : ''; ?> />
                    <?php _e('啟用 API 回應快取', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，相同的 AI 請求將使用快取回應，減少 API 費用和響應延遲。適合訪客流量大的網站。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group" style="margin-top: 12px;">
                <label for="api_cache_ttl"><?php _e('快取有效期（TTL）：', 'mp-ukagaka'); ?></label>
                <select id="api_cache_ttl" name="api_cache_ttl" style="width: auto; min-width: 200px;">
                    <option value="1800" <?php echo $api_cache_ttl === 1800 ? 'selected="selected"' : ''; ?>><?php _e('30 分鐘', 'mp-ukagaka'); ?></option>
                    <option value="3600" <?php echo $api_cache_ttl === 3600 ? 'selected="selected"' : ''; ?>><?php _e('1 小時（推薦）', 'mp-ukagaka'); ?></option>
                    <option value="7200" <?php echo $api_cache_ttl === 7200 ? 'selected="selected"' : ''; ?>><?php _e('2 小時', 'mp-ukagaka'); ?></option>
                    <option value="21600" <?php echo $api_cache_ttl === 21600 ? 'selected="selected"' : ''; ?>><?php _e('6 小時', 'mp-ukagaka'); ?></option>
                    <option value="86400" <?php echo $api_cache_ttl === 86400 ? 'selected="selected"' : ''; ?>><?php _e('24 小時', 'mp-ukagaka'); ?></option>
                </select>
                <br />
                <small><?php _e('快取過期後，下次相同請求將重新呼叫 API。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #B8E6E6;">
                <label><?php _e('快取狀態：', 'mp-ukagaka'); ?></label>
                <div style="margin-top: 8px; display: flex; gap: 24px; align-items: center;">
                    <span>
                        <strong><?php echo esc_html($cache_stats['count']); ?></strong> <?php _e('筆快取', 'mp-ukagaka'); ?>
                    </span>
                    <span>
                        <strong><?php echo esc_html(number_format($cache_stats['size_kb'], 1)); ?></strong> KB
                    </span>
                    <button type="button" id="clear_api_cache" class="button" style="margin-left: auto;">
                        <?php _e('清除所有快取', 'mp-ukagaka'); ?>
                    </button>
                    <span id="cache_clear_result"></span>
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
            // 提供商選項卡切換
            $('.mpu-provider-tab').on('click', function() {
                var provider = $(this).data('provider');

                // 更新選項卡狀態
                $('.mpu-provider-tab').removeClass('active');
                $(this).addClass('active');

                // 更新隱藏欄位
                $('#llm_provider').val(provider);

                // 更新內容顯示
                $('.mpu-provider-content').removeClass('active');
                $('.mpu-provider-content[data-provider="' + provider + '"]').addClass('active');
            });

            // 連接測試函數（通用）
            function testConnection(provider, apiKeyId, modelId, resultId, buttonId) {
                var $btn = $('#' + buttonId);
                var apiKey = $('#' + apiKeyId).val();
                var model = $('#' + modelId).val();

                $btn.prop('disabled', true);
                $('#' + resultId).html('<span class="mpu-loading"></span><?php _e("測試中...", "mp-ukagaka"); ?>');

                var ajaxData = {
                    action: 'mpu_test_' + provider + '_connection',
                    model: model,
                    nonce: '<?php echo wp_create_nonce("mpu_test_connection"); ?>'
                };

                if (provider !== 'ollama') {
                    ajaxData.api_key = apiKey;
                } else {
                    ajaxData.endpoint = $('#ollama_endpoint').val();
                }

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $('#' + resultId).html('<span class="mpu-test-success">✓ ' + response.data + '</span>');
                        } else {
                            $('#' + resultId).html('<span class="mpu-test-error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $('#' + resultId).html('<span class="mpu-test-error">✗ <?php _e("測試失敗，請檢查網絡連接", "mp-ukagaka"); ?> (' + error + ')</span>');
                    }
                });
            }

            // Gemini 連接測試
            $('#test_gemini_connection').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                testConnection('gemini', 'llm_gemini_api_key', 'llm_gemini_model', 'gemini_test_result', 'test_gemini_connection');
                return false;
            });

            // OpenAI 連接測試
            $('#test_openai_connection').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                testConnection('openai', 'llm_openai_api_key', 'llm_openai_model', 'openai_test_result', 'test_openai_connection');
                return false;
            });

            // Claude 連接測試
            $('#test_claude_connection').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                testConnection('claude', 'llm_claude_api_key', 'llm_claude_model', 'claude_test_result', 'test_claude_connection');
                return false;
            });

            // Ollama 連接測試
            $('#test_ollama_connection').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                testConnection('ollama', '', 'ollama_model', 'ollama_test_result', 'test_ollama_connection');
                return false;
            });

            // 天氣 API 測試
            $('#test_weather_api').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                var latitude = $('#weather_latitude').val();
                var longitude = $('#weather_longitude').val();
                
                $btn.prop('disabled', true);
                $('#weather_test_result').html('<span class="mpu-loading"></span><?php _e("測試中...", "mp-ukagaka"); ?>');
                
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'mpu_test_weather_api',
                        latitude: latitude,
                        longitude: longitude,
                        nonce: '<?php echo wp_create_nonce("mpu_test_weather"); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $('#weather_test_result').html('<span class="mpu-test-success">✓ ' + response.data + '</span>');
                        } else {
                            $('#weather_test_result').html('<span class="mpu-test-error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $('#weather_test_result').html('<span class="mpu-test-error">✗ <?php _e("測試失敗，請檢查網絡連接", "mp-ukagaka"); ?> (' + error + ')</span>');
                    }
                });
                
                return false;
            });

            // 清除 API 快取按鈕
            $('#clear_api_cache').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                
                if (!confirm('<?php _e("確定要清除所有 API 快取嗎？", "mp-ukagaka"); ?>')) {
                    return false;
                }
                
                $btn.prop('disabled', true);
                $('#cache_clear_result').html('<span class="mpu-loading"></span><?php _e("清除中...", "mp-ukagaka"); ?>');
                
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'mpu_clear_api_cache',
                        nonce: '<?php echo wp_create_nonce("mpu_clear_cache"); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $('#cache_clear_result').html('<span class="mpu-test-success">✓ ' + response.data + '</span>');
                            // 更新統計顯示為 0
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $('#cache_clear_result').html('<span class="mpu-test-error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $('#cache_clear_result').html('<span class="mpu-test-error">✗ <?php _e("清除失敗", "mp-ukagaka"); ?> (' + error + ')</span>');
                    }
                });
                
                return false;
            });
        });
    })(jQuery);
</script>