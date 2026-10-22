<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/razorpay.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'member') {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in first.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
// CSRF: this endpoint is called by fetch() from pay.php, which sends the
// token as a header instead of a form field.
$sent = $_SERVER['HTTP_X_CSRF'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    http_response_code(400);
    echo json_encode(['error' => 'Session expired — refresh the page and try again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$packageCode = $body['package'] ?? '';
$memberId = current_member_id();

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM packages WHERE code = ?');
$stmt->execute([$packageCode]);
$pkg = $stmt->fetch();
if (!$pkg) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown package.']);
    exit;
}

try {
    $ins = $pdo->prepare('INSERT INTO payments (member_id, package_code, amount, status, channel) VALUES (?,?,?,"created","razorpay")');
    $ins->execute([$memberId, $packageCode, $pkg['price']]);
    $paymentId = (int)$pdo->lastInsertId();

    $order = razorpay_create_order((float)$pkg['price'], 'gsaa-' . $paymentId);
    $pdo->prepare('UPDATE payments SET razorpay_order_id = ? WHERE id = ?')->execute([$order['id'], $paymentId]);

    echo json_encode([
        'order_id' => $order['id'],
        'amount' => $order['amount'],
        'currency' => $order['currency'],
        'key' => RAZORPAY_KEY_ID,
        'payment_id' => $paymentId,
        'name' => SITE_NAME,
        'description' => $pkg['name'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
