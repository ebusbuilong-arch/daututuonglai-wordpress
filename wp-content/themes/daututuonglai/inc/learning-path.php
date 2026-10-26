<?php
if(!defined('ABSPATH')) exit;
// Keep previously shared article URLs working while providing one learning path.
add_action('template_redirect',function(){
 if(is_singular('post') && get_post_field('post_name',get_queried_object_id())==='lo-trinh-tu-hoc-chung-khoan-90-ngay'){
  wp_safe_redirect(home_url('/lo-trinh-hoc/'),301);exit;
 }
});
function dttl_seven_day_path(){
 $lessons=dttl_lessons();
 $days=array(
 array('Nền tảng','Hiểu cổ phiếu, thị trường và những rủi ro đầu tiên.',1,7,'Viết mục tiêu học và khoản tiền cần giữ riêng cho sinh hoạt.'),
 array('Thao tác & chi phí','Làm quen ứng dụng, lệnh và các khoản phí.',8,13,'Tìm bảng giá, số dư và lịch sử lệnh trong ứng dụng. Tính thử chi phí bằng ví dụ trong bài.'),
 array('Hiểu doanh nghiệp','Đọc kết quả kinh doanh, tài sản và dòng tiền.',14,19,'Chọn một doanh nghiệp, ghi nguồn doanh thu, lợi nhuận và điểm cần tìm hiểu thêm.'),
 array('Tích lũy dài hạn','Hiểu phương pháp, sản phẩm và giới hạn.',20,25,'Viết một kế hoạch tích lũy giả định phù hợp với thu nhập và khoản dự phòng.'),
 array('Phương pháp & rủi ro','Thực hành với kế hoạch và các kịch bản.',26,30,'Viết lý do mua, mức vốn dự kiến và điều kiện dừng trước khi thực hành mô phỏng.'),
 array('Thực hành mô phỏng','Ghép kiến thức thành một kế hoạch bằng tiền ảo.',0,0,'Tạo giao dịch mô phỏng, ghi lý do, cập nhật giá giả định và xem lãi/lỗ. Không gửi lệnh lên sàn.'),
 array('Kiểm tra & ôn tập','Đối chiếu điều đã hiểu và chọn phần cần học lại.',0,0,'Làm bài kiểm tra, ghi câu chưa hiểu, quay lại chặng tương ứng và viết quy trình của riêng bạn.')
 ); ?>
<section id="7-ngay" class="learning-stage dttl-seven-days" data-learning-progress>
<h2>Tiến độ lộ trình 7 ngày</h2><p data-progress-status role="status">Đã hoàn thành 0/7 chặng</p><progress max="7" value="0" aria-label="Tiến độ lộ trình 7 ngày"></progress><p data-storage-note role="status"></p>
<nav class="dttl-day-jumps" aria-label="Chọn ngày học"><?php foreach($days as $i=>$day): ?><a href="#ngay-<?php echo (int)$i+1; ?>">Ngày <?php echo (int)$i+1; ?></a><?php endforeach; ?></nav>
<div class="dttl-day-grid"><?php foreach($days as $i=>$day): $n=$i+1; ?>
<section id="ngay-<?php echo (int)$n; ?>" class="dttl-panel dttl-day-card"><span id="chang-<?php echo esc_attr(str_pad($n,2,'0',STR_PAD_LEFT)); ?>" class="eyebrow dark">NGÀY <?php echo (int)$n; ?> · CHẶNG <?php echo (int)$n; ?></span><h3><?php echo esc_html($day[0]); ?></h3><p><?php echo esc_html($day[1]); ?></p>
<?php if($n<=5): ?><div class="lesson-list"><?php foreach($lessons as $lesson): $order=(int)get_post_meta($lesson->ID,'_dttl_lesson_order',true); if($order<$day[2]||$order>$day[3])continue; ?><a class="lesson-row" href="<?php echo esc_url(get_permalink($lesson->ID)); ?>"><span class="lesson-number"><?php echo esc_html(str_pad($order,2,'0',STR_PAD_LEFT)); ?></span><div><h4><?php echo esc_html($lesson->post_title); ?></h4><p><?php echo esc_html($lesson->post_excerpt); ?></p></div><span class="lesson-read">Đọc bài →</span></a><?php endforeach; ?></div><?php endif; ?>
<?php if($n===2): ?><p><a href="<?php echo esc_url(home_url('/huong-dan-app-chung-khoan/')); ?>">Hướng dẫn SSI, VNDIRECT, VPS →</a></p><?php elseif($n===6): ?><p><a class="btn primary" href="<?php echo esc_url(home_url('/danh-muc-mo-phong/')); ?>">Mở danh mục mô phỏng →</a></p><p><a href="<?php echo esc_url(home_url('/thi-truong/')); ?>">Quan sát thị trường →</a></p><?php elseif($n===7): ?><p><a class="btn primary" href="<?php echo esc_url(dttl_learning_post_url('bai-kiem-tra-kien-thuc-chung-khoan')); ?>">Làm bài kiểm tra →</a></p><p><a href="#ngay-1">Ôn lại từ chặng 1 →</a></p><?php endif; ?>
<p><strong>Thực hành:</strong> <?php echo esc_html($day[4]); ?></p><label class="dttl-day-done"><input type="checkbox" data-day="<?php echo (int)$n; ?>"> Tôi đã hoàn thành chặng <?php echo (int)$n; ?></label></section>
<?php endforeach; ?></div><p>Tiến độ lưu trên trình duyệt và thiết bị này, chưa đồng bộ giữa các máy. Xóa dữ liệu trình duyệt sẽ mất tiến độ. Hoàn thành lộ trình không đồng nghĩa đã sẵn sàng giao dịch tiền thật.</p><button type="button" data-reset-progress>Đặt lại tiến độ 7 chặng</button><noscript>Bật JavaScript để lưu tiến độ học.</noscript>
</section>
<?php }
