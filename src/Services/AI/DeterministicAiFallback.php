<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\BookingService;
use App\Services\PricingService;
use PDO;

/**
 * Tier 3 zero-dependency deterministic AI fallback engine.
 * Guarantees 100% availability offline or when Cloud/Local LLMs are unconfigured.
 */
final class DeterministicAiFallback
{
    public static function parseSearchConstraints(string $query, array $brandRows): array
    {
        $q = strtolower(trim($query));
        $constraints = [
            'seats' => null,
            'fuel' => null,
            'transmission' => null,
            'max_price' => null,
            'brand_id' => null,
            'brand_name' => null,
            'keywords' => [],
            'terrain' => null,
        ];

        // Extract seating capacity ("7 seater", "5 people", "family of 6")
        if (preg_match('/\b([4-9]|1[0-2])\s*(?:seater|seats?|people|persons?|pax)\b/i', $q, $m)) {
            $constraints['seats'] = (int) $m[1];
        } elseif (str_contains($q, 'family') || str_contains($q, 'group') || str_contains($q, 'big')) {
            $constraints['seats'] = 6;
        }

        // Fuel types
        foreach (['diesel' => 'Diesel', 'petrol' => 'Petrol', 'cng' => 'CNG', 'electric' => 'Electric', 'ev' => 'Electric'] as $kw => $val) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $q)) {
                $constraints['fuel'] = $val;
                break;
            }
        }

        // Transmission
        if (preg_match('/\b(automatic|auto|amt|cvt)\b/i', $q)) {
            $constraints['transmission'] = 'Automatic';
        } elseif (preg_match('/\bmanual\b/i', $q)) {
            $constraints['transmission'] = 'Manual';
        }

        // Budget ("under 3000", "below ₹4,500", "max 2500", "budget 2000")
        if (preg_match('/(?:under|below|max|budget|within|less\s+than)\s*(?:rs\.?|inr|₹)?\s*([0-9][0-9,]{2,5})/i', $q, $m)) {
            $constraints['max_price'] = (int) str_replace(',', '', $m[1]);
        }

        // Terrain / destination context
        if (preg_match('/\b(shillong|tawang|cherrapunji|hill|hills|mountain|suv|4x4)\b/i', $q)) {
            $constraints['terrain'] = 'hills';
        } elseif (preg_match('/\b(city|commute|budget|cheap|economical)\b/i', $q)) {
            $constraints['terrain'] = 'city';
        }

        // Brand match
        foreach ($brandRows as $b) {
            $bName = trim((string) $b->BrandName);
            if ($bName !== '' && str_contains($q, strtolower($bName))) {
                $constraints['brand_id'] = (int) $b->id;
                $constraints['brand_name'] = $bName;
                break;
            }
        }

        return $constraints;
    }

    public static function scoreAndRankVehicles(array $vehicles, array $constraints, string $rawQuery): array
    {
        $qLower = strtolower($rawQuery);
        $scored = [];

        foreach ($vehicles as $v) {
            $score = 50;
            $reasons = [];

            if (isset($v->is_available) && (int) $v->is_available === 0) {
                $score -= 40;
            } else {
                $reasons[] = 'Available';
            }

            if ($constraints['brand_id'] !== null && (int) $v->VehiclesBrand === (int) $constraints['brand_id']) {
                $score += 25;
                $reasons[] = $v->BrandName . ' match';
            }

            if ($constraints['fuel'] !== null && strcasecmp((string) $v->FuelType, (string) $constraints['fuel']) === 0) {
                $score += 20;
                $reasons[] = $v->FuelType;
            }

            if ($constraints['transmission'] !== null && strcasecmp((string) ($v->Transmission ?? 'Manual'), (string) $constraints['transmission']) === 0) {
                $score += 18;
                $reasons[] = $v->Transmission;
            }

            if ($constraints['seats'] !== null) {
                if ((int) $v->SeatingCapacity >= (int) $constraints['seats']) {
                    $score += 22;
                    $reasons[] = $v->SeatingCapacity . '-seater capacity';
                } else {
                    $score -= 25;
                }
            }

            if ($constraints['max_price'] !== null) {
                if ((int) $v->PricePerDay <= (int) $constraints['max_price']) {
                    $score += 20;
                    $reasons[] = 'Within ₹' . number_format((float) $constraints['max_price']) . '/day budget';
                } else {
                    $score -= 30;
                }
            }

            if ($constraints['terrain'] === 'hills' && ((int) $v->SeatingCapacity >= 5 || strcasecmp((string) $v->FuelType, 'Diesel') === 0)) {
                $score += 12;
                $reasons[] = 'Strong hill/highway capability';
            } elseif ($constraints['terrain'] === 'city' && (int) $v->PricePerDay <= 2200) {
                $score += 12;
                $reasons[] = 'Economical city rate';
            }

            if ($qLower !== '' && str_contains(strtolower((string) $v->VehiclesTitle . ' ' . $v->VehiclesOverview), $qLower)) {
                $score += 15;
            }

            $v->ai_score = $score;
            $v->ai_reason = !empty($reasons)
                ? 'Match: ' . implode(' • ', array_slice($reasons, 0, 3))
                : 'Verified DriveX fleet option';
            $scored[] = $v;
        }

        usort($scored, static fn (object $a, object $b): int => ($b->ai_score <=> $a->ai_score) ?: ((int) $a->PricePerDay <=> (int) $b->PricePerDay));

        return $scored;
    }

    public static function optimizeBooking(object $vehicle, int $days, int $tokenBalance): string
    {
        $days = max(1, $days);
        $base = (float) $vehicle->PricePerDay * $days;
        $maxUsable = (int) floor($tokenBalance / PricingService::TOKENS_PER_UNIT) * PricingService::TOKENS_PER_UNIT;

        if ($tokenBalance >= PricingService::MIN_REDEEM_TOKENS) {
            // Cap tokens at base amount so no value is wasted
            $neededToCover = (int) ceil($base / PricingService::DISCOUNT_PER_UNIT_INR) * PricingService::TOKENS_PER_UNIT;
            $recommended = min($maxUsable, $neededToCover);
            if ($recommended < PricingService::MIN_REDEEM_TOKENS) {
                $recommended = PricingService::MIN_REDEEM_TOKENS;
            }
            $disc = min($base, ($recommended / PricingService::TOKENS_PER_UNIT) * PricingService::DISCOUNT_PER_UNIT_INR);
            $tokenMsg = "Recommended: Redeem {$recommended} tokens to save ₹" . number_format($disc) . " on this {$days}-day trip (and earn +5 tokens upon completion).";
        } else {
            $needed = PricingService::MIN_REDEEM_TOKENS - $tokenBalance;
            $tokenMsg = "You have {$tokenBalance} tokens ({$needed} more needed to unlock ₹300+ off). Completing this trip earns +5 tokens.";
        }

        $fuel = (string) $vehicle->FuelType;
        $routeTip = strcasecmp($fuel, 'Diesel') === 0
            ? 'Diesel torque makes this vehicle well-suited for Meghalaya/Kaziranga highway inclines.'
            : 'Ideal for smooth Guwahati city driving and weekend Assam highway trips.';

        return $tokenMsg . ' ' . $routeTip;
    }

    public static function answerPolicyQuestion(string $question, PDO $dbh): string
    {
        $q = strtolower(trim($question));

        if (preg_match('/\b(token|discount|loyalty|redeem|earn|points?)\b/i', $q)) {
            return 'DriveX Loyalty Tokens: You earn 5 tokens automatically whenever an admin marks your completed trip. Once you hold at least 15 tokens, you can redeem them in multiples of 5 (5 tokens = ₹100 discount) during booking.';
        }
        if (preg_match('/\b(cancel|refund|reschedule)\b/i', $q)) {
            return 'Cancellation Policy: You can cancel pending or confirmed bookings directly from "My Bookings". Any loyalty tokens redeemed on a cancelled booking are refunded automatically to your wallet.';
        }
        if (preg_match('/\b(pay|payment|upi|card|razorpay|sandbox)\b/i', $q)) {
            return 'Payments: DriveX Assam supports UPI, Credit/Debit Cards, Wallets, and Net Banking with a 10-minute checkout price lock, plus an option to reserve and pay at pickup.';
        }
        if (preg_match('/\b(contact|phone|email|address|where|hub|guwahati|location)\b/i', $q)) {
            $info = $dbh->query('SELECT Address, EmailId, ContactNo FROM tblcontactusinfo LIMIT 1')->fetch();
            $addr = $info->Address ?? 'GS Road, Guwahati, Assam';
            $phone = $info->ContactNo ?? '+91 9876543210';
            $email = $info->EmailId ?? 'support@drivexassam.com';
            return "Our main hub is located at {$addr}. You can reach 24/7 support at {$phone} or {$email}.";
        }
        if (preg_match('/\b(price|rate|cost|cheap|suv|car|available|fleet)\b/i', $q)) {
            $stats = $dbh->query('SELECT COUNT(*) AS cnt, MIN(PricePerDay) AS min_p, MAX(PricePerDay) AS max_p FROM tblvehicles')->fetch();
            $cnt = (int) ($stats->cnt ?? 0);
            $minP = number_format((float) ($stats->min_p ?? 1400));
            $maxP = number_format((float) ($stats->max_p ?? 4800));
            return "We currently have {$cnt} verified vehicles ranging from ₹{$minP}/day to ₹{$maxP}/day. Visit Fleet & Booking or use the Smart Trip Car Finder to filter by exact dates and budget.";
        }

        return 'I can help with fleet availability, daily ₹ pricing, loyalty token rules (5 tokens = ₹100 off), cancellation refunds, or Guwahati pickup hub details. Try asking: "How do loyalty tokens work?" or "What is your cheapest car?"';
    }

    public static function draftInquiryReply(string $customerName, string $message): string
    {
        $m = strtolower($message);
        $topicNote = 'We have received your inquiry and our Guwahati operations desk will confirm availability for your requested dates.';

        if (str_contains($m, 'price') || str_contains($m, 'rate') || str_contains($m, 'cost')) {
            $topicNote = 'All our daily rates in ₹ INR include standard vehicle maintenance and 24/7 roadside assistance across Assam. You can view live totals for your exact dates directly on each vehicle page.';
        } elseif (str_contains($m, 'airport') || str_contains($m, 'pickup') || str_contains($m, 'guwahati')) {
            $topicNote = 'We offer convenient pickup from our GS Road Guwahati Hub as well as Lokpriya Gopinath Bordoloi International Airport (GAU) and Guwahati Railway Station.';
        } elseif (str_contains($m, 'token') || str_contains($m, 'discount')) {
            $topicNote = 'You earn 5 loyalty tokens after every completed trip, and once you reach 15 tokens you can redeem them at checkout (5 tokens = ₹100 off).';
        }

        return "Hello {$customerName},\n\nThank you for reaching out to DriveX Assam.\n\n{$topicNote}\n\nPlease reply to this email or call our helpline if you would like us to lock a vehicle for your travel dates.\n\nBest regards,\nDriveX Assam Support Team";
    }

    public static function generatePricingInsights(PDO $dbh): array
    {
        $rows = $dbh->query(
            'SELECT v.id, v.VehiclesTitle, b.BrandName, v.PricePerDay,
                    COUNT(bk.id) AS total_bookings,
                    SUM(CASE WHEN bk.Status IN (' . BookingService::STATUS_CONFIRMED . ',' . BookingService::STATUS_COMPLETED . ') THEN 1 ELSE 0 END) AS active_or_done
             FROM tblvehicles v
             JOIN tblbrands b ON b.id = v.VehiclesBrand
             LEFT JOIN tblbooking bk ON bk.VehicleId = v.id
             GROUP BY v.id, v.VehiclesTitle, b.BrandName, v.PricePerDay
             ORDER BY total_bookings DESC, v.PricePerDay DESC'
        )->fetchAll();

        $tips = [];
        foreach ($rows as $r) {
            $cnt = (int) $r->total_bookings;
            $rate = (int) $r->PricePerDay;
            $name = $r->BrandName . ' ' . $r->VehiclesTitle;

            if ($cnt >= 3) {
                $suggested = (int) round($rate * 1.12 / 50) * 50;
                $tips[] = [
                    'title' => "High Demand: {$name} (₹{$rate}/day)",
                    'detail' => "{$cnt} bookings recorded. Consider a +10–12% weekend surge rate (₹{$suggested}/day) to maximize peak revenue.",
                ];
            } elseif ($cnt === 0) {
                $suggested = max(900, (int) round($rate * 0.90 / 50) * 50);
                $tips[] = [
                    'title' => "Idle Inventory: {$name} (₹{$rate}/day)",
                    'detail' => "0 bookings so far. Consider a weekday promo rate (₹{$suggested}/day) or feature it on the homepage to boost utilization.",
                ];
            }
        }

        if (empty($tips)) {
            $tips[] = [
                'title' => 'Balanced Fleet Utilization',
                'detail' => 'Booking distribution across your vehicles is currently balanced. Keep monitoring weekend demand spikes.',
            ];
        }

        return array_slice($tips, 0, 5);
    }
}
