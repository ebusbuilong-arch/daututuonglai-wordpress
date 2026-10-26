<?php
if(!defined('ABSPATH')) exit;
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_style('dttl-learning',get_template_directory_uri().'/assets/css/learning.css',array('dttl-main'),'1.9.9');
 wp_enqueue_script('dttl-learning',get_template_directory_uri().'/assets/js/learning.js',array(),'1.9.9',true);
});
add_action('pre_get_posts',function($q){if(!is_admin() && $q->is_main_query() && $q->is_search()){$q->set('post_type',array('post','page'));$q->set('post_status','publish');$q->set('posts_per_page',12);}});
function dttl_learning_post_url($slug){$p=get_page_by_path($slug,OBJECT,'post');return $p?get_permalink($p):home_url('/lo-trinh-hoc/');}
function dttl_learning_start(){ ?>
<section class="container dttl-start" aria-label="Bắt đầu học và tìm bài"><div><span class="eyebrow dark">DÀNH CHO NGƯỜI MỚI</span><h2>Học từng chút. Hiểu từng bước.</h2><p>7 ngày · Mỗi ngày một chặng · Không cần giao dịch tiền thật</p></div><?php get_search_form(); ?><div class="dttl-quick"><a href="<?php echo esc_url(home_url('/lo-trinh-hoc/#7-ngay')); ?>">① Lộ trình học 7 ngày</a><a href="<?php echo esc_url(dttl_learning_post_url('bai-kiem-tra-kien-thuc-chung-khoan')); ?>">② Bài kiểm tra</a><a href="<?php echo esc_url(home_url('/cong-cu-dau-tu/')); ?>">③ Lãi kép & tiến độ học</a></div><div class="dttl-markets"><span>Khám phá kiến thức:</span><?php foreach(array('crypto'=>'Crypto','hang-hoa'=>'Hàng hóa','forex'=>'Forex') as $slug=>$name): ?><a href="<?php echo esc_url(home_url('/'.$slug.'/')); ?>"><?php echo esc_html($name); ?> →</a><?php endforeach; ?></div></section>
<?php }
add_filter('wp_nav_menu_items',function($items,$args){if(isset($args->theme_location)&&$args->theme_location==='primary'){$items.='<li class="dttl-nav-search">'.get_search_form(array('echo'=>false)).'</li>';}return $items;},20,2);
function dttl_learning_progress(){
 $days=array('Nền tảng','Thao tác & chi phí','Hiểu doanh nghiệp','Tích lũy dài hạn','Phương pháp & rủi ro','Thực hành mô phỏng','Kiểm tra & ôn tập');ob_start(); ?>
<section class="dttl-panel" data-learning-progress><h2>Tiến độ học 7 ngày</h2><p>Tự đánh dấu sau khi hoàn thành. Tiến độ chỉ lưu trên trình duyệt, thiết bị này; không cần tài khoản và chưa đồng bộ giữa các máy. Xóa dữ liệu trình duyệt sẽ mất tiến độ.</p><p data-progress-status role="status">Đã hoàn thành 0/7 ngày</p><progress max="7" value="0" aria-label="Tiến độ học 7 ngày"></progress><div class="dttl-days"><?php foreach($days as $i=>$name): ?><label><input type="checkbox" data-day="<?php echo esc_attr($i+1); ?>"> <span><strong>Ngày <?php echo esc_html($i+1); ?></strong> · <?php echo esc_html($name); ?></span></label><?php endforeach; ?></div><a href="<?php echo esc_url(home_url('/lo-trinh-hoc/#7-ngay')); ?>">Mở nội dung lộ trình →</a><p><a href="<?php echo esc_url(dttl_learning_post_url('bai-kiem-tra-kien-thuc-chung-khoan')); ?>">Làm bài kiểm tra →</a></p><button type="button" data-reset-progress>Đặt lại tiến độ</button><p data-storage-note role="status"></p><noscript>Bật JavaScript để lưu tiến độ học trên thiết bị.</noscript></section>
<?php return ob_get_clean(); }
add_shortcode('dttl_learning_progress','dttl_learning_progress');
add_filter('the_content',function($html){if(is_singular('post') && in_the_loop() && is_main_query()){
 if(get_post_field('post_name',get_the_ID())==='lo-trinh-tu-hoc-chung-khoan-90-ngay')$html.=dttl_learning_progress();
 $html.='<section class="dttl-panel"><label><input type="checkbox" data-read-post="'.esc_attr(get_the_ID()).'"> Tôi đã đọc xong bài này</label><p data-read-status role="status"></p><a href="'.esc_url(home_url('/cong-cu-dau-tu/')).'">Xem tiến độ học →</a></section>';
}return $html;},25);
function dttl_compound_tools(){ob_start(); ?>
<section class="dttl-panel"><span class="eyebrow dark">THỰC HÀNH BẰNG TIỀN ẢO</span><h2>Danh mục mô phỏng</h2><p>Tập mua bán cổ phiếu, chia vốn và đọc lãi/lỗ. Giá nhập thủ công; không gửi lệnh lên sàn.</p><a class="simple-link" href="<?php echo esc_url(home_url('/danh-muc-mo-phong/')); ?>">Mở danh mục mô phỏng →</a></section>
<p>Thử một kế hoạch tích lũy bằng số liệu giả định. Công cụ không dự báo giá hay cam kết lợi nhuận.</p>
<section class="dttl-panel"><h2>Tính lãi kép</h2><form data-compound><div class="dttl-fields"><label>Vốn ban đầu (VNĐ)<input name="initial" type="number" min="0" max="1000000000000" step="1000" value="10000000" required></label><label>Góp cuối mỗi tháng (VNĐ)<input name="monthly" type="number" min="0" max="1000000000" step="1000" value="1000000" required></label><label>Lợi suất giả định (%/năm)<input name="rate" type="number" min="-90" max="100" step="0.1" value="6" required></label><label>Thời gian (năm)<input name="years" type="number" min="1" max="50" step="1" value="10" required></label></div><button type="submit">Tính kế hoạch</button></form><p>Lợi suất hiệu dụng năm được quy đổi sang tháng: (1 + r)<sup>1/12</sup> − 1. Góp tiền cuối tháng; tái đầu tư toàn bộ lợi tức. Chưa tính phí, thuế và lạm phát. Thực tế có thể lỗ.</p><div data-compound-result aria-live="polite"></div><noscript>Bật JavaScript để tính toán. Ví dụ: 10 triệu đồng, không góp thêm, lợi suất 6%/năm trong 1 năm → 10,6 triệu đồng, trước phí và thuế.</noscript></section>
<?php echo dttl_learning_progress(); ?><section class="dttl-panel"><h2>Bài đã học</h2><p data-read-total role="status">Bật JavaScript để xem số bài đã đánh dấu trên thiết bị này.</p><p>Trong mỗi bài viết, tích “Tôi đã đọc xong bài này”. Bỏ tích nếu muốn học lại.</p><a href="<?php echo esc_url(home_url('/lo-trinh-hoc/')); ?>">Mở lộ trình 7 chặng →</a></section>
<?php return ob_get_clean();}
add_shortcode('dttl_compound_tools','dttl_compound_tools');
add_shortcode('dttl_market_hub',function($atts){
 $atts=shortcode_atts(array('kind'=>'crypto'),$atts);$kind=sanitize_key($atts['kind']);
 if(!in_array($kind,array('crypto','hang-hoa','forex'),true))return '';
 $items=json_decode(file_get_contents(get_template_directory().'/learning-content.json'),true);$items=array_merge((array)$items,dttl_market_expansion_items());$html='<div class="dttl-hub-lessons">';$n=0;
 foreach((array)$items as $item){if($item['type']!=='post'||strpos($item['slug'],$kind.'-')!==0)continue;$p=get_page_by_path($item['slug'],OBJECT,'post');if(!$p||$p->post_status!=='publish')continue;$n++;
 if($kind==='hang-hoa' && $n===1)$html.='<header style="grid-column:1/-1"><h2>Phần 1 · Nền tảng & cơ chế giao dịch</h2><p>Đọc cách vận hành, quy mô, ký quỹ và đáo hạn trước khi chuyển sang phân tích từng thị trường.</p><a href="#chuyen-sau">Đến các bài chuyên sâu →</a></header>';
 if($kind==='hang-hoa' && $n===9)$html.='<header id="chuyen-sau" style="grid-column:1/-1;scroll-margin-top:110px"><h2>Phần 2 · Phân tích chuyên sâu</h2><p>Vàng, dầu, đồng, nông sản, cà phê và đường giá kỳ hạn. Mỗi bài có tình huống giả định và bài thực hành.</p></header>';
 $cover=has_post_thumbnail($p)?'<a href="'.esc_url(get_permalink($p)).'">'.get_the_post_thumbnail($p,'medium_large',array('class'=>'dttl-hub-cover')).'</a>':'';
 $html.='<article class="dttl-panel">'.$cover.'<span class="eyebrow dark">BÀI '.$n.'</span><h2><a href="'.esc_url(get_permalink($p)).'">'.esc_html($item['title']).'</a></h2><p>'.esc_html($item['excerpt']).'</p><a href="'.esc_url(get_permalink($p)).'">Bắt đầu học →</a></article>';}
 return $html.'</div><p><a href="'.esc_url(home_url('/thi-truong/')).'">Xem bảng giá và biểu đồ thị trường →</a></p><p>Dữ liệu do bên thứ ba cung cấp, có thể chậm hoặc gián đoạn. Kiểm tra mã, đơn vị, thời điểm và nguồn giá; không dùng riêng biểu đồ để quyết định giao dịch.</p>';
});
// Idempotent, bounded content installation; no cron and no automatic cache preloading.
add_action('admin_init',function(){
 if(!current_user_can('manage_options') || get_option('dttl_learning_190_done'))return;
 $items=json_decode(file_get_contents(get_template_directory().'/learning-content.json'),true);if(!is_array($items))return;
 $items[]=array('type'=>'page','slug'=>'cong-cu-dau-tu','title'=>'Công cụ đầu tư & tiến độ học','excerpt'=>'Tính lãi kép theo kế hoạch góp tiền, lưu tiến độ học 7 ngày và đánh dấu bài đã học trên thiết bị.','content'=>'[dttl_compound_tools]');
 $added=0;$complete=true;
 foreach($items as $item){$old=get_page_by_path($item['slug'],OBJECT,$item['type']);if($old)continue;if($added>=3){$complete=false;break;}
 $id=wp_insert_post(array('post_type'=>$item['type'],'post_name'=>$item['slug'],'post_title'=>$item['title'],'post_excerpt'=>$item['excerpt'],'post_content'=>$item['content'],'post_status'=>'publish','comment_status'=>'closed'),true);
 if(is_wp_error($id)||!$id){$complete=false;break;} $added++;
 if(!empty($item['category'])){$term=term_exists($item['category'],'category');if(!$term)$term=wp_insert_term($item['category'],'category');if(!is_wp_error($term))wp_set_post_categories($id,array((int)$term['term_id']));}
 }
 if($complete)update_option('dttl_learning_190_done',1);
});
