<?php
/**
 * Minimal Razorpay REST client — no SDK dependency, just cURL + HTTP basic
 * auth with your key_id/key_secret (config.php). Good enough for creating
 * orders; signature verification for payments/webhooks is below too.
 */

function razorpay_create_order(float $amountRupees, string $receipt): array
{
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    $payload = json_encode([
        'amount' => (int)round($amountRupees * 100), // paise
        'currency' => 'INR',
        'receipt' => $receipt,
        'payment_capture' => 1,
    ]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Could not reach Razorpay: ' . $curlErr);
    }
    $data = json_decode($response, true);
    if ($httpCode >= 300 || !isset($data['id'])) {
        $reason = $data['error']['description'] ?? $response;
        throw new RuntimeException('Razorpay order creation failed: ' . $reason);
    }
    return $data; // includes id, amount, currency, ...
}

/** Verify the signature Razorpay Checkout sends back on successful payment. */
function razorpay_verify_payment_signature(string $orderId, string $paymentId, string $signature): bool
{
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    return hash_equals($expected, $signature);
}

/** Verify a webhook request's X-Razorpay-Signature header against the raw body. */
function razorpay_verify_webhook_signature(string $rawBody, string $signature): bool
{
    $expected = hash_hmac('sha256', $rawBody, RAZORPAY_WEBHOOK_SECRET);
    return hash_equals($expected, $signature);
}
