<?php get_header(); ?>
<main id="content" class="container section">
<header class="archive-head"><span class="eyebrow dark">THƯ VIỆN NỘI DUNG</span><h1><?php echo get_search_query() ? 'Kết quả tìm kiếm' : 'Tất cả bài viết'; ?></h1><?php get_search_form(); ?></header>
<div class="article-grid"><?php if (have_posts()): while (have_posts()): the_post(); ?>
<article class="article-card"><a href="<?php the_permalink(); ?>"><?php if (has_post_thumbnail()) the_post_thumbnail('medium_large', array('loading'=>'lazy')); else echo '<span class="article-placeholder">ĐẦU TƯ TƯƠNG LAI</span>'; ?></a><div class="article-content"><small><?php echo esc_html(get_the_date('d/m/Y')); ?></small><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p><?php echo esc_html(get_the_excerpt()); ?></p></div></article>
<?php endwhile; else: ?><p>Chưa tìm thấy nội dung phù hợp. Bạn có thể thử từ khóa khác.</p><?php endif; ?></div><div class="pagination"><?php the_posts_pagination(); ?></div></main><?php get_footer(); ?>
