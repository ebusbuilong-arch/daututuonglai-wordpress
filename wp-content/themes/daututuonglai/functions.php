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
    wp_enqueue_style('dttl-main', get_template_directory_uri().'/assets/css/main.css', array(), '2.0.0-header-20261021');
    wp_enqueue_script('dttl-main', get_template_directory_uri().'/assets/js/main.js', array(), '1.9.0', true);
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
    return $cat ? get_category_link($cat->term_id) : home_url('/');
}
function dttl_install_content() {
    if (get_option('dttl_content_v1')) return;
    $content=json_decode(file_get_contents(get_template_directory().'/content.json'),true);
    if (!$content || empty($content['pages']) || empty($content['posts'])) return;
    foreach(dttl_categories() as $slug=>$name) {
        if(!term_exists($slug,'category')) wp_insert_term($name,'category',array('slug'=>$slug));
    }
    $created=array();
    foreach($content['posts'] as $item) {
        $existing=get_posts(array('name'=>$item['slug'],'post_type'=>'post','post_status'=>'publish','numberposts'=>1));
        if($existing) {$created[$item['slug']]=$existing[0]->ID;continue;}
        $cat=get_category_by_slug($item['category']);
        $id=wp_insert_post(array('post_title'=>$item['title'],'post_name'=>$item['slug'],'post_status'=>'publish','post_type'=>'post','post_content'=>$item['content'],'post_excerpt'=>$item['excerpt'],'post_category'=>$cat?array($cat->term_id):array(),'comment_status'=>'closed'),true);
        if(is_wp_error($id)) return;
        $created[$item['slug']]=$id;
        update_post_meta($id,'dttl_created',1);
    }
    require_once ABSPATH.'wp-admin/includes/image.php';
    $images=array('hoc-dau-tu-tuong-lai'=>'Học đầu tư — ảnh minh họa AI','tich-luy-dai-han'=>'Tích lũy dài hạn — ảnh minh họa AI','ai-tu-dong-hoa'=>'AI và tự động hóa — ảnh minh họa AI');
    $attachments=array();
    foreach($images as $file=>$alt) {
        $source=get_template_directory().'/assets/images/'.$file.'.webp';
        $uploaded=wp_upload_bits($file.'.webp',null,file_get_contents($source));
        if(!empty($uploaded['error'])) continue;
        $id=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_title'=>$alt,'post_status'=>'inherit','post_excerpt'=>'Ảnh minh họa được tạo bằng AI.'),$uploaded['file']);
        if(!$id || is_wp_error($id)) continue;
        wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$uploaded['file']));
        update_post_meta($id,'_wp_attachment_image_alt',$alt); $attachments[$file]=$id;
    }
    foreach($created as $slug=>$id) {
        $file=$slug==='ai-doanh-nghiep-kinh-te-tuong-lai'?'ai-tu-dong-hoa':($slug==='lai-kep-va-thoi-gian'?'tich-luy-dai-han':'hoc-dau-tu-tuong-lai');
        if(isset($attachments[$file])) set_post_thumbnail($id,$attachments[$file]);
    }
    foreach($content['pages'] as $item) {
        $existing=get_page_by_path($item['slug']);
        if($existing) {$created[$item['slug']]=$existing->ID;continue;}
        $body=$item['content'];
        foreach($created as $slug=>$post_id) $body=str_replace('/?dttl-link='.$slug,get_permalink($post_id),$body);
        $id=wp_insert_post(array('post_title'=>$item['title'],'post_name'=>$item['slug'],'post_status'=>'publish','post_type'=>'page','post_content'=>$body,'comment_status'=>'closed'),true);
        if(is_wp_error($id)) return;
        $created[$item['slug']]=$id; update_post_meta($id,'dttl_created',1);
    }
    update_option('show_on_front','page'); update_option('page_on_front',$created['trang-chu']); update_option('page_for_posts',$created['bai-viet']);
    update_option('blogname','Đầu Tư Tương Lai'); update_option('blogdescription','Học chứng khoán và khám phá kinh tế tương lai');
    remove_theme_mod('custom_logo');
    $menu=wp_create_nav_menu('Đầu Tư Tương Lai — Menu chính');
    if(!is_wp_error($menu)) {
        foreach(array('Trang chủ'=>'trang-chu','Lộ trình học'=>'lo-trinh-hoc','Bài viết'=>'bai-viet') as $label=>$slug) wp_update_nav_menu_item($menu,0,array('menu-item-title'=>$label,'menu-item-object'=>'page','menu-item-object-id'=>$created[$slug],'menu-item-type'=>'post_type','menu-item-status'=>'publish'));
        foreach(array('kinh-te-tuong-lai'=>'Kinh tế tương lai','goc-chia-se'=>'Góc chia sẻ') as $slug=>$label) { $cat=get_category_by_slug($slug); wp_update_nav_menu_item($menu,0,array('menu-item-title'=>$label,'menu-item-object'=>'category','menu-item-object-id'=>$cat->term_id,'menu-item-type'=>'taxonomy','menu-item-status'=>'publish')); }
        set_theme_mod('nav_menu_locations',array('primary'=>$menu));
    }
    update_option('dttl_content_v1',1);
    flush_rewrite_rules();
    if(function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('after_switch_theme','dttl_install_content');
function dttl_meta_description() {
    if(defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) return;
    $description=is_front_page()?'Học chứng khoán từ con số 0, xây dựng tư duy đầu tư và khám phá kinh tế tương lai trong kỷ nguyên AI.':(is_singular()?wp_strip_all_tags(get_the_excerpt()):'Thư viện kiến thức chứng khoán và kinh tế tương lai.');
    if($description) echo '<meta name="description" content="'.esc_attr($description).'">'."\n";
}
add_action('wp_head','dttl_meta_description',1);

function dttl_market_assets() {
    if(!is_page('thi-truong')) return;
    wp_enqueue_style('dttl-market',get_template_directory_uri().'/assets/css/market.css',array('dttl-main'),'1.9.5');
    wp_enqueue_script('dttl-market',get_template_directory_uri().'/assets/js/market.js',array('dttl-main'),'2.0.0-news1',true);
}
add_action('wp_enqueue_scripts','dttl_market_assets');
function dttl_market_install() {
    if(!current_user_can('manage_options') || get_option('dttl_market_v1')) return;
    $page=get_page_by_path('thi-truong');
    $id=$page?$page->ID:wp_insert_post(array('post_title'=>'Thị trường & Phân tích','post_name'=>'thi-truong','post_type'=>'page','post_status'=>'publish','post_content'=>'Theo dõi chứng khoán, tiền số, vàng và tiền tệ với biểu đồ, tín hiệu kỹ thuật và tin từ nguồn. Giá và độ trễ theo nhà cung cấp dữ liệu.','post_excerpt'=>'Giá, biểu đồ tương tác, xu hướng kỹ thuật và tin thị trường. Hướng dẫn dễ hiểu cho người mới.','comment_status'=>'closed'),true);
    if(!$id || is_wp_error($id)) return;
    $locations=get_nav_menu_locations();
    if(!empty($locations['primary'])) {
        $menu=$locations['primary'];$found=false;
        foreach((array)wp_get_nav_menu_items($menu) as $item) if((int)$item->object_id===(int)$id && $item->object==='page') $found=true;
        if(!$found) wp_update_nav_menu_item($menu,0,array('menu-item-title'=>'Thị trường','menu-item-object'=>'page','menu-item-object-id'=>$id,'menu-item-type'=>'post_type','menu-item-status'=>'publish','menu-item-position'=>2));
    }
    update_option('dttl_market_v1',1);
    if(function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_market_install');

function dttl_install_site_icon() {
    if(!current_user_can('manage_options') || get_option('dttl_icon_v1')) return;
    require_once ABSPATH.'wp-admin/includes/image.php';
    $source=get_template_directory().'/assets/images/dttl-icon-512.png';
    $uploaded=wp_upload_bits('dau-tu-tuong-lai-icon.png',null,file_get_contents($source));
    if(!empty($uploaded['error'])) return;
    $id=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Biểu tượng Đầu Tư Tương Lai','post_status'=>'inherit'),$uploaded['file']);
    if(!$id || is_wp_error($id)) return;
    wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$uploaded['file']));
    update_post_meta($id,'_wp_attachment_image_alt','Biểu tượng Đầu Tư Tương Lai');
    update_option('site_icon',$id); update_option('dttl_icon_v1',1);
    if(function_exists('rocket_clean_domain')) rocket_clean_domain();
}
add_action('admin_init','dttl_install_site_icon');

require_once get_template_directory().'/inc/lessons.php';
require_once get_template_directory().'/inc/app-guides.php';
require_once get_template_directory().'/inc/stock-extras.php';

function dttl_app_menu_link($items,$args) {
    if(isset($args->theme_location) && $args->theme_location==='primary') {
        $items.='<li><a href="'.esc_url(home_url('/huong-dan-app-chung-khoan/')).'">Hướng dẫn app</a></li><li><a href="'.esc_url(home_url('/danh-muc-mo-phong/')).'">Mô phỏng</a></li><li><a href="'.esc_url(home_url('/thuat-ngu-chung-khoan/')).'">Thuật ngữ</a></li>';
    }
    return $items;
}
// Primary links are managed in the WordPress menu.

require_once get_template_directory().'/inc/seo.php';

require_once get_template_directory().'/inc/stock-quiz.php';

require_once get_template_directory().'/inc/roadmap-seven.php';

require_once get_template_directory().'/inc/learning-tools.php';
require_once get_template_directory().'/inc/beginner-pack.php';
require_once get_template_directory().'/inc/short-term-pack.php';
require_once get_template_directory().'/inc/long-term-pack.php';
require_once get_template_directory().'/inc/article-covers.php';

require_once get_template_directory().'/inc/future-economy-pack.php';

require_once get_template_directory().'/inc/market-expansion.php';
require_once get_template_directory().'/inc/portfolio.php';

add_action('wp_enqueue_scripts',function(){
 if(!is_singular('post'))return;
 wp_enqueue_style('dttl-reader',get_template_directory_uri().'/assets/css/reader.css',array('dttl-main'),'1.9.8');
 wp_enqueue_script('dttl-reader',get_template_directory_uri().'/assets/js/reader.js',array(),'2.0.0-lang-20261020',true);
});

require_once get_template_directory().'/inc/learning-path.php';
require_once get_template_directory().'/inc/commodity-deep.php';


/* DTTL private feedback mailbox — 2026-10-14. */
add_action('init', function () {
    register_post_type('dttl_feedback', array(
        'labels'=>array('name'=>'Hòm thư góp ý','singular_name'=>'Góp ý','edit_item'=>'Xem góp ý','all_items'=>'Tất cả góp ý','not_found'=>'Chưa có góp ý nào.'),
        'public'=>false,'publicly_queryable'=>false,'exclude_from_search'=>true,'show_ui'=>true,'show_in_rest'=>false,'rewrite'=>false,'query_var'=>false,
        'menu_icon'=>'dashicons-email-alt','supports'=>array('title','editor'),'map_meta_cap'=>false,
        'capabilities'=>array('edit_post'=>'manage_options','read_post'=>'manage_options','delete_post'=>'manage_options','edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','read_private_posts'=>'manage_options','delete_posts'=>'manage_options','delete_private_posts'=>'manage_options','delete_published_posts'=>'manage_options','delete_others_posts'=>'manage_options','edit_private_posts'=>'manage_options','edit_published_posts'=>'manage_options','create_posts'=>'do_not_allow')
    ));
});
function dttl_feedback_token() {
    nocache_headers();
    wp_send_json_success(array('nonce'=>wp_create_nonce('dttl_feedback_send')));
}
add_action('wp_ajax_dttl_feedback_token','dttl_feedback_token');
add_action('wp_ajax_nopriv_dttl_feedback_token','dttl_feedback_token');
function dttl_feedback_send() {
    nocache_headers();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_send_json_error(array('message'=>'Yêu cầu không hợp lệ.'),405);
    if (!check_ajax_referer('dttl_feedback_send','nonce',false)) wp_send_json_error(array('message'=>'Phiên gửi đã hết hạn. Vui lòng thử lại.'),403);
    if (!empty($_POST['website'])) wp_send_json_error(array('message'=>'Không thể gửi biểu mẫu này.'),400);
    $text = isset($_POST['message']) && is_string($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($length < 5 || $length > 5000) wp_send_json_error(array('message'=>'Anh/chị vui lòng viết từ 5 đến 5.000 ký tự.'),400);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $sender = hash_hmac('sha256',$ip,wp_salt('nonce'));
    $request = isset($_POST['request_id']) && is_string($_POST['request_id']) ? sanitize_key($_POST['request_id']) : '';
    if (!$request || strlen($request)>80) wp_send_json_error(array('message'=>'Vui lòng mở lại hòm thư và thử lại.'),400);
    $receipt = 'dttl_fb_done_'.hash('sha256',$sender.$request);
    if (get_transient($receipt)) wp_send_json_success(array('message'=>'Góp ý đã được gửi vào hòm thư. Cảm ơn anh/chị!'));
    $limit_key = 'dttl_fb_rate_'.substr($sender,0,32);
    $attempts = (int)get_transient($limit_key);
    if ($attempts >= 5) wp_send_json_error(array('message'=>'Anh/chị đã gửi nhiều góp ý. Vui lòng thử lại sau một giờ.'),429);
    set_transient($limit_key,$attempts+1,HOUR_IN_SECONDS);
    $image = null;
    if (isset($_FILES['photo']) && (!isset($_FILES['photo']['error']) || $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE)) {
        $file = $_FILES['photo'];
        if (!isset($file['error'],$file['size'],$file['tmp_name']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3*MB_IN_BYTES || !is_uploaded_file($file['tmp_name'])) wp_send_json_error(array('message'=>'Không tải được ảnh. Chọn một ảnh JPG, PNG hoặc WebP tối đa 3 MB.'),400);
        $info = @getimagesize($file['tmp_name']);
        $allowed = array('image/jpeg','image/png','image/webp');
        if (!$info || !in_array($info['mime'],$allowed,true) || $info[0]*$info[1]>20000000) wp_send_json_error(array('message'=>'Ảnh phải là JPG, PNG hoặc WebP, không quá 20 triệu điểm ảnh.'),400);
        $bytes = file_get_contents($file['tmp_name']);
        if ($bytes === false || strlen($bytes)>3*MB_IN_BYTES) wp_send_json_error(array('message'=>'Không đọc được ảnh. Vui lòng chọn lại ảnh.'),400);
        $image = array('mime'=>$info['mime'],'data'=>base64_encode($bytes));
    }
    $id = wp_insert_post(array('post_type'=>'dttl_feedback','post_status'=>'private','post_title'=>'Góp ý '.wp_date('d/m/Y H:i').' — '.wp_trim_words($text,10,'…'),'post_content'=>$text,'comment_status'=>'closed','ping_status'=>'closed'),true);
    if (is_wp_error($id) || !$id) wp_send_json_error(array('message'=>'Chưa lưu được góp ý. Nội dung vẫn được giữ để anh/chị thử lại.'),500);
    if ($image && !add_post_meta($id,'_dttl_feedback_image',$image,true)) {
        wp_trash_post($id);
        wp_send_json_error(array('message'=>'Chưa lưu được ảnh. Anh/chị vui lòng thử gửi lại.'),500);
    }
    $page = isset($_POST['page']) && is_string($_POST['page']) ? esc_url_raw(wp_unslash($_POST['page'])) : '';
    if ($page && wp_parse_url($page,PHP_URL_HOST)===wp_parse_url(home_url(),PHP_URL_HOST)) update_post_meta($id,'_dttl_feedback_page',$page);
    set_transient($receipt,$id,DAY_IN_SECONDS);
    wp_send_json_success(array('message'=>'Góp ý đã được gửi vào hòm thư. Cảm ơn anh/chị!'));
}
add_action('wp_ajax_dttl_feedback_send','dttl_feedback_send');
add_action('wp_ajax_nopriv_dttl_feedback_send','dttl_feedback_send');
add_action('add_meta_boxes_dttl_feedback',function($post){
    add_meta_box('dttl-feedback-detail','Ảnh đính kèm và trang gửi góp ý',function($post){
        $page=get_post_meta($post->ID,'_dttl_feedback_page',true);
        if ($page) echo '<p>Trang gửi: <a href="'.esc_url($page).'" target="_blank" rel="noopener noreferrer">'.esc_html($page).'</a></p>';
        if (get_post_meta($post->ID,'_dttl_feedback_image',true)) {
            $url=wp_nonce_url(admin_url('admin-post.php?action=dttl_feedback_image&id='.$post->ID),'dttl_feedback_image_'.$post->ID);
            echo '<p>Ảnh chỉ hiển thị cho quản trị viên.</p><img src="'.esc_url($url).'" alt="Ảnh đính kèm góp ý" style="max-width:100%;max-height:650px;height:auto">';
        } else echo '<p>Góp ý này không đính kèm ảnh.</p>';
    },'dttl_feedback','normal','high');
});
add_action('admin_post_dttl_feedback_image',function(){
    $id=isset($_GET['id'])?absint($_GET['id']):0;
    if (!current_user_can('manage_options') || get_post_type($id)!=='dttl_feedback') wp_die('Không có quyền xem ảnh.','',array('response'=>403));
    check_admin_referer('dttl_feedback_image_'.$id);
    $image=get_post_meta($id,'_dttl_feedback_image',true);
    if (!is_array($image) || empty($image['data']) || !in_array($image['mime'],array('image/jpeg','image/png','image/webp'),true)) wp_die('Không tìm thấy ảnh.','',array('response'=>404));
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: '.$image['mime']);
    header('Content-Disposition: inline');
    echo base64_decode($image['data'],true);
    exit;
});
add_filter('wp_nav_menu_items',function($items,$args){
    if (isset($args->theme_location) && $args->theme_location==='primary') {
        $items=preg_replace('~<li\b[^>]*class=["\'][^"\']*dttl-nav-search[^"\']*["\'][^>]*>.*?</li>~s','',$items);
        $items.='<li class="dttl-nav-feedback"><button type="button" class="dttl-feedback-open" aria-haspopup="dialog" aria-controls="dttl-feedback-note"><span aria-hidden="true">✉</span> Hòm thư góp ý</button></li>';
    }
    return $items;
},999,2);
add_action('wp_footer',function(){ ?>
<style id="dttl-feedback-style">
.dttl-nav-feedback{display:flex;align-items:center}.main-nav .dttl-feedback-open{font:inherit;font-weight:700;font-size:13px;border:1px solid #b8cebd;background:#f1f7f1;color:#255638;border-radius:10px;padding:9px 12px;cursor:pointer;white-space:nowrap;display:inline-flex;gap:7px;align-items:center}.dttl-feedback-open:hover{background:#e4efe5}.dttl-feedback-open:focus-visible,#dttl-feedback-note button:focus-visible{outline:3px solid #b07935;outline-offset:3px}
#dttl-feedback-note{box-sizing:border-box;width:min(430px,calc(100vw - 28px));max-height:calc(100dvh - 32px);overflow:auto;border:1px solid #d9cda9;border-radius:18px;background:#fffcf0;color:#24352b;padding:27px;margin:auto;box-shadow:0 24px 80px #16291d40;font-family:inherit}#dttl-feedback-note::backdrop{background:#16291d66;backdrop-filter:blur(3px)}#dttl-feedback-note *{box-sizing:border-box}#dttl-feedback-note h2{font-size:24px;line-height:1.3;margin:5px 25px 8px 0;color:#214b33}#dttl-feedback-note .dttl-fb-eyebrow{font-size:11px;letter-spacing:2px;font-weight:800;color:#88713f;margin:0}#dttl-feedback-note .dttl-fb-intro{font-size:14px;line-height:1.6;margin:0 0 18px;color:#647065}#dttl-feedback-note .dttl-fb-close{position:absolute;top:14px;right:14px;background:transparent;color:#53634f;border:0;border-radius:50%;font-size:26px;line-height:30px;width:34px;height:34px;cursor:pointer}#dttl-feedback-note label{display:block;font-size:13px;font-weight:700;margin:12px 0 7px}#dttl-feedback-note textarea{display:block;resize:vertical;width:100%;min-height:148px;max-height:290px;border:1px solid #ded8bf;border-radius:9px;padding:12px;color:#24352b;font:15px/1.8 inherit;font-family:inherit;font-size:15px;line-height:1.8;background:repeating-linear-gradient(#fffef8,#fffef8 26px,#eae5d4 27px);outline-offset:3px}#dttl-feedback-note input[type=file]{width:100%;font-size:12px;color:#52614e}#dttl-feedback-note input::file-selector-button{background:#ede8d5;color:#43583e;border:0;padding:9px 12px;border-radius:7px;margin-right:9px;cursor:pointer}#dttl-feedback-note .dttl-fb-help{font-size:11px;line-height:1.55;color:#737966;margin:8px 0}#dttl-feedback-note .dttl-fb-honey{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}#dttl-feedback-note .dttl-fb-submit{display:flex;align-items:center;justify-content:center;gap:10px;border:0;border-radius:10px;width:100%;padding:13px;background:#285e3e;color:white;font:inherit;font-weight:700;cursor:pointer;margin-top:17px}#dttl-feedback-note button:disabled{opacity:.6;cursor:wait}#dttl-feedback-note .dttl-fb-status{font-size:13px;line-height:1.6;margin:10px 0 0;color:#934327}#dttl-feedback-note .dttl-fb-success{text-align:center;padding:16px 0 5px}#dttl-feedback-note .dttl-fb-success h3{font-size:21px;margin:4px 0 10px;color:#285e3e}#dttl-feedback-note .dttl-fb-success p{font-size:14px;line-height:1.7;color:#596753}#dttl-feedback-note .dttl-fb-mailbox{width:190px;height:175px;overflow:visible}#dttl-feedback-note.is-sent .dttl-fb-letter{animation:dttl-letter-in 1.15s ease-in-out both;transform-box:fill-box;transform-origin:center}#dttl-feedback-note.is-sent .dttl-fb-flag{animation:dttl-flag-up .4s 1s ease-out both;transform-origin:145px 112px}@keyframes dttl-letter-in{0%{transform:translateY(-56px) rotate(-12deg);opacity:0}20%{opacity:1}65%{transform:translateY(0) rotate(0);opacity:1}100%{transform:translateY(27px) scale(.7);opacity:0}}@keyframes dttl-flag-up{from{transform:rotate(80deg)}to{transform:rotate(0)}}@media(prefers-reduced-motion:reduce){#dttl-feedback-note.is-sent .dttl-fb-letter,#dttl-feedback-note.is-sent .dttl-fb-flag{animation:none}}@media(max-width:600px){#dttl-feedback-note{padding:22px}.dttl-nav-feedback{width:100%}.main-nav .dttl-feedback-open{width:100%;justify-content:center;margin-top:5px}}
</style>
<dialog id="dttl-feedback-note" aria-labelledby="dttl-feedback-heading" aria-describedby="dttl-feedback-description">
<button type="button" class="dttl-fb-close" aria-label="Đóng hòm thư">×</button>
<p class="dttl-fb-eyebrow">MỘT LỜI NHẮN NHỎ</p><h2 id="dttl-feedback-heading">Hòm thư góp ý</h2>
<p class="dttl-fb-intro" id="dttl-feedback-description">Anh/chị muốn trang tốt hơn ở điểm nào? Gửi một lời nhắn cho Đầu Tư Tương Lai nhé.</p>
<form id="dttl-feedback-form">
<label for="dttl-fb-message">Nội dung góp ý</label><textarea id="dttl-fb-message" name="message" minlength="5" maxlength="5000" required placeholder="Viết góp ý của anh/chị ở đây…"></textarea>
<label for="dttl-fb-photo">Đính kèm ảnh <span style="font-weight:400">(không bắt buộc)</span></label><input id="dttl-fb-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="dttl-fb-photo-help"><p class="dttl-fb-help" id="dttl-fb-photo-help">1 ảnh JPG, PNG hoặc WebP · tối đa 3 MB.</p>
<div class="dttl-fb-honey" aria-hidden="true"><label for="dttl-fb-website">Website</label><input id="dttl-fb-website" name="website" tabindex="-1" autocomplete="off"></div>
<p class="dttl-fb-help">Góp ý và ảnh chỉ dành cho quản trị viên, không hiển thị công khai. Vui lòng không gửi mật khẩu hay thông tin nhạy cảm.</p>
<button type="submit" class="dttl-fb-submit"><span>Gửi vào hòm thư</span><span aria-hidden="true">↗</span></button><p class="dttl-fb-status" role="status" aria-live="polite"></p>
</form>
<div class="dttl-fb-success" hidden tabindex="-1">
<svg class="dttl-fb-mailbox" viewBox="0 0 190 175" aria-hidden="true"><ellipse cx="95" cy="162" rx="65" ry="6" fill="#e8e3cc"/><path d="M93 119h13v40H93z" fill="#957549"/><rect x="38" y="66" width="115" height="67" rx="17" fill="#315d40"/><path d="M38 88c0-29 36-29 36 0v45H38z" fill="#477752"/><rect x="49" y="91" width="92" height="6" rx="3" fill="#183c29"/><g class="dttl-fb-letter"><rect x="60" y="45" width="67" height="43" rx="4" fill="#fffaf0" stroke="#b89961" stroke-width="2"/><path d="m61 48 32 24 33-24M62 85l22-21m42 21-22-21" fill="none" stroke="#b89961" stroke-width="2"/><path d="M110 53h10v9h-10z" fill="#bfd1b4"/></g><rect x="48" y="98" width="93" height="26" rx="5" fill="#315d40"/><text x="96" y="115" text-anchor="middle" fill="#f5e9be" font-size="9" font-family="sans-serif" letter-spacing="1">GÓP Ý</text><g class="dttl-fb-flag"><path d="M145 111V58h23v16h-19v37" fill="#c98f3e"/><circle cx="145" cy="112" r="5" fill="#e6c67b"/></g></svg>
<h3>Thư đã vào hòm!</h3><p>Đã nhận được góp ý của anh/chị.<br>Cảm ơn anh/chị đã giúp trang ngày một tốt hơn.</p><button type="button" class="dttl-fb-submit dttl-fb-done">Xong</button>
</div></dialog>
<script id="dttl-feedback-script">
(function(){
'use strict';
var dialog=document.getElementById('dttl-feedback-note'),form=document.getElementById('dttl-feedback-form');
if(!dialog||!form)return;
var message=form.querySelector('textarea'),photo=form.querySelector('[type=file]'),submit=form.querySelector('[type=submit]'),status=form.querySelector('.dttl-fb-status'),success=dialog.querySelector('.dttl-fb-success'),intro=dialog.querySelector('.dttl-fb-intro'),sending=false,opener=null,requestId='';
var endpoint=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
function newId(){return Date.now().toString(36)+'-'+Math.random().toString(36).slice(2)+'-'+Math.random().toString(36).slice(2);}
function close(){if(!sending)dialog.close();}
document.querySelectorAll('.dttl-feedback-open').forEach(function(button){button.addEventListener('click',function(){opener=button;if(dialog.classList.contains('is-sent')){dialog.classList.remove('is-sent');success.hidden=true;form.hidden=false;intro.hidden=false;status.textContent='';}if(!requestId)requestId=newId();dialog.showModal();message.focus();});});
dialog.querySelector('.dttl-fb-close').addEventListener('click',close);dialog.querySelector('.dttl-fb-done').addEventListener('click',close);
dialog.addEventListener('cancel',function(event){if(sending)event.preventDefault();});
dialog.addEventListener('close',function(){if(opener)opener.focus();});
dialog.addEventListener('click',function(event){if(event.target===dialog){var rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)close();}});
photo.addEventListener('change',function(){status.textContent='';});
form.addEventListener('submit',async function(event){
event.preventDefault();if(sending)return;
if(message.value.trim().length<5){status.textContent='Anh/chị vui lòng viết ít nhất 5 ký tự.';message.focus();return;}
var file=photo.files[0];if(file&&(file.size>3*1024*1024||!['image/jpeg','image/png','image/webp'].includes(file.type))){status.textContent='Chọn ảnh JPG, PNG hoặc WebP tối đa 3 MB.';photo.focus();return;}
sending=true;submit.disabled=true;dialog.querySelector('.dttl-fb-close').disabled=true;submit.querySelector('span').textContent='Đang gửi…';status.textContent='';
try{
var tokenBody=new FormData();tokenBody.set('action','dttl_feedback_token');
var tokenResponse=await fetch(endpoint,{method:'POST',body:tokenBody,credentials:'same-origin',cache:'no-store'});var token=await tokenResponse.json();
if(!tokenResponse.ok||!token.success||!token.data.nonce)throw new Error('Không kết nối được hòm thư. Anh/chị thử lại nhé.');
var data=new FormData(form);data.set('action','dttl_feedback_send');data.set('nonce',token.data.nonce);data.set('request_id',requestId);data.set('page',location.origin+location.pathname);
var response=await fetch(endpoint,{method:'POST',body:data,credentials:'same-origin',cache:'no-store'});var result=await response.json();
if(!response.ok||!result.success)throw new Error(result.data&&result.data.message?result.data.message:'Chưa gửi được góp ý. Anh/chị vui lòng thử lại.');
form.reset();requestId='';form.hidden=true;intro.hidden=true;success.hidden=false;dialog.classList.add('is-sent');success.focus();
}catch(error){status.textContent=error.message&&error.message.indexOf('JSON')===-1?error.message:'Chưa gửi được góp ý. Nội dung vẫn được giữ để anh/chị thử lại.';}
finally{sending=false;submit.disabled=false;dialog.querySelector('.dttl-fb-close').disabled=false;submit.querySelector('span').textContent='Gửi vào hòm thư';}
});
})();
</script>
<?php },30);

/* DTTL member access — 2026-10-20. Native WordPress email/password flow. */
add_filter('wp_nav_menu_items', function($items,$args){
 if(isset($args->theme_location) && $args->theme_location==='primary') {
  $items.='<li class="dttl-nav-account"><button type="button" class="dttl-account-open" aria-haspopup="dialog" aria-controls="dttl-account-dialog"><span aria-hidden="true">♙</span> Tài khoản</button></li>';
 }
 return $items;
},1000,2);
add_action('wp_footer',function(){ ?>
<style>
.dttl-nav-account{display:flex;align-items:center}.main-nav .dttl-account-open{font:inherit;font-weight:700;font-size:13px;border:1px solid #b8cebd;background:#285e3e;color:#fff;border-radius:10px;padding:9px 12px;cursor:pointer;white-space:nowrap;display:inline-flex;gap:7px;align-items:center}.dttl-account-open:focus-visible{outline:3px solid #b07935;outline-offset:3px}
#dttl-account-dialog{box-sizing:border-box;width:min(420px,calc(100vw - 28px));max-height:calc(100dvh - 32px);overflow:auto;border:1px solid #d9cda9;border-radius:18px;background:#fffcf0;color:#24352b;padding:28px;margin:auto;box-shadow:0 24px 80px #16291d40;font-family:inherit}#dttl-account-dialog::backdrop{background:#16291d66;backdrop-filter:blur(3px)}#dttl-account-dialog h2{font-size:25px;color:#214b33;margin:0 25px 14px 0}#dttl-account-dialog p{font-size:15px;line-height:1.7}#dttl-account-dialog .dttl-account-close{position:absolute;right:12px;top:10px;background:transparent;border:0;font-size:27px;color:#285e3e;cursor:pointer}#dttl-account-dialog a.dttl-account-link{display:block;padding:12px 16px;margin:10px 0;text-align:center;border-radius:10px;background:#285e3e;color:#fff;font-weight:700;text-decoration:none}#dttl-account-dialog a.dttl-account-secondary{background:#e7eee2;color:#285e3e}#dttl-account-dialog small{display:block;line-height:1.6;color:#657260}@media(max-width:600px){.dttl-nav-account{width:100%}.main-nav .dttl-account-open{width:100%;justify-content:center;margin-top:5px}}
</style>
<dialog id="dttl-account-dialog" aria-labelledby="dttl-account-title">
<button type="button" class="dttl-account-close" aria-label="Đóng tài khoản">×</button>
<h2 id="dttl-account-title">Tài khoản</h2>
<?php if(is_user_logged_in()): $member=wp_get_current_user(); ?>
<p>Xin chào, <strong><?php echo esc_html($member->display_name); ?></strong>.</p>
<a class="dttl-account-link" href="<?php echo esc_url(home_url('/cong-cu-dau-tu/')); ?>">Công cụ & tiến độ học</a>
<a class="dttl-account-link dttl-account-secondary" href="<?php echo esc_url(home_url('/tai-khoan/#dm-wallet')); ?>">Ví AIM của tôi</a><a class="dttl-account-link dttl-account-secondary" href="<?php echo esc_url(home_url('/tai-khoan/')); ?>">Bài viết & tài khoản của tôi</a>
<a class="dttl-account-link dttl-account-secondary" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Đăng xuất</a>
<?php else: ?>
<p>Đăng nhập để sử dụng tài khoản Đầu Tư Tương Lai.</p>
<a class="dttl-account-link" href="<?php echo esc_url(wp_login_url(home_url('/tai-khoan/'))); ?>">Đăng nhập</a>
<?php if(get_option('users_can_register')): ?><a class="dttl-account-link dttl-account-secondary" href="<?php echo esc_url(wp_registration_url()); ?>">Đăng ký miễn phí</a><?php endif; ?>
<p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Quên mật khẩu / Gửi lại email đặt mật khẩu</a></p>
<small>Đăng ký bằng email của bạn. Mở thư xác nhận để đặt mật khẩu trước khi đăng nhập. Kiểm tra cả mục Spam nếu chưa thấy thư.</small>
<?php endif; ?>
</dialog>
<script>(function(){var d=document.getElementById('dttl-account-dialog');if(!d)return;var opener;document.querySelectorAll('.dttl-account-open').forEach(function(b){b.addEventListener('click',function(){opener=b;d.showModal();});});d.querySelector('.dttl-account-close').addEventListener('click',function(){d.close();});d.addEventListener('close',function(){if(opener)opener.focus();});d.addEventListener('click',function(e){if(e.target===d){var r=d.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)d.close();}});})();</script>
<?php },31);
add_filter('login_headerurl',function(){return home_url('/');});
add_filter('login_headertext',function(){return 'Đầu Tư Tương Lai';});
add_action('login_enqueue_scripts',function(){ ?>
<style>body.login{background:#f3f5ee;color:#24352b}body.login #login{width:min(380px,calc(100% - 36px));padding-top:6vh}body.login h1 a{background:none;width:auto;height:auto;text-indent:0;font-size:27px;line-height:1.4;font-weight:750;color:#285e3e;margin-bottom:22px}body.login form{border:1px solid #d9dfd0;border-radius:16px;box-shadow:0 12px 40px #214b3310;background:#fffef8;padding:26px}body.login .button-primary{background:#285e3e;border-color:#285e3e;border-radius:7px}body.login input.input{border-radius:7px}body.login #nav a,body.login #backtoblog a{color:#285e3e}body.login .message{border-left-color:#285e3e}body.login .language-switcher{display:none}</style>
<?php });
add_filter('login_message',function($message){
 $action=isset($_GET['action']) && is_string($_GET['action'])?sanitize_key($_GET['action']):'login';
 if($action==='register')$message.='<p class="message">Tạo tài khoản miễn phí. Sau khi đăng ký, hãy mở email để xác nhận và tự đặt mật khẩu.</p>';
 return $message;
});
add_filter('login_redirect',function($redirect,$requested,$user){
 if($user instanceof WP_User && !user_can($user,'edit_posts'))return home_url('/tai-khoan/');
 return $redirect;
},10,3);
add_filter('show_admin_bar',function($show){return current_user_can('edit_posts')?$show:false;});
/* Limit public registration mail volume without changing login credentials. */
add_filter('registration_errors',function($errors,$login,$email){
 $ip=isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'';
 $key='dttl_reg_'.substr(hash_hmac('sha256',$ip,wp_salt('nonce')),0,32);
 $count=(int)get_transient($key);
 if($count>=5)$errors->add('dttl_rate','Bạn đã thử đăng ký nhiều lần. Vui lòng thử lại sau một giờ.');
 else set_transient($key,$count+1,HOUR_IN_SECONDS);
 return $errors;
},20,3);

/* DTTL member workspace v1 — 2026-10-20. */
function dttl_member_url(){return home_url('/tai-khoan/');}
function dttl_member_record($name){$x=get_user_meta(get_current_user_id(),'_dttl_member_'.$name,true);return is_array($x)&&isset($x['rev'])?$x:array('rev'=>0,'data'=>null);}
function dttl_member_fail($message,$code=400){wp_send_json_error(array('message'=>$message),$code);}
function dttl_member_text($key){return isset($_POST[$key])&&is_string($_POST[$key])?wp_unslash($_POST[$key]):'';}
function dttl_member_ids($value,$max=2000){if(!is_array($value)||count($value)>$max)dttl_member_fail('Danh sách vượt giới hạn.');return array_values(array_unique(array_filter(array_map('absint',$value))));}
function dttl_member_clean($name,$data){
 if(!is_array($data))dttl_member_fail('Dữ liệu không hợp lệ.');
 if($name==='learning')return array('stageDays'=>array_values(array_intersect(dttl_member_ids($data['stageDays']??array(),7),range(1,7))),'posts'=>dttl_member_ids($data['posts']??array()));
 if($name==='favorites'){return array_values(array_filter(dttl_member_ids($data,500),function($id){return get_post_type($id)==='post'&&get_post_status($id)==='publish';}));}
 if($name==='quiz'){
  if(count($data)>20)dttl_member_fail('Chỉ lưu 20 lần kiểm tra gần nhất.');$out=array();
  foreach($data as $r){if(!is_array($r)||!isset($r['score'],$r['total'])||!is_numeric($r['score'])||!is_numeric($r['total']))dttl_member_fail('Kết quả không hợp lệ.');$total=(int)$r['total'];$score=(int)$r['score'];if($total<1||$total>100||$score<0||$score>$total)dttl_member_fail('Điểm không hợp lệ.');$out[]=array('score'=>$score,'total'=>$total,'at'=>sanitize_text_field($r['at']??''));}return $out;
 }
 if($name==='portfolio'){
  if(($data['version']??0)!==1||!isset($data['initial'],$data['trades'],$data['quotes'])||!is_numeric($data['initial'])||$data['initial']<1||$data['initial']>1000000000000||!is_array($data['trades'])||count($data['trades'])>1000||!is_array($data['quotes'])||count($data['quotes'])>100)dttl_member_fail('Danh mục không đúng định dạng.');
  $clean=array('version'=>1,'initial'=>(float)$data['initial'],'trades'=>array(),'quotes'=>new stdClass());$quotes=array();
  foreach($data['trades'] as $t){if(!is_array($t)||!in_array($t['side']??'',array('buy','sell'),true)||!preg_match('/^[A-Z][A-Z0-9]{1,9}$/',$t['symbol']??''))dttl_member_fail('Giao dịch không hợp lệ.');foreach(array('qty'=>1000000,'price'=>1000000000,'fee'=>1000000000) as $k=>$max){if(!isset($t[$k])||!is_numeric($t[$k])||floor($t[$k])!=$t[$k]||$t[$k]<($k==='fee'?0:1)||$t[$k]>$max)dttl_member_fail('Số liệu mô phỏng không hợp lệ.');}if(!is_string($t['at']??null)||!strtotime($t['at'])||strlen($t['at'])>35)dttl_member_fail('Ngày không hợp lệ.');$clean['trades'][]=array('side'=>$t['side'],'symbol'=>$t['symbol'],'qty'=>(int)$t['qty'],'price'=>(int)$t['price'],'fee'=>(int)$t['fee'],'note'=>mb_substr(sanitize_textarea_field($t['note']??''),0,300),'at'=>$t['at']);}
  foreach($data['quotes'] as $symbol=>$q){if(!preg_match('/^[A-Z][A-Z0-9]{1,9}$/',$symbol)||!is_array($q)||!is_numeric($q['price']??null)||$q['price']<1||$q['price']>1000000000||!is_string($q['at']??null)||!strtotime($q['at']))dttl_member_fail('Giá mô phỏng không hợp lệ.');$quotes[$symbol]=array('price'=>(int)$q['price'],'at'=>sanitize_text_field($q['at']));}$clean['quotes']=(object)$quotes;return $clean;
 }
 dttl_member_fail('Loại dữ liệu không hợp lệ.');
}
add_action('wp_ajax_dttl_member_api',function(){
 nocache_headers();if($_SERVER['REQUEST_METHOD']!=='POST')dttl_member_fail('Yêu cầu không hợp lệ.',405);
 if(!is_user_logged_in())dttl_member_fail('Vui lòng đăng nhập.',401);
 if(!check_ajax_referer('dttl_member','nonce',false))dttl_member_fail('Phiên đã hết hạn. Hãy tải lại trang.',403);
 $uid=get_current_user_id();$op=sanitize_key(dttl_member_text('op'));
 if($op==='state'){
  $name=sanitize_key(dttl_member_text('name'));if(!in_array($name,array('learning','favorites','quiz','portfolio'),true))dttl_member_fail('Không hợp lệ.');
  $raw=dttl_member_text('data');if(strlen($raw)>800000)dttl_member_fail('Dữ liệu quá lớn.');$data=dttl_member_clean($name,json_decode($raw,true));$old=dttl_member_record($name);$expected=absint(dttl_member_text('rev'));
  if($expected!==$old['rev'])dttl_member_fail('Dữ liệu đã đổi ở thiết bị khác. Tải lại trang trước khi sửa tiếp.',409);
  $next=array('rev'=>$old['rev']+1,'data'=>$data,'updated'=>time());$key='_dttl_member_'.$name;
  $ok=$old['rev']?update_user_meta($uid,$key,$next,$old):add_user_meta($uid,$key,$next,true);
  if(!$ok)dttl_member_fail('Chưa lưu được hoặc dữ liệu vừa thay đổi. Hãy tải lại trang.',409);
  wp_send_json_success(array('rev'=>$next['rev'],'message'=>'Đã lưu vào tài khoản.'));
 }
 if($op==='profile'){
  $name=sanitize_text_field(dttl_member_text('display_name'));if(mb_strlen($name)<2||mb_strlen($name)>60)dttl_member_fail('Tên hiển thị từ 2–60 ký tự.');
  $r=wp_update_user(array('ID'=>$uid,'display_name'=>$name));if(is_wp_error($r))dttl_member_fail('Chưa lưu được hồ sơ.');wp_send_json_success(array('message'=>'Đã lưu tên hiển thị.'));
 }
 if($op==='article'){
  $id=absint(dttl_member_text('post_id'));$title=sanitize_text_field(dttl_member_text('title'));$body=sanitize_textarea_field(dttl_member_text('body'));$mode=dttl_member_text('mode');
  if(mb_strlen($title)<5||mb_strlen($title)>180||mb_strlen($body)<30||mb_strlen($body)>30000)dttl_member_fail('Tiêu đề 5–180 ký tự; nội dung 30–30.000 ký tự.');
  if(!in_array($mode,array('draft','pending'),true))dttl_member_fail('Trạng thái không hợp lệ.');
  if($id){$p=get_post($id);if(!$p||$p->post_type!=='post'||(int)$p->post_author!==$uid||!get_post_meta($id,'_dttl_member_submission',true)||!in_array($p->post_status,array('draft','pending'),true))dttl_member_fail('Chỉ chỉnh sửa bài riêng chưa xuất bản.',403);if(dttl_member_text('modified')!==$p->post_modified_gmt)dttl_member_fail('Bài đã được cập nhật. Hãy mở lại bài trước khi sửa.',409);}
  else{$rate='dttl_article_'.$uid;$count=(int)get_transient($rate);if($count>=10)dttl_member_fail('Bạn đã tạo 10 bài hôm nay. Vui lòng thử lại ngày mai.',429);}
  if(!empty($_FILES['photo']['name'])){
   $f=$_FILES['photo'];if(is_array($f['error'])||$f['error']!==UPLOAD_ERR_OK||$f['size']>3*MB_IN_BYTES||!is_uploaded_file($f['tmp_name']))dttl_member_fail('Ảnh JPG, PNG hoặc WebP tối đa 3 MB.');$info=@getimagesize($f['tmp_name']);if(!$info||!in_array($info['mime'],array('image/jpeg','image/png','image/webp'),true)||$info[0]*$info[1]>20000000)dttl_member_fail('Ảnh không hợp lệ hoặc quá 20 triệu điểm ảnh.');
  }
  $cat=get_category_by_slug('goc-chia-se');if(!$cat)dttl_member_fail('Chuyên mục Góc chia sẻ chưa sẵn sàng.');
  $post=array('post_type'=>'post','post_title'=>$title,'post_content'=>wpautop(esc_html($body)),'post_status'=>$mode,'post_category'=>array($cat->term_id),'comment_status'=>'closed');
  if($id){$post['ID']=$id;$result=wp_update_post(wp_slash($post),true);}else{$post['post_author']=$uid;$result=wp_insert_post(wp_slash($post),true);}
  if(is_wp_error($result)||!$result)dttl_member_fail('Chưa lưu được bài.');$is_new=!$id;$id=$result;
  update_post_meta($id,'_dttl_member_submission',1);update_post_meta($id,'_dttl_member_body',wp_slash($body));
  if($is_new)set_transient('dttl_article_'.$uid,$count+1,DAY_IN_SECONDS);
  $image_warning='';if(!empty($_FILES['photo']['name'])){
   require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
   $image=media_handle_upload('photo',$id,array(),array('test_form'=>false,'mimes'=>array('jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp')));
   if(is_wp_error($image))$image_warning=' Ảnh chưa tải được; mở lại bài để thử ảnh khác.';else{set_post_thumbnail($id,$image);update_post_meta($image,'_wp_attachment_image_alt',$title);}
  }
  $saved=get_post($id);wp_send_json_success(array('id'=>$id,'modified'=>$saved->post_modified_gmt,'message'=>($mode==='pending'?'Đã gửi admin duyệt. Bài chưa hiển thị công khai.':'Đã lưu bản nháp.').$image_warning,'url'=>add_query_arg('edit',$id,dttl_member_url())));
 }
 dttl_member_fail('Thao tác không hợp lệ.');
});
add_action('wp_ajax_nopriv_dttl_member_api',function(){dttl_member_fail('Vui lòng đăng nhập rồi thử lại.',401);});
add_action('admin_init',function(){if(!current_user_can('manage_options')||get_option('dttl_member_page_v1'))return;$p=get_page_by_path('tai-khoan');if(!$p){$id=wp_insert_post(array('post_type'=>'page','post_title'=>'Tài khoản của tôi','post_name'=>'tai-khoan','post_status'=>'publish','post_content'=>'[dttl_member_workspace]','comment_status'=>'closed'),true);if(is_wp_error($id)||!$id)return;}else if(strpos($p->post_content,'[dttl_member_workspace]')===false)return;update_option('dttl_member_page_v1',1);});
add_action('template_redirect',function(){if(is_page('tai-khoan')){if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();}});
add_filter('wp_robots',function($robots){if(is_page('tai-khoan')){$robots['noindex']=true;$robots['nofollow']=true;}return $robots;});
add_action('add_meta_boxes_post',function($post){if(!get_post_meta($post->ID,'_dttl_member_submission',true))return;add_meta_box('dttl-member-review','Góp ý cho thành viên',function($post){wp_nonce_field('dttl_member_review','dttl_member_review_nonce');echo '<p>Để yêu cầu chỉnh sửa: ghi góp ý và chuyển trạng thái bài về Nháp. Thành viên sẽ thấy góp ý trong Bài của tôi. Khi duyệt, dùng nút Đăng của WordPress.</p><textarea name="dttl_member_review_note" rows="5" style="width:100%">'.esc_textarea(get_post_meta($post->ID,'_dttl_member_review_note',true)).'</textarea>';},'post','normal','high');});
add_action('save_post_post',function($id){if(!current_user_can('edit_others_posts')||wp_is_post_revision($id)||!get_post_meta($id,'_dttl_member_submission',true)||!isset($_POST['dttl_member_review_nonce'])||!wp_verify_nonce($_POST['dttl_member_review_nonce'],'dttl_member_review'))return;$note=sanitize_textarea_field(wp_unslash($_POST['dttl_member_review_note']??''));update_post_meta($id,'_dttl_member_review_note',$note);});
add_action('admin_menu',function(){add_submenu_page('edit.php','Bài thành viên chờ duyệt','Bài thành viên chờ duyệt','edit_others_posts','edit.php?post_status=pending&post_type=post&dttl_members=1');});
add_action('pre_get_posts',function($q){if(is_admin()&&$q->is_main_query()&&current_user_can('edit_others_posts')&&isset($_GET['dttl_members']))$q->set('meta_key','_dttl_member_submission');});
add_shortcode('dttl_member_workspace',function(){
 ob_start(); ?><section class="dttl-member"><p class="eyebrow dark">GÓC CỦA BẠN</p>
<?php if(!is_user_logged_in()): ?><h2>Đăng nhập để tiếp tục</h2><p>Gửi bài chia sẻ chờ admin duyệt, lưu bài yêu thích, tiến độ học và danh mục mô phỏng theo tài khoản.</p><p><a class="dm-button" href="<?php echo esc_url(wp_login_url(dttl_member_url())); ?>">Đăng nhập</a> <a class="dm-button dm-secondary" href="<?php echo esc_url(wp_registration_url()); ?>">Đăng ký miễn phí</a></p></section><?php return ob_get_clean();endif;
 $u=wp_get_current_user();$learning=dttl_member_record('learning')['data']??array('stageDays'=>array(),'posts'=>array());$favorites=dttl_member_record('favorites')['data']??array();$quizzes=dttl_member_record('quiz')['data']??array();$portfolio=dttl_member_record('portfolio');
 ?><h2>Xin chào, <?php echo esc_html($u->display_name); ?></h2><p>Một nơi để viết, lưu lại và tiếp tục hành trình học của bạn.</p>
<?php echo dttl_aim_wallet(); ?><nav class="dm-tabs" aria-label="Khu vực thành viên"><a href="#dm-wallet">Ví AIM</a><a href="#dm-posts">Bài của tôi</a><a href="#dm-write">Viết bài</a><a href="#dm-favorites">Bài đã lưu</a><a href="#dm-progress">Tiến độ học</a><a href="#dm-portfolio">Mô phỏng</a><a href="#dm-profile">Hồ sơ</a></nav>
<section class="dm-card" id="dm-posts"><h3>Bài của tôi</h3><p>Bài chỉ xuất hiện trong Góc chia sẻ sau khi admin duyệt đăng.</p>
<?php $page=max(1,absint($_GET['member_page']??1));$posts=new WP_Query(array('post_type'=>'post','author'=>$u->ID,'post_status'=>array('draft','pending','publish'),'meta_key'=>'_dttl_member_submission','posts_per_page'=>10,'paged'=>$page));if(!$posts->have_posts())echo '<p>Chưa có bài nào. Hãy viết chia sẻ đầu tiên bên dưới.</p>';foreach($posts->posts as $p){$note=get_post_meta($p->ID,'_dttl_member_review_note',true);$label=$p->post_status==='publish'?'Đã đăng':($p->post_status==='pending'?'Chờ duyệt':($note?'Cần chỉnh sửa':'Bản nháp'));echo '<article class="dm-item"><strong>'.esc_html($p->post_title).'</strong><span class="dm-badge">'.esc_html($label).'</span>';if($p->post_status==='publish')echo '<a href="'.esc_url(get_permalink($p)).'">Xem bài →</a>';else echo '<a href="'.esc_url(add_query_arg('edit',$p->ID,dttl_member_url()).'#dm-write').'">Mở / chỉnh sửa →</a>';if($note)echo '<p class="dm-note">Góp ý của admin: '.nl2br(esc_html($note)).'</p>';echo '</article>';}
 if($posts->max_num_pages>1)echo '<p>'.paginate_links(array('base'=>add_query_arg('member_page','%#%',dttl_member_url()),'format'=>'','current'=>$page,'total'=>$posts->max_num_pages)).'</p>';
 $edit=absint($_GET['edit']??0);$editing=$edit?get_post($edit):null;if($editing&&((int)$editing->post_author!==$u->ID||$editing->post_type!=='post'||!get_post_meta($edit,'_dttl_member_submission',true)||!in_array($editing->post_status,array('draft','pending'),true)))$editing=null;
 ?></section>
<section class="dm-card" id="dm-write"><h3><?php echo $editing?'Chỉnh sửa bài chia sẻ':'Viết bài chia sẻ'; ?></h3><p>Chia sẻ trải nghiệm, bài học hoặc góc nhìn của bạn. Viết bằng lời của mình và ghi nguồn khi tham khảo.</p><form id="dm-article" enctype="multipart/form-data"><input type="hidden" name="post_id" value="<?php echo $editing?(int)$editing->ID:0; ?>"><input type="hidden" name="modified" value="<?php echo $editing?esc_attr($editing->post_modified_gmt):''; ?>"><label>Tiêu đề<input name="title" required minlength="5" maxlength="180" value="<?php echo $editing?esc_attr($editing->post_title):''; ?>"></label><label>Nội dung<textarea name="body" required minlength="30" maxlength="30000" rows="12" placeholder="Câu chuyện, điều bạn học được và nguồn tham khảo…"><?php echo $editing?esc_textarea(get_post_meta($editing->ID,'_dttl_member_body',true)?:wp_strip_all_tags($editing->post_content)):''; ?></textarea></label><label>Ảnh đại diện (không bắt buộc)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label><small>JPG, PNG hoặc WebP · tối đa 3 MB. Không tải ảnh chứa thông tin riêng tư.</small><?php if($editing&&has_post_thumbnail($editing))echo get_the_post_thumbnail($editing,'thumbnail',array('class'=>'dm-thumb')); ?><div class="dm-actions"><button type="submit" name="mode" value="draft" class="dm-secondary">Lưu nháp</button><button type="submit" name="mode" value="pending">Gửi admin duyệt</button><?php if($editing): ?><a href="<?php echo esc_url(dttl_member_url().'#dm-write'); ?>">Viết bài mới</a><?php endif; ?></div><p class="dm-status" role="status"></p></form></section>
<section class="dm-card" id="dm-favorites"><h3>Bài đã lưu</h3><?php $shown=0;foreach($favorites as $id){if(get_post_status($id)!=='publish')continue;$shown++;echo '<div class="dm-item"><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a><button type="button" class="dm-save dm-secondary" data-dm-save="'.(int)$id.'">Bỏ lưu</button></div>';}if(!$shown)echo '<p>Chưa lưu bài nào. Bấm “Lưu bài” ở cuối bài viết để đọc lại tại đây.</p>'; ?></section>
<section class="dm-card" id="dm-progress"><h3>Tiến độ học & kết quả kiểm tra</h3><div class="dm-stats"><div><strong><?php echo count($learning['stageDays']); ?>/7</strong><span>chặng đã hoàn thành</span></div><div><strong><?php echo count($learning['posts']); ?></strong><span>bài đã đọc</span></div></div><p><a class="dm-button" href="<?php echo esc_url(home_url('/cong-cu-dau-tu/')); ?>">Cập nhật tiến độ học</a> <a href="<?php echo esc_url(dttl_learning_post_url('bai-kiem-tra-kien-thuc-chung-khoan')); ?>">Làm bài kiểm tra →</a></p><h4>Kết quả gần đây</h4><?php if(!$quizzes)echo '<p>Chưa có kết quả được lưu.</p>';foreach(array_reverse($quizzes) as $q)echo '<p>'.esc_html($q['score'].'/'.$q['total'].' câu đúng · '.(strtotime($q['at'])?wp_date('d/m/Y H:i',strtotime($q['at'])):'')).'</p>'; ?><small>Kết quả tự luyện, không phải chứng chỉ.</small></section>
<section class="dm-card" id="dm-portfolio"><h3>Danh mục mô phỏng của tôi</h3><p><?php echo $portfolio['rev']?'Danh mục đã được lưu riêng theo tài khoản.':'Bắt đầu bài tập bằng tiền giả lập và lưu lại để tiếp tục trên thiết bị khác.'; ?></p><a class="dm-button" href="<?php echo esc_url(home_url('/danh-muc-mo-phong/')); ?>">Mở danh mục mô phỏng</a><p><small>Giá nhập tay, tiền giả lập; không gửi lệnh giao dịch thật.</small></p></section>
<section class="dm-card" id="dm-profile"><h3>Hồ sơ</h3><form id="dm-profile-form"><label>Tên hiển thị<input name="display_name" minlength="2" maxlength="60" required value="<?php echo esc_attr($u->display_name); ?>"></label><p>Email: <?php echo esc_html($u->user_email); ?></p><button type="submit">Lưu hồ sơ</button><p class="dm-status" role="status"></p></form><p><a href="<?php echo esc_url(wp_lostpassword_url(dttl_member_url())); ?>">Đặt lại mật khẩu qua email</a> · <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Đăng xuất</a></p></section>
</section><?php return ob_get_clean();});
add_filter('the_content',function($html){if(is_singular('post')&&in_the_loop()&&is_main_query())$html.='<section class="dm-save-panel"><button type="button" class="dm-save" data-dm-save="'.get_the_ID().'">Lưu bài</button> <a href="'.esc_url(dttl_member_url().'#dm-favorites').'">Bài đã lưu</a><span class="dm-save-status" role="status"></span></section>';return $html;},30);
add_action('wp_head',function(){if(!is_user_logged_in())return;$data=array('uid'=>get_current_user_id(),'nonce'=>wp_create_nonce('dttl_member'),'endpoint'=>admin_url('admin-ajax.php'),'records'=>array());foreach(array('learning','favorites','quiz','portfolio') as $name)$data['records'][$name]=dttl_member_record($name);echo '<script>window.DTTLMember='.wp_json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>';},50);
add_action('wp_footer',function(){ ?>
<style>.dttl-member{max-width:940px;margin:auto}.dm-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0}.dm-tabs a,.dm-badge{background:#edf4e9;color:#285e3e;border-radius:20px;padding:8px 13px;font-size:13px;text-decoration:none}.dm-card{padding:25px;margin:22px 0;background:#fffef8;border:1px solid #dce4d7;border-radius:16px;scroll-margin-top:90px}.dm-card h3{margin:0 0 14px;font-size:23px;color:#285e3e}.dm-card label{display:block;font-weight:600;margin:14px 0}.dm-card input:not([type=hidden]),.dm-card textarea{display:block;width:100%;max-width:100%;box-sizing:border-box;font:inherit;padding:11px;border:1px solid #cbd6c7;border-radius:8px;margin-top:7px;background:#fff}.dm-card textarea{resize:vertical}.dm-card button,.dm-button,.dm-save-panel button{display:inline-block;background:#285e3e;color:#fff;border:0;border-radius:8px;padding:11px 17px;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}.dm-card .dm-secondary,.dm-secondary{background:#e7eee2;color:#285e3e}.dm-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}.dm-item{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:15px 0;border-bottom:1px solid #e3e8df}.dm-item>strong,.dm-item>a:first-child{flex:1;min-width:160px}.dm-note{flex-basis:100%;white-space:normal;background:#faf0d7;padding:12px;border-radius:8px}.dm-status,.dm-save-status{display:block;line-height:1.6;color:#28633e;margin-top:10px}.dm-stats{display:flex;gap:24px}.dm-stats strong{display:block;font-size:30px;color:#285e3e}.dm-stats span{font-size:13px}.dm-thumb{display:block;margin-top:12px;max-width:150px;height:auto}.dm-save-panel{padding:20px;margin:20px 0;background:#f2f6ee;border-radius:12px}.dm-card button:disabled{opacity:.55;cursor:wait}.dm-sync-status{padding:10px 14px;background:#edf4e9;border-radius:9px;font-size:13px;line-height:1.6;margin:12px 0}.dm-card small{font-size:12px;line-height:1.6;color:#62715e}@media(max-width:600px){.dm-card{padding:17px}.dm-tabs{gap:6px}.dm-tabs a{font-size:12px;padding:8px 10px}}
</style>
<script>
(function(){'use strict';var M=window.DTTLMember;var accountUrl=<?php echo wp_json_encode(dttl_member_url()); ?>;
if(M){
 M.chains={};M.failed={};M.save=function(name,data){var snapshot=JSON.parse(JSON.stringify(data));var next=(M.chains[name]||Promise.resolve()).then(async function(){if(M.failed[name])throw Error('Đồng bộ đang dừng. Tải lại trang trước khi sửa tiếp.');var fd=new FormData();fd.set('action','dttl_member_api');fd.set('op','state');fd.set('nonce',M.nonce);fd.set('name',name);fd.set('rev',M.records[name].rev);fd.set('data',JSON.stringify(snapshot));var response=await fetch(M.endpoint,{method:'POST',credentials:'same-origin',body:fd,cache:'no-store'});var result=await response.json();if(!response.ok||!result.success)throw Error(result.data&&result.data.message||'Chưa đồng bộ được. Hãy kiểm tra kết nối rồi tải lại trang.');M.records[name]={rev:result.data.rev,data:snapshot};return result.data;});M.chains[name]=next.catch(function(err){M.failed[name]=true;throw err;});M.chains[name].catch(function(){});return next;};
 window.addEventListener('beforeunload',function(e){if(M.pending){e.preventDefault();e.returnValue='';}});
 M.sync=function(name,data,note){M.pending=(M.pending||0)+1;if(note)note.textContent='Đang lưu vào tài khoản…';return M.save(name,data).then(function(){if(note)note.textContent='Đã đồng bộ vào tài khoản.';}).catch(function(err){if(note)note.textContent='Chưa đồng bộ: '+err.message;throw err;}).finally(function(){M.pending--;});};
}
function setSaved(){var ids=M&&M.records.favorites.data||[];document.querySelectorAll('[data-dm-save]').forEach(function(b){var yes=ids.includes(Number(b.dataset.dmSave));b.textContent=yes?'Bỏ lưu':'Lưu bài';b.setAttribute('aria-pressed',String(yes));});}setSaved();
document.querySelectorAll('[data-dm-save]').forEach(function(b){b.addEventListener('click',async function(){if(!M){location.href=accountUrl;return;}var id=Number(b.dataset.dmSave),ids=(M.records.favorites.data||[]).slice(),next=ids.includes(id)?ids.filter(function(x){return x!==id;}):ids.concat(id);var note=b.parentElement.querySelector('[role=status]');if(!note){note=document.createElement('span');note.setAttribute('role','status');b.parentElement.appendChild(note);}document.querySelectorAll('[data-dm-save]').forEach(function(x){x.disabled=true;});try{await M.sync('favorites',next,note);setSaved();}catch(e){}finally{document.querySelectorAll('[data-dm-save]').forEach(function(x){x.disabled=false;});}});});
async function send(form,op,extra){var status=form.querySelector('.dm-status');if(!M){status.textContent='Vui lòng đăng nhập.';return;}var fd=new FormData(form);fd.set('action','dttl_member_api');fd.set('op',op);fd.set('nonce',M.nonce);Object.keys(extra||{}).forEach(function(k){fd.set(k,extra[k]);});var buttons=form.querySelectorAll('button');buttons.forEach(function(b){b.disabled=true;});status.textContent='Đang lưu…';try{var r=await fetch(M.endpoint,{method:'POST',credentials:'same-origin',body:fd,cache:'no-store'}),j=await r.json();if(!r.ok||!j.success)throw Error(j.data&&j.data.message||'Chưa lưu được.');status.textContent=j.data.message;if(j.data.id){form.elements.post_id.value=j.data.id;form.elements.modified.value=j.data.modified;form.elements.photo.value='';var a=document.createElement('a');a.href=accountUrl+'#dm-posts';a.textContent=' Xem danh sách bài →';status.appendChild(a);}return true;}catch(e){status.textContent=e.message;return false;}finally{buttons.forEach(function(b){b.disabled=false;});}}
var article=document.getElementById('dm-article');if(article){var dirty=false;article.addEventListener('input',function(){dirty=true;});article.addEventListener('submit',async function(e){e.preventDefault();if(!article.reportValidity())return;var file=article.elements.photo.files[0];if(file&&file.size>3*1024*1024){article.querySelector('.dm-status').textContent='Ảnh tối đa 3 MB.';return;}var saved=await send(article,'article',{mode:e.submitter&&e.submitter.value||'draft'});if(saved)dirty=false;});window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue='';}});}
var profile=document.getElementById('dm-profile-form');if(profile)profile.addEventListener('submit',function(e){e.preventDefault();send(profile,'profile');});
if(M)document.querySelectorAll('[data-stock-quiz]').forEach(function(form){form.addEventListener('submit',function(){setTimeout(function(){var qs=Array.from(form.querySelectorAll('[data-answer]'));if(!qs.length||qs.some(function(q){return !q.querySelector('input:checked');}))return;var score=qs.filter(function(q){return q.querySelector('input:checked').value===q.dataset.answer;}).length;var records=(M.records.quiz.data||[]).slice(-19);records.push({score:score,total:qs.length,at:new Date().toISOString()});var note=form.querySelector('.dm-quiz-sync');if(!note){note=document.createElement('p');note.className='dm-quiz-sync';note.setAttribute('role','status');form.appendChild(note);}M.sync('quiz',records,note).catch(function(){});},0);});});
})();
</script>
<?php },19);

add_filter('script_loader_src',function($src,$handle){if(in_array($handle,array('dttl-learning','dttl-portfolio'),true))return add_query_arg('ver','2.0.0-member-20261020',$src);return $src;},20,2);

/* AIM internal pilot wallet — integer tenths, no blockchain or money conversion. */
function dttl_aim_record($uid){$r=get_user_meta($uid,'_dttl_aim_wallet_v1',true);return is_array($r)&&isset($r['entries'])?$r:array('version'=>1,'free_units'=>0,'balance_units'=>0,'entries'=>array());}
function dttl_aim_amount($units){return number_format(((int)$units)/10,1,',','.').' AIM';}
function dttl_aim_lock($name){global $wpdb;return (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',substr('dttl_aim_'.hash('sha256',$wpdb->prefix.$name),0,64)))==='1';}
function dttl_aim_unlock($name){global $wpdb;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',substr('dttl_aim_'.hash('sha256',$wpdb->prefix.$name),0,64)));}
function dttl_aim_grant($uid,$event,$units,$label){
 if(!$uid||!get_userdata($uid)||$units<1||$units>5)return false;
 if(!dttl_aim_lock('user_'.$uid))return false;
 try{wp_cache_delete($uid,'user_meta');$r=dttl_aim_record($uid);foreach($r['entries'] as $entry)if($entry['event']===$event)return true;
 $amount=min((int)$units,max(0,10-(int)$r['free_units']));
 $r['entries'][]=array('event'=>$event,'units'=>$amount,'label'=>$label,'at'=>time(),'limited'=>$amount<$units);
 $r['free_units']+=$amount;$r['balance_units']+=$amount;
 return (bool)update_user_meta($uid,'_dttl_aim_wallet_v1',$r);
 }finally{dttl_aim_unlock('user_'.$uid);}
}
function dttl_aim_verified($uid){
 update_user_meta($uid,'_dttl_aim_email_verified',1);
 return dttl_aim_grant($uid,'welcome',5,'Thưởng tài khoản đã xác nhận email');
}
add_action('after_password_reset',function($user){dttl_aim_verified($user->ID);},20,1);
function dttl_aim_reward_post($id){
 if(get_post_type($id)!=='post'||get_post_status($id)!=='publish'||!get_post_meta($id,'_dttl_member_submission',true))return;
 if(!dttl_aim_lock('post_'.$id))return;
 try{wp_cache_delete($id,'post_meta');if(get_post_meta($id,'_dttl_aim_reward_recorded',true))return;$uid=(int)get_post_field('post_author',$id);if(!get_user_meta($uid,'_dttl_aim_email_verified',true))return;
 if(dttl_aim_grant($uid,'post:'.$id,1,'Bài chia sẻ được duyệt: '.get_the_title($id)))update_post_meta($id,'_dttl_aim_reward_recorded',$uid);
 }finally{dttl_aim_unlock('post_'.$id);}
}
add_action('transition_post_status',function($new,$old,$post){if($new==='publish'&&$old!=='publish')dttl_aim_reward_post($post->ID);},20,3);
/* Retry bounded missed awards on the member's next wallet visit; no cron. */
function dttl_aim_reconcile($uid){
 if(get_user_meta($uid,'_dttl_aim_email_verified',true))dttl_aim_grant($uid,'welcome',5,'Thưởng tài khoản đã xác nhận email');
 $ids=get_posts(array('post_type'=>'post','post_status'=>'publish','author'=>$uid,'posts_per_page'=>10,'fields'=>'ids','meta_query'=>array(array('key'=>'_dttl_member_submission','compare'=>'EXISTS'),array('key'=>'_dttl_aim_reward_recorded','compare'=>'NOT EXISTS'))));foreach($ids as $id)dttl_aim_reward_post($id);
}
add_action('wp_ajax_dttl_aim_verify_email',function(){
 nocache_headers();if($_SERVER['REQUEST_METHOD']!=='POST'||!check_ajax_referer('dttl_aim_email','nonce',false))wp_send_json_error(array('message'=>'Phiên hết hạn. Hãy tải lại trang.'),403);
 $uid=get_current_user_id();if(!$uid)wp_send_json_error(array('message'=>'Vui lòng đăng nhập.'),401);
 if(get_user_meta($uid,'_dttl_aim_email_verified',true)){dttl_aim_verified($uid);wp_send_json_success(array('message'=>'Email đã xác nhận. Tải lại ví để xem số dư.'));}
 $key='dttl_aim_mail_'.$uid;if(get_transient($key))wp_send_json_error(array('message'=>'Thư vừa được gửi. Vui lòng chờ 5 phút trước khi gửi lại.'),429);
 set_transient($key,1,5*MINUTE_IN_SECONDS);$token=wp_generate_password(48,false,false);$hash=hash('sha256',$token);set_transient('dttl_aim_verify_'.$hash,array('uid'=>$uid,'email'=>get_userdata($uid)->user_email),DAY_IN_SECONDS);
 $url=add_query_arg(array('action'=>'dttl_aim_verify','token'=>$token),admin_url('admin-post.php'));
 $sent=wp_mail(get_userdata($uid)->user_email,'Xác nhận email nhận thưởng AIM — Đầu Tư Tương Lai',"Xác nhận email để nhận thưởng tài khoản 0,5 AIM thử nghiệm:

".$url."

Liên kết có hiệu lực 24 giờ. AIM hiện là điểm thử nghiệm nội bộ, chưa phải token blockchain và không quy đổi tiền mặt. Nếu không yêu cầu, bạn có thể bỏ qua thư này.");
 if(!$sent){delete_transient($key);delete_transient('dttl_aim_verify_'.$hash);wp_send_json_error(array('message'=>'Chưa gửi được thư. Vui lòng thử lại sau.'),500);}
 wp_send_json_success(array('message'=>'Đã gửi thư xác nhận. Kiểm tra hộp thư đến và Spam.'));
});
function dttl_aim_verify_email_link(){
 nocache_headers();$token=isset($_GET['token'])&&is_string($_GET['token'])?sanitize_text_field(wp_unslash($_GET['token'])):'';
 if(!preg_match('/^[A-Za-z0-9]{48}$/',$token))wp_die('Liên kết xác nhận không hợp lệ.');
 $key='dttl_aim_verify_'.hash('sha256',$token);$record=get_transient($key);$user=is_array($record)?get_userdata($record['uid']):false;
 if(!$user||$user->user_email!==$record['email'])wp_die('Liên kết hết hạn hoặc email đã thay đổi. Vui lòng mở ví để gửi lại.');
 if(!dttl_aim_verified($user->ID))wp_die('Chưa cộng được thưởng. Vui lòng thử lại liên kết sau ít phút.');
 delete_transient($key);wp_safe_redirect(home_url('/tai-khoan/?aim_verified=1#dm-wallet'));exit;
}
add_action('admin_post_dttl_aim_verify','dttl_aim_verify_email_link');
add_action('admin_post_nopriv_dttl_aim_verify','dttl_aim_verify_email_link');
function dttl_aim_wallet(){
 if(!is_user_logged_in())return '';$uid=get_current_user_id();dttl_aim_reconcile($uid);$r=dttl_aim_record($uid);$verified=get_user_meta($uid,'_dttl_aim_email_verified',true);ob_start(); ?>
<section class="dm-card aim-wallet" id="dm-wallet"><div class="aim-heading"><div><span class="eyebrow dark">AI FOR MANKIND</span><h3>Ví AIM của tôi</h3></div><span class="aim-pilot">Thử nghiệm nội bộ</span></div>
<p class="aim-balance-label">Số dư khả dụng</p><p class="aim-balance"><?php echo esc_html(dttl_aim_amount($r['balance_units'])); ?></p>
<div class="aim-meter-head"><span>Đã nhận miễn phí</span><strong><?php echo esc_html(dttl_aim_amount($r['free_units'])); ?> / 1 AIM</strong></div><progress max="10" value="<?php echo (int)$r['free_units']; ?>" aria-label="Hạn mức AIM miễn phí"></progress>
<p class="aim-caption"><?php echo $r['free_units']>=10?'Bạn đã nhận đủ 1 AIM miễn phí.':'Còn có thể nhận '.esc_html(dttl_aim_amount(10-$r['free_units'])).' miễn phí.'; ?></p>
<div class="aim-rewards"><div><strong>+0,5 AIM</strong><span>Tài khoản xác nhận email · một lần</span></div><div><strong>+0,1 AIM</strong><span>Mỗi bài chia sẻ được admin duyệt</span></div></div>
<?php if(!$verified): ?><p>Xác nhận email để nhận thưởng tài khoản. Nếu đã tạo tài khoản trước khi ví AIM ra mắt, bạn có thể xác nhận tại đây.</p><button type="button" id="aim-confirm-email">Gửi email xác nhận</button><p id="aim-email-status" role="status"></p><?php else: ?><p class="aim-verified">✓ Email đã xác nhận</p><?php endif; ?>
<p><a class="dm-button" href="#dm-write">Viết bài chia sẻ</a></p><h4>Lịch sử thưởng</h4>
<?php if(!$r['entries']): ?><p class="aim-empty">Chưa có giao dịch thưởng. Phần thưởng sẽ hiện tại đây khi đủ điều kiện.</p><?php else: ?><div class="aim-history"><?php foreach(array_reverse($r['entries']) as $entry): ?><div class="aim-entry"><div><strong><?php echo esc_html($entry['label']); ?></strong><small><?php echo esc_html(wp_date('d/m/Y H:i',$entry['at'])); ?><?php if($entry['limited'])echo ' · Đã áp dụng giới hạn miễn phí'; ?></small></div><b><?php echo $entry['units']?'+'.esc_html(dttl_aim_amount($entry['units'])):'Không cộng thêm'; ?></b></div><?php endforeach; ?></div><?php endif; ?>
<p class="aim-disclaimer">AIM hiện là điểm thử nghiệm nội bộ. Chưa phát hành trên blockchain; chưa có nạp, rút, chuyển ví hoặc quy đổi tiền mặt. Tổng thưởng miễn phí cộng dồn tối đa 1 AIM/tài khoản.</p></section>
<?php return ob_get_clean();}
add_action('admin_menu',function(){add_menu_page('Quản lý AIM','Ví AIM','manage_options','dttl-aim-admin','dttl_aim_admin','dashicons-money-alt',59);});
function dttl_aim_admin(){if(!current_user_can('manage_options'))return;echo '<div class="wrap"><h1>Quản lý AIM — bản thử nghiệm nội bộ</h1><h2>Hiến pháp AIM · chỉ dành cho admin</h2><ul><li>Tên: AIM — AI for Mankind.</li><li>Định hướng token tương lai: trần nguồn cung theo dân số thế giới ước tính; hạn mức phát hành theo số người tiếp cận AI ước tính. Chưa kết nối số liệu và chưa phát hành blockchain.</li><li>Thưởng tài khoản xác nhận email: 0,5 AIM, một lần.</li><li>Thưởng bài chia sẻ được duyệt đăng: 0,1 AIM/bài, không lặp khi sửa hay đăng lại.</li><li>Tổng nhận miễn phí trọn đời: tối đa 1 AIM/tài khoản. Số dư và tổng đã nhận được theo dõi riêng.</li><li>Hiện chưa triển khai giao dịch, nạp/rút, chuyển hoặc đổi tiền.</li><li>Một tài khoản không đồng nghĩa một người duy nhất. Giới hạn hiện áp dụng theo tài khoản.</li></ul><h2>Ví thành viên</h2>';
 $page=max(1,absint($_GET['aim_page']??1));$q=new WP_User_Query(array('number'=>30,'offset'=>($page-1)*30,'meta_key'=>'_dttl_aim_wallet_v1','fields'=>'all','count_total'=>true));echo '<table class="widefat striped"><thead><tr><th>Thành viên</th><th>Số dư</th><th>Đã nhận miễn phí</th><th>Lịch sử</th></tr></thead><tbody>';
 foreach($q->get_results() as $u){$r=dttl_aim_record($u->ID);echo '<tr><td>'.esc_html($u->display_name).' (#'.(int)$u->ID.')</td><td>'.esc_html(dttl_aim_amount($r['balance_units'])).'</td><td>'.esc_html(dttl_aim_amount($r['free_units'])).' / 1 AIM</td><td><details><summary>'.count($r['entries']).' bản ghi</summary>';foreach(array_reverse($r['entries']) as $e)echo '<p>'.esc_html(wp_date('d/m/Y H:i',$e['at']).' · '.$e['label'].' · +'.dttl_aim_amount($e['units'])).'</p>';echo '</details></td></tr>';}
 if(!$q->get_total())echo '<tr><td colspan="4">Chưa có thưởng được cấp. Không tự cộng cho tài khoản cũ khi chưa có xác nhận email.</td></tr>';echo '</tbody></table>';echo paginate_links(array('base'=>add_query_arg('aim_page','%#%'),'format'=>'','current'=>$page,'total'=>max(1,ceil($q->get_total()/30))));echo '</div>';
}
add_action('wp_footer',function(){if(!is_page('tai-khoan'))return; ?>
<style>.aim-wallet{background:linear-gradient(135deg,#f1f7eb,#fffdf4);border-color:#ccdcbc}.aim-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.aim-heading h3{margin:6px 0}.aim-pilot{font-size:11px;background:#e5eddc;color:#49653a;padding:6px 10px;border-radius:20px}.aim-balance-label{margin:23px 0 0;color:#63705b;font-size:13px}.aim-wallet .aim-balance{font-size:44px;font-weight:800;line-height:1.2;color:#285e3e;margin:8px 0 24px}.aim-meter-head{display:flex;justify-content:space-between;gap:12px;font-size:13px}.aim-wallet progress{width:100%;height:12px;accent-color:#285e3e;margin:10px 0 0}.aim-caption{font-size:13px;color:#62715e}.aim-rewards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0}.aim-rewards>div{padding:16px;background:#ffffffa8;border:1px solid #dce6d2;border-radius:10px}.aim-rewards strong,.aim-rewards span{display:block}.aim-rewards strong{font-size:22px;color:#285e3e}.aim-rewards span{font-size:12px;line-height:1.6;margin-top:6px}.aim-entry{display:flex;justify-content:space-between;gap:18px;padding:14px 0;border-bottom:1px solid #dce4d6}.aim-entry strong{font-size:14px}.aim-entry small{display:block;margin-top:4px}.aim-entry b{white-space:nowrap;color:#285e3e;font-size:14px}.aim-disclaimer{font-size:12px!important;line-height:1.7;color:#68735e;margin-top:20px}.aim-verified{color:#285e3e;font-size:13px}.aim-empty{font-size:14px;color:#68735e}@media(max-width:480px){.aim-rewards{grid-template-columns:1fr}.aim-wallet .aim-balance{font-size:36px}.aim-entry{flex-wrap:wrap}}
</style>
<script>(function(){var b=document.getElementById('aim-confirm-email');if(!b)return;b.addEventListener('click',async function(){b.disabled=true;var s=document.getElementById('aim-email-status');s.textContent='Đang gửi thư…';try{var d=new FormData();d.set('action','dttl_aim_verify_email');d.set('nonce',<?php echo wp_json_encode(wp_create_nonce('dttl_aim_email')); ?>);var r=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',credentials:'same-origin',body:d,cache:'no-store'});var j=await r.json();if(!r.ok||!j.success)throw Error(j.data&&j.data.message||'Chưa gửi được thư.');s.textContent=j.data.message;}catch(e){s.textContent=e.message;}finally{b.disabled=false;}});})();</script>
<?php },32);

add_action('profile_update',function($uid,$old){$u=get_userdata($uid);if($u&&$old->user_email!==$u->user_email)delete_user_meta($uid,'_dttl_aim_email_verified');},20,2);

/* DTTL password setup: confirm twice, minimum 8 characters, account redirect. */
add_action('validate_password_reset', function($errors, $user) {
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
 $p = isset($_POST['pass1']) && is_string($_POST['pass1']) ? wp_unslash($_POST['pass1']) : '';
 $c = isset($_POST['dttl_password_confirm']) && is_string($_POST['dttl_password_confirm']) ? wp_unslash($_POST['dttl_password_confirm']) : '';
 if (mb_strlen($p, 'UTF-8') < 8) $errors->add('dttl_password_length', 'Mật khẩu cần có ít nhất 8 ký tự.');
 if ($p !== $c) $errors->add('dttl_password_match', 'Hai mật khẩu chưa khớp. Vui lòng nhập lại.');
}, 20, 2);
add_action('resetpass_form', function($user) { ?>
<p><label for="dttl-password-confirm">Xác nhận mật khẩu</label><input type="password" name="dttl_password_confirm" id="dttl-password-confirm" class="input" autocomplete="new-password" minlength="8" required></p>
<p class="description">Mật khẩu từ 8 ký tự trở lên. Nhập hai lần giống nhau để xác nhận.</p>
<?php });
add_action('login_enqueue_scripts', function() {
 $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
 if (in_array($action, array('rp','resetpass'), true)) wp_dequeue_script('user-profile');
}, 100);
add_action('login_footer', function() { ?>
<script>(function(){
var c=document.getElementById('dttl-password-confirm'),p=document.getElementById('pass1');if(!c||!p)return;
var f=c.form,b=f.querySelector('[type="submit"]');p.type='password';p.value='';p.removeAttribute('data-pw');p.required=true;p.minLength=8;p.autocomplete='new-password';
var firstLabel=f.querySelector('label[for="pass1"]');if(firstLabel)firstLabel.textContent='Mật khẩu mới';
var oldConfirm=f.querySelector('[name="pass2"]');if(oldConfirm){oldConfirm.type='hidden';oldConfirm.required=false;var oldWrap=oldConfirm.closest('.user-pass2-wrap');if(oldWrap)oldWrap.style.display='none';f.querySelectorAll('label[for="pass2"]').forEach(function(e){e.style.display='none';});}
f.querySelectorAll('.wp-pwd').forEach(function(e){e.style.display='block';});
f.querySelectorAll('.wp-hide-pw,.wp-generate-pw,#pass-strength-result,.pw-weak,.indicator-hint').forEach(function(e){e.style.display='none';});

[p,c].forEach(function(input){
var wrap=document.createElement('span');wrap.style.cssText='display:block;position:relative;clear:both';input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);input.style.paddingRight='48px';input.style.marginBottom='0';wrap.style.marginBottom='16px';
var eye=document.createElement('button');eye.type='button';eye.setAttribute('aria-label','Hiện mật khẩu');eye.setAttribute('aria-controls',input.id);eye.setAttribute('aria-pressed','false');eye.style.cssText='position:absolute;right:3px;top:3px;width:42px;height:42px;display:flex;align-items:center;justify-content:center;background:transparent;border:0;border-radius:6px;color:#285e3e;cursor:pointer;padding:8px';
eye.innerHTML='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="dttl-eye-slash" d="M3 3l18 18" style="display:none"/></svg>';
eye.addEventListener('click',function(){var show=input.type==='password';input.type=show?'text':'password';eye.setAttribute('aria-label',show?'Ẩn mật khẩu':'Hiện mật khẩu');eye.setAttribute('aria-pressed',show?'true':'false');eye.title=show?'Ẩn mật khẩu':'Hiện mật khẩu';eye.querySelector('.dttl-eye-slash').style.display=show?'':'none';});wrap.appendChild(eye);
});
function check(){var legacy=f.querySelector('[name="pass2"]');if(legacy)legacy.value=c.value;var enough=Array.from(p.value).length>=8,match=p.value===c.value;p.setCustomValidity(enough?'':'Mật khẩu cần có ít nhất 8 ký tự.');c.setCustomValidity(match?'':'Hai mật khẩu chưa khớp.');if(b)b.disabled=!(enough&&match);}
p.addEventListener('input',check);c.addEventListener('input',check);f.addEventListener('submit',function(e){check();if(!f.checkValidity()){e.preventDefault();f.reportValidity();}});check();
})();</script>
<?php });
add_action('after_password_reset', function($user) {
 if (!isset($_POST['dttl_password_confirm']) || !($user instanceof WP_User)) return;
 wp_set_current_user($user->ID);
 wp_set_auth_cookie($user->ID, false, is_ssl());
 wp_safe_redirect(home_url('/tai-khoan/'));
 exit;
}, 999, 1);
