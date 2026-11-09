<?php
// Offline regression harness: WordPress storage and SSI transport are fixtures.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    public $code; private $message; private $data;
    function __construct($code, $message, $data) { $this->code = $code; $this->message = $message; $this->data = $data; }
    function get_error_data() { return $this->data; }
    function get_error_message() { return $this->message; }
}
class WP_REST_Request {
    private $params = [];
    function __construct($method) {}
    function set_param($key, $value) { $this->params[$key] = $value; }
    function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
    private $data;
    function __construct($data) { $this->data = $data; }
    function get_data() { return $this->data; }
}
class DTTL_SSI_Data {
    const OPTION = 'fixture_credentials'; const URL = 'https://fixture.invalid/token';
    static function credentials() { return ['apiKey' => 'fixture', 'apiSecret' => 'not-a-secret']; }
    static function encrypt($value) {
        $iv = str_repeat('a', 12); $tag = '';
        $cipher = openssl_encrypt(json_encode($value), 'aes-256-gcm', hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return new WP_REST_Response($value); }
function wp_salt($kind) { return 'fixture-' . $kind; }
function wp_json_encode($value) { return json_encode($value); }
function sanitize_text_field($value) { return strip_tags($value); }
function get_option($key, $default = null) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function get_transient($key) {
    $row = $GLOBALS['transients'][$key] ?? null;
    return $row && $row['expires'] > $GLOBALS['fixture_now']->getTimestamp() ? $row['value'] : false;
}
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = ['value' => $value, 'ttl' => $ttl, 'expires' => $GLOBALS['fixture_now']->getTimestamp() + $ttl]; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_remote_post($url, $args) { $GLOBALS['auth_calls']++; return ['code' => 200, 'body' => json_encode(['accessToken' => 'fixture-token'])]; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function candle($date, $price = 100) { return ['tradingDate' => $date, 'open' => $price, 'high' => $price + 2, 'low' => $price - 1, 'close' => $price + 1, 'volume' => 100]; }
function wp_remote_get($url, $args) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $path = basename(parse_url($url, PHP_URL_PATH));
    $GLOBALS['calls'][] = [$path, $query];
    if ($GLOBALS['http_code'] !== 200) { return ['code' => $GLOBALS['http_code'], 'body' => '{}']; }
    if ($GLOBALS['transport']) { return ($GLOBALS['transport'])($path, $query); }
    if ($path === 'securitiesByBoard') { $data = ['data' => [['symbol' => 'FPT', 'symbolNameVi' => 'Fixture FPT', 'symbolNameEn' => 'Fixture FPT']]]; }
    else {
        $now = $GLOBALS['fixture_now'];
        $date = $query['timeFrame'] === '1d' ? ($GLOBALS['daily_current'] ? $now : $now->modify('-1 day'))->format('Y-m-d') : $now->format('Y-m-d H:i:s');
        if ($query['timeFrame'] === '1d' && $GLOBALS['daily_stamp']) { $date .= ' ' . $GLOBALS['daily_stamp']; }
        $data = ['data' => $GLOBALS['empty_bars'] ? [] : [candle($date, $query['timeFrame'] === '1d' ? 100 : $GLOBALS['minute_price'])], 'pageSize' => 200];
    }
    return ['code' => 200, 'body' => json_encode($data)];
}
require __DIR__ . '/../wp-content/plugins/dttl-ssi-data/charts.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function reset_fixture() {
    $GLOBALS['transients'] = []; $GLOBALS['options'] = [DTTL_SSI_Data::OPTION => 'fixture-namespace'];
    $GLOBALS['calls'] = []; $GLOBALS['auth_calls'] = 0; $GLOBALS['http_code'] = 200; $GLOBALS['empty_bars'] = false;
    $GLOBALS['fixture_now'] = new DateTimeImmutable('2026-11-09 10:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $GLOBALS['daily_current'] = false; $GLOBALS['daily_stamp'] = ''; $GLOBALS['minute_price'] = 200; $GLOBALS['transport'] = null;
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
}
function key_for($frame = '1m', $board = 'HOSE') { return 'dttl_ssi_bars_031_' . substr(hash('sha256', get_option(DTTL_SSI_Data::OPTION)), 0, 12) . '_' . $board . '_FPT_' . $frame; }
function request_chart($frame = '1m', $symbol = 'FPT', $board = 'HOSE') {
    $request = new WP_REST_Request('GET');
    foreach (['symbol' => $symbol, 'board' => $board, 'frame' => $frame] as $key => $value) { $request->set_param($key, $value); }
    return DTTL_SSI_Charts::history_route($request, $GLOBALS['fixture_now']);
}
function private_call($name, ...$args) { $method = new ReflectionMethod(DTTL_SSI_Charts::class, $name); $method->setAccessible(true); return $method->invoke(null, ...$args); }
function exhaust_day() { set_transient('dttl_ssi_budget_day_' . floor(time() / DAY_IN_SECONDS), 1500, DAY_IN_SECONDS); }
function test($name, $fn) { reset_fixture(); $fn(); echo "PASS: $name\n"; }

test('fresh cache is shared and retains original timestamps', function() {
    $first = request_chart()->get_data(); $calls = count($GLOBALS['calls']);
    $_SERVER['REMOTE_ADDR'] = '192.0.2.2';
    $second = request_chart()->get_data();
    check($first === $second && count($GLOBALS['calls']) === $calls, 'Fresh cache caused another upstream request');
    check($first['stale'] === false && count($first['bars']) === 1, 'Valid bars not returned');
    check(get_transient(key_for() . '_lastgood')['fetchedAt'] === $first['fetchedAt'], 'Retained timestamp changed');
    check($GLOBALS['transients'][key_for() . '_lastgood']['ttl'] === 30 * DAY_IN_SECONDS, 'Retention must be bounded');
});
test('day quota returns timestamped fallback without spending minute budget', function() {
    $original = request_chart()->get_data(); delete_transient(key_for()); exhaust_day();
    $minute_key = 'dttl_ssi_budget_minute_' . floor(time() / 60); $minute_count = get_transient($minute_key); $calls = count($GLOBALS['calls']);
    $fallback = request_chart()->get_data();
    check($fallback['stale'] && $fallback['staleReason'] === 'rate_limit', 'Fallback not labeled stale');
    check($fallback['fetchedAt'] === $original['fetchedAt'] && $fallback['bars'] === $original['bars'], 'Old data was relabeled or modified');
    check($fallback['pollSeconds'] >= 60 && count($GLOBALS['calls']) === $calls, 'Blocked quota still fetched');
    check(get_transient($minute_key) === $minute_count, 'Day-denied request spent minute budget');
    request_chart(); check(count($GLOBALS['calls']) === $calls, 'Backoff still called upstream');
    $quote = DTTL_SSI_Charts::market_data('ohlc', ['symbol' => 'FPT']);
    check(is_wp_error($quote) && $quote->get_error_data()['status'] === 429, 'Order-quote path must not use stale fallback');
});
test('cold cache returns real quota error rather than fabricated data', function() {
    exhaust_day(); $error = request_chart();
    check(is_wp_error($error) && $error->get_error_data()['limit'] === 'day', 'Missing day diagnostic');
    check(empty($GLOBALS['calls']) && $GLOBALS['auth_calls'] === 0, 'Exhausted budget called SSI');
});
test('minute and upstream limits are distinct and caps are unchanged', function() {
    set_transient('dttl_ssi_budget_minute_' . floor(time()/60), 60, 60);
    $error = private_call('budget'); check($error->get_error_data()['limit'] === 'minute', 'Wrong minute limit');
    delete_transient('dttl_ssi_budget_minute_' . floor(time()/60));
    set_transient('dttl_ssi_budget_day_' . floor(time()/DAY_IN_SECONDS), 1499, DAY_IN_SECONDS);
    check(private_call('budget') === true, 'Last allowed request rejected');
    check(private_call('budget')->get_error_data()['limit'] === 'day', 'Daily cap was increased');
});
test('upstream failure preserves bars and recovers after backoff', function() {
    $good = request_chart()->get_data(); delete_transient(key_for()); $GLOBALS['http_code'] = 429;
    $fallback = request_chart()->get_data();
    check($fallback['stale'] && $fallback['fetchedAt'] === $good['fetchedAt'], 'Upstream fallback lost source timestamp');
    $GLOBALS['http_code'] = 200; delete_transient(key_for() . '_retry'); delete_transient('dttl_ssi_pause');
    $recovered = request_chart()->get_data(); check(!$recovered['stale'], 'Recovery remains stale');
});
test('empty responses do not erase last-good history', function() {
    $good = request_chart()->get_data(); delete_transient(key_for()); $GLOBALS['empty_bars'] = true;
    $fallback = request_chart()->get_data(); check($fallback['stale'] && $fallback['bars'] === $good['bars'], 'Empty response erased history');
});
test('cached daily history avoids repeated year-history requests', function() {
    $first = request_chart('1d'); check(!is_wp_error($first), 'First daily load failed');
    delete_transient(key_for('1d'));
    $before = count(array_filter($GLOBALS['calls'], function($c) { return ($c[1]['timeFrame'] ?? '') === '1d'; }));
    request_chart('1d');
    $after = count(array_filter($GLOBALS['calls'], function($c) { return ($c[1]['timeFrame'] ?? '') === '1d'; }));
    check($before === 1 && $after === $before, 'Daily history fetched again');
});
test('daily candles do not regress after lunch or close', function() {
    foreach ([['11:34', '11:39'], ['15:14', '15:19']] as $times) {
        reset_fixture(); $GLOBALS['daily_current'] = true;
        $GLOBALS['fixture_now'] = new DateTimeImmutable('2026-11-09 ' . $times[0], new DateTimeZone('Asia/Ho_Chi_Minh'));
        $before = request_chart('1d')->get_data();
        check(end($before['bars'])['close'] === 201.0, 'Missing intraday-derived price');
        check($GLOBALS['transients'][key_for('1d') . '_daily_history']['ttl'] === 60, 'Daily history outlives session boundary');
        $GLOBALS['fixture_now'] = new DateTimeImmutable('2026-11-09 ' . $times[1], new DateTimeZone('Asia/Ho_Chi_Minh'));
        $GLOBALS['minute_price'] = 220;
        $after = request_chart('1d')->get_data();
        check(end($after['bars'])['close'] === 221.0 && !$after['stale'], 'Daily close regressed to older SSI daily bar');
        check(end($after['bars'])['volume'] >= end($before['bars'])['volume'], 'Daily volume regressed at boundary');
        check($after['fetchedAt'] !== $before['fetchedAt'], 'Updated candle retained incorrect fetch time');
    }
});
test('daily fallback prefers saved minute candle over lagging daily candle', function() {
    $GLOBALS['daily_current'] = true; $GLOBALS['daily_stamp'] = '14:00:00';
    $GLOBALS['fixture_now'] = new DateTimeImmutable('2026-11-09 14:14:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $before = request_chart('1d')->get_data();
    delete_transient(key_for('1d')); delete_transient(key_for('1m')); $GLOBALS['http_code'] = 503;
    $after = request_chart('1d')->get_data();
    check($after['stale'] && $after['bars'] === $before['bars'] && $after['fetchedAt'] === $before['fetchedAt'], 'Fallback regressed the provisional candle');
});
test('paginated bars deduplicate repeated pages and preserve fallback on partial failure', function() {
    $symbols = [['symbol' => 'FPT', 'name' => 'Fixture', 'nameEn' => 'Fixture', 'board' => 'HOSE']];
    set_transient('dttl_ssi_symbols_' . substr(hash('sha256', get_option(DTTL_SSI_Data::OPTION)), 0, 12) . '_HOSE', $symbols, HOUR_IN_SECONDS);
    $GLOBALS['transport'] = function($path, $query) {
        $page = (int) $query['pageIndex'];
        $rows = $page === 1 ? [candle('2026-11-09 09:00:00'), candle('2026-11-09 09:01:00')] : [candle('2026-11-09 09:02:00')];
        return ['code' => 200, 'body' => json_encode(['data' => $rows, 'pageSize' => 2])];
    };
    $good = request_chart()->get_data(); check(count($good['bars']) === 3, 'Second page omitted');
    delete_transient(key_for());
    $GLOBALS['transport'] = function($path, $query) { return ['code' => 200, 'body' => json_encode(['data' => [candle('2026-11-09 09:00:00'), candle('2026-11-09 09:01:00')], 'pageSize' => 2])]; };
    $count = count($GLOBALS['calls']); $duplicate = request_chart()->get_data();
    check($duplicate['stale'] && $duplicate['bars'] === $good['bars'] && count($GLOBALS['calls']) - $count === 2, 'Repeated page must stop and must not regress the last candle');
    delete_transient(key_for()); delete_transient(key_for() . '_retry');
    $GLOBALS['transport'] = function($path, $query) {
        return (int) $query['pageIndex'] === 2 ? ['code' => 503, 'body' => '{}'] : ['code' => 200, 'body' => json_encode(['data' => [candle('2026-11-09 09:00:00'), candle('2026-11-09 09:01:00')], 'pageSize' => 2])];
    };
    $partial = request_chart()->get_data();
    check($partial['stale'] && $partial['bars'] === $duplicate['bars'], 'Failed page replaced retained history with partial result');
});
test('invalid parameters, boards and credential namespaces never borrow fallback', function() {
    request_chart(); exhaust_day();
    check(request_chart('1m', '<bad>')->get_error_data()['status'] === 400, 'Invalid symbol accepted');
    check(is_wp_error(request_chart('1m', 'FPT', 'HNX')), 'Fallback crossed exchanges');
    $GLOBALS['options'][DTTL_SSI_Data::OPTION] = 'other-namespace';
    check(is_wp_error(request_chart()), 'Fallback crossed credential namespace');
});
test('market-aware cache policy respects lunch, weekends and next opening', function() {
    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    foreach ([['2026-11-09 10:00', '1d', 300], ['2026-11-09 10:00', '1m', 60], ['2026-11-09 12:00', '1d', 3600], ['2026-11-09 08:58', '1m', 120], ['2026-11-13 16:00', '1d', 21600]] as $case) {
        check(private_call('cache_seconds', new DateTimeImmutable($case[0], $tz), $case[1]) === $case[2], 'Unexpected cache interval at ' . $case[0]);
    }
});
echo "All offline SSI cache regressions passed.\n";
