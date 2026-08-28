<?php
require_once 'auth/auth.php';
require_once 'config/config.php';
requireLogin();

$user = getCurrentUser();

$pending_count = 0;
$total_users = 0;
$active_users = 0;
$total_checklists = 0;
$alert_count = 0;
$manager_pending_count = 0;
$manager_approved_count = 0;

if (isAdmin()) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT COUNT(*) as count FROM user_registrations WHERE status = 'pending'");
        $pending_count = $stmt->fetch()['count'];
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE username != 'admin'");
        $total_users = $stmt->fetch()['count'];
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND username != 'admin'");
        $active_users = $stmt->fetch()['count'];
        $stmt = $db->query("SELECT COUNT(*) as count FROM formulir_checklist");
        $total_checklists = $stmt->fetch()['count'];
        $vehicle_alerts = getVehicleAlerts(30);
        $alert_count = count($vehicle_alerts);
    } catch(Exception $e) {
        error_log("Dashboard Error: " . $e->getMessage());
    }
}

if (isManager()) {
  try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM formulir_checklist WHERE status_approval = 'signed_hsse'");
    $manager_pending_count = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM formulir_checklist WHERE status_approval = 'approved'");
    $manager_approved_count = (int)$stmt->fetchColumn();
  } catch (Exception $e) {
    error_log('Manager dashboard error: ' . $e->getMessage());
  }
}

$my_vehicles = [];
$pending_dokumen_count = 0;
$pengurus_msg = '';
$pengurus_msg_type = '';
if (isPengurus()) {
    ensurePengurusTablesExist();

    // Handle pengurus self-registering a vehicle
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_kendaraan') {
        $nopol_baru    = strtoupper(trim(sanitizeInput($_POST['nomor_polisi'] ?? '')));
        $transport_baru = sanitizeInput($_POST['nama_transport'] ?? '');
        if (empty($nopol_baru)) {
            $pengurus_msg = 'Nomor polisi tidak boleh kosong.';
            $pengurus_msg_type = 'error';
        } else {
            try {
                $db2 = Database::getInstance()->getConnection();
                $ins = $db2->prepare("INSERT IGNORE INTO pengurus_kendaraan (user_id, nomor_polisi, nama_transport) VALUES (:uid, :nopol, :nt)");
                $ins->execute([':uid' => $user['id'], ':nopol' => $nopol_baru, ':nt' => $transport_baru ?: null]);
                if ($ins->rowCount() > 0) {
                    $pengurus_msg = 'Kendaraan ' . htmlspecialchars($nopol_baru) . ' berhasil didaftarkan. Silakan upload dokumennya.';
                    $pengurus_msg_type = 'success';
                } else {
                    $pengurus_msg = 'Kendaraan ' . htmlspecialchars($nopol_baru) . ' sudah terdaftar di akun Anda.';
                    $pengurus_msg_type = 'info';
                }
            } catch(Exception $e) {
                $pengurus_msg = 'Gagal mendaftarkan kendaraan. Coba lagi.';
                $pengurus_msg_type = 'error';
            }
        }
    }

    $my_vehicles = getPengurusVehicles($user['id']);
    foreach ($my_vehicles as $v) {
        $pending_dokumen_count += (int)($v['pending_dokumen'] ?? 0);
    }

    $ekim_notifikasi = getEkimNotifikasiForUser($user['id']);
}
?><!DOCTYPE html>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title> PT Pertamina Patra Niaga</title>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
      background: #f1f4f8;
      height: 100vh;
      overflow: hidden;
      color: #1a2332;
      font-size: 14px;
      line-height: 1.5;
    }

    .app-shell {
      display: flex;
      height: 100vh;
    }

    /* ============================================================
       SIDEBAR
       ============================================================ */
    .sidebar {
      width: 252px;
      min-width: 252px;
      background: #0d1f35;
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow-y: auto;
      flex-shrink: 0;
    }

    .sidebar-brand {
      padding: 20px 20px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }

    .sidebar-brand img {
      height: 34px;
      object-fit: contain;
      filter: brightness(0) invert(1);
      opacity: 0.9;
      display: block;
      margin-bottom: 8px;
    }

    .sidebar-brand-sub {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      color: rgba(255,255,255,0.35);
    }

    .sidebar-nav {
      padding: 10px 0;
      flex: 1;
    }

    .nav-section-label {
      padding: 16px 20px 6px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 1.4px;
      text-transform: uppercase;
      color: rgba(255,255,255,0.28);
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 20px;
      color: rgba(255,255,255,0.6);
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.15s, color 0.15s;
      border-left: 3px solid transparent;
    }

    .nav-item:hover {
      background: rgba(255,255,255,0.06);
      color: rgba(255,255,255,0.9);
    }

    .nav-item.active {
      background: rgba(255,255,255,0.09);
      color: #ffffff;
      border-left-color: #e63b2e;
    }

    .nav-item svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
      opacity: 0.75;
    }

    .nav-item.active svg { opacity: 1; }

    .nav-badge {
      margin-left: auto;
      background: #c8102e;
      color: white;
      border-radius: 3px;
      padding: 1px 6px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }

    .nav-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 20px;
      color: rgba(255,255,255,0.6);
      font-size: 13.5px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.15s, color 0.15s;
      border-left: 3px solid transparent;
      user-select: none;
    }

    .nav-toggle:hover {
      background: rgba(255,255,255,0.06);
      color: rgba(255,255,255,0.9);
    }

    .nav-toggle-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .nav-toggle svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
      opacity: 0.75;
    }

    .nav-arrow {
      font-size: 9px;
      opacity: 0.45;
      transition: transform 0.2s;
      line-height: 1;
    }

    .nav-toggle.open .nav-arrow { transform: rotate(180deg); }

    .nav-submenu {
      background: rgba(0,0,0,0.2);
      overflow: hidden;
      max-height: 0;
      transition: max-height 0.3s ease;
    }

    .nav-submenu.open { max-height: 300px; }

    .nav-submenu a {
      display: block;
      padding: 9px 20px 9px 46px;
      color: rgba(255,255,255,0.5);
      text-decoration: none;
      font-size: 13px;
      transition: color 0.15s, background 0.15s;
      position: relative;
    }

    .nav-submenu a::before {
      content: "";
      position: absolute;
      left: 30px;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 4px;
      background: rgba(255,255,255,0.25);
      border-radius: 50%;
    }

    .nav-submenu a:hover { color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.04); }
    .nav-submenu a.active-sub { color: #e8a000; }
    .nav-submenu a.active-sub::before { background: #e8a000; }

    .sidebar-footer {
      padding: 14px 20px;
      border-top: 1px solid rgba(255,255,255,0.08);
      font-size: 12px;
      color: rgba(255,255,255,0.35);
      line-height: 1.7;
    }

    .sidebar-footer strong {
      color: rgba(255,255,255,0.65);
      font-weight: 600;
      display: block;
      font-size: 12.5px;
    }

    /* ============================================================
       MAIN WRAPPER
       ============================================================ */
    .main-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0;
    }

    /* TOP BAR */
    .top-bar {
      background: #ffffff;
      height: 58px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      border-bottom: 1px solid #dde3ec;
      flex-shrink: 0;
      z-index: 10;
    }

    .top-bar-left {
      display: flex;
      align-items: center;
      gap: 0;
    }

    .top-bar-accent {
      width: 3px;
      height: 28px;
      background: #c8102e;
      border-radius: 2px;
      margin-right: 14px;
      flex-shrink: 0;
    }

    .top-bar-title {
      font-size: 15px;
      font-weight: 700;
      color: #0d1f35;
      letter-spacing: 0.1px;
    }

    .top-bar-subtitle {
      font-size: 12px;
      color: #7a8ba0;
      margin-left: 10px;
      padding-left: 10px;
      border-left: 1px solid #dde3ec;
    }

    .top-bar-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .top-bar-user-info {
      text-align: right;
      margin-right: 4px;
    }

    .top-bar-user-name {
      font-size: 13px;
      font-weight: 600;
      color: #1a2332;
      display: block;
    }

    .top-bar-user-role {
      font-size: 11px;
      color: #7a8ba0;
      display: block;
    }

    .btn-topbar {
      padding: 6px 14px;
      background: transparent;
      color: #4a5568;
      border: 1px solid #d1d9e0;
      border-radius: 4px;
      font-size: 12.5px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.15s;
      white-space: nowrap;
    }

    .btn-topbar:hover {
      background: #f8fafc;
      border-color: #b0bec8;
      color: #1a2332;
    }

    .btn-topbar-danger {
      padding: 6px 14px;
      background: transparent;
      color: #c8102e;
      border: 1px solid #c8102e;
      border-radius: 4px;
      font-size: 12.5px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.15s;
      white-space: nowrap;
    }

    .btn-topbar-danger:hover {
      background: #c8102e;
      color: white;
    }

    /* PAGE CONTENT */
    .page-content {
      flex: 1;
      overflow-y: auto;
      padding: 26px 28px;
      background: #f1f4f8;
    }

    .content-panel { display: none; }
    .content-panel.active { display: block; }

    /* ============================================================
       ADMIN DASHBOARD PANEL
       ============================================================ */
    .page-heading {
      margin-bottom: 22px;
      padding-bottom: 18px;
      border-bottom: 1px solid #dde3ec;
    }

    .page-heading h2 {
      font-size: 20px;
      font-weight: 700;
      color: #0d1f35;
      margin-bottom: 3px;
    }

    .page-heading p {
      font-size: 13px;
      color: #7a8ba0;
    }

    /* Metric cards */
    .metrics-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 26px;
    }

    .metric-card {
      background: white;
      padding: 20px 22px;
      border-radius: 6px;
      border: 1px solid #dde3ec;
      border-top: 3px solid #0d1f35;
      position: relative;
    }

    .metric-label {
      font-size: 11px;
      font-weight: 700;
      color: #7a8ba0;
      text-transform: uppercase;
      letter-spacing: 0.7px;
      margin-bottom: 12px;
    }

    .metric-value {
      font-size: 32px;
      font-weight: 700;
      color: #0d1f35;
      line-height: 1;
      font-variant-numeric: tabular-nums;
      margin-bottom: 6px;
    }

    .metric-status {
      font-size: 11.5px;
      font-weight: 600;
      color: #7a8ba0;
    }

    .metric-status.alert { color: #c8102e; }
    .metric-status.ok    { color: #059669; }

    /* Section heading */
    .section-heading {
      font-size: 13px;
      font-weight: 700;
      color: #0d1f35;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 14px;
      padding-bottom: 8px;
      border-bottom: 1px solid #e8ecf2;
    }

    /* Action cards grid */
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 28px;
    }

    .action-card {
      background: white;
      padding: 18px 20px;
      border-radius: 6px;
      border: 1px solid #dde3ec;
      border-left: 3px solid #c8102e;
      text-decoration: none;
      display: block;
      transition: box-shadow 0.18s, border-color 0.18s;
    }

    .action-card:hover {
      box-shadow: 0 4px 18px rgba(13,31,53,0.10);
      border-color: #c8b8bb;
      border-left-color: #c8102e;
    }

    .action-card-category {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.9px;
      color: #7a8ba0;
      margin-bottom: 6px;
    }

    .action-card h4 {
      font-size: 13.5px;
      font-weight: 700;
      color: #0d1f35;
      margin-bottom: 5px;
      line-height: 1.3;
    }

    .action-card p {
      font-size: 12px;
      color: #7a8ba0;
      line-height: 1.5;
      margin: 0;
    }

    .action-card-badge {
      display: inline-block;
      margin-top: 10px;
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 700;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    .action-card-badge.ok {
      background: #f0fdf4;
      color: #15803d;
      border-color: #bbf7d0;
    }

    /* APP FOOTER */
    .app-footer {
      background: white;
      border-top: 1px solid #dde3ec;
      padding: 10px 28px;
      font-size: 11.5px;
      color: #a0aec0;
      flex-shrink: 0;
    }

    /* ============================================================
       RESPONSIVE
       ============================================================ */
    @media (max-width: 1280px) {
      .metrics-row  { grid-template-columns: repeat(2, 1fr); }
      .actions-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 1024px) {
      .top-bar-subtitle { display: none; }
    }

    @media (max-width: 960px) {
      .sidebar { width: 220px; min-width: 220px; }
      .data-cols { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
      .app-shell    { flex-direction: column; height: auto; }
      body          { height: auto; overflow: auto; }
      .sidebar      { width: 100%; height: auto; min-width: unset; }
      .main-wrapper { overflow: visible; }
      .page-content { overflow: visible; }
      .metrics-row  { grid-template-columns: 1fr 1fr; }
      .actions-grid { grid-template-columns: 1fr; }
      .top-bar      { height: auto; padding: 12px 16px; flex-wrap: wrap; gap: 10px; }
      .top-bar-right { flex-wrap: wrap; gap: 8px; }
      .page-content { padding: 16px; }
    }
  </style>
</head>
<body>
  <div class="app-shell">

    <!-- ====== SIDEBAR ====== -->
    <aside class="sidebar">
      <div class="sidebar-brand">
        <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="PT Pertamina Patra Niaga">
        <span class="sidebar-brand-sub">PRIMA (Pertamina Checklist Mobil Tangki)</span>
      </div>

      <nav class="sidebar-nav">

        <?php if (isManager()): ?>
        <a href="#" class="nav-item active" id="navManagerDashboard" data-section="section-manager-dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
          </svg>
          Dashboard
        </a>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <a href="#" class="nav-item active" id="navDashboard" data-section="section-dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
          </svg>
          Dashboard
        </a>
        <?php endif; ?>

        <?php if (isPengurus()): ?>
        <a href="#" class="nav-item active" id="navPengurus" data-section="section-pengurus">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="1" y="3" width="15" height="13" rx="1"/>
            <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
          Kendaraan Saya
          <?php if ($pending_dokumen_count > 0): ?>
          <span style="margin-left:auto;background:#c8102e;color:white;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;"><?php echo $pending_dokumen_count; ?></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (!isPengurus() && !isManager()): ?>
        <div class="nav-toggle open" id="toggleChecklist">
          <div class="nav-toggle-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14,2 14,8 20,8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Input Checklist
          </div>
          <span class="nav-arrow">&#9660;</span>
        </div>
        <div class="nav-submenu open" id="checklistSubmenu">
          <a href="index.html?jenis=spbu">Checklist MT SPBU</a>
          <a href="index-industri.html?jenis=industri">Checklist MT Industri</a>
          <a href="#" id="navDataDB" data-section="section-data" class="<?php echo isAdmin() ? '' : 'active-sub'; ?>">Data &amp; Database</a>
        </div>

        <a href="api/list.php" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <line x1="8" y1="6" x2="21" y2="6"/>
            <line x1="8" y1="12" x2="21" y2="12"/>
            <line x1="8" y1="18" x2="21" y2="18"/>
            <line x1="3" y1="6" x2="3.01" y2="6"/>
            <line x1="3" y1="12" x2="3.01" y2="12"/>
            <line x1="3" y1="18" x2="3.01" y2="18"/>
          </svg>
          Data Checklist
        </a>

        <?php if (isAdmin()): ?>
        <a href="vehicles/kelola-kendaraan.php" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="1" y="3" width="15" height="13" rx="1"/>
            <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            <line x1="5.5" y1="7" x2="11" y2="7"/><line x1="8.25" y1="4.5" x2="8.25" y2="9.5"/>
          </svg>
          Kelola Kendaraan
        </a>
        <?php endif; ?>

        <a href="vehicles/register-vehicle.php" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="1" y="3" width="15" height="13" rx="1"/>
            <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/>
            <circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
          Registrasi Kendaraan
        </a>
        <?php endif; ?>

        <?php if (isManager()): ?>
        <a href="admin/pending-approval.php" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M20 6 9 17l-5-5"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10"/>
          </svg>
          Checklist Menunggu Persetujuan
          <?php if ($manager_pending_count > 0): ?>
          <span class="nav-badge" id="manager-pending-badge"><?php echo $manager_pending_count; ?></span>
          <?php endif; ?>
        </a>
        <a href="api/list.php?status=approved" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
          Checklist Approved
        </a>
        <a href="api/list.php" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
            <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
          </svg>
          Histori Checklist
        </a>
        <?php endif; ?>

      </nav>

      <div class="sidebar-footer">
        <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
        <?php echo getRoleLabel(); ?>
      </div>
    </aside>

    <!-- ====== MAIN WRAPPER ====== -->
    <div class="main-wrapper">

      <header class="top-bar">
        <div class="top-bar-left">
          <span class="top-bar-accent"></span>
          <span class="top-bar-title">Kartu Izin Masuk (KIM)</span>
          <span class="top-bar-subtitle">PT Pertamina Patra Niaga</span>
        </div>
        <div class="top-bar-right">
          <div class="top-bar-user-info">
            <span class="top-bar-user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
            <span class="top-bar-user-role"><?php echo getRoleLabel(); ?></span>
          </div>
          <?php if (!isManager()): ?>
          <a href="api/get-user.php" class="btn-topbar">Akun HSSE</a>
          <?php endif; ?>
          <a href="auth/logout.php" class="btn-topbar-danger">Keluar</a>
        </div>
      </header>

      <div class="page-content">

        <?php if (isManager()): ?>
        <div id="section-manager-dashboard" class="content-panel active">
          <div class="page-heading">
            <h2>Dashboard Manager</h2>
            <p>Review checklist yang telah ditandatangani HSSE dan berikan persetujuan akhir.</p>
          </div>
          <div class="metrics-row">
            <a href="admin/pending-approval.php" class="metric-card" style="text-decoration:none;color:inherit;">
              <div class="metric-label">Checklist Menunggu Approval</div>
              <div class="metric-value"><?php echo $manager_pending_count; ?></div>
              <div class="metric-status <?php echo $manager_pending_count > 0 ? 'alert' : 'ok'; ?>">
                <?php echo $manager_pending_count > 0 ? 'Perlu Ditinjau' : 'Tidak Ada Antrian'; ?>
              </div>
            </a>
            <a href="api/list.php?status=approved" class="metric-card" style="text-decoration:none;color:inherit;">
              <div class="metric-label">Checklist Approved</div>
              <div class="metric-value"><?php echo $manager_approved_count; ?></div>
              <div class="metric-status ok">Telah Disetujui</div>
            </a>
          </div>
          <div class="section-heading">Akses Cepat</div>
          <div class="actions-grid">
            <a href="admin/pending-approval.php" class="action-card">
              <div class="action-card-category">Persetujuan</div>
              <h4>Checklist Menunggu Persetujuan</h4>
              <p>Lihat detail, review hasil pemeriksaan, dan berikan approval digital.</p>
              <?php if ($manager_pending_count > 0): ?><span class="action-card-badge"><?php echo $manager_pending_count; ?> Menunggu</span><?php endif; ?>
            </a>
            <a href="api/list.php?status=approved" class="action-card">
              <div class="action-card-category">Riwayat</div>
              <h4>Checklist Approved</h4>
              <p>Lihat dokumen yang telah disetujui beserta QR Code verifikasinya.</p>
            </a>
            <a href="api/list.php" class="action-card">
              <div class="action-card-category">Riwayat</div>
              <h4>Histori Checklist</h4>
              <p>Lihat seluruh checklist dalam mode baca saja.</p>
            </a>
          </div>
        </div>
        <?php endif; ?>

        <!-- ====== ADMIN DASHBOARD ====== -->
        <?php if (isAdmin()): ?>
        <div id="section-dashboard" class="content-panel active">

          <div class="page-heading">
            <h2>Dashboard Administrator</h2>
            <p>Ringkasan aktivitas dan akses cepat ke fungsi administrasi sistem.</p>
          </div>

          <div class="metrics-row">
            <div class="metric-card">
              <div class="metric-label">Pendaftaran Pending</div>
              <div class="metric-value"><?php echo $pending_count; ?></div>
              <div class="metric-status <?php echo $pending_count > 0 ? 'alert' : ''; ?>">
                <?php echo $pending_count > 0 ? 'Menunggu Persetujuan' : 'Tidak Ada Antrian'; ?>
              </div>
            </div>
            <div class="metric-card">
              <div class="metric-label">User Aktif</div>
              <div class="metric-value"><?php echo $active_users; ?></div>
              <div class="metric-status ok">Status Aktif</div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Total User</div>
              <div class="metric-value"><?php echo $total_users; ?></div>
              <div class="metric-status">Terdaftar</div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Total Checklist</div>
              <div class="metric-value"><?php echo $total_checklists; ?></div>
              <div class="metric-status">Entri Data</div>
            </div>
          </div>

          <div class="section-heading">Akses Cepat</div>
          <div class="actions-grid">

            <a href="admin/approve-registrations.php" class="action-card">
              <div class="action-card-category">Manajemen User</div>
              <h4>Review Pendaftaran</h4>
              <p>Setujui atau tolak permintaan pendaftaran akun baru.</p>
              <?php if ($pending_count > 0): ?>
                <span class="action-card-badge"><?php echo $pending_count; ?> Pending</span>
              <?php endif; ?>
            </a>

            <a href="vehicles/vehicle-alerts.php" class="action-card">
              <div class="action-card-category">Inspeksi Kendaraan</div>
              <h4>Notifikasi Inspeksi</h4>
              <p>Kendaraan yang masa berlaku KIM-nya akan atau telah habis.</p>
              <?php if ($alert_count > 0): ?>
                <span class="action-card-badge"><?php echo $alert_count; ?> Kendaraan</span>
              <?php else: ?>
                <span class="action-card-badge ok">Semua Valid</span>
              <?php endif; ?>
            </a>

            <a href="admin/manage-users.php" class="action-card">
              <div class="action-card-category">Manajemen User</div>
              <h4>Kelola Akun User</h4>
              <p>Aktifkan, nonaktifkan, atau ubah akun pengguna sistem.</p>
            </a>

            <a href="api/list.php" class="action-card">
              <div class="action-card-category">Data</div>
              <h4>Data Checklist</h4>
              <p>Lihat seluruh data checklist yang telah diinput pengguna.</p>
            </a>

            <a href="admin/audit-logs.php" class="action-card">
              <div class="action-card-category">Keamanan</div>
              <h4>Audit Log Sistem</h4>
              <p>Pantau seluruh aktivitas dan perubahan data dalam sistem.</p>
            </a>

            <a href="config/system-settings.php" class="action-card">
              <div class="action-card-category">Sistem</div>
              <h4>Pengaturan Sistem</h4>
              <p>Konfigurasi, backup database, dan pemeliharaan sistem.</p>
            </a>


            <a href="vehicles/register-vehicle.php" class="action-card">
              <div class="action-card-category">Kendaraan</div>
              <h4>Registrasi Kendaraan</h4>
              <p>Daftarkan kendaraan baru ke dalam database sistem.</p>
            </a>

            <a href="scripts/email-notifikasi.php" class="action-card">
              <div class="action-card-category">Notifikasi</div>
              <h4>Notifikasi Email KIM</h4>
              <p>Kirim email otomatis ke kontraktor saat masa berlaku KIM kendaraan habis.</p>
            </a>

            <a href="admin/dokumen-admin.php" class="action-card">
              <div class="action-card-category">Pengurus</div>
              <h4>Dokumen Pengurus</h4>
              <p>Review dokumen yang diupload pengurus mobil tangki dan kelola penugasan kendaraan.</p>
            </a>

            <a href="vehicles/kelola-kendaraan.php" class="action-card">
              <div class="action-card-category">Kendaraan</div>
              <h4>Kelola Kendaraan</h4>
              <p>Tambah, ubah, dan hapus data kendaraan. Edit nomor polisi, merk, nama transport, tanggal KIM, dan lainnya.</p>
            </a>

          </div>
        </div>
        <?php endif; ?>

        <!-- ====== PENGURUS PANEL ====== -->
        <?php if (isPengurus()): ?>
        <div id="section-pengurus" class="content-panel active">

        <div class="db-panel-header">
          <h2>Kendaraan Saya</h2>
          <p>Daftarkan kendaraan Anda, lalu upload foto surat-surat yang diperlukan untuk inspeksi KIM.</p>
        </div>

        <!-- Notifikasi status EKIM (diterbitkan / diblokir / ditolak) -->
        <div style="background:white;border:1px solid #dde3ec;border-radius:6px;padding:18px 22px;margin-bottom:18px;">
          <div style="font-size:13px;font-weight:700;color:#0d1f35;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Notifikasi EKIM
          </div>
          <?php if (empty($ekim_notifikasi)): ?>
          <div style="font-size:13px;color:#9aacbb;">Belum ada notifikasi status EKIM untuk kendaraan Anda.</div>
          <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:8px;max-height:340px;overflow-y:auto;">
            <?php foreach ($ekim_notifikasi as $n):
              $st = $n['status'];
              $color = $st === 'issued' ? '#15803d' : ($st === 'blocked' ? '#c8102e' : '#b45309');
              $bg    = $st === 'issued' ? '#dcfce7' : ($st === 'blocked' ? '#fee2e2' : '#fef3c7');
              $label = $st === 'issued' ? 'EKIM Diterbitkan' : ($st === 'blocked' ? 'EKIM Tidak Dapat Diterbitkan' : 'Formulir Ditolak');
            ?>
            <div style="border-left:4px solid <?php echo $color; ?>;background:<?php echo $n['is_new'] ? $bg : '#f8fafc'; ?>;border-radius:4px;padding:10px 14px;">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <span style="font-size:12px;font-weight:700;color:<?php echo $color; ?>;"><?php echo $label; ?> &mdash; <?php echo htmlspecialchars($n['nomor_polisi']); ?></span>
                <span style="font-size:11px;color:#9aacbb;"><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></span>
              </div>
              <div style="font-size:12px;color:#4a5568;margin-top:4px;line-height:1.5;"><?php echo htmlspecialchars($n['pesan']); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($pengurus_msg): ?>
        <div style="padding:10px 16px;border-radius:5px;margin-bottom:16px;font-size:13px;font-weight:600;
          <?php echo $pengurus_msg_type === 'success' ? 'background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;' : ($pengurus_msg_type === 'error' ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;' : 'background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;'); ?>">
          <?php echo $pengurus_msg; ?>
        </div>
        <?php endif; ?>

        <!-- Form tambah kendaraan -->
        <div style="background:white;border:1px solid #dde3ec;border-radius:6px;padding:20px 22px;margin-bottom:18px;">
          <div style="font-size:13px;font-weight:700;color:#0d1f35;margin-bottom:14px;">
            + Daftarkan Kendaraan &amp; Upload Dokumen
          </div>
          <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="action" value="tambah_kendaraan">
            <div style="flex:1;min-width:160px;">
              <div style="font-size:11px;font-weight:700;color:#4a5568;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Nomor Polisi *</div>
              <input type="text" name="nomor_polisi" placeholder="Contoh: B 1234 ABC"
                style="width:100%;padding:9px 12px;border:1px solid #d1d9e0;border-radius:4px;font-size:13px;font-family:inherit;text-transform:uppercase;"
                required maxlength="20">
            </div>
            <div style="flex:2;min-width:200px;">
              <div style="font-size:11px;font-weight:700;color:#4a5568;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Nama Perusahaan / Transport <span style="font-weight:400;text-transform:none;">(opsional)</span></div>
              <input type="text" name="nama_transport" placeholder="Contoh: PT Maju Jaya Transport"
                style="width:100%;padding:9px 12px;border:1px solid #d1d9e0;border-radius:4px;font-size:13px;font-family:inherit;"
                maxlength="100">
            </div>
            <div>
              <button type="submit" style="display:inline-flex;align-items:center;gap:6px;background:#c8102e;color:white;border:none;border-radius:4px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Daftarkan
              </button>
            </div>
          </form>
          <div style="margin-top:10px;font-size:12px;color:#9aacbb;">
            Setelah kendaraan terdaftar, klik <strong>Upload Dokumen</strong> untuk mengirim foto:
            STNK &bull; Pajak Kendaraan &bull; SIMFIT (Industri) &bull; Surat Tera Metrologi &bull; Surat Keur DLLAAJR
          </div>
        </div>

        <?php if (!empty($my_vehicles)): ?>
        <div style="display:grid;gap:12px;">
          <?php foreach ($my_vehicles as $v):
            $total   = (int)($v['total_dokumen']     ?? 0);
            $pending = (int)($v['pending_dokumen']   ?? 0);
            $ok      = (int)($v['disetujui_dokumen'] ?? 0);
            $ditolak = (int)($v['ditolak_dokumen']   ?? 0);
          ?>
          <div style="background:white;border:1px solid #dde3ec;border-radius:6px;border-left:4px solid <?php echo ($ditolak > 0) ? '#c8102e' : (($ok === 0 && $total === 0) ? '#d97706' : '#0d1f35'); ?>;padding:16px 20px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
            <div style="flex:1;min-width:180px;">
              <div style="font-size:15px;font-weight:700;color:#0d1f35;"><?php echo htmlspecialchars($v['nomor_polisi']); ?></div>
              <div style="font-size:12px;color:#7a8ba0;margin-top:2px;"><?php echo htmlspecialchars($v['nama_transport'] ?: 'Belum ada nama transport'); ?></div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <?php if ($total === 0): ?>
                <span style="font-size:12px;color:#b45309;background:#fef3c7;padding:3px 10px;border-radius:4px;font-weight:700;">&#9888; Belum ada dokumen</span>
              <?php else: ?>
                <?php if ($ok > 0): ?>
                  <span style="font-size:12px;color:#15803d;background:#dcfce7;padding:3px 10px;border-radius:4px;font-weight:700;">&#10003; <?php echo $ok; ?> Disetujui</span>
                <?php endif; ?>
                <?php if ($pending > 0): ?>
                  <span style="font-size:12px;color:#92400e;background:#fef9c3;padding:3px 10px;border-radius:4px;font-weight:700;">&#9203; <?php echo $pending; ?> Menunggu</span>
                <?php endif; ?>
                <?php if ($ditolak > 0): ?>
                  <span style="font-size:12px;color:#991b1b;background:#fee2e2;padding:3px 10px;border-radius:4px;font-weight:700;">&#10007; <?php echo $ditolak; ?> Ditolak — upload ulang</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <a href="documents/upload-dokumen.php?nopol=<?php echo urlencode($v['nomor_polisi']); ?>" style="display:inline-flex;align-items:center;gap:6px;background:#c8102e;color:white;border:none;border-radius:4px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Upload / Lihat Dokumen
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php endif; ?>

      </div><!-- /page-content -->

      <footer class="app-footer">
        PRIMA (Pertamina Checklist Mobil Tangki) &nbsp;&mdash;&nbsp; &copy; 2026 PT Pertamina Patra Niaga. Seluruh hak dilindungi.
      </footer>

    </div><!-- /main-wrapper -->
  </div><!-- /app-shell -->

  <script>
    document.addEventListener('DOMContentLoaded', function () {

      // Submenu toggle
      const toggleBtn = document.getElementById('toggleChecklist');
      const submenu   = document.getElementById('checklistSubmenu');
      if (toggleBtn && submenu) {
        toggleBtn.addEventListener('click', function () {
          const open = submenu.classList.contains('open');
          submenu.classList.toggle('open', !open);
          toggleBtn.classList.toggle('open', !open);
        });
      }

      // Section navigation
      const sectionLinks = document.querySelectorAll('[data-section]');
      const panels       = document.querySelectorAll('.content-panel');
      const navItems     = document.querySelectorAll('.nav-item[data-section]');

      function activateSection(id) {
        panels.forEach(p => p.classList.remove('active'));
        const target = document.getElementById(id);
        if (target) target.classList.add('active');
        navItems.forEach(item => item.classList.toggle('active', item.dataset.section === id));
        document.querySelectorAll('.nav-submenu a[data-section]').forEach(a => {
          a.classList.toggle('active-sub', a.dataset.section === id);
        });
      }

      sectionLinks.forEach(link => {
        link.addEventListener('click', function (e) {
          e.preventDefault();
          activateSection(this.dataset.section);
        });
      });

      <?php if (isManager()): ?>
      async function refreshManagerPendingCount() {
        try {
          const response = await fetch('api/api-manager-pending-count.php', { cache: 'no-store' });
          const result = await response.json();
          const badge = document.getElementById('manager-pending-badge');
          if (!result.success || !badge) return;
          badge.textContent = result.data.count;
          badge.style.display = result.data.count > 0 ? '' : 'none';
        } catch (error) {
          console.error('Manager pending-count update failed:', error);
        }
      }
      setInterval(refreshManagerPendingCount, 30000);
      <?php endif; ?>

    });
  </script>
</body>
</html>
