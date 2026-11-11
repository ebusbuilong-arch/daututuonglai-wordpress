<?php
defined('ABSPATH') || exit;

final class AIM_Stock_Margin_Admin {
    const PAGE = 'aim-stock-margin';

    static function menu() {
        add_management_page('Ký quỹ cổ phiếu AIM', 'Ký quỹ cổ phiếu AIM', 'manage_options', self::PAGE, array(__CLASS__, 'page'));
    }

    static function settings() {
        if (!current_user_can('manage_options')) wp_die('Không có quyền.', '', array('response' => 403));
        check_admin_referer('aim_stock_margin_settings');
        $rate = isset($_POST['annual_bps']) && is_string($_POST['annual_bps']) ? wp_unslash($_POST['annual_bps']) : '';
        if (!preg_match('/^\d{1,5}$/D', $rate) || (int) $rate > 10000) {
            self::finish('Lãi suất phải từ 0 đến 10.000 điểm cơ bản.', true);
        }
        update_option('aimtr_stock_margin_annual_bps', (int) $rate, false);
        update_option('aimtr_stock_margin_enabled', isset($_POST['enabled']) ? '1' : '0', false);
        self::finish('Đã cập nhật quy tắc mở vị thế margin.');
    }

    static function close() {
        if (!current_user_can('manage_options')) wp_die('Không có quyền.', '', array('response' => 403));
        check_admin_referer('aim_stock_margin_close');
        $uid = absint($_POST['uid'] ?? 0);
        $symbol = isset($_POST['symbol']) && is_string($_POST['symbol']) ? wp_unslash($_POST['symbol']) : '';
        $open_id = isset($_POST['open_id']) && is_string($_POST['open_id']) ? wp_unslash($_POST['open_id']) : '';
        $bound = isset($_POST['minimum_bid']) && is_string($_POST['minimum_bid']) ? wp_unslash($_POST['minimum_bid']) : '';
        if (!$uid || !isset(AIM_VN_Stock_Feed::map()[$symbol]) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $bound) || (float) $bound <= 0 || !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $open_id)) {
            self::finish('Hãy nhập giá bán tối thiểu hợp lệ bằng VND.', true);
        }
        global $wpdb;
        $table = AIM_Trading::table('stocks');
        $row = $wpdb->get_var($wpdb->prepare("SELECT state FROM $table WHERE user_id=%d", $uid));
        $state = is_string($row) ? json_decode($row, true) : array();
        $position = $state['margin_positions'][$symbol] ?? null;
        if (!$position || $position['open_request_id'] !== $open_id) self::finish('Vị thế đã thay đổi. Tải lại danh sách.', true);

        $result = AIM_Stock_Accounts::execute(array(
            'request_id' => wp_generate_uuid4(), 'symbol' => $symbol, 'funding' => 'margin',
            'side' => 'sell', 'quantity' => $position['quantity'], 'bound' => (float) $bound,
            'order_type' => 'limit_ioc', 'open_request_id' => $open_id,
        ), $uid, get_current_user_id());

        if (is_wp_error($result)) self::finish($result->get_error_message(), true);
        self::finish(sprintf('Đã chốt %s cho tài khoản #%d. Hoàn về tài khoản %s AIM.', $symbol, $uid, self::aim($result['event']['settlement']['returned'])));
    }

    private static function finish($message, $error = false) {
        set_transient('aim_stock_margin_notice_' . get_current_user_id(), array($message, $error), 60);
        wp_safe_redirect(admin_url('tools.php?page=' . self::PAGE));
        exit;
    }

    private static function aim($micro) {
        return number_format_i18n($micro / AIM_Trading::UNIT, 6);
    }

    static function page() {
        if (!current_user_can('manage_options')) return;
        $notice_key = 'aim_stock_margin_notice_' . get_current_user_id();
        $notice = get_transient($notice_key);
        delete_transient($notice_key);
        $action = admin_url('admin-post.php');
        echo '<div class="wrap"><h1>Ký quỹ cổ phiếu AIM</h1>';
        if ($notice) echo '<div class="notice ' . ($notice[1] ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($notice[0]) . '</p></div>';
        echo '<p>Vị thế mua mô phỏng 2×. Ký quỹ được giữ riêng cho từng vị thế, phí và khoản quyết toán được ghi vào sổ quỹ AIM.</p>';
        echo '<form method="post" action="' . esc_url($action) . '"><input type="hidden" name="action" value="aim_stock_margin_settings">';
        wp_nonce_field('aim_stock_margin_settings');
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked(get_option('aimtr_stock_margin_enabled', '0'), '1', false) . '> Cho phép tài khoản mở vị thế margin</label><p>';
        echo '<label>Lãi vay năm (điểm cơ bản, 1.200 = 12%) <input type="number" min="0" max="10000" name="annual_bps" required value="' . esc_attr(get_option('aimtr_stock_margin_annual_bps', 1200)) . '"></label></p>';
        submit_button('Lưu quy tắc');
        echo '</form>';
        if (!AIM_Stock_Accounts::ready()) {
            echo '<p>Chưa khởi tạo sổ tài khoản cổ phiếu.</p></div>';
            return;
        }
        global $wpdb;
        $table = AIM_Trading::table('stocks');
        $pool = $wpdb->get_var('SELECT cash FROM ' . AIM_Trading::table('accounts') . ' WHERE user_id=0');
        $page = max(1, absint($_GET['paged'] ?? 1));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id,state FROM $table WHERE CASE WHEN JSON_VALID(state) THEN JSON_LENGTH(JSON_EXTRACT(state,'$.margin_positions')) ELSE 0 END > 0 ORDER BY user_id LIMIT 51 OFFSET %d",
            ($page - 1) * 50
        ), ARRAY_A);
        echo '<h2>Vị thế đang mở</h2><p>Quỹ khả dụng: ' . esc_html(is_numeric($pool) ? self::aim($pool) . ' AIM' : 'Chưa đọc được') . '</p>';
        if ($wpdb->last_error || !is_array($rows)) {
            echo '<p>Chưa đọc được danh sách ký quỹ.</p></div>';
            return;
        }
        if (!$rows) echo '<p>Chưa có vị thế margin trên trang này.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Tài khoản</th><th>Mã</th><th>Số CP</th><th>Ký quỹ AIM</th><th>Dư nợ AIM</th><th>Chốt vị thế</th></tr></thead><tbody>';
        foreach (array_slice($rows, 0, 50) as $row) {
            $state = json_decode($row['state'], true);
            foreach ($state['margin_positions'] ?? array() as $position) {
                echo '<tr><td>#' . esc_html($row['user_id']) . '</td><td>' . esc_html($position['symbol']) . '</td><td>' . esc_html($position['quantity']) . '</td><td>' . esc_html(self::aim($position['collateral'])) . '</td><td>' . esc_html(self::aim($position['loan'])) . '</td><td>';
                echo '<form method="post" action="' . esc_url($action) . '"><input type="hidden" name="action" value="aim_stock_margin_close">';
                wp_nonce_field('aim_stock_margin_close');
                foreach (array('uid' => $row['user_id'], 'symbol' => $position['symbol'], 'open_id' => $position['open_request_id']) as $name => $value) {
                    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
                }
                echo '<label>Giá tối thiểu (VND) <input type="number" name="minimum_bid" min="0.01" step="0.01" required></label> <button class="button">Chốt toàn bộ</button></form></td></tr>';
            }
        }
        echo '</tbody></table><p>';
        if ($page > 1) echo '<a href="' . esc_url(admin_url('tools.php?page=' . self::PAGE . '&paged=' . ($page - 1))) . '">Trang trước</a> ';
        if (count($rows) > 50) echo '<a href="' . esc_url(admin_url('tools.php?page=' . self::PAGE . '&paged=' . ($page + 1))) . '">Trang sau</a>';
        echo '</p>';
        $ledger = AIM_Trading::table('ledger');
        $events = $wpdb->get_results("SELECT user_id,symbol,kind,cash_delta,pool_delta,execution,created FROM $ledger WHERE kind IN ('stock_margin_buy','stock_margin_sell') ORDER BY id DESC LIMIT 30", ARRAY_A);
        echo '<h2>Sổ quỹ margin gần đây</h2><table class="widefat striped"><thead><tr><th>Giờ UTC</th><th>Tài khoản</th><th>Mã</th><th>Nghiệp vụ</th><th>Thay đổi ký quỹ AIM</th><th>Thay đổi quỹ khả dụng AIM</th><th>Lãi vay AIM</th><th>Admin</th></tr></thead><tbody>';
        foreach ($events ?: array() as $row) {
            $entry = json_decode($row['execution'], true);
            $cells = array($row['created'], '#'.$row['user_id'], $row['symbol'], $row['kind']==='stock_margin_buy'?'Mở vị thế':'Chốt vị thế',
                self::aim($entry['escrow_delta'] ?? 0), self::aim($row['pool_delta']),
                self::aim($entry['settlement']['interest'] ?? 0), !empty($entry['admin_id'])?'#'.$entry['admin_id']:'—');
            echo '<tr>';
            foreach ($cells as $value) echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        if ($wpdb->last_error) echo '<tr><td colspan="8">Chưa đọc được sổ quỹ.</td></tr>';
        echo '</tbody></table></div>';
    }
}

add_action('admin_menu', array('AIM_Stock_Margin_Admin', 'menu'));
add_action('admin_post_aim_stock_margin_settings', array('AIM_Stock_Margin_Admin', 'settings'));
add_action('admin_post_aim_stock_margin_close', array('AIM_Stock_Margin_Admin', 'close'));
