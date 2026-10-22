<?php
$PAGE_TITLE = 'Activate package';
require __DIR__ . '/_top.php';
$pdo = db();
$pkgs = $pdo->query('SELECT * FROM packages ORDER BY price')->fetchAll();
$csrfToken = csrf_token();
?>
<h1>Activate a package</h1><p class="sub">Pay securely with Razorpay (cards, UPI, netbanking, wallets).</p>
<div id="payMsg" class="alert bad" style="display:none"></div>
<div class="grid g2">
<?php foreach ($pkgs as $p): ?>
<div class="card">
<h3><?= htmlspecialchars($p['name']) ?></h3>
<div class="num" style="font-size:28px;margin:6px 0"><?= rupee($p['price']) ?></div>
<ul style="margin:10px 0 16px 18px;color:var(--mut);font-size:13.5px">
<li>Sponsor income: <?= rupee($p['sponsor_income']) ?></li>
<li>Matrix parent income: <?= rupee($p['matrix_income']) ?> up to level <?= (int)$p['matrix_levels'] ?></li>
</ul>
<button class="btn pay-btn" data-pkg="<?= htmlspecialchars($p['code']) ?>" style="width:100%">Pay <?= rupee($p['price']) ?></button>
</div>
<?php endforeach; ?>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const CSRF = <?= json_encode($csrfToken) ?>;
document.querySelectorAll('.pay-btn').forEach(btn => {
  btn.dataset.label = btn.textContent;
  btn.addEventListener('click', async () => {
  const msg = document.getElementById('payMsg');
  msg.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Please wait…';
  try {
    const res = await fetch('/api/razorpay-create-order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF': CSRF},
      body: JSON.stringify({ package: btn.dataset.pkg })
    });
    const order = await res.json();
    if (order.error) throw new Error(order.error);

    const rzp = new Razorpay({
      key: order.key,
      amount: order.amount,
      currency: order.currency,
      name: order.name,
      description: order.description,
      order_id: order.order_id,
      handler: async function (response) {
        const verify = await fetch('/api/razorpay-verify.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF': CSRF},
          body: JSON.stringify({ ...response, payment_id: order.payment_id })
        });
        const result = await verify.json();
        if (result.ok) {
          location.href = 'index.php';
        } else {
          msg.textContent = result.error || 'Payment could not be verified. If money was deducted, contact support with your payment ID.';
          msg.style.display = 'block';
        }
      },
      modal: { ondismiss: function () { btn.disabled = false; btn.textContent = btn.dataset.label; } },
      theme: { color: '#a5141c' }
    });
    rzp.open();
    btn.textContent = btn.dataset.label;
  } catch (e) {
    msg.textContent = e.message;
    msg.style.display = 'block';
    btn.textContent = btn.dataset.label;
  } finally {
    btn.disabled = false;
  }
  });
});
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
