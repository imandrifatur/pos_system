<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Chart of Accounts (COA)';
$activePage = 'coa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save') {

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // Ambil & sanitasi input
        $code            = isset($_POST['code']) ? trim($_POST['code']) : '';
        $name            = isset($_POST['name']) ? trim($_POST['name']) : '';
        $type            = isset($_POST['type']) ? $_POST['type'] : '';
        $normal_balance  = isset($_POST['normal_balance']) ? $_POST['normal_balance'] : '';

        // Validasi sederhana
        if ($code === '' || $name === '') {
            flash('error', 'Kode dan Nama akun wajib diisi.');
            header('Location: coa.php');
            exit;
        }

        $data = [$code, $name, $type, $normal_balance];

        if ($id > 0) {

            $stmt = $pdo->prepare("
                UPDATE coa 
                SET code = ?, name = ?, type = ?, normal_balance = ?
                WHERE id = ?
            ");
            $stmt->execute(array_merge($data, [$id]));

            flash('success', 'Akun berhasil diperbarui.');

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO coa (code, name, type, normal_balance)
                VALUES (?,?,?,?)
            ");
            $stmt->execute($data);

            flash('success', 'Akun berhasil ditambahkan.');
        }
    }

    header('Location: coa.php');
    exit;
}
$coas = $pdo->query("SELECT * FROM coa WHERE is_active=1 ORDER BY code")->fetchAll();
$grouped = [];
foreach ($coas as $c) { $grouped[$c['type']][] = $c; }

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Chart of Accounts (<?= count($coas) ?> Akun)</span>
    <button class="btn btn-primary" onclick="openModal('modalCOA');document.getElementById('coa-id').value=''">
      <i class="fas fa-plus"></i> Tambah Akun
    </button>
  </div>

  <?php $typeLabels = ['aset'=>'ASET','kewajiban'=>'KEWAJIBAN','modal'=>'MODAL','pendapatan'=>'PENDAPATAN','beban'=>'BEBAN'];
        $typeColors = ['aset'=>'blue','kewajiban'=>'orange','modal'=>'teal','pendapatan'=>'green','beban'=>'red'];
  foreach ($typeLabels as $type => $label): if (!isset($grouped[$type])) continue; ?>
  <div style="margin-bottom:20px">
    <div style="font-weight:700;font-size:12px;letter-spacing:1px;padding:8px 0;border-bottom:1px solid var(--border);color:var(--<?= $typeColors[$type] ?>);margin-bottom:8px">
      <?= $label ?>
    </div>
    <table class="data-table">
      <thead><tr><th>Kode</th><th>Nama Akun</th><th>Tipe</th><th>Saldo Normal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($grouped[$type] as $c): ?>
        <tr>
          <td><span style="font-family:monospace"><?= $c['code'] ?></span></td>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($c['type']) ?></span></td>
          <td><span class="badge badge-<?= $c['normal_balance']==='debit'?'warning':'success' ?>"><?= ucfirst($c['normal_balance']) ?></span></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="editCoa(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="fas fa-edit"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>
</div>

<div class="modal-overlay" id="modalCOA">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Tambah / Edit Akun</span>
      <button class="modal-close" onclick="closeModal('modalCOA')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="coa-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Kode Akun *</label><input type="text" name="code" id="coa-code" class="form-control" required placeholder="1001"></div>
          <div class="form-group"><label class="form-label">Nama Akun *</label><input type="text" name="name" id="coa-name" class="form-control" required></div>
          <div class="form-group">
            <label class="form-label">Tipe *</label>
            <select name="type" id="coa-type" class="form-control" required>
              <option value="aset">Aset</option><option value="kewajiban">Kewajiban</option>
              <option value="modal">Modal</option><option value="pendapatan">Pendapatan</option><option value="beban">Beban</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Saldo Normal *</label>
            <select name="normal_balance" id="coa-norm" class="form-control" required>
              <option value="debit">Debit</option><option value="kredit">Kredit</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalCOA')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editCoa(c) {
  document.getElementById('coa-id').value   = c.id;
  document.getElementById('coa-code').value = c.code;
  document.getElementById('coa-name').value = c.name;
  document.getElementById('coa-type').value = c.type;
  document.getElementById('coa-norm').value = c.normal_balance;
  openModal('modalCOA');
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
