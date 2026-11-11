<?php
defined('ABSPATH') || exit;

/** Long-only AIM simulation. All amounts are integer micro-AIM. */
final class AIM_Stock_Margin_Engine {
    const BASIS_POINTS = 10000;
    const YEAR_SECONDS = 365 * 86400;

    static function open($symbol, $quantity, $quote, $now, $price_limit, $annual_bps) {
        AIM_Stock_Engine::need(is_string($symbol) && preg_match('/^[A-Z]{3}$/D', $symbol), 'Mã cổ phiếu không hợp lệ.');
        AIM_Stock_Engine::need(is_int($quantity) && $quantity >= 100 && $quantity <= 1000000 && $quantity % 100 === 0, 'Khối lượng margin phải là bội số của 100 cổ phiếu.');
        AIM_Stock_Engine::need(is_int($annual_bps) && $annual_bps >= 0 && $annual_bps <= self::BASIS_POINTS, 'Lãi suất mô phỏng không hợp lệ.');
        AIM_Stock_Engine::quote($quote, $symbol, $now);
        AIM_Stock_Engine::need($quote['currency'] === 'VND', 'Margin chỉ áp dụng cho cổ phiếu Việt Nam.');
        self::check_limit($price_limit, (float) $quote['ask'], true);

        $entry_value = AIM_Stock_Engine::money($quantity * $quote['ask'], 'VND');
        AIM_Stock_Engine::need($entry_value >= 2, 'Giá trị vị thế quá nhỏ.');
        $collateral = intdiv($entry_value + 1, 2);
        $loan = $entry_value - $collateral;
        $fee = self::fee($entry_value);

        return array(
            'position' => array(
                'symbol' => $symbol,
                'quantity' => $quantity,
                'entry_price' => (float) $quote['ask'],
                'entry_value' => $entry_value,
                'collateral' => $collateral,
                'loan' => $loan,
                'entry_fee' => $fee,
                'annual_bps' => $annual_bps,
                'opened_at' => intdiv($now, 1000),
            ),
            'cash_delta' => -($collateral + $fee),
            'pool_delta' => $fee,
            'escrow_delta' => $collateral,
        );
    }

    static function mark($position, $bid, $now) {
        AIM_Stock_Engine::need(is_numeric($bid) && is_finite((float) $bid) && $bid > 0, 'Thiếu giá định giá margin.');
        $elapsed = max(0, intdiv($now, 1000) - $position['opened_at']);
        $value = AIM_Stock_Engine::money($position['quantity'] * $bid, 'VND');
        $interest = (int) ceil($position['loan'] * ($position['annual_bps'] / self::BASIS_POINTS) * ($elapsed / self::YEAR_SECONDS));
        $fee = self::fee($value);
        $equity = $value - $position['loan'] - $interest - $fee;
        $returned = max(0, $equity);

        return array(
            'value' => $value,
            'interest' => $interest,
            'exit_fee' => $fee,
            'equity' => $equity,
            'equity_ratio' => $value > 0 ? $equity / $value : 0,
            'returned' => $returned,
            'shortfall' => max(0, -$equity),
            'realized' => $returned - $position['collateral'] - $position['entry_fee'],
        );
    }

    static function close($position, $quote, $now, $price_limit) {
        AIM_Stock_Engine::quote($quote, $position['symbol'], $now);
        AIM_Stock_Engine::need($quote['currency'] === 'VND', 'Giá chốt margin phải bằng VND.');
        self::check_limit($price_limit, (float) $quote['bid'], false);

        $settlement = self::mark($position, (float) $quote['bid'], $now);
        return array(
            'settlement' => $settlement,
            'cash_delta' => $settlement['returned'],
            'pool_delta' => $position['collateral'] - $settlement['returned'],
            'escrow_delta' => -$position['collateral'],
        );
    }

    private static function fee($value) {
        return max(1, intdiv($value + 999, 1000));
    }

    private static function check_limit($limit, $price, $buy) {
        AIM_Stock_Engine::need((is_float($limit) || is_int($limit)) && is_finite((float) $limit) && $limit > 0 && $limit <= 1000000000, 'Thiếu giá bảo vệ hợp lệ.');
        AIM_Stock_Engine::need($buy ? $price <= $limit : $price >= $limit, 'Giá đã vượt mức cho phép.');
    }
}
