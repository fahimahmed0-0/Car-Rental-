<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Single source of truth for rental day calculation, token discounts, and totals.
 */
final class PricingService
{
    public const TOKENS_PER_UNIT = 5;
    public const DISCOUNT_PER_UNIT_INR = 100.0;
    public const MIN_REDEEM_TOKENS = 15;
    public const TOKENS_EARNED_PER_TRIP = 5;

    /**
     * Parses Y-m-d dates and computes inclusive calendar rental days (From == To => 1 day).
     */
    public static function calculateDays(string $fromDate, string $toDate, bool $enforceFutureStart = true): int
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', trim($fromDate));
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', trim($toDate));

        if ($from === false || $to === false) {
            throw new InvalidArgumentException('Please enter valid pickup and drop-off dates (YYYY-MM-DD).');
        }

        if ($enforceFutureStart) {
            $today = new DateTimeImmutable('today');
            if ($from < $today) {
                throw new InvalidArgumentException('Pickup date cannot be in the past.');
            }
        }

        if ($to < $from) {
            throw new InvalidArgumentException('Drop-off date cannot be before pickup date.');
        }

        return (int) $from->diff($to)->days + 1;
    }

    /**
     * Computes server-authoritative pricing breakdown and validates token redemption rules.
     *
     * @return array{days:int, daily_rate:float, base_amount:float, tokens_used:int, discount_amount:float, tax_amount:float, final_amount:float}
     */
    public static function calculateQuote(
        float $pricePerDay,
        string $fromDate,
        string $toDate,
        int $tokensRequested = 0,
        int $availableTokenBalance = 0,
        bool $enforceFutureStart = true
    ): array {
        if ($pricePerDay <= 0) {
            throw new InvalidArgumentException('Vehicle daily rate is invalid.');
        }

        $days = self::calculateDays($fromDate, $toDate, $enforceFutureStart);
        $baseAmount = round($pricePerDay * $days, 2);

        if ($tokensRequested < 0) {
            throw new InvalidArgumentException('Invalid token count.');
        }

        $tokensUsed = 0;
        $discountAmount = 0.0;

        if ($tokensRequested > 0) {
            if ($tokensRequested < self::MIN_REDEEM_TOKENS) {
                throw new InvalidArgumentException('Minimum ' . self::MIN_REDEEM_TOKENS . ' tokens required for redemption.');
            }
            if ($tokensRequested % self::TOKENS_PER_UNIT !== 0) {
                throw new InvalidArgumentException('Tokens must be redeemed in multiples of ' . self::TOKENS_PER_UNIT . '.');
            }
            if ($tokensRequested > $availableTokenBalance) {
                throw new InvalidArgumentException('Insufficient token balance.');
            }

            $rawDiscount = ($tokensRequested / self::TOKENS_PER_UNIT) * self::DISCOUNT_PER_UNIT_INR;
            $discountAmount = min($baseAmount, round($rawDiscount, 2));
            $tokensUsed = $tokensRequested;
        }

        $taxAmount = 0.0;
        $finalAmount = max(0.0, round($baseAmount - $discountAmount + $taxAmount, 2));

        return [
            'days' => $days,
            'daily_rate' => round($pricePerDay, 2),
            'base_amount' => $baseAmount,
            'tokens_used' => $tokensUsed,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'final_amount' => $finalAmount,
        ];
    }
}
