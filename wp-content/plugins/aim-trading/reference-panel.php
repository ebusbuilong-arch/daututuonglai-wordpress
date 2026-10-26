<?php defined('ABSPATH') || exit; ?>
<section class="panel ex-admin-feed ex-reference-panel"><div class="ex-admin-body" id="ref-panel"><h2>Giá tham chiếu · Kiểm tra nguồn mới</h2>
<p>Biểu đồ đường 1 phút và giá mô phỏng cùng nguồn Twelve Data. Cập nhật mỗi 60 giây khi bật theo dõi. Giá quá 180 giây sẽ ngừng khớp. Spread mô phỏng: Forex 0,02%, cổ phiếu và hàng hóa 0,1%; admin đối ứng thanh khoản.</p>
<p>Biểu đồ TradingView phía trên vẫn là biểu đồ tham khảo riêng. Nguồn mới chỉ thay nguồn khớp khi được cấu hình và kiểm chứng.</p>
<label>Mã kiểm tra<select id="ref-symbol"><?php foreach(AIM_Reference_Feed::map() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
<button class="start" id="ref-start" type="button">Bật theo dõi giá 60 giây</button><button class="start" id="ref-stop" type="button">Dừng theo dõi</button>
<button class="start" id="ref-enable" type="button">Kích hoạt mã đang chọn cho giao dịch AIM</button><p id="ref-enable-status" role="status"></p>
<p id="ref-status" role="status">Chưa bật theo dõi nguồn mới.</p><div id="ref-chart"></div>
<p>Cấu hình khóa AIM_TWELVE_DATA_KEY trên VPS. Chọn AIM_REFERENCE_FEED = twelvedata sau khi xác minh giá, mã, phiên và quyền sử dụng dữ liệu. Không đổi nguồn khi còn vị thế. Không gửi khóa vào trò chuyện.</p></div></section>

<?php if($market==='commodity'): ?>
<section class="panel ex-admin-feed ex-reference-panel"><div class="ex-admin-body" id="oil-reference-panel">
<h2>Nguồn dầu WTI &amp; Brent · CommodityPriceAPI</h2>
<p>Giá dầu giao ngay liên tục, USD/thùng. Nguồn giá tham khảo riêng với TradingView. Dùng Bid/Ask nguồn để khớp AIM sau khi kích hoạt từng mã. Thời gian hiển thị là phản hồi API, không phải thời điểm phát sinh giá. Phiên mô phỏng hoạt động khi phản hồi nguồn còn hiệu lực (tối đa 120 giây, nhận trên VPS tối đa 90 giây).</p>
<button class="start" id="oil-reference-check" type="button">Kiểm tra kết nối giá dầu</button>
<p id="oil-reference-status" role="status">Khóa được đọc trên VPS; không gửi xuống trình duyệt. Lưu đệm dùng chung 60 giây; không tạo giá khi nguồn lỗi.</p>
<div id="oil-reference-result"></div>
</div></section>
<?php endif; ?>
