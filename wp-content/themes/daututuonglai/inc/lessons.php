<?php
if (!defined('ABSPATH')) exit;
function dttl_lessons() {
    static $lessons = null;
    if ($lessons === null) {
        $lessons = get_posts(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>30,'meta_key'=>'_dttl_lesson_order','orderby'=>'meta_value_num','order'=>'ASC'));
    }
    return $lessons;
}
function dttl_lesson_navigation() {
    $number = (int) get_post_meta(get_the_ID(),'_dttl_lesson_order',true);
    if (!$number) { the_post_navigation(); return; }
    $lessons=dttl_lessons(); $prev=null; $next=null;
    foreach($lessons as $lesson) {
        $order=(int)get_post_meta($lesson->ID,'_dttl_lesson_order',true);
        if($order===$number-1) $prev=$lesson;
        if($order===$number+1) $next=$lesson;
    }
    echo '<nav class="lesson-navigation" aria-label="Điều hướng bài học">';
    if($prev) echo '<a href="'.esc_url(get_permalink($prev->ID)).'"><small>← Bài trước</small><strong>'.esc_html($prev->post_title).'</strong></a>';
    else echo '<a href="'.esc_url(home_url('/lo-trinh-hoc/')).'"><small>Lộ trình học</small><strong>Bắt đầu từ kiến thức nền tảng</strong></a>';
    if($next) echo '<a href="'.esc_url(get_permalink($next->ID)).'"><small>Bài tiếp theo →</small><strong>'.esc_html($next->post_title).'</strong></a>';
    else echo '<a href="'.esc_url(home_url('/lo-trinh-hoc/')).'"><small>Đã đọc hết các bài đang có</small><strong>Xem lộ trình và các bài đã sẵn sàng →</strong></a>';
    echo '</nav>';
}
function dttl_resolve_lesson_links($body,$ids) {
    return preg_replace_callback('/\{\{(post|page):([a-z0-9-]+)\}\}/',function($match) use($ids) {
        if(isset($ids[$match[2]])) return esc_url(get_permalink($ids[$match[2]]));
        if($match[1]==='page') {$page=get_page_by_path($match[2]); if($page && $page->post_status==='publish') return esc_url(get_permalink($page->ID));}
        $found=get_posts(array('name'=>$match[2],'post_type'=>$match[1],'post_status'=>'publish','numberposts'=>1));
        return $found?esc_url(get_permalink($found[0]->ID)):esc_url(home_url('/lo-trinh-hoc/'));
    },$body);
}
function dttl_install_lessons_pack_01() {
    if(!current_user_can('manage_options') || get_option('dttl_lessons_pack_01')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/lessons-pack-01.json'),true);
    if(!is_array($pack) || count($pack)!==7) return;
    $category=get_category_by_slug('kien-thuc-chung-khoan');
    if(!$category) return;
    $ids=array();
    foreach($pack as $lesson) {
        $existing=get_posts(array('name'=>$lesson['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($existing) $ids[$lesson['slug']]=$existing[0]->ID;
        else {
            $id=wp_insert_post(array('post_title'=>$lesson['title'],'post_name'=>$lesson['slug'],'post_type'=>'post','post_status'=>'draft','post_category'=>array($category->term_id),'comment_status'=>'closed'),true);
            if(is_wp_error($id)||!$id) return;
            $ids[$lesson['slug']]=$id;
        }
    }
    require_once ABSPATH.'wp-admin/includes/image.php';
    $new_images=array();
    foreach($pack as $lesson) {
        $id=$ids[$lesson['slug']];
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$lesson['title'],'post_excerpt'=>$lesson['excerpt'],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;
        update_post_meta($id,'_dttl_lesson_order',$lesson['order']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        update_post_meta($id,'_dttl_lesson_pack','01');
        $file=$lesson['image'];
        if(in_array($file,array('hoc-dau-tu-tuong-lai','tich-luy-dai-han'),true) && has_post_thumbnail($id)) continue;
        if(isset($new_images[$file])) {set_post_thumbnail($id,$new_images[$file]);continue;}
        $source=get_template_directory().'/assets/images/'.$file.'.webp';
        if(!is_file($source)) continue;
        $upload=wp_upload_bits($file.'.webp',null,file_get_contents($source));
        if(!empty($upload['error'])) continue;
        $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$lesson['title'].' — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa được tạo bằng AI; không phải dữ liệu thị trường.','post_status'=>'inherit'),$upload['file'],$id);
        if(is_wp_error($image_id)||!$image_id) continue;
        wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
        update_post_meta($image_id,'_wp_attachment_image_alt',$lesson['title'].' — ảnh minh họa');
        set_post_thumbnail($id,$image_id);$new_images[$file]=$image_id;
    }
    $roadmap=get_page_by_path('lo-trinh-hoc');
    if($roadmap) wp_update_post(array('ID'=>$roadmap->ID,'post_content'=>'Lộ trình 30 bài qua 5 chặng. Chặng đầu gồm 7 bài nền tảng đã xuất bản; các chặng sau sẽ được bổ sung khi biên tập hoàn tất.'));
    $pillar=get_page_by_path('kien-thuc-chung-khoan');
    if(!$pillar) wp_insert_post(array('post_title'=>'Học chứng khoán cho người mới','post_name'=>'kien-thuc-chung-khoan','post_type'=>'page','post_status'=>'publish','post_excerpt'=>'Bắt đầu từ khái niệm, đọc lần lượt 7 bài nền tảng và thực hành quan sát thị trường.','post_content'=>'Lộ trình kiến thức chứng khoán cho người mới bắt đầu.','comment_status'=>'closed'));
    update_option('dttl_lessons_pack_01',array('published'=>7,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    if(function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_lessons_pack_01');

function dttl_install_lessons_pack_02() {
    if(!current_user_can('manage_options') || get_option('dttl_lessons_pack_02')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/lessons-pack-02.json'),true);
    if(!is_array($pack) || count($pack)!==6) return;
    $category=get_category_by_slug('kien-thuc-chung-khoan');
    if(!$category) return;
    $ids=array();
    foreach($pack as $lesson) {
        $existing=get_posts(array('name'=>$lesson['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($existing) $ids[$lesson['slug']]=$existing[0]->ID;
        else {
            $id=wp_insert_post(array('post_title'=>$lesson['title'],'post_name'=>$lesson['slug'],'post_type'=>'post','post_status'=>'draft','post_category'=>array($category->term_id),'comment_status'=>'closed'),true);
            if(is_wp_error($id)||!$id) return;
            $ids[$lesson['slug']]=$id;
        }
    }
    require_once ABSPATH.'wp-admin/includes/image.php';
    $new_images=array();
    foreach($pack as $lesson) {
        $result=wp_update_post(array('ID'=>$ids[$lesson['slug']],'post_status'=>'publish'),true);
        if(is_wp_error($result)||!$result) return;
    }
    foreach($pack as $lesson) {
        $id=$ids[$lesson['slug']];
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$lesson['title'],'post_excerpt'=>$lesson['excerpt'],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;
        update_post_meta($id,'_dttl_lesson_order',$lesson['order']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        update_post_meta($id,'_dttl_lesson_pack','02');
        $file=$lesson['image'];
        if(in_array($file,array('hoc-dau-tu-tuong-lai','tich-luy-dai-han'),true) && has_post_thumbnail($id)) continue;
        if(isset($new_images[$file])) {set_post_thumbnail($id,$new_images[$file]);continue;}
        $source=get_template_directory().'/assets/images/'.$file.'.webp';
        if(!is_file($source)) continue;
        $upload=wp_upload_bits($file.'.webp',null,file_get_contents($source));
        if(!empty($upload['error'])) continue;
        $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$lesson['title'].' — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa được tạo bằng AI; không phải dữ liệu thị trường.','post_status'=>'inherit'),$upload['file'],$id);
        if(is_wp_error($image_id)||!$image_id) continue;
        wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
        update_post_meta($image_id,'_wp_attachment_image_alt',$lesson['title'].' — ảnh minh họa');
        set_post_thumbnail($id,$image_id);$new_images[$file]=$image_id;
    }
    $roadmap=get_page_by_path('lo-trinh-hoc');
    if($roadmap) wp_update_post(array('ID'=>$roadmap->ID,'post_content'=>'Lộ trình 30 bài qua 5 chặng. Đã xuất bản 13 bài: 7 bài nền tảng và 6 bài thao tác, chi phí. 17 bài tiếp theo sẽ được bổ sung khi biên tập hoàn tất.'));
    $pillar=get_page_by_path('kien-thuc-chung-khoan');
    if(!$pillar) wp_insert_post(array('post_title'=>'Học chứng khoán cho người mới','post_name'=>'kien-thuc-chung-khoan','post_type'=>'page','post_status'=>'publish','post_excerpt'=>'Bắt đầu từ khái niệm, đọc lần lượt 7 bài nền tảng và thực hành quan sát thị trường.','post_content'=>'Lộ trình kiến thức chứng khoán cho người mới bắt đầu.','comment_status'=>'closed'));
    update_option('dttl_lessons_pack_02',array('published'=>6,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    if(function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_lessons_pack_02');

function dttl_install_lessons_pack_03() {
    if(!current_user_can('manage_options') || get_option('dttl_lessons_pack_03')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/lessons-pack-03.json'),true);
    if(!is_array($pack) || count($pack)!==17) return;
    $category=get_category_by_slug('kien-thuc-chung-khoan'); if(!$category) return;
    $ids=get_option('dttl_lessons_pack_03_ids',array());
    foreach($pack as $lesson) {
        if(isset($ids[$lesson['slug']]) && get_post($ids[$lesson['slug']])) continue;
        $existing=get_posts(array('name'=>$lesson['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($existing) $id=$existing[0]->ID;
        else $id=wp_insert_post(array('post_title'=>$lesson['title'],'post_name'=>$lesson['slug'],'post_type'=>'post','post_status'=>'draft','post_category'=>array($category->term_id),'comment_status'=>'closed'),true);
        if(is_wp_error($id)||!$id) return;
        $ids[$lesson['slug']]=$id; update_option('dttl_lessons_pack_03_ids',$ids);
    }
    require_once ABSPATH.'wp-admin/includes/image.php'; $processed=0;
    foreach($pack as $lesson) {
        $id=$ids[$lesson['slug']];
        if(get_post_meta($id,'_dttl_lesson_pack',true)==='03' && has_post_thumbnail($id)) continue;
        $image_id=(int)get_post_meta($id,'_dttl_pack03_image',true);
        if(!$image_id || !get_post($image_id)) {
            $source=get_template_directory().'/assets/images/'.$lesson['image'].'.webp'; if(!is_file($source) || !filesize($source)) return;
            $upload=wp_upload_bits($lesson['image'].'.webp',null,file_get_contents($source)); if(!empty($upload['error'])) return;
            $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$lesson['title'].' — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa được tạo bằng AI; không phải dữ liệu thị trường.','post_status'=>'inherit'),$upload['file'],$id);
            if(is_wp_error($image_id)||!$image_id) return;
            update_post_meta($id,'_dttl_pack03_image',$image_id);
            wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
            update_post_meta($image_id,'_wp_attachment_image_alt',$lesson['title'].' — ảnh minh họa');
        }
        set_post_thumbnail($id,$image_id);
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$lesson['title'],'post_excerpt'=>$lesson['excerpt'],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;
        update_post_meta($id,'_dttl_lesson_order',$lesson['order']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        update_post_meta($id,'_dttl_lesson_pack','03');
        $processed++; if($processed>=3) break;
    }
    $finished=true; foreach($pack as $lesson) if(get_post_meta($ids[$lesson['slug']],'_dttl_lesson_pack',true)!=='03') {$finished=false;break;}
    if($finished) {
        // Resolve every cross-link again after all target lessons are published.
        foreach($pack as $lesson) wp_update_post(array('ID'=>$ids[$lesson['slug']],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids)));
        $roadmap=get_page_by_path('lo-trinh-hoc'); if($roadmap) wp_update_post(array('ID'=>$roadmap->ID,'post_content'=>'Lộ trình học chứng khoán 30 bài qua 5 chặng. Cả 30 bài đã xuất bản, có ví dụ, bài tập và nguồn tham khảo.'));
        update_option('dttl_lessons_pack_03',array('published'=>17,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    }
    if($processed && function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_lessons_pack_03');
