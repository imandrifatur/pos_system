<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle  = 'Buku Besar';
$activePage = 'ledger';

$from    = $_GET['from']   ?? date('Y-m-01');
$to      = $_GET['to']     ?? date('Y-m-d');
$coaId   = (int)($_GET['coa_id'] ?? 0);

$coas = $pdo->query("SELECT * FROM coa WHERE is_active=1 ORDER BY code")->fetchAll();

$ledgerData = [];
if ($coaId) {
    $coa = $pdo->query("SELECT * FROM coa WHERE id=$coaId")->fetch();

    /* Saldo awal: semua transaksi sebelum $from */
    $saldoAwal = $pdo->prepare("
        SELECT COALESCE(SUM(ji.debit),0) - COALESCE(SUM(ji.credit),0)
        FROM journal_items ji
        JOIN journals j ON ji.journal_id=j.id
        WHERE ji.coa_id=? AND j.journal_date < ?");
    $saldoAwal->execute([$coaId, $from]);
    $rawSaldoAwal = (float)$saldoAwal->fetchColumn();

    /* Konversi ke saldo normal */
    $openingBalance = ($coa['normal_balance'] === 'debit') ? $rawSaldoAwal : -$rawSaldoAwal;

    /* Mutasi dalam periode */
    $stmt = $pdo->prepare("
        SELECT j.journal_date, j.journal_no, j.description as journal_desc,
               ji.description as item_desc, ji.debit, ji.credit
        FROM journal_items ji
        JOIN journals j ON ji.journal_id=j.id
        WHERE ji.coa_id=? AND j.journal_date BETWEEN ? AND ?
        ORDER BY j.journal_date, j.id");
    $stmt->execute([$coaId, $from, $to]);
    $mutations = $stmt->fetchAll();

    /* Hitung running balance */
    $balance = $openingBalance;
    $ledgerData = [];
    $totalD = 0; $totalC = 0;
    foreach ($mutations as $m) {
        if ($coa['normal_balance'] === 'debit') {
            $balance += $m['debit'] - $m['credit'];
        } else {
            $balance += $m['credit'] - $m['debit'];
        }
        $totalD += $m['debit'];
        $totalC += $m['credit'];
        $ledgerData[] = array_merge($m, ['running_balance' => $balance]);
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Filter -->
<div class="card" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;flex:1;min-width:220px">
      <label class="form-label">Pilih Akun (COA) *</label>
      <select name="coa_id" class="form-control" required>
        <option value="">-- Pilih Akun --</option>
        <?php foreach ($coas as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $coaId==$c['id']?'selected':'' ?>>
          <?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Dari</label>
      <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Sampai</label>
      <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Tampilkan</button>
    <?php if ($coaId): ?>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    <?php endif; ?>
  </form>
</div>

<?php if ($coaId && isset($coa)): ?>
<!-- Ledger Header Info -->
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <div style="font-size:20px;font-weight:800"><?= htmlspecialchars($coa['name']) ?></div>
      <div style="color:var(--text-secondary);font-size:13px;margin-top:4px">
        Kode: <span style="font-family:monospace;color:var(--accent)"><?= $coa['code'] ?></span> |
        Tipe: <span class="badge badge-info"><?= ucfirst($coa['type']) ?></span> |
        Saldo Normal: <span class="badge badge-<?= $coa['normal_balance']==='debit'?'warning':'success' ?>"><?= ucfirst($coa['normal_balance']) ?></span>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:13px;color:var(--text-secondary)">Periode: <?= date('d/m/Y', strtotime($from)) ?> — <?= date('d/m/Y', strtotime($to)) ?></div>
    </div>
  </div>
</div>

<!-- Ledger Table -->
<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:100px">Tanggal</th>
          <th style="width:160px">No. Jurnal</th>
          <th>Keterangan</th>
          <th style="text-align:right">Debit</th>
          <th style="text-align:right">Kredit</th>
          <th style="text-align:right">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <!-- Opening Balance -->
        <tr style="background:var(--blue-dim)">
          <td colspan="2" style="color:var(--blue);font-weight:700"><?= date('d/m/Y', strtotime($from)) ?></td>
          <td style="color:var(--blue);font-weight:700">Saldo Awal</td>
          <td></td><td></td>
          <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--blue)"><?= formatRupiah($openingBalance) ?></td>
        </tr>

        <?php if (empty($ledgerData)): ?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Tidak ada mutasi dalam periode ini</td></tr>
        <?php else: foreach ($ledgerData as $row): ?>
        <tr>
          <td style="font-size:12px"><?= date('d/m/Y', strtotime($row['journal_date'])) ?></td>
          <td><span style="font-family:monospace;font-size:11px"><?= $row['journal_no'] ?></span></td>
          <td>
            <div><?= htmlspecialchars($row['journal_desc']) ?></div>
            <?php if ($row['item_desc']): ?>
            <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($row['item_desc']) ?></div>
            <?php endif; ?>
          </td>
          <td style="text-align:right" class="amount"><?= $row['debit'] > 0 ? formatRupiah($row['debit']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
          <td style="text-align:right" class="amount"><?= $row['credit'] > 0 ? formatRupiah($row['credit']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
          <td style="text-align:right;font-family:monospace;font-weight:600;color:<?= $row['running_balance'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">
            <?= formatRupiah(abs($row['running_balance'])) ?><?= $row['running_balance'] < 0 ? ' (K)' : '' ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>

        <!-- Closing Balance -->
        <?php
        $closingBalance = isset($ledgerData) && !empty($ledgerData)
            ? end($ledgerData)['running_balance']
            : $openingBalance;
        ?>
        <tr style="background:var(--accent-dim);font-weight:800">
          <td colspan="2" style="color:var(--accent)">Saldo Akhir</td>
          <td style="color:var(--accent)"><?= date('d/m/Y', strtotime($to)) ?></td>
          <td style="text-align:right" class="amount"><?= isset($totalD) ? formatRupiah($totalD) : '' ?></td>
          <td style="text-align:right" class="amount"><?= isset($totalC) ? formatRupiah($totalC) : '' ?></td>
          <td style="text-align:right;font-family:monospace;color:var(--accent);font-size:15px"><?= formatRupiah(abs($closingBalance)) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif (!$coaId): ?>
<div class="card">
  <div class="empty-state">
    <i class="fas fa-book-open"></i>
    <p style="margin-top:12px;font-size:15px">Pilih akun untuk menampilkan Buku Besar</p>
    <p style="margin-top:6px;font-size:13px;color:var(--text-muted)">Pilih akun (COA) dan periode di atas, lalu klik Tampilkan</p>
  </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
