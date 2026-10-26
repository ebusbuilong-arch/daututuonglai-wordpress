<?php
if (!defined('ABSPATH')) exit;

// Install local optimized covers once, one image per owner page load; no scheduled work.
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || wp_doing_ajax() || get_option('dttl_article_covers_194_done')) return;
    $items = json_decode(file_get_contents(get_template_directory().'/article-covers-194.json'), true);
    if (!is_array($items)) return;
    $processed = 0;
    $missing = false;
    require_once ABSPATH.'wp-admin/includes/image.php';
    foreach ($items as $item) {
        $post = get_page_by_path($item['slug'], OBJECT, 'post');
        if (!$post || $post->post_status !== 'publish') { $missing = true; continue; }
        if (has_post_thumbnail($post->ID)) continue;
        if ($processed >= 1) return;
        $source = get_template_directory().'/assets/images/'.basename($item['image']);
        if (!is_file($source) || !filesize($source)) return;
        $image_id = (int) get_post_meta($post->ID, '_dttl_cover_194', true);
        if (!$image_id || !get_post($image_id)) {
            $upload = wp_upload_bits(basename($item['image']), null, file_get_contents($source));
            if (!empty($upload['error'])) return;
            $image_id = wp_insert_attachment(array(
                'post_mime_type' => 'image/webp', 'post_title' => $item['alt'],
                'post_excerpt' => 'Ảnh minh họa AI, không phải dữ liệu thị trường thực tế.', 'post_status' => 'inherit'
            ), $upload['file'], $post->ID, true);
            if (is_wp_error($image_id) || !$image_id) return;
            update_post_meta($post->ID, '_dttl_cover_194', $image_id);
            wp_update_attachment_metadata($image_id, wp_generate_attachment_metadata($image_id, $upload['file']));
            update_post_meta($image_id, '_wp_attachment_image_alt', $item['alt']);
        }
        set_post_thumbnail($post->ID, $image_id);
        if (!has_post_thumbnail($post->ID)) return;
        $processed++;
    }
    if (!$missing) {
        update_option('dttl_article_covers_194_done', 1);
        if (function_exists('rocket_clean_domain')) rocket_clean_domain();
    }
}, 30);
