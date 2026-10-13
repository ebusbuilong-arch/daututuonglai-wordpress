# Đầu Tư Tương Lai — WordPress

Theme riêng cho https://daututuonglai.vn, mã nguồn tại `wp-content/themes/daututuonglai/`.

Repository chỉ chứa giao diện, không chứa cơ sở dữ liệu, wp-config.php, mật khẩu hoặc uploads.

GitHub Actions kiểm tra cú pháp PHP/JavaScript và tạo ZIP cài theme. Tải artifact sau khi kiểm tra thành công, giải nén artifact để lấy `daututuonglai-theme-install.zip`, vào WordPress → Giao diện → Thêm mới → Tải giao diện lên, cài rồi kích hoạt Dau Tu Tuong Lai. Nếu theme đã có, chọn thay thế phiên bản hiện tại; giữ một bản ZIP cũ để phục hồi.

Không cài lại WordPress, không nhập hay xóa cơ sở dữ liệu. Khi kích hoạt lần đầu, theme chỉ bổ sung chuyên mục và trang giới thiệu còn thiếu, không ghi đè bài/trang có sẵn. Theme tự hiển thị trang chủ từ front-page.php và lấy bài viết hiện có.

Phiên bản 0.2.0: sửa liên kết thư viện bài viết, bổ sung tìm kiếm, cải thiện menu mobile, bàn phím, hình logo và hiển thị bảng trên màn hình nhỏ.

Chưa cấu hình tự triển khai tới hosting. Việc cập nhật GitHub chưa đồng nghĩa giao diện live đã được thay.
