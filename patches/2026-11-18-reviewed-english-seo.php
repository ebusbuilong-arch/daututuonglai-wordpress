<?php
/**
 * Applied to the current live theme inc/seo.php on 2026-11-18.
 * Reference patch only; this path is not deployed automatically.
 * The repository theme is older than production. Do not overwrite the live
 * theme with it. Merge these functions once into the current seo.php only.
 * Uses TranslatePress translations and Rank Math filters for English posts.
 * VI and admin output remain unchanged; missing translations retain originals.
 */

// Translate reviewed post metadata for English pages (2026-11-18).
function dttl_reviewed_en_metadata($text) {
    global $TRP_LANGUAGE;
    static $busy = false;
    if ($busy || is_admin() || !is_singular('post') || !is_string($text) || $text === '' || !is_string($TRP_LANGUAGE) || strpos($TRP_LANGUAGE, 'en') !== 0 || !function_exists('trp_translate')) {
        return $text;
    }
    $busy = true;
    try {
        $translated = trp_translate($text, $TRP_LANGUAGE, false);
        return is_string($translated) && trim($translated) !== '' ? wp_strip_all_tags($translated) : $text;
    } finally {
        $busy = false;
    }
}
add_filter('rank_math/frontend/title', 'dttl_reviewed_en_metadata', 99);
add_filter('rank_math/frontend/description', 'dttl_reviewed_en_metadata', 99);

function dttl_reviewed_en_image_attributes($attr) {
    if (!empty($attr['alt'])) {
        $attr['alt'] = dttl_reviewed_en_metadata($attr['alt']);
    }
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'dttl_reviewed_en_image_attributes', 99);

function dttl_reviewed_en_content_alts($html) {
    global $TRP_LANGUAGE;
    if (is_admin() || !is_singular('post') || !is_string($TRP_LANGUAGE) || strpos($TRP_LANGUAGE, 'en') !== 0 || !class_exists('WP_HTML_Tag_Processor')) return $html;
    $tags = new WP_HTML_Tag_Processor($html);
    while ($tags->next_tag('img')) {
        $alt = $tags->get_attribute('alt');
        if (is_string($alt) && $alt !== '') {
            $translated = dttl_reviewed_en_metadata($alt);
            if ($translated !== $alt) $tags->set_attribute('alt', $translated);
        }
    }
    return $tags->get_updated_html();
}
add_filter('the_content', 'dttl_reviewed_en_content_alts', 99);
