<?php
/**
 * Shared sidebar navigation, extracted from home.php so it can persist
 * across standalone pages (list.php, kelola-kendaraan.php, register-vehicle.php, ...).
 *
 * Expects: $user (from getCurrentUser()) and auth.php/config.php already required
 * by the including page. Set $activeNav before including to highlight the
 * current page: 'checklist' | 'kelola' | 'register' | 'pending' | 'approved'.
 */
$activeNav = $activeNav ?? '';
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="PT Pertamina Patra Niaga">
    <span class="sidebar-brand-sub">PRIMA (Pertamina Checklist Mobil Tangki)</span>
  </div>

  <nav class="sidebar-nav">

    <?php if (isManager()): ?>
    <a href="home.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
      </svg>
      Dashboard
    </a>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <a href="home.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
      </svg>
      Dashboard
    </a>
    <?php endif; ?>

    <?php if (isPengurus()): ?>
    <a href="home.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="1" y="3" width="15" height="13" rx="1"/>
        <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
        <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
      </svg>
      Kendaraan Saya
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
      <a href="home.php">Data &amp; Database</a>
    </div>

    <a href="list.php" class="nav-item<?php echo $activeNav === 'checklist' ? ' active' : ''; ?>">
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
    <a href="kelola-kendaraan.php" class="nav-item<?php echo $activeNav === 'kelola' ? ' active' : ''; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="1" y="3" width="15" height="13" rx="1"/>
        <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
        <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
        <line x1="5.5" y1="7" x2="11" y2="7"/><line x1="8.25" y1="4.5" x2="8.25" y2="9.5"/>
      </svg>
      Kelola Kendaraan
    </a>
    <?php endif; ?>

    <a href="register-vehicle.php" class="nav-item<?php echo $activeNav === 'register' ? ' active' : ''; ?>">
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
    <a href="pending-approval.php" class="nav-item<?php echo $activeNav === 'pending' ? ' active' : ''; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M20 6 9 17l-5-5"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10"/>
      </svg>
      Checklist Menunggu Persetujuan
    </a>
    <a href="list.php?status=approved" class="nav-item<?php echo $activeNav === 'approved' ? ' active' : ''; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
      Checklist Approved
    </a>
    <a href="list.php" class="nav-item<?php echo $activeNav === 'checklist' ? ' active' : ''; ?>">
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
