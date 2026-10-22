<?php
/**
 * Called by the browser (Razorpay Checkout's success handler) right after
 * payment. Verifies the signature and activates the package immediately,
 * so the member doesn't have to wait for the webhook. The webhook
 * (razorpay-webhook.php) is the source of truth and is idempotent, so it's
 * safe if both fire.
 */
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/razorpay.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'member') {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in first.']);
    exit;
}
$sent = $_SERVER['HTTP_X_CSRF'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    http_response_code(400);
    echo json_encode(['error' => 'Session expired.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId = $body['razorpay_order_id'] ?? '';
$paymentRef = $body['razorpay_payment_id'] ?? '';
$signature = $body['razorpay_signature'] ?? '';
$paymentDbId = (int)($body['payment_id'] ?? 0);

if (!$orderId || !$paymentRef || !$signature || !razorpay_verify_payment_signature($orderId, $paymentRef, $signature)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payment signature could not be verified.']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? AND razorpay_order_id = ?');
$stmt->execute([$paymentDbId, $orderId]);
$payment = $stmt->fetch();
if (!$payment || (int)$payment['member_id'] !== current_member_id()) {
    http_response_code(404);
    echo json_encode(['error' => 'Payment not found.']);
    exit;
}

if ($payment['status'] !== 'paid') {
    $pdo->prepare('UPDATE payments SET status="paid", razorpay_payment_id=?, razorpay_signature=?, paid_at=? WHERE id=?')
        ->execute([$paymentRef, $signature, date('Y-m-d H:i:s'), $paymentDbId]);
    activate_member_package((int)$payment['member_id'], $payment['package_code'], $paymentDbId);
    log_activity('payment.razorpay_success', 'Payment #' . $paymentDbId);
}

echo json_encode(['ok' => true]);
