<?php get_header(); $guides=dttl_app_guides(); $apps=array('ssi'=>'SSI iBoard Pro','vndirect'=>'VNDIRECT DGO / DSTOCK','vps'=>'VPS SmartOne'); ?>
<main id="content" class="container learning-page section">
<span class="eyebrow dark">THAO TÁC TRÊN ĐIỆN THOẠI · DÀNH CHO NGƯỜI MỚI</span>
<h1>Hướng dẫn app chứng khoán</h1>
<p class="learning-intro">Chọn app bạn đang dùng rồi đọc 3 bài theo thứ tự: làm quen và bảo mật → kiểm tra tiền → phiếu lệnh và sổ lệnh. Nếu chỉ đang học, dừng trước bước xác nhận giao dịch.</p>
<div class="learning-status"><strong><?php echo count($guides); ?>/9 bài đã xuất bản</strong><span>Đối chiếu tài liệu chính thức ngày 13/10/2026 · Giao diện có thể thay đổi theo phiên bản.</span></div>
<nav class="app-shortcuts" aria-label="Chọn ứng dụng"><?php foreach($apps as $slug=>$app): ?><a class="btn outline" href="#<?php echo esc_attr($slug); ?>"><?php echo esc_html($app); ?></a><?php endforeach; ?></nav>
<div class="app-search"><label for="app-guide-search">Tìm thao tác hoặc tên app</label><input id="app-guide-search" type="search" placeholder="Ví dụ: SSI, nạp tiền, hủy lệnh…" aria-controls="app-guide-results"><p id="app-guide-empty" hidden role="status">Chưa tìm thấy bài phù hợp. Thử tên app hoặc xóa từ khóa để xem toàn bộ.</p></div>
<div id="app-guide-results">
<?php foreach($apps as $slug=>$app): $group=array_filter($guides,function($post)use($app){return get_post_meta($post->ID,'_dttl_app_name',true)===$app;}); ?>
<section id="<?php echo esc_attr($slug); ?>" class="learning-stage app-guide-group"><div class="stage-heading"><div><span class="eyebrow dark">HỌC THEO APP</span><h2><?php echo esc_html($app); ?></h2></div><span class="stage-ready"><?php echo count($group); ?> bài</span></div><div class="lesson-list">
<?php foreach($group as $post): ?><a class="lesson-row app-guide-row" href="<?php echo esc_url(get_permalink($post->ID)); ?>"><span class="lesson-number"><?php echo esc_html(str_pad(get_post_meta($post->ID,'_dttl_app_part',true),2,'0',STR_PAD_LEFT)); ?></span><div><h3><?php echo esc_html($post->post_title); ?></h3><p><?php echo esc_html($post->post_excerpt); ?></p></div><span class="lesson-read">Đọc bài →</span></a><?php endforeach; ?></div></section>
<?php endforeach; ?></div>
<section class="learning-practice"><div><h2>Chưa rõ bảng giá, LO hay tiền chờ về?</h2><p>Ôn chặng thao tác và chi phí trong lộ trình. Các bài app dùng tài liệu công khai, chưa kiểm thử bằng tài khoản giao dịch thực.</p></div><a class="btn primary" href="<?php echo esc_url(home_url('/lo-trinh-hoc/#chang-02')); ?>">Ôn kiến thức thao tác →</a></section>
</main><?php get_footer(); ?>
