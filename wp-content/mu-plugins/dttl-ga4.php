<?php
/**
 * Plugin Name: DTTL Google Analytics
 * Description: Loads the site's GA4 web stream on public WordPress pages.
 */

defined('ABSPATH') || exit;

add_action('wp_head', static function () {
    if (is_preview() || is_page('tai-khoan')) {
        return;
    }
    ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-VV4TX709T9"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){window.dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-VV4TX709T9');
</script>
    <?php
}, 1);

add_action('wp_loaded', static function () {
    if (function_exists('rocket_clean_domain') && add_option('dttl_ga4_cache_refreshed', 1, '', false)) {
        rocket_clean_domain();
    }
}, 20);
