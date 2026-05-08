<div>
    <h3><?php _e('✨ 創建新偽春菜', 'mp-ukagaka'); ?></h3>
    
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

    // 覆蓋確認模式
    $overwrite_pending = (isset($_GET['overwrite_pending']) && $_GET['overwrite_pending'] === '1');
    $overwrite_data = null;
    if ($overwrite_pending) {
        $pending = get_transient('mpu_ghost_overwrite_' . get_current_user_id());
        $overwrite_data = ($pending && !empty($pending['ghost_id'])) ? $pending : null;
    }
    ?>
    
    <?php if ($overwrite_data): ?>
        <!-- 覆蓋確認模式 -->
        <div class="notice notice-warning" style="margin-bottom:20px;">
            <p><strong><?php _e('⚠️ 既存の Personality を上書きしようとしています。', 'mp-ukagaka'); ?></strong></p>
            <p><?php printf(
                __('ID <code>%s</code> の Personality ディレクトリがすでに存在します。上書きすると、既存のファイルがすべて置き換えられます。', 'mp-ukagaka'),
                esc_html($overwrite_data['ghost_id'])
            ); ?></p>
        </div>
        <form method="post" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>">
            <?php wp_nonce_field('mp_ukagaka_settings'); ?>
            <p>
                <input name="submit_confirm_ghost_overwrite" class="button button-primary" value="<?php _e('上書きを確認して続行', 'mp-ukagaka'); ?>" type="submit" />
                <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=2'); ?>" class="button" style="margin-left:10px;"><?php _e('キャンセル', 'mp-ukagaka'); ?></a>
            </p>
        </form>
    <?php elseif ($preview_data === null): ?>
        <!-- ZIP 上傳模式 -->
        <p style="color: #8A7FA0; margin-bottom: 20px;">
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
        
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #DED6EE;" />
        
        <p style="color: #8A7FA0; margin-bottom: 20px;">
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
            <div class="mpu-settings-card" style="border: 2px solid #7B68AE; background: #F7F4FC;">
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