<?php
if (!defined('ABSPATH')) exit;

function dttl_app_guides() {
    return get_posts(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>9,'meta_key'=>'_dttl_app_order','orderby'=>'meta_value_num','order'=>'ASC'));
}

function dttl_app_navigation() {
    $app=get_post_meta(get_the_ID(),'_dttl_app_name',true);
    $part=(int)get_post_meta(get_the_ID(),'_dttl_app_part',true);
    $prev=null; $next=null;
    foreach(dttl_app_guides() as $post) {
        if(get_post_meta($post->ID,'_dttl_app_name',true)!==$app) continue;
        $n=(int)get_post_meta($post->ID,'_dttl_app_part',true);
        if($n===$part-1) $prev=$post;
        if($n===$part+1) $next=$post;
    }
    echo '<nav class="lesson-navigation" aria-label="Các bài hướng dẫn app">';
    foreach(array(array($prev,'← Bài trước'),array($next,'Bài tiếp theo →')) as $item) {
        if($item[0]) echo '<a href="'.esc_url(get_permalink($item[0]->ID)).'"><small>'.esc_html($item[1]).'</small><strong>'.esc_html($item[0]->post_title).'</strong></a>';
        else echo '<a href="'.esc_url(home_url('/huong-dan-app-chung-khoan/')).'"><small>Tra cứu theo app</small><strong>SSI · VNDIRECT · VPS →</strong></a>';
    }
    echo '</nav>';
}

function dttl_install_app_guides_01() {
    if(!current_user_can('manage_options') || get_option('dttl_app_guides_01')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/app-guides-pack-01.json'),true);
    if(!is_array($pack) || count($pack)!==9) return;
    $category=get_category_by_slug('huong-dan-app');
    if(!$category) {
        $term=wp_insert_term('Hướng dẫn app chứng khoán','category',array('slug'=>'huong-dan-app'));
        if(is_wp_error($term)) return;
        $category=get_category($term['term_id']);
    }
    $page=get_page_by_path('huong-dan-app-chung-khoan');
    if(!$page) {
        $page_id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Hướng dẫn app chứng khoán','post_name'=>'huong-dan-app-chung-khoan','post_content'=>'Hướng dẫn thao tác SSI iBoard Pro, VNDIRECT DGO/DSTOCK và VPS SmartOne cho người mới.','post_excerpt'=>'Tra cứu 9 bài hướng dẫn: làm quen app, giao dịch tiền và quản lý lệnh.','comment_status'=>'closed'),true);
        if(is_wp_error($page_id)||!$page_id) return;
    }
    $ids=get_option('dttl_app_guides_01_ids',array());
    foreach($pack as $guide) {
        if(isset($ids[$guide['slug']]) && get_post($ids[$guide['slug']])) continue;
        $found=get_posts(array('name'=>$guide['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($found) $ids[$guide['slug']]=$found[0]->ID;
        else {
            $id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>$guide['title'],'post_name'=>$guide['slug'],'comment_status'=>'closed'),true);
            if(is_wp_error($id)||!$id) return;
            $ids[$guide['slug']]=$id;
        }
        update_option('dttl_app_guides_01_ids',$ids);
    }
    require_once ABSPATH.'wp-admin/includes/image.php';
    $processed=0; $images=get_option('dttl_app_guides_01_images',array());
    foreach($pack as $guide) {
        $id=$ids[$guide['slug']];
        if(get_post_meta($id,'_dttl_app_pack',true)==='01' && has_post_thumbnail($id)) continue;
        $file=$guide['image']; $image_id=isset($images[$file])?(int)$images[$file]:0;
        if(!$image_id || !get_post($image_id)) {
            $source=get_template_directory().'/assets/images/'.$file.'.webp';
            if(!is_file($source)||!filesize($source)) return;
            $upload=wp_upload_bits($file.'.webp',null,file_get_contents($source)); if(!empty($upload['error'])) return;
            $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>'Học thao tác app — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa AI, không phải màn hình ứng dụng chứng khoán.','post_status'=>'inherit'),$upload['file'],$id);
            if(is_wp_error($image_id)||!$image_id) return;
            $images[$file]=$image_id; update_option('dttl_app_guides_01_images',$images);
            wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
            update_post_meta($image_id,'_wp_attachment_image_alt','Minh họa học thao tác ứng dụng chứng khoán');
        }
        set_post_thumbnail($id,$image_id);
        update_post_meta($id,'_dttl_app_order',$guide['order']);
        update_post_meta($id,'_dttl_app_name',$guide['app']);
        update_post_meta($id,'_dttl_app_part',$guide['part']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$guide['title'],'post_excerpt'=>$guide['excerpt'],'post_content'=>dttl_resolve_lesson_links($guide['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;
        update_post_meta($id,'_dttl_app_pack','01');
        if(++$processed>=3) break;
    }
    $finished=true;
    foreach($pack as $guide) if(get_post_meta($ids[$guide['slug']],'_dttl_app_pack',true)!=='01') {$finished=false;break;}
    if($finished) update_option('dttl_app_guides_01',array('published'=>9,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    if($processed && function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_app_guides_01');
