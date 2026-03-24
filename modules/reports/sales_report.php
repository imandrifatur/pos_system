<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle  = 'Laporan Penjualan';
$activePage = 'sales_report';

$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$group  = $_GET['group']  ?? 'daily';   // daily | monthly | product | customer | payment
$status = $_GET['status'] ?? 'selesai';

/* ── Summary ─────────────────────────────────────────── */
$smtBase = "FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
            WHERE s.sale_date BETWEEN ? AND ?
            AND s.status = ?";
$pBase = [$from, $to . ' 23:59:59', $status];

$totalOmzet  = $pdo->prepare("SELECT COALESCE(SUM(s.total),0) $smtBase"); $totalOmzet->execute($pBase);  $totalOmzet  = (float)$totalOmzet->fetchColumn();
$totalTx     = $pdo->prepare("SELECT COUNT(*) $smtBase");                  $totalTx->execute($pBase);     $totalTx     = (int)$totalTx->fetchColumn();
$avgTx       = $totalTx > 0 ? $totalOmzet / $totalTx : 0;
$totalHPP    = $pdo->prepare("SELECT COALESCE(SUM(si.buy_price*si.quantity),0)
               FROM sale_items si JOIN sales s ON si.sale_id=s.id
               WHERE s.sale_date BETWEEN ? AND ? AND s.status=?");
$totalHPP->execute($pBase); $totalHPP = (float)$totalHPP->fetchColumn();
$totalLaba   = $totalOmzet - $totalHPP;

/* ── Group Data ───────────────────────────────────────── */
$rows = [];
if ($group === 'daily') {
    $stmt = $pdo->prepare("SELECT DATE(s.sale_date) as period,
        COUNT(*) as tx, SUM(s.total) as omzet,
        SUM(si2.hpp) as hpp
        FROM sales s
        LEFT JOIN (SELECT sale_id, SUM(buy_price*quantity) as hpp FROM sale_items GROUP BY sale_id) si2 ON si2.sale_id=s.id
        WHERE s.sale_date BETWEEN ? AND ? AND s.status=?
        GROUP BY DATE(s.sale_date) ORDER BY period DESC");
    $stmt->execute($pBase); $rows = $stmt->fetchAll();
    $colLabel = 'Tanggal';

} elseif ($group === 'monthly') {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(s.sale_date,'%Y-%m') as period,
        COUNT(*) as tx, SUM(s.total) as omzet,
        SUM(si2.hpp) as hpp
        FROM sales s
        LEFT JOIN (SELECT sale_id, SUM(buy_price*quantity) as hpp FROM sale_items GROUP BY sale_id) si2 ON si2.sale_id=s.id
        WHERE s.sale_date BETWEEN ? AND ? AND s.status=?
        GROUP BY DATE_FORMAT(s.sale_date,'%Y-%m') ORDER BY period DESC");
    $stmt->execute($pBase); $rows = $stmt->fetchAll();
    $colLabel = 'Bulan';

} elseif ($group === 'product') {
    $stmt = $pdo->prepare("SELECT p.code, p.name as period,
        SUM(si.quantity) as tx, SUM(si.subtotal) as omzet,
        SUM(si.buy_price*si.quantity) as hpp
        FROM sale_items si
        JOIN products p ON si.product_id=p.id
        JOIN sales s ON si.sale_id=s.id
        WHERE s.sale_date BETWEEN ? AND ? AND s.status=?
        GROUP BY p.id ORDER BY omzet DESC");
    $stmt->execute($pBase); $rows = $stmt->fetchAll();
    $colLabel = 'Produk';

} elseif ($group === 'customer') {
    $stmt = $pdo->prepare("SELECT COALESCE(c.name,'Umum') as period,
        COUNT(*) as tx, SUM(s.total) as omzet,
        SUM(si2.hpp) as hpp
        FROM sales s
        LEFT JOIN customers c ON s.customer_id=c.id
        LEFT JOIN (SELECT sale_id, SUM(buy_price*quantity) as hpp FROM sale_items GROUP BY sale_id) si2 ON si2.sale_id=s.id
        WHERE s.sale_date BETWEEN ? AND ? AND s.status=?
        GROUP BY s.customer_id ORDER BY omzet DESC");
    $stmt->execute($pBase); $rows = $stmt->fetchAll();
    $colLabel = 'Pelanggan';

} elseif ($group === 'payment') {
    $stmt = $pdo->prepare("SELECT s.payment_method as period,
        COUNT(*) as tx, SUM(s.total) as omzet,
        SUM(si2.hpp) as hpp
        FROM sales s
        LEFT JOIN (SELECT sale_id, SUM(buy_price*quantity) as hpp FROM sale_items GROUP BY sale_id) si2 ON si2.sale_id=s.id
        WHERE s.sale_date BETWEEN ? AND ? AND s.status=?
        GROUP BY s.payment_method ORDER BY omzet DESC");
    $stmt->execute($pBase); $rows = $stmt->fetchAll();
    $colLabel = 'Metode Bayar';
}

/* ── Chart data (top 10) ─────────────────────────────── */
$chartLabels  = array_slice(array_column($rows, 'period'), 0, 10);
$chartOmzet   = array_slice(array_column($rows, 'omzet'),  0, 10);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Sampai</label>
      <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Kelompokkan</label>
      <select name="group" class="form-control">
        <option value="daily"    <?= $group==='daily'?'selected':'' ?>>Per Hari</option>
        <option value="monthly"  <?= $group==='monthly'?'selected':'' ?>>Per Bulan</option>
        <option value="product"  <?= $group==='product'?'selected':'' ?>>Per Produk</option>
        <option value="customer" <?= $group==='customer'?'selected':'' ?>>Per Pelanggan</option>
        <option value="payment"  <?= $group==='payment'?'selected':'' ?>>Per Metode Bayar</option>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="selesai" <?= $status==='selesai'?'selected':'' ?>>Selesai</option>
        <option value="piutang" <?= $status==='piutang'?'selected':'' ?>>Piutang</option>
        <option value="batal"   <?= $status==='batal'?'selected':'' ?>>Batal</option>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Tampilkan</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
  </form>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-label">Total Omzet</div><div class="stat-value" style="font-size:15px"><?= formatRupiah($totalOmzet) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
    <div><div class="stat-label">Total Transaksi</div><div class="stat-value"><?= number_format($totalTx) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-chart-line"></i></div>
    <div><div class="stat-label">Rata-rata Transaksi</div><div class="stat-value" style="font-size:15px"><?= formatRupiah($avgTx) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-percentage"></i></div>
    <div><div class="stat-label">Laba Kotor</div><div class="stat-value" style="font-size:15px;color:var(--accent)"><?= formatRupiah($totalLaba) ?></div></div>
  </div>
</div>



<!-- Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Detail Laporan — <?= ucfirst(str_replace(['daily','monthly','product','customer','payment'],['Per Hari','Per Bulan','Per Produk','Per Pelanggan','Per Metode'], $group)) ?></span>
    <span style="color:var(--text-secondary);font-size:13px"><?= count($rows) ?> baris</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th><?= $colLabel ?></th>
          <?= $group==='product' ? '<th>Kode</th><th>Qty Terjual</th>' : '<th>Jml Transaksi</th>' ?>
          <th>Omzet</th>
          <th>HPP</th>
          <th>Laba Kotor</th>
          <th>Margin %</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada data</td></tr>
        <?php else:
          $totO=0;$totH=0;$totT=0;
          foreach ($rows as $r):
            $laba   = ($r['omzet']??0) - ($r['hpp']??0);
            $margin = ($r['omzet']??0) > 0 ? ($laba / $r['omzet']) * 100 : 0;
            $totO  += $r['omzet']??0;
            $totH  += $r['hpp']??0;
            $totT  += $r['tx']??0;
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['period']) ?></strong></td>
          <?php if ($group==='product'): ?>
          <td><span style="font-family:monospace;font-size:12px"><?= htmlspecialchars($r['code']??'-') ?></span></td>
          <td class="amount"><?= number_format($r['tx'],2) ?></td>
          <?php else: ?>
          <td style="text-align:center"><?= number_format($r['tx']) ?></td>
          <?php endif; ?>
          <td class="amount"><?= formatRupiah($r['omzet']??0) ?></td>
          <td class="amount" style="color:var(--red)"><?= formatRupiah($r['hpp']??0) ?></td>
          <td class="amount" style="color:var(--green)"><?= formatRupiah($laba) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;background:var(--bg-elevated);border-radius:4px;height:6px;overflow:hidden">
                <div style="width:<?= min(100,$margin) ?>%;background:var(--accent);height:100%"></div>
              </div>
              <span style="font-size:12px;font-weight:600;min-width:36px"><?= number_format($margin,1) ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <!-- Totals -->
        <tr style="background:var(--bg-elevated);font-weight:700">
          <td colspan="<?= $group==='product'?3:2 ?>">TOTAL</td>
          <td class="amount"><?= formatRupiah($totO) ?></td>
          <td class="amount" style="color:var(--red)"><?= formatRupiah($totH) ?></td>
          <td class="amount" style="color:var(--green)"><?= formatRupiah($totO-$totH) ?></td>
          <td><?= $totO > 0 ? number_format((($totO-$totH)/$totO)*100,1).'%' : '-' ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
<?php if (!empty($chartLabels)): ?>
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Omzet',
      data: <?= json_encode(array_map('floatval', $chartOmzet)) ?>,
      backgroundColor: 'rgba(0,212,170,.35)',
      borderColor: '#00d4aa',
      borderWidth: 2, borderRadius: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#8b949e' }, grid: { color: '#2a3348' } },
      y: { ticks: { color: '#8b949e', callback: v => 'Rp '+v.toLocaleString('id-ID') }, grid: { color: '#2a3348' } }
    }
  }
});
<?php endif; ?>
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
