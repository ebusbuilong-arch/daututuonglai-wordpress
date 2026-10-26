# AIM Trading 0.3.5

Tách luyện tập khỏi theo dõi thị trường.
- Các kịch bản cổ phiếu, Forex, hàng hóa chuyển sang ?mode=workshop&market=...; không thay đổi dữ liệu bài học.
- Tab stock/forex/commodity dùng giao diện sàn riêng: 28 mã, tìm kiếm, yêu thích, biểu đồ nến TradingView, khung thời gian, sáng/tối, thông tin sản phẩm và tab tài khoản.
- Các thị trường mới chỉ theo dõi, CHƯA khớp lệnh AIM. Không lấy giá từ widget để ghi lãi/lỗ. Cần nguồn báo giá máy chủ có quyền sử dụng, bid/ask có dấu thời gian, lịch phiên/ngày lễ và kỳ hạn cụ thể.
- Crypto và bridge giữ nguyên nghiệp vụ; sửa chỉ dẫn và menu mobile.
- Không thay đổi số dư, bảng dữ liệu, cron, worker hay theme website.

Kiểm tra: 640 kiểm tra PHP nghiệp vụ hiện có đạt; toàn bộ PHP parse; JavaScript mới kiểm tra cú pháp.

Nguồn widget chính thức: https://www.tradingview.com/widget-docs/widgets/charts/advanced-chart/ và https://www.tradingview.com/widget-docs/faq/data/ . Dữ liệu widget có thể trễ/cuối ngày hoặc mã không được nhúng.
