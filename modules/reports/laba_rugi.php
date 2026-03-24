<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Laporan Laba Rugi';
$activePage = 'laba_rugi';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Total pendapatan penjualan
$penjualan = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date BETWEEN ? AND ? AND status='selesai'");
$penjualan->execute([$from, $to . ' 23:59:59']); $penjualan = (float)$penjualan->fetchColumn();

// HPP
$hpp = $pdo->prepare("SELECT COALESCE(SUM(si.buy_price * si.quantity),0) FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE s.sale_date BETWEEN ? AND ? AND s.status='selesai'");
$hpp->execute([$from, $to . ' 23:59:59']); $hpp = (float)$hpp->fetchColumn();

// Beban dari jurnal (coa type = beban)
$beban = $pdo->prepare("
  SELECT c.name, c.code, SUM(ji.debit - ji.credit) as net
  FROM journal_items ji
  JOIN journals j ON ji.journal_id = j.id
  JOIN coa c ON ji.coa_id = c.id
  WHERE c.type = 'beban' AND c.code != '5001'
  AND j.journal_date BETWEEN ? AND ?
  GROUP BY c.id ORDER BY c.code
");
$beban->execute([$from, $to]); $bebanRows = $beban->fetchAll();
$totalBeban = array_sum(array_column($bebanRows, 'net'));

$labaKotor = $penjualan - $hpp;
$labaBersih = $labaKotor - $totalBeban;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card no-print" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
    <div class="form-group" style="margin:0">
      <label class="form-label">Periode Dari</label>
      <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Sampai</label>
      <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Tampilkan</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
  </form>
</div>

<div class="card" style="max-width:700px">
  <div style="text-align:center;margin-bottom:24px">
    <h2 style="font-size:20px;font-weight:800">LAPORAN LABA RUGI</h2>
    <p style="color:var(--text-secondary)">Periode: <?= date('d/m/Y', strtotime($from)) ?> s/d <?= date('d/m/Y', strtotime($to)) ?></p>
  </div>

  <!-- Pendapatan -->
  <div style="margin-bottom:20px">
    <div style="font-weight:700;font-size:14px;padding:8px 0;border-bottom:2px solid var(--accent);color:var(--accent);margin-bottom:10px">
      PENDAPATAN
    </div>
    <div style="display:flex;justify-content:space-between;padding:6px 0 6px 16px">
      <span>Penjualan Bersih</span>
      <span class="amount"><?= formatRupiah($penjualan) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:6px 0 6px 16px">
      <span>Harga Pokok Penjualan (HPP)</span>
      <span class="amount" style="color:var(--red)">(<?= formatRupiah($hpp) ?>)</span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);font-weight:700;font-size:15px;margin-top:6px">
      <span>LABA KOTOR</span>
      <span class="amount" style="color:<?= $labaKotor >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= formatRupiah(abs($labaKotor)) ?><?= $labaKotor < 0 ? ' (Rugi)' : '' ?></span>
    </div>
  </div>

  <!-- Beban Operasional -->
  <div style="margin-bottom:20px">
    <div style="font-weight:700;font-size:14px;padding:8px 0;border-bottom:2px solid var(--orange);color:var(--orange);margin-bottom:10px">
      BEBAN OPERASIONAL
    </div>
    <?php if (empty($bebanRows)): ?>
    <p style="color:var(--text-muted);padding:6px 0 6px 16px;font-size:13px">Tidak ada beban tercatat</p>
    <?php else: foreach ($bebanRows as $b): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0 6px 16px">
      <span><?= htmlspecialchars($b['code'] . ' - ' . $b['name']) ?></span>
      <span class="amount" style="color:var(--red)"><?= formatRupiah(max(0,$b['net'])) ?></span>
    </div>
    <?php endforeach; endif; ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0 8px 16px;border-top:1px dashed var(--border);font-weight:600;margin-top:4px">
      <span>Total Beban Operasional</span>
      <span class="amount" style="color:var(--red)"><?= formatRupiah($totalBeban) ?></span>
    </div>
  </div>

  <!-- Laba Bersih -->
  <div style="background:<?= $labaBersih >= 0 ? 'var(--green-dim)' : 'var(--red-dim)' ?>;border:1px solid <?= $labaBersih >= 0 ? 'var(--green)' : 'var(--red)' ?>;border-radius:var(--radius-sm);padding:16px 20px;display:flex;justify-content:space-between;align-items:center">
    <span style="font-weight:800;font-size:16px">LABA BERSIH</span>
    <span style="font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;color:<?= $labaBersih >= 0 ? 'var(--green)' : 'var(--red)' ?>">
      <?= $labaBersih < 0 ? '(' : '' ?><?= formatRupiah(abs($labaBersih)) ?><?= $labaBersih < 0 ? ')' : '' ?>
    </span>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
