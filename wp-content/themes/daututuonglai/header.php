<?php if (!defined('ABSPATH')) exit; ?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip" href="#content">Bỏ qua menu</a>
<header class="site-header"><div class="container header-inner">
<div class="brand"><?php if (has_custom_logo()) { the_custom_logo(); } else { ?><a href="<?php echo esc_url(home_url('/')); ?>" aria-label="Đầu Tư Tương Lai"><span class="brand-icon">↗</span><span>ĐẦU TƯ <strong>TƯƠNG LAI</strong><small>Hiểu hôm nay · Đầu tư cho ngày mai</small></span></a><?php } ?></div>
<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="Mở menu">☰</button>
<nav id="primary-menu" class="main-nav" aria-label="Menu chính"><?php if (has_nav_menu('primary')) { wp_nav_menu(array('theme_location'=>'primary','container'=>false)); } else { ?><ul><li><a href="<?php echo esc_url(home_url('/')); ?>">Trang chủ</a></li><?php foreach(dttl_categories() as $slug=>$label): ?><li><a href="<?php echo esc_url(dttl_category_url($slug)); ?>"><?php echo esc_html($label); ?></a></li><?php endforeach; ?></ul><?php } ?></nav>
</div></header>
