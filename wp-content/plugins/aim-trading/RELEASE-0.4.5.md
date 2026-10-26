# AIM Trading 0.4.5 — Latest-price reference feed

Adds a server-only Twelve Data adapter and admin chart acceptance panel on stock, Forex and commodity pages. The reference feed supplies latest price + a disclosed synthetic spread (0.02% total Forex, 0.1% stocks/commodities). Synthetic liquidity belongs to the AIM admin, not an external order book. No broker orders are sent.

Quotes cache for 60 seconds across users; source last_quote_at must be no older than 180 seconds and fetch receipt no older than 90 seconds. Provider market-open flag required; daily-bar timestamp is never substituted for latest-quote time. Original Alpaca/OANDA freshness remains strict. Retry IDs and atomic accounting unchanged. Forex/commodity entries pin their source; do not change feed while positions remain open. Rate limiting blocks requests for 60 seconds and never falls back to old prices.

Chart panel: 120 close-price points at one minute from the same provider; append the latest reference point. Poll only when admin presses Start, every 60 seconds; Stop cancels requests. Existing TradingView widgets remain separately labelled references until live-source acceptance. The new panel is admin-only and is not yet the public primary chart.

## Server setup
Before WordPress bootstrap in wp-config.php, privately set:

    define('AIM_TWELVE_DATA_KEY', 'YOUR_SERVER_SIDE_KEY');

First use “Giá tham chiếu · Kiểm tra nguồn mới” to verify each requested symbol, timestamps, session flags and chart data. Key alone permits admin source diagnostics without switching execution. No key is stored in the browser, ZIP, or API response. The provider requires a key and appropriate symbol/usage entitlements; free access to all instruments is not promised. If last_quote_at or session flags are missing, that instrument fails closed pending adapter verification.

Only after live verification and closing old positions:

    define('AIM_REFERENCE_FEED', 'twelvedata');

Existing trading enable flags still apply: AIM_FOREX_ADMIN_PILOT, AIM_COMMODITY_ADMIN_PILOT, AIM_STOCK_TRADING_ENABLED. Public chart/quote agreement and data-use acceptance must precede public activation. No flags or credentials were added on the live VPS by this release. No wallet transactions used for verification.

## Tests
31 new adapter/engine checks + 8 persisted-account checks passed, including expiry, synthetic spread, closed session, wrong symbol/currency, per-credential rate backoff, source pinning, idempotence and admin pool conservation. Existing stock, Forex, commodity, wallet/account regression suites passed. SQLite adapter used for persistence tests; actual concurrent MySQL acceptance and live source still pending.

## References
https://twelvedata.com/docs
https://twelvedata.com/commodities
https://twelvedata.com/markets/382560/commodity/wti-usd
https://twelvedata.com/markets/874865/commodity/xbr-usd
