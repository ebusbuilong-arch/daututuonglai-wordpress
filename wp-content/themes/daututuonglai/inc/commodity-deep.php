<?php
if(!defined('ABSPATH'))exit;
add_filter('the_content',function($html){
 if(is_page('hang-hoa') && in_the_loop() && is_main_query())$html=str_replace('Học lần lượt ba bài dưới đây.','Bắt đầu với phần nền tảng, sau đó chọn các bài phân tích chuyên sâu theo từng thị trường.',$html);
 return $html;
},9);
function dttl_commodity_deep_items(){
 static $items=null;
 if($items===null)$items=json_decode(file_get_contents(get_template_directory().'/commodity-deep-200.json'),true);
 return is_array($items)?$items:array();
}
add_action('admin_init',function(){
 if(!current_user_can('manage_options')||wp_doing_ajax()||get_option('dttl_commodity_200_done'))return;
 $items=dttl_commodity_deep_items();if(count($items)!==6)return;
 $term=get_term_by('name','Kiến thức Hàng hóa','category');if(!$term)return;
 $added=0;
 foreach($items as $item){
  if(get_page_by_path($item['slug'],OBJECT,'post'))continue;
  if($added>=3)return;
  $id=wp_insert_post(array('post_type'=>'post','post_name'=>$item['slug'],'post_title'=>$item['title'],'post_excerpt'=>$item['excerpt'],'post_content'=>$item['content'],'post_status'=>'publish','comment_status'=>'closed','post_category'=>array($term->term_id)),true);
  if(is_wp_error($id)||!$id)return;
  update_post_meta($id,'_dttl_commodity_200','1');
  $added++;
 }
 update_option('dttl_commodity_200_done',1);
 if(function_exists('rocket_clean_domain'))rocket_clean_domain();
});
