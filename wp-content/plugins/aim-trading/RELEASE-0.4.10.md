# AIM Trading 0.4.10
CommodityPriceAPI WTI/Brent Bid/Ask now available for explicitly enabled AIM simulation trading.
Response time and receipt age are used for a simulation availability window; market quote time, latency and market session remain unknown and are not represented as verified.
Shared 60-second cache; response max 120 seconds, receipt max 90 seconds. No fabricated fallback price. Existing atomic accounting and source matching remain enforced.
TradingView remains a separate reference chart; no historical CommodityPriceAPI series is supplied.
Validated oil Long/Short round trips, expired quote rejection, AIM conservation, persisted WTI/Brent opens/closes, replay protection, reference-feed regression and permissions.
