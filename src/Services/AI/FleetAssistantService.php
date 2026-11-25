<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\Env;
use App\Services\BookingService;
use PDO;
use Throwable;

/**
 * Fleet search, rental assistant, and pricing insights service with optional external LLM integration
 * (Gemini / OpenAI / local Ollama) and built-in rule-based execution.
 */
final class FleetAssistantService
{
    private PDO $dbh;

    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * Natural-language vehicle search grounded in live MariaDB availability.
     */
    public function smartCarSearch(string $query, string $fromDate = '', string $toDate = ''): array
    {
        $start = microtime(true);
        $query = trim($query);
        $brands = $this->dbh->query('SELECT id, BrandName FROM tblbrands ORDER BY BrandName ASC')->fetchAll();

        // Extract structured filters via configured provider or built-in parser
        $tierUsed = 'builtin';
        $provider = 'php_rule_engine';
        $constraints = null;

        $prompt = "Extract car rental search filters as strict JSON with keys: "
            . "seats (integer or null), fuel (Petrol|Diesel|CNG|Electric or null), "
            . "transmission (Manual|Automatic or null), max_price (integer or null), "
            . "brand_name (string or null), terrain (hills|city or null). Query: " . $query;

        $llmResult = $this->callLlmWithCircuitBreaker('search', $prompt, true);
        if ($llmResult['ok'] && is_array($llmResult['json'])) {
            $tierUsed = $llmResult['tier'];
            $provider = $llmResult['provider'];
            $j = $llmResult['json'];
            $constraints = [
                'seats' => isset($j['seats']) && is_numeric($j['seats']) ? (int) $j['seats'] : null,
                'fuel' => !empty($j['fuel']) ? (string) $j['fuel'] : null,
                'transmission' => !empty($j['transmission']) ? (string) $j['transmission'] : null,
                'max_price' => isset($j['max_price']) && is_numeric($j['max_price']) ? (int) $j['max_price'] : null,
                'brand_id' => null,
                'brand_name' => !empty($j['brand_name']) ? (string) $j['brand_name'] : null,
                'terrain' => !empty($j['terrain']) ? (string) $j['terrain'] : null,
            ];
            if ($constraints['brand_name'] !== null) {
                foreach ($brands as $b) {
                    if (stripos($b->BrandName, $constraints['brand_name']) !== false) {
                        $constraints['brand_id'] = (int) $b->id;
                        break;
                    }
                }
            }
        } else {
            $constraints = RuleEngine::parseSearchConstraints($query, $brands);
        }

        // Ground against real MariaDB vehicles + live availability
        $availExpr = '1 AS is_available';
        $params = [];
        if ($fromDate !== '' && $toDate !== '') {
            $availExpr = "CASE WHEN EXISTS (
                SELECT 1 FROM tblbooking bk
                WHERE bk.VehicleId = v.id
                  AND (
                      bk.Status IN (" . BookingService::STATUS_CONFIRMED . ", " . BookingService::STATUS_ACTIVE . ")
                      OR (bk.Status = " . BookingService::STATUS_PENDING . " AND (bk.PriceLockUntil IS NULL OR bk.PriceLockUntil >= NOW()))
                  )
                  AND NOT (DATE(bk.ToDate) < :af OR DATE(bk.FromDate) > :at)
            ) THEN 0 ELSE 1 END AS is_available";
            $params[':af'] = $fromDate;
            $params[':at'] = $toDate;
        }

        $sql = "SELECT v.id, v.VehiclesTitle, v.VehiclesBrand, v.VehiclesOverview, v.PricePerDay,
                       v.FuelType, v.ModelYear, v.SeatingCapacity, v.Transmission, v.Vimage1,
                       b.BrandName, {$availExpr}
                FROM tblvehicles v
                JOIN tblbrands b ON b.id = v.VehiclesBrand";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);
        $allVehicles = $stmt->fetchAll();

        $ranked = RuleEngine::scoreAndRankVehicles($allVehicles, $constraints, $query);

        $parts = [];
        if ($constraints['seats']) {
            $parts[] = $constraints['seats'] . '+ seats';
        }
        if ($constraints['fuel']) {
            $parts[] = $constraints['fuel'];
        }
        if ($constraints['transmission']) {
            $parts[] = $constraints['transmission'];
        }
        if ($constraints['max_price']) {
            $parts[] = 'under ₹' . number_format((float) $constraints['max_price']) . '/day';
        }
        if ($constraints['brand_name']) {
            $parts[] = 'Brand: ' . $constraints['brand_name'];
        }

        $explanation = !empty($parts)
            ? 'Ranked ' . count($ranked) . ' vehicles prioritizing: ' . implode(', ', $parts) . '.'
            : 'Showing best available matches from our DriveX Assam fleet.';

        $this->logAiCall('search', $tierUsed, $provider, $start, true, null);

        return [
            'success' => true,
            'vehicles' => array_slice($ranked, 0, 12),
            'constraints' => $constraints,
            'explanation' => $explanation,
            'tier_used' => $tierUsed,
            'tier_label' => $this->tierLabel($tierUsed, $provider),
        ];
    }

    public function optimizeBooking(int $vehicleId, int $days, int $tokenBalance): array
    {
        $start = microtime(true);
        $stmt = $this->dbh->prepare(
            'SELECT v.*, b.BrandName FROM tblvehicles v JOIN tblbrands b ON b.id = v.VehiclesBrand WHERE v.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $vehicleId]);
        $vehicle = $stmt->fetch();

        if (!$vehicle) {
            return ['success' => false, 'advice' => 'Vehicle not found.'];
        }

        $advice = RuleEngine::optimizeBooking($vehicle, $days, $tokenBalance);
        $this->logAiCall('optimize_booking', 'builtin', 'php_rule_engine', $start, true, null);

        return [
            'success' => true,
            'advice' => $advice,
            'tier_label' => 'Booking Advisor',
        ];
    }

    public function answerAssistant(string $question): array
    {
        $start = microtime(true);
        $question = trim($question);

        $cacheKey = hash('sha256', 'chat:' . strtolower($question));
        if ($cached = $this->getCached($cacheKey)) {
            $cached['cached'] = true;
            return $cached;
        }

        $tierUsed = 'builtin';
        $provider = 'php_rule_engine';
        $reply = null;

        $facts = RuleEngine::answerPolicyQuestion($question, $this->dbh);
        $prompt = "You are DriveX Assam's concise, helpful car rental assistant. "
            . "Answer in 2-4 crisp sentences using ONLY these verified facts (never invent prices or policies): {$facts}\n"
            . "Customer question: {$question}";

        $llmResult = $this->callLlmWithCircuitBreaker('assistant', $prompt, false);
        if ($llmResult['ok'] && !empty($llmResult['text'])) {
            $reply = $llmResult['text'];
            $tierUsed = $llmResult['tier'];
            $provider = $llmResult['provider'];
        } else {
            $reply = $facts;
        }

        $this->logAiCall('assistant', $tierUsed, $provider, $start, true, null);

        $res = [
            'success' => true,
            'reply' => $reply,
            'tier_used' => $tierUsed,
            'tier_label' => $this->tierLabel($tierUsed, $provider),
        ];
        $this->putCache($cacheKey, 'assistant', $res, 900);

        return $res;
    }

    public function draftInquiryReply(string $customerName, string $message): array
    {
        $start = microtime(true);
        $tierUsed = 'builtin';
        $provider = 'php_rule_engine';

        $prompt = "Draft a polite, concise customer support email reply from DriveX Assam (Guwahati car rental) to {$customerName}. "
            . "Keep it professional and practical. Do not invent specific booking numbers. Customer message: {$message}";

        $llmResult = $this->callLlmWithCircuitBreaker('draft_reply', $prompt, false);
        if ($llmResult['ok'] && !empty($llmResult['text'])) {
            $reply = $llmResult['text'];
            $tierUsed = $llmResult['tier'];
            $provider = $llmResult['provider'];
        } else {
            $reply = RuleEngine::draftInquiryReply($customerName, $message);
        }

        $this->logAiCall('draft_reply', $tierUsed, $provider, $start, true, null);

        return [
            'success' => true,
            'reply' => $reply,
            'tier_used' => $tierUsed,
            'tier_label' => $this->tierLabel($tierUsed, $provider),
        ];
    }

    public function getPricingInsights(): array
    {
        $start = microtime(true);
        $tips = RuleEngine::generatePricingInsights($this->dbh);
        $this->logAiCall('pricing_insights', 'builtin', 'sql_analytics', $start, true, null);

        return [
            'success' => true,
            'insights' => $tips,
            'tier_label' => 'Utilization Analytics',
        ];
    }

    /**
     * Calls configured external or local LLM endpoint with a circuit breaker.
     */
    private function callLlmWithCircuitBreaker(string $feature, string $prompt, bool $expectJson): array
    {
        $timeout = max(2, min(10, Env::int('AI_TIMEOUT_SEC', 5)));

        // Cloud provider
        $apiKey = trim((string) Env::get('AI_API_KEY', ''));
        $provider = strtolower(trim((string) Env::get('AI_PROVIDER', 'gemini')));

        if ($apiKey !== '' && !$this->isCircuitOpen()) {
            $model = trim((string) Env::get('AI_MODEL', 'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
            $res = $provider === 'openai'
                ? $this->callOpenAiCompatible('https://api.openai.com/v1/chat/completions', $apiKey, $model, $prompt, $timeout, $expectJson)
                : $this->callGemini($apiKey, $model, $prompt, $timeout, $expectJson);

            if ($res['ok']) {
                $res['tier'] = 'cloud';
                $res['provider'] = $provider;
                return $res;
            }
            $this->logAiCall($feature, 'cloud', $provider, microtime(true), false, $res['error'] ?? 'Cloud provider error');
        }

        // Local Ollama endpoint
        $localUrl = trim((string) Env::get('AI_LOCAL_URL', ''));
        if ($localUrl !== '') {
            $localModel = trim((string) Env::get('AI_LOCAL_MODEL', 'llama3.2')) ?: 'llama3.2';
            $res = $this->callOllama($localUrl, $localModel, $prompt, $timeout, $expectJson);
            if ($res['ok']) {
                $res['tier'] = 'local';
                $res['provider'] = 'ollama';
                return $res;
            }
            $this->logAiCall($feature, 'local', 'ollama', microtime(true), false, $res['error'] ?? 'Local endpoint error');
        }

        return ['ok' => false];
    }

    private function callGemini(string $apiKey, string $model, string $prompt, int $timeout, bool $expectJson): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
        ];
        if ($expectJson) {
            $body['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $resp = $this->httpPostJson($url, $body, [], $timeout);
        if (!$resp['ok']) {
            return $resp;
        }

        $text = $resp['data']['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Empty Gemini response'];
        }

        if ($expectJson) {
            $decoded = json_decode($text, true);
            return is_array($decoded)
                ? ['ok' => true, 'json' => $decoded, 'text' => $text]
                : ['ok' => false, 'error' => 'Invalid JSON from Gemini'];
        }

        return ['ok' => true, 'text' => trim($text)];
    }

    private function callOpenAiCompatible(string $url, string $apiKey, string $model, string $prompt, int $timeout, bool $expectJson): array
    {
        $body = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.2,
        ];
        if ($expectJson) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $resp = $this->httpPostJson($url, $body, ['Authorization: Bearer ' . $apiKey], $timeout);
        if (!$resp['ok']) {
            return $resp;
        }

        $text = $resp['data']['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Empty OpenAI response'];
        }

        if ($expectJson) {
            $decoded = json_decode($text, true);
            return is_array($decoded)
                ? ['ok' => true, 'json' => $decoded, 'text' => $text]
                : ['ok' => false, 'error' => 'Invalid JSON from OpenAI'];
        }

        return ['ok' => true, 'text' => trim($text)];
    }

    private function callOllama(string $baseUrl, string $model, string $prompt, int $timeout, bool $expectJson): array
    {
        $url = rtrim($baseUrl, '/') . '/api/generate';
        $body = ['model' => $model, 'prompt' => $prompt, 'stream' => false];
        if ($expectJson) {
            $body['format'] = 'json';
        }

        $resp = $this->httpPostJson($url, $body, [], $timeout);
        if (!$resp['ok']) {
            return $resp;
        }

        $text = $resp['data']['response'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Empty Ollama response'];
        }

        if ($expectJson) {
            $decoded = json_decode($text, true);
            return is_array($decoded)
                ? ['ok' => true, 'json' => $decoded, 'text' => $text]
                : ['ok' => false, 'error' => 'Invalid JSON from Ollama'];
        }

        return ['ok' => true, 'text' => trim($text)];
    }

    private function httpPostJson(string $url, array $payload, array $extraHeaders, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL extension unavailable'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'cURL init failed'];
        }

        $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $code < 200 || $code >= 300) {
            return ['ok' => false, 'error' => $err ?: "HTTP {$code}"];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded)
            ? ['ok' => true, 'data' => $decoded]
            : ['ok' => false, 'error' => 'Non-JSON HTTP response'];
    }

    private function isCircuitOpen(): bool
    {
        try {
            $stmt = $this->dbh->query(
                "SELECT COUNT(*) FROM tblailogs
                 WHERE tier_used = 'cloud' AND success = 0
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            return (int) $stmt->fetchColumn() >= 3;
        } catch (Throwable) {
            return false;
        }
    }

    private function getCached(string $key): ?array
    {
        try {
            $stmt = $this->dbh->prepare('SELECT response_json FROM tblaicache WHERE cache_key = :k AND expires_at >= NOW() LIMIT 1');
            $stmt->execute([':k' => $key]);
            $raw = $stmt->fetchColumn();
            if (!is_string($raw)) {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function putCache(string $key, string $feature, array $data, int $ttlSeconds): void
    {
        try {
            $stmt = $this->dbh->prepare(
                'INSERT INTO tblaicache (cache_key, feature, response_json, expires_at)
                 VALUES (:k, :f, :json, DATE_ADD(NOW(), INTERVAL :ttl SECOND))
                 ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), expires_at = VALUES(expires_at)'
            );
            $stmt->bindValue(':k', $key, PDO::PARAM_STR);
            $stmt->bindValue(':f', $feature, PDO::PARAM_STR);
            $stmt->bindValue(':json', json_encode($data, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(':ttl', max(60, $ttlSeconds), PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable) {
            // Non-fatal
        }
    }

    private function logAiCall(string $feature, string $tier, string $provider, float $startTime, bool $success, ?string $err): void
    {
        try {
            $ms = (int) round((microtime(true) - $startTime) * 1000);
            $stmt = $this->dbh->prepare(
                'INSERT INTO tblailogs (feature, tier_used, provider, latency_ms, success, error_note)
                 VALUES (:f, :t, :p, :ms, :ok, :err)'
            );
            $stmt->execute([
                ':f' => substr($feature, 0, 50),
                ':t' => substr($tier, 0, 30),
                ':p' => substr($provider, 0, 40),
                ':ms' => $ms,
                ':ok' => $success ? 1 : 0,
                ':err' => $err ? substr($err, 0, 255) : null,
            ]);
        } catch (Throwable) {
            // Non-fatal
        }
    }

    private function tierLabel(string $tier, string $provider): string
    {
        return match ($tier) {
            'cloud' => 'Assistant (' . ucfirst($provider) . ')',
            'local' => 'Assistant (Local Model)',
            default => 'DriveX Catalog Engine',
        };
    }
}
