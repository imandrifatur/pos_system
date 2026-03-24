<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Neraca';
$activePage = 'neraca';

$asOf = $_GET['date'] ?? date('Y-m-d');

function getCoaBalance($pdo, $type, $date) {
    $stmt = $pdo->prepare("
        SELECT c.code, c.name, c.normal_balance,
               SUM(ji.debit) as total_debit, SUM(ji.credit) as total_credit
        FROM coa c
        LEFT JOIN journal_items ji ON ji.coa_id = c.id
        LEFT JOIN journals j ON ji.journal_id = j.id AND j.journal_date <= ?
        WHERE c.type = ? AND c.is_active = 1
        GROUP BY c.id ORDER BY c.code
    ");
    $stmt->execute([$date, $type]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        if ($r['normal_balance'] === 'debit') {
            $r['balance'] = ($r['total_debit'] ?? 0) - ($r['total_credit'] ?? 0);
        } else {
            $r['balance'] = ($r['total_credit'] ?? 0) - ($r['total_debit'] ?? 0);
        }
    }
    return $rows;
}

$aset      = getCoaBalance($pdo, 'aset', $asOf);
$kewajiban = getCoaBalance($pdo, 'kewajiban', $asOf);
$modal     = getCoaBalance($pdo, 'modal', $asOf);

$totalAset      = array_sum(array_column($aset, 'balance'));
$totalKewajiban = array_sum(array_column($kewajiban, 'balance'));
$totalModal     = array_sum(array_column($modal, 'balance'));
$totalKewModal  = $totalKewajiban + $totalModal;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card no-print" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
    <div class="form-group" style="margin:0">
      <label class="form-label">Per Tanggal</label>
      <input type="date" name="date" class="form-control" value="<?= $asOf ?>">
    </div>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Tampilkan</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
  </form>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <!-- ASET -->
  <div class="card">
    <div style="text-align:center;margin-bottom:16px">
      <h3 style="font-weight:800">NERACA</h3>
      <p style="color:var(--text-secondary);font-size:13px">Per Tanggal <?= date('d/m/Y', strtotime($asOf)) ?></p>
    </div>
    <div style="font-weight:700;font-size:13px;padding:8px 0;border-bottom:2px solid var(--blue);color:var(--blue);margin-bottom:10px">ASET</div>
    <?php foreach ($aset as $r): if ($r['balance'] == 0) continue; ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0 5px 16px;font-size:13px">
      <span><?= htmlspecialchars($r['code'] . ' - ' . $r['name']) ?></span>
      <span class="amount"><?= formatRupiah($r['balance']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:2px solid var(--border);font-weight:800;margin-top:8px">
      <span>TOTAL ASET</span>
      <span class="amount" style="color:var(--blue)"><?= formatRupiah($totalAset) ?></span>
    </div>
  </div>

  <!-- KEWAJIBAN + MODAL -->
  <div class="card">
    <div style="height:52px"></div>
    <div style="font-weight:700;font-size:13px;padding:8px 0;border-bottom:2px solid var(--orange);color:var(--orange);margin-bottom:10px">KEWAJIBAN</div>
    <?php foreach ($kewajiban as $r): if ($r['balance'] == 0) continue; ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0 5px 16px;font-size:13px">
      <span><?= htmlspecialchars($r['code'] . ' - ' . $r['name']) ?></span>
      <span class="amount"><?= formatRupiah($r['balance']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px dashed var(--border);font-weight:700;font-size:13px;margin-top:6px">
      <span>Total Kewajiban</span><span class="amount" style="color:var(--orange)"><?= formatRupiah($totalKewajiban) ?></span>
    </div>

    <div style="font-weight:700;font-size:13px;padding:8px 0;border-bottom:2px solid var(--accent);color:var(--accent);margin-bottom:10px;margin-top:16px">MODAL</div>
    <?php foreach ($modal as $r): if ($r['balance'] == 0) continue; ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0 5px 16px;font-size:13px">
      <span><?= htmlspecialchars($r['code'] . ' - ' . $r['name']) ?></span>
      <span class="amount"><?= formatRupiah($r['balance']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px dashed var(--border);font-weight:700;font-size:13px;margin-top:6px">
      <span>Total Modal</span><span class="amount" style="color:var(--accent)"><?= formatRupiah($totalModal) ?></span>
    </div>

    <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:2px solid var(--border);font-weight:800;margin-top:8px">
      <span>TOTAL KEWAJIBAN + MODAL</span>
      <span class="amount" style="color:<?= abs($totalKewModal - $totalAset) < 1 ? 'var(--green)' : 'var(--red)' ?>"><?= formatRupiah($totalKewModal) ?></span>
    </div>

    <?php if (abs($totalKewModal - $totalAset) >= 1): ?>
    <div style="color:var(--red);font-size:12px;margin-top:8px"><i class="fas fa-exclamation-triangle"></i> Perhatian: Neraca belum seimbang. Selisih: <?= formatRupiah(abs($totalAset - $totalKewModal)) ?></div>
    <?php else: ?>
    <div style="color:var(--green);font-size:12px;margin-top:8px"><i class="fas fa-check-circle"></i> Neraca seimbang</div>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
