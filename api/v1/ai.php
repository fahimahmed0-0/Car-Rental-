<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Services\AI\AiOrchestrator;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

Csrf::requireValid();

$bucket = 'ai_api:' . Security::clientIp();
if (!Security::checkRateLimit($dbh, $bucket, 30, 300)) {
    Security::jsonResponse(['success' => false, 'message' => 'Too many requests. Please slow down briefly.'], 429);
}
Security::hitRateLimit($dbh, $bucket);

$input = $_POST;
if (empty($input) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = (string) ($input['action'] ?? '');
$ai = new AiOrchestrator($dbh);

try {
    switch ($action) {
        case 'search':
            $query = trim((string) ($input['query'] ?? ''));
            $from = trim((string) ($input['from_date'] ?? ''));
            $to = trim((string) ($input['to_date'] ?? ''));
            Security::jsonResponse($ai->smartCarSearch($query, $from, $to));

        case 'optimize_booking':
            $vhid = (int) ($input['vehicle_id'] ?? 0);
            $days = (int) ($input['days'] ?? 1);
            $bal = (int) ($input['token_balance'] ?? 0);
            Security::jsonResponse($ai->optimizeBooking($vhid, $days, $bal));

        case 'assistant':
            $q = trim((string) ($input['question'] ?? ''));
            if ($q === '') {
                Security::jsonResponse(['success' => false, 'message' => 'Please enter a question.'], 400);
            }
            Security::jsonResponse($ai->answerAssistant($q));

        case 'draft_reply':
            if (empty($_SESSION['alogin'])) {
                Security::jsonResponse(['success' => false, 'message' => 'Admin authentication required.'], 401);
            }
            $name = trim((string) ($input['customer_name'] ?? 'Customer'));
            $msg = trim((string) ($input['message'] ?? ''));
            Security::jsonResponse($ai->draftInquiryReply($name, $msg));

        case 'pricing_insights':
            if (empty($_SESSION['alogin'])) {
                Security::jsonResponse(['success' => false, 'message' => 'Admin authentication required.'], 401);
            }
            Security::jsonResponse($ai->getPricingInsights());

        default:
            Security::jsonResponse(['success' => false, 'message' => 'Unsupported AI action.'], 400);
    }
} catch (Throwable $e) {
    error_log('AI endpoint error: ' . $e->getMessage());
    Security::jsonResponse(['success' => false, 'message' => 'Assistant unavailable right now.'], 500);
}
