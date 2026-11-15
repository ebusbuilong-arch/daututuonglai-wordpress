<?php if (!defined('ABSPATH')) { exit; } ?>
<p><?php echo esc_html(dttl_th_t(array('Bản kiểm tra giúp bạn tự rà soát, không chấm điểm mức an toàn hoặc khuyến nghị giao dịch. Chỉ lưu trên trình duyệt này, tách theo tài khoản; chưa đồng bộ thiết bị.','This review supports reflection; it does not score safety or recommend a trade. Saved only in this browser, separately for the current account; no cross-device sync.'))); ?></p>
<label><?php echo esc_html(dttl_th_t(array('Quyết định / tài sản đang xem xét','Decision / asset under review'))); ?><input type="text" data-check-title maxlength="150"></label>
<?php foreach(array(
array('Tôi hiểu tài sản và nguồn thông tin đang dùng.','I understand the asset and my information sources.'),
array('Tôi đã phân biệt dữ kiện kiểm chứng được và giả định của mình.','I have separated verified facts from my assumptions.'),
array('Tôi đã tính phí, thuế và khả năng chịu lỗ.','I have considered fees, taxes and potential losses.'),
array('Khoản tiền này không dành cho chi tiêu thiết yếu sắp tới.','This money is not needed for upcoming essential expenses.'),
array('Tôi đã xác định số tiền và tỷ trọng tối đa cho quyết định này.','I have defined the amount and maximum allocation for this decision.'),
array('Tôi đã xem xét thanh khoản và khả năng thoát vị thế.','I have considered liquidity and the ability to exit the position.'),
array('Tôi hiểu rủi ro đòn bẩy, tỷ giá hoặc lưu ký nếu có.','I understand any leverage, currency or custody risks.'),
array('Tôi biết khi nào cần xem xét lại quyết định.','I know when to reassess my decision.'),
array('Tôi đã cân nhắc tình huống xấu và phương án không giao dịch.','I have considered an adverse scenario and the option not to trade.'),
array('Tôi đã ghi lý do vào nhật ký trước khi thao tác.','I have recorded my reasons in my journal before acting.')
) as $label): ?><label class="th-check"><input type="checkbox" data-check-item> <span><?php echo esc_html(dttl_th_t($label)); ?></span></label><?php endforeach; ?>
<p data-check-summary role="status"></p>
<label><?php echo esc_html(dttl_th_t(array('Điều còn chưa rõ / việc cần kiểm tra thêm','Uncertainties / items to verify'))); ?><textarea data-check-notes rows="4" maxlength="4000"></textarea></label>
<div class="th-actions"><button type="button" data-check-save><?php echo esc_html(dttl_th_t(array('Lưu bản kiểm tra','Save review'))); ?></button><button type="button" class="th-secondary" data-check-export><?php echo esc_html(dttl_th_t(array('Xuất bản kiểm tra CSV','Export review CSV'))); ?></button><button type="button" class="th-secondary" data-check-reset><?php echo esc_html(dttl_th_t(array('Soạn bản mới','Start a new review'))); ?></button></div>
<p data-check-status role="status" aria-live="polite"></p>
<p><a class="th-button" href="<?php echo esc_url(dttl_th_url('nhat-ky-dau-tu')); ?>"><?php echo esc_html(dttl_th_t(array('Mở nhật ký đầu tư','Open investment journal'))); ?> →</a></p>
