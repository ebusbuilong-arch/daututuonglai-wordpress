<?php
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function wp_http_validate_url($url) { return filter_var($url, FILTER_VALIDATE_URL); }
require __DIR__ . '/../wp-content/mu-plugins/dttl-live-mp3.php';
function check($value, $expected) {
    if ($value !== $expected) { throw new RuntimeException('Callback recovery regression'); }
}
$url = 'https://media.everaicdn.com/test.mp3';
$payload = ['request_id' => 'job-81', 'status' => 'SUCCESS', 'audio_link' => $url];
$data = ['status' => 1, 'result' => ['request_id' => 'job-81', 'payload' => $payload]];
check(DTTL_Live_MP3::callback_audio('job-81', 200, $data), $url);
$data['result']['payload'] = json_encode($payload);
check(DTTL_Live_MP3::callback_audio('job-81', 200, $data), $url);
check(DTTL_Live_MP3::callback_audio('job-82', 200, $data), '');
check(DTTL_Live_MP3::callback_audio('job-81', 403, $data), '');
foreach (['request_id' => 'other', 'status' => 'PROCESSING', 'audio_expired' => true,
    'audio_link' => 'https://untrusted.example/test.mp3'] as $key => $value) {
    $data['result']['payload'] = array_replace($payload, [$key => $value]);
    check(DTTL_Live_MP3::callback_audio('job-81', 200, $data), '');
}
check(DTTL_Live_MP3::callback_audio('job-81', 200, null), '');
echo "Callback recovery tests passed\n";

$id = '1bfd106b-e6c3-4fd2-bee9-4673adfc09d9';
check(DTTL_Live_MP3::external_download_url($id), 'https://everai.vn/text-to-speech/download?request_id=' . $id);
foreach (['', 'https://evil.example', $id . '&next=evil', $id . "\n", null] as $invalid) {
    check(DTTL_Live_MP3::external_download_url($invalid), '');
}
echo "External download tests passed\n";
