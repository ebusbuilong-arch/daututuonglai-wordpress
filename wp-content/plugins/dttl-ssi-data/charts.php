<?php
if (!defined('ABSPATH')) { exit; }
final class DTTL_SSI_Charts {
    const VERSION = '0.4.2';
    public static function boot() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_filter('rest_pre_serve_request', [__CLASS__, 'public_cors'], 100, 4);
        add_shortcode('dttl_stock_chart', [__CLASS__, 'shortcode']);
        add_action('template_redirect', [__CLASS__, 'embed']);
        add_filter('the_title', function($title, $id) {
            if (!is_admin() && strpos($_SERVER['REQUEST_URI'] ?? '', '/en/') === 0 && $title === 'Biểu đồ chứng khoán' && has_shortcode((string) get_post_field('post_content', $id), 'dttl_stock_chart')) { return 'Stock charts'; }
            return $title;
        }, 20, 2);
        add_action('admin_enqueue_scripts', function($hook) { if ($hook === 'toplevel_page_dttl-ssi') { self::assets(); } });
        add_action('wp_enqueue_scripts', function() { if (is_singular() && has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'dttl_stock_chart')) { self::assets(); } });
    }
    public static function market_data($path, $query) {
        if ($path !== 'ohlc') { return self::error('Tuyến dữ liệu không hợp lệ.', 400); }
        return self::request($path, $query);
    }
    public static function embed() {
        if (!is_page('bieu-do-chung-khoan') || !isset($_GET['dttl_ssi_embed'])) { return; }
        nocache_headers(); header_remove("X-Frame-Options"); header("Content-Security-Policy: frame-ancestors *");
        self::assets();
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>SSI chart</title>';
        wp_print_styles('dttl-ssi-chart');
        echo '<style>body{margin:0;background:#fff}body>p,body>h2{display:none}.dttl-stock{margin:0!important;border:0!important;border-radius:0!important;padding:12px!important}.dttl-stock-heading,.dttl-stock-controls,.dttl-stock-note{display:none!important}.dttl-stock-plot{height:310px!important}.dttl-stock-summary{font-size:12px}.dttl-stock-price{font-size:14px}.dttl-stock-tools{margin-top:8px}</style></head><body>';
        if (isset($_GET['dttl_ssi_embed']) && $_GET['dttl_ssi_embed']==='widget') { echo '<style>.dttl-stock-controls{display:flex!important;flex-wrap:wrap}.dttl-stock-plot{height:360px!important}.dttl-stock-note{display:block!important}</style>'; }
        echo self::shortcode(); echo '<footer style="padding:10px 12px;font:13px sans-serif"><a href="'.esc_url(home_url('/bieu-do-chung-khoan/')).'" target="_blank" rel="noopener">Biểu đồ CK Việt Nam · Đầu Tư Tương Lai</a> · Nguồn SSI</footer>'; wp_print_scripts('dttl-ssi-chart'); echo '</body></html>'; exit;
    }
    public static function routes() {
        register_rest_route('dttl-ssi/v1', '/symbols', ['methods' => 'GET', 'callback' => [__CLASS__, 'symbols_route'], 'permission_callback' => [__CLASS__, 'public_permission']]);
        register_rest_route('dttl-ssi/v1', '/history', ['methods' => 'GET', 'callback' => [__CLASS__, 'history_route'], 'permission_callback' => [__CLASS__, 'public_permission']]);
    }
    public static function public_permission($request) {
        $key='dttl_ssi_public_'.substr(hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', wp_salt('nonce')),0,24).'_'.floor(time()/60);
        $n=(int)get_transient($key);
        if($n>=60){return self::error('API: tối đa 60 yêu cầu/phút/IP. Thử lại sau một phút.',429);}
        set_transient($key,$n+1,60); return true;
    }
    public static function public_cors($served,$result,$request,$server) {
        if(in_array($request->get_route(),['/dttl-ssi/v1/symbols','/dttl-ssi/v1/history'],true)&&in_array($request->get_method(),['GET','OPTIONS'],true)){
            header_remove('Access-Control-Allow-Credentials');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
        }
        return $served;
    }
    public static function api_guide($en) {
        $base=rest_url('dttl-ssi/v1/');
        $symbols=$base.'symbols?board=HOSE';
        $history=$base.'history?symbol=FPT&board=HOSE&frame=1d';
        ob_start(); ?>
        <section id="stock-api" style="margin:32px 0;padding:24px;border:1px solid #dbe2ea;border-radius:16px;overflow-wrap:anywhere">
        <h2><?php echo $en?'Embed the chart on your website':'Lấy biểu đồ về website'; ?></h2>
        <p><?php echo $en?'Paste this iframe into an HTML block. Visitors can select an exchange, symbol and timeframe inside the widget.':'Dán iframe dưới đây vào khối HTML của web. Người xem có thể chọn sàn, mã và khung nến ngay trong biểu đồ.'; ?></p>
        <pre style="white-space:pre-wrap;overflow-wrap:anywhere"><code><?php echo esc_html('<iframe src="'.home_url('/bieu-do-chung-khoan/?dttl_ssi_embed=widget&symbol=FPT&board=HOSE&frame=1d').'" title="Biểu đồ chứng khoán Việt Nam" width="100%" height="720" style="border:0;border-radius:12px" loading="lazy"></iframe>'); ?></code></pre>
        <h2><?php echo $en?'Stock data API for websites':'API dữ liệu chứng khoán cho website'; ?></h2>
        <p><?php echo $en?'Read-only JSON API. No API key or login required. Browser requests from other domains are supported (CORS); use credentials: omit. SSI credentials remain on our server.':'API JSON chỉ đọc, không cần khóa hoặc đăng nhập. Web khác gọi được qua trình duyệt (CORS); dùng credentials: omit. Khóa SSI được giữ trên máy chủ.'; ?></p>
        <p><strong><?php echo $en?'Symbol list':'Danh sách mã'; ?></strong><br><a href="<?php echo esc_url($symbols); ?>"><code><?php echo esc_html($symbols); ?></code></a></p>
        <p><strong><?php echo $en?'Candlesticks and volume':'Dữ liệu nến và khối lượng'; ?></strong><br><a href="<?php echo esc_url($history); ?>"><code><?php echo esc_html($history); ?></code></a></p>
        <p><code>board</code>: HOSE / HNX / UPCOM · <code>symbol</code>: FPT, HPG, SHS, ACV…<br><code>frame</code>: 1m / 5m / 15m / 1h / 1d</p>
        <pre style="overflow:auto;white-space:pre;padding:16px;background:#101827;color:#e2e8f0;border-radius:10px"><code><?php echo esc_html("const response = await fetch(".wp_json_encode($history, JSON_UNESCAPED_SLASHES).", {credentials: 'omit'});
if (!response.ok) throw new Error('API HTTP ' + response.status);
const data = await response.json();
console.log(data.security, data.bars, data.fetchedAt);"); ?></code></pre>
        <p><code>bars[]</code>: <code>time</code> (Unix seconds), <code>date</code> (UTC+7), <code>open</code>, <code>high</code>, <code>low</code>, <code>close</code>, <code>volume</code>.<br><code>security</code>, <code>frame</code>, <code>fetchedAt</code>, <code>source</code>, <code>priceUnit</code>, <code>pollSeconds</code>.</p>
        <p><?php echo $en?'Limits: 60 requests/minute/IP; at most 12 uncached history requests/minute/IP. Refresh no faster than pollSeconds (60 seconds intraday, 300 seconds daily). Shared upstream quotas may temporarily stop new loads. HTTP 429: wait at least 60 seconds; 503: retry later; 400/404: check parameters.':'Giới hạn: 60 yêu cầu/phút/IP; tối đa 12 yêu cầu lịch sử chưa có bộ nhớ đệm/phút/IP. Tải lại không nhanh hơn pollSeconds (60 giây với nến trong ngày, 300 giây với nến ngày). Hạn mức nguồn dùng chung có thể tạm dừng tải mới. HTTP 429: đợi tối thiểu 60 giây; 503: thử lại sau; 400/404: kiểm tra tham số.'; ?></p>
        <p><?php echo $en?'Source: SSI via Đầu Tư Tương Lai. Keep attribution, display the last candle time, and use the returned price units. This is not a tick feed or an order API; the current candle may be incomplete. Maximum 800 candles per response; no historical pagination is exposed.':'Nguồn: SSI qua Đầu Tư Tương Lai. Giữ thông tin nguồn, hiển thị thời điểm nến cuối và dùng đơn vị giá trả về. Đây không phải dữ liệu từng tick hay API đặt lệnh; nến hiện tại có thể chưa kết thúc. Mỗi phản hồi tối đa 800 nến; API chưa hỗ trợ phân trang lịch sử.'; ?></p>
        </section>
        <?php return ob_get_clean();
    }
    private static function error($message, $status = 503) { return new WP_Error('ssi_unavailable', $message, ['status' => $status]); }
    private static function namespace_key() { return substr(hash('sha256', (string) get_option(DTTL_SSI_Data::OPTION, '')), 0, 12); }
    private static function budget() {
        if (get_transient('dttl_ssi_pause')) { return self::error('SSI đang giới hạn kết nối. Vui lòng thử lại sau ít phút.', 429); }
        foreach (['minute' => [60, 60], 'day' => [1500, DAY_IN_SECONDS]] as $type => $config) {
            $key = 'dttl_ssi_budget_' . $type . '_' . floor(time() / $config[1]);
            $count = (int) get_transient($key);
            if ($count >= $config[0]) { return self::error('Đã đạt giới hạn tải dữ liệu. Vui lòng thử lại sau.', 429); }
            set_transient($key, $count + 1, $config[1]);
        }
        return true;
    }
    private static function token() {
        $sealed = get_transient('dttl_ssi_access_token');
        if (is_string($sealed) && $sealed !== '') {
            $raw = base64_decode($sealed, true);
            if ($raw !== false && strlen($raw) > 28) {
                $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
                $decoded = $plain ? json_decode($plain, true) : null;
                if (is_array($decoded) && !empty($decoded['token'])) { return $decoded['token']; }
            }
        }
        try { $credentials = DTTL_SSI_Data::credentials(); } catch (RuntimeException $e) { return self::error('Kết nối SSI cần được cấu hình lại.'); }
        if (empty($credentials['apiKey']) || empty($credentials['apiSecret'])) { return self::error('Chưa cấu hình kết nối dữ liệu SSI.'); }
        $budget = self::budget(); if (is_wp_error($budget)) { return $budget; }
        $response = wp_remote_post(DTTL_SSI_Data::URL, ['timeout' => 15, 'redirection' => 0, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode($credentials), 'limit_response_size' => 65536]);
        if (is_wp_error($response)) { return self::error('Chưa kết nối được máy chủ SSI.'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 429) { set_transient('dttl_ssi_pause', 1, 60); }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($data) || empty($data['accessToken']) || !is_string($data['accessToken'])) { return self::error('SSI chưa cấp quyền truy cập dữ liệu. Vui lòng thử lại sau.'); }
        $ttl = 600;
        if (!empty($data['expiresAt']) && is_numeric($data['expiresAt'])) {
            $expires = (float) $data['expiresAt']; if ($expires > 100000000000) { $expires /= 1000; }
            $ttl = max(1, min(600, (int) $expires - time() - 30));
        }
        set_transient('dttl_ssi_access_token', DTTL_SSI_Data::encrypt(['token' => $data['accessToken']]), $ttl);
        return $data['accessToken'];
    }
    private static function request($path, $query, $retry = true) {
        $token = self::token(); if (is_wp_error($token)) { return $token; }
        $budget = self::budget(); if (is_wp_error($budget)) { return $budget; }
        $url = 'https://api.ssi.com.vn/api/v3/data/' . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $response = wp_remote_get($url, ['timeout' => 20, 'redirection' => 0, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept-Language' => 'vi'], 'limit_response_size' => 2097152]);
        if (is_wp_error($response)) { return self::error('Kết nối SSI tạm thời gián đoạn. Vui lòng thử lại.'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 401 && $retry) { delete_transient('dttl_ssi_access_token'); return self::request($path, $query, false); }
        if ($code === 429) { set_transient('dttl_ssi_pause', 1, 60); return self::error('SSI đang giới hạn tải dữ liệu. Vui lòng thử lại sau một phút.', 429); }
        if ($code !== 200) {
            $detail = json_decode(wp_remote_retrieve_body($response), true);
            $message = is_array($detail) ? ($detail['msg'] ?? $detail['message'] ?? '') : '';
            $message = is_string($message) ? sanitize_text_field(substr($message, 0, 400)) : '';
            try { foreach (DTTL_SSI_Data::credentials() as $secret) { if ($secret !== '') { $message = str_replace($secret, '[redacted]', $message); } } } catch (RuntimeException $e) { $message = ''; }
            $message = str_replace($token, '[redacted]', $message);
            update_option('dttl_ssi_data_diagnostic', ['path' => $path, 'code' => $code, 'message' => $message], false);
            return self::error('Không lấy được dữ liệu SSI (HTTP ' . $code . ').');
        }
        delete_option('dttl_ssi_data_diagnostic');
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : self::error('SSI trả về dữ liệu chưa hợp lệ.');
    }
    private static function symbols($board) {
        $key = 'dttl_ssi_symbols_' . self::namespace_key() . '_' . $board;
        $cached = get_transient($key); if (is_array($cached)) { return $cached; }
        $data = self::request('securitiesByBoard', ['board' => $board]);
        if (is_wp_error($data)) { return $data; }
        $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
        $symbols = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['symbol']) || !preg_match('/^[A-Z0-9]{2,15}$/', $row['symbol'])) { continue; }
            $symbols[] = ['symbol' => $row['symbol'], 'name' => sanitize_text_field($row['symbolNameVi'] ?? $row['symbol']), 'nameEn' => sanitize_text_field($row['symbolNameEn'] ?? $row['symbol']), 'board' => $board];
        }
        if (!$symbols) { return self::error('Chưa nhận được danh sách mã từ SSI.'); }
        usort($symbols, function($a, $b) { return strcmp($a['symbol'], $b['symbol']); });
        set_transient($key, $symbols, 12 * HOUR_IN_SECONDS);
        return $symbols;
    }
    public static function symbols_route($request) {
        $board = strtoupper((string) ($request->get_param('board') ?: 'HOSE'));
        if (!in_array($board, ['HOSE', 'HNX', 'UPCOM'], true)) { return self::error('Sàn không hợp lệ.', 400); }
        $rows = self::symbols($board); if (is_wp_error($rows)) { return $rows; }
        return rest_ensure_response(['symbols' => $rows]);
    }
    public static function normalize($rows, $frame) {
        $bars = []; $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $stamp = $row['tradingDate'] ?? '';
            if (!is_string($stamp) && !is_numeric($stamp)) { continue; }
            $dt = false;
            if (is_numeric($stamp) && strlen((string) $stamp) >= 10) {
                $epoch = (float) $stamp; if ($epoch > 100000000000) { $epoch /= 1000; }
                $dt = (new DateTimeImmutable('@' . (int) $epoch))->setTimezone($tz);
            } else {
                foreach (['!Y-m-d\TH:i:s.uP', '!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s', '!Y/m/d H:i:s', '!d/m/Y H:i:s', '!Y-m-d', '!Y/m/d', '!d/m/Y', '!Ymd'] as $format) {
                    $candidate = DateTimeImmutable::createFromFormat($format, (string) $stamp, $tz);
                    $err = DateTimeImmutable::getLastErrors();
                    if ($candidate && (!$err || (!$err['warning_count'] && !$err['error_count']))) { $dt = $candidate; break; }
                }
            }
            if (!$dt) { continue; }
            $bar = ['time' => $dt->getTimestamp(), 'date' => $dt->format($frame === '1d' ? 'Y-m-d' : 'Y-m-d H:i')];
            foreach (['open', 'high', 'low', 'close', 'volume'] as $field) {
                $value = $row[$field] ?? null;
                if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) { continue 2; }
                $bar[$field] = (float) $value;
            }
            if ($bar['high'] < max($bar['open'], $bar['close'], $bar['low']) || $bar['low'] > min($bar['open'], $bar['close'])) { continue; }
            $bars[$bar['time']] = $bar;
        }
        ksort($bars, SORT_NUMERIC); return array_values($bars);
    }
    public static function history_route($request) {
        $symbol = strtoupper((string) $request->get_param('symbol'));
        $board = strtoupper((string) ($request->get_param('board') ?: 'HOSE'));
        $frame = (string) ($request->get_param('frame') ?: '1d');
        $days = ['1d' => 365, '1h' => 30, '15m' => 10, '5m' => 5, '1m' => 2];
        if (!preg_match('/^[A-Z0-9]{2,15}$/', $symbol) || !isset($days[$frame]) || !in_array($board, ['HOSE', 'HNX', 'UPCOM'], true)) { return self::error('Mã hoặc khung thời gian không hợp lệ.', 400); }
        $key = 'dttl_ssi_bars_031_' . self::namespace_key() . '_' . $board . '_' . $symbol . '_' . $frame;
        $cached = get_transient($key); if (is_array($cached)) { return rest_ensure_response($cached); }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ipkey = 'dttl_ssi_ip_' . substr(hash_hmac('sha256', $ip, wp_salt('nonce')), 0, 24) . '_' . floor(time()/60);
        $n = (int) get_transient($ipkey); if ($n >= 12) { return self::error('Anh/chị đang đổi mã quá nhanh. Vui lòng chờ một phút.', 429); }
        set_transient($ipkey, $n + 1, 60);
        $symbols = self::symbols($board); if (is_wp_error($symbols)) { return $symbols; }
        $selected = null; foreach ($symbols as $item) { if ($item['symbol'] === $symbol) { $selected = $item; break; } }
        if (!$selected) { return self::error('Mã không có trong danh sách của sàn đã chọn.', 404); }
        $lock = $key . '_lock';
        if (get_transient($lock)) { return self::error('Dữ liệu đang được cập nhật. Vui lòng thử lại sau vài giây.', 503); }
        set_transient($lock, 1, 90);
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
            $format = 'Y/m/d H:i:s';
            $rows = []; $seen = [];
            for ($page = 1; $page <= 4; $page++) {
                $data = self::request('ohlc', ['symbol' => $symbol, 'timeFrame' => $frame, 'from' => $now->modify('-' . $days[$frame] . ' days')->setTime(0,0)->format($format), 'to' => $now->format($format), 'pageIndex' => $page, 'pageSize' => 200]);
                if (is_wp_error($data)) { return $data; }
                $batch = $data['data'] ?? null;
                if (!is_array($batch)) { return self::error('Dữ liệu nến SSI chưa đúng định dạng.'); }
                $signature = md5(wp_json_encode($batch));
                if (isset($seen[$signature])) { break; } $seen[$signature] = true;
                $rows = array_merge($rows, $batch);
                $size = !empty($data['pageSize']) ? (int) $data['pageSize'] : 200;
                if (!$batch || count($batch) < $size) { break; }
            }
            $bars = self::normalize($rows, $frame);
            if ($rows && !$bars) { return self::error('SSI đã trả dữ liệu nhưng định dạng nến cần được kiểm tra.'); }
            $provisional = false;
            // Daily history may contain completed sessions only. Build today's
            // evolving candle from SSI minute bars; never invent a live quote.
            if ($frame === '1d') {
                $minute_request = new WP_REST_Request('GET');
                $minute_request->set_param('symbol', $symbol);
                $minute_request->set_param('board', $board);
                $minute_request->set_param('frame', '1m');
                $minute_response = self::history_route($minute_request);
                if (!is_wp_error($minute_response)) {
                    $minute_data = $minute_response->get_data();
                    $today = $now->format('Y-m-d');
                    $intraday = array_values(array_filter($minute_data['bars'] ?? [], function($b) use ($today) { return substr($b['date'], 0, 10) === $today; }));
                    if ($intraday) {
                        $first = reset($intraday); $last = end($intraday);
                        $current = ['time' => $now->setTime(0,0)->getTimestamp(), 'date' => $today,
                            'open' => $first['open'], 'high' => max(array_column($intraday,'high')),
                            'low' => min(array_column($intraday,'low')), 'close' => $last['close'],
                            'volume' => array_sum(array_column($intraday,'volume'))];
                        $bars = array_values(array_filter($bars, function($b) use ($today) { return $b['date'] !== $today; }));
                        $bars[] = $current;
                        $provisional = $last['date'];
                    }
                }
            }
            $result = ['security' => $selected, 'frame' => $frame, 'bars' => $bars, 'fetchedAt' => $now->format(DATE_ATOM), 'source' => 'SSI', 'priceUnit' => 'SSI', 'provisionalAt' => $provisional, 'pollSeconds' => 60];
            set_transient($key, $result, 60);
            return rest_ensure_response($result);
        } finally { delete_transient($lock); }
    }
    public static function assets() {
        wp_enqueue_style('dttl-ssi-chart', plugins_url('assets/chart.css', __FILE__), [], self::VERSION);
        wp_enqueue_script('dttl-ssi-chart', plugins_url('assets/chart.js', __FILE__), [], self::VERSION, true);
    }
    public static function shortcode($attributes = []) {
        self::assets();
        $en = strpos($_SERVER['REQUEST_URI'] ?? '', '/en/') === 0;
        $embed = isset($_GET['dttl_ssi_embed']);
        $initial_symbol = $embed && isset($_GET['symbol']) && is_string($_GET['symbol']) && preg_match('/^[A-Z0-9]{2,15}$/', $_GET['symbol']) ? $_GET['symbol'] : 'FPT';
        $initial_board = $embed && isset($_GET['board']) && in_array($_GET['board'], ['HOSE','HNX','UPCOM'], true) ? $_GET['board'] : 'HOSE';
        $initial_frame = $embed && isset($_GET['frame']) && in_array($_GET['frame'], ['1d','1h','15m','5m','1m'], true) ? $_GET['frame'] : '1d';
        ob_start(); ?>
        <p><?php echo $en ? 'Explore candlestick charts and trading volumes for stocks on HOSE, HNX and UPCOM using data from SSI.' : 'Tra cứu biểu đồ nến và khối lượng giao dịch các mã chứng khoán trên HOSE, HNX và UPCOM với dữ liệu từ SSI.'; ?></p>
        <section class="dttl-stock" data-endpoint="<?php echo esc_url(rest_url('dttl-ssi/v1/')); ?>" data-lang="<?php echo $en ? 'en' : 'vi'; ?>" aria-label="<?php echo $en ? 'Vietnam stock chart' : 'Biểu đồ chứng khoán Việt Nam'; ?>">
            <div class="dttl-stock-heading"><div><span class="dttl-stock-eyebrow">SSI MARKET DATA</span><h2><?php echo $en ? 'Vietnam stock chart' : 'Biểu đồ chứng khoán Việt Nam'; ?></h2></div><span class="dttl-stock-badge"><?php echo $en ? 'Market data' : 'Dữ liệu thị trường'; ?></span></div>
            <form class="dttl-stock-controls">
                <label><?php echo $en ? 'Exchange' : 'Sàn'; ?><select name="board"><?php foreach (['HOSE','HNX','UPCOM'] as $b) { echo '<option' . selected($initial_board,$b,false) . '>' . esc_html($b) . '</option>'; } ?></select></label>
                <label class="dttl-stock-search"><?php echo $en ? 'Symbol' : 'Mã chứng khoán'; ?><input name="symbol" value="<?php echo esc_attr($initial_symbol); ?>" maxlength="15" autocomplete="off" placeholder="FPT, HPG, VNM…"><datalist></datalist></label>
                <label><?php echo $en ? 'Timeframe' : 'Khung nến'; ?><select name="frame"><option value="1d" <?php selected($initial_frame,'1d'); ?>><?php echo $en ? '1 day' : '1 ngày'; ?></option><option value="1h" <?php selected($initial_frame,'1h'); ?>><?php echo $en ? '1 hour' : '1 giờ'; ?></option><option value="15m" <?php selected($initial_frame,'15m'); ?>><?php echo $en ? '15 min' : '15 phút'; ?></option><option value="5m" <?php selected($initial_frame,'5m'); ?>><?php echo $en ? '5 min' : '5 phút'; ?></option><option value="1m" <?php selected($initial_frame,'1m'); ?>><?php echo $en ? '1 min' : '1 phút'; ?></option></select></label>
                <button type="submit"><?php echo $en ? 'View chart' : 'Xem biểu đồ'; ?></button>
            </form>
            <div class="dttl-stock-summary"><strong class="dttl-stock-name">—</strong><span class="dttl-stock-price">—</span></div>
            <p class="dttl-stock-status" role="status" aria-live="polite"><?php echo $en ? 'Loading SSI data…' : 'Đang tải dữ liệu SSI…'; ?></p>
            <div class="dttl-stock-plot" tabindex="0" aria-label="<?php echo $en ? 'Candlestick and volume chart' : 'Biểu đồ nến và khối lượng'; ?>"><canvas></canvas><div class="dttl-stock-tooltip" hidden></div></div>
            <div class="dttl-stock-tools"><label><?php echo $en ? 'Visible candles' : 'Số nến hiển thị'; ?><select name="count"><option value="40">40</option><option value="80" selected>80</option><option value="160">160</option><option value="10000"><?php echo $en ? 'All' : 'Tất cả'; ?></option></select></label><label class="dttl-stock-pan"><?php echo $en ? 'History' : 'Xem lịch sử'; ?><input name="pan" type="range" min="0" max="0" value="0" aria-label="<?php echo $en ? 'Move through history' : 'Di chuyển qua lịch sử'; ?>"></label><button type="button" class="dttl-stock-refresh"><?php echo $en ? 'Refresh' : 'Làm mới'; ?></button></div>
            <p class="dttl-stock-note"><?php echo $en ? 'Source: SSI · Vietnam time (UTC+7). Prices use SSI’s returned units. Change is versus the previous candle. Updates every 1–5 minutes; not a live tick feed.' : 'Nguồn: SSI · Giờ Việt Nam (UTC+7). Giá theo đơn vị SSI trả về. Tăng/giảm so với nến trước. Cập nhật mỗi 1–5 phút; nến hiện tại có thể chưa kết thúc.'; ?></p>
        </section>
        <h2><?php echo $en ? 'How to use the chart' : 'Cách xem biểu đồ'; ?></h2>
        <p><?php echo $en ? 'Choose an exchange, enter a stock symbol and select View chart. Change the timeframe to explore minute, hourly or daily candles. Tap or hover over a candle to see its opening, high, low and closing prices and trading volume.' : 'Chọn sàn, nhập mã chứng khoán rồi bấm Xem biểu đồ. Đổi khung nến để xem biến động theo phút, giờ hoặc ngày. Chạm hoặc đưa chuột lên nến để xem giá mở cửa, cao nhất, thấp nhất, đóng cửa và khối lượng.'; ?></p>
        <p><?php echo $en ? 'Choose the number of visible candles and use the History slider to explore earlier periods. The last candle time and data retrieval time appear above the chart; the latest trades may not yet be reflected.' : 'Chọn số nến hiển thị và kéo thanh Xem lịch sử để xem các giai đoạn trước. Thời điểm nến cuối và thời điểm tải dữ liệu được ghi phía trên biểu đồ; dữ liệu có thể chưa phản ánh giao dịch mới nhất.'; ?></p>
        <?php if (!$embed) { ?>
        <section class="dttl-fx" data-lang="<?php echo $en?'en':'vi'; ?>">
        <h2><?php echo $en?'Forex charts':'Biểu đồ Forex'; ?></h2>
        <p><?php echo $en?'Embedded TradingView chart. Availability and delay depend on the selected provider. Forex data is not included in the SSI API.':'Biểu đồ nhúng TradingView. Khả năng hiển thị và độ trễ phụ thuộc nguồn được chọn. Dữ liệu Forex không nằm trong API SSI của web.'; ?></p>
        <label><?php echo $en?'Currency pair':'Cặp tiền'; ?> <select class="dttl-fx-pair"><option>EURUSD</option><option>GBPUSD</option><option>USDJPY</option><option>AUDUSD</option><option>USDCHF</option><option>USDCAD</option><option>NZDUSD</option></select></label>
        <button type="button" class="dttl-fx-open"><?php echo $en?'Load Forex chart':'Mở biểu đồ Forex'; ?></button>
        <div class="dttl-fx-chart"></div>
        <p><a class="dttl-fx-link" href="https://www.tradingview.com/symbols/EURUSD/" target="_blank" rel="noopener nofollow"><?php echo $en?'View on TradingView if the chart cannot load':'Xem trên TradingView nếu biểu đồ không tải được'; ?></a></p>
        </section>
        <?php echo self::api_guide($en); } return ob_get_clean();
    }
}

