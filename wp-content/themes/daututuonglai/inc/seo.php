<?php
if (!defined('ABSPATH')) exit;
function dttl_has_seo_plugin() { return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION'); }
function dttl_seo_text($text, $limit=165) {
    $text=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags(strip_shortcodes($text)),ENT_QUOTES,'UTF-8')));
    if(mb_strlen($text,'UTF-8')<=$limit) return $text;
    $short=mb_substr($text,0,$limit-1,'UTF-8');
    if(preg_match('/^(.{70,}[.!?])\s/us',$short,$match)) return trim($match[1]);
    return preg_replace('/\s+\S*$/u','',$short).'…';
}
function dttl_seo_description() {
    if(is_singular()) {
        $post=get_queried_object();
        $custom=get_post_meta($post->ID,'_dttl_seo_description',true);
        if($custom) return dttl_seo_text($custom);
        $pages=array(
            'lo-trinh-hoc'=>'Lộ trình học chứng khoán 7 ngày cho người mới: 30 bài nền tảng, thực hành mô phỏng, bài kiểm tra và lưu tiến độ học trên trình duyệt.',
            'kien-thuc-chung-khoan'=>'Học chứng khoán từ con số 0: hiểu cổ phiếu, thị trường, cách đọc doanh nghiệp và quản trị rủi ro qua lộ trình, ví dụ và bài tập.',
            'crypto'=>'Kiến thức Crypto cho người mới: Bitcoin, blockchain, ví, bảo mật và rủi ro tài sản số. Học qua ví dụ, tránh nhầm giá tăng với giá trị.',
            'forex'=>'Học Forex từ nền tảng: tỷ giá, pip, lot, spread, đòn bẩy và quản trị rủi ro. Ví dụ dễ hiểu để luyện tập trước khi dùng tiền thật.',
            'hang-hoa'=>'Tìm hiểu thị trường hàng hóa: vàng, dầu, đồng, nông sản và cà phê. Học cách đọc cung cầu, tồn kho và rủi ro hợp đồng kỳ hạn.',
            'thi-truong'=>'Theo dõi biểu đồ và nguồn dữ liệu chứng khoán, Crypto, vàng, tiền tệ. Học cách đọc giá, đơn vị, độ trễ và kiểm chứng thông tin thị trường.',
            'cong-cu-dau-tu'=>'Công cụ tính lãi kép và theo dõi tiến độ học đầu tư. Thử vốn ban đầu, khoản góp và thời gian bằng số liệu giả định để hiểu cách tính.',
            'danh-muc-mo-phong'=>'Thực hành danh mục đầu tư bằng tiền ảo: ghi giao dịch, giá vốn, tỷ trọng và lãi lỗ giả định. Dữ liệu lưu trên trình duyệt, không gửi lệnh thật.',
            'huong-dan-app-chung-khoan'=>'Hướng dẫn làm quen SSI iBoard Pro, VNDIRECT và VPS SmartOne: tài khoản, bảng giá, tiền và sổ lệnh. Kiểm tra thao tác trước khi giao dịch.',
            'thuat-ngu-chung-khoan'=>'Tra cứu thuật ngữ chứng khoán cho người mới: giải thích dễ hiểu, ví dụ, điểm dễ nhầm và liên kết bài học theo từng nhóm kiến thức.',
            'gioi-thieu'=>'Tìm hiểu Đầu Tư Tương Lai: website học chứng khoán và kinh tế tương lai, nội dung do L0 biên soạn cho người mới tìm hiểu đầu tư.',
            'chinh-sach-bien-tap'=>'Chính sách biên tập Đầu Tư Tương Lai: nguyên tắc nguồn tham khảo, kiểm chứng dữ kiện, ví dụ giả định, cập nhật và giới hạn nội dung giáo dục.'
        );
        if(is_page() && isset($pages[$post->post_name])) return $pages[$post->post_name];
    }
    if(is_front_page()) return 'Học chứng khoán từ con số 0, theo dõi thị trường và khám phá kinh tế tương lai. Lộ trình dễ hiểu, hướng dẫn app và kiến thức có nguồn.';
    if(is_home()) return 'Thư viện bài viết Đầu Tư Tương Lai: chứng khoán cho người mới, thao tác app, phân tích doanh nghiệp, quản trị rủi ro và kinh tế AI.';
    if(is_category()) {
        $descriptions=array('kien-thuc-chung-khoan'=>'Kiến thức chứng khoán cho người mới: thị trường, báo cáo tài chính, biểu đồ, chi phí và quản trị rủi ro. Có ví dụ và bài tập thực hành.','kinh-te-tuong-lai'=>'Khám phá AI, robot, tự động hóa và tác động đến doanh nghiệp, việc làm, năng suất. Phân biệt nghiên cứu, dữ kiện và dự báo.','goc-chia-se'=>'Góc chia sẻ về hành trình học đầu tư: kinh nghiệm, cách kiểm chứng thông tin và xây dựng thói quen đánh giá quyết định.','dau-tu-ngan-han'=>'Tìm hiểu biểu đồ, xu hướng, khối lượng và quản trị vốn khi giao dịch ngắn hạn. Học cách ghi giả thuyết và nhận diện rủi ro.','dau-tu-dai-han'=>'Kiến thức đầu tư dài hạn: doanh nghiệp, giá trị, lãi kép, ETF, cổ tức và đa dạng hóa. Ví dụ dễ hiểu dành cho người mới.');
        $term=get_queried_object();return $descriptions[$term->slug]??wp_strip_all_tags(term_description());
    }
    if(is_singular()) { $post=get_queried_object();return dttl_seo_text($post->post_excerpt?:$post->post_content); }
    return 'Kiến thức chứng khoán, hướng dẫn thực hành và góc nhìn về kinh tế tương lai dành cho người mới.';
}
function dttl_seo_head() {
    if(dttl_has_seo_plugin() || is_404() || is_search() || is_feed()) return;
    $url=is_singular()?wp_get_canonical_url(get_queried_object_id()):strtok(get_pagenum_link(max(1,(int)get_query_var('paged'))),'?');
    if(!$url) $url=get_permalink();
    $title=html_entity_decode(wp_get_document_title(),ENT_QUOTES,'UTF-8');$description=dttl_seo_description();
    $image=is_singular()&&has_post_thumbnail()?get_the_post_thumbnail_url(get_queried_object_id(),'full'):get_template_directory_uri().'/assets/images/hoc-dau-tu-tuong-lai.webp';
    $type=is_singular('post')?'article':'website';
    echo '<meta name="description" content="'.esc_attr($description).'">'."\n";
    if(!is_singular()) echo '<link rel="canonical" href="'.esc_url($url).'">'."\n";
    foreach(array('og:locale'=>'vi_VN','og:type'=>$type,'og:site_name'=>get_bloginfo('name'),'og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:image'=>$image,'og:image:alt'=>is_singular()?get_the_title(get_queried_object_id()):get_bloginfo('name')) as $key=>$value) echo '<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">'."\n";
    foreach(array('twitter:card'=>'summary_large_image','twitter:title'=>$title,'twitter:description'=>$description,'twitter:image'=>$image) as $key=>$value) echo '<meta name="'.esc_attr($key).'" content="'.esc_attr($value).'">'."\n";
    $home=home_url('/');$graph=array(array('@type'=>'Organization','@id'=>$home.'#organization','name'=>get_bloginfo('name'),'url'=>$home),array('@type'=>'WebSite','@id'=>$home.'#website','url'=>$home,'name'=>get_bloginfo('name'),'publisher'=>array('@id'=>$home.'#organization'),'inLanguage'=>'vi-VN'));
    if(is_singular()) {
        $id=get_queried_object_id();$page_id=$url.'#webpage';
        $graph[]=array('@type'=>'WebPage','@id'=>$page_id,'url'=>$url,'name'=>$title,'description'=>$description,'isPartOf'=>array('@id'=>$home.'#website'),'inLanguage'=>'vi-VN');
        if(is_singular('post')) {
            $graph[]=array('@type'=>'Article','@id'=>$url.'#article','headline'=>html_entity_decode(get_the_title($id),ENT_QUOTES,'UTF-8'),'description'=>$description,'image'=>array($image),'datePublished'=>get_the_date(DATE_W3C,$id),'dateModified'=>get_the_modified_date(DATE_W3C,$id),'author'=>array('@type'=>'Person','name'=>'L0'),'publisher'=>array('@id'=>$home.'#organization'),'mainEntityOfPage'=>array('@id'=>$page_id),'inLanguage'=>'vi-VN');
            $crumbs=array(array('@type'=>'ListItem','position'=>1,'name'=>'Trang chủ','item'=>$home));$cats=get_the_category($id);
            if($cats) $crumbs[]=array('@type'=>'ListItem','position'=>2,'name'=>$cats[0]->name,'item'=>get_category_link($cats[0]->term_id));
            $crumbs[]=array('@type'=>'ListItem','position'=>count($crumbs)+1,'name'=>get_the_title($id),'item'=>$url);
            $graph[]=array('@type'=>'BreadcrumbList','itemListElement'=>$crumbs);
        }
    }
    echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@graph'=>$graph),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>'."\n";
}
remove_action('wp_head','dttl_meta_description',1);
add_action('wp_head','dttl_seo_head',1);
function dttl_release_cache_refresh() {
    if(!current_user_can('manage_options')) return;
    $version=wp_get_theme()->get('Version');
    if(get_option('dttl_cache_release')===$version) return;
    if(function_exists('rocket_clean_domain')) rocket_clean_domain();
    update_option('dttl_cache_release',$version,false);
}
add_action('admin_init','dttl_release_cache_refresh',99);
// Bounded owner-only diagnostics. No credentials, settings changes or arbitrary URLs.
add_action('admin_menu',function(){add_management_page('Kiểm tra website','Kiểm tra website','manage_options','dttl-site-check','dttl_site_check_page');});
function dttl_site_check_page() {
    if(!current_user_can('manage_options')) return;
    if(isset($_POST['dttl_check'])) {
        check_admin_referer('dttl_site_check');$results=array();
        $paths=array('/robots.txt','/wp-sitemap.xml','/wp-sitemap-posts-post-1.xml','/wp-sitemap-posts-page-1.xml');
        foreach($paths as $path) {
            $response=wp_safe_remote_get(home_url($path),array('timeout'=>8,'redirection'=>2,'limit_response_size'=>524288));
            if(is_wp_error($response)) {$results[]=array('path'=>$path,'status'=>'Lỗi kết nối: '.$response->get_error_message());continue;}
            $body=wp_remote_retrieve_body($response);$code=wp_remote_retrieve_response_code($response);
            $count=substr_count($body,'<loc>');
            $results[]=array('path'=>$path,'status'=>'HTTP '.$code.($count?' · '.$count.' URL':''),'robots'=>$path==='/robots.txt'?$body:'');
        }
        update_option('dttl_site_check_result',array('at'=>current_time('mysql'),'results'=>$results),false);
    }
    echo '<div class="wrap"><h1>Kiểm tra website Đầu Tư Tương Lai</h1><p>Kiểm tra nguồn sitemap, robots và trạng thái lịch chạy. Chỉ quản trị viên xem được trang này.</p>';
    echo '<form method="post">';wp_nonce_field('dttl_site_check');submit_button('Kiểm tra sitemap và robots','primary','dttl_check');echo '</form>';
    $result=get_option('dttl_site_check_result');
    if($result) {echo '<p>Lần kiểm tra: '.esc_html($result['at']).'</p><ul>';foreach($result['results'] as $row) {echo '<li><strong>'.esc_html($row['path']).'</strong>: '.esc_html($row['status']).'</li>';if(!empty($row['robots'])) echo '<pre>'.esc_html($row['robots']).'</pre>';}echo '</ul>';}
    echo '<h2>Lịch chạy nền</h2><p>DISABLE_WP_CRON: '.(defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON?'Bật (cần lịch chạy từ máy chủ)':'Tắt (WordPress chạy theo lượt truy cập)').'</p><ul>';
    foreach(array('rocket_purge_time_event','action_scheduler_run_queue') as $hook) {
        $event=wp_get_scheduled_event($hook);echo '<li>'.esc_html($hook).': '.($event?esc_html(wp_date('d/m/Y H:i:s',$event->timestamp)):'Không có lịch không tham số').' · callback: '.(has_action($hook)?'Có':'Không').'</li>';
    }
    dttl_chatgpt_link_audit();
    echo '</ul><p>Cache đã làm mới cho phiên bản: '.esc_html(get_option('dttl_cache_release','Chưa ghi nhận')).'</p></div>';
}

// Read-only audit of published content, menus, widgets and active theme sources.
function dttl_chatgpt_link_audit() {
    $sources=array();
    $posts=get_posts(array('post_type'=>array('post','page','nav_menu_item'),'post_status'=>'publish','posts_per_page'=>-1));
    foreach($posts as $p) {
        $sources['Nội dung #'.$p->ID.' '.$p->post_title]=$p->post_content.' '.$p->post_excerpt;
        if($p->post_type==='nav_menu_item') $sources['Menu #'.$p->ID]=get_post_meta($p->ID,'_menu_item_url',true);
    }
    foreach(get_terms(array('taxonomy'=>array('category','post_tag'),'hide_empty'=>false)) as $term) $sources['Danh mục/thẻ '.$term->name]=$term->description;
    foreach(array('widget_text','widget_custom_html','widget_block') as $key) $sources[$key]=wp_json_encode(get_option($key,array()));
    $root=get_template_directory();
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($files as $file) {
        if($file->isFile() && in_array(strtolower($file->getExtension()),array('php','js','json','html','css'),true) && $file->getSize()<2097152) $sources['Theme '.substr($file->getPathname(),strlen($root)+1)]=file_get_contents($file->getPathname());
    }
    $hits=array();
    foreach($sources as $label=>$text) {
        $text=str_replace('\/','/',html_entity_decode((string)$text,ENT_QUOTES,'UTF-8'));
        preg_match_all('~https?://[^\s<>"\x27]+~iu',$text,$matches);
        foreach(array_unique($matches[0]) as $url) if(stripos(rawurldecode($url),'chatgpt')!==false) $hits[]=array($label,$url);
    }
    echo '<h2>Kiểm tra liên kết chứa ChatGPT</h2><p>Đã quét '.count($posts).' bài/trang/mục menu và '.count($sources).' nguồn (gồm danh mục, widget, mã theme). Không thay đổi nội dung.</p>';
    if(!$hits) echo '<p><strong>Không tìm thấy URL chứa chatgpt trong các nguồn đã quét.</strong></p>';
    else {echo '<table class="widefat"><thead><tr><th>Nguồn</th><th>URL</th></tr></thead><tbody>';foreach($hits as $hit) echo '<tr><td>'.esc_html($hit[0]).'</td><td>'.esc_html($hit[1]).'</td></tr>';echo '</tbody></table>';}
}
