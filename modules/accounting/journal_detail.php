<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$journal = $pdo->query("SELECT j.*, u.full_name FROM journals j JOIN users u ON j.user_id=u.id WHERE j.id=$id")->fetch();
if (!$journal) { echo '<p style="color:var(--red)">Jurnal tidak ditemukan.</p>'; exit; }

$items = $pdo->query("SELECT ji.*, c.code, c.name as coa_name FROM journal_items ji JOIN coa c ON ji.coa_id=c.id WHERE ji.journal_id=$id")->fetchAll();
?>
<table style="width:100%;font-size:13px;margin-bottom:12px">
  <tr><td style="color:var(--text-secondary);width:140px">No. Jurnal</td><td><strong style="font-family:monospace"><?= $journal['journal_no'] ?></strong></td></tr>
  <tr><td style="color:var(--text-secondary)">Tanggal</td><td><?= date('d/m/Y', strtotime($journal['journal_date'])) ?></td></tr>
  <tr><td style="color:var(--text-secondary)">Keterangan</td><td><?= htmlspecialchars($journal['description']) ?></td></tr>
  <tr><td style="color:var(--text-secondary)">Tipe</td><td><span class="badge badge-info"><?= ucfirst($journal['type']) ?></span></td></tr>
  <tr><td style="color:var(--text-secondary)">Input oleh</td><td><?= htmlspecialchars($journal['full_name']) ?></td></tr>
</table>

<table style="width:100%;border-collapse:collapse">
  <thead>
    <tr style="background:var(--bg-elevated)">
      <th style="padding:10px 12px;text-align:left;font-size:11px;color:var(--text-secondary)">Kode Akun</th>
      <th style="padding:10px 12px;text-align:left;font-size:11px;color:var(--text-secondary)">Nama Akun</th>
      <th style="padding:10px 12px;text-align:left;font-size:11px;color:var(--text-secondary)">Keterangan</th>
      <th style="padding:10px 12px;text-align:right;font-size:11px;color:var(--text-secondary)">Debit</th>
      <th style="padding:10px 12px;text-align:right;font-size:11px;color:var(--text-secondary)">Kredit</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $item): ?>
    <tr style="border-bottom:1px solid var(--border-light)">
      <td style="padding:10px 12px;font-family:monospace;font-size:12px"><?= $item['code'] ?></td>
      <td style="padding:10px 12px"><?= htmlspecialchars($item['coa_name']) ?></td>
      <td style="padding:10px 12px;font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($item['description'] ?? '') ?></td>
      <td style="padding:10px 12px;text-align:right;font-family:monospace"><?= $item['debit'] > 0 ? formatRupiah($item['debit']) : '-' ?></td>
      <td style="padding:10px 12px;text-align:right;font-family:monospace"><?= $item['credit'] > 0 ? formatRupiah($item['credit']) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="font-weight:700;background:var(--bg-elevated)">
      <td colspan="3" style="padding:10px 12px;text-align:right">TOTAL</td>
      <td style="padding:10px 12px;text-align:right;font-family:monospace;color:var(--accent)"><?= formatRupiah($journal['total_debit']) ?></td>
      <td style="padding:10px 12px;text-align:right;font-family:monospace;color:var(--accent)"><?= formatRupiah($journal['total_credit']) ?></td>
    </tr>
  </tfoot>
</table>
