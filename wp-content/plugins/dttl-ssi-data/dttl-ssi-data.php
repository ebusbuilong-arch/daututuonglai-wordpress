<?php
/**
 * Plugin Name: DTTL SSI Data
 * Description: Cấu hình kết nối dữ liệu thị trường SSI cho Đầu Tư Tương Lai.
 * Version: 0.2.1
 * Author: DTTL
 */
if (!defined('ABSPATH')) { exit; }
final class DTTL_SSI_Data {
    const OPTION = 'dttl_ssi_credentials';
    const STATUS = 'dttl_ssi_status';
    const URL = 'https://api.ssi.com.vn/api/v3/auth/token';
    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_dttl_ssi_save', [__CLASS__, 'save']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [__CLASS__, 'links']);
    }
    public static function links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=dttl-ssi')) . '">Cấu hình SSI</a>');
        return $links;
    }
    public static function menu() {
        add_menu_page('Cấu hình SSI', 'Dữ liệu SSI', 'manage_options', 'dttl-ssi', [__CLASS__, 'page'], 'dashicons-chart-line', 59);
    }
    private static function key() { return hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true); }
    public static function encrypt($value) {
        if (!function_exists('openssl_encrypt')) { throw new RuntimeException('Máy chủ cần bật OpenSSL để lưu khóa an toàn.'); }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(wp_json_encode($value), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) { throw new RuntimeException('Không mã hóa được khóa. Chưa lưu thay đổi.'); }
        return base64_encode($iv . $tag . $cipher);
    }
    public static function credentials() {
        $stored = get_option(self::OPTION, '');
        if (!$stored) { return ['apiKey' => '', 'apiSecret' => '']; }
        $raw = base64_decode($stored, true);
        if (!function_exists('openssl_decrypt') || $raw === false || strlen($raw) < 29) { throw new RuntimeException('Không đọc được khóa đã lưu. Vui lòng nhập lại cả hai khóa.'); }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        $data = $plain === false ? null : json_decode($plain, true);
        if (!is_array($data) || !isset($data['apiKey'], $data['apiSecret'])) { throw new RuntimeException('Khóa mã hóa máy chủ đã thay đổi. Vui lòng nhập lại cả hai khóa SSI.'); }
        return $data;
    }
    public static function check($credentials) {
        $response = wp_remote_post(self::URL, [
            'timeout' => 20, 'redirection' => 0, 'sslverify' => true,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode($credentials), 'limit_response_size' => 65536,
        ]);
        if (is_wp_error($response)) { return ['ok' => false, 'message' => 'Không kết nối được SSI. Kiểm tra mạng máy chủ rồi thử lại.']; }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code === 200 && is_array($data) && !empty($data['accessToken']) && is_string($data['accessToken'])) {
            return ['ok' => true, 'message' => 'Xác thực SSI thành công. Khóa đã dùng được để lấy access token. Chưa kiểm tra dữ liệu nến hoặc luồng giá trực tiếp.'];
        }
        $messages = [400 => 'SSI từ chối thông tin gửi lên. Kiểm tra lại API Key và API Secret.', 401 => 'API Key hoặc API Secret không hợp lệ hoặc đã hết hạn.', 403 => 'SSI từ chối quyền truy cập. Kiểm tra trạng thái key và nhóm quyền dữ liệu.', 429 => 'SSI đang giới hạn số lần gọi. Vui lòng chờ rồi kiểm tra lại.'];
        return ['ok' => false, 'message' => $messages[$code] ?? ('SSI chưa xác thực thành công (HTTP ' . $code . '). Vui lòng thử lại sau.')];
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('Không có quyền truy cập.', '', ['response' => 403]); }
        check_admin_referer('dttl_ssi_save');
        $mode = isset($_POST['mode']) && is_string($_POST['mode']) ? sanitize_key($_POST['mode']) : 'save';
        try {
            $new = [];
            foreach (['apiKey', 'apiSecret'] as $field) {
                $input = $_POST[$field] ?? '';
                if (!is_string($input)) { throw new RuntimeException('Thông tin khóa không hợp lệ.'); }
                $input = trim(wp_unslash($input));
                if (strlen($input) > 4096 || preg_match('/[\x00-\x20\x7f]/', $input)) { throw new RuntimeException('Khóa không được chứa khoảng trắng hoặc xuống dòng.'); }
                $new[$field] = $input;
            }
            $credentials = ($new['apiKey'] !== '' && $new['apiSecret'] !== '') ? $new : self::credentials();
            foreach ($new as $field => $value) { if ($value !== '') { $credentials[$field] = $value; } }
            if (!$credentials['apiKey'] || !$credentials['apiSecret']) { throw new RuntimeException('Anh nhập đủ API Key và API Secret trước khi lưu nhé.'); }
            if ($new['apiKey'] !== '' || $new['apiSecret'] !== '') {
                update_option(self::OPTION, self::encrypt($credentials), false);
                delete_option(self::STATUS);
                delete_transient('dttl_ssi_access_token');
            }
            $result = ['ok' => true, 'message' => 'Đã lưu cấu hình SSI. Anh bấm “Kiểm tra kết nối” để xác thực khóa.'];
            if ($mode === 'test') {
                if (get_transient('dttl_ssi_test_cooldown')) { throw new RuntimeException('Vui lòng đợi 15 giây giữa hai lần kiểm tra kết nối. Cấu hình đã được lưu.'); }
                set_transient('dttl_ssi_test_cooldown', 1, 15);
                $result = self::check($credentials);
                update_option(self::STATUS, array_merge($result, ['time' => time()]), false);
            }
        } catch (RuntimeException $e) { $result = ['ok' => false, 'message' => $e->getMessage()]; }
        set_transient('dttl_ssi_notice_' . get_current_user_id(), $result, 120);
        wp_safe_redirect(admin_url('admin.php?page=dttl-ssi'));
        exit;
    }
    public static function page() {
        if (!current_user_can('manage_options')) { return; }
        $saved = (bool) get_option(self::OPTION, '');
        $notice = get_transient('dttl_ssi_notice_' . get_current_user_id());
        delete_transient('dttl_ssi_notice_' . get_current_user_id());
        $status = get_option(self::STATUS, []);
        ?>
        <div class="wrap" style="max-width:900px">
            <h1>Cấu hình dữ liệu SSI</h1>
            <p>Kết nối dữ liệu chứng khoán Việt Nam cho Đầu Tư Tương Lai.</p>
            <?php if (is_array($notice)) : ?><div class="notice <?php echo !empty($notice['ok']) ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html($notice['message']); ?></p></div><?php endif; ?>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;margin-top:20px">
                <h2 style="margin-top:0">Thông tin kết nối</h2>
                <p><strong>Trạng thái khóa:</strong> <?php echo $saved ? 'Đã lưu — nhập khóa mới nếu muốn thay đổi.' : 'Chưa nhập khóa.'; ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                    <input type="hidden" name="action" value="dttl_ssi_save">
                    <?php wp_nonce_field('dttl_ssi_save'); ?>
                    <table class="form-table" role="presentation">
                        <?php foreach (['apiKey' => 'API Key', 'apiSecret' => 'API Secret'] as $field => $label) : ?>
                        <tr><th scope="row"><label for="dttl-ssi-<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input class="regular-text" style="max-width:100%" type="password" id="dttl-ssi-<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" value="" autocomplete="new-password" spellcheck="false" maxlength="4096" placeholder="<?php echo $saved ? 'Đã lưu — để trống để giữ nguyên' : 'Dán ' . esc_attr($label) . ' tại đây'; ?>">
                        </td></tr><?php endforeach; ?>
                        <tr><th scope="row">Máy chủ SSI</th><td><code>https://api.ssi.com.vn</code><p class="description">Môi trường Production — dữ liệu thật.</p></td></tr>
                    </table>
                    <p>Khóa được mã hóa khi lưu và không hiển thị lại trên trang. Chỉ quản trị viên được thay đổi cấu hình.</p>
                    <p><button type="submit" class="button button-primary" name="mode" value="save">Lưu cấu hình</button> <button type="submit" class="button" name="mode" value="test">Kiểm tra kết nối</button></p>
                    <p class="description">“Kiểm tra kết nối” sẽ lưu thông tin vừa nhập và thử xác thực với SSI.</p>
                </form>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;margin-top:16px">
                <h2 style="margin-top:0">Kết quả kiểm tra</h2>
                <?php if (!empty($status['time'])) : ?>
                    <p><strong><?php echo !empty($status['ok']) ? 'Xác thực thành công' : 'Kết nối chưa thành công'; ?></strong> · <?php echo esc_html(wp_date('d/m/Y H:i:s', $status['time'])); ?></p>
                    <p><?php echo esc_html($status['message']); ?></p>
                <?php else : ?><p>Chưa kiểm tra kết nối với khóa hiện tại.</p><?php endif; ?>
                <p>Quyền dùng cho biểu đồ: <strong>All api data</strong> và <strong>All stream data</strong>. Không cần Private Key hoặc OTP cho bước xác thực dữ liệu này.</p>
                <p class="description">Chèn shortcode [dttl_stock_chart] vào trang hoặc bài viết để hiển thị biểu đồ. Dữ liệu được lưu đệm để giảm số lượt gọi SSI.</p>
            </div>
            <?php $diag = get_option('dttl_ssi_data_diagnostic', []); if ($diag) : ?><p><strong>Chẩn đoán SSI:</strong> <?php echo esc_html(wp_json_encode($diag, JSON_UNESCAPED_UNICODE)); ?></p><?php endif; ?>
            <h2>Xem trước biểu đồ</h2>
            <?php echo DTTL_SSI_Charts::shortcode(); ?>
        </div>
        <?php
    }
}
DTTL_SSI_Data::boot();

require_once __DIR__ . '/charts.php';
DTTL_SSI_Charts::boot();
