<?php
if (!defined('ABSPATH')) exit;

function dttl_long_term_pack() {
    static $items = null;
    if ($items === null) $items = json_decode(file_get_contents(get_template_directory().'/long-term-pack-01.json'), true);
    return is_array($items) ? $items : array();
}

function dttl_long_term_section() {
    echo '<section id="thuc-hanh-dai-han" class="dttl-panel"><span class="eyebrow dark">ĐẦU TƯ DÀI HẠN</span><h2>5 bài thực hành để theo đuổi mục tiêu dài hạn</h2><p>Đọc tài liệu, ghi lý do đầu tư, tính tỷ trọng và kiểm tra sức mua. Học theo thứ tự; mọi ví dụ là tình huống giả định.</p><div class="lesson-list">';
    foreach (dttl_long_term_pack() as $i => $item) {
        $post = get_page_by_path($item['slug'], OBJECT, 'post');
        if (!$post || $post->post_status !== 'publish') continue;
        echo '<a class="lesson-row" href="'.esc_url(get_permalink($post)).'"><span class="lesson-number">'.esc_html($i + 1).'</span><div><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['excerpt']).'</p></div><span class="lesson-read">Đọc bài →</span></a>';
    }
    echo '</div></section>';
}

add_filter('the_content', function ($html) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $html;
    $items = dttl_long_term_pack();
    $slug = get_post_field('post_name', get_the_ID());
    foreach ($items as $i => $item) {
        if ($item['slug'] !== $slug) continue;
        $html .= '<nav class="lesson-navigation" aria-label="Gói dài hạn"><a href="'.esc_url(home_url('/category/dau-tu-dai-han/#thuc-hanh-dai-han')).'">← Mục lục 5 bài dài hạn</a>';
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
    if (!current_user_can('manage_options') || wp_doing_ajax() || get_option('dttl_long_term_194_done')) return;
    $category = get_category_by_slug('dau-tu-dai-han');
    if (!$category) return;
    $added = 0;
    foreach (dttl_long_term_pack() as $item) {
        if (get_page_by_path($item['slug'], OBJECT, 'post')) continue;
        if ($added >= 2) return;
        $id = wp_insert_post(array(
            'post_type' => 'post', 'post_name' => $item['slug'], 'post_title' => $item['title'],
            'post_excerpt' => $item['excerpt'], 'post_content' => $item['content'],
            'post_status' => 'publish', 'post_category' => array($category->term_id), 'comment_status' => 'closed'
        ), true);
        if (is_wp_error($id) || !$id) return;
        update_post_meta($id, '_dttl_long_term_pack', '01');
        $added++;
    }
    update_option('dttl_long_term_194_done', 1);
});
