# AIM Trading 0.4.8
- Admin kích hoạt từng mã bằng giá Twelve Data hợp lệ trong phiên; danh sách lưu trên máy chủ.
- Kiểm tra không có vị thế cũ trước khi đổi nguồn. Không sửa khóa trên trình duyệt.
- Phiếu Market, Long/Short, TP/SL, chuyển AIM, đóng toàn bộ/một phần, lịch sử Forex/CFD hàng hóa nối với engine và worker hiện có.
- Cổ phiếu dùng engine Spot hiện có, chỉ mở mã trong danh sách đã kiểm chứng.
- Giá quá 180 giây/phiên đóng bị khóa. API ghi xác thực, idempotency và đối ứng quỹ giữ nguyên.
- Snapshot giữ các mã hợp lệ nếu một mã không được nguồn cung cấp. Khi mở mới, các vị thế sẵn có vẫn phải qua kiểm tra rủi ro đầy đủ.
- Forex/hàng hóa chưa tính swap; giao diện công bố rõ. Không có giao nhận hàng.
- Dầu WTI/Brent chưa kích hoạt; nguồn thay thế cần kiểm chứng qua API riêng.
- Kiểm tra: 31 feed; 8 reference persistence; 10 launch/member lifecycle; 17 stock persistence cùng bộ kiểm tra nền.
