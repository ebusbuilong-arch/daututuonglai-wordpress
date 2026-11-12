<?php
/**
 * Plugin Name: DTTL Lesson Recordings
 * Description: Shows the matching official YouTube replay on selected lesson posts.
 */

defined('ABSPATH') || exit;

function dttl_lesson_recording_map() {
    return [
        'doc-bao-cao-quy-truoc-khi-tich-luy' => ['4Cc3qLimAho', '061'],
        'tai-can-bang-bang-tien-gop-moi' => ['8zpjRxDd4sw', '062'],
        'loi-nhuan-thuc-sau-lam-phat-dai-han' => ['cphO8ylOMXU', '063'],
        'ra-soat-danh-muc-khi-gan-ngay-can-tien' => ['9AKw8_Hzffo', '064'],
        'phieu-ke-hoach-giao-dich-ngan-han' => ['yVIdXPUG6RQ', '065'],
        'spread-truot-gia-va-chi-phi-ngan-han' => ['bnf4NfPGbBg', '066'],
        'ty-le-lai-lo-ky-vong-giao-dich' => ['D9WnKx98rl4', '067'],
        'sut-giam-von-va-muc-hoi-phuc' => ['4ckX28hwGrE', '068'],
        'danh-gia-giao-dich-thu-khong-chon-ket-qua-dep' => ['ykpmtDam6zw', '069'],
        'crypto-la-gi-nguoi-moi' => ['WN6AVfcxu4E', '070'],
    ];
}

add_action('wp_loaded', static function () {
    $option = 'dttl_replay_cache_refreshed_061_070';
    if (!function_exists('rocket_clean_files') || get_option($option)) {
        return;
    }

    $urls = [];
    foreach (array_keys(dttl_lesson_recording_map()) as $slug) {
        $post = get_page_by_path($slug, OBJECT, 'post');
        if ($post && $post->post_status === 'publish') {
            $url = get_permalink($post);
            if ($url) {
                $urls[] = $url;
            }
        }
    }
    if (count($urls) === count(dttl_lesson_recording_map())) {
        rocket_clean_files($urls);
        update_option($option, 1, false);
    }
}, 20);

add_filter('the_content', static function ($content) {
    if (is_admin() || !is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $recordings = dttl_lesson_recording_map();
    $slug = get_post_field('post_name', get_the_ID());
    if (!isset($recordings[$slug])) {
        return $content;
    }

    [$video, $number] = $recordings[$slug];
    if (strpos($content, $video) !== false || strpos($content, 'dttl-lesson-recording') !== false) {
        return $content;
    }

    $locale = defined('TRP_LANGUAGE') ? TRP_LANGUAGE : get_locale();
    $english = strpos($locale, 'en') === 0;
    $heading = $english ? 'Watch this lesson' : 'Xem video bài học';
    $button = $english ? 'Watch on YouTube' : 'Mở video trên YouTube';
    $url = 'https://www.youtube.com/watch?v=' . $video;

    $recording = '<section class="dttl-lesson-recording" aria-label="' . esc_attr($heading . ' ' . $number) . '" style="margin:24px 0;padding:20px;border:1px solid #d5e8e3;border-radius:16px;background:#f3faf8">';
    $recording .= '<h2 style="margin-top:0">' . esc_html($heading . ' · ' . $number) . '</h2>';
    $recording .= '<p style="margin-bottom:0"><a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block;padding:12px 20px;background:#cf2028;color:#fff;border-radius:10px;text-decoration:none;font-weight:600">▶ ' . esc_html($button) . ' ↗</a></p>';
    $recording .= '</section>';

    return $recording . $content;
}, 9999);
