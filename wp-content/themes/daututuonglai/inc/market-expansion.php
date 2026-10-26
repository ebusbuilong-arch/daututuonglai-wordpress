<?php
if (!defined('ABSPATH')) exit;
function dttl_market_expansion_items() {
    static $items=null;
    if ($items===null) $items=json_decode(file_get_contents(get_template_directory().'/market-expansion-197.json'),true);
    return array_merge(is_array($items)?$items:array(),function_exists('dttl_commodity_deep_items')?dttl_commodity_deep_items():array());
}
add_filter('the_content',function($html){
    if (strpos($html, 'dttl-topic-navigation') !== false) return $html;
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $html;
    $slug=get_post_field('post_name',get_the_ID());$current=null;
    foreach(dttl_market_expansion_items() as $item) if($item['slug']===$slug) $current=$item;
    if(!$current) return $html;
    $items=array_values(array_filter(dttl_market_expansion_items(),function($item)use($current){return $item['kind']===$current['kind'];}));
    foreach($items as $i=>$item) if($item['slug']===$slug){
        $html.='<nav class="lesson-navigation" aria-label="Bài mở rộng"><a href="'.esc_url(home_url('/'.$current['kind'].'/')).'">← Mục lục chuyên mục</a>';
        foreach(array($i-1=>'Bài trước',$i+1=>'Bài tiếp theo') as $j=>$label){
            if(!isset($items[$j]))continue;$p=get_page_by_path($items[$j]['slug'],OBJECT,'post');
            if($p && $p->post_status==='publish')$html.='<a href="'.esc_url(get_permalink($p)).'">'.esc_html($label.': '.$p->post_title).'</a>';
        }
        return $html.'</nav>';
    }
    return $html;
},26);
add_action('admin_init',function(){
    if(!current_user_can('manage_options') || wp_doing_ajax() || get_option('dttl_expansion_197_done'))return;
    $items=array_slice(dttl_market_expansion_items(),0,15);if(count($items)!==15)return;
    $items[]=array('type'=>'page','slug'=>'danh-muc-mo-phong','title'=>'Danh mục mô phỏng: tập đầu tư bằng tiền ảo','excerpt'=>'Tập mua bán cổ phiếu với vốn giả lập, giá nhập tay và nhật ký trên trình duyệt.','content'=>'[dttl_portfolio]');
    $added=0;
    foreach($items as $item){
        if(get_page_by_path($item['slug'],OBJECT,$item['type']))continue;
        if($added>=3)return;
        $category=0;
        if(!empty($item['category'])){$term=get_term_by('name',$item['category'],'category');if(!$term)return;$category=$term->term_id;}
        $args=array('post_type'=>$item['type'],'post_name'=>$item['slug'],'post_title'=>$item['title'],'post_excerpt'=>$item['excerpt'],'post_content'=>$item['content'],'post_status'=>'publish','comment_status'=>'closed');
        if($category)$args['post_category']=array($category);
        $id=wp_insert_post($args,true);if(is_wp_error($id)||!$id)return;
        if(!empty($item['cover_from'])){$cover=get_page_by_path($item['cover_from'],OBJECT,'post');if($cover&&has_post_thumbnail($cover->ID))set_post_thumbnail($id,get_post_thumbnail_id($cover->ID));}
        update_post_meta($id,'_dttl_expansion_197','1');$added++;
    }
    update_option('dttl_expansion_197_done',1);
    if(function_exists('rocket_clean_domain'))rocket_clean_domain();
});
