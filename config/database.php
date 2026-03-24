<?php
// ============================================================
// KONFIGURASI DATABASE & APLIKASI
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_accounting');
define('DB_PORT', 3306);

define('APP_NAME', 'POS & Akuntansi');
define('APP_VERSION', '1.0.0');
define('CURRENCY', 'Rp');
define('TAX_RATE', 0.11); // 11% PPN
define('BASE_URL', (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false)
    ? 'https://' . $_SERVER['HTTP_HOST'] . '/pos_system'
    : 'http://localhost/pos_system');
// Timezone
date_default_timezone_set('Asia/Jakarta');

// ============================================================
// KONEKSI DATABASE (PDO)
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function generateInvoice($prefix = 'INV') {
    return $prefix . '/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -6));
}

function generateJournalNo() {
    return 'JRN/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -6));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' .BASE_URL  . '/index.php');
        exit;
    }
}

function hasRole($roles) {
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['role'] ?? '', $roles);
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
