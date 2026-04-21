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
$claude_model = isset($mpu_opt['llm_claude_model']) ? $mpu_opt['llm_claude_model'] : (isset($mpu_opt['claude_model']) ? $mpu_opt['claude_model'] : 'claude-sonnet-4-6');

// 各 provider 預設模型清單（用於判斷是否為自訂）
$gemini_preset_models = ['gemini-2.5-flash', 'gemini-2.5-pro'];
$openai_preset_models = ['gpt-4.1-mini-2025-04-14', 'gpt-4o-mini', 'gpt-4o'];
$claude_preset_models = ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001', 'claude-opus-4-7'];

$gemini_is_custom = !in_array($gemini_model, $gemini_preset_models, true);
$openai_is_custom = !in_array($openai_model, $openai_preset_models, true);
$claude_is_custom = !in_array($claude_model, $claude_preset_models, true);

// 檢查是否啟用頁面感知
$ai_enabled = isset($mpu_opt['ai_enabled']) && $mpu_opt['ai_enabled'];

// 檢查是否啟用LLM取代內建對話（支援所有提供商）
$llm_replace_dialogue = isset($mpu_opt['llm_replace_dialogue']) ? $mpu_opt['llm_replace_dialogue'] : (isset($mpu_opt['ollama_replace_dialogue']) && $mpu_opt['ollama_replace_dialogue'] && $current_provider === 'ollama');
?>

<div>
    <h3><?php _e('🤖 LLM 設定', 'mp-ukagaka'); ?></h3>
    <p style="color: #8A7FA0; margin-bottom: 20px;">
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
                    <input type="password" id="llm_gemini_api_key" name="llm_gemini_api_key" value="" placeholder="<?php echo $gemini_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 Google Gemini API Key', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="new-password" />
                    <br />
                    <small><?php _e('請前往 <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if ($gemini_key_exists) {
                                                                                                                                                                    echo '<span class="mpu-key-set">✓ ' . __('已設定', 'mp-ukagaka') . '</span>';
                                                                                                                                                                } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="llm_gemini_model_picker"><?php _e('Gemini 模型：', 'mp-ukagaka'); ?></label>
                    <input type="hidden" id="llm_gemini_model" name="llm_gemini_model" value="<?php echo esc_attr($gemini_model); ?>" />
                    <select id="llm_gemini_model_picker" style="width: 100%; max-width: 400px;">
                        <option value="gemini-2.5-flash" <?php echo (!$gemini_is_custom && $gemini_model === 'gemini-2.5-flash') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Gemini 2.5 Flash (推薦)', 'mp-ukagaka')); ?></option>
                        <option value="gemini-2.5-pro" <?php echo (!$gemini_is_custom && $gemini_model === 'gemini-2.5-pro') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Gemini 2.5 Pro (更聰明，適合複雜推理)', 'mp-ukagaka')); ?></option>
                        <option value="__custom" <?php echo $gemini_is_custom ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('自訂模型…', 'mp-ukagaka')); ?></option>
                    </select>
                    <div id="llm_gemini_model_custom_wrap" style="display:<?php echo $gemini_is_custom ? 'block' : 'none'; ?>;margin-top:8px;">
                        <input type="text" id="llm_gemini_model_custom_input" style="width: 100%; max-width: 400px;"
                            value="<?php echo $gemini_is_custom ? esc_attr($gemini_model) : ''; ?>"
                            placeholder="<?php esc_attr_e('輸入完整模型 ID，例如：gemini-2.0-flash', 'mp-ukagaka'); ?>"
                            autocomplete="off" />
                    </div>
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
                    <input type="password" id="llm_openai_api_key" name="llm_openai_api_key" value="" placeholder="<?php echo $openai_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 OpenAI API Key', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="new-password" />
                    <br />
                    <small><?php _e('請前往 <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if ($openai_key_exists) {
                                                                                                                                                                echo '<span class="mpu-key-set">✓ ' . __('已設定', 'mp-ukagaka') . '</span>';
                                                                                                                                                            } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="llm_openai_model_picker"><?php _e('OpenAI 模型：', 'mp-ukagaka'); ?></label>
                    <input type="hidden" id="llm_openai_model" name="llm_openai_model" value="<?php echo esc_attr($openai_model); ?>" />
                    <select id="llm_openai_model_picker" style="width: 100%; max-width: 400px;">
                        <option value="gpt-4.1-mini-2025-04-14" <?php echo (!$openai_is_custom && $openai_model === 'gpt-4.1-mini-2025-04-14') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4.1 Mini (推薦，速度快成本低)', 'mp-ukagaka')); ?></option>
                        <option value="gpt-4o-mini" <?php echo (!$openai_is_custom && $openai_model === 'gpt-4o-mini') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4o Mini (快速且經濟)', 'mp-ukagaka')); ?></option>
                        <option value="gpt-4o" <?php echo (!$openai_is_custom && $openai_model === 'gpt-4o') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4o (更聰明)', 'mp-ukagaka')); ?></option>
                        <option value="__custom" <?php echo $openai_is_custom ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('自訂模型…', 'mp-ukagaka')); ?></option>
                    </select>
                    <div id="llm_openai_model_custom_wrap" style="display:<?php echo $openai_is_custom ? 'block' : 'none'; ?>;margin-top:8px;">
                        <input type="text" id="llm_openai_model_custom_input" style="width: 100%; max-width: 400px;"
                            value="<?php echo $openai_is_custom ? esc_attr($openai_model) : ''; ?>"
                            placeholder="<?php esc_attr_e('輸入完整模型 ID，例如：gpt-4.1-2025-04-14', 'mp-ukagaka'); ?>"
                            autocomplete="off" />
                    </div>
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
                    <input type="password" id="llm_claude_api_key" name="llm_claude_api_key" value="" placeholder="<?php echo $claude_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 Claude API Key', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="new-password" />
                    <br />
                    <small><?php _e('請前往 <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a> 取得 API Key', 'mp-ukagaka'); ?> <?php if ($claude_key_exists) {
                                                                                                                                                            echo '<span class="mpu-key-set">✓ ' . __('已設定', 'mp-ukagaka') . '</span>';
                                                                                                                                                        } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="llm_claude_model_picker"><?php _e('Claude 模型：', 'mp-ukagaka'); ?></label>
                    <input type="hidden" id="llm_claude_model" name="llm_claude_model" value="<?php echo esc_attr($claude_model); ?>" />
                    <select id="llm_claude_model_picker" style="width: 100%; max-width: 400px;">
                        <option value="claude-sonnet-4-6" <?php echo (!$claude_is_custom && $claude_model === 'claude-sonnet-4-6') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Sonnet 4.6 (推薦)', 'mp-ukagaka')); ?></option>
                        <option value="claude-haiku-4-5-20251001" <?php echo (!$claude_is_custom && $claude_model === 'claude-haiku-4-5-20251001') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Haiku 4.5 (快速)', 'mp-ukagaka')); ?></option>
                        <option value="claude-opus-4-7" <?php echo (!$claude_is_custom && $claude_model === 'claude-opus-4-7') ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Opus 4.7 (進階)', 'mp-ukagaka')); ?></option>
                        <option value="__custom" <?php echo $claude_is_custom ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('自訂模型…', 'mp-ukagaka')); ?></option>
                    </select>
                    <div id="llm_claude_model_custom_wrap" style="display:<?php echo $claude_is_custom ? 'block' : 'none'; ?>;margin-top:8px;">
                        <input type="text" id="llm_claude_model_custom_input" style="width: 100%; max-width: 400px;"
                            value="<?php echo $claude_is_custom ? esc_attr($claude_model) : ''; ?>"
                            placeholder="<?php esc_attr_e('輸入完整模型 ID，例如：claude-opus-4-7', 'mp-ukagaka'); ?>"
                            autocomplete="off" />
                    </div>
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
            <div class="mpu-field-group" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #DED6EE;">
                <label><?php _e('快取狀態：', 'mp-ukagaka'); ?></label>
                <div style="margin-top: 8px; display: flex; gap: 24px; align-items: center;">
                    <span>
                        <strong><?php echo esc_html($cache_stats['count']); ?></strong> <?php _e('筆快取', 'mp-ukagaka'); ?>
                    </span>
                    <span>
                        <strong><?php echo esc_html(number_format($cache_stats['size_kb'] ?? 0, 1)); ?></strong> KB
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

            // 自訂模型選擇邏輯（通用）
            function setupCustomModelPicker(pickerId, valueId, customWrapId, customInputId) {
                var $picker = $('#' + pickerId);
                var $value = $('#' + valueId);
                var $wrap = $('#' + customWrapId);
                var $input = $('#' + customInputId);

                $picker.on('change', function() {
                    if (this.value === '__custom') {
                        $wrap.show();
                        $input.focus();
                        $value.val($input.val().trim());
                    } else {
                        $wrap.hide();
                        $input.val('');
                        $value.val(this.value);
                    }
                });

                $input.on('input', function() {
                    $value.val(this.value.trim());
                });
            }

            setupCustomModelPicker('llm_gemini_model_picker', 'llm_gemini_model', 'llm_gemini_model_custom_wrap', 'llm_gemini_model_custom_input');
            setupCustomModelPicker('llm_openai_model_picker', 'llm_openai_model', 'llm_openai_model_custom_wrap', 'llm_openai_model_custom_input');
            setupCustomModelPicker('llm_claude_model_picker', 'llm_claude_model', 'llm_claude_model_custom_wrap', 'llm_claude_model_custom_input');

            // 連接測試函數（通用）
            function testConnection(provider, apiKeyId, modelId, resultId, buttonId) {
                var $btn = $('#' + buttonId);
                var apiKey = apiKeyId ? $('#' + apiKeyId).val() : '';
                var model = modelId ? $('#' + modelId).val() : '';

                $btn.prop('disabled', true);
                $('#' + resultId).html('<span class="mpu-loading"></span><?php _e("測試中...", "mp-ukagaka"); ?>');

                var requestData = { model: model };

                if (provider !== 'ollama') {
                    requestData.api_key = apiKey;
                } else {
                    requestData.endpoint = $('#ollama_endpoint').val();
                }

                $.ajax({
                    url: '<?php echo esc_url(rest_url("mp-ukagaka/v1/test-connection/")); ?>' + provider,
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                    data: requestData,
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $('#' + resultId).html('<span class="mpu-test-success">✓ ' + response.msg + '</span>');
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : error;
                        $('#' + resultId).html('<span class="mpu-test-error">✗ ' + errorMsg + '</span>');
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
                    url: '<?php echo esc_url(rest_url("mp-ukagaka/v1/test-connection/weather")); ?>',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                    data: { latitude: latitude, longitude: longitude },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $('#weather_test_result').html('<span class="mpu-test-success">✓ ' + response.msg + '</span>');
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : error;
                        $('#weather_test_result').html('<span class="mpu-test-error">✗ ' + errorMsg + '</span>');
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
                    url: '<?php echo esc_url(rest_url("mp-ukagaka/v1/clear-cache")); ?>',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $('#cache_clear_result').html('<span class="mpu-test-success">✓ ' + response.msg + '</span>');
                        // 更新統計顯示為 0
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : error;
                        $('#cache_clear_result').html('<span class="mpu-test-error">✗ ' + errorMsg + '</span>');
                    }
                });
                
                return false;
            });
        });
    })(jQuery);
</script>