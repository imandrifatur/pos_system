<?php
session_start();
require_once __DIR__ . '/config/database.php';
requireLogin();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Stats
$today = date('Y-m-d');
$month = date('Y-m');

$salesDay   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(sale_date)='$today' AND status='selesai'")->fetchColumn();
$salesMonth = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')='$month' AND status='selesai'")->fetchColumn();
$txDay      = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date)='$today' AND status='selesai'")->fetchColumn();
$totalProducts= $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
$lowStock   = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= min_stock AND is_active=1")->fetchColumn();

// Recent sales
$recentSales = $pdo->query("SELECT s.*, COALESCE(c.name,'Umum') as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.sale_date DESC LIMIT 8")->fetchAll();

// Sales chart data (last 7 days)
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-$i days"));
  $label = date('d/m', strtotime($d));
  $amount = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(sale_date)='$d' AND status='selesai'")->fetchColumn();
  $chartData[] = ['label' => $label, 'amount' => (float)$amount];
}

include 'includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div>
      <div class="stat-label">Penjualan Hari Ini</div>
      <div class="stat-value" style="font-size:16px"><?= formatRupiah($salesDay) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-chart-bar"></i></div>
    <div>
      <div class="stat-label">Penjualan Bulan Ini</div>
      <div class="stat-value" style="font-size:16px"><?= formatRupiah($salesMonth) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-receipt"></i></div>
    <div>
      <div class="stat-label">Transaksi Hari Ini</div>
      <div class="stat-value"><?= $txDay ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-boxes"></i></div>
    <div>
      <div class="stat-label">Total Produk Aktif</div>
      <div class="stat-value"><?= $totalProducts ?></div>
    </div>
  </div>
  <?php if ($lowStock > 0): ?>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
    <div>
      <div class="stat-label">Stok Menipis</div>
      <div class="stat-value"><?= $lowStock ?> Produk</div>
    </div>
  </div>
  <?php endif; ?>
</div>



  <!-- Recent Sales -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-clock" style="color:var(--blue);margin-right:8px"></i>Transaksi Terbaru</span>
      <a href="<?= APP_URL ?>/modules/pos/sales_list.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($recentSales)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Belum ada transaksi</td></tr>
          <?php else: foreach ($recentSales as $s): ?>
          <tr>
            <td><span style="font-family:monospace;font-size:12px"><?= $s['invoice_no'] ?></span></td>
            <td><?= htmlspecialchars($s['customer_name']) ?></td>
            <td class="amount"><?= formatRupiah($s['total']) ?></td>
            <td><span class="badge badge-<?= $s['status']==='selesai'?'success':($s['status']==='batal'?'danger':'warning') ?>"><?= ucfirst($s['status']) ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Quick Access -->
<div class="card" style="margin-top:0">
  <div class="card-header"><span class="card-title">Akses Cepat</span></div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="<?= APP_URL ?>/modules/pos/kasir.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> Buka Kasir</a>
    <a href="<?= APP_URL ?>/modules/inventory/purchases.php" class="btn btn-secondary"><i class="fas fa-truck-loading"></i> Catat Pembelian</a>
    <a href="<?= APP_URL ?>/modules/accounting/journal.php" class="btn btn-secondary"><i class="fas fa-book"></i> Input Jurnal</a>
    <a href="<?= APP_URL ?>/modules/accounting/expenses.php" class="btn btn-secondary"><i class="fas fa-file-invoice-dollar"></i> Catat Pengeluaran</a>
    <a href="<?= APP_URL ?>/modules/reports/laba_rugi.php" class="btn btn-secondary"><i class="fas fa-chart-line"></i> Laba Rugi</a>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: {
    labels: chartData.map(d => d.label),
    datasets: [{
      label: 'Penjualan',
      data: chartData.map(d => d.amount),
      backgroundColor: 'rgba(0,212,170,.3)',
      borderColor: 'rgba(0,212,170,1)',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#8b949e' }, grid: { color: '#2a3348' } },
      y: { ticks: { color: '#8b949e', callback: v => 'Rp ' + v.toLocaleString('id-ID') }, grid: { color: '#2a3348' } }
    }
  }
});
</script>

<?php include 'includes/footer.php'; ?>
