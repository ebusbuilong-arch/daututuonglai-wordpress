AIM Trading 0.4.3
Cash equities: Market with 0.5% guard, all-or-none Limit IOC, integer shares, USD1000/AIM, 0.1% fees, holdings/cost/realized PnL/history, atomic counterparty accounting, idempotency and fail-closed Alpaca Paper data adapter. New InnoDB tables migrated on admin_init. Source credentials absent, execution disabled by default. No live trades executed.
Forex conversion v2: all USD notional/margin/commission/PnL divided by 1000. No migration of active legacy positions. Public Forex remains disabled; commodity/VN execution not implemented.
Validation: 35 stock engine checks, 17 stock persistence checks plus 665 existing regression checks, 106 Forex engine checks, 45 Forex feed checks. SQLite rollback tests are not a native MySQL concurrency test.
