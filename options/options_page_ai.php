<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($mpu_opt) || !is_array($mpu_opt)) {
    $mpu_opt = function_exists('mpu_get_option') ? mpu_get_option() : [];
}
?>
<div>
    <h3><?php _e('🧠 AI 設定 (Context Awareness)', 'mp-ukagaka'); ?></h3>
    <p style="color: #8A7FA0; margin-bottom: 20px;">
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
                    <option value="" <?php if (!isset($mpu_opt['ai_language']) || $mpu_opt['ai_language'] === '') {
                                            echo ' selected="selected"';
                                        } ?>><?php _e('預設', 'mp-ukagaka'); ?></option>
                    <option value="zh-TW" <?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'zh-TW') {
                                                echo ' selected="selected"';
                                            } ?>><?php _e('繁體中文', 'mp-ukagaka'); ?></option>
                    <option value="ja" <?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'ja') {
                                            echo ' selected="selected"';
                                        } ?>><?php _e('日本語', 'mp-ukagaka'); ?></option>
                    <option value="en" <?php if (isset($mpu_opt['ai_language']) && $mpu_opt['ai_language'] == 'en') {
                                            echo ' selected="selected"';
                                        } ?>><?php _e('English', 'mp-ukagaka'); ?></option>
                </select>
                <small><?php _e('此為全域 AI 回應語言，也會用於 {{language}} prompt 變數；選擇預設時會跟隨角色 manifest.json 的 language。', 'mp-ukagaka'); ?></small>
            </div>
            <div class="mpu-field-group">
                <label for="ai_system_prompt"><?php _e('人格設定 (System Prompt)：', 'mp-ukagaka'); ?></label>
                <?php
                // 偵測目前系統提示詞的來源並顯示指示器
                $current_personality_id = function_exists('mpu_get_current_personality_id') ? mpu_get_current_personality_id() : null;
                $prompt_source = function_exists('mpu_detect_prompt_source') ? mpu_detect_prompt_source($current_personality_id, $mpu_opt) : 'backend';
                $source_configs = [
                    'modular' => [
                        'color' => '#46b450',
                        'bg' => '#ecf7ed',
                        'icon' => '🧩',
                        'label' => __('模組化檔案使用中', 'mp-ukagaka'),
                        'desc' => __('instructions.md + personality.md 的內容將作為 System Prompt。下方 textarea 的內容不會被使用。', 'mp-ukagaka'),
                        'readonly' => true,
                    ],
                    'system_prompt_md' => [
                        'color' => '#0073aa',
                        'bg' => '#e8f4fd',
                        'icon' => '📄',
                        'label' => __('Legacy 檔案使用中', 'mp-ukagaka'),
                        'desc' => __('system_prompt.md 的內容將作為 System Prompt。下方 textarea 的內容不會被使用。', 'mp-ukagaka'),
                        'readonly' => true,
                    ],
                    'manifest' => [
                        'color' => '#826eb4',
                        'bg' => '#f3f0f8',
                        'icon' => '📋',
                        'label' => __('manifest.json 使用中', 'mp-ukagaka'),
                        'desc' => __('manifest.json 的 system_prompt 欄位將作為 System Prompt。下方 textarea 的內容不會被使用。', 'mp-ukagaka'),
                        'readonly' => true,
                    ],
                    'backend' => [
                        'color' => '#dba617',
                        'bg' => '#fef8e8',
                        'icon' => '✏️',
                        'label' => __('Textarea 使用中', 'mp-ukagaka'),
                        'desc' => __('下方 textarea 的內容將作為 System Prompt。', 'mp-ukagaka'),
                        'readonly' => false,
                    ],
                    'default' => [
                        'color' => '#dc3232',
                        'bg' => '#fbeaea',
                        'icon' => '⚠️',
                        'label' => __('未設定（內建預設值）', 'mp-ukagaka'),
                        'desc' => __('沒有找到任何設定，將使用內建的預設 System Prompt。', 'mp-ukagaka'),
                        'readonly' => false,
                    ],
                ];
                $cfg = $source_configs[$prompt_source] ?? $source_configs['default'];
                ?>
                <div style="margin: 6px 0 8px; padding: 8px 12px; border-left: 4px solid <?php echo esc_attr($cfg['color']); ?>; background: <?php echo esc_attr($cfg['bg']); ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <strong><?php echo esc_html($cfg['icon'] . ' ' . $cfg['label']); ?></strong>
                    <span style="margin-left: 6px; color: #555;"><?php echo esc_html($cfg['desc']); ?></span>
                </div>
                <textarea cols="60" rows="10" id="ai_system_prompt" name="ai_system_prompt" class="resizable" style="line-height:130%; width: 100%; max-width: 850px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace;<?php echo $cfg['readonly'] ? ' opacity: 0.6;' : ''; ?>"<?php echo $cfg['readonly'] ? ' readonly' : ''; ?>><?php echo isset($mpu_opt['ai_system_prompt']) ? esc_textarea($mpu_opt['ai_system_prompt']) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。必ずこのキャラクターとして振る舞い、一人称は「私」を使用してください。回答は日本語で、50文字以内の短い一言で返してください。自分が AI や Qwen だと言わないでください。'; ?></textarea>
                <small>
                    <?php _e('提示：支援 Markdown 格式，可直接使用結構化格式增強模型理解。<br>可使用 {{變數名}} 進行變數替換（如：{{ukagaka_display_name}}、{{time_context}}、{{language}} 等）。', 'mp-ukagaka'); ?>
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
                <label for="ai_max_tokens"><?php _e('最大輸出 Token (Max Output Tokens)：', 'mp-ukagaka'); ?></label>
                <input type="number" id="ai_max_tokens" name="ai_max_tokens" value="<?php echo isset($mpu_opt['ai_max_tokens']) ? intval($mpu_opt['ai_max_tokens']) : 1000; ?>" min="100" max="8192" style="width: 80px;" />
                <small><?php _e('設定 AI 回應的最大長度（Token 數）。預設 1000。增加此值可避免回應被截斷，但會增加 API 成本（如有）。', 'mp-ukagaka'); ?></small>
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
                <label for="ai_greet_prompt"><?php _e('首次訪客打招呼提示詞（後備）：', 'mp-ukagaka'); ?></label>
                <textarea cols="60" rows="8" id="ai_greet_prompt" name="ai_greet_prompt" class="resizable" style="line-height:130%; width: 100%; font-family: 'Consolas', 'Monaco', 'Courier New', monospace;"><?php echo isset($mpu_opt['ai_greet_prompt']) ? esc_textarea($mpu_opt['ai_greet_prompt']) : 'あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。'; ?></textarea>
                <small>
                    <?php _e('角色的 <code>dynamics.json</code> 中有 <code>greet_first_visit</code> 設定時，會優先使用該設定作為會話指示。此欄位僅在角色無 dynamics.json 設定時作為後備使用。<br>可使用 {{變數名}} 進行變數替換（如：{{ukagaka_display_name}}、{{time_context}}、{{language}} 等）。', 'mp-ukagaka'); ?>
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

    <!-- User Memory: 管理人の記憶 -->
    <?php
    $mpu_memory_data    = get_user_meta(get_current_user_id(), 'mpu_user_memory', true);
    $mpu_memory_facts   = (is_array($mpu_memory_data) && !empty($mpu_memory_data['facts'])) ? $mpu_memory_data['facts'] : [];
    $mpu_memory_updated = (is_array($mpu_memory_data) && !empty($mpu_memory_data['updated_at'])) ? $mpu_memory_data['updated_at'] : null;
    ?>
    <div class="mpu-settings-card" style="margin-top: 20px;">
        <h4><?php _e('🧠 管理人の記憶 (User Memory)', 'mp-ukagaka'); ?></h4>
        <?php if (!empty($mpu_memory_facts)): ?>
            <?php if ($mpu_memory_updated): ?>
                <p style="color: #666; font-size: 12px; margin-bottom: 8px;">
                    <?php echo esc_html(sprintf(__('最終更新: %s', 'mp-ukagaka'), $mpu_memory_updated)); ?>
                </p>
            <?php endif; ?>
            <ul style="list-style: disc; padding-left: 20px; margin-bottom: 16px;">
                <?php foreach ($mpu_memory_facts as $mpu_fact): ?>
                    <li><?php echo esc_html($mpu_fact); ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=mp-ukagaka/options.php&cur_page=5')); ?>">
                <?php wp_nonce_field('mpu_clear_memory'); ?>
                <input type="hidden" name="mpu_action" value="clear_memory" />
                <input type="submit" class="button button-secondary"
                       value="<?php esc_attr_e('記憶を消去', 'mp-ukagaka'); ?>"
                       onclick="return confirm('<?php echo esc_js(__('管理人の記憶を全て消去しますか？', 'mp-ukagaka')); ?>')" />
            </form>
        <?php else: ?>
            <p style="color: #999; margin-bottom: 8px;">
                <?php _e('保存された記憶はありません。', 'mp-ukagaka'); ?>
            </p>
        <?php endif; ?>
        <p style="color: #8A7FA0; margin-top: 12px;">
            <small><?php _e('チャット画面で /remember と入力すると、フリーレンが会話から記憶を抽出します。', 'mp-ukagaka'); ?></small>
        </p>
    </div>
</div>
