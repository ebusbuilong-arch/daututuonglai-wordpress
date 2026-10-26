# AIM Trading 0.1.2 — bộ xử lý Limit

Bản thử nội bộ chỉ dành cho quản trị. Không giao dịch tiền thật và chưa nối ví blockchain.

## Sau khi cập nhật plugin
Mở một trang wp-admin một lần để plugin tạo bảng orders (không xóa hay thay số dư cũ).
Chạy ở Terminal VPS:

```sh
/www/server/php/83/bin/php -l /www/wwwroot/daututuonglai.vn/wp-content/plugins/aim-trading/limit-orders.php
runuser -u www -- /www/server/php/83/bin/php /www/wwwroot/daututuonglai.vn/wp-content/plugins/aim-trading/worker.php --check
```

Phải có dòng `AIM Trading 0.1.2: PHP + WordPress + 3 InnoDB tables OK. No orders executed.`
Lệnh --check chỉ kiểm tra, không khớp lệnh và không bật dịch vụ.

## Cài và bật sau khi kiểm tra đạt

```sh
sh /www/wwwroot/daututuonglai.vn/wp-content/plugins/aim-trading/ops/install-worker.sh
systemctl status aim-trading-worker.timer --no-pager
journalctl -u aim-trading-worker.service -n 20 --no-pager
```

Script kiểm tra cú pháp, chạy --check dưới user www, cài đúng hai unit AIM Trading và bật timer. Không thay đổi cron hay preload WordPress. Mỗi lượt tải WordPress một lần qua PHP CLI. Timer chạy lại sau khi lượt trước kết thúc khoảng 5 giây; không phải hệ thống realtime. Một lượt tối đa 60 giây, CPU tối đa 25% một lõi và RAM 256 MB. Các giới hạn cần đo lại trên VPS thực tế. Lượt nhàn rỗi không ghi journal liên tục.

## Kiểm tra trên sàn
1. Làm mới trang AIM Trading. Trạng thái bộ xử lý Limit chuyển sang hoạt động.
2. Đặt lệnh mua nhỏ dưới giá Ask hiện tại, thấy tiền khả dụng giảm và tiền giữ chỗ tăng.
3. Hủy lệnh, số giữ chỗ về trước đó; sổ tiền chưa có mua/bán.
4. Đặt lệnh mua nhỏ có giá giới hạn cao hơn Ask. Sau lượt worker, khớp ở Ask thực tế không vượt giá giới hạn. Đóng trình duyệt rồi mở lại vẫn thấy kết quả.
5. Kiểm tra bán Limit bằng lượng vừa mua, giữ/hủy và khớp ở Bid.
6. Dừng timer, chờ 90 giây: ngừng nhận Limit mới; vẫn hủy lệnh được. Bật lại không khớp lặp.

Các bước có giao dịch sẽ làm thay đổi số dư AIM mô phỏng và lịch sử. Chưa nghiệm thu tải, cạnh tranh MySQL thật, restart giữa transaction hay thiết bị mobile thật.

## Dừng/khôi phục

```sh
systemctl disable --now aim-trading-worker.timer
systemctl stop aim-trading-worker.service
```

Lệnh chờ vẫn nằm trong DB, tiền/tài sản vẫn giữ chỗ. Hủy bằng giao diện khi cần. KHÔNG hạ về 0.1.1 khi còn lệnh pending, vì bản đó không hiểu tiền giữ chỗ. Muốn rollback: dừng timer, hủy hết pending bằng 0.1.2, sao lưu DB+plugin, rồi mới thay bản cũ. Không xóa bảng orders/ledger để rollback.

## Quy tắc
- Limit GTC, khớp toàn bộ hoặc tiếp tục chờ. Chưa sửa lệnh, partial fill, GTD, Stop, OCO, Short hay leverage.
- Mua: Ask <= limit; bán: Bid >= limit. Giá tốt hơn được áp dụng.
- Giữ chỗ tối đa phí 0,1% khi mua; bán giữ số lượng. Khi khớp ghi ledger và cập nhật trạng thái trong cùng transaction.
- Trình tự khóa pool -> account -> order dùng chung với Market và Cancel. MySQL advisory lock ngăn hai worker cùng chạy.
- Không khớp hồi tố khi giá từng chạm trong thời gian mất nguồn. Không bảo đảm bắt mọi lần chạm giữa các lượt lấy mẫu.
- 20 pending/tài khoản, 100 toàn hệ thống. Heartbeat quá 90 giây chặn đặt Limit mới.
- Thanh khoản mô phỏng tự đối ứng, chưa theo độ sâu. Quỹ không đủ thì lệnh tiếp tục chờ, không tự cấp tiền.
