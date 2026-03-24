<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$pageTitle = 'Riwayat Penjualan';
$activePage = 'sales';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$q    = trim($_GET['q'] ?? '');

$where = "WHERE s.sale_date BETWEEN ? AND ?";
$params = [$from, $to . ' 23:59:59'];
if ($q) { $where .= " AND s.invoice_no LIKE ?"; $params[] = "%$q%"; }

$sales = $pdo->prepare("SELECT s.*, COALESCE(c.name,'Umum') as cust_name, u.full_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id JOIN users u ON s.user_id=u.id $where ORDER BY s.sale_date DESC");
$sales->execute($params); $sales = $sales->fetchAll();

$totalSales = array_sum(array_map(function($s) {
    return (isset($s['status'], $s['total']) && $s['status'] === 'selesai')
        ? $s['total']
        : 0;
}, $sales));

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Riwayat Penjualan</span>
    <a href="<?= APP_URL ?>/modules/pos/kasir.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> Buka Kasir</a>
  </div>
  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <input type="text" name="q" class="form-control" placeholder="Cari invoice..." value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:200px">
    <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:140px">
    <input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:140px">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Cari</button>
  </form>

  <div style="margin-bottom:12px;padding:12px;background:var(--bg-elevated);border-radius:var(--radius-sm);display:flex;gap:24px">
    <div><span style="color:var(--text-muted);font-size:12px">Total Transaksi</span><br><strong><?= count($sales) ?></strong></div>
    <div><span style="color:var(--text-muted);font-size:12px">Total Penjualan</span><br><strong style="color:var(--green)"><?= formatRupiah($totalSales) ?></strong></div>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Invoice</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th>Subtotal</th><th>Total</th><th>Pembayaran</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($sales)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada data</td></tr>
        <?php else: foreach ($sales as $s): ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px"><?= $s['invoice_no'] ?></span></td>
          <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($s['sale_date'])) ?></td>
          <td><?= htmlspecialchars($s['cust_name']) ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($s['full_name']) ?></td>
          <td class="amount"><?= formatRupiah($s['subtotal']) ?></td>
          <td class="amount" style="font-weight:700"><?= formatRupiah($s['total']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst($s['payment_method']) ?></span></td>
          <td><span class="badge badge-<?= $s['status']==='selesai'?'success':($s['status']==='batal'?'danger':'warning') ?>"><?= ucfirst($s['status']) ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
