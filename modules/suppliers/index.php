<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle  = 'Manajemen Supplier';
$activePage = 'suppliers';

/* ── CRUD ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['code']), trim($_POST['name']),
            trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? ''),
        ];
     if ($id) {

    $stmt = $pdo->prepare("
        UPDATE suppliers 
        SET code = ?, name = ?, phone = ?, email = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute(array_merge($data, [$id]));

    flash('success', 'Data supplier berhasil diperbarui.');

} else {

    $stmt = $pdo->prepare("
        INSERT INTO suppliers (code, name, phone, email, address)
        VALUES (?,?,?,?,?)
    ");
    $stmt->execute($data);

    flash('success', 'Supplier baru berhasil ditambahkan.');
}

} elseif ($action === 'delete') {

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE suppliers SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        flash('success', 'Supplier berhasil dihapus.');
    }
}

header('Location: index.php');
exit;

/* ── Detail AJAX ──────────────────────────────────────── */
if (isset($_GET['detail'])) {
    $sid    = (int)$_GET['detail'];
    $purch  = $pdo->query("SELECT * FROM purchases WHERE supplier_id=$sid ORDER BY purchase_date DESC LIMIT 20")->fetchAll();
    $totBeli= $pdo->query("SELECT COALESCE(SUM(total),0) FROM purchases WHERE supplier_id=$sid AND status='selesai'")->fetchColumn();
    $totTx  = $pdo->query("SELECT COUNT(*) FROM purchases WHERE supplier_id=$sid")->fetchColumn();
    header('Content-Type: text/html');
    echo '<div>';
    echo "<div style='display:flex;gap:20px;margin-bottom:16px'>";
    echo "<div><div style='font-size:12px;color:var(--text-secondary)'>Total Pembelian</div><div style='font-size:20px;font-weight:800;color:var(--orange)'>".formatRupiah($totBeli)."</div></div>";
    echo "<div><div style='font-size:12px;color:var(--text-secondary)'>Total Transaksi</div><div style='font-size:20px;font-weight:800'>$totTx</div></div>";
    echo "</div>";
    if (empty($purch)) { echo '<p style="color:var(--text-muted)">Belum ada pembelian</p>'; }
    else {
        echo '<table class="data-table"><thead><tr><th>Invoice</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead><tbody>';
        foreach ($purch as $p) {
            $badge = $p['status']==='selesai'?'success':($p['status']==='batal'?'danger':'warning');
            echo "<tr><td><span style='font-family:monospace;font-size:12px'>{$p['invoice_no']}</span></td>";
            echo "<td style='font-size:12px'>".date('d/m/Y H:i',strtotime($p['purchase_date']))."</td>";
            echo "<td class='amount'>".formatRupiah($p['total'])."</td>";
            echo "<td><span class='badge badge-$badge'>".ucfirst($p['status'])."</span></td></tr>";
        }
        echo '</tbody></table>';
    }
    echo '</div>';
    exit;
}
}

/* ── Gen code ─────────────────────────────────────────── */
if (isset($_GET['gen_code'])) {
    $last = $pdo->query("SELECT code FROM suppliers ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 3) + 1 : 1;
    echo json_encode(['code' => 'SUP' . str_pad($num, 4, '0', STR_PAD_LEFT)]);
    exit;
}

$q = trim($_GET['q'] ?? '');
$where  = "WHERE is_active=1";
$params = [];
if ($q) { $where .= " AND (name LIKE ? OR code LIKE ? OR phone LIKE ?)"; $params=array_fill(0,3,"%$q%"); }
$stmt = $pdo->prepare("SELECT * FROM suppliers $where ORDER BY name");
$stmt->execute($params); $suppliers = $stmt->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-industry" style="color:var(--orange);margin-right:8px"></i>Daftar Supplier (<?= count($suppliers) ?>)</span>
    <button class="btn btn-primary" onclick="newSupplier()"><i class="fas fa-plus"></i> Tambah Supplier</button>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px">
    <input type="text" name="q" class="form-control" placeholder="Cari nama, kode, telepon..." value="<?= htmlspecialchars($q) ?>" style="flex:1">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Cari</button>
  </form>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Kode</th><th>Nama Supplier</th><th>Telepon</th><th>Email</th><th>Alamat</th><th>Saldo Hutang</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($suppliers)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada supplier</td></tr>
        <?php else: foreach ($suppliers as $s): ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px;color:var(--orange)"><?= htmlspecialchars($s['code']) ?></span></td>
          <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
          <td><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($s['email'] ?: '—') ?></td>
          <td style="font-size:12px;color:var(--text-secondary);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($s['address'] ?: '—') ?></td>
          <td class="amount" style="color:<?= $s['balance'] > 0 ? 'var(--red)' : 'var(--text-secondary)' ?>"><?= formatRupiah($s['balance']) ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn btn-blue btn-sm" onclick="viewDetail(<?= $s['id'] ?>,'<?= addslashes($s['name']) ?>')" title="Riwayat"><i class="fas fa-history"></i></button>
            <button class="btn btn-secondary btn-sm" onclick="editSupplier(<?= htmlspecialchars(json_encode($s)) ?>)" title="Edit"><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="Hapus supplier <?= htmlspecialchars($s['name']) ?>?"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form -->
<div class="modal-overlay" id="modalSup">
  <div class="modal" style="min-width:540px">
    <div class="modal-header">
      <span class="modal-title" id="sup-modal-title">Tambah Supplier</span>
      <button class="modal-close" onclick="closeModal('modalSup')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="sup-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Kode Supplier *</label>
            <div style="display:flex;gap:8px">
              <input type="text" name="code" id="sup-code" class="form-control" required>
              <button type="button" class="btn btn-secondary btn-sm" onclick="genCode()"><i class="fas fa-magic"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Supplier *</label>
            <input type="text" name="name" id="sup-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon</label>
            <input type="text" name="phone" id="sup-phone" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="sup-email" class="form-control">
          </div>
        </div>
        <div class="form-group" style="margin-top:12px">
          <label class="form-label">Alamat</label>
          <textarea name="address" id="sup-addr" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalSup')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal-overlay" id="modalDetail">
  <div class="modal" style="min-width:620px">
    <div class="modal-header">
      <span class="modal-title" id="detail-title">Riwayat Pembelian</span>
      <button class="modal-close" onclick="closeModal('modalDetail')">&times;</button>
    </div>
    <div class="modal-body" id="detail-body"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div></div>
  </div>
</div>

<script>
function newSupplier() {
  document.getElementById('sup-modal-title').textContent = 'Tambah Supplier';
  ['sup-id','sup-code','sup-name','sup-phone','sup-email','sup-addr'].forEach(id=>document.getElementById(id).value='');
  openModal('modalSup');
}
function editSupplier(s) {
  document.getElementById('sup-modal-title').textContent = 'Edit Supplier';
  document.getElementById('sup-id').value    = s.id;
  document.getElementById('sup-code').value  = s.code;
  document.getElementById('sup-name').value  = s.name;
  document.getElementById('sup-phone').value = s.phone||'';
  document.getElementById('sup-email').value = s.email||'';
  document.getElementById('sup-addr').value  = s.address||'';
  openModal('modalSup');
}
async function genCode(){
  const r=await fetch('index.php?gen_code=1');
  const d=await r.json();
  document.getElementById('sup-code').value=d.code;
}
async function viewDetail(id,name){
  document.getElementById('detail-title').textContent='Riwayat — '+name;
  document.getElementById('detail-body').innerHTML='<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
  openModal('modalDetail');
  const r=await fetch('index.php?detail='+id);
  document.getElementById('detail-body').innerHTML=await r.text();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
