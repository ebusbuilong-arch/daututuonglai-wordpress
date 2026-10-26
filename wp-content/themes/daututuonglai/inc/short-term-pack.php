<?php
if(!defined('ABSPATH'))exit;
function dttl_short_term_pack(){static $items=null;if($items===null)$items=json_decode(file_get_contents(get_template_directory().'/short-term-pack-01.json'),true);return is_array($items)?$items:array();}
function dttl_short_term_section(){
 echo '<section id="thuc-hanh-ngan-han" class="dttl-panel"><span class="eyebrow dark">ĐẦU TƯ NGẮN HẠN</span><h2>5 bài thực hành trước khi giao dịch</h2><p>Lập kế hoạch, tính đủ chi phí và đánh giá kết quả trên giấy. Đây là nội dung giáo dục, không phải tín hiệu mua bán.</p><div class="lesson-list">';
 foreach(dttl_short_term_pack() as $i=>$item){$p=get_page_by_path($item['slug'],OBJECT,'post');if(!$p||$p->post_status!=='publish')continue;echo '<a class="lesson-row" href="'.esc_url(get_permalink($p)).'"><span class="lesson-number">'.esc_html($i+1).'</span><div><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['excerpt']).'</p></div><span class="lesson-read">Đọc bài →</span></a>';}
 echo '</div></section>';
}
add_filter('the_content',function($html){if(!is_singular('post')||!in_the_loop()||!is_main_query())return $html;$items=dttl_short_term_pack();$slug=get_post_field('post_name',get_the_ID());foreach($items as $i=>$item){if($item['slug']!==$slug)continue;$html.='<nav class="lesson-navigation" aria-label="Gói ngắn hạn"><a href="'.esc_url(home_url('/category/dau-tu-ngan-han/#thuc-hanh-ngan-han')).'">← Mục lục 5 bài ngắn hạn</a>';foreach(array($i-1=>'Bài trước',$i+1=>'Bài tiếp theo') as $j=>$label){if(!isset($items[$j]))continue;$p=get_page_by_path($items[$j]['slug'],OBJECT,'post');if($p&&$p->post_status==='publish')$html.='<a href="'.esc_url(get_permalink($p)).'">'.esc_html($label.': '.$p->post_title).'</a>';}$html.='</nav>';break;}return $html;},24);
// Only add missing posts, two per owner request. No cron and no edits to existing articles.
add_action('admin_init',function(){if(!current_user_can('manage_options')||get_option('dttl_short_term_193_done'))return;$added=0;$complete=true;$cat=get_category_by_slug('dau-tu-ngan-han');
 foreach(dttl_short_term_pack() as $item){if(get_page_by_path($item['slug'],OBJECT,'post'))continue;if($added>=2){$complete=false;break;}$id=wp_insert_post(array('post_type'=>'post','post_name'=>$item['slug'],'post_title'=>$item['title'],'post_excerpt'=>$item['excerpt'],'post_content'=>$item['content'],'post_status'=>'publish','post_category'=>$cat?array($cat->term_id):array(),'comment_status'=>'closed'),true);if(is_wp_error($id)||!$id){$complete=false;break;}$added++;update_post_meta($id,'_dttl_short_term_pack','01');}
 if($complete)update_option('dttl_short_term_193_done',1);
});
