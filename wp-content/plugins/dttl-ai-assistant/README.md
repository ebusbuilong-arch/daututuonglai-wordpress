# Trợ lý Đầu Tư Tương Lai — 0.1.0

Plugin WordPress độc lập, lấy ý tưởng luồng tư vấn VIE nhưng không dùng AI Core vốn khóa nguồn và lời nhắc riêng cho đồng phục. PHP gọi OpenAI Responses trực tiếp; không cần dịch vụ Node mới.

## Cài đặt
1. WordPress → Plugin → Cài mới → Tải plugin lên → chọn ZIP → Kích hoạt.
2. Cài đặt → Trợ lý AI → nhập khóa OpenAI API (hoặc hằng DTTL_AI_API_KEY trong wp-config.php).
3. Kiểm tra model được phép dùng trong tài khoản; mặc định gpt-4.1-mini.
4. Bật “Hỏi AI”, lưu. Xóa cache trang một lần nếu nút chưa hiện.
5. Test trên PC/mobile và cả VI/EN trước khi mở rộng. Có thể tắt nút mà vẫn giữ cấu hình.

Mặc định nút chưa bật, tối đa 200 yêu cầu AI/ngày UTC và 10 câu hỏi/10 phút/IP. Yêu cầu nhà cung cấp lỗi vẫn được tính; đây không phải giới hạn tiền. Đặt ngân sách riêng phía nhà cung cấp. Không thay theme, ví, cron hệ thống hoặc cấu hình cache. Plugin có lịch riêng dọn bộ đếm; không bật WP-Cron nếu máy chủ đang tắt.

## Nguồn và quyền riêng tư
- Tìm bài post/page đang publish, không mật khẩu; không chạy shortcode, không đọc usermeta, ví hoặc giao dịch.
- Tối đa 4 nguồn, chọn đoạn liên quan, kèm link WordPress tạo. Khi bài thay đổi, truy vấn sau lấy nội dung mới.
- Có trường loại ID khỏi nguồn. Website có plugin hội viên cần loại các bài bị plugin đó hạn chế (dù trạng thái publish).
- Tìm từ khóa tiếng Việt bỏ dấu; có từ tương đương tiếng Anh cho vài chủ đề chính. Đây là tìm từ khóa, chưa phải semantic/vector search; có thể bỏ sót cách diễn đạt khác.
- Với Polylang, lọc ngôn ngữ bài. Với nội dung dịch bằng cơ chế khác, câu trả lời theo VI/EN nhưng nguồn có thể còn tiếng Việt. Cần kiểm tra cơ chế dịch live trước khi xác nhận đồng bộ hoàn toàn.
- Câu hỏi, tối đa 6 lượt hội thoại và trích đoạn nguồn gửi tới OpenAI. store:false. Không gửi khóa ra trình duyệt. Thông báo dữ liệu nằm ngay trong khung chat.
- Không lưu hội thoại vào database hoặc localStorage. Bộ đếm lưu mã băm IP và được dọn hằng ngày nếu WordPress cron hoạt động. Nếu cron tắt lâu, chạy hook dttl_ai_cleanup theo lịch quản trị hiện có để tránh bộ đếm tích lũy.
- Chỉ hiển thị plain text, nguồn link cùng origin. Chỉ dẫn cho AI yêu cầu bám nguồn, nói rõ thiếu dữ liệu, không tư vấn mua/bán cá nhân hóa. Đây là ràng buộc lời nhắc, không bảo đảm mô hình luôn đúng.

## Triển khai và giới hạn kiểm thử
Bộ ZIP không chứa khóa, không tự sao chép cấu hình VIE và không tự triển khai lên hosting. Cần kiểm tra thực tế với khóa API trên WordPress đang chạy. Repo theme hiện có là bản cũ, vì vậy chỉ bổ sung plugin, không cài lại theme từ repo.
