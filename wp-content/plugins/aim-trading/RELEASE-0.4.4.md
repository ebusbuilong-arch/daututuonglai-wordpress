# AIM Trading 0.4.4 — Commodity CFD admin pilot

Adds isolated commodity accounting, OANDA Practice read-only adapter, admin controls and worker integration for XAU_USD, XAG_USD, WTICO_USD and BCO_USD. Symbols are candidates; actual availability is discovered from the configured account before pricing requests. No broker orders are sent.

Simulation rules: 1 AIM = USD 1,000 buying power; gold lot = 10 ounces, silver = 100 ounces, oil = 100 barrels. Fees = 0.1% actual executed notional per side, rounded up to micro-AIM. Leverage 1–20, limited further by source margin rate. Separate isolated margin per position, stopout at 50% initial margin. Gaps beyond margin recorded as admin subsidy; no free-cash debt. These are AIM rules, not claims of standard exchange contracts.

Market long/short, partial/full close, TP/SL changes, automatic exits and pending exits on insufficient depth. Transactional spot transfers and admin counterparty accounting; unique request IDs prevent repeated payment. Existing Crypto reservations remain protected. Missing/stale/untradeable quotes do not execute. Adapter never reads TradingView widget prices.

Admin-only and disabled by default. Configure AIM_OANDA_PRACTICE_ACCOUNT and AIM_OANDA_PRACTICE_TOKEN privately on the server; account must support the commodities. Open WordPress admin to create two InnoDB tables, verify feed in Hàng hóa, then enable AIM_COMMODITY_ADMIN_PILOT only for acceptance testing. Existing worker runs commodity ticks without changing systemd configuration. Disabling pilot blocks deposits/new positions but permits free-cash withdrawal and exits.

Not implemented: public commodity trading, live source acceptance (credentials missing), financing/roll adjustments, dated futures contracts or physical delivery, pending limit orders. Charts and execution feeds can differ. Agricultural futures remain observation-only. Real MySQL concurrent-lock acceptance remains required; local DB tests use SQLite adapter.

Validation: 175 commodity engine assertions, 48 adapter assertions, 25 commodity persistence assertions plus 640 existing checks; stock persistence suite 682 checks including 25 Forex; Forex engine 106, stock engine 35, Forex feed 45. Syntax checked across PHP and new JS. Tests use deterministic fixtures, never live wallets.

References: https://developer.oanda.com/rest-live-v20/primitives-df/ and https://developer.oanda.com/rest-live-v20/account-ep/ (instrument types, precision, margin, account-specific availability).
