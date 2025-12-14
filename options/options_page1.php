  <div>
      <!-- 標題：春菜們 -->
      <h3><?php _e('春菜們', 'mp-ukagaka'); ?></h3>

      <!-- 說明文字：圖片和吐槽欄的填寫規則 -->
      <p>
          <?php _e('圖片欄中，請填寫完整的 URL，不要忘記以 http:// 開頭。', 'mp-ukagaka'); ?>
          <br />
          <?php _e('吐槽欄中，每行代表一條吐槽。不可使用 HTML 代碼。', 'mp-ukagaka'); ?>
      </p>

      <!-- 表單：用於提交春菜的更改 -->
      <form method="post" name="ukagakas" id="ukagakas" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1'); ?>">
          <?php foreach ($mpu_opt['ukagakas'] as $key => $value) { ?>
              <?php wp_nonce_field('mp_ukagaka_settings'); ?>
              <!-- 分隔線 -->
              <div style="height:1px;background:#DFDFDF;width:400px;"></div>

              <!-- 春菜單個設定區塊 -->
              <div>
                  <!-- 春菜編號與刪除連結 -->
                  <p>
                      #<?php echo $key; ?>
                      <?php if ($key == str_replace('default', '', $key)) { ?>
                          / <a href="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=1&del=' . esc_attr($key)); ?>">[<?php _e('刪除', 'mp-ukagaka'); ?>]</a>
                      <?php } ?>
                  </p>

                  <!-- 選項：是否可顯示 -->
                  <p><label><input type="checkbox" name="ukagakas[<?php echo $key; ?>][show]" value="true" <?php if ($value['show']) {
                                                                                                                echo ' checked="checked"';
                                                                                                            } ?> /><?php _e('可顯示', 'mp-ukagaka'); ?></label></p>

                  <!-- 輸入欄：春菜名稱 -->
                  <p><label><?php _e('名稱：', 'mp-ukagaka'); ?><br /><input type="text" name="ukagakas[<?php echo $key; ?>][name]" value="<?php echo mpu_output_filter($value['name']); ?>" size="45" /></label></p>

                  <!-- 輸入欄：春菜圖片 URL -->
                  <p><label><?php _e('圖片：', 'mp-ukagaka'); ?><br /><input type="text" name="ukagakas[<?php echo $key; ?>][shell]" value="<?php echo mpu_output_filter($value['shell']); ?>" size="45" /></label></p>

                  <p><label><?php _e('吐槽：', 'mp-ukagaka'); ?><br /><textarea name="ukagakas[<?php echo $key; ?>][msg]" rows="3" cols="60" class="resizable" style="line-height:130%;"><?php echo esc_textarea(mpu_array2str($value['msg'])); ?></textarea></label></p>
                  <!-- 輸入欄：對話檔案名稱 -->
                  <p><label><?php _e('對話檔案名稱：', 'mp-ukagaka'); ?><br /><input type="text" name="ukagakas[<?php echo $key; ?>][dialog_filename]" value="<?php echo isset($value['dialog_filename']) ? mpu_output_filter($value['dialog_filename']) : $key; ?>" size="45" /></label><br />
                      <?php _e('此名稱將用於外部對話檔案，例如：asuna.txt 或 asuna.json', 'mp-ukagaka'); ?></p>
                  <!-- 新增：生成對話檔案的勾選框 -->
                  <p><label><input type="checkbox" name="generate_dialog_file[<?php echo $key; ?>]" value="true" /><?php _e('生成對話檔案', 'mp-ukagaka'); ?></label><br />
                      <?php _e('勾選此項將使用上方吐槽內容生成對應的對話檔案', 'mp-ukagaka'); ?></p>
              </div>
          <?php } ?>

          <!-- 提交按鈕：儲存更改 -->
          <p><input name="submit2" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
      </form>
  </div>