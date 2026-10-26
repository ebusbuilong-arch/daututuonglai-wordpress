<?php
if (!defined('ABSPATH')) exit;

function dttl_future_economy_pack() {
    static $items = null;
    if ($items === null) $items = json_decode(file_get_contents(get_template_directory().'/future-economy-pack-01.json'), true);
    return is_array($items) ? $items : array();
}

function dttl_future_economy_section() {
    echo '<section id="thuc-hanh-kinh-te-tuong-lai" class="dttl-panel"><span class="eyebrow dark">KINH TẾ TƯƠNG LAI</span><h2>5 bài thực hành để tự kiểm chứng xu hướng</h2><p>Đo hiệu quả AI, đọc chuỗi hạ tầng và cung–cầu, phân biệt dự báo với dữ kiện. Kết thúc bằng một bảng theo dõi trong 7 ngày.</p><div class="lesson-list">';
    foreach (dttl_future_economy_pack() as $i => $item) {
        $post = get_page_by_path($item['slug'], OBJECT, 'post');
        if (!$post || $post->post_status !== 'publish') continue;
        echo '<a class="lesson-row" href="'.esc_url(get_permalink($post)).'"><span class="lesson-number">'.esc_html($i + 1).'</span><div><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['excerpt']).'</p></div><span class="lesson-read">Đọc bài →</span></a>';
    }
    echo '</div></section>';
}

add_filter('the_content', function ($html) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $html;
    $items = dttl_future_economy_pack();
    $slug = get_post_field('post_name', get_the_ID());
    foreach ($items as $i => $item) {
        if ($item['slug'] !== $slug) continue;
        $html .= '<nav class="lesson-navigation" aria-label="Gói kinh tế tương lai"><a href="'.esc_url(home_url('/category/kinh-te-tuong-lai/#thuc-hanh-kinh-te-tuong-lai')).'">← Mục lục 5 bài kinh tế tương lai</a>';
        foreach (array($i - 1 => 'Bài trước', $i + 1 => 'Bài tiếp theo') as $j => $label) {
            if (!isset($items[$j])) continue;
            $post = get_page_by_path($items[$j]['slug'], OBJECT, 'post');
            if ($post && $post->post_status === 'publish') $html .= '<a href="'.esc_url(get_permalink($post)).'">'.esc_html($label.': '.$post->post_title).'</a>';
        }
        $html .= '</nav>';
        break;
    }
    return $html;
}, 25);

// Owner requests only; add at most two missing posts and never overwrite existing content.
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || wp_doing_ajax() || get_option('dttl_future_economy_196_done')) return;
    $category = get_category_by_slug('kinh-te-tuong-lai');
    if (!$category) return;
    $added = 0;
    foreach (dttl_future_economy_pack() as $item) {
        if (get_page_by_path($item['slug'], OBJECT, 'post')) continue;
        if ($added >= 2) return;
        $id = wp_insert_post(array(
            'post_type' => 'post', 'post_name' => $item['slug'], 'post_title' => $item['title'],
            'post_excerpt' => $item['excerpt'], 'post_content' => $item['content'],
            'post_status' => 'publish', 'post_category' => array($category->term_id), 'comment_status' => 'closed'
        ), true);
        if (is_wp_error($id) || !$id) return;
        update_post_meta($id, '_dttl_future_economy_pack', '01');
        update_post_meta($id, '_dttl_source_checked', '2026-10-13');
        $cover_post = get_page_by_path($item['cover_from'], OBJECT, 'post');
        if ($cover_post && has_post_thumbnail($cover_post->ID)) {
            set_post_thumbnail($id, get_post_thumbnail_id($cover_post->ID));
        }
        $added++;
    }
    update_option('dttl_future_economy_196_done', 1);
    if (function_exists('rocket_clean_domain')) rocket_clean_domain();
});
