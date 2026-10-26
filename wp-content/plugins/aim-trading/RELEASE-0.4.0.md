# AIM Trading 0.4.0 — persisted Forex administrator pilot

Adds two InnoDB tables: forex account state/revision/active flag and an idempotent
event ledger. Administrator-only REST routes and panel support transferring AIM
from/to Spot, market entry, partial/full exit, TP/SL changes and reading history.
Fixed-fixture verification remains separate and never funds this account.

Transactions lock pool → Spot account → Forex state, check revision, then update
state, cash and event/transfer ledgers together. Duplicate request IDs return the
original result, including during a feed outage. Changed payloads are rejected.
Pool payouts cannot make pool cash negative. Transfers out use only free isolated
cash; collateral cannot be withdrawn. New exposure needs a healthy worker and
at least 100,000 AIM pool reserve. Automatic old exits take priority over new entry.

The existing CLI worker now runs Forex after the other engines. Missing Forex
configuration does not stop Crypto. Forex processes one active account per pass,
using a rotating cursor and advisory lock. Freshness is checked after SQL locks.
Waiting exits retry on later ticks. History excludes empty worker events.

## Activation boundary

Public trading remains OFF; routes and controls are administrator-only.
Default AIM_FOREX_ADMIN_PILOT is false. No live balances were changed for this
release. The owner must configure Practice source credentials and verify live
quotes before deliberately enabling the pilot constant in wp-config.php:

```php
define('AIM_FOREX_ADMIN_PILOT', true);
```

Do not enable for the public yet: overnight swap is not implemented, commercial
redistribution rights are not confirmed, and native MySQL concurrency/load tests
and full live-source trade round trips remain outstanding. Account previews show
stored entries and realized PnL, not live unrealized PnL. Limit orders and broader
currency conversion are not included. Reuse the explicit AIM simulation policy
from RELEASE-0.3.9.md; it is not an assertion of OANDA broker trading conditions.

## Validation

25 persistence/transaction cases added to the existing 640-check suite, plus
106 position-engine and 45 feed/preview cases: 816 checks total. Persistence tests
use SQLite with an isolated wpdb stand-in; they verify rollback/replay/account
isolation and conservation, not native MySQL lock behavior. Browser validation
checks migration readiness, disabled pilot controls and existing Crypto worker.

## Next market data task

VN equities: the currently embedded TradingView HNX widgets are EOD. HOSE
symbols do not render through the current embedding. SSI FastConnect officially
documents market-data and intraday candles; integration requires account API
access and confirmation of rights for public AIM display. No SSI connection or
permission has been configured in this release.
