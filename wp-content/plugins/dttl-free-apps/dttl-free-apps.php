<?php
/**
 * Plugin Name: DTTL Công cụ miễn phí
 * Description: Kho nhiều ứng dụng, phân nhóm, bản tải theo hệ điều hành và tải dành cho thành viên.
 * Version: 0.2.2
 * Author: Đầu Tư Tương Lai
 */
if (!defined('ABSPATH')) { exit; }
function dttl_apps_platforms() { return array('windows'=>'Windows', 'macos'=>'macOS', 'linux'=>'Linux'); }
add_action('init', function () {
    register_post_type('dttl_app', array('labels'=>array('name'=>'Công cụ miễn phí','singular_name'=>'Ứng dụng','add_new_item'=>'Thêm ứng dụng','edit_item'=>'Sửa ứng dụng','all_items'=>'Tất cả ứng dụng'), 'public'=>true,'show_in_rest'=>false,'menu_icon'=>'dashicons-desktop','supports'=>array('title','editor','excerpt','thumbnail','revisions'),'rewrite'=>array('slug'=>'ung-dung','with_front'=>false),'has_archive'=>false,'capability_type'=>'post','map_meta_cap'=>true,'capabilities'=>array('create_posts'=>'manage_options','edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','delete_posts'=>'manage_options','read_private_posts'=>'manage_options')));
    register_taxonomy('dttl_app_group', 'dttl_app', array('label'=>'Nhóm công cụ','hierarchical'=>true,'show_admin_column'=>true,'show_in_rest'=>false,'public'=>true,'rewrite'=>array('slug'=>'nhom-cong-cu'),'capabilities'=>array('manage_terms'=>'manage_options','edit_terms'=>'manage_options','delete_terms'=>'manage_options','assign_terms'=>'manage_options')));
});
register_activation_hook(__FILE__, function () { update_option('dttl_apps_flush', 1, false); });
add_action('init', function () { if (get_option('dttl_apps_flush')) { flush_rewrite_rules(false); delete_option('dttl_apps_flush'); } }, 99);

function dttl_apps_payload($id, $os) {
    $meta = get_post_meta($id, '_dttl_file_' . $os, true);
    if (!is_array($meta) || empty($meta['file'])) { return false; }
    if ($meta['file'] === 'bundled' && $os === 'windows' && $id === (int)get_option('dttl_first_app_id')) { $path = __DIR__ . '/payload.php'; }
    elseif (preg_match('/^[a-f0-9]{40}\.php$/D', $meta['file'])) { $path = WP_CONTENT_DIR . '/dttl-app-downloads/' . $meta['file']; }
    else { return false; }
    if (!is_readable($path)) { return false; }
    return array($path, $meta);
}
function dttl_apps_download() {
    nocache_headers(); header('Cache-Control: private, no-store, max-age=0');
    $id = isset($_GET['app']) ? absint($_GET['app']) : (int)get_option('dttl_first_app_id');
    $os = isset($_GET['os']) ? sanitize_key($_GET['os']) : 'windows';
    $post = get_post($id);
    if (!$post || $post->post_type !== 'dttl_app' || !isset(dttl_apps_platforms()[$os]) || ($post->post_status !== 'publish' && !current_user_can('manage_options'))) { wp_die('Không tìm thấy bản tải.', '', array('response'=>404)); }
    if (!is_user_logged_in()) { wp_safe_redirect(wp_login_url(get_permalink($id))); exit; }
    if (post_password_required($post)) { wp_die('Ứng dụng được bảo vệ bằng mật khẩu.', '', array('response'=>403)); }
    $payload = dttl_apps_payload($id, $os);
    if (!$payload) { wp_die('Bản tải chưa sẵn sàng.', '', array('response'=>404)); }
    $encoded = require $payload[0]; $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
    if (!$bytes || substr($bytes,0,2)!=='PK' || (!empty($payload[1]['sha256']) && !hash_equals($payload[1]['sha256'],hash('sha256',$bytes)))) { wp_die('Gói tải không hợp lệ.', '', array('response'=>503)); }
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . sanitize_file_name($payload[1]['name']) . '"');
    header('X-Content-Type-Options: nosniff'); header('Content-Length: ' . strlen($bytes));
    update_post_meta($id, '_dttl_download_count', (int)get_post_meta($id,'_dttl_download_count',true)+1);
    echo $bytes; exit;
}
add_action('admin_post_dttl_apps_download','dttl_apps_download');
add_action('admin_post_nopriv_dttl_apps_download','dttl_apps_download');
function dttl_apps_download_panel($id) {
    $online=esc_url(get_post_meta($id,'_dttl_online_url',true));
    if($online)return '<section class="dttl-download"><h2>Dùng ứng dụng trực tuyến</h2><p><a class="dttl-app-button" href="'.$online.'">Mở ứng dụng miễn phí →</a></p><p>Chạy trên trình duyệt, không cần tải bộ cài.</p></section>'; 
    $version=get_post_meta($id,'_dttl_version',true); $req=get_post_meta($id,'_dttl_requirements',true);
    $html='<section class="dttl-download"><h2>Tải ứng dụng</h2><p>Phiên bản '.esc_html($version).'</p>';
    $found=false;
    foreach(dttl_apps_platforms() as $os=>$label) {
        $payload=dttl_apps_payload($id,$os); if(!$payload)continue; $found=true;
        $url=add_query_arg(array('action'=>'dttl_apps_download','app'=>$id,'os'=>$os),admin_url('admin-post.php'));
        $html.='<p><a class="dttl-app-button" href="'.esc_url($url).'">Tải miễn phí cho '.esc_html($label).'</a> <small>'.esc_html(size_format((int)$payload[1]['size'])).' · ZIP</small></p>';
        if(!empty($payload[1]['sha256']))$html.='<details><summary>Mã kiểm tra SHA256</summary><code style="overflow-wrap:anywhere">'.esc_html($payload[1]['sha256']).'</code></details>';
    }
    if(!$found)$html.='<p>Bản tải đang được hoàn thiện.</p>';
    $html.='<p>'.esc_html($req).'</p><p>Đăng nhập bằng tài khoản Đầu Tư Tương Lai để tải. <a href="'.esc_url(add_query_arg('redirect_to',get_permalink($id),wp_registration_url())).'">Đăng ký miễn phí</a>.</p></section>';
    return $html;
}
add_filter('the_content',function($content){ if(is_singular('dttl_app')&&in_the_loop()&&is_main_query())return $content.dttl_apps_download_panel(get_the_ID());return $content; },20);
add_shortcode('dttl_screen_layout_download',function(){ $id=(int)get_option('dttl_first_app_id');return $id && get_post_status($id)==='publish' ? dttl_apps_download_panel($id) : '<p>Ứng dụng chia màn hình đang được kiểm tra.</p>'; });
add_shortcode('dttl_free_tools',function(){
    $group=isset($_GET['nhom'])?sanitize_title(wp_unslash($_GET['nhom'])):'';
    $query=isset($_GET['tim'])?sanitize_text_field(wp_unslash($_GET['tim'])):'';
    $args=array('post_type'=>'dttl_app','post_status'=>'publish','posts_per_page'=>12,'orderby'=>'title','order'=>'ASC','paged'=>max(1,isset($_GET['trang'])?absint($_GET['trang']):1));
    if($query!=='')$args['s']=$query;
    if($group!=='')$args['tax_query']=array(array('taxonomy'=>'dttl_app_group','field'=>'slug','terms'=>$group));
    $apps=new WP_Query($args);ob_start(); ?>
    <div class="dttl-catalog"><p class="dttl-app-intro">Những công cụ đơn giản giúp bạn học tập và làm việc thuận tiện hơn. Khám phá từng ứng dụng, chọn đúng hệ điều hành và đăng nhập để tải miễn phí.</p>
    <form class="dttl-app-filters" method="get" action="<?php echo esc_url(get_permalink()); ?>"><label>Tìm công cụ<input type="search" name="tim" value="<?php echo esc_attr($query); ?>" placeholder="Tên hoặc chức năng ứng dụng"></label><label>Nhóm công cụ<select name="nhom"><option value="">Tất cả nhóm</option><?php foreach(get_terms(array('taxonomy'=>'dttl_app_group','hide_empty'=>false)) as $term): ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($group,$term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></label><button class="dttl-app-button" type="submit">Tìm kiếm</button></form>
    <div class="dttl-app-grid"><?php while($apps->have_posts()):$apps->the_post();$id=get_the_ID(); ?>
    <a class="dttl-app-card" href="<?php echo esc_url(get_post_meta($id,'_dttl_online_url',true) ?: get_permalink()); ?>" aria-label="<?php echo esc_attr(get_the_title().(get_post_meta($id,'_dttl_online_url',true)?' — mở ứng dụng trực tuyến':' — tải và hướng dẫn sử dụng')); ?>"><?php if(has_post_thumbnail()):echo get_the_post_thumbnail($id,'thumbnail',array('loading'=>'lazy','alt'=>''));else:?><div class="dttl-app-icon" aria-hidden="true">▦</div><?php endif; ?>
    <h2><?php the_title(); ?></h2><span class="dttl-app-card-hint"><?php echo get_post_meta($id,'_dttl_online_url',true)?'Mở ứng dụng trực tuyến →':'Tải &amp; hướng dẫn sử dụng →'; ?></span></a>
    <?php endwhile;wp_reset_postdata(); ?></div>
    <?php if(!$apps->found_posts): ?><div class="dttl-download"><h2><?php echo $query||$group?'Chưa tìm thấy công cụ phù hợp':'Ứng dụng đầu tiên đang được hoàn thiện'; ?></h2><p><?php echo $query||$group?'Thử từ khóa khác hoặc chọn tất cả nhóm công cụ.':'DTTL Chia màn hình đang được kiểm tra. Các ứng dụng đã sẵn sàng sẽ xuất hiện tại đây.'; ?></p></div><?php endif; ?>
    <?php if($apps->max_num_pages>1)echo '<nav aria-label="Các trang ứng dụng">'.wp_kses_post(paginate_links(array('base'=>add_query_arg('trang','%#%'),'format'=>'','current'=>$args['paged'],'total'=>$apps->max_num_pages))).'</nav>'; ?>
    <p class="dttl-app-more">Cần tính lãi lỗ, giá vốn hoặc lãi kép? <a href="<?php echo esc_url(home_url('/cong-cu-dau-tu/')); ?>">Mở công cụ đầu tư trực tuyến →</a></p></div>
    <?php return ob_get_clean();
});
add_action('wp_enqueue_scripts',function(){ if(is_singular('dttl_app') || is_page((int)get_option('dttl_apps_hub_id')) || (is_singular() && has_shortcode(get_post()->post_content,'dttl_screen_layout_download')))wp_enqueue_style('dttl-apps',plugins_url('catalog.css',__FILE__),array(),'0.2.2'); });

add_action('add_meta_boxes',function(){add_meta_box('dttl-app-files','Phiên bản và file tải','dttl_apps_meta','dttl_app','normal','high');});
add_action('post_edit_form_tag',function($post){if($post->post_type==='dttl_app')echo ' enctype="multipart/form-data"';});
function dttl_apps_meta($post){
    wp_nonce_field('dttl_apps_save','dttl_apps_nonce');
    echo '<p><label>Link ứng dụng trực tuyến <input type="url" style="width:100%" name="dttl_online_url" value="'.esc_attr(get_post_meta($post->ID,'_dttl_online_url',true)).'"></label></p>';
    echo '<p><label>Phiên bản <input name="dttl_version" value="'.esc_attr(get_post_meta($post->ID,'_dttl_version',true)).'" placeholder="1.0 hoặc 0.2 beta"></label></p>';
    echo '<p><label>Yêu cầu và lưu ý sử dụng<br><textarea name="dttl_requirements" rows="3" style="width:100%">'.esc_textarea(get_post_meta($post->ID,'_dttl_requirements',true)).'</textarea></label></p>';
    echo '<p>Tải gói ZIP riêng cho mỗi hệ điều hành (tối đa 50 MB, chịu thêm giới hạn upload của máy chủ). Bỏ trống để giữ bản cũ. File chỉ được trả qua điểm tải có kiểm tra đăng nhập.</p>';
    foreach(dttl_apps_platforms() as $os=>$label){$meta=get_post_meta($post->ID,'_dttl_file_'.$os,true);echo '<p><label><strong>'.esc_html($label).'</strong> <input type="file" accept=".zip,application/zip" name="dttl_zip_'.$os.'"></label>';if(is_array($meta))echo '<br>Bản hiện có: '.esc_html($meta['name']).' · '.esc_html(size_format($meta['size']));echo '</p>';}
    echo '<p>Lượt yêu cầu tải: '.esc_html((string)(int)get_post_meta($post->ID,'_dttl_download_count',true)).'</p>';
}
add_action('save_post_dttl_app',function($id){
    if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE||wp_is_post_revision($id)||!current_user_can('manage_options')||!isset($_POST['dttl_apps_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dttl_apps_nonce'])),'dttl_apps_save'))return;
    update_post_meta($id,'_dttl_online_url',esc_url_raw(wp_unslash($_POST['dttl_online_url']??''),array('https')));
    update_post_meta($id,'_dttl_version',sanitize_text_field(wp_unslash($_POST['dttl_version']??'')));
    update_post_meta($id,'_dttl_requirements',sanitize_textarea_field(wp_unslash($_POST['dttl_requirements']??'')));
    foreach(dttl_apps_platforms() as $os=>$label){
        $file=$_FILES['dttl_zip_'.$os]??null;if(!$file||$file['error']===UPLOAD_ERR_NO_FILE)continue;
        $error='';
        if(!current_user_can('upload_files')||$file['error']!==UPLOAD_ERR_OK||$file['size']>50*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='zip'||!is_uploaded_file($file['tmp_name']))$error='Không nhận được ZIP hợp lệ hoặc file vượt giới hạn.';
        if(!$error){$bytes=file_get_contents($file['tmp_name']);if(substr($bytes,0,2)!=='PK')$error='File không phải gói ZIP.';}
        if(!$error){
            $dir=WP_CONTENT_DIR.'/dttl-app-downloads';
            if(!wp_mkdir_p($dir)||!is_writable($dir))$error='Thư mục lưu bản tải chưa ghi được.';
            else {
                $name=bin2hex(random_bytes(20)).'.php';$code="<?php\nif (!defined('ABSPATH')) { http_response_code(403); exit; }\nreturn '".base64_encode($bytes)."';\n";
                if(file_put_contents($dir.'/'.$name,$code,LOCK_EX)!==strlen($code))$error='Không lưu được gói tải.';
                else{chmod($dir.'/'.$name,0644);update_post_meta($id,'_dttl_file_'.$os,array('file'=>$name,'name'=>sanitize_file_name($file['name']),'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)));}
            }
        }
        if($error)set_transient('dttl_apps_error_'.get_current_user_id(),$label.': '.$error,120);
    }
});
add_action('admin_notices',function(){ $key='dttl_apps_error_'.get_current_user_id();$error=get_transient($key);if($error){echo '<div class="notice notice-error"><p>'.esc_html($error).' Bản tải cũ được giữ nguyên.</p></div>';delete_transient($key);} });
add_action('admin_menu',function(){add_submenu_page('edit.php?post_type=dttl_app','Thiết lập kho công cụ','Thiết lập kho','manage_options','dttl-free-apps','dttl_apps_admin');});
function dttl_apps_admin(){
    if(!current_user_can('manage_options'))return;
    echo '<div class="wrap"><h1>Kho Công cụ miễn phí</h1><p>Thêm nhiều ứng dụng, chia nhóm và quản lý file tải cho Windows, macOS, Linux trong mục Công cụ miễn phí. Chỉ những ứng dụng đã xuất bản mới hiện ngoài kho.</p><p>Bài SEO đã chuẩn bị là trang riêng, không bị tự xuất bản khi thiết lập kho.</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('dttl_apps_setup');echo '<input type="hidden" name="action" value="dttl_apps_setup">';submit_button('Khởi tạo kho và bản nháp ứng dụng đầu tiên');echo '</form>';
    $hub=(int)get_option('dttl_apps_hub_id');if($hub)echo '<p><a href="'.esc_url(get_edit_post_link($hub)).'">Sửa trang kho</a> · <a href="'.esc_url(get_permalink($hub)).'">Xem kho công cụ</a></p>';
    echo '<p>Shortcode kho: <code>[dttl_free_tools]</code></p></div>';
}
add_action('admin_post_dttl_apps_setup',function(){
    if(!current_user_can('manage_options'))wp_die('Không có quyền.','',array('response'=>403));check_admin_referer('dttl_apps_setup');
    foreach(array('Năng suất & công việc','Tệp tin & hình ảnh','Học tập','Đầu tư & tính toán','Tiện ích máy tính') as $term)if(!term_exists($term,'dttl_app_group'))wp_insert_term($term,'dttl_app_group');
    $hub=(int)get_option('dttl_apps_hub_id');if(!$hub||!get_post($hub)){$found=get_page_by_path('cong-cu-mien-phi');if($found)$hub=$found->ID;else{$hub=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Công cụ miễn phí','post_name'=>'cong-cu-mien-phi','post_content'=>'[dttl_free_tools]'),true);if(is_wp_error($hub))wp_die(esc_html($hub->get_error_message()));}update_option('dttl_apps_hub_id',$hub,false);}
    if(get_post_status($hub)==='draft')wp_update_post(array('ID'=>$hub,'post_status'=>'publish'));
    $first=(int)get_option('dttl_first_app_id');
    if(!$first||!get_post($first)){
        $first=wp_insert_post(array('post_type'=>'dttl_app','post_status'=>'draft','post_title'=>'DTTL Chia màn hình','post_name'=>'chia-man-hinh','post_excerpt'=>'Desktop chính bình thường, kéo cửa sổ sang 1–3 ô phụ để hiển thị riêng.','post_content'=>'<p>DTTL Chia màn hình 0.2 beta dành cho Windows: giữ vùng desktop chính để làm việc và chừa một vùng phụ bên phải.</p><h2>Cách sử dụng</h2><ol><li>Tải ZIP, giải nén rồi mở DTTL-Chia-Man-Hinh.exe.</li><li>Kéo thanh tiêu đề của cửa sổ đang mở và thả vào ô phụ.</li><li>Kéo cửa sổ ra vùng chính để bỏ ghim, hoặc bấm Trả cửa sổ để khôi phục vị trí cũ.</li><li>Trong Tùy chọn, chọn 1–3 ô, chiều rộng vùng phụ và màn hình.</li><li>Chọn Thoát và trả desktop để đóng ứng dụng.</li></ol><p>Mỗi ô chứa một cửa sổ. Thả cửa sổ mới sẽ trả cửa sổ cũ về vị trí trước đó. Có thể cần tách tab trình duyệt thành cửa sổ riêng trước khi kéo.</p><p>Bản portable không cần cài đặt, không quảng cáo và không gửi dữ liệu mạng. Cửa sổ có kích thước tối thiểu lớn có thể không vừa ô; chọn ít ô hơn hoặc tăng chiều rộng. Ứng dụng chạy quyền quản trị và chế độ toàn màn hình có thể không hoạt động như mong muốn. Đây là bản beta chưa ký số, cần thử trên máy thực tế trước bản ổn định.</p>'),true);
        if(is_wp_error($first))wp_die(esc_html($first->get_error_message()));update_option('dttl_first_app_id',$first,false);
        $term=term_exists('Năng suất & công việc','dttl_app_group');if($term)wp_set_object_terms($first,array((int)$term['term_id']),'dttl_app_group');
        update_post_meta($first,'_dttl_version','0.2 beta');update_post_meta($first,'_dttl_requirements','Windows 10/11 Intel/AMD, 32-bit hoặc 64-bit; .NET Framework 4.8. Chưa có bản macOS/Linux/ARM được kiểm thử.');
        if(is_readable(__DIR__.'/payload.php')){$raw=base64_decode((require __DIR__.'/payload.php'),true);if($raw)update_post_meta($first,'_dttl_file_windows',array('file'=>'bundled','name'=>'DTTL-Chia-Man-Hinh-0.2-beta.zip','size'=>strlen($raw),'sha256'=>hash('sha256',$raw)));}
    }
    flush_rewrite_rules(false);wp_safe_redirect(admin_url('edit.php?post_type=dttl_app&page=dttl-free-apps'));exit;
});
