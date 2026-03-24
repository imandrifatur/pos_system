<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Jurnal Umum';
$activePage = 'journal';

// Save journal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_journal'])) {
    $date   = $_POST['journal_date'];
    $desc   = trim($_POST['description']);
    $coaIds = $_POST['coa_id'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits= $_POST['credit'] ?? [];
    $descs  = $_POST['item_desc'] ?? [];

    $totalD = array_sum(array_map('floatval', $debits));
    $totalC = array_sum(array_map('floatval', $credits));

    if (abs($totalD - $totalC) > 0.01) {
        flash('error', 'Jurnal tidak seimbang! Debit: ' . formatRupiah($totalD) . ' | Kredit: ' . formatRupiah($totalC));
        header('Location: journal.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        $journalNo = generateJournalNo();
        $pdo->prepare("INSERT INTO journals (journal_no,journal_date,description,type,total_debit,total_credit,user_id) VALUES (?,?,?,'umum',?,?,?)")
            ->execute([$journalNo, $date, $desc, $totalD, $totalC, $_SESSION['user_id']]);
        $jId = $pdo->lastInsertId();

        foreach ($coaIds as $i => $coaId) {
            if (!$coaId) continue;
            $d = (float)($debits[$i] ?? 0);
            $c = (float)($credits[$i] ?? 0);
            if ($d == 0 && $c == 0) continue;
            $pdo->prepare("INSERT INTO journal_items (journal_id,coa_id,description,debit,credit) VALUES (?,?,?,?,?)")
                ->execute([$jId, $coaId, $descs[$i] ?? '', $d, $c]);
        }
        $pdo->commit();
        flash('success', "Jurnal $journalNo berhasil disimpan.");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    header('Location: journal.php'); exit;
}

// Fetch journals
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$journals = $pdo->prepare("SELECT j.*, u.full_name FROM journals j JOIN users u ON j.user_id=u.id WHERE j.journal_date BETWEEN ? AND ? ORDER BY j.journal_date DESC, j.id DESC");
$journals->execute([$from, $to]); $journals = $journals->fetchAll();
$coas = $pdo->query("SELECT * FROM coa WHERE is_active=1 ORDER BY code")->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
  <!-- Filter -->
  <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex:1">
    <div class="form-group" style="margin:0">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Sampai Tanggal</label>
      <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
  </form>
  <button class="btn btn-primary" onclick="openModal('modalJurnal')">
    <i class="fas fa-plus"></i> Input Jurnal
  </button>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Jurnal Umum (<?= count($journals) ?> entri)</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>No. Jurnal</th><th>Tanggal</th><th>Keterangan</th><th>Tipe</th><th>Total Debit</th><th>Total Kredit</th><th>Input Oleh</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($journals)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada jurnal</td></tr>
        <?php else: foreach ($journals as $j): ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px"><?= $j['journal_no'] ?></span></td>
          <td><?= date('d/m/Y', strtotime($j['journal_date'])) ?></td>
          <td><?= htmlspecialchars($j['description']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($j['type']) ?></span></td>
          <td class="amount"><?= formatRupiah($j['total_debit']) ?></td>
          <td class="amount"><?= formatRupiah($j['total_credit']) ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($j['full_name']) ?></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="viewJournal(<?= $j['id'] ?>)"><i class="fas fa-eye"></i></button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Input Jurnal -->
<div class="modal-overlay" id="modalJurnal">
  <div class="modal" style="min-width:750px;max-width:900px">
    <div class="modal-header">
      <span class="modal-title">Input Jurnal Umum</span>
      <button class="modal-close" onclick="closeModal('modalJurnal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="save_journal" value="1">
      <div class="modal-body">
        <div class="form-grid" style="margin-bottom:14px">
          <div class="form-group">
            <label class="form-label">Tanggal Jurnal *</label>
            <input type="date" name="journal_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan *</label>
            <input type="text" name="description" class="form-control" required placeholder="Keterangan transaksi...">
          </div>
        </div>

        <!-- Journal Lines -->
        <table style="width:100%;border-collapse:collapse" id="journal-lines">
          <thead>
            <tr>
              <th style="padding:8px;text-align:left;font-size:12px;color:var(--text-secondary);border-bottom:1px solid var(--border)">Akun</th>
              <th style="padding:8px;text-align:left;font-size:12px;color:var(--text-secondary);border-bottom:1px solid var(--border)">Keterangan</th>
              <th style="padding:8px;text-align:right;font-size:12px;color:var(--text-secondary);border-bottom:1px solid var(--border)">Debit</th>
              <th style="padding:8px;text-align:right;font-size:12px;color:var(--text-secondary);border-bottom:1px solid var(--border)">Kredit</th>
              <th style="width:36px"></th>
            </tr>
          </thead>
          <tbody id="journal-body">
            <!-- Lines added by JS -->
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" style="padding:8px;font-weight:700;text-align:right;border-top:1px solid var(--border)">TOTAL</td>
              <td style="padding:8px;text-align:right;font-weight:700;font-family:monospace;border-top:1px solid var(--border)" id="total-debit">Rp 0</td>
              <td style="padding:8px;text-align:right;font-weight:700;font-family:monospace;border-top:1px solid var(--border)" id="total-credit">Rp 0</td>
              <td style="border-top:1px solid var(--border)"></td>
            </tr>
          </tfoot>
        </table>

        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="addJournalLine()">
          <i class="fas fa-plus"></i> Tambah Baris
        </button>
      </div>
      <div class="modal-footer">
        <div id="balance-indicator" style="margin-right:auto;font-size:13px"></div>
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalJurnal')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Jurnal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal View Journal -->
<div class="modal-overlay" id="modalView">
  <div class="modal" style="min-width:600px">
    <div class="modal-header">
      <span class="modal-title">Detail Jurnal</span>
      <button class="modal-close" onclick="closeModal('modalView')">&times;</button>
    </div>
    <div class="modal-body" id="modal-view-body">Loading...</div>
  </div>
</div>

<script>
/* ===== DATA COA (FIX PHP 7.3) ===== */
const coaOptions = <?= json_encode(array_map(function($c) {
    return [
        'id'   => $c['id'],
        'code' => $c['code'],
        'name' => $c['name']
    ];
}, $coas)) ?>;

/* ===== BUILD SELECT ===== */
function buildCoaSelect(name, val='') {
  let html = `<select name="${name}" class="form-control"
                onchange="calcTotals()" style="font-size:12px">
    <option value="">-- Pilih Akun --</option>`;

  coaOptions.forEach(function(c) {
    html += `<option value="${c.id}" ${c.id==val?'selected':''}>
                ${c.code} - ${c.name}
            </option>`;
  });

  return html + '</select>';
}

/* ===== TAMBAH BARIS ===== */
function addJournalLine(d=0, c=0, desc='', coaId='') {
  const tbody = document.getElementById('journal-body');
  if (!tbody) return;

  const row = document.createElement('tr');

  row.innerHTML = `
    <td style="padding:4px">${buildCoaSelect('coa_id[]', coaId)}</td>
    
    <td style="padding:4px">
        <input type="text" name="item_desc[]"
        class="form-control"
        value="${desc}"
        style="font-size:12px"
        placeholder="Keterangan...">
    </td>

    <td style="padding:4px">
        <input type="number" name="debit[]"
        class="form-control"
        value="${d}"
        min="0"
        oninput="calcTotals()"
        style="font-size:12px;text-align:right">
    </td>

    <td style="padding:4px">
        <input type="number" name="credit[]"
        class="form-control"
        value="${c}"
        min="0"
        oninput="calcTotals()"
        style="font-size:12px;text-align:right">
    </td>

    <td style="padding:4px">
        <button type="button"
        class="btn btn-danger btn-icon btn-sm"
        onclick="removeRow(this)">
            <i class="fas fa-times"></i>
        </button>
    </td>
  `;

  tbody.appendChild(row);
  calcTotals();
}

/* ===== HAPUS BARIS ===== */
function removeRow(btn) {
  const row = btn.closest('tr');
  if (row) row.remove();
  calcTotals();
}

/* ===== HITUNG TOTAL ===== */
function calcTotals() {
  let d = 0, c = 0;

  document.querySelectorAll('[name="debit[]"]').forEach(function(el) {
    d += parseFloat(el.value) || 0;
  });

  document.querySelectorAll('[name="credit[]"]').forEach(function(el) {
    c += parseFloat(el.value) || 0;
  });

  const debitEl  = document.getElementById('total-debit');
  const creditEl = document.getElementById('total-credit');
  const bal      = document.getElementById('balance-indicator');

  if (debitEl)  debitEl.textContent  = 'Rp ' + d.toLocaleString('id-ID');
  if (creditEl) creditEl.textContent = 'Rp ' + c.toLocaleString('id-ID');

  if (bal) {
    if (Math.abs(d - c) < 0.01) {
      bal.innerHTML = '<span style="color:green"><i class="fas fa-check-circle"></i> Seimbang</span>';
    } else {
      bal.innerHTML = `<span style="color:red">
        <i class="fas fa-exclamation-circle"></i>
        Selisih: Rp ${Math.abs(d - c).toLocaleString('id-ID')}
      </span>`;
    }
  }
}

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', function() {
  addJournalLine();
  addJournalLine();
});

/* ===== VIEW DETAIL ===== */
async function viewJournal(id) {
  openModal('modalView');

  const body = document.getElementById('modal-view-body');
  if (body) {
    body.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  }

  try {
    const resp = await fetch('journal_detail.php?id=' + id);
    const html = await resp.text();

    if (body) body.innerHTML = html;

  } catch (e) {
    if (body) body.innerHTML = 'Gagal load data';
  }
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
