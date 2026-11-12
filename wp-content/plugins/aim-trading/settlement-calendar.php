<?php
defined('ABSPATH') || exit;

final class AIM_VN_Stock_Settlement {
    private const HOLIDAYS = array(
        '2026-01-02',
        '2026-02-16', '2026-02-17', '2026-02-18', '2026-02-19', '2026-02-20',
        '2026-04-27', '2026-08-31', '2026-09-01',
        '2027-02-04', '2027-02-05', '2027-02-08', '2027-02-09', '2027-02-10',
    );

    static function trade_day(int $now): string {
        return (new DateTimeImmutable('@' . intdiv($now, 1000)))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d');
    }

    private static function is_business_day(DateTimeImmutable $day): bool {
        if ((int) $day->format('N') >= 6) return false;
        $date = $day->format('Y-m-d');
        $fixed = array('01-01', '04-30', '05-01', '09-02');
        if (in_array($day->format('m-d'), $fixed, true)) return false;
        $holidays = apply_filters('aim_vn_stock_settlement_holidays', self::HOLIDAYS, (int) $day->format('Y'));
        return !in_array($date, (array) $holidays, true);
    }

    static function sellable_at(string $trade_day): int {
        static $calendar = array();
        if (isset($calendar[$trade_day])) return $calendar[$trade_day];
        $day = new DateTimeImmutable($trade_day . ' 13:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
        for ($remaining = 2; $remaining > 0;) {
            $day = $day->modify('+1 day');
            if (self::is_business_day($day)) $remaining--;
        }
        return $calendar[$trade_day] = $day->getTimestamp() * 1000;
    }

    static function pending(array $position, int $now): array {
        $pending = array();
        foreach ($position['settlement_lots'] ?? array() as $trade_day => $quantity) {
            if (self::sellable_at($trade_day) > $now) $pending[$trade_day] = $quantity;
        }
        ksort($pending);
        return $pending;
    }

    static function public_schedule(array $position, int $now): array {
        $schedule = array();
        foreach (self::pending($position, $now) as $trade_day => $quantity) {
            $schedule[] = array('quantity' => $quantity, 'sellable_at' => self::sellable_at($trade_day));
        }
        return $schedule;
    }

    static function format_time(int $timestamp): string {
        return (new DateTimeImmutable('@' . intdiv($timestamp, 1000)))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('H:i d/m/Y');
    }
}
