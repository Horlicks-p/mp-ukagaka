<?php

/**
 * 日記設定頁面
 * 
 * 自動日記功能（フリーレン手記）的獨立設定頁面
 * 包含獨立的 AI 供應商選擇，可與對話功能使用不同的模型
 * 
 * @package MP_Ukagaka
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit();
}

// 獲取日記相關設定
$diary_enabled = isset($mpu_opt['diary_enabled']) && $mpu_opt['diary_enabled'];
$diary_category = isset($mpu_opt['diary_category']) ? intval($mpu_opt['diary_category']) : 0;
$diary_author = isset($mpu_opt['diary_author']) ? intval($mpu_opt['diary_author']) : get_current_user_id();
$diary_trigger_rate = isset($mpu_opt['diary_trigger_rate']) ? intval($mpu_opt['diary_trigger_rate']) : 2;

// 日記專用 AI 供應商設定
$diary_provider = isset($mpu_opt['diary_provider']) ? $mpu_opt['diary_provider'] : 'gemini';

// 檢查 API Key 是否存在
$diary_gemini_key_exists = !empty($mpu_opt['diary_gemini_api_key']);
$diary_openai_key_exists = !empty($mpu_opt['diary_openai_api_key']);
$diary_claude_key_exists = !empty($mpu_opt['diary_claude_api_key']);

// 獲取模型設定
$diary_gemini_model = isset($mpu_opt['diary_gemini_model']) ? $mpu_opt['diary_gemini_model'] : 'gemini-2.5-flash';
$diary_openai_model = isset($mpu_opt['diary_openai_model']) ? $mpu_opt['diary_openai_model'] : 'gpt-4.1-mini-2025-04-14';
$diary_claude_model = isset($mpu_opt['diary_claude_model']) ? $mpu_opt['diary_claude_model'] : 'claude-sonnet-4-5-20250929';

// Ollama 設定（可複用主設定或獨立設定）
$diary_ollama_endpoint = isset($mpu_opt['diary_ollama_endpoint']) ? $mpu_opt['diary_ollama_endpoint'] : (isset($mpu_opt['ollama_endpoint']) ? $mpu_opt['ollama_endpoint'] : 'http://localhost:11434');
$diary_ollama_model = isset($mpu_opt['diary_ollama_model']) ? $mpu_opt['diary_ollama_model'] : (isset($mpu_opt['ollama_model']) ? $mpu_opt['ollama_model'] : 'qwen3:8b');

// 文章簽名
$diary_signature = isset($mpu_opt['diary_signature']) ? $mpu_opt['diary_signature'] : '*このエントリは、千年以上生きた魔法使のフリーレンが書きました。';
?>

<style>
    /* 複用 LLM 設定頁面樣式 */
    .mpu-diary-card {
        background: #E8F4F8;
        border: 1px solid #B8E6E6;
        border-radius: 10px;
        padding: 20px 24px;
        margin: 20px 0;
        box-shadow: 0 2px 8px rgba(168, 216, 234, 0.15);
    }

    .mpu-diary-card h4 {
        color: #4A9EBD;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #A8D8EA;
    }

    .mpu-diary-card .mpu-field-group {
        margin-bottom: 16px;
    }

    .mpu-diary-card .mpu-field-group:last-child {
        margin-bottom: 0;
    }

    .mpu-diary-card label {
        color: #2C3E50;
        font-weight: 500;
    }

    .mpu-diary-card small {
        color: #5A7A8C;
        display: block;
        margin-top: 4px;
    }

    .mpu-diary-card code {
        background: #D4E8F0;
        color: #2C3E50;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: "Courier New", Consolas, monospace;
        font-size: 12px;
        border: 1px solid #B8E6E6;
    }

    /* 提供商選項卡樣式 */
    .mpu-diary-provider-tabs {
        display: flex;
        gap: 8px;
        margin: 16px 0;
        flex-wrap: wrap;
    }

    .mpu-diary-provider-tab {
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

    .mpu-diary-provider-tab:hover {
        background: #C5E3F6;
        border-color: #A8D8EA;
    }

    .mpu-diary-provider-tab.active {
        background: linear-gradient(135deg, #4A9EBD 0%, #5FB3A1 100%);
        color: white;
        border-color: #4A9EBD;
        box-shadow: 0 2px 4px rgba(74, 158, 189, 0.2);
    }

    .mpu-diary-provider-content {
        display: none;
    }

    .mpu-diary-provider-content.active {
        display: block;
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

    /* 機率滑桿樣式 */
    .mpu-range-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mpu-range-container input[type="range"] {
        width: 200px;
        accent-color: #4A9EBD;
    }

    .mpu-range-value {
        min-width: 50px;
        font-weight: 600;
        color: #4A9EBD;
    }
</style>

<div>
    <h3><?php _e('📓 日記設定', 'mp-ukagaka'); ?></h3>
    <p style="color: #5A7A8C; margin-bottom: 20px;">
        <small><?php _e('設定自動日記功能（フリーレン手記）。角色會以極低機率自動發表日記文章。', 'mp-ukagaka'); ?></small>
    </p>
    <form method="post" name="diary_setting" id="diary_setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=7'); ?>">
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>

        <!-- 基本設定 -->
        <div class="mpu-diary-card">
            <h4><?php _e('📝 基本設定', 'mp-ukagaka'); ?></h4>
            <div class="mpu-field-group">
                <label>
                    <input type="checkbox" id="diary_enabled" name="diary_enabled" value="1" <?php echo $diary_enabled ? 'checked="checked"' : ''; ?> />
                    <?php _e('啟用自動日記功能', 'mp-ukagaka'); ?>
                </label>
                <br />
                <small><?php _e('啟用後，角色會以極低機率（由下方設定）自動發表日記文章到指定分類。', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label for="diary_category"><?php _e('發文分類：', 'mp-ukagaka'); ?></label>
                <?php
                wp_dropdown_categories(array(
                    'name' => 'diary_category',
                    'id' => 'diary_category',
                    'selected' => $diary_category,
                    'show_option_none' => __('-- 請選擇分類 --', 'mp-ukagaka'),
                    'option_none_value' => 0,
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'class' => '',
                ));
                ?>
                <br />
                <small><?php _e('建議先建立「フリーレン手記」分類（slug: frieren-notes）再選擇。', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label for="diary_author"><?php _e('發文作者：', 'mp-ukagaka'); ?></label>
                <?php
                wp_dropdown_users(array(
                    'name' => 'diary_author',
                    'id' => 'diary_author',
                    'selected' => $diary_author,
                    'who' => 'authors',
                ));
                ?>
                <br />
                <small><?php _e('日記將以此使用者身份發表。', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label for="diary_trigger_rate"><?php _e('觸發機率：', 'mp-ukagaka'); ?></label>
                <div class="mpu-range-container">
                    <input type="range" id="diary_trigger_rate" name="diary_trigger_rate" min="1" max="10" value="<?php echo esc_attr($diary_trigger_rate); ?>" />
                    <span class="mpu-range-value" id="diary_trigger_rate_display"><?php echo $diary_trigger_rate; ?>%</span>
                </div>
                <small><?php _e('每日觸發機率。2% 約等於每月 0~1 篇，5% 約等於每月 1~2 篇。', 'mp-ukagaka'); ?></small>
            </div>

            <div class="mpu-field-group">
                <label for="diary_signature"><?php _e('文章簽名：', 'mp-ukagaka'); ?></label>
                <input type="text" id="diary_signature" name="diary_signature" value="<?php echo esc_attr($diary_signature); ?>" style="width: 100%; max-width: 500px;" />
                <br />
                <small><?php _e('將附加於每篇日記的末尾。留空則不附加。', 'mp-ukagaka'); ?></small>
            </div>
        </div>

        <!-- 日記專用 AI 供應商 -->
        <div class="mpu-diary-card">
            <h4><?php _e('🤖 日記 AI 供應商', 'mp-ukagaka'); ?></h4>
            <small style="display: block; margin-bottom: 16px; color: #5A7A8C;">
                <?php _e('日記功能使用獨立的 AI 設定，可與對話功能使用不同的模型以節省成本。', 'mp-ukagaka'); ?>
            </small>

            <div class="mpu-diary-provider-tabs">
                <div class="mpu-diary-provider-tab <?php echo $diary_provider === 'gemini' ? 'active' : ''; ?>" data-provider="gemini">
                    ✨ Gemini
                </div>
                <div class="mpu-diary-provider-tab <?php echo $diary_provider === 'openai' ? 'active' : ''; ?>" data-provider="openai">
                    🧠 OpenAI
                </div>
                <div class="mpu-diary-provider-tab <?php echo $diary_provider === 'claude' ? 'active' : ''; ?>" data-provider="claude">
                    🎯 Claude
                </div>
                <div class="mpu-diary-provider-tab <?php echo $diary_provider === 'ollama' ? 'active' : ''; ?>" data-provider="ollama">
                    🖥️ Ollama
                </div>
            </div>

            <input type="hidden" id="diary_provider" name="diary_provider" value="<?php echo esc_attr($diary_provider); ?>" />

            <!-- Gemini 設定 -->
            <div class="mpu-diary-provider-content <?php echo $diary_provider === 'gemini' ? 'active' : ''; ?>" data-provider="gemini">
                <div class="mpu-field-group">
                    <label for="diary_gemini_api_key"><?php _e('Gemini API Key：', 'mp-ukagaka'); ?></label>
                    <input type="password" id="diary_gemini_api_key" name="diary_gemini_api_key" value="" placeholder="<?php echo $diary_gemini_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 API Key（或留空使用 LLM 設定頁的 Key）', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="off" />
                    <br />
                    <small><?php _e('留空則使用 LLM 設定頁面的 API Key。', 'mp-ukagaka'); ?> <?php if ($diary_gemini_key_exists) {
                        echo '<span class="mpu-key-set">✓ ' . __('已設定專用 Key', 'mp-ukagaka') . '</span>';
                    } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="diary_gemini_model"><?php _e('Gemini 模型：', 'mp-ukagaka'); ?></label>
                    <select id="diary_gemini_model" name="diary_gemini_model" style="width: 100%; max-width: 400px;">
                        <option value="gemini-2.5-flash" <?php echo $diary_gemini_model === 'gemini-2.5-flash' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Gemini 2.5 Flash (推薦)', 'mp-ukagaka')); ?></option>
                        <option value="gemini-2.5-pro" <?php echo $diary_gemini_model === 'gemini-2.5-pro' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Gemini 2.5 Pro (更聰明，適合複雜推理)', 'mp-ukagaka')); ?></option>
                    </select>
                </div>
            </div>

            <!-- OpenAI 設定 -->
            <div class="mpu-diary-provider-content <?php echo $diary_provider === 'openai' ? 'active' : ''; ?>" data-provider="openai">
                <div class="mpu-field-group">
                    <label for="diary_openai_api_key"><?php _e('OpenAI API Key：', 'mp-ukagaka'); ?></label>
                    <input type="password" id="diary_openai_api_key" name="diary_openai_api_key" value="" placeholder="<?php echo $diary_openai_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 API Key（或留空使用 LLM 設定頁的 Key）', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="off" />
                    <br />
                    <small><?php _e('留空則使用 LLM 設定頁面的 API Key。', 'mp-ukagaka'); ?> <?php if ($diary_openai_key_exists) {
                        echo '<span class="mpu-key-set">✓ ' . __('已設定專用 Key', 'mp-ukagaka') . '</span>';
                    } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="diary_openai_model"><?php _e('OpenAI 模型：', 'mp-ukagaka'); ?></label>
                    <select id="diary_openai_model" name="diary_openai_model" style="width: 100%; max-width: 400px;">
                        <option value="gpt-4.1-mini-2025-04-14" <?php echo $diary_openai_model === 'gpt-4.1-mini-2025-04-14' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4.1 Mini (推薦，速度快成本低)', 'mp-ukagaka')); ?></option>
                        <option value="gpt-4o-mini" <?php echo $diary_openai_model === 'gpt-4o-mini' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4o Mini (快速且經濟)', 'mp-ukagaka')); ?></option>
                        <option value="gpt-4o" <?php echo $diary_openai_model === 'gpt-4o' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('GPT-4o (更聰明)', 'mp-ukagaka')); ?></option>
                    </select>
                </div>
            </div>

            <!-- Claude 設定 -->
            <div class="mpu-diary-provider-content <?php echo $diary_provider === 'claude' ? 'active' : ''; ?>" data-provider="claude">
                <div class="mpu-field-group">
                    <label for="diary_claude_api_key"><?php _e('Claude API Key：', 'mp-ukagaka'); ?></label>
                    <input type="password" id="diary_claude_api_key" name="diary_claude_api_key" value="" placeholder="<?php echo $diary_claude_key_exists ? __('(已隱藏以確保安全)', 'mp-ukagaka') : __('請輸入 API Key（或留空使用 LLM 設定頁的 Key）', 'mp-ukagaka'); ?>" style="width: 100%; max-width: 400px;" autocomplete="off" />
                    <br />
                    <small><?php _e('留空則使用 LLM 設定頁面的 API Key。', 'mp-ukagaka'); ?> <?php if ($diary_claude_key_exists) {
                        echo '<span class="mpu-key-set">✓ ' . __('已設定專用 Key', 'mp-ukagaka') . '</span>';
                    } ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="diary_claude_model"><?php _e('Claude 模型：', 'mp-ukagaka'); ?></label>
                    <select id="diary_claude_model" name="diary_claude_model" style="width: 100%; max-width: 400px;">
                        <option value="claude-sonnet-4-5-20250929" <?php echo $diary_claude_model === 'claude-sonnet-4-5-20250929' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Sonnet 4.5 (推薦)', 'mp-ukagaka')); ?></option>
                        <option value="claude-haiku-4-5-20251001" <?php echo $diary_claude_model === 'claude-haiku-4-5-20251001' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Haiku 4.5 (快速)', 'mp-ukagaka')); ?></option>
                        <option value="claude-opus-4-5-20251101" <?php echo $diary_claude_model === 'claude-opus-4-5-20251101' ? 'selected="selected"' : ''; ?>><?php echo esc_html(__('Claude Opus 4.5 (進階)', 'mp-ukagaka')); ?></option>
                    </select>
                </div>
            </div>

            <!-- Ollama 設定 -->
            <div class="mpu-diary-provider-content <?php echo $diary_provider === 'ollama' ? 'active' : ''; ?>" data-provider="ollama">
                <div class="mpu-field-group">
                    <label for="diary_ollama_endpoint"><?php _e('Ollama 端點：', 'mp-ukagaka'); ?></label>
                    <input type="text" id="diary_ollama_endpoint" name="diary_ollama_endpoint" value="<?php echo esc_attr($diary_ollama_endpoint); ?>" style="width: 100%; max-width: 400px;" placeholder="http://localhost:11434" />
                    <br />
                    <small><?php _e('留空則使用 LLM 設定頁面的端點。', 'mp-ukagaka'); ?></small>
                </div>
                <div class="mpu-field-group">
                    <label for="diary_ollama_model"><?php _e('模型名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" id="diary_ollama_model" name="diary_ollama_model" value="<?php echo esc_attr($diary_ollama_model); ?>" style="width: 100%; max-width: 300px;" placeholder="qwen2.5:12b" />
                </div>
            </div>
        </div>

        <!-- 測試按鈕 -->
        <div class="mpu-diary-card">
            <h4><?php _e('🧪 測試', 'mp-ukagaka'); ?></h4>
            <div class="mpu-test-row">
                <button type="button" id="test_diary_generate" class="button"><?php _e('立即生成一篇日記（測試）', 'mp-ukagaka'); ?></button>
                <span id="diary_test_result"></span>
            </div>
            <small><?php _e('點擊後會使用上方設定立即生成並發表一篇日記，用於測試功能是否正常。', 'mp-ukagaka'); ?></small>
        </div>

        <p><input name="submit_diary" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
    </form>
</div>

<script>
    (function($) {
        'use strict';

        $(document).ready(function() {
            // 提供商選項卡切換
            $('.mpu-diary-provider-tab').on('click', function() {
                var provider = $(this).data('provider');

                // 更新選項卡狀態
                $('.mpu-diary-provider-tab').removeClass('active');
                $(this).addClass('active');

                // 更新隱藏欄位
                $('#diary_provider').val(provider);

                // 更新內容顯示
                $('.mpu-diary-provider-content').removeClass('active');
                $('.mpu-diary-provider-content[data-provider="' + provider + '"]').addClass('active');
            });

            // 機率滑桿數值顯示
            $('#diary_trigger_rate').on('input', function() {
                $('#diary_trigger_rate_display').text($(this).val() + '%');
            });

            // 測試生成日記
            $('#test_diary_generate').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $btn = $(this);
                $btn.prop('disabled', true);
                $('#diary_test_result').html('<span class="mpu-loading"></span><?php _e("生成中...", "mp-ukagaka"); ?>');

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'mpu_test_diary_generate',
                        nonce: '<?php echo wp_create_nonce("mpu_test_diary"); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $('#diary_test_result').html('<span class="mpu-test-success">✓ ' + response.data + '</span>');
                        } else {
                            $('#diary_test_result').html('<span class="mpu-test-error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $('#diary_test_result').html('<span class="mpu-test-error">✗ <?php _e("測試失敗", "mp-ukagaka"); ?> (' + error + ')</span>');
                    }
                });

                return false;
            });
        });
    })(jQuery);
</script>
