<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Kasir (POS)';
$activePage = 'pos';

// Handle checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cartJson   = $_POST['cart_data'] ?? '[]';
    $cartItems  = json_decode($cartJson, true);
    $customerId = $_POST['customer_id'] ?: null;
    $paid       = (float)preg_replace('/\D/', '', $_POST['paid_amount'] ?? '0');
    $payMethod  = $_POST['payment_method'] ?? 'tunai';
    $notes      = trim($_POST['notes'] ?? '');

    if (empty($cartItems)) {
        flash('error', 'Keranjang kosong!');
        header('Location: kasir.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item['price'] * $item['qty'];
        }
        $total    = $subtotal;
        $change   = max(0, $paid - $total);
        $invoiceNo = generateInvoice('INV');
        $status   = $payMethod === 'piutang' ? 'piutang' : 'selesai';

        // Insert sale
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no,customer_id,user_id,subtotal,total,paid,change_due,payment_method,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$invoiceNo, $customerId, $_SESSION['user_id'], $subtotal, $total, $paid, $change, $payMethod, $status, $notes]);
        $saleId = $pdo->lastInsertId();

        // Insert sale items + update stock
        foreach ($cartItems as $item) {
            $prod = $pdo->query("SELECT * FROM products WHERE id=" . (int)$item['id'])->fetch();
            $subtItem = $item['price'] * $item['qty'];
            $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,buy_price,sell_price,subtotal) VALUES (?,?,?,?,?,?)")
                ->execute([$saleId, $item['id'], $item['qty'], $prod['buy_price'], $item['price'], $subtItem]);

            $stockBefore = $prod['stock'];
            $stockAfter  = $stockBefore - $item['qty'];
            $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$stockAfter, $item['id']]);
            $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,stock_before,stock_after,reference_type,reference_id,user_id) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$item['id'], 'keluar', $item['qty'], $stockBefore, $stockAfter, 'sale', $saleId, $_SESSION['user_id']]);
        }

        // Auto-create journal entry
        $journalNo = generateJournalNo();
        $pdo->prepare("INSERT INTO journals (journal_no,journal_date,description,type,reference_type,reference_id,total_debit,total_credit,user_id) VALUES (?,CURDATE(),?,?,?,?,?,?,?)")
            ->execute([$journalNo, "Penjualan - $invoiceNo", 'penjualan', 'sale', $saleId, $total, $total, $_SESSION['user_id']]);
        $journalId = $pdo->lastInsertId();

        // Debit: Kas / Piutang | Credit: Penjualan
        $debitCoa = $payMethod === 'piutang' ? 1100 : 1001; // Piutang Usaha or Kas
        $coaDebit  = $pdo->query("SELECT id FROM coa WHERE code='$debitCoa'")->fetchColumn() ?: 1;
        $coaCredit = $pdo->query("SELECT id FROM coa WHERE code='4001'")->fetchColumn() ?: 1;

        $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,?,?)")
            ->execute([$journalId, $coaDebit, "Penerimaan kas/piutang - $invoiceNo", $total, 0]);
        $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,?,?)")
            ->execute([$journalId, $coaCredit, "Pendapatan penjualan - $invoiceNo", 0, $total]);

        $pdo->prepare("UPDATE sales SET journal_id=? WHERE id=?")->execute([$journalId, $saleId]);
        $pdo->commit();

        $_SESSION['last_invoice'] = $saleId;
        flash('success', "Transaksi $invoiceNo berhasil! Kembalian: " . formatRupiah($change));
        header('Location: kasir.php'); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        header('Location: kasir.php'); exit;
    }
}

// Load products & customers
$products  = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.name")->fetchAll();
$customers = $pdo->query("SELECT * FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include APP_URL ? dirname(__DIR__, 2) . '/includes/header.php' : '../../includes/header.php';
?>

<div class="pos-layout">
  <!-- LEFT: Products -->
  <div class="pos-products">
    <!-- Search & Filter -->
    <div style="display:flex;gap:10px;margin-bottom:14px;align-items:center">
      <div class="product-search" style="flex:1">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="form-control" placeholder="Cari produk atau scan barcode..." oninput="filterProducts(this.value)" style="padding-left:38px">
      </div>
      <select class="form-control" style="width:160px" onchange="filterCategory(this.value)">
        <option value="">Semua Kategori</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Product Grid -->
    <div class="product-grid" id="product-grid">
      <?php foreach ($products as $p): ?>
      <div class="product-card" data-name="<?= htmlspecialchars($p['name']) ?>" data-cat="<?= $p['category_id'] ?>"
           onclick="addToCart(<?= $p['id'] ?>,'<?= addslashes(htmlspecialchars($p['name'])) ?>',<?= $p['sell_price'] ?>,<?= $p['stock'] ?>)">
        <div style="font-size:24px;margin-bottom:6px;color:var(--text-muted)"><i class="fas fa-cube"></i></div>
        <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="prod-price"><?= formatRupiah($p['sell_price']) ?></div>
        <div class="prod-stock">Stok: <?= $p['stock'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-cart">
    <div class="cart-header">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="cart-title"><i class="fas fa-shopping-cart" style="color:var(--accent);margin-right:8px"></i>Keranjang (<span id="cart-count">0</span>)</span>
        <button class="btn btn-danger btn-sm" onclick="cart=[];renderCart()"><i class="fas fa-trash"></i> Kosongkan</button>
      </div>
      <select class="form-control" style="margin-top:10px" id="customer-select">
        <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="cart-items" id="cart-items">
      <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>Keranjang kosong</p></div>
    </div>

    <div class="cart-footer">
      <div class="cart-totals">
        <div class="cart-total-row">
          <span>Subtotal</span><span class="amount" id="cart-subtotal">Rp 0</span>
        </div>
        <div class="cart-total-row grand">
          <span>Total</span><span class="amount" id="cart-total">Rp 0</span>
        </div>
      </div>

      <form method="POST" action="" id="checkout-form">
        <input type="hidden" name="cart_data" id="cart-data">
        <input type="hidden" name="customer_id" id="cust-hidden">

        <div class="form-group" style="margin-bottom:8px">
          <select name="payment_method" class="form-control">
            <option value="tunai">💵 Tunai</option>
            <option value="transfer">🏦 Transfer Bank</option>
            <option value="kartu">💳 Kartu Debit/Kredit</option>
            <option value="piutang">📄 Piutang</option>
          </select>
        </div>

        <div class="pay-input">
          <input type="text" name="paid_amount" id="paid-amount" class="form-control"
                 placeholder="Uang diterima" oninput="this.value=this.value.replace(/\D/g,'');calculateChange()">
        </div>

        <div class="cart-total-row" style="margin-bottom:12px">
          <span style="font-size:13px">Kembalian</span>
          <span class="amount" id="change-due" style="color:var(--green);font-weight:700">Rp 0</span>
        </div>

        <button type="button" onclick="processCheckout()" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px">
          <i class="fas fa-check-circle"></i> Proses Pembayaran
        </button>
        <input type="hidden" name="checkout" value="1">
      </form>
    </div>
  </div>
</div>

<script>
function filterCategory(catId) {
  document.querySelectorAll('.product-card').forEach(card => {
    card.style.display = (!catId || card.dataset.cat === catId) ? '' : 'none';
  });
}

function processCheckout() {
  if (cart.length === 0) return showToast('Keranjang masih kosong!', 'error');
  document.getElementById('cart-data').value = JSON.stringify(cart);
  document.getElementById('cust-hidden').value = document.getElementById('customer-select').value;
  document.getElementById('checkout-form').submit();
}
</script>

<?php
$footerPath = dirname(__DIR__, 2) . '/includes/footer.php';
include $footerPath;
?>
