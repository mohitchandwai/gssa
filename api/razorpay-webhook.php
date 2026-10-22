<?php
/**
 * Razorpay server-to-server webhook. Configure this URL in
 * Razorpay Dashboard -> Settings -> Webhooks, subscribed to
 * "payment.captured" (and optionally "payment.failed"), with the same
 * secret as RAZORPAY_WEBHOOK_SECRET in config.php.
 *
 * This is the source of truth for payment status — idempotent, so it's
 * safe even if razorpay-verify.php already activated the package.
 */
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/razorpay.php';
header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (!$signature || !razorpay_verify_webhook_signature($rawBody, $signature)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
$type = $event['event'] ?? '';

if ($type === 'payment.captured' || $type === 'order.paid') {
    $entity = $event['payload']['payment']['entity'] ?? null;
    if ($entity) {
        $orderId = $entity['order_id'] ?? '';
        $paymentRef = $entity['id'] ?? '';

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE razorpay_order_id = ?');
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch();

        if ($payment && $payment['status'] !== 'paid') {
            $pdo->prepare('UPDATE payments SET status="paid", razorpay_payment_id=?, paid_at=? WHERE id=?')
                ->execute([$paymentRef, date('Y-m-d H:i:s'), $payment['id']]);
            activate_member_package((int)$payment['member_id'], $payment['package_code'], (int)$payment['id']);
            log_activity('payment.webhook_captured', 'Payment #' . $payment['id']);
        }
    }
} elseif ($type === 'payment.failed') {
    $entity = $event['payload']['payment']['entity'] ?? null;
    if ($entity) {
        $orderId = $entity['order_id'] ?? '';
        db()->prepare("UPDATE payments SET status='failed' WHERE razorpay_order_id = ? AND status != 'paid'")->execute([$orderId]);
    }
}

http_response_code(200);
echo json_encode(['ok' => true]);
