<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle  = 'Manajemen Stok';
$activePage = 'stock';

/* ── Stock Adjustment ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $prodId = (int)$_POST['product_id'];
    $type   = $_POST['adj_type']; // masuk | keluar | koreksi
    $qty    = (float)$_POST['quantity'];
    $notes  = trim($_POST['notes'] ?? '');

    $prod = $pdo->query("SELECT * FROM products WHERE id=$prodId")->fetch();
    if (!$prod) { flash('error','Produk tidak ditemukan'); header('Location: stock.php'); exit; }

    $stockBefore = $prod['stock'];
    if ($type === 'koreksi') {
        $stockAfter = $qty; // qty = stok baru
    } elseif ($type === 'masuk') {
        $stockAfter = $stockBefore + $qty;
    } else { // keluar
        $stockAfter = max(0, $stockBefore - $qty);
    }

    $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$stockAfter, $prodId]);
    $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,stock_before,stock_after,reference_type,notes,user_id)
                   VALUES (?,?,?,?,?,'manual',?,?)")
        ->execute([$prodId, $type, $qty, $stockBefore, $stockAfter, $notes, $_SESSION['user_id']]);
    flash('success', "Stok {$prod['name']} diperbarui: $stockBefore → $stockAfter");
    header('Location: stock.php'); exit;
}

/* ── Filters ──────────────────────────────────────────── */
$q       = trim($_GET['q'] ?? '');
$catF    = (int)($_GET['cat'] ?? 0);
$stockF  = $_GET['stock'] ?? ''; // '' | low | out
$tab     = $_GET['tab']   ?? 'stock'; // stock | movement

$where  = "WHERE p.is_active=1";
$params = [];
if ($q)     { $where .= " AND (p.name LIKE ? OR p.code LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
if ($catF)  { $where .= " AND p.category_id=?"; $params[]=$catF; }
if ($stockF === 'low')  $where .= " AND p.stock <= p.min_stock AND p.stock > 0";
if ($stockF === 'out')  $where .= " AND p.stock = 0";

$products = $pdo->prepare("SELECT p.*, c.name as cat_name, u.symbol
    FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN units u ON p.unit_id=u.id
    $where ORDER BY p.name");
$products->execute($params); $products = $products->fetchAll();

/* ── Movement history ─────────────────────────────────── */
$movements = [];
if ($tab === 'movement') {
    $from    = $_GET['from'] ?? date('Y-m-01');
    $to      = $_GET['to']   ?? date('Y-m-d');
    $mStmt = $pdo->prepare("SELECT sm.*, p.name as prod_name, p.code as prod_code, u.full_name
        FROM stock_movements sm JOIN products p ON sm.product_id=p.id JOIN users u ON sm.user_id=u.id
        WHERE DATE(sm.created_at) BETWEEN ? AND ?
        ORDER BY sm.created_at DESC LIMIT 200");
    $mStmt->execute([$from, $to]); $movements = $mStmt->fetchAll();
}

/* ── Stats ────────────────────────────────────────────── */
$totalProducts = count($products);

$lowStockCount = count(array_filter($products, function($p) {
    return isset($p['stock'], $p['min_stock']) &&
           $p['stock'] <= $p['min_stock'] &&
           $p['stock'] > 0;
}));

$outStockCount = count(array_filter($products, function($p) {
    return isset($p['stock']) && $p['stock'] == 0;
}));

$totalStockVal = array_sum(array_map(function($p) {
    return (isset($p['stock'], $p['buy_price']))
        ? $p['stock'] * $p['buy_price']
        : 0;
}, $products));
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allProducts = $pdo->query("SELECT id,code,name,stock FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-boxes"></i></div><div><div class="stat-label">Total Produk</div><div class="stat-value"><?= $totalProducts ?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-label">Stok Menipis</div><div class="stat-value"><?= $lowStockCount ?></div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div><div class="stat-label">Stok Habis</div><div class="stat-value"><?= $outStockCount ?></div></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-warehouse"></i></div><div><div class="stat-label">Nilai Stok</div><div class="stat-value" style="font-size:14px"><?= formatRupiah($totalStockVal) ?></div></div></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:1px solid var(--border)">
  <a href="?tab=stock<?= $q?"&q=$q":'' ?>" class="btn <?= $tab!=='movement'?'btn-primary':'btn-secondary' ?>" style="border-radius:var(--radius-sm) var(--radius-sm) 0 0">
    <i class="fas fa-boxes"></i> Posisi Stok
  </a>
  <a href="?tab=movement" class="btn <?= $tab==='movement'?'btn-primary':'btn-secondary' ?>" style="border-radius:var(--radius-sm) var(--radius-sm) 0 0;margin-left:4px">
    <i class="fas fa-exchange-alt"></i> Mutasi Stok
  </a>
  <div style="flex:1"></div>
  <button class="btn btn-primary" onclick="openModal('modalAdj')"><i class="fas fa-sliders-h"></i> Penyesuaian Stok</button>
</div>

<?php if ($tab !== 'movement'): ?>
<!-- Stock View -->
<div class="card">
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <input type="hidden" name="tab" value="stock">
    <input type="text" name="q" class="form-control" placeholder="Cari produk..." value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:200px">
    <select name="cat" class="form-control" style="width:160px">
      <option value="">Semua Kategori</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $catF==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="stock" class="form-control" style="width:140px">
      <option value="" <?= $stockF===''?'selected':'' ?>>Semua Stok</option>
      <option value="low" <?= $stockF==='low'?'selected':'' ?>>⚠️ Stok Menipis</option>
      <option value="out" <?= $stockF==='out'?'selected':'' ?>>❌ Stok Habis</option>
    </select>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Filter</button>
  </form>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Satuan</th><th style="text-align:right">Stok</th><th style="text-align:right">Min Stok</th><th style="text-align:right">Harga Beli</th><th style="text-align:right">Nilai Stok</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada produk</td></tr>
        <?php else:
          foreach ($products as $p):
            $pct   = $p['min_stock'] > 0 ? min(100, ($p['stock'] / $p['min_stock']) * 100) : 100;
            $color = $p['stock'] == 0 ? 'var(--red)' : ($p['stock'] <= $p['min_stock'] ? 'var(--orange)' : 'var(--green)');
            $badge = $p['stock'] == 0 ? 'danger' : ($p['stock'] <= $p['min_stock'] ? 'warning' : 'success');
            $label = $p['stock'] == 0 ? 'Habis' : ($p['stock'] <= $p['min_stock'] ? 'Menipis' : 'Normal');
        ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['code']) ?></span></td>
          <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td style="font-size:12px"><?= htmlspecialchars($p['cat_name']??'—') ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($p['symbol']??'—') ?></td>
          <td style="text-align:right">
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px">
              <div style="width:60px;background:var(--bg-elevated);border-radius:4px;height:6px">
                <div style="width:<?= $pct ?>%;background:<?= $color ?>;height:100%;border-radius:4px"></div>
              </div>
              <span style="font-family:monospace;font-weight:700;color:<?= $color ?>"><?= number_format($p['stock'],2) ?></span>
            </div>
          </td>
          <td style="text-align:right;font-family:monospace"><?= number_format($p['min_stock'],2) ?></td>
          <td style="text-align:right" class="amount"><?= formatRupiah($p['buy_price']) ?></td>
          <td style="text-align:right" class="amount"><?= formatRupiah($p['stock'] * $p['buy_price']) ?></td>
          <td><span class="badge badge-<?= $badge ?>"><?= $label ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($products)): ?>
      <tfoot>
        <tr style="background:var(--bg-elevated);font-weight:700">
          <td colspan="7" style="padding:10px 14px;text-align:right">TOTAL NILAI STOK</td>
          <td style="padding:10px 14px;text-align:right;font-family:monospace;color:var(--accent)"><?= formatRupiah($totalStockVal) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php else: ?>
<!-- Movement View -->
<div class="card">
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px">
    <input type="hidden" name="tab" value="movement">
    <input type="date" name="from" class="form-control" value="<?= $_GET['from'] ?? date('Y-m-01') ?>" style="width:140px">
    <input type="date" name="to"   class="form-control" value="<?= $_GET['to']   ?? date('Y-m-d') ?>"  style="width:140px">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
  </form>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Waktu</th><th>Produk</th><th>Tipe</th><th>Referensi</th><th style="text-align:right">Qty</th><th style="text-align:right">Stok Sebelum</th><th style="text-align:right">Stok Sesudah</th><th>Input oleh</th></tr>
      </thead>
      <tbody>
        <?php if (empty($movements)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada mutasi</td></tr>
        <?php else: foreach ($movements as $m):
          $typeColor = $m['type']==='masuk'?'var(--green)':($m['type']==='keluar'?'var(--red)':'var(--orange)');
          $typeIcon  = $m['type']==='masuk'?'arrow-down':'arrow-up';
        ?>
        <tr>
          <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($m['prod_name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);font-family:monospace"><?= $m['prod_code'] ?></div>
          </td>
          <td>
            <span class="badge badge-<?= $m['type']==='masuk'?'success':($m['type']==='keluar'?'danger':'warning') ?>">
              <i class="fas fa-<?= $typeIcon ?>"></i> <?= ucfirst($m['type']) ?>
            </span>
          </td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($m['reference_type']??'manual') ?> <?= $m['notes'] ? '— '.$m['notes'] : '' ?></td>
          <td style="text-align:right;font-family:monospace;font-weight:700;color:<?= $typeColor ?>">
            <?= $m['type']==='masuk'?'+':'-' ?><?= number_format($m['quantity'],2) ?>
          </td>
          <td style="text-align:right;font-family:monospace"><?= number_format($m['stock_before'],2) ?></td>
          <td style="text-align:right;font-family:monospace;font-weight:600"><?= number_format($m['stock_after'],2) ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($m['full_name']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Modal Penyesuaian -->
<div class="modal-overlay" id="modalAdj">
  <div class="modal" style="min-width:460px">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-sliders-h" style="color:var(--accent)"></i> Penyesuaian Stok</span>
      <button class="modal-close" onclick="closeModal('modalAdj')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="adjust_stock" value="1">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px">
          <label class="form-label">Produk *</label>
          <select name="product_id" class="form-control" required id="adj-prod" onchange="showCurrentStock(this)">
            <option value="">-- Pilih Produk --</option>
            <?php foreach ($allProducts as $p): ?>
            <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>"><?= htmlspecialchars($p['code'].' - '.$p['name']) ?> (Stok: <?= $p['stock'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="stock-info" style="display:none;background:var(--bg-elevated);border-radius:var(--radius-sm);padding:10px;margin-bottom:12px;font-size:13px">
          Stok saat ini: <strong id="current-stock" style="color:var(--accent)">0</strong>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label class="form-label">Tipe Penyesuaian *</label>
          <select name="adj_type" class="form-control" id="adj-type" onchange="updateAdjLabel()">
            <option value="masuk">➕ Stok Masuk (tambah)</option>
            <option value="keluar">➖ Stok Keluar (kurang)</option>
            <option value="koreksi">🔄 Koreksi (set stok baru)</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label class="form-label" id="adj-qty-label">Jumlah *</label>
          <input type="number" name="quantity" class="form-control" min="0" step="0.01" required placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">Catatan / Alasan</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Stok opname, retur, kerusakan..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAdj')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function showCurrentStock(sel) {
  const opt = sel.options[sel.selectedIndex];
  const stock = opt.dataset.stock || 0;
  document.getElementById('current-stock').textContent = stock;
  document.getElementById('stock-info').style.display = sel.value ? '' : 'none';
}
function updateAdjLabel() {
  const type = document.getElementById('adj-type').value;
  const labels = { masuk:'Jumlah Tambah', keluar:'Jumlah Kurang', koreksi:'Stok Baru (Hasil Opname)' };
  document.getElementById('adj-qty-label').textContent = labels[type] + ' *';
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
