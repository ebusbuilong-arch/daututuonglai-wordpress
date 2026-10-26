<?php defined('ABSPATH') || exit; ?>
<aside class="panel ex-ticket" id="fx-pilot">
<div class="panel-heading"><h2>Phiếu lệnh</h2><span class="chip">KÝ QUỸ AIM</span></div>
<div class="side-tabs" role="group" aria-label="Chiều giao dịch"><button id="ex-buy" class="selected buy" aria-pressed="true">Long / Mua</button><button id="ex-sell" class="sell" aria-pressed="false">Short / Bán</button></div>
<p id="mt-price" role="status">Đang kiểm tra nguồn giá…</p>
<form id="fx-pilot-open">
<input type="hidden" id="fx-pilot-symbol"><input type="hidden" id="fx-pilot-side" value="long">
<label>Loại lệnh<select disabled><option>Market · Thị trường</option></select></label>
<div class="ex-two"><label>Khối lượng · Lot<input id="fx-pilot-lots" inputmode="decimal" value="0.01" required></label><label>Đòn bẩy<select id="fx-pilot-leverage"><option value="1">1x</option><option value="5">5x</option><option value="10" selected>10x</option><option value="20">20x</option></select></label></div>
<div class="ex-two"><label>Chốt lời<input id="fx-pilot-tp" inputmode="decimal" placeholder="Không bắt buộc"></label><label>Cắt lỗ<input id="fx-pilot-sl" inputmode="decimal" placeholder="Không bắt buộc"></label></div>
<p id="mt-estimate">Chờ giá để tính ký quỹ.</p>
<button id="fx-pilot-open-submit" class="action buy" disabled>Mở Long</button>
</form>
<p id="fx-pilot-message" role="status"></p><button type="button" id="fx-pilot-retry" class="start" hidden>Đối chiếu yêu cầu trước</button>
<p id="fx-pilot-readiness" role="status"></p>
<div class="ex-two"><div>AIM khả dụng<strong id="fx-pilot-cash">—</strong></div><div>Ký quỹ<strong id="fx-pilot-margin">—</strong></div><div>Lãi/lỗ đã chốt<strong id="fx-pilot-realized">—</strong></div><div>Phí đã trả<strong id="fx-pilot-fees">—</strong></div></div>
<button type="button" id="fx-pilot-refresh" class="start">Làm mới tài khoản</button>
<details><summary>Chuyển AIM vào / ra tài khoản này</summary>
<form id="fx-pilot-transfer"><label>Số AIM<input id="fx-pilot-amount" inputmode="decimal" placeholder="10" required></label><label>Hướng chuyển<select id="fx-pilot-direction"><option value="transfer_in">Giao ngay → tài khoản này</option><option value="transfer_out">Tài khoản này → giao ngay</option></select></label><button class="start" id="fx-pilot-transfer-submit" disabled>Chuyển AIM</button></form>
</details>
<p class="ex-small">1 AIM = 1.000 USD sức mua mô phỏng. Dầu dùng Bid/Ask CommodityPriceAPI (độ trễ thị trường chưa xác định); các mã khác dùng Twelve Data cộng spread mô phỏng; quỹ admin đối ứng. Không gửi lệnh ra sàn. Chưa tính phí qua đêm. TP/SL và thanh lý chỉ xử lý khi có báo giá hợp lệ; có thể trượt giá.</p>
</aside>
