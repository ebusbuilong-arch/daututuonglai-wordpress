<?php
/** Plugin Name: DTTL Modular Tools Hub
 * Description: Independent investment tools, bilingual catalog and legacy links.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) { exit; }
function dttl_th_registry() {
    static $items;
    if ($items === null) { $items = json_decode(file_get_contents(__DIR__.'/dttl-tools-hub/registry.json'), true); }
    return $items;
}
function dttl_th_en() { global $TRP_LANGUAGE; return strpos((string)$TRP_LANGUAGE, 'en') === 0; }
function dttl_th_t($pair) { return $pair[dttl_th_en() ? 1 : 0]; }
function dttl_th_url($slug = '') {
    $url = home_url('/cong-cu-mien-phi/'.($slug ? $slug.'/' : ''));
    if (dttl_th_en() && class_exists('TRP_Translate_Press')) {
        $converter = TRP_Translate_Press::get_trp_instance()->get_component('url_converter');
        $url = $converter->get_url_for_language('en_US', $url, '');
    }
    return $url;
}
// Create only missing tool pages. Never replace existing content or progress records.
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || get_option('dttl_th_pages_version') === '1') { return; }
    $parent = get_page_by_path('cong-cu-mien-phi');
    if (!$parent) { return; }
    $ok = true;
    foreach (dttl_th_registry() as $key => $tool) {
        $page = get_page_by_path('cong-cu-mien-phi/'.$tool['slug']);
        if ($page) { if (get_post_meta($page->ID, '_dttl_tool_key', true) !== $key) { $ok = false; } continue; }
        $id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_parent'=>$parent->ID,
            'post_name'=>$tool['slug'],'post_title'=>$tool['title'][0],
            'post_content'=>'[dttl_tool key="'.$key.'"]','meta_input'=>array('_dttl_tool_key'=>$key)), true);
        if (is_wp_error($id)) { $ok = false; }
    }
    if ($ok) { update_option('dttl_th_pages_version', '1', false); }
});
function dttl_th_key() { return is_page() ? get_post_meta(get_queried_object_id(), '_dttl_tool_key', true) : ''; }
add_action('wp_enqueue_scripts', function () {
    $base = plugins_url('dttl-tools-hub/', __FILE__);
    wp_enqueue_script('dttl-tool-links', $base.'links.js', array(), '1.0.0', true);
    $key = dttl_th_key();
    if (!is_page(array('cong-cu-mien-phi','cong-cu-dau-tu')) && !$key && !is_singular('dttl_app')) { return; }
    wp_enqueue_style('dttl-tools-hub', $base.'tools.css', array(), '1.0.0');
    if (isset(dttl_th_registry()[$key])) {
        wp_enqueue_script('dttl-tools-core', $base.'core.js', array(), '1.0.0', true);
        if (file_exists(__DIR__.'/dttl-tools-hub/modules/'.$key.'.js')) {
            wp_enqueue_script('dttl-tool-'.$key, $base.'modules/'.$key.'.js', array('dttl-tools-core'), '1.0.0', true);
        }
    } else { wp_enqueue_script('dttl-tools-catalog', $base.'catalog.js', array(), '1.0.0', true); }
}, 99);
add_filter('the_title', function ($title, $id) {
    if (is_admin()) { return $title; }
    $key = get_post_meta($id, '_dttl_tool_key', true);
    $tools = dttl_th_registry();
    return isset($tools[$key]) ? dttl_th_t($tools[$key]['title']) : $title;
}, 20, 2);
function dttl_th_catalog() {
    ob_start(); ?>
    <div class="dttl-th" data-tool-catalog>
    <p><?php echo esc_html(dttl_th_t(array('Chọn từng công cụ để sử dụng trực tuyến hoặc tải ứng dụng. Các máy tính dùng dữ liệu bạn nhập và không gửi lệnh giao dịch.','Choose a tool to use online or download an app. Calculators use your inputs and do not place trades.'))); ?></p>
    <form class="th-filters" role="search"><label><?php echo esc_html(dttl_th_t(array('Tìm công cụ','Find a tool'))); ?><input type="search" name="tim" placeholder="<?php echo esc_attr(dttl_th_t(array('Tên hoặc chức năng','Name or function'))); ?>"></label><label><?php echo esc_html(dttl_th_t(array('Nhóm công cụ','Tool group'))); ?><select name="nhom"><option value=""><?php echo esc_html(dttl_th_t(array('Tất cả nhóm','All groups'))); ?></option><option value="investment"><?php echo esc_html(dttl_th_t(array('Tính toán đầu tư','Investment calculators'))); ?></option><option value="learning"><?php echo esc_html(dttl_th_t(array('Học tập & thực hành','Learning & practice'))); ?></option><option value="apps"><?php echo esc_html(dttl_th_t(array('Ứng dụng','Apps'))); ?></option></select></label><button type="submit"><?php echo esc_html(dttl_th_t(array('Tìm kiếm','Search'))); ?></button></form>
    <p class="th-count" role="status"></p><div class="th-grid">
    <?php foreach (dttl_th_registry() as $key=>$tool): ?>
    <article class="th-card" data-group="<?php echo in_array($key,array('journal','checklist','progress'),true)?'learning':'investment'; ?>"><span class="th-icon" aria-hidden="true"><?php echo esc_html(array('profit'=>'±','average'=>'÷','recovery'=>'↗','allocation'=>'%','compound'=>'Σ','journal'=>'▤','checklist'=>'✓','progress'=>'7')[$key]); ?></span><h2><?php echo esc_html(dttl_th_t($tool['title'])); ?></h2><p><?php echo esc_html(dttl_th_t($tool['description'])); ?></p><a class="th-button" href="<?php echo esc_url(dttl_th_url($tool['slug'])); ?>"><?php echo esc_html(dttl_th_t(array('Mở công cụ','Open tool'))); ?> →</a></article>
    <?php endforeach;
    $apps=get_posts(array('post_type'=>'dttl_app','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
    foreach ($apps as $app): $url=get_post_meta($app->ID,'_dttl_online_url',true); ?>
    <article class="th-card" data-group="apps"><span class="th-icon" aria-hidden="true">▦</span><h2><?php echo esc_html(get_the_title($app)); ?></h2><p><?php echo esc_html(dttl_th_t($url?array('Dùng ngay trên trình duyệt.','Use directly in your browser.'):array('Xem hướng dẫn và bản tải dành cho thiết bị của bạn.','View instructions and downloads for your device.'))); ?></p><a class="th-button" href="<?php echo esc_url($url ?: get_permalink($app)); ?>"><?php echo esc_html(dttl_th_t($url?array('Mở ứng dụng','Open app'):array('Tải & hướng dẫn','Download & guide'))); ?> →</a></article>
    <?php endforeach; ?></div><p class="th-empty" hidden><?php echo esc_html(dttl_th_t(array('Chưa tìm thấy công cụ phù hợp. Thử từ khóa khác hoặc chọn tất cả nhóm.','No matching tools. Try another keyword or select all groups.'))); ?></p></div>
    <?php return ob_get_clean();
}
add_filter('the_content', function ($content) {
    if (!is_admin() && is_main_query() && in_the_loop() && is_page(array('cong-cu-mien-phi','cong-cu-dau-tu'))) { return dttl_th_catalog(); }
    return $content;
}, 30);
add_shortcode('dttl_tool', function ($atts) {
    $key = sanitize_key($atts['key'] ?? ''); $tools=dttl_th_registry();
    if (!isset($tools[$key])) { return ''; } $tool=$tools[$key];
    ob_start(); ?>
    <div class="dttl-th" data-tool-page="<?php echo esc_attr($key); ?>" data-lang="<?php echo dttl_th_en()?'en':'vi'; ?>" <?php if ($key !== 'progress') { echo 'data-no-translation'; } ?>>
    <nav class="th-nav"><a href="<?php echo esc_url(dttl_th_url()); ?>">← <?php echo esc_html(dttl_th_t(array('Tất cả công cụ','All tools'))); ?></a></nav>
    <p><?php echo esc_html(dttl_th_t($tool['description'])); ?></p>
    <section class="th-panel">
    <?php if ($tool['fields']): ?>
    <form data-calculator="<?php echo esc_attr($key); ?>"><div class="th-fields">
    <?php foreach ($tool['fields'] as $field): ?><label for="th-<?php echo esc_attr($field['id']); ?>"><?php echo esc_html(dttl_th_t($field['label'])); ?><input id="th-<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr($field['id']); ?>" type="number" inputmode="decimal" min="<?php echo esc_attr($field['min']); ?>" max="<?php echo esc_attr($field['max']); ?>" step="<?php echo esc_attr($field['step']); ?>" value="<?php echo esc_attr($field['value']); ?>" required></label><?php endforeach; ?>
    </div><p class="th-help"><?php echo esc_html(dttl_th_t(array('Nhập số không có dấu phân cách hàng nghìn; dùng cùng đơn vị tiền trong một phép tính.','Enter numbers without thousands separators; use the same currency unit throughout a calculation.'))); ?></p><button type="submit"><?php echo esc_html(dttl_th_t(array('Tính kết quả','Calculate'))); ?></button><div class="th-result" role="status" aria-live="polite"></div></form>
    <details><summary><?php echo esc_html(dttl_th_t(array('Công thức và giả định','Formula and assumptions'))); ?></summary><p><?php echo esc_html(dttl_th_t($tool['note'])); ?></p></details>
    <?php else: include __DIR__.'/dttl-tools-hub/modules/'.$key.'.php'; endif; ?>
    </section><noscript><?php echo esc_html(dttl_th_t(array('Bật JavaScript để sử dụng chức năng tương tác.','Enable JavaScript to use interactive features.'))); ?></noscript>
    </div><?php return ob_get_clean();
});
