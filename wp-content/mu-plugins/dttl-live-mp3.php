<?php
/**
 * Plugin Name: DTTL – MP3 bài live
 * Description: Tạo và tải MP3 theo số bài bằng EverAI trong WordPress.
 */

defined('ABSPATH') || exit;

final class DTTL_Live_MP3 {
    const PAGE = 'dttl-live-mp3';
    const OPTION = 'dttl_live_mp3_settings';
    const CRON = 'dttl_live_mp3_process';
    const API = 'https://www.everai.vn/api/v1/tts';
    const MAX_CHARS = 25000;
    const MAX_BATCH = 10;
    const CLAIM_SECONDS = 60;

    static function boot() {
        add_action('admin_menu', function () {
            $hook = add_management_page('MP3 bài live', 'MP3 bài live', 'manage_options', self::PAGE, [self::class, 'page']);
            add_action('load-' . $hook, [self::class, 'install']);
        });
        add_action('admin_enqueue_scripts', function ($hook) {
            if ($hook !== 'tools_page_' . self::PAGE) {
                return;
            }
            $url = plugin_dir_url(__FILE__) . 'dttl-live-mp3/';
            wp_enqueue_style(self::PAGE, $url . 'admin.css', [], '1.0.1');
            wp_enqueue_script(self::PAGE, $url . 'admin.js', [], '1.0.2', true);
            wp_localize_script(self::PAGE, 'dttlLiveMp3', ['nonce' => wp_create_nonce(self::PAGE)]);
        });
        add_action('admin_post_dttl_live_mp3_settings', [self::class, 'save_settings']);
        add_action('admin_post_dttl_live_mp3_import', [self::class, 'import']);
        add_action('admin_post_dttl_live_mp3_download', [self::class, 'download']);
        add_action('wp_ajax_dttl_live_mp3_tick', [self::class, 'tick']);
        add_action('wp_ajax_dttl_live_mp3_retry', [self::class, 'retry']);
        add_action(self::CRON, [self::class, 'run_queue']);
    }

    static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dttl_live_mp3';
    }

    static function install() {
        if (get_option('dttl_live_mp3_schema') === '1') {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            lesson int unsigned NOT NULL,
            title varchar(250) NOT NULL,
            script longtext NOT NULL,
            voice varchar(120) NOT NULL,
            model varchar(30) NOT NULL,
            speed decimal(2,1) NOT NULL,
            state varchar(15) NOT NULL,
            request_id varchar(128) NOT NULL DEFAULT '',
            audio_url text NOT NULL,
            detail varchar(200) NOT NULL DEFAULT '',
            progress tinyint unsigned NOT NULL DEFAULT 0,
            touched bigint unsigned NOT NULL,
            next_check bigint unsigned NOT NULL,
            PRIMARY KEY  (lesson),
            KEY queue (state,next_check)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            update_option('dttl_live_mp3_schema', '1', false);
        }
    }

    static function settings() {
        return wp_parse_args(get_option(self::OPTION, []), [
            'key' => '', 'voice' => '', 'model' => 'everai-v1.6', 'speed' => 1.0,
        ]);
    }

    static function key() {
        $environment = defined('DTTL_EVERAI_API_KEY') ? DTTL_EVERAI_API_KEY : getenv('DTTL_EVERAI_API_KEY');
        if ($environment) {
            return trim($environment);
        }
        $encoded = self::settings()['key'];
        if (!$encoded || !function_exists('sodium_crypto_secretbox_open')) {
            return '';
        }
        $bytes = base64_decode($encoded, true);
        $nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (!$bytes || strlen($bytes) < $nonce_size + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return '';
        }
        return sodium_crypto_secretbox_open(substr($bytes, $nonce_size), substr($bytes, 0, $nonce_size), self::storage_key()) ?: '';
    }

    static function storage_key() {
        return hash('sha256', wp_salt('auth') . wp_salt('secure_auth') . self::PAGE, true);
    }

    static function require_admin() {
        if (!current_user_can('manage_options')) {
            wp_die('Chỉ quản trị viên được sử dụng công cụ.', '', ['response' => 403]);
        }
    }

    static function notice($message, $error = false) {
        set_transient(self::PAGE . '-' . get_current_user_id(), [$message, $error], MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('tools.php?page=' . self::PAGE));
        exit;
    }

    static function save_settings() {
        self::require_admin();
        check_admin_referer('dttl_live_mp3_settings');
        $key = trim(wp_unslash((string) ($_POST['api_key'] ?? '')));
        $voice = trim(wp_unslash((string) ($_POST['voice'] ?? '')));
        $model = (string) ($_POST['model'] ?? '');
        $speed = (float) ($_POST['speed'] ?? 0);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,120}$/D', $voice)
            || !in_array($model, ['everai-v1.6', 'everai-v1.5', 'everai-v1.5-turbo'], true)
            || $speed < 0.5 || $speed > 2.0) {
            self::notice('Kiểm tra mã giọng, mô hình và tốc độ đọc.', true);
        }
        $settings = self::settings();
        if ($key !== '') {
            if (!preg_match('/^[!-~]{8,512}$/D', $key) || !function_exists('sodium_crypto_secretbox')) {
                self::notice('Khóa API chưa hợp lệ hoặc máy chủ cần cấu hình DTTL_EVERAI_API_KEY.', true);
            }
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $settings['key'] = base64_encode($nonce . sodium_crypto_secretbox($key, $nonce, self::storage_key()));
        }
        $settings['voice'] = $voice;
        $settings['model'] = $model;
        $settings['speed'] = round($speed, 1);
        update_option(self::OPTION, $settings, false);
        self::schedule();
        self::notice('Đã lưu cấu hình EverAI.');
    }

    static function lesson_name($lesson) {
        return sprintf('%03d', $lesson);
    }

    static function parse_batch($json) {
        $data = json_decode($json, true);
        if (!is_array($data) || !array_is_list($data) || !$data || count($data) > self::MAX_BATCH) {
            return new WP_Error('batch', 'Gói cần từ 1 đến 10 bài.');
        }
        $batch = [];
        foreach ($data as $item) {
            if (!is_array($item) || !is_int($item['lesson'] ?? null) || $item['lesson'] < 1 || $item['lesson'] > 99999
                || !is_string($item['text'] ?? null) || !is_string($item['title'] ?? '')) {
                return new WP_Error('lesson', 'Kiểm tra số bài, tiêu đề và văn bản của gói.');
            }
            $lesson = $item['lesson'];
            $script = trim(str_replace(["\r\n", "\r"], "\n", $item['text']));
            $length = preg_match_all('/./us', $script);
            if (isset($batch[$lesson]) || !$length || $length > self::MAX_CHARS || str_contains($script, "\0")) {
                return new WP_Error('script', 'Bài ' . self::lesson_name($lesson) . ' bị trùng, trống hoặc vượt 25.000 ký tự.');
            }
            $title = sanitize_text_field($item['title'] ?? '');
            $batch[$lesson] = ['lesson' => $lesson, 'title' => wp_html_excerpt($title, 200), 'script' => $script];
        }
        ksort($batch);
        return $batch;
    }

    static function import() {
        self::require_admin();
        check_admin_referer('dttl_live_mp3_import');
        self::install();
        if (get_option('dttl_live_mp3_schema') !== '1') {
            self::notice('Chưa tạo được bảng lưu hàng đợi MP3.', true);
        }
        $settings = self::settings();
        $json = wp_unslash((string) ($_POST['batch'] ?? ''));
        if (strlen($json) > 1250000) {
            self::notice('Gói văn bản vượt dung lượng cho phép.', true);
        }
        $batch = self::parse_batch($json);
        if (is_wp_error($batch)) {
            self::notice($batch->get_error_message(), true);
        }
        global $wpdb;
        $table = self::table();
        $added = [];
        $duplicates = [];
        $errors = [];
        foreach ($batch as $item) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (lesson,title,script,voice,model,speed,state,audio_url,touched,next_check)
                 VALUES (%d,%s,%s,%s,%s,%f,'queued','',%d,0) ON DUPLICATE KEY UPDATE lesson=lesson",
                $item['lesson'], $item['title'], $item['script'], $settings['voice'], $settings['model'], $settings['speed'], time()
            ));
            $number = self::lesson_name($item['lesson']);
            if ($result === false) {
                $errors[] = $number;
            } elseif ($result === 1) {
                $added[] = $number;
            } else {
                $duplicates[] = $number;
            }
        }
        self::schedule();
        $message = $added ? 'Đã xếp hàng: ' . implode(', ', $added) . '. ' : '';
        $message .= $duplicates ? 'Bài đã có, giữ bản hiện tại: ' . implode(', ', $duplicates) . '. ' : '';
        $message .= $errors ? 'Chưa lưu được: ' . implode(', ', $errors) . '.' : '';
        self::notice(trim($message), (bool) $errors);
    }

    static function schedule() {
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_single_event(time() + 15, self::CRON);
        }
    }

    static function run_queue() {
        $settings = self::settings();
        if (get_option('dttl_live_mp3_schema') !== '1' || !self::key() || !$settings['voice']) {
            return;
        }
        global $wpdb;
        $table = self::table();
        $now = time();
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET state='uncertain', detail=%s WHERE state='sending' AND touched < %d",
            'Chưa xác minh được EverAI đã nhận yêu cầu. Kiểm tra lịch sử EverAI.', $now - 120
        ));
        // Recheck requests whose download link was rejected by the old host rules.
        if (get_option('dttl_live_mp3_result_sync') !== '1') {
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $table SET state='processing', next_check=0, detail='' WHERE state='uncertain'
                 AND request_id<>'' AND detail=%s",
                'EverAI đã xử lý nhưng chưa trả link MP3 hợp lệ. Kiểm tra lịch sử EverAI.'
            ));
            if ($updated !== false) {
                update_option('dttl_live_mp3_result_sync', '1', false);
            }
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE state IN ('queued','processing') AND next_check <= %d
             ORDER BY CASE WHEN state='queued' THEN 0 ELSE 1 END, next_check, lesson LIMIT 1", $now
        ));
        if ($row) {
            self::process($row, $settings);
        }
        if ($wpdb->get_var("SELECT lesson FROM $table WHERE state IN ('queued','processing','sending') LIMIT 1")) {
            self::schedule();
        }
    }

    static function process($row, $settings) {
        global $wpdb;
        $table = self::table();
        $sending = $row->state === 'queued';
        $now = time();
        $where = ['lesson' => $row->lesson, 'state' => $row->state, 'next_check' => $row->next_check];
        $changes = ['state' => $sending ? 'sending' : 'processing', 'touched' => $now, 'next_check' => $now + self::CLAIM_SECONDS];
        if ($sending && $row->voice === '') {
            foreach (['voice', 'model', 'speed'] as $field) {
                $row->$field = $changes[$field] = $settings[$field];
            }
        }
        $claimed = $wpdb->update($table, $changes, $where);
        if ($claimed !== 1) {
            return;
        }
        $where['state'] = $sending ? 'sending' : 'processing';
        $where['next_check'] = $changes['next_check'];
        $args = [
            'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 262144,
            'headers' => ['Authorization' => 'Bearer ' . self::key(), 'Content-Type' => 'application/json'],
            'method' => $sending ? 'POST' : 'GET',
        ];
        $url = self::API;
        if ($sending) {
            $args['body'] = wp_json_encode([
                'response_type' => 'indirect', 'input_text' => $row->script,
                'voice_code' => $row->voice, 'model_id' => $row->model,
                'audio_type' => 'mp3', 'bitrate' => 128, 'speed_rate' => (float) $row->speed,
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $url .= '/' . rawurlencode($row->request_id);
        }
        $response = wp_remote_request($url, $args);
        $http = (int) wp_remote_retrieve_response_code($response);
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $update = self::outcome($row, $sending, $http, $payload, $result);
        $wpdb->update($table, $update + ['touched' => time()], $where);
    }

    static function outcome($row, $sending, $http, $payload, $result) {
        $valid = $http >= 200 && $http < 300 && (string) ($payload['status'] ?? '') === '1';
        if ($sending && $valid && preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', (string) ($result['request_id'] ?? ''))) {
            return ['state' => 'processing', 'request_id' => $result['request_id'], 'detail' => '', 'next_check' => time() + 10];
        }
        if (!$valid || $sending) {
            $rejected = in_array($http, [400, 401, 402, 403, 413, 422, 429], true)
                || ($http >= 200 && $http < 300 && (string) ($payload['status'] ?? '') === '0');
            if ($sending && !$rejected) {
                return ['state' => 'uncertain', 'detail' => 'Chưa xác minh được EverAI đã nhận yêu cầu. Kiểm tra lịch sử EverAI.'];
            }
            $detail = $http ? 'EverAI chưa trả kết quả hợp lệ (HTTP ' . $http . ').' : 'Chưa kết nối được EverAI.';
            $provider_code = (string) ($payload['error_code'] ?? '');
            if (preg_match('/^[a-zA-Z0-9_-]{1,50}$/D', $provider_code)) {
                $detail .= ' Mã: ' . $provider_code;
            }
            return $sending ? ['state' => 'failed', 'detail' => $detail]
                : ['state' => 'processing', 'detail' => $detail, 'next_check' => time() + 90];
        }
        $status = strtolower(trim((string) ($result['status'] ?? '')));
        if (filter_var($result['audio_expired'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['state' => 'expired', 'detail' => 'Link tải EverAI đã hết hạn.'];
        }
        if (in_array($status, ['failure', 'failed', 'error', 'cancelled', 'canceled'], true)) {
            return ['state' => 'failed', 'detail' => 'EverAI báo tạo audio thất bại.'];
        }
        $audio_url = is_string($result['audio_link'] ?? null) ? trim($result['audio_link']) : '';
        if (self::valid_audio_url($audio_url)) {
            return ['state' => 'ready', 'progress' => 100, 'detail' => '', 'audio_url' => $audio_url];
        }
        if ($audio_url !== '' && in_array($status, ['done', 'success', 'completed'], true)) {
            return ['state' => 'uncertain', 'detail' => 'Link MP3 do EverAI trả về chưa được hỗ trợ. Anh có thể tải trong Lịch sử EverAI.'];
        }
        $progress = min(100, max(0, (int) ($result['progress'] ?? 0)));
        return ['state' => 'processing', 'progress' => $progress,
            'detail' => $progress >= 99 ? 'WordPress đang chờ đường dẫn tải MP3 từ API EverAI.' : '',
            'next_check' => time() + 20];
    }

    static function valid_audio_url($url) {
        if (!$url || !wp_http_validate_url($url) || wp_parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return $host === 'everai.vn' || str_ends_with($host, '.everai.vn') || $host === 'media.everaicdn.com';
    }

    static function jobs($page = 1) {
        if (get_option('dttl_live_mp3_schema') !== '1') {
            return [];
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT lesson,title,state,detail,progress,(request_id <> \'\') AS has_request FROM ' . self::table()
            . ' ORDER BY lesson DESC LIMIT 30 OFFSET %d',
            ($page - 1) * 30
        )) ?: [];
        foreach ($rows as $row) {
            $row->can_recheck = (bool) $row->has_request
                && in_array($row->state, ['processing', 'uncertain'], true);
            unset($row->has_request);
            if ($row->state === 'ready') {
                $row->download_nonce = wp_create_nonce('dttl_live_mp3_download_' . $row->lesson);
            }
        }
        return $rows;
    }

    static function tick() {
        self::require_admin();
        check_ajax_referer(self::PAGE, 'nonce');
        $lesson = absint($_POST['recheck_lesson'] ?? 0);
        if ($lesson) {
            self::recheck($lesson);
        } else {
            self::run_queue();
        }
        $page = max(1, min(4000, absint($_POST['page'] ?? 1)));
        wp_send_json_success(['rows' => self::jobs($page), 'configured' => (bool) (self::key() && self::settings()['voice'])]);
    }

    static function recheck($lesson) {
        if (get_option('dttl_live_mp3_schema') !== '1' || !self::key()) {
            return;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE lesson=%d AND request_id<>''
             AND state IN ('processing','uncertain') AND (next_check <> touched + %d OR next_check <= %d)",
            $lesson, self::CLAIM_SECONDS, time()
        ));
        if ($row) {
            self::process($row, self::settings());
        }
    }

    static function retry() {
        self::require_admin();
        check_ajax_referer(self::PAGE, 'nonce');
        if (!self::key()) {
            wp_send_json_error('Lưu khóa API EverAI trước.', 400);
        }
        global $wpdb;
        $table = self::table();
        $lesson = absint($_POST['lesson'] ?? 0);
        $changed = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET state='queued',request_id='',audio_url='',detail='',progress=0,touched=%d,next_check=0
             WHERE lesson=%d AND state IN ('failed','expired')", time(), $lesson
        ));
        if ($changed !== 1) {
            wp_send_json_error('Bài này chưa thể tạo lại.', 409);
        }
        self::schedule();
        wp_send_json_success();
    }

    static function download() {
        self::require_admin();
        $lesson = absint($_GET['lesson'] ?? 0);
        check_admin_referer('dttl_live_mp3_download_' . $lesson);
        global $wpdb;
        $table = self::table();
        $url = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT audio_url FROM $table WHERE lesson=%d AND state='ready'", $lesson
        ));
        if (!self::valid_audio_url($url)) {
            wp_die('Bài này chưa có link MP3 hợp lệ.', '', ['response' => 404]);
        }
        $temp = wp_tempnam('dttl-mp3');
        if (!$temp) {
            wp_die('Không tạo được file tạm.', '', ['response' => 503]);
        }
        $limit = 32 * MB_IN_BYTES;
        $response = wp_safe_remote_get($url, [
            'timeout' => 40, 'stream' => true, 'filename' => $temp, 'limit_response_size' => $limit + 1, 'redirection' => 3,
        ]);
        $http = (int) wp_remote_retrieve_response_code($response);
        $size = filesize($temp);
        $sample = (string) file_get_contents($temp, false, null, 0, 3);
        $is_mp3 = $sample === 'ID3' || (strlen($sample) >= 2 && ord($sample[0]) === 255 && (ord($sample[1]) & 224) === 224);
        if ($http !== 200 || !$size || $size > $limit || !$is_mp3) {
            unlink($temp);
            if (in_array($http, [403, 404, 410], true)) {
                $wpdb->update($table, ['state' => 'expired', 'detail' => 'Link tải EverAI không còn truy cập được.'],
                    ['lesson' => $lesson, 'state' => 'ready', 'audio_url' => $url]);
            }
            wp_die('Chưa tải được MP3 từ EverAI. Quay lại trang công cụ để kiểm tra trạng thái.', '', ['response' => 502]);
        }
        nocache_headers();
        header('Content-Type: audio/mpeg');
        header('Content-Disposition: attachment; filename="' . self::lesson_name($lesson) . '.mp3"');
        header('Content-Length: ' . $size);
        header('X-Content-Type-Options: nosniff');
        readfile($temp);
        unlink($temp);
        exit;
    }

    static function page() {
        self::require_admin();
        require __DIR__ . '/dttl-live-mp3/admin.php';
    }
}

DTTL_Live_MP3::boot();
