<?php if (!defined('ABSPATH')) exit; ?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip" href="#content">Bỏ qua menu</a>
<header id="page-top" class="site-header" tabindex="-1"><div class="container header-inner">
<div class="brand"><?php if (has_custom_logo()) { the_custom_logo(); } else { ?><a href="<?php echo esc_url(home_url('/')); ?>" aria-label="Đầu Tư Tương Lai"><span class="brand-icon">↗</span><span>ĐẦU TƯ <strong>TƯƠNG LAI</strong><small>Hiểu hôm nay · Đầu tư cho ngày mai</small></span></a><?php } ?>
<?php if (function_exists('trp_custom_language_switcher')) : $dttl_header_languages = trp_custom_language_switcher(); ?>
<nav class="dttl-header-languages" aria-label="Language" data-no-translation>
<?php $dttl_language_separator = false; foreach (array('vi'=>'VI', 'en_US'=>'EN') as $dttl_language_code=>$dttl_language_label) : if (empty($dttl_header_languages[$dttl_language_code]['current_page_url'])) continue; ?>
<?php if ($dttl_language_separator) : ?><span aria-hidden="true">|</span><?php endif; ?>
<a class="dttl-language-<?php echo esc_attr($dttl_language_code); ?>" href="<?php echo esc_url($dttl_header_languages[$dttl_language_code]['current_page_url']); ?>" hreflang="<?php echo $dttl_language_code === 'vi' ? 'vi' : 'en'; ?>" lang="<?php echo $dttl_language_code === 'vi' ? 'vi' : 'en'; ?>" data-no-translation><?php echo esc_html($dttl_language_label); ?></a>
<?php $dttl_language_separator = true; endforeach; ?>
<button type="button" class="dttl-account-open dttl-header-account" aria-haspopup="dialog" aria-controls="dttl-account-dialog">Tài khoản</button>
</nav>
<style>
.site-header .brand .dttl-header-languages{display:flex;align-items:center;justify-content:center;gap:7px;margin:8px 0 0;line-height:1;font-size:12px;color:#9aa6a4}
.site-header .brand .dttl-header-languages a{display:inline-flex;align-items:center;justify-content:center;min-width:28px;min-height:28px;padding:2px 4px;font-size:12px;line-height:1;font-weight:500;color:#657773;text-decoration:none;border-radius:4px}
.site-header .brand .dttl-header-languages a:hover,.site-header .brand .dttl-header-languages a:focus-visible{color:#087f70;background:#eef7f4;outline-offset:2px}
html[lang^="vi"] .site-header .brand .dttl-language-vi,html[lang^="en"] .site-header .brand .dttl-language-en_US{font-weight:800;color:#087f70}
.home .site-header .header-inner{gap:10px}
@media(min-width:1024px){.home .site-header .main-nav>ul{row-gap:4px}}
.trp-floating-switcher{display:none!important}
@media(max-width:1000px){.site-header .header-inner{min-height:64px;padding-top:3px;padding-bottom:3px}.site-header .brand{display:block;min-width:0}.site-header .brand .dttl-header-languages{display:flex!important;position:fixed;right:calc(max(16px,env(safe-area-inset-right)) + 8px);bottom:calc(78px + env(safe-area-inset-bottom));z-index:111;visibility:visible;opacity:1;flex-direction:column;align-items:center;justify-content:center;margin:0;gap:6px;width:32px;padding:0;background:transparent;border:0;border-radius:0;box-shadow:none}.site-header .brand .dttl-header-languages>span{display:none}.site-header .brand .dttl-header-languages a{width:32px;height:32px;min-width:32px;min-height:32px;padding:0;font-size:11px;border:1px solid #d6e6e1;border-radius:50%;background:#fff;box-shadow:0 2px 7px #14243b14}.site-header .menu-toggle{flex-shrink:0}}
.site-header .brand .dttl-header-languages{flex-wrap:nowrap}.site-header .dttl-header-account{display:inline-flex!important;align-items:center;justify-content:center;white-space:nowrap;flex-shrink:0;font:inherit;font-size:12px;font-weight:600;color:#285e3e;background:#fff;border:1px solid #d6e6e1;border-radius:7px;padding:6px 9px;min-height:28px;cursor:pointer;visibility:visible!important;opacity:1!important}.site-header .dttl-header-account:hover{background:#eef7f4}.site-header .dttl-header-account:focus-visible{outline:2px solid #087f70;outline-offset:2px}.dttl-nav-account{display:none!important}@media(max-width:1000px){.site-header .dttl-header-account{height:32px;font-size:11px;box-shadow:0 2px 7px #14243b14}}
</style>
<?php endif; ?>
</div>
<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="Mở menu">☰</button>
<nav id="primary-menu" class="main-nav" aria-label="Menu chính"><?php if (has_nav_menu('primary')) { wp_nav_menu(array('theme_location'=>'primary','container'=>false)); } else { ?><ul><li><a href="<?php echo esc_url(home_url('/')); ?>">Trang chủ</a></li><?php foreach(dttl_categories() as $slug=>$label): ?><li><a href="<?php echo esc_url(dttl_category_url($slug)); ?>"><?php echo esc_html($label); ?></a></li><?php endforeach; ?></ul><?php } ?></nav>
</div></header>
