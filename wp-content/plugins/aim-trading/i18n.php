<?php
defined('ABSPATH') || exit;
/** Language context for the standalone trading screens. Never changes ledger values. */
function aimtr_page_language(){
 global $TRP_LANGUAGE;
 $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
 if(preg_match('~(?:^|/)en(?:/|$)~',(string)$path))return 'en';
 if(is_string($TRP_LANGUAGE)&&strpos(strtolower($TRP_LANGUAGE),'en')===0)return 'en';
 return 'vi';
}
function aimtr_language_output($html){
 $guide='<script src="'.esc_url(plugins_url('assets/get-started.js',__FILE__)).'?v='.AIM_Trading::VERSION.'" defer></script>';
 $html=str_replace('</body>',$guide.'</body>',$html);
 if(aimtr_page_language()!=='en')return $html;
 $dict=json_decode(file_get_contents(__DIR__.'/assets/en-dictionary.json'),true);
 $boot='<script>window.AIMTradingEnglish='.wp_json_encode($dict,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script><script src="'.esc_url(plugins_url('assets/language.js',__FILE__)).'?v='.AIM_Trading::VERSION.'" defer></script>';
 $html=str_replace('<html lang="vi">','<html lang="en">',$html);
 return str_replace('</head>',$boot.'</head>',$html);
}
