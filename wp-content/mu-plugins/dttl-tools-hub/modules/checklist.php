<?php if (!defined('ABSPATH')) { exit; } ?>
<p><?php echo esc_html(dttl_th_t(array('Dùng để tự rà soát trong lần mở trang này. Các ô đánh dấu không lưu khi tải lại.','Use this checklist during your current visit. Checkmarks are not saved after reloading.'))); ?></p>
<?php foreach(array(
array('Tôi hiểu tài sản và nguồn thông tin đang dùng.','I understand the asset and my information sources.'),
array('Tôi đã tính phí, thuế và khả năng chịu lỗ.','I have considered fees, taxes and potential losses.'),
array('Khoản tiền này không dành cho chi tiêu thiết yếu sắp tới.','This money is not needed for upcoming essential expenses.'),
array('Tôi biết khi nào cần xem xét lại quyết định.','I know when to reassess my decision.'),
array('Tôi đã ghi lý do vào nhật ký trước khi thao tác.','I have recorded my reasons in my journal before acting.')
) as $label): ?><label class="th-check"><input type="checkbox"> <span><?php echo esc_html(dttl_th_t($label)); ?></span></label><?php endforeach; ?>
<p><a class="th-button" href="<?php echo esc_url(dttl_th_url('nhat-ky-dau-tu')); ?>"><?php echo esc_html(dttl_th_t(array('Mở nhật ký đầu tư','Open investment journal'))); ?> →</a></p>
