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

<div>
    <h3><?php _e('📓 日記設定', 'mp-ukagaka'); ?></h3>
    <p style="color: #8A7FA0; margin-bottom: 20px;">
        <small><?php _e('設定自動日記功能（フリーレン手記）。角色會以極低機率自動發表日記文章。', 'mp-ukagaka'); ?></small>
    </p>
    <!-- Cron 健康狀態 -->
    <?php
    $cron_running        = get_transient('mpu_cron_diary_running');
    $cron_last_run       = get_transient('mpu_cron_diary_last_run');
    $cron_next_scheduled = wp_next_scheduled('mpu_daily_diary_check');
    ?>
    <div class="mpu-diary-card">
        <h4><?php _e('📊 Cron 執行狀態', 'mp-ukagaka'); ?></h4>
        <?php if ($cron_running) : ?>
            <p><span style="color:#f0ad4e;">🔄 <?php _e('Cron 目前正在執行中…', 'mp-ukagaka'); ?></span></p>
        <?php elseif ($cron_last_run) : ?>
            <?php
            $run_time = wp_date('Y-m-d H:i:s', $cron_last_run['time']);
            switch ($cron_last_run['status']) {
                case 'ok':
                    $status_html = '<span style="color:#46b450;">✅ ' . esc_html__('成功', 'mp-ukagaka') . '</span>';
                    if (!empty($cron_last_run['post_id'])) {
                        $post_url     = get_permalink((int) $cron_last_run['post_id']);
                        $status_html .= ' — <a href="' . esc_url($post_url) . '" target="_blank">'
                            . sprintf(esc_html__('文章 #%d', 'mp-ukagaka'), (int) $cron_last_run['post_id'])
                            . '</a>';
                    }
                    break;
                case 'error':
                    $status_html = '<span style="color:#dc3232;">❌ ' . esc_html__('失敗', 'mp-ukagaka') . '</span>';
                    if (!empty($cron_last_run['error'])) {
                        $status_html .= ' — <code>' . esc_html($cron_last_run['error']) . '</code>';
                    }
                    break;
                case 'skipped':
                    $status_html = '<span style="color:#8A7FA0;">⏭️ ' . esc_html__('跳過（本次機率未觸發）', 'mp-ukagaka') . '</span>';
                    break;
                default:
                    $status_html = '<span>' . esc_html($cron_last_run['status']) . '</span>';
            }
            ?>
            <table class="widefat" style="max-width:500px;">
                <tr>
                    <th style="width:120px;"><?php _e('上次執行', 'mp-ukagaka'); ?></th>
                    <td><?php echo esc_html($run_time); ?></td>
                </tr>
                <tr>
                    <th><?php _e('狀態', 'mp-ukagaka'); ?></th>
                    <td><?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                </tr>
            </table>
        <?php else : ?>
            <p style="color:#8A7FA0;"><em><?php _e('尚無執行記錄。', 'mp-ukagaka'); ?></em></p>
        <?php endif; ?>
        <p style="margin-top:12px;">
            <?php if ($cron_next_scheduled) : ?>
                <?php printf(
                    /* translators: %s = datetime */
                    esc_html__('下次排程：%s', 'mp-ukagaka'),
                    '<strong>' . esc_html(wp_date('Y-m-d H:i:s', $cron_next_scheduled)) . '</strong>'
                ); ?>
            <?php else : ?>
                <span style="color:#dc3232;"><?php _e('⚠️ 排程事件未註冊，請停用後重新啟用插件。', 'mp-ukagaka'); ?></span>
            <?php endif; ?>
        </p>
    </div>

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
            <small style="display: block; margin-bottom: 16px; color: #8A7FA0;">
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
