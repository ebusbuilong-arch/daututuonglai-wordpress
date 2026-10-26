# Forex Practice — AIM Trading 0.3.8

## Trạng thái

Chỉ kiểm tra nguồn và tính thử lệnh cho quản trị viên. Không tạo vị thế,
không giữ/trừ AIM, không gửi lệnh đến nhà môi giới. Crypto giữ cơ chế hiện có.
Chứng khoán, Forex và hàng hóa vẫn là màn hình theo dõi; bài học nằm ở Luyện tập.

## Cấu hình trên VPS

Cần tài khoản OANDA Practice của chủ website, có quyền API. Xác minh khả năng
đăng ký tại khu vực của chủ tài khoản và điều khoản nguồn trước khi chọn dùng.
Không cần tài khoản tiền thật. Không gửi token trong trò chuyện.

Trong wp-config.php, trước dòng require_once ABSPATH . 'wp-settings.php',
chủ VPS tự điền hai hằng số bằng thông tin tài khoản của mình:

```php
define('AIM_OANDA_PRACTICE_ACCOUNT', 'ACCOUNT_ID_CUA_ANH');
define('AIM_OANDA_PRACTICE_TOKEN', 'TOKEN_PRACTICE_CUA_ANH');
```

Đăng nhập quản trị, mở AIM Trading → Forex → Quản trị · Nguồn giá Forex.
Bấm Kiểm tra kết nối Bid/Ask. Nguồn phải trả thời gian mới, trạng thái giao dịch,
Bid/Ask và độ sâu cho EUR_USD, GBP_USD, AUD_USD, NZD_USD.
Nếu báo giá hết hạn, đóng cửa hoặc thiếu thanh khoản, phép tính bị từ chối.

## Giới hạn bản này

- Chỉ truy vấn GET pricing/instruments tới api-fxpractice.oanda.com; không gọi tuyến đặt lệnh.
- Route kiểm tra chỉ cấp cho manage_options và nonce WordPress.
- Thử Long theo Ask, Short theo Bid; giá bình quân theo các mức thanh khoản.
- 1 lot = 100.000 đơn vị; đòn bẩy tối đa 20x, vẫn áp dụng mức ký quỹ cao hơn từ nguồn.
- Quy ước thử tính 1 AIM = 1.000 USD danh nghĩa; không có cam kết đổi AIM sang USD.
- Chưa tính hoa hồng/swap, chưa nối ví, vị thế, TP/SL hoặc thanh lý Forex.
- Phải xác minh quyền phân phối dữ liệu cho người dùng trước khi mở nguồn công khai.
- Biểu đồ TradingView độc lập với nguồn OANDA; không dùng giá widget để khớp.

## Khôi phục / dừng

Xóa hai dòng hằng số để ngừng truy cập nguồn. Không có bảng Forex hoặc số dư
nào cần hoàn nguyên. Không thay đổi timer/cron hoặc cấu hình cơ sở dữ liệu.

## Kiểm chứng phiên bản

0.3.7 đã bỏ kiểm tra phiên bản worker bằng so sánh tuyệt đối 0.3.4.
0.3.8 giữ kiểm tra phiên bản tối thiểu và kiểm tra bảng InnoDB để worker tiếp tục
chạy sau các lần nâng phiên bản giao diện.
