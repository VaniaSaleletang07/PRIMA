<style>
    /* ============================================================
       SHARED APP SHELL / SIDEBAR STYLES
       (extracted from home.php so the sidebar can persist across pages)
       ============================================================ */
    html, body { height: 100%; }
    body { margin: 0; padding: 0; overflow: hidden; }

    .app-shell {
      display: flex;
      height: 100vh;
    }

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
      text-decoration: none;
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

    .nav-submenu {
      background: rgba(0,0,0,0.2);
    }

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

    .page-content {
      flex: 1;
      overflow-y: auto;
      background: #f1f4f8;
    }

    @media (max-width: 1024px) {
      .top-bar-subtitle { display: none; }
    }

    @media (max-width: 960px) {
      .sidebar { width: 220px; min-width: 220px; }
    }

    @media (max-width: 768px) {
      .app-shell    { flex-direction: column; height: auto; }
      body          { height: auto; overflow: auto; }
      .sidebar      { width: 100%; height: auto; min-width: unset; }
      .main-wrapper { overflow: visible; }
      .page-content { overflow: visible; }
      .top-bar      { height: auto; padding: 12px 16px; flex-wrap: wrap; gap: 10px; }
      .top-bar-right { flex-wrap: wrap; gap: 8px; }
    }
</style>
