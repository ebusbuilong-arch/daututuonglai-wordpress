<?php
/**
 * Plugin Name: DTTL Ứng dụng miễn phí
 * Description: Trang giới thiệu và tải DTTL Chia màn hình dành cho thành viên.
 * Version: 0.1.0
 * Author: Đầu Tư Tương Lai
 */
if (!defined('ABSPATH')) { exit; }

function dttl_apps_ready() { return is_readable(__DIR__ . '/payload.php'); }
function dttl_apps_page_url() {
    $id = (int) get_option('dttl_apps_page_id');
    return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/');
}
function dttl_apps_download() {
    nocache_headers();
    header('Cache-Control: private, no-store, max-age=0');
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(dttl_apps_page_url())); exit;
    }
    if (!dttl_apps_ready()) { wp_die('Bản tải đang được chuẩn bị.', 'Chưa có bản tải', array('response' => 503)); }
    $encoded = require __DIR__ . '/payload.php';
    $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
    if (!$bytes || substr($bytes, 0, 2) !== 'PK') { wp_die('Gói tải không hợp lệ.', '', array('response' => 503)); }
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="DTTL-Chia-Man-Hinh-0.1-beta.zip"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . strlen($bytes));
    update_option('dttl_apps_downloads', (int) get_option('dttl_apps_downloads', 0) + 1, false);
    echo $bytes; exit;
}
add_action('admin_post_dttl_apps_download', 'dttl_apps_download');
add_action('admin_post_nopriv_dttl_apps_download', 'dttl_apps_download');

add_shortcode('dttl_screen_layout_download', function () {
    // The same endpoint is safe to cache publicly: authentication is checked on every download.
    $url = admin_url('admin-post.php?action=dttl_apps_download');
    $ready = dttl_apps_ready();
    ob_start(); ?>
    <section class="dttl-app-download" aria-label="Tải ứng dụng chia màn hình" style="padding:24px;border:1px solid #dae3ef;border-radius:16px;background:#f4f7fc;margin:24px 0">
      <strong style="font-size:1.35em">DTTL Chia màn hình · 0.1 beta</strong>
      <p>Windows 10/11 · Intel/AMD 32-bit và 64-bit · Miễn phí · Chạy trực tiếp</p>
      <?php if ($ready): ?>
      <a style="display:inline-block;padding:12px 20px;background:#003399;color:#fff;border-radius:9px;text-decoration:none" href="<?php echo esc_url($url); ?>">Tải miễn phí cho Windows</a>
      <?php else: ?><p><strong>Bản tải đang được chuẩn bị.</strong></p><?php endif; ?>
      <p>Đăng nhập bằng tài khoản Đầu Tư Tương Lai để tải. Chưa có tài khoản? <a href="<?php echo esc_url(add_query_arg('redirect_to', dttl_apps_page_url(), wp_registration_url())); ?>">Đăng ký miễn phí</a>.</p>
      <small>Bản thử nghiệm, chưa ký số. Cần .NET Framework 4.8. Chưa có bản macOS, Linux hoặc Windows ARM được kiểm thử.</small>
    </section>
    <?php return ob_get_clean();
});

add_action('admin_menu', function () {
    add_management_page('Ứng dụng miễn phí', 'Ứng dụng miễn phí', 'manage_options', 'dttl-free-apps', 'dttl_apps_admin');
});
function dttl_apps_admin() {
    if (!current_user_can('manage_options')) { return; }
    echo '<div class="wrap"><h1>DTTL Ứng dụng miễn phí</h1>';
    echo '<p>DTTL Chia màn hình 0.1 beta — ' . (dttl_apps_ready() ? 'Đã có gói tải.' : 'Chưa đóng gói payload; cài bản ZIP được tạo bởi workflow.') . '</p>';
    echo '<p>Lượt yêu cầu tải của thành viên (không phải số người dùng duy nhất): ' . esc_html((string) get_option('dttl_apps_downloads', 0)) . '</p>';
    $id = (int) get_option('dttl_apps_page_id');
    if ($id && get_post($id)) {
        echo '<p><a href="' . esc_url(get_edit_post_link($id)) . '">Sửa trang giới thiệu</a> · <a href="' . esc_url(get_permalink($id)) . '">Xem trang</a></p>';
    } else {
        echo '<p>Tạo trang giới thiệu có bài SEO và nút tải. Không thay đổi menu hoặc bài hiện có.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('dttl_apps_publish');
        echo '<input type="hidden" name="action" value="dttl_apps_publish">';
        submit_button('Tạo và xuất bản trang ứng dụng'); echo '</form>';
    }
    echo '<p>Shortcode: <code>[dttl_screen_layout_download]</code>. Có thể chèn vào các bài liên quan. Gỡ plugin không xóa trang hoặc số lượt tải.</p></div>';
}
add_action('admin_post_dttl_apps_publish', function () {
    if (!current_user_can('manage_options')) { wp_die('Không có quyền.', '', array('response' => 403)); }
    check_admin_referer('dttl_apps_publish');
    if (!dttl_apps_ready()) { wp_die('Cần cài gói có file ứng dụng trước khi xuất bản.'); }
    $old = (int) get_option('dttl_apps_page_id');
    if (!$old || !get_post($old)) {
        $existing = get_page_by_path('phan-mem-chia-man-hinh-may-tinh');
        if ($existing) { wp_die('Đã có trang cùng đường dẫn. Hãy chỉnh trang hiện có; plugin không ghi đè nội dung.'); }
        $content = file_get_contents(__DIR__ . '/article.html');
        $id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Phần mềm chia màn hình máy tính miễn phí cho Windows 10/11','post_name'=>'phan-mem-chia-man-hinh-may-tinh','post_content'=>$content,'post_excerpt'=>'Chia màn hình máy tính thành 2, 3 hoặc 4 ô với DTTL. Chọn cửa sổ, chỉnh tỷ lệ và sắp xếp nhanh. Đăng ký tài khoản để tải miễn phí.'), true);
        if (is_wp_error($id)) { wp_die(esc_html($id->get_error_message())); }
        update_option('dttl_apps_page_id', $id, false);
        update_post_meta($id, 'rank_math_title', 'Phần mềm chia màn hình máy tính miễn phí | DTTL');
        update_post_meta($id, 'rank_math_description', 'Tải DTTL Chia màn hình cho Windows 10/11: bố cục 2–4 ô, chỉnh tỷ lệ, chọn màn hình và hoàn tác. Miễn phí cho thành viên Đầu Tư Tương Lai.');
        update_post_meta($id, 'rank_math_focus_keyword', 'phần mềm chia màn hình máy tính');
    }
    wp_safe_redirect(admin_url('tools.php?page=dttl-free-apps')); exit;
});
