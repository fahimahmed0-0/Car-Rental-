<?php

declare(strict_types=1);

use App\Services\PaymentService;
use App\Services\PricingService;

/**
 * Legacy compatibility facade delegating to App\Services\PaymentService & PricingService.
 */
class PaymentGateway
{
    private PDO $dbh;
    private PaymentService $service;

    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
        $this->service = new PaymentService($dbh);
    }

    public function calculateBookingAmount(int|string $vehicleId, string $fromDate, string $toDate, float $discountAmount = 0): float
    {
        $stmt = $this->dbh->prepare('SELECT PricePerDay FROM tblvehicles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $vehicleId]);
        $rate = (float) $stmt->fetchColumn();
        if ($rate <= 0) {
            return 0.0;
        }
        $days = PricingService::calculateDays($fromDate, $toDate, false);
        return max(0.0, round(($rate * $days) - $discountAmount, 2));
    }

    public function getAvailablePaymentMethods(): array
    {
        return PaymentService::VALID_METHODS;
    }

    public function getUserPaymentHistory(string $userEmail, int $limit = 10): array
    {
        return $this->service->getUserPaymentHistory($userEmail, $limit);
    }
}
