<?php if (!defined('ABSPATH')) { exit; } ?>
<p class="th-help"><?php echo esc_html(dttl_th_t(array('Mỗi dòng là một giao dịch mua rồi bán hết, không đòn bẩy. Đơn vị tiền: VNĐ. Dữ liệu chỉ lưu trong trình duyệt này, tách theo tài khoản đang dùng; chưa đồng bộ sang thiết bị khác. Xuất JSON để sao lưu và nhập lại khi cần.','Each row is one fully closed buy-then-sell trade without leverage. Currency: VND. Data is stored only in this browser, separately for the current account; it does not sync to other devices. Export JSON to back up and restore later.'))); ?></p>
<form data-journal-form>
<div class="th-fields">
<label><?php echo esc_html(dttl_th_t(array('Ngày đóng giao dịch','Closing date'))); ?><input type="date" name="date" required></label>
<label><?php echo esc_html(dttl_th_t(array('Tên hoặc mã tài sản','Asset name or symbol'))); ?><input type="text" name="asset" maxlength="80" required></label>
<?php foreach(array('qty'=>array('Số lượng','Quantity'),'buy'=>array('Giá mua / đơn vị (VNĐ)','Buy price / unit (VND)'),'sell'=>array('Giá bán / đơn vị (VNĐ)','Sell price / unit (VND)'),'fees'=>array('Tổng phí và thuế (VNĐ)','Total fees and taxes (VND)'),'income'=>array('Thu nhập khác sau thuế (VNĐ)','Other after-tax income (VND)')) as $name=>$label): ?>
<label><?php echo esc_html(dttl_th_t($label)); ?><input type="number" name="<?php echo esc_attr($name); ?>" min="<?php echo in_array($name,array('qty','buy'),true)?'0.00000001':'0'; ?>" max="1000000000000" step="any" value="<?php echo $name==='qty'?'1':'0'; ?>" required></label>
<?php endforeach; ?>
</div>
<label><?php echo esc_html(dttl_th_t(array('Lý do vào lệnh, rủi ro và điều kiện xem xét lại','Entry reason, risk and review conditions'))); ?><textarea name="reason" rows="3" maxlength="2000"></textarea></label>
<label><?php echo esc_html(dttl_th_t(array('Bài học sau giao dịch','Lessons after the trade'))); ?><textarea name="lesson" rows="3" maxlength="2000"></textarea></label>
<div class="th-actions"><button type="submit" data-journal-save><?php echo esc_html(dttl_th_t(array('Lưu giao dịch','Save trade'))); ?></button><button type="button" class="th-secondary" data-journal-new><?php echo esc_html(dttl_th_t(array('Nhập giao dịch mới','New trade'))); ?></button></div>
</form>
<p data-journal-status role="status" aria-live="polite"></p>
<div class="th-actions"><button type="button" class="th-secondary" data-journal-export><?php echo esc_html(dttl_th_t(array('Xuất nhật ký CSV','Export journal CSV'))); ?></button><button type="button" class="th-secondary" data-journal-backup><?php echo esc_html(dttl_th_t(array('Sao lưu JSON','Back up JSON'))); ?></button><button type="button" class="th-secondary" data-journal-undo hidden><?php echo esc_html(dttl_th_t(array('Hoàn tác xóa','Undo deletion'))); ?></button></div>
<label><?php echo esc_html(dttl_th_t(array('Nhập bản sao JSON (gộp, giữ dòng đã có)','Import JSON backup (merge, keep existing rows)'))); ?><input data-journal-import type="file" accept=".json,application/json"></label>
<div data-journal-summary class="th-result"></div><div data-journal-table class="th-table" tabindex="0"></div>
