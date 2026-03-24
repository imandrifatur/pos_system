<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle  = 'Manajemen Pelanggan';
$activePage = 'customers';

/* ── CRUD ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['code']);
        $name = trim($_POST['name']);
        $phone= trim($_POST['phone'] ?? '');
        $email= trim($_POST['email'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $limit= (float)preg_replace('/\D/', '', $_POST['credit_limit'] ?? '0');

        if ($id) {
            $pdo->prepare("UPDATE customers SET code=?,name=?,phone=?,email=?,address=?,credit_limit=? WHERE id=?")
                ->execute([$code,$name,$phone,$email,$addr,$limit,$id]);
            flash('success', 'Data pelanggan berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO customers (code,name,phone,email,address,credit_limit) VALUES (?,?,?,?,?,?)")
                ->execute([$code,$name,$phone,$email,$addr,$limit]);
            flash('success', 'Pelanggan baru berhasil ditambahkan.');
        }

    } elseif ($action === 'delete') {
        $pdo->prepare("UPDATE customers SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
        flash('success', 'Pelanggan berhasil dihapus.');
    }
    header('Location: index.php'); exit;
}

/* ── View detail via AJAX ─────────────────────────────── */
if (isset($_GET['detail'])) {
    $cid = (int)$_GET['detail'];
    $cust = $pdo->query("SELECT * FROM customers WHERE id=$cid")->fetch();
    $txs  = $pdo->query("SELECT * FROM sales WHERE customer_id=$cid ORDER BY sale_date DESC LIMIT 20")->fetchAll();
    $totSpend = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE customer_id=$cid AND status='selesai'")->fetchColumn();
    $txCount  = $pdo->query("SELECT COUNT(*) FROM sales WHERE customer_id=$cid")->fetchColumn();
    header('Content-Type: text/html');
    echo '<div style="padding:4px">';
    echo '<div style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap">';
    echo '<div><div style="font-size:12px;color:var(--text-secondary)">Total Belanja</div><div style="font-size:20px;font-weight:800;color:var(--accent)">'.formatRupiah($totSpend).'</div></div>';
    echo '<div><div style="font-size:12px;color:var(--text-secondary)">Total Transaksi</div><div style="font-size:20px;font-weight:800">'.$txCount.'</div></div>';
    echo '</div>';
    if (empty($txs)) { echo '<p style="color:var(--text-muted)">Belum ada transaksi</p>'; }
    else {
        echo '<table class="data-table"><thead><tr><th>Invoice</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead><tbody>';
        foreach ($txs as $t) {
            $badge = $t['status']==='selesai'?'success':($t['status']==='batal'?'danger':'warning');
            echo "<tr><td><span style='font-family:monospace;font-size:12px'>{$t['invoice_no']}</span></td>";
            echo "<td style='font-size:12px'>".date('d/m/Y H:i',strtotime($t['sale_date']))."</td>";
            echo "<td class='amount'>".formatRupiah($t['total'])."</td>";
            echo "<td><span class='badge badge-$badge'>".ucfirst($t['status'])."</span></td></tr>";
        }
        echo '</tbody></table>';
    }
    echo '</div>';
    exit;
}

/* ── Generate customer code ───────────────────────────── */
if (isset($_GET['gen_code'])) {
    $last = $pdo->query("SELECT code FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 4) + 1 : 1;
    echo json_encode(['code' => 'CUST' . str_pad($num, 4, '0', STR_PAD_LEFT)]);
    exit;
}

$q = trim($_GET['q'] ?? '');
$where  = "WHERE is_active=1";
$params = [];
if ($q) { $where .= " AND (name LIKE ? OR code LIKE ? OR phone LIKE ?)"; $params=array_fill(0,3,"%$q%"); }

$stmt = $pdo->prepare("SELECT * FROM customers $where ORDER BY name");
$stmt->execute($params); $customers = $stmt->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-users" style="color:var(--blue);margin-right:8px"></i>Daftar Pelanggan (<?= count($customers) ?>)</span>
    <button class="btn btn-primary" onclick="newCustomer()"><i class="fas fa-plus"></i> Tambah Pelanggan</button>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px">
    <input type="text" name="q" class="form-control" placeholder="Cari nama, kode, atau telepon..." value="<?= htmlspecialchars($q) ?>" style="flex:1">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Cari</button>
  </form>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Kode</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Limit Kredit</th><th>Saldo Piutang</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($customers)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada pelanggan</td></tr>
        <?php else: foreach ($customers as $c): ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px;color:var(--accent)"><?= htmlspecialchars($c['code']) ?></span></td>
          <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
          <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
          <td class="amount"><?= formatRupiah($c['credit_limit']) ?></td>
          <td class="amount" style="color:<?= $c['balance'] > 0 ? 'var(--red)' : 'var(--text-secondary)' ?>"><?= formatRupiah($c['balance']) ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn btn-blue btn-sm" onclick="viewDetail(<?= $c['id'] ?>,'<?= addslashes($c['name']) ?>')" title="Riwayat"><i class="fas fa-history"></i></button>
            <button class="btn btn-secondary btn-sm" onclick="editCustomer(<?= htmlspecialchars(json_encode($c)) ?>)" title="Edit"><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="Hapus pelanggan <?= htmlspecialchars($c['name']) ?>?"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form -->
<div class="modal-overlay" id="modalCust">
  <div class="modal" style="min-width:540px">
    <div class="modal-header">
      <span class="modal-title" id="cust-modal-title">Tambah Pelanggan</span>
      <button class="modal-close" onclick="closeModal('modalCust')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="cust-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Kode Pelanggan *</label>
            <div style="display:flex;gap:8px">
              <input type="text" name="code" id="cust-code" class="form-control" required placeholder="CUST0001">
              <button type="button" class="btn btn-secondary btn-sm" onclick="genCode()" title="Generate otomatis"><i class="fas fa-magic"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="name" id="cust-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon</label>
            <input type="text" name="phone" id="cust-phone" class="form-control" placeholder="08xxxxxxxxxx">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="cust-email" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Limit Kredit</label>
            <input type="text" name="credit_limit" id="cust-limit" class="form-control" placeholder="0" oninput="this.value=this.value.replace(/\D/g,'')">
          </div>
        </div>
        <div class="form-group" style="margin-top:12px">
          <label class="form-label">Alamat</label>
          <textarea name="address" id="cust-addr" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCust')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal-overlay" id="modalDetail">
  <div class="modal" style="min-width:620px">
    <div class="modal-header">
      <span class="modal-title" id="detail-title">Riwayat Transaksi</span>
      <button class="modal-close" onclick="closeModal('modalDetail')">&times;</button>
    </div>
    <div class="modal-body" id="detail-body" style="min-height:200px"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div></div>
  </div>
</div>

<script>
function newCustomer() {
  document.getElementById('cust-modal-title').textContent = 'Tambah Pelanggan';
  ['cust-id','cust-code','cust-name','cust-phone','cust-email','cust-addr','cust-limit'].forEach(id => document.getElementById(id).value = '');
  openModal('modalCust');
}
function editCustomer(c) {
  document.getElementById('cust-modal-title').textContent = 'Edit Pelanggan';
  document.getElementById('cust-id').value    = c.id;
  document.getElementById('cust-code').value  = c.code;
  document.getElementById('cust-name').value  = c.name;
  document.getElementById('cust-phone').value = c.phone || '';
  document.getElementById('cust-email').value = c.email || '';
  document.getElementById('cust-addr').value  = c.address || '';
  document.getElementById('cust-limit').value = c.credit_limit || '0';
  openModal('modalCust');
}
async function genCode() {
  const r = await fetch('index.php?gen_code=1');
  const d = await r.json();
  document.getElementById('cust-code').value = d.code;
}
async function viewDetail(id, name) {
  document.getElementById('detail-title').textContent = 'Riwayat — ' + name;
  document.getElementById('detail-body').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
  openModal('modalDetail');
  const r = await fetch('index.php?detail=' + id);
  document.getElementById('detail-body').innerHTML = await r.text();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
