<?php defined('ABSPATH') || exit; ?>
<div class="ex-forex-pilot" id="fx-pilot">
<h3>Tài khoản Forex · thử nghiệm quản trị</h3>
<p>1 AIM = 1.000 USD sức mua. Vị thế và lịch sử lưu trên máy chủ. Chuyển vốn từ AIM giao ngay; đây không phải tiền của bài kiểm chứng phía trên. Chưa mở cho thành viên, chưa tính phí qua đêm.</p>
<p id="fx-pilot-readiness" role="status">Đang kiểm tra dữ liệu Forex…</p>
<button type="button" id="fx-pilot-refresh" class="start">Tải lại tài khoản Forex</button>
<div class="ex-admin-fields"><div>AIM khả dụng<strong id="fx-pilot-cash">—</strong></div><div>Ký quỹ đang giữ<strong id="fx-pilot-margin">—</strong></div><div>Lãi/lỗ đã chốt, trước phí<strong id="fx-pilot-realized">—</strong></div><div>Phí đã trả<strong id="fx-pilot-fees">—</strong></div></div>
<details><summary>Chuyển vốn và mở vị thế thử</summary>
<p>Chỉ mở sau khi cấu hình nguồn và bật <code>AIM_FOREX_ADMIN_PILOT</code> trên VPS. Nguồn phải được kiểm chứng; phiên bản này dùng quy tắc ký quỹ riêng của AIM.</p>
<form id="fx-pilot-transfer"><div class="ex-admin-fields"><label>Số AIM<input id="fx-pilot-amount" inputmode="decimal" placeholder="10" required></label><label>Hướng chuyển<select id="fx-pilot-direction"><option value="transfer_in">Giao ngay → Forex</option><option value="transfer_out">Forex → Giao ngay</option></select></label></div><button class="start" id="fx-pilot-transfer-submit" disabled>Chuyển AIM</button></form>
<form id="fx-pilot-open"><div class="ex-admin-fields"><label>Cặp tiền<select id="fx-pilot-symbol"><option>EUR_USD</option><option>GBP_USD</option><option>AUD_USD</option><option>NZD_USD</option></select></label><label>Chiều<select id="fx-pilot-side"><option value="long">Long / Mua</option><option value="short">Short / Bán</option></select></label><label>Khối lượng lot<input id="fx-pilot-lots" inputmode="decimal" value="0.001" required></label><label>Đòn bẩy<select id="fx-pilot-leverage"><option value="1">1x</option><option value="5">5x</option><option value="10" selected>10x</option><option value="20">20x</option></select></label><label>Chốt lời<input id="fx-pilot-tp" inputmode="decimal" placeholder="Không bắt buộc"></label><label>Cắt lỗ<input id="fx-pilot-sl" inputmode="decimal" placeholder="Không bắt buộc"></label></div><button id="fx-pilot-open-submit" class="start" disabled>Mở vị thế theo giá thị trường</button></form>
</details>
<p id="fx-pilot-message" role="status"></p><button type="button" id="fx-pilot-retry" class="start" hidden>Kiểm tra lại yêu cầu trước</button>
<h3>Vị thế đang giữ</h3><p>Giá mở đã ghi nhận; bảng này không phải định giá lãi/lỗ tức thời.</p><div id="fx-pilot-positions" class="table-scroll"></div>
<h3>Lịch sử Forex</h3><div id="fx-pilot-history" class="table-scroll"></div>
</div>
