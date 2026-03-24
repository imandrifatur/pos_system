<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Manajemen Produk';
$activePage = 'products';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save') {

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // Validasi & sanitasi input
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $unit_id     = !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
        $code        = isset($_POST['code']) ? trim($_POST['code']) : '';
        $name        = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        $buy_price  = isset($_POST['buy_price']) ? (float)str_replace(',', '', $_POST['buy_price']) : 0;
        $sell_price = isset($_POST['sell_price']) ? (float)str_replace(',', '', $_POST['sell_price']) : 0;
        $stock      = isset($_POST['stock']) ? (float)$_POST['stock'] : 0;
        $min_stock  = isset($_POST['min_stock']) ? (float)$_POST['min_stock'] : 0;

        // Validasi sederhana
        if ($code === '' || $name === '') {
            flash('error', 'Kode dan Nama produk wajib diisi.');
            header('Location: products.php');
            exit;
        }

        $data = [
            $category_id,
            $unit_id,
            $code,
            $name,
            $description,
            $buy_price,
            $sell_price,
            $stock,
            $min_stock
        ];

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE products SET
                    category_id = ?,
                    unit_id     = ?,
                    code        = ?,
                    name        = ?,
                    description = ?,
                    buy_price   = ?,
                    sell_price  = ?,
                    stock       = ?,
                    min_stock   = ?
                WHERE id = ?
            ");
            $stmt->execute(array_merge($data, [$id]));

            flash('success', 'Produk berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products
                (category_id, unit_id, code, name, description, buy_price, sell_price, stock, min_stock)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute($data);

            flash('success', 'Produk berhasil ditambahkan.');
        }

    } elseif ($action === 'delete') {

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);

            flash('success', 'Produk berhasil dihapus.');
        }
    }

    header('Location: products.php');
    exit;
}
$search = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$where = "WHERE p.is_active=1";
$params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR p.code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catFilter) { $where .= " AND p.category_id=?"; $params[] = $catFilter; }

$products  = $pdo->prepare("SELECT p.*, c.name as cat_name, u.symbol as unit_sym FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN units u ON p.unit_id=u.id $where ORDER BY p.name");
$products->execute($params); $products = $products->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$units      = $pdo->query("SELECT * FROM units ORDER BY name")->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Daftar Produk (<?= count($products) ?>)</span>
    <button class="btn btn-primary" onclick="openModal('modalProduct');resetForm()">
      <i class="fas fa-plus"></i> Tambah Produk
    </button>
  </div>

  <!-- Filter -->
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px">
    <input type="text" name="q" class="form-control" placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>" style="flex:1">
    <select name="cat" class="form-control" style="width:180px">
      <option value="">Semua Kategori</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Cari</button>
  </form>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Harga Beli</th><th>Harga Jual</th><th>Stok</th><th>Min Stok</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada produk ditemukan</td></tr>
        <?php else: foreach ($products as $p): $lowStock = $p['stock'] <= $p['min_stock']; ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['code']) ?></span></td>
          <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
          <td class="amount"><?= formatRupiah($p['buy_price']) ?></td>
          <td class="amount"><?= formatRupiah($p['sell_price']) ?></td>
          <td>
            <span class="badge badge-<?= $lowStock ? 'danger' : 'success' ?>">
              <?= $p['stock'] ?> <?= $p['unit_sym'] ?>
            </span>
          </td>
          <td><?= $p['min_stock'] ?></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="Hapus produk ini?"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Product -->
<div class="modal-overlay" id="modalProduct">
  <div class="modal" style="min-width:600px">
    <div class="modal-header">
      <span class="modal-title" id="modal-title">Tambah Produk</span>
      <button class="modal-close" onclick="closeModal('modalProduct')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="prod-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Kode Produk *</label>
            <input type="text" name="code" id="prod-code" class="form-control" required placeholder="PRD001">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Produk *</label>
            <input type="text" name="name" id="prod-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <select name="category_id" id="prod-cat" class="form-control">
              <option value="">-- Pilih Kategori --</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Satuan</label>
            <select name="unit_id" id="prod-unit" class="form-control">
              <option value="">-- Pilih Satuan --</option>
              <?php foreach ($units as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Harga Beli</label>
            <input type="number" name="buy_price" id="prod-buy" class="form-control" value="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Harga Jual *</label>
            <input type="number" name="sell_price" id="prod-sell" class="form-control" value="0" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Stok Awal</label>
            <input type="number" name="stock" id="prod-stock" class="form-control" value="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Minimum Stok</label>
            <input type="number" name="min_stock" id="prod-minstk" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="form-group" style="margin-top:12px">
          <label class="form-label">Deskripsi</label>
          <textarea name="description" id="prod-desc" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalProduct')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function resetForm() {
  document.getElementById('modal-title').textContent = 'Tambah Produk';
  ['prod-id','prod-code','prod-name','prod-desc'].forEach(id => document.getElementById(id).value = '');
  ['prod-buy','prod-sell','prod-stock','prod-minstk'].forEach(id => document.getElementById(id).value = '0');
  ['prod-cat','prod-unit'].forEach(id => document.getElementById(id).value = '');
}

function editProduct(p) {
  document.getElementById('modal-title').textContent = 'Edit Produk';
  document.getElementById('prod-id').value   = p.id;
  document.getElementById('prod-code').value = p.code;
  document.getElementById('prod-name').value = p.name;
  document.getElementById('prod-desc').value = p.description || '';
  document.getElementById('prod-buy').value  = p.buy_price;
  document.getElementById('prod-sell').value = p.sell_price;
  document.getElementById('prod-stock').value= p.stock;
  document.getElementById('prod-minstk').value= p.min_stock;
  document.getElementById('prod-cat').value  = p.category_id || '';
  document.getElementById('prod-unit').value = p.unit_id || '';
  openModal('modalProduct');
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
