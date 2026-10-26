<?php
if (!defined('ABSPATH')) exit;

function dttl_extra_posts() {
    return get_posts(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>29,'meta_key'=>'_dttl_extra_order','orderby'=>'meta_value_num','order'=>'ASC'));
}
function dttl_glossary_terms() {
    static $terms=null;
    if($terms===null) {$data=json_decode(file_get_contents(get_template_directory().'/stock-glossary.json'),true);$terms=is_array($data)?$data:array();}
    return $terms;
}
function dttl_extra_navigation() {
    $n=(int)get_post_meta(get_the_ID(),'_dttl_extra_order',true);$prev=null;$next=null;
    foreach(dttl_extra_posts() as $post) {
        $order=(int)get_post_meta($post->ID,'_dttl_extra_order',true);
        if($order===$n-1) $prev=$post;
        if($order===$n+1) $next=$post;
    }
    echo '<nav class="lesson-navigation" aria-label="Điều hướng bài đọc bổ sung">';
    foreach(array(array($prev,'← Đọc trước'),array($next,'Đọc tiếp →')) as $item) {
        if($item[0]) echo '<a href="'.esc_url(get_permalink($item[0]->ID)).'"><small>'.esc_html($item[1]).'</small><strong>'.esc_html($item[0]->post_title).'</strong></a>';
        else echo '<a href="'.esc_url(home_url('/thuat-ngu-chung-khoan/')).'"><small>Tra cứu nhanh</small><strong>30 thuật ngữ chứng khoán →</strong></a>';
    }
    echo '</nav>';
}
function dttl_extra_reading_section() {
    $posts=array_filter(dttl_extra_posts(),function($post){return $post->post_name!=='lo-trinh-tu-hoc-chung-khoan-90-ngay';});
    echo '<section id="doc-bo-sung" class="learning-stage"><div class="stage-heading"><div><span class="eyebrow dark">ĐỌC BỔ SUNG SAU NỀN TẢNG</span><h2>Đầu tư, thị trường và kinh tế tương lai</h2></div><span class="stage-ready">'.count($posts).' bài</span></div><p>Chọn chủ đề đang cần hoặc đọc lần lượt. Gặp từ lạ, mở <a class="simple-link" href="'.esc_url(home_url('/thuat-ngu-chung-khoan/')).'">trang thuật ngữ →</a></p>';
    foreach(array(1=>'Doanh nghiệp, biểu đồ & quản trị rủi ro',2=>'Thực hành, vĩ mô & công nghệ',3=>'Kinh tế tương lai & tự kiểm tra') as $group=>$label) {
        echo '<div id="doc-bo-sung-0'.$group.'" class="extra-reading-group"><h3>'.esc_html($label).'</h3><div class="lesson-list">';
        foreach($posts as $post) {
            $order=(int)get_post_meta($post->ID,'_dttl_extra_order',true);
            if($order < (($group-1)*10+1) || $order > $group*10) continue;
            echo '<a class="lesson-row" href="'.esc_url(get_permalink($post->ID)).'"><span class="lesson-number">'.esc_html(str_pad($order,2,'0',STR_PAD_LEFT)).'</span><div><h3>'.esc_html($post->post_title).'</h3><p>'.esc_html($post->post_excerpt).'</p></div><span class="lesson-read">Đọc bài →</span></a>';
        }
        echo '</div></div>';
    }
    echo '</section>';
}
function dttl_install_stock_extras_01() {
    if(!current_user_can('manage_options') || get_option('dttl_stock_extras_01')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/stock-extras-pack-01.json'),true);
    if(!is_array($pack)||count($pack)!==10) return;
    $category=get_category_by_slug('kien-thuc-chung-khoan');if(!$category) return;
    $page=get_page_by_path('thuat-ngu-chung-khoan');
    if(!$page) {
        $page_id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'30 thuật ngữ chứng khoán cho người mới','post_name'=>'thuat-ngu-chung-khoan','post_excerpt'=>'Tìm theo từ khóa và nhóm; định nghĩa, ví dụ, điều dễ nhầm và bài đọc thêm.','post_content'=>'Tra cứu 30 thuật ngữ qua 5 nhóm: nền tảng, giao dịch, doanh nghiệp, phương pháp, rủi ro và hiệu suất.','comment_status'=>'closed'),true);
        if(is_wp_error($page_id)||!$page_id) return;
    }
    $ids=get_option('dttl_stock_extras_01_ids',array());
    foreach($pack as $lesson) {
        if(isset($ids[$lesson['slug']])&&get_post($ids[$lesson['slug']])) continue;
        $existing=get_posts(array('name'=>$lesson['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($existing) $ids[$lesson['slug']]=$existing[0]->ID;
        else {
            $id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>$lesson['title'],'post_name'=>$lesson['slug'],'comment_status'=>'closed'),true);
            if(is_wp_error($id)||!$id) return;$ids[$lesson['slug']]=$id;
        }
        update_option('dttl_stock_extras_01_ids',$ids);
    }
    require_once ABSPATH.'wp-admin/includes/image.php';$processed=0;
    foreach($pack as $lesson) {
        $id=$ids[$lesson['slug']];
        if(get_post_meta($id,'_dttl_extra_pack',true)==='01'&&has_post_thumbnail($id)) continue;
        $image_id=(int)get_post_meta($id,'_dttl_extra_image',true);
        if(!$image_id||!get_post($image_id)) {
            $source=get_template_directory().'/assets/images/'.$lesson['image'].'.webp';if(!is_file($source)||!filesize($source)) return;
            $upload=wp_upload_bits($lesson['image'].'.webp',null,file_get_contents($source));if(!empty($upload['error'])) return;
            $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$lesson['title'].' — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa AI; không phải dữ liệu thị trường hoặc màn hình giao dịch.','post_status'=>'inherit'),$upload['file'],$id);
            if(is_wp_error($image_id)||!$image_id) return;
            update_post_meta($id,'_dttl_extra_image',$image_id);
            wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
            update_post_meta($image_id,'_wp_attachment_image_alt',$lesson['title'].' — ảnh minh họa AI');
        }
        set_post_thumbnail($id,$image_id);
        update_post_meta($id,'_dttl_extra_order',$lesson['order']);update_post_meta($id,'_dttl_extra_group',$lesson['group']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$lesson['title'],'post_excerpt'=>$lesson['excerpt'],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;update_post_meta($id,'_dttl_extra_pack','01');
        if(++$processed>=3) break;
    }
    $finished=true;foreach($pack as $lesson) if(get_post_meta($ids[$lesson['slug']],'_dttl_extra_pack',true)!=='01') {$finished=false;break;}
    if($finished) {
        foreach($pack as $lesson) wp_update_post(array('ID'=>$ids[$lesson['slug']],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids)));
        update_option('dttl_stock_extras_01',array('published'=>10,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    }
    if($processed&&function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_stock_extras_01');

function dttl_install_stock_extras_02() {
    if(!current_user_can('manage_options') || get_option('dttl_stock_extras_02')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/stock-extras-pack-02.json'),true);
    if(!is_array($pack)||count($pack)!==10) return;
    $ids=get_option('dttl_stock_extras_02_ids',array());
    foreach($pack as $lesson) {
        if(isset($ids[$lesson['slug']])&&get_post($ids[$lesson['slug']])) continue;
        $existing=get_posts(array('name'=>$lesson['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($existing) $ids[$lesson['slug']]=$existing[0]->ID;
        else {
            $id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>$lesson['title'],'post_name'=>$lesson['slug'],'comment_status'=>'closed'),true);
            if(is_wp_error($id)||!$id) return;$ids[$lesson['slug']]=$id;
        }
        update_option('dttl_stock_extras_02_ids',$ids);
    }
    require_once ABSPATH.'wp-admin/includes/image.php';$processed=0;
    foreach($pack as $lesson) {
        $category=get_category_by_slug($lesson['category']);if(!$category) return;
        $id=$ids[$lesson['slug']];
        if(get_post_meta($id,'_dttl_extra_pack',true)==='02'&&has_post_thumbnail($id)) continue;
        $image_id=(int)get_post_meta($id,'_dttl_extra_image',true);
        if(!$image_id||!get_post($image_id)) {
            $source=get_template_directory().'/assets/images/'.$lesson['image'].'.webp';if(!is_file($source)||!filesize($source)) return;
            $upload=wp_upload_bits($lesson['image'].'.webp',null,file_get_contents($source));if(!empty($upload['error'])) return;
            $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$lesson['title'].' — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa AI; không phải dữ liệu thị trường hoặc màn hình giao dịch.','post_status'=>'inherit'),$upload['file'],$id);
            if(is_wp_error($image_id)||!$image_id) return;
            update_post_meta($id,'_dttl_extra_image',$image_id);
            wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
            update_post_meta($image_id,'_wp_attachment_image_alt',$lesson['title'].' — ảnh minh họa AI');
        }
        set_post_thumbnail($id,$image_id);
        update_post_meta($id,'_dttl_extra_order',$lesson['order']);update_post_meta($id,'_dttl_extra_group',$lesson['group']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$lesson['title'],'post_excerpt'=>$lesson['excerpt'],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;update_post_meta($id,'_dttl_extra_pack','02');
        if(++$processed>=3) break;
    }
    $finished=true;foreach($pack as $lesson) if(get_post_meta($ids[$lesson['slug']],'_dttl_extra_pack',true)!=='02') {$finished=false;break;}
    if($finished) {
        foreach($pack as $lesson) wp_update_post(array('ID'=>$ids[$lesson['slug']],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids)));
        update_option('dttl_stock_extras_02',array('published'=>10,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    }
    if($processed&&function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_stock_extras_02');

function dttl_install_stock_extras_03() {
    if(!current_user_can('manage_options') || get_option('dttl_stock_extras_03')) return;
    $pack=json_decode(file_get_contents(get_template_directory().'/stock-extras-pack-03.json'),true);
    if(!is_array($pack)||count($pack)!==9) return;
    $ids=get_option('dttl_stock_extras_03_ids',array());
    foreach($pack as $lesson) {
        if(isset($ids[$lesson['slug']])&&get_post($ids[$lesson['slug']])) continue;
        $existing=get_posts(array('name'=>$lesson['slug'],'post_type'=>'post','post_status'=>array('publish','draft','pending'),'numberposts'=>1));
        if($existing) $ids[$lesson['slug']]=$existing[0]->ID;
        else {
            $id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>$lesson['title'],'post_name'=>$lesson['slug'],'comment_status'=>'closed'),true);
            if(is_wp_error($id)||!$id) return;$ids[$lesson['slug']]=$id;
        }
        update_option('dttl_stock_extras_03_ids',$ids);
    }
    require_once ABSPATH.'wp-admin/includes/image.php';$processed=0;
    foreach($pack as $lesson) {
        $category=get_category_by_slug($lesson['category']);if(!$category) return;
        $id=$ids[$lesson['slug']];
        if(get_post_meta($id,'_dttl_extra_pack',true)==='03'&&has_post_thumbnail($id)) continue;
        $image_id=(int)get_post_meta($id,'_dttl_extra_image',true);
        if(!$image_id||!get_post($image_id)) {
            $source=get_template_directory().'/assets/images/'.$lesson['image'].'.webp';if(!is_file($source)||!filesize($source)) return;
            $upload=wp_upload_bits($lesson['image'].'.webp',null,file_get_contents($source));if(!empty($upload['error'])) return;
            $image_id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$lesson['title'].' — ảnh minh họa AI','post_excerpt'=>'Ảnh minh họa AI; không phải dữ liệu thị trường hoặc màn hình giao dịch.','post_status'=>'inherit'),$upload['file'],$id);
            if(is_wp_error($image_id)||!$image_id) return;
            update_post_meta($id,'_dttl_extra_image',$image_id);
            wp_update_attachment_metadata($image_id,wp_generate_attachment_metadata($image_id,$upload['file']));
            update_post_meta($image_id,'_wp_attachment_image_alt',$lesson['title'].' — ảnh minh họa AI');
        }
        set_post_thumbnail($id,$image_id);
        update_post_meta($id,'_dttl_extra_order',$lesson['order']);update_post_meta($id,'_dttl_extra_group',$lesson['group']);
        update_post_meta($id,'_dttl_source_checked','2026-10-13');
        $result=wp_update_post(array('ID'=>$id,'post_title'=>$lesson['title'],'post_excerpt'=>$lesson['excerpt'],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids),'post_status'=>'publish','post_category'=>array($category->term_id)),true);
        if(is_wp_error($result)||!$result) return;update_post_meta($id,'_dttl_extra_pack','03');
        if(++$processed>=3) break;
    }
    $finished=true;foreach($pack as $lesson) if(get_post_meta($ids[$lesson['slug']],'_dttl_extra_pack',true)!=='03') {$finished=false;break;}
    if($finished) {
        foreach($pack as $lesson) wp_update_post(array('ID'=>$ids[$lesson['slug']],'post_content'=>dttl_resolve_lesson_links($lesson['content'],$ids)));
        update_option('dttl_stock_extras_03',array('published'=>9,'completed_at'=>current_time('mysql'),'ids'=>$ids));
    }
    if($processed&&function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_stock_extras_03');
