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
    <h3><?php _e('創建新偽春菜', 'mp-ukagaka'); ?></h3>
    
    <?php
    // 檢查是否有 ZIP 上傳的預覽數據
    $preview_data = null;
    $transient_key = 'mpu_ghost_zip_preview_' . get_current_user_id();
    if (isset($_GET['preview']) && $_GET['preview'] === '1') {
        $preview_data = get_transient($transient_key);
        if ($preview_data === false) {
            $preview_data = null;
        }
    }
    ?>
    
    <?php if ($preview_data === null): ?>
        <!-- ZIP 上傳模式 -->
        <p style="color: #5A7A8C; margin-bottom: 20px;">
            <small><?php _e('上傳包含 manifest.json、shell/ 等文件的 ZIP 壓縮包，系統會自動解壓並驗證。', 'mp-ukagaka'); ?></small>
        </p>
        <form method="post" name="upload_zip" id="upload_zip" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('mp_ukagaka_settings'); ?>
            
            <div class="mpu-settings-card">
                <h4><?php _e('📦 ZIP 文件上傳', 'mp-ukagaka'); ?></h4>
                
                <div class="mpu-field-group">
                    <label><?php _e('選擇 ZIP 文件：', 'mp-ukagaka'); ?></label>
                    <input type="file" name="ghost_zip_file" accept=".zip" style="width: 100%; max-width: 500px;" />
                    <small><?php _e('ZIP 文件應包含：manifest.json（必需）、shell/ 文件夾（必需，包含圖片文件）、prompts.json、weights.json 等（可選）', 'mp-ukagaka'); ?></small>
                </div>
            </div>
            
            <p><input name="submit_upload_zip" class="button button-primary" value="<?php _e('上傳並驗證', 'mp-ukagaka'); ?>" type="submit" /></p>
        </form>
        
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #B8E6E6;" />
        
        <p style="color: #5A7A8C; margin-bottom: 20px;">
            <small>
                <?php _e('或使用傳統方式創建（僅支援內建對話系統，無法使用 LLM 對話功能）：圖片請填寫完整的 URL，不要忘記以 http:// 或 https:// 開頭。', 'mp-ukagaka'); ?>
            </small>
        </p>
    <?php else: ?>
        <!-- 預覽確認模式 -->
        <div class="updated" style="margin-bottom: 20px;">
            <p><strong><?php _e('✅ ZIP 文件上傳成功！請確認以下資訊：', 'mp-ukagaka'); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <form method="post" name="create" id="create" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>"<?php echo ($preview_data === null) ? '' : ' enctype="multipart/form-data"'; ?>>
        <?php wp_nonce_field('mp_ukagaka_settings'); ?>
        
        <?php if ($preview_data !== null): ?>
            <!-- 預覽區域 -->
            <div class="mpu-settings-card" style="border: 2px solid #4A9EBD; background: #F0F9FC;">
                <h4><?php _e('📋 預覽資訊', 'mp-ukagaka'); ?></h4>
                
                <div class="mpu-field-group">
                    <label><strong><?php _e('角色名稱：', 'mp-ukagaka'); ?></strong></label>
                    <p style="margin: 5px 0; padding: 8px; background: white; border-radius: 4px;"><?php echo esc_html($preview_data['name'] ?? ''); ?></p>
                </div>
                
                <div class="mpu-field-group">
                    <label><strong><?php _e('角色 ID：', 'mp-ukagaka'); ?></strong></label>
                    <p style="margin: 5px 0; padding: 8px; background: white; border-radius: 4px;"><?php echo esc_html($preview_data['id'] ?? ''); ?></p>
                </div>
                
                <div class="mpu-field-group">
                    <label><strong><?php _e('Shell 路徑：', 'mp-ukagaka'); ?></strong></label>
                    <p style="margin: 5px 0; padding: 8px; background: white; border-radius: 4px; word-break: break-all;"><?php echo esc_html($preview_data['shell_url'] ?? ''); ?></p>
                </div>
                
                <?php if (!empty($preview_data['version'])): ?>
                <div class="mpu-field-group">
                    <label><strong><?php _e('版本：', 'mp-ukagaka'); ?></strong></label>
                    <p style="margin: 5px 0; padding: 8px; background: white; border-radius: 4px;"><?php echo esc_html($preview_data['version']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($preview_data['author'])): ?>
                <div class="mpu-field-group">
                    <label><strong><?php _e('作者：', 'mp-ukagaka'); ?></strong></label>
                    <p style="margin: 5px 0; padding: 8px; background: white; border-radius: 4px;"><?php echo esc_html($preview_data['author']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($preview_data['description'])): ?>
                <div class="mpu-field-group">
                    <label><strong><?php _e('描述：', 'mp-ukagaka'); ?></strong></label>
                    <p style="margin: 5px 0; padding: 8px; background: white; border-radius: 4px;"><?php echo esc_html($preview_data['description']); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- 隱藏字段存儲預覽數據 -->
                <input type="hidden" name="ghost_preview_id" value="<?php echo esc_attr($preview_data['id'] ?? ''); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($preview_data !== null): ?>
            <!-- 確認創建按鈕 -->
            <p>
                <input name="submit3" class="button button-primary" value="<?php _e('確定創建', 'mp-ukagaka'); ?>" type="submit" />
                <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>" class="button" style="margin-left: 10px;"><?php _e('取消', 'mp-ukagaka'); ?></a>
            </p>
        <?php else: ?>
            <!-- 傳統手動創建表單 -->
            <div class="mpu-settings-card">
                <h4><?php _e('➕ 新增偽春菜（手動方式）', 'mp-ukagaka'); ?></h4>
                
                <div style="background: #FFF3CD; border: 1px solid #FFC107; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                        <strong><?php _e('⚠️ 重要提示：', 'mp-ukagaka'); ?></strong><br/>
                        <?php _e('傳統手動方式創建的偽春菜只能使用內建對話系統（從對話檔案讀取），無法使用 LLM 對話功能。', 'mp-ukagaka'); ?><br/>
                        <?php _e('如需使用 LLM 對話功能（AI 生成對話），請使用上方的 ZIP 上傳方式創建，並確保 ZIP 文件包含 manifest.json、prompts.json、weights.json 等必要文件。', 'mp-ukagaka'); ?>
                    </p>
                </div>

                <div class="mpu-field-group">
                    <label><input type="checkbox" name="ukagaka[show]" value="true" /><?php _e('可顯示', 'mp-ukagaka'); ?></label>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagaka[name]" value="" style="width: 100%; max-width: 400px;" />
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('圖片：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagaka[shell]" value="http://" style="width: 100%; max-width: 500px;" />
                    <small><?php _e('請填寫完整的 URL，不要忘記以 http:// 或 https:// 開頭', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('吐槽：', 'mp-ukagaka'); ?></label>
                    <textarea name="ukagaka[msg]" rows="5" cols="60" class="resizable" style="line-height:130%; width: 100%; max-width: 850px;"></textarea>
                    <small><?php _e('每行一條吐槽，不可使用 HTML 代碼。', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><?php _e('對話檔案名稱：', 'mp-ukagaka'); ?></label>
                    <input type="text" name="ukagaka[dialog_filename]" value="" style="width: 100%; max-width: 300px;" />
                    <small><?php _e('此名稱將用於外部對話檔案，例如：asuna.txt 或 asuna.json', 'mp-ukagaka'); ?></small>
                </div>

                <div class="mpu-field-group">
                    <label><input type="checkbox" name="generate_dialog_file_new" value="true" /><?php _e('生成對話檔案', 'mp-ukagaka'); ?></label>
                    <small><?php _e('勾選此項將使用上方吐槽內容生成對應的對話檔案', 'mp-ukagaka'); ?></small>
                </div>
            </div>

            <p><input name="submit3" class="button" value="<?php _e(' 創 建 ', 'mp-ukagaka'); ?>" type="submit" /></p>
        <?php endif; ?>
    </form>
</div>