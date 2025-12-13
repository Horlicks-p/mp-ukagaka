  <div>
      <!-- 標題：通用設定 -->
      <h3><?php _e('通用設定', 'mp-ukagaka'); ?></h3>

      <!-- 表單：用於提交通用設定的表單 -->
      <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=0'); ?>">
          <?php wp_nonce_field('mp_ukagaka_settings'); ?>
          <!-- 選項：選擇預設春菜 -->
          <p>
              <label for="cur_ukagaka"><?php _e('預設春菜：', 'mp-ukagaka'); ?></label>
              <select id="cur_ukagaka" name="cur_ukagaka">
                  <?php foreach ($mpu_opt['ukagakas'] as $key => $value) { ?>
                      <option value="<?php echo $key; ?>" <?php if ($key == $mpu_opt['cur_ukagaka']) {
                                                                echo ' selected="selected"';
                                                            } ?>><?php echo mpu_output_filter($value['name']); ?></option>
                  <?php } ?>
              </select>
          </p>

          <!-- 選項：是否預設顯示春菜 -->
          <p><label for="show_ukagaka"><input id="show_ukagaka" name="show_ukagaka" type="checkbox" value="true" <?php if ($mpu_opt['show_ukagaka']) {
                                                                                                                        echo ' checked="checked"';
                                                                                                                    } ?> /><?php _e('預設顯示春菜', 'mp-ukagaka'); ?></label></p>

          <!-- 選項：是否預設顯示對話框 -->
          <p><label for="show_msg"><input id="show_msg" name="show_msg" type="checkbox" value="true" <?php if ($mpu_opt['show_msg']) {
                                                                                                            echo ' checked="checked"';
                                                                                                        } ?> /><?php _e('預設顯示對話框', 'mp-ukagaka'); ?></label></p>

          <!-- 選項：預設會話模式 -->
          <p>
              <?php _e('預設會話：', 'mp-ukagaka'); ?>
              <label><input name="default_msg[]" type="radio" value="0" <?php if ($mpu_opt['default_msg'] == 0) {
                                                                            echo ' checked="checked"';
                                                                        } ?> /><?php _e('隨機吐槽', 'mp-ukagaka'); ?></label>
              <label><input name="default_msg[]" type="radio" value="1" <?php if ($mpu_opt['default_msg'] == 1) {
                                                                            echo ' checked="checked"';
                                                                        } ?> /><?php _e('第一條吐槽', 'mp-ukagaka'); ?></label>
          </p>

          <!-- 選項：會話順序模式 -->
          <p>
              <?php _e('會話順序：', 'mp-ukagaka'); ?>
              <label><input name="next_msg[]" type="radio" value="0" <?php if ($mpu_opt['next_msg'] == 0) {
                                                                            echo ' checked="checked"';
                                                                        } ?> /><?php _e('順序吐槽', 'mp-ukagaka'); ?></label>
              <label><input name="next_msg[]" type="radio" value="1" <?php if ($mpu_opt['next_msg'] == 1) {
                                                                            echo ' checked="checked"';
                                                                        } ?> /><?php _e('隨機吐槽', 'mp-ukagaka'); ?></label>
          </p>

          <!-- 選項：點擊春菜的行為 -->
          <p>
              <?php _e('點擊春菜：', 'mp-ukagaka'); ?>

              <label><input name="click_ukagaka[]" type="radio" value="0" <?php if ($mpu_opt['click_ukagaka'] == 0) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('下一條吐槽', 'mp-ukagaka'); ?></label>
              <label><input name="click_ukagaka[]" type="radio" value="1" <?php if ($mpu_opt['click_ukagaka'] == 1) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('無操作', 'mp-ukagaka'); ?></label>
              <!-- 選項：自動對話功能 -->
          <p>
              <?php _e('自動對話：', 'mp-ukagaka'); ?>
              <label><input name="auto_talk" type="checkbox" value="true" <?php if (isset($mpu_opt['auto_talk']) && $mpu_opt['auto_talk']) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('啟用自動對話功能', 'mp-ukagaka'); ?></label>
          </p>

          <!-- 選項：自動對話間隔時間 -->
          <p>
              <label><?php _e('自動對話間隔時間（秒）：', 'mp-ukagaka'); ?><input type="number" name="auto_talk_interval" value="<?php echo isset($mpu_opt['auto_talk_interval']) ? intval($mpu_opt['auto_talk_interval']) : 8; ?>" min="3" max="30" style="width: 80px;" /></label>
          </p>

          <!-- 選項：打字效果速度 -->
          <p>
              <label><?php _e('打字效果速度（毫秒/字）：', 'mp-ukagaka'); ?><input type="number" name="typewriter_speed" value="<?php echo isset($mpu_opt['typewriter_speed']) ? intval($mpu_opt['typewriter_speed']) : 40; ?>" min="10" max="200" style="width: 80px;" /></label>
              <br /><small><?php _e('數值越小打字越快，建議範圍 20-80，預設 40', 'mp-ukagaka'); ?></small>
          </p>
          </p>
          <!-- 選項：外部對話文件格式 -->
          <p>
              <?php _e('外部對話文件格式：', 'mp-ukagaka'); ?>
              <label><input name="external_file_format[]" type="radio" value="txt" <?php if (!isset($mpu_opt['external_file_format']) || $mpu_opt['external_file_format'] == 'txt') {
                                                                                        echo ' checked="checked"';
                                                                                    } ?> /><?php _e('純文字格式 (TXT)', 'mp-ukagaka'); ?></label>
              <label><input name="external_file_format[]" type="radio" value="json" <?php if (isset($mpu_opt['external_file_format']) && $mpu_opt['external_file_format'] == 'json') {
                                                                                        echo ' checked="checked"';
                                                                                    } ?> /><?php _e('JSON 格式', 'mp-ukagaka'); ?></label>
              <br /><small><?php _e('注意：系統已固定使用外部對話文件，對話將從 dialogs/ 資料夾讀取。', 'mp-ukagaka'); ?></small>
          </p>

          <!-- 選項：是否使用自訂樣式 -->
          <p><label><input type="checkbox" id="no_style" name="no_style" value="true" <?php if ($mpu_opt['no_style']) {
                                                                                            echo ' checked="checked"';
                                                                                        } ?> /><?php _e('使用自訂樣式', 'mp-ukagaka'); ?></label></p>

          <!-- 可選高級設定：HTML 生成位置（僅在特定模式下顯示） -->
          <?php if (isset($_GET['mpu_mode'])) { ?>
              <div style="height:1px;background:#DFDFDF;width:400px;"></div>
              <p>
                  <?php _e('HTML 生成位置：', 'mp-ukagaka'); ?> (<?php _e('一般情況下請勿更改', 'mp-ukagaka'); ?>)
                  <br />
                  <label><input type="radio" name="insert_html[]" value="0" <?php if ($mpu_opt['insert_html'] == 0) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('在</body>前', 'mp-ukagaka'); ?></label> <?php _e('當春菜無法顯示（主題尾部無 wp_footer() 函數）或 wp_footer() 位置導致樣式異常時，請勾選此項', 'mp-ukagaka'); ?>
                  <br />
                  <label><input type="radio" name="insert_html[]" value="1" <?php if ($mpu_opt['insert_html'] == 1) {
                                                                                echo ' checked="checked"';
                                                                            } ?> /><?php _e('在 wp_footer() 處', 'mp-ukagaka'); ?></label> <?php _e('這是 WordPress 常用方式，但若主題尾部無 wp_footer() 函數，春菜將無法顯示', 'mp-ukagaka'); ?>
              </p>
          <?php } ?>

          <!-- 分隔線 -->
          <div style="height:1px;background:#DFDFDF;width:400px;"></div>

          <!-- 選項：不顯示春菜的頁面 -->
          <p>
              <label for="no_page"><?php _e('不在以下頁面顯示春菜', 'mp-ukagaka'); ?></label><br />
              <textarea cols="40" rows="3" id="no_page" name="no_page" class="resizable" style="line-height:130%;"><?php echo $mpu_opt['no_page']; ?></textarea><br />
              <?php _e('輸入不顯示春菜的頁面 URL，每行一條。', 'mp-ukagaka'); ?><br />
              <?php _e('在地址尾部加入 (*) 可進行模糊匹配。', 'mp-ukagaka'); ?>
          </p>

          <!-- 提交按鈕：保存設定 -->
          <p><input name="submit1" class="button" value="<?php _e(' 儲 存 ', 'mp-ukagaka'); ?>" type="submit" /></p>
      </form>

      <!-- 分隔線 -->
      <div style="height:1px;background:#DFDFDF;width:550px;"></div>

      <h3><?php _e('插件資訊', 'mp-ukagaka'); ?></h3>
      <p><?php _e('版本：', 'mp-ukagaka'); ?> <?php echo MPU_VERSION; ?></p>
      <p><?php _e('春菜數量：', 'mp-ukagaka'); ?> <?php echo count($mpu_opt['ukagakas']); ?></p>
      <p><?php _e('平均會話數：', 'mp-ukagaka'); ?> <?php echo round(mpu_count_total_msg() / count($mpu_opt['ukagakas']), 1); ?></p>
      <p><?php _e('維護者網站：', 'mp-ukagaka'); ?> <a href="https://www.moelog.com/" title="MoeLog"><?php _e('前往維護者網站', 'mp-ukagaka'); ?></a></p>

      <!-- 分隔線 -->
      <div style="height:1px;background:#DFDFDF;width:550px;"></div>

      <!-- 表單：重置設定的表單 -->
      <form method="post" name="setting" id="setting" action="<?php echo admin_url('options-general.php?page=' . $base_name . '&cur_page=0'); ?>">
          <!-- 標題：重置設定 -->
          <h3><?php _e('重置設定', 'mp-ukagaka'); ?></h3>

          <!-- 選項：確認重置 -->
          <p><label><input id="reset_mpu" name="reset_mpu" type="checkbox" value="true" /><?php _e('確認重置', 'mp-ukagaka'); ?></label></p>

          <!-- 注意事項：重置警告 -->
          <p><?php _e('重置設定將還原插件的所有配置為預設值，所有設定及春菜將被刪除。此操作無法撤銷，請謹慎操作。', 'mp-ukagaka'); ?></p>

          <!-- 提交按鈕：執行重置 -->
          <p><input name="submit_reset" class="button" value="<?php _e(' 重 置 ', 'mp-ukagaka'); ?>" type="submit" /></p>
      </form>
  </div>