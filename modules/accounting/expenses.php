<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Pengeluaran / Beban';
$activePage = 'expenses';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    $coaId  = (int)$_POST['coa_id'];
    $date   = $_POST['expense_date'];
    $desc   = trim($_POST['description']);
    $amount = (float)preg_replace('/\D/', '', $_POST['amount']);
    $method = $_POST['payment_method'];
    $expNo  = generateInvoice('EXP');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO expenses (expense_no,coa_id,expense_date,description,amount,payment_method,user_id) VALUES (?,?,?,?,?,?,?)")
            ->execute([$expNo, $coaId, $date, $desc, $amount, $method, $_SESSION['user_id']]);
        $expId = $pdo->lastInsertId();

        // Auto-journal
        $jNo = generateJournalNo();
        $pdo->prepare("INSERT INTO journals (journal_no,journal_date,description,type,reference_type,reference_id,total_debit,total_credit,user_id) VALUES (?,?,'Pengeluaran: $desc','kas_keluar','expense',?,?,?,?)")
            ->execute([$jNo, $date, $expId, $amount, $amount, $_SESSION['user_id']]);
        $jId = $pdo->lastInsertId();

        $kasId  = $pdo->query("SELECT id FROM coa WHERE code='1001'")->fetchColumn();
        $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,0,?)")->execute([$jId, $kasId, $desc, $amount]);
        $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,?,0)")->execute([$jId, $coaId, $desc, $amount]);
        $pdo->prepare("UPDATE expenses SET journal_id=? WHERE id=?")->execute([$jId, $expId]);

        $pdo->commit();
        flash('success', "Pengeluaran $expNo berhasil dicatat.");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    header('Location: expenses.php'); exit;
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$expenses = $pdo->prepare("SELECT e.*, c.name as coa_name, u.full_name FROM expenses e JOIN coa c ON e.coa_id=c.id JOIN users u ON e.user_id=u.id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC");
$expenses->execute([$from, $to]); $expenses = $expenses->fetchAll();
$totalExp = array_sum(array_column($expenses, 'amount'));

$bebanCoas = $pdo->query("SELECT * FROM coa WHERE type='beban' AND is_active=1 ORDER BY code")->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <span class="card-title">Daftar Pengeluaran</span>
    <button class="btn btn-primary" onclick="openModal('modalExp')"><i class="fas fa-plus"></i> Catat Pengeluaran</button>
  </div>
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px">
    <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:140px">
    <input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:140px">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i></button>
  </form>

  <div style="margin-bottom:12px;padding:12px;background:var(--red-dim);border:1px solid var(--red);border-radius:var(--radius-sm)">
    <span style="color:var(--text-secondary);font-size:12px">Total Pengeluaran</span><br>
    <strong style="color:var(--red);font-size:18px"><?= formatRupiah($totalExp) ?></strong>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>No</th><th>Tanggal</th><th>Akun Beban</th><th>Keterangan</th><th>Jumlah</th><th>Pembayaran</th><th>Input oleh</th></tr></thead>
      <tbody>
        <?php if (empty($expenses)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Belum ada pengeluaran</td></tr>
        <?php else: foreach ($expenses as $e): ?>
        <tr>
          <td><span style="font-family:monospace;font-size:11px"><?= $e['expense_no'] ?></span></td>
          <td><?= date('d/m/Y', strtotime($e['expense_date'])) ?></td>
          <td><?= htmlspecialchars($e['coa_name']) ?></td>
          <td><?= htmlspecialchars($e['description']) ?></td>
          <td class="amount" style="color:var(--red)"><?= formatRupiah($e['amount']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($e['payment_method']) ?></span></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($e['full_name']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalExp">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Catat Pengeluaran</span>
      <button class="modal-close" onclick="closeModal('modalExp')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="save_expense" value="1">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Tanggal *</label>
            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Akun Beban *</label>
            <select name="coa_id" class="form-control" required>
              <option value="">-- Pilih Akun --</option>
              <?php foreach ($bebanCoas as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'] . ' - ' . $c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jumlah *</label>
            <input type="text" name="amount" class="form-control" required placeholder="0" oninput="this.value=this.value.replace(/\D/g,'')">
          </div>
          <div class="form-group">
            <label class="form-label">Metode Pembayaran</label>
            <select name="payment_method" class="form-control">
              <option value="tunai">Tunai</option>
              <option value="transfer">Transfer</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-top:12px">
          <label class="form-label">Keterangan *</label>
          <input type="text" name="description" class="form-control" required placeholder="Deskripsi pengeluaran...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalExp')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
