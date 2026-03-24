<?php if (!defined('APP_NAME')) die('Direct access not allowed'); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-store-alt"></i></div>
    <div class="brand-text">
      <span class="brand-name"><?= APP_NAME ?></span>
      <span class="brand-version">v<?= APP_VERSION ?></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-group">
      <span class="nav-label">UTAMA</span>
      <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>">
        <i class="fas fa-chart-pie"></i><span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/modules/pos/kasir.php" class="nav-item <?= ($activePage??'')==='pos'?'active':'' ?>">
        <i class="fas fa-cash-register"></i><span>Kasir (POS)</span>
      </a>
    </div>

    <div class="nav-group">
      <span class="nav-label">STOK</span>
      <a href="<?= APP_URL ?>/modules/inventory/products.php" class="nav-item <?= ($activePage??'')==='products'?'active':'' ?>">
        <i class="fas fa-boxes"></i><span>Produk</span>
      </a>
      <a href="<?= APP_URL ?>/modules/inventory/purchases.php" class="nav-item <?= ($activePage??'')==='purchases'?'active':'' ?>">
        <i class="fas fa-truck-loading"></i><span>Pembelian</span>
      </a>
      <a href="<?= APP_URL ?>/modules/inventory/stock.php" class="nav-item <?= ($activePage??'')==='stock'?'active':'' ?>">
        <i class="fas fa-warehouse"></i><span>Stok</span>
      </a>
    </div>

    <div class="nav-group">
      <span class="nav-label">PENJUALAN</span>
      <a href="<?= APP_URL ?>/modules/pos/sales_list.php" class="nav-item <?= ($activePage??'')==='sales'?'active':'' ?>">
        <i class="fas fa-receipt"></i><span>Riwayat Penjualan</span>
      </a>
      <a href="<?= APP_URL ?>/modules/customers/index.php" class="nav-item <?= ($activePage??'')==='customers'?'active':'' ?>">
        <i class="fas fa-users"></i><span>Pelanggan</span>
      </a>
      <a href="<?= APP_URL ?>/modules/suppliers/index.php" class="nav-item <?= ($activePage??'')==='suppliers'?'active':'' ?>">
        <i class="fas fa-industry"></i><span>Supplier</span>
      </a>
    </div>

    <div class="nav-group">
      <span class="nav-label">AKUNTANSI</span>
      <a href="<?= APP_URL ?>/modules/accounting/journal.php" class="nav-item <?= ($activePage??'')==='journal'?'active':'' ?>">
        <i class="fas fa-book"></i><span>Jurnal Umum</span>
      </a>
      <a href="<?= APP_URL ?>/modules/accounting/coa.php" class="nav-item <?= ($activePage??'')==='coa'?'active':'' ?>">
        <i class="fas fa-sitemap"></i><span>Akun (COA)</span>
      </a>
      <a href="<?= APP_URL ?>/modules/accounting/expenses.php" class="nav-item <?= ($activePage??'')==='expenses'?'active':'' ?>">
        <i class="fas fa-file-invoice-dollar"></i><span>Pengeluaran</span>
      </a>
      <a href="<?= APP_URL ?>/modules/accounting/ledger.php" class="nav-item <?= ($activePage??'')==='ledger'?'active':'' ?>">
        <i class="fas fa-book-open"></i><span>Buku Besar</span>
      </a>
    </div>

    <div class="nav-group">
      <span class="nav-label">LAPORAN</span>
      <a href="<?= APP_URL ?>/modules/reports/sales_report.php" class="nav-item <?= ($activePage??'')==='sales_report'?'active':'' ?>">
        <i class="fas fa-file-chart-bar"></i><span>Lap. Penjualan</span>
      </a>
      <a href="<?= APP_URL ?>/modules/reports/laba_rugi.php" class="nav-item <?= ($activePage??'')==='laba_rugi'?'active':'' ?>">
        <i class="fas fa-chart-line"></i><span>Laba Rugi</span>
      </a>
      <a href="<?= APP_URL ?>/modules/reports/neraca.php" class="nav-item <?= ($activePage??'')==='neraca'?'active':'' ?>">
        <i class="fas fa-balance-scale"></i><span>Neraca</span>
      </a>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name']??'A',0,1)) ?></div>
      <div class="user-detail">
        <span class="user-name"><?= htmlspecialchars($_SESSION['full_name']??'Admin') ?></span>
        <span class="user-role"><?= ucfirst($_SESSION['role']??'') ?></span>
      </div>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
  <div class="topbar">
    <button class="btn-toggle-sidebar" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
    <div class="topbar-right">
      <span class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('d M Y') ?></span>
    </div>
  </div>

  <?php $flash = getFlash(); if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
    <button onclick="this.parentElement.remove()" class="alert-close">&times;</button>
  </div>
  <?php endif; ?>

  <div class="page-body">
