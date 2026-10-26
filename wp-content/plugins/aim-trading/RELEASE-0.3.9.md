# AIM Trading 0.3.9

## Delivered

- Pure isolated Forex position engine for the four USD-quoted Practice pairs.
- Long/Short entry, partial/full close, TP/SL amendments, stop-market exits.
- Close-side Bid/Ask valuation; executable depth and volume-weighted fills.
- Shared observed depth consumed once across all exits in an evaluation pass.
- Stale, out-of-order, unavailable and closed-market quotes pause processing.
- Automatic exits latch and wait when full depth is unavailable; no invented fills.
- Ledger conservation: available cash + reserved margin + cumulative pool delta
  equals starting cash. Gaps beyond isolated margin record a system subsidy.
- Administrator-only deterministic verification endpoint and table with six cases.
- UI release labels and asset versions now derive from the plugin version.

## Simulation policy (not broker specifications)

Policy forex-isolated-v1: 1 USD notional = 1 AIM for calculations, commission
3.5 AIM per standard lot per side; maintenance 50% of initial isolated margin
after estimated closing commission. Negative isolated balance is protected;
shortfall belongs to the simulator's opposing pool. TP and SL trigger market
exits at observed closing-side depth, not guaranteed trigger prices.
Each administrative verification case starts independently with 1,000 test AIM.

## Deployment boundary

This version DOES NOT enable public Forex orders or mutate balances/wallets.
The engine returns state/events but has no database or external order access.
The verification endpoint uses fixed fixtures, not live prices, and is restricted
to manage_options. The OANDA adapter remains read-only and admin-only.

Still required before public Forex trading:
1. Configure owner-controlled OANDA Practice credentials on the VPS and verify
   current instrument metadata, fresh quotes and data distribution rights.
2. Implement transactional persistence, request deduplication, pool solvency
   enforcement, account funding and worker integration for Forex state/events.
3. Specify and implement overnight fees/rollover and reconcile real feed behavior.
4. End-to-end test fills, exits, restart recovery and account/pool conservation.

Crypto trading is unchanged. Stocks and commodities remain observation screens.
Lessons remain under Luyện tập. No cron, preload, theme or wallet configuration
is changed by this release.

## Validation

106 Forex lifecycle checks, 45 Forex source/preview checks and existing 640-check
regression suite. Browser verification required after installation.
