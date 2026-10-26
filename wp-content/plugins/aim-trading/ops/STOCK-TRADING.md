# Cổ phiếu AIM 0.4.3

1 AIM = 1.000 USD. Phí 0,1% mỗi chiều. Cổ phiếu nguyên, mua bằng tiền có sẵn, không bán khống. Limit IOC khớp toàn bộ ngay tại Bid/Ask hoặc từ chối; không giữ lệnh chờ. Market bảo vệ trượt giá 0,5% từ báo giá kiểm tra. Admin đối ứng; không gửi lệnh sang môi giới. Thanh toán tức thời trong mô phỏng. Chưa tự xử lý cổ tức/chia tách nên chưa mở cộng đồng mặc định.

## Cấu hình VPS
Đặt trước dòng nạp WordPress trong wp-config.php, dùng thông tin nguồn Alpaca Paper riêng của chủ web:
```php
define('AIM_ALPACA_DATA_KEY', 'KEY_CUA_ANH');
define('AIM_ALPACA_DATA_SECRET', 'SECRET_CUA_ANH');
define('AIM_ALPACA_DATA_FEED', 'iex'); // hoặc sip nếu gói dữ liệu cho phép
define('AIM_STOCK_TRADING_ENABLED', true); // admin thử trước
// Chỉ bật khi quyền nguồn cho phép phục vụ thành viên:
// define('AIM_STOCK_PUBLIC_DATA_ALLOWED', true);
```
Không gửi key/secret trong chat. Không có key sẵn trong mã nguồn. API chỉ GET clock/assets/latest quote; không đặt lệnh Alpaca.

Nguồn: https://docs.alpaca.markets/us/reference/stocklatestquotes-1
Clock: https://paper-api.alpaca.markets/v2/clock
Chỉ phiên chính theo clock, mã active/tradable, nguồn giá <=15s, thời gian nhận <=5s. Ngoài phiên/mất nguồn/sai mã/nghịch Bid-Ask đều từ chối. IEX không phải toàn bộ thị trường; biểu đồ Cboe One có thể khác nguồn đặt lệnh.

## Lưu trữ và kiểm chứng
Mở một trang wp-admin sau cập nhật để tạo bảng stocks và stock_events (InnoDB). Khóa quỹ, tài khoản, danh mục trong cùng giao dịch; kiểm tra AIM giữ cho lệnh Crypto, ghi sổ ledger. request_id chống lặp, gửi lại cùng request sau timeout. Lỗi ghi sổ rollback toàn bộ. Chưa kiểm thử đồng thời trên MySQL thật hoặc mua/bán với nguồn live do chưa có khóa.

## Giới hạn bản này
10 cổ phiếu quốc tế có engine/UI và adapter nguồn; VN chưa có nguồn/tỷ giá. Forex đổi tỷ lệ v2; không chuyển tỷ lệ vị thế v1 đang mở, cần tất toán/đối soát trước. Chỉ tài khoản v1 không còn vị thế được chuyển chính sách khi ghi lần tiếp theo. Hàng hóa chưa có bộ giao dịch; không được bật bằng cách giả lập báo giá.
