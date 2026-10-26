<?php
if(!defined('ABSPATH')) exit;
function dttl_shorten_roadmap_seven() {
 if(!current_user_can('manage_options')||get_option('dttl_roadmap_seven_181'))return;
 $pack=json_decode(file_get_contents(get_template_directory().'/stock-extras-pack-03.json'),true);
 $ids=get_option('dttl_stock_extras_03_ids',array());
 foreach($pack as $item){
  if(!in_array((int)$item['seo_stt'],array(59,60),true))continue;
  $id=isset($ids[$item['slug']])?(int)$ids[$item['slug']]:0;if(!$id||!get_post($id))return;
  $result=wp_update_post(array('ID'=>$id,'post_title'=>$item['title'],'post_excerpt'=>$item['excerpt'],'post_content'=>dttl_resolve_lesson_links($item['content'],$ids)),true);
  if(is_wp_error($result))return;
  if((int)$item['seo_stt']===60){$image=get_post_thumbnail_id($id);if($image){update_post_meta($image,'_wp_attachment_image_alt',$item['title'].' — ảnh minh họa AI');wp_update_post(array('ID'=>$image,'post_title'=>$item['title'].' — ảnh minh họa AI'));}}
 }
 update_option('dttl_roadmap_seven_181',current_time('mysql'));
 if(function_exists('rocket_clean_domain'))rocket_clean_domain();
}
add_action('admin_init','dttl_shorten_roadmap_seven',30);
