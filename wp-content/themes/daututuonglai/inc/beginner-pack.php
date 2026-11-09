<?php
if(!defined('ABSPATH'))exit;
function dttl_beginner_pack(){static $items=null;if($items===null)$items=json_decode(file_get_contents(get_template_directory().'/beginner-pack-01.json'),true);return is_array($items)?$items:array();}
function dttl_beginner_section(){
 echo '<section id="bat-dau-tu-so-0" class="dttl-panel"><span class="eyebrow dark">TÔI CHƯA BIẾT GÌ</span><h2>Trước khi đầu tư: 5 bài chuẩn bị</h2><p>Đi từ tiền của mình đến mục tiêu và cách học. Mỗi bài có ví dụ, bài tập và câu hỏi tự kiểm tra; chưa cần giao dịch tiền thật.</p><div class="lesson-list">';
 foreach(dttl_beginner_pack() as $i=>$item){$p=get_page_by_path($item['slug'],OBJECT,'post');if(!$p||$p->post_status!=='publish')continue;echo '<a class="lesson-row" href="'.esc_url(get_permalink($p)).'"><span class="lesson-number">'.esc_html($i+1).'</span><div><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['excerpt']).'</p></div><span class="lesson-read">Đọc bài →</span></a>';}
 echo '</div></section>';
}
add_filter('the_content',function($html){if(!is_singular('post')||!in_the_loop()||!is_main_query())return $html;$items=dttl_beginner_pack();$slug=get_post_field('post_name',get_the_ID());foreach($items as $i=>$item){if($item['slug']!==$slug)continue;$html.='<nav class="lesson-navigation" aria-label="Gói nhập môn"><a href="'.esc_url(home_url('/kien-thuc-chung-khoan/#bat-dau-tu-so-0')).'">← Mục lục 5 bài chuẩn bị</a>';foreach(array($i-1=>'Bài trước',$i+1=>'Bài tiếp theo') as $j=>$label){if(!isset($items[$j]))continue;$p=get_page_by_path($items[$j]['slug'],OBJECT,'post');if($p&&$p->post_status==='publish')$html.='<a href="'.esc_url(get_permalink($p)).'">'.esc_html($label.': '.$p->post_title).'</a>';}$html.='</nav>';break;}return $html;},24);
// Only add missing posts, two per owner request. No cron and no edits to existing articles.
add_action('admin_init',function(){if(!current_user_can('manage_options')||get_option('dttl_beginner_192_done'))return;$added=0;$complete=true;$cat=get_category_by_slug('kien-thuc-chung-khoan');
 foreach(dttl_beginner_pack() as $item){if(get_page_by_path($item['slug'],OBJECT,'post'))continue;if($added>=2){$complete=false;break;}$id=wp_insert_post(array('post_type'=>'post','post_name'=>$item['slug'],'post_title'=>$item['title'],'post_excerpt'=>$item['excerpt'],'post_content'=>$item['content'],'post_status'=>'publish','post_category'=>$cat?array($cat->term_id):array(),'comment_status'=>'closed'),true);if(is_wp_error($id)||!$id){$complete=false;break;}$added++;update_post_meta($id,'_dttl_beginner_pack','01');}
 if($complete)update_option('dttl_beginner_192_done',1);
});
