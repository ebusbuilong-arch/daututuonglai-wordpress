<?php
if (!defined('ABSPATH')) { exit; }

function dttl_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script'));
    add_theme_support('custom-logo', array('height'=>64,'width'=>220,'flex-height'=>true,'flex-width'=>true));
    register_nav_menus(array('primary'=>__('Menu chính','daututuonglai'),'footer'=>__('Menu chân trang','daututuonglai')));
}
add_action('after_setup_theme','dttl_setup');

function dttl_assets() {
    wp_enqueue_style('dttl-main', get_template_directory_uri().'/assets/css/main.css', array(), '0.2.0');
    wp_enqueue_script('dttl-main', get_template_directory_uri().'/assets/js/main.js', array(), '0.2.0', true);
}
add_action('wp_enqueue_scripts','dttl_assets');

function dttl_excerpt_length($length) { return 24; }
add_filter('excerpt_length', 'dttl_excerpt_length', 999);

function dttl_categories() {
    return array(
        'kien-thuc-chung-khoan' => 'Kiến thức chứng khoán',
        'dau-tu-ngan-han' => 'Đầu tư ngắn hạn',
        'dau-tu-dai-han' => 'Đầu tư dài hạn',
        'tin-tuc-chung-khoan' => 'Tin tức chứng khoán',
        'goc-chia-se' => 'Góc chia sẻ',
        'kinh-te-tuong-lai' => 'Kinh tế tương lai',
    );
}
function dttl_category_url($slug) {
    $cat = get_category_by_slug($slug);
    return $cat ? get_category_link($cat->term_id) : add_query_arg('category_name', $slug, home_url('/'));
}
function dttl_install_content() {
    if (get_option('dttl_starter_done')) return;
    foreach (dttl_categories() as $slug => $name) {
        if (!term_exists($slug, 'category')) wp_insert_term($name, 'category', array('slug'=>$slug));
    }
    $pages = array('Gioi thieu'=>'Giới thiệu','Cong cu'=>'Công cụ đầu tư','Lo trinh hoc'=>'Lộ trình học','Chinh sach bien tap'=>'Chính sách biên tập');
    foreach ($pages as $key=>$title) {
        $slug = sanitize_title($key);
        if (!get_page_by_path($slug)) {
            wp_insert_post(array('post_title'=>$title,'post_name'=>$slug,'post_type'=>'page','post_status'=>'publish','post_content'=>'Nội dung đang được biên tập.'));
        }
    }
    update_option('dttl_starter_done',1);
}
add_action('after_switch_theme','dttl_install_content');

function dttl_posts_url() {
    $page_id = (int) get_option('page_for_posts');
    return $page_id ? get_permalink($page_id) : add_query_arg('s', '', home_url('/'));
}
