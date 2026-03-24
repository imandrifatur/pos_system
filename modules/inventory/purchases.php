<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle  = 'Pembelian Barang';
$activePage = 'purchases';

/* ── Save Purchase ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $supplierId = (int)$_POST['supplier_id'];
    $date       = $_POST['purchase_date'];
    $notes      = trim($_POST['notes'] ?? '');
    $payMethod  = $_POST['payment_method'];
    $paid       = (float)preg_replace('/\D/', '', $_POST['paid'] ?? '0');
    $prodIds    = $_POST['product_id'] ?? [];
    $qtys       = $_POST['quantity']   ?? [];
    $prices     = $_POST['buy_price']  ?? [];

    if (empty($prodIds)) { flash('error','Tambahkan minimal 1 produk!'); header('Location: purchases.php'); exit; }

    $pdo->beginTransaction();
    try {
        $subtotal = 0;
        foreach ($prodIds as $i => $pid) {
            $subtotal += (float)preg_replace('/\D/','',$prices[$i]) * (float)$qtys[$i];
        }
        $total  = $subtotal;
        $status = $payMethod === 'hutang' ? 'hutang' : 'selesai';
        $invNo  = generateInvoice('PO');

        $pdo->prepare("INSERT INTO purchases (invoice_no,supplier_id,user_id,purchase_date,subtotal,total,paid,payment_method,status,notes)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$invNo, $supplierId, $_SESSION['user_id'], $date, $subtotal, $total, $paid, $payMethod, $status, $notes]);
        $purchId = $pdo->lastInsertId();

        foreach ($prodIds as $i => $pid) {
            $pid   = (int)$pid;
            $qty   = (float)$qtys[$i];
            $price = (float)preg_replace('/\D/','',$prices[$i]);
            $sub   = $price * $qty;
            $pdo->prepare("INSERT INTO purchase_items (purchase_id,product_id,quantity,buy_price,subtotal) VALUES (?,?,?,?,?)")
                ->execute([$purchId, $pid, $qty, $price, $sub]);

            $prod = $pdo->query("SELECT * FROM products WHERE id=$pid")->fetch();
            $stockBefore = $prod['stock'];
            $stockAfter  = $stockBefore + $qty;
            $pdo->prepare("UPDATE products SET stock=?,buy_price=? WHERE id=?")->execute([$stockAfter, $price, $pid]);
            $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,stock_before,stock_after,reference_type,reference_id,user_id)
                           VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$pid,'masuk',$qty,$stockBefore,$stockAfter,'purchase',$purchId,$_SESSION['user_id']]);
        }

        /* Auto-journal */
        $jNo = generateJournalNo();
        $pdo->prepare("INSERT INTO journals (journal_no,journal_date,description,type,reference_type,reference_id,total_debit,total_credit,user_id)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$jNo, $date, "Pembelian - $invNo", 'pembelian', 'purchase', $purchId, $total, $total, $_SESSION['user_id']]);
        $jId = $pdo->lastInsertId();

        $persId = $pdo->query("SELECT id FROM coa WHERE code='1200'")->fetchColumn(); // Persediaan
        $bayId  = $payMethod === 'hutang'
            ? $pdo->query("SELECT id FROM coa WHERE code='2001'")->fetchColumn()  // Hutang Usaha
            : $pdo->query("SELECT id FROM coa WHERE code='1001'")->fetchColumn(); // Kas

        $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,?,0)")
            ->execute([$jId, $persId, "Pembelian barang - $invNo", $total]);
        $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,0,?)")
            ->execute([$jId, $bayId, "Pembayaran - $invNo", $total]);

        $pdo->prepare("UPDATE purchases SET journal_id=? WHERE id=?")->execute([$jId, $purchId]);
        $pdo->commit();
        flash('success', "Pembelian $invNo berhasil dicatat! Stok diperbarui otomatis.");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    header('Location: purchases.php'); exit;
}

/* ── Detail AJAX ──────────────────────────────────────── */
if (isset($_GET['detail'])) {
    $pid   = (int)$_GET['detail'];
    $items = $pdo->query("SELECT pi.*, p.name, p.code FROM purchase_items pi JOIN products p ON pi.product_id=p.id WHERE pi.purchase_id=$pid")->fetchAll();
    echo '<table class="data-table"><thead><tr><th>Kode</th><th>Produk</th><th>Qty</th><th>Harga Beli</th><th>Subtotal</th></tr></thead><tbody>';
    foreach ($items as $it) {
        echo "<tr><td style='font-family:monospace;font-size:12px'>{$it['code']}</td><td>{$it['name']}</td>";
        echo "<td>{$it['quantity']}</td><td class='amount'>".formatRupiah($it['buy_price'])."</td>";
        echo "<td class='amount'>".formatRupiah($it['subtotal'])."</td></tr>";
    }
    echo '</tbody></table>';
    exit;
}

/* ── Data ─────────────────────────────────────────────── */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$q    = trim($_GET['q'] ?? '');

$where  = "WHERE p.purchase_date BETWEEN ? AND ?";
$params = [$from, $to . ' 23:59:59'];
if ($q) { $where .= " AND (p.invoice_no LIKE ? OR s.name LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare("
    SELECT p.*, s.name as sup_name, u.full_name
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    JOIN users u ON p.user_id = u.id
    $where
    ORDER BY p.purchase_date DESC
");

$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Hitung total purchase status selesai */
$totalPurch = array_sum(array_map(function($p) {
    return (isset($p['status'], $p['total']) && $p['status'] === 'selesai')
        ? $p['total']
        : 0;
}, $purchases));
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
$products  = $pdo->query("SELECT p.*, u.symbol FROM products p LEFT JOIN units u ON p.unit_id=u.id WHERE p.is_active=1 ORDER BY p.name")->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-truck-loading" style="color:var(--orange);margin-right:8px"></i>Daftar Pembelian</span>
    <button class="btn btn-primary" onclick="openModal('modalPurch')"><i class="fas fa-plus"></i> Catat Pembelian</button>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <input type="text" name="q" class="form-control" placeholder="Cari invoice / supplier..." value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:200px">
    <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:140px">
    <input type="date" name="to"   class="form-control" value="<?= $to ?>"   style="width:140px">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
  </form>

  <div style="margin-bottom:12px;padding:12px;background:var(--orange-dim);border:1px solid var(--orange);border-radius:var(--radius-sm)">
    <span style="font-size:12px;color:var(--text-secondary)">Total Pembelian Periode Ini</span><br>
    <strong style="color:var(--orange);font-size:18px"><?= formatRupiah($totalPurch) ?></strong>
    <span style="color:var(--text-secondary);font-size:13px;margin-left:12px"><?= count($purchases) ?> transaksi</span>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Invoice</th><th>Tanggal</th><th>Supplier</th><th>Total</th><th>Dibayar</th><th>Metode</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada data pembelian</td></tr>
        <?php else: foreach ($purchases as $p): ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px"><?= $p['invoice_no'] ?></span></td>
          <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($p['purchase_date'])) ?></td>
          <td><?= htmlspecialchars($p['sup_name']) ?></td>
          <td class="amount" style="font-weight:700"><?= formatRupiah($p['total']) ?></td>
          <td class="amount"><?= formatRupiah($p['paid']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($p['payment_method']) ?></span></td>
          <td><span class="badge badge-<?= $p['status']==='selesai'?'success':($p['status']==='batal'?'danger':'warning') ?>"><?= ucfirst($p['status']) ?></span></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="viewDetail(<?= $p['id'] ?>)"><i class="fas fa-eye"></i> Detail</button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Input Pembelian -->
<div class="modal-overlay" id="modalPurch">
  <div class="modal" style="min-width:750px;max-width:950px">
    <div class="modal-header">
      <span class="modal-title">Catat Pembelian Baru</span>
      <button class="modal-close" onclick="closeModal('modalPurch')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="save_purchase" value="1">
      <div class="modal-body">
        <div class="form-grid" style="margin-bottom:16px">
          <div class="form-group">
            <label class="form-label">Supplier *</label>
            <select name="supplier_id" class="form-control" required>
              <option value="">-- Pilih Supplier --</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Beli *</label>
            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Metode Bayar</label>
            <select name="payment_method" class="form-control" id="pay-method-purch">
              <option value="tunai">💵 Tunai</option>
              <option value="transfer">🏦 Transfer</option>
              <option value="hutang">📄 Hutang</option>
            </select>
          </div>
          <div class="form-group" id="paid-group">
            <label class="form-label">Jumlah Dibayar</label>
            <input type="text" name="paid" id="purch-paid" class="form-control" placeholder="0" oninput="this.value=this.value.replace(/\D/g,'')">
          </div>
        </div>

        <!-- Product rows -->
        <div style="margin-bottom:8px;font-weight:700;font-size:13px;color:var(--text-secondary)">ITEM PEMBELIAN</div>
        <table style="width:100%;border-collapse:collapse" id="purch-table">
          <thead>
            <tr style="background:var(--bg-elevated)">
              <th style="padding:8px;font-size:11px;color:var(--text-secondary);text-align:left">Produk</th>
              <th style="padding:8px;font-size:11px;color:var(--text-secondary);text-align:right;width:100px">Qty</th>
              <th style="padding:8px;font-size:11px;color:var(--text-secondary);text-align:right;width:150px">Harga Beli</th>
              <th style="padding:8px;font-size:11px;color:var(--text-secondary);text-align:right;width:140px">Subtotal</th>
              <th style="width:36px"></th>
            </tr>
          </thead>
          <tbody id="purch-body"></tbody>
          <tfoot>
            <tr style="font-weight:700;background:var(--bg-elevated)">
              <td colspan="3" style="padding:10px;text-align:right">TOTAL</td>
              <td style="padding:10px;text-align:right;font-family:monospace;color:var(--accent)" id="purch-total">Rp 0</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="addPurchRow()">
          <i class="fas fa-plus"></i> Tambah Produk
        </button>

        <div class="form-group" style="margin-top:14px">
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalPurch')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan & Update Stok</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal-overlay" id="modalDetail">
  <div class="modal" style="min-width:580px">
    <div class="modal-header"><span class="modal-title">Detail Pembelian</span><button class="modal-close" onclick="closeModal('modalDetail')">&times;</button></div>
    <div class="modal-body" id="detail-body"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div></div>
  </div>
</div>

<script>
/* ===== DATA PRODUK (FIX PHP 7.3) ===== */
const prodOptions = <?= json_encode(array_map(function($p) {
    return [
        'id'    => $p['id'],
        'code'  => $p['code'],
        'name'  => $p['name'],
        'price' => $p['buy_price'],
        'sym'   => isset($p['symbol']) ? $p['symbol'] : ''
    ];
}, $products)) ?>;

/* ===== BUILD SELECT ===== */
function buildProdSelect(name, val='') {
  let html = `<select name="${name}" class="form-control" onchange="updatePurchRow(this)" style="font-size:12px">
    <option value="">-- Pilih Produk --</option>`;

  prodOptions.forEach(function(p) {
    html += `<option value="${p.id}" data-price="${p.price}" ${p.id==val?'selected':''}>
                ${p.code} - ${p.name}
            </option>`;
  });

  return html + '</select>';
}

/* ===== TAMBAH ROW ===== */
function addPurchRow() {
  const tbody = document.getElementById('purch-body');
  if (!tbody) return;

  const row = document.createElement('tr');
  row.innerHTML = `
    <td style="padding:4px">${buildProdSelect('product_id[]')}</td>
    <td style="padding:4px">
        <input type="number" name="quantity[]" class="form-control"
        value="1" min="0.01" step="0.01"
        oninput="calcPurchTotal()"
        style="font-size:12px;text-align:right">
    </td>
    <td style="padding:4px">
        <input type="text" name="buy_price[]"
        class="form-control row-price"
        placeholder="0"
        oninput="formatNumber(this); calcPurchTotal()"
        style="font-size:12px;text-align:right">
    </td>
    <td class="row-subtotal"
        style="padding:4px;text-align:right;font-family:monospace;font-size:12px">
        Rp 0
    </td>
    <td style="padding:4px">
        <button type="button" class="btn btn-danger btn-icon btn-sm"
        onclick="removeRow(this)">
            <i class="fas fa-times"></i>
        </button>
    </td>
  `;

  tbody.appendChild(row);
}

/* ===== FORMAT ANGKA ===== */
function formatNumber(el) {
  let val = el.value.replace(/\D/g,'');
  el.value = val ? parseInt(val).toLocaleString('id-ID') : '';
}

/* ===== HAPUS ROW ===== */
function removeRow(btn) {
  const row = btn.closest('tr');
  if (row) row.remove();
  calcPurchTotal();
}

/* ===== UPDATE ROW ===== */
function updatePurchRow(sel) {
  const opt = sel.options[sel.selectedIndex];
  const price = opt ? (opt.dataset.price || 0) : 0;

  const row = sel.closest('tr');
  if (!row) return;

  const priceInput = row.querySelector('.row-price');
  if (priceInput) {
    priceInput.value = parseInt(price).toLocaleString('id-ID');
  }

  calcPurchTotal();
}

/* ===== HITUNG TOTAL ===== */
function calcPurchTotal() {
  let total = 0;

  document.querySelectorAll('#purch-body tr').forEach(function(row) {
    const qtyEl   = row.querySelector('[name="quantity[]"]');
    const priceEl = row.querySelector('.row-price');

    const qty   = qtyEl ? parseFloat(qtyEl.value) : 0;
    const price = priceEl ? parseFloat(priceEl.value.replace(/\D/g,'')) : 0;

    const sub = qty * price;

    const subEl = row.querySelector('.row-subtotal');
    if (subEl) {
      subEl.textContent = 'Rp ' + sub.toLocaleString('id-ID');
    }

    total += sub;
  });

  const totalEl = document.getElementById('purch-total');
  if (totalEl) {
    totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
  }

  /* Auto isi pembayaran */
  const method = document.getElementById('pay-method-purch');
  const paid   = document.getElementById('purch-paid');

  if (method && paid && method.value === 'tunai') {
    paid.value = total;
  }
}

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', function() {
  addPurchRow();

  const method = document.getElementById('pay-method-purch');
  const paidGroup = document.getElementById('paid-group');

  if (method && paidGroup) {
    method.addEventListener('change', function() {
      paidGroup.style.display = this.value === 'hutang' ? 'none' : '';
    });
  }
});

/* ===== DETAIL MODAL ===== */
async function viewDetail(id) {
  openModal('modalDetail');

  const body = document.getElementById('detail-body');
  if (body) {
    body.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
  }

  try {
    const r = await fetch('purchases.php?detail=' + id);
    const html = await r.text();

    if (body) body.innerHTML = html;
  } catch (e) {
    if (body) body.innerHTML = 'Gagal load data';
  }
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
