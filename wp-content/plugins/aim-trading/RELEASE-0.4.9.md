# AIM Trading 0.4.9

Adds server-only CommodityPriceAPI oil reference connection using AIM_COMMODITY_PRICE_API_KEY. Administrator commodity page has an explicit WTI/Brent connection check. No automatic polling; shared 60-second cache and backoff. Exact SPOT symbols, USD/Bbl metadata, response age, numeric prices and Bid/Ask order validated. Secrets and provider error bodies never returned.

This feed is deliberately NOT connected to order execution. Provider timestamp documents response generation, not quote time; there is no documented market-open flag. Response age cannot establish market freshness. No time/open/tradeable fields are emitted. Existing Twelve Data execution remains unchanged.

Source specification: https://commoditypriceapi.com/docs and https://commoditypriceapi.com/oil-price-api inspected 2026-10-22. To enable oil fills later, verify price freshness and session state, source pinning for existing positions, and test accounting before activation.
