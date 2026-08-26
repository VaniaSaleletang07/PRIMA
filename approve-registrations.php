<?php
/**
 * Approve/Reject User Registrations
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

$user = getCurrentUser();

// Get all registration requests
try {
    $db = Database::getInstance()->getConnection();
    
    // Try to use VIEW first (better for hosting performance)
    // Fallback to direct query if VIEW doesn't exist
    try {
        $stmt = $db->query("SELECT * FROM v_registration_requests");
        $registrations = $stmt->fetchAll();
    } catch(Exception $viewError) {
        // VIEW not exists, use direct query
        error_log("View not found, using direct query: " . $viewError->getMessage());
        $stmt = $db->query("
            SELECT 
                ur.id,
                ur.username,
                ur.full_name,
                ur.email,
                ur.phone,
                ur.department,
                ur.position,
                ur.reason,
                COALESCE(ur.requested_role, 'user') as requested_role,
                ur.status,
                ur.created_at,
                ur.reviewed_at,
                ur.rejection_reason,
                u.full_name as reviewed_by_name
            FROM user_registrations ur
            LEFT JOIN users u ON ur.reviewed_by = u.id
            ORDER BY ur.created_at DESC
        ");
        $registrations = $stmt->fetchAll();
    }
} catch(Exception $e) {
    error_log("Approve Registrations Error: " . $e->getMessage());
    $registrations = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Pendaftaran - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            color: #1a2332;
            font-size: 14px;
            line-height: 1.5;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0;
            }
            to { 
                opacity: 1;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #c8102e 0%, #a00d26 100%);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(200, 16, 46, 0.2);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .page-header h1 {
            color: white;
            font-size: 28px;
            margin: 0;
            font-weight: 700;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .registrations-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%);
            padding: 0 20px;
        }

        .tab {
            padding: 18px 32px;
            cursor: pointer;
            border-bottom: 4px solid transparent;
            transition: all 0.3s;
            font-weight: 600;
            color: #666;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 4px;
            background: linear-gradient(90deg, #c8102e 0%, #ff4757 100%);
            transition: width 0.3s ease;
        }

        .tab.active::before {
            width: 100%;
        }

        .tab.active {
            color: #c8102e;
            background: white;
        }

        .tab:hover {
            color: #c8102e;
            background: rgba(200, 16, 46, 0.05);
        }

        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 8px;
            background: linear-gradient(135deg, #c8102e 0%, #ff4757 100%);
            color: white;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(200, 16, 46, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .tab-content {
            display: none;
            padding: 30px;
            animation: fadeIn 0.4s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        .reg-card {
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: linear-gradient(to bottom, #ffffff 0%, #fafbfc 100%);
            position: relative;
            overflow: hidden;
        }

        .reg-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #c8102e 0%, #ff4757 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .reg-card:hover::before {
            transform: scaleY(1);
        }

        .reg-card:hover {
            border-color: #c8102e;
            box-shadow: 0 8px 24px rgba(200, 16, 46, 0.15);
            transform: translateY(-4px);
        }

        .reg-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0f0f0;
        }

        .reg-info h3 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 6px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reg-meta {
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-pending {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #854d0e;
        }

        .status-approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .reg-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            font-size: 14px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #c8102e;
            transition: all 0.3s;
        }

        .detail-item:hover {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transform: translateX(4px);
        }

        .detail-label {
            color: #6c757d;
            font-size: 11px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .reason-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
            position: relative;
        }

        .reason-box .detail-label {
            margin-bottom: 10px;
            color: #495057;
        }

        .reason-box .detail-value {
            color: #555;
            font-weight: normal;
            line-height: 1.7;
        }

        .reg-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-approve {
            padding: 12px 28px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve::before {
            content: '✓';
            font-size: 16px;
            font-weight: bold;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-approve:active {
            transform: translateY(0);
        }

        .btn-reject {
            padding: 12px 28px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reject::before {
            content: '✕';
            font-size: 16px;
            font-weight: bold;
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-reject:active {
            transform: translateY(0);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }

        .empty-state svg {
            width: 140px;
            height: 140px;
            margin-bottom: 24px;
            opacity: 0.2;
        }

        .empty-state p:first-of-type {
            font-size: 20px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .reviewed-info {
            font-size: 13px;
            color: #6c757d;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reviewed-info::before {
            content: 'ℹ️';
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 32px;
            max-width: 540px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            min-height: 120px;
            margin-bottom: 20px;
            transition: all 0.3s;
            resize: vertical;
        }

        .modal-content textarea:focus {
            outline: none;
            border-color: #c8102e;
            box-shadow: 0 0 0 3px rgba(200, 16, 46, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 12px 28px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .user-info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: linear-gradient(to right, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 3px solid #c8102e;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .user-info-text {
            font-size: 14px;
            color: #666;
        }

        .user-info-text strong {
            color: #2c3e50;
            font-weight: 700;
        }

        .btn-logout {
            padding: 8px 20px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        /* ========================================
           RESPONSIVE MOBILE DESIGN
           ======================================== */

        @media (max-width: 968px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }

            .user-info-bar {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
                padding: 12px 15px;
            }

            .user-info-text {
                text-align: center;
            }

            .btn-logout {
                width: 100%;
                text-align: center;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .page-header p {
                font-size: 13px;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }

            .btn-back {
                width: 100%;
                font-size: 13px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h3 {
                font-size: 28px;
            }

            .stat-card p {
                font-size: 11px;
            }

            .tabs {
                flex-direction: column;
                gap: 8px;
            }

            .tab-btn {
                width: 100%;
                font-size: 13px;
                padding: 10px 15px;
            }

            .registrations-list {
                gap: 15px;
            }

            .registration-card {
                padding: 16px;
            }

            .reg-header h3 {
                font-size: 16px;
            }

            .reg-info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .reg-info-item {
                padding: 8px;
            }

            .reg-info-label {
                font-size: 11px;
            }

            .reg-info-value {
                font-size: 13px;
            }

            .reg-actions {
                flex-direction: column;
                gap: 8px;
            }

            .btn-approve, .btn-reject {
                width: 100%;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 20px;
            }

            .stat-card h3 {
                font-size: 24px;
            }

            .registration-card {
                padding: 14px;
            }

            .reg-header h3 {
                font-size: 15px;
            }
        }
    </style>
    <style>
    /* ── CORPORATE OVERRIDE ── */
    .page-container { max-width:1280px; margin:0 auto; padding:28px 20px 48px; animation:none; }
    .page-header { background:#fff!important; border:1px solid #dde3ec; border-top:3px solid #c8102e; border-radius:6px; padding:20px 26px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; box-shadow:none; overflow:visible; }
    .page-header::before,.page-header::after { display:none!important; }
    .page-header h1 { color:#1a2332!important; font-size:18px; font-weight:700; position:static; z-index:auto; }
    .page-header h1::before { display:none!important; }
    .page-header p { color:#6b7a8f!important; font-size:12px; position:static; z-index:auto; }
    .btn-back { background:#fff!important; color:#374151!important; padding:8px 16px; border-radius:5px!important; border:1px solid #d1d5db!important; font-weight:500; font-size:13px; backdrop-filter:none; position:static; z-index:auto; box-shadow:none; }
    .btn-back:hover { background:#f9fafb!important; border-color:#9ca3af!important; transform:none; box-shadow:none; }
    .registrations-container { background:#fff; border:1px solid #dde3ec; border-radius:6px; box-shadow:none; }
    .tabs { background:#fafbfc!important; border-bottom:1px solid #dde3ec; padding:0 20px; }
    .tab { padding:13px 22px; border-bottom:2px solid transparent; color:#6b7a8f; font-size:13px; position:static; }
    .tab::before { display:none!important; }
    .tab.active { color:#c8102e!important; border-bottom-color:#c8102e!important; background:transparent!important; }
    .tab:hover { color:#c8102e!important; background:transparent!important; }
    .tab-badge { background:#c8102e!important; border-radius:10px!important; min-width:20px; height:20px; box-shadow:none!important; animation:none!important; }
    .tab-content { padding:24px; animation:none!important; }
    .reg-card { border:1px solid #dde3ec!important; border-radius:6px!important; padding:20px; margin-bottom:14px; background:#fff!important; position:static; overflow:visible; }
    .reg-card::before { display:none!important; }
    .reg-card:hover { border-color:#c8102e!important; box-shadow:none!important; transform:none!important; }
    .reg-header { border-bottom:1px solid #f0f2f5; margin-bottom:16px; padding-bottom:14px; }
    .reg-info h3 { color:#1a2332!important; font-size:15px; }
    .reg-info h3::before { display:none!important; }
    .reg-meta { color:#9aacbb; font-size:12px; }
    .reg-meta::before { display:none!important; }
    .status-badge { border-radius:4px!important; box-shadow:none!important; font-size:11px; }
    .status-pending  { background:#fffbeb!important; color:#92400e!important; border:1px solid #fde68a; }
    .status-approved { background:#f0fdf4!important; color:#166534!important; border:1px solid #bbf7d0; }
    .status-rejected { background:#fef2f2!important; color:#991b1b!important; border:1px solid #fecaca; }
    .reg-details { gap:10px; margin-bottom:14px; }
    .detail-item { border-radius:5px!important; border:1px solid #edf0f4; border-left:2px solid #dde3ec!important; background:#f8fafc!important; transition:none!important; }
    .detail-item:hover { background:#f8fafc!important; box-shadow:none!important; transform:none!important; }
    .detail-label { color:#9aacbb; font-size:10px; }
    .detail-value { color:#1a2332; font-size:13px; }
    .reason-box { background:#f8fafc!important; border:1px solid #edf0f4!important; border-radius:5px!important; position:static; }
    .reason-box::before { display:none!important; }
    .btn-approve { background:#16a34a!important; border:1px solid #16a34a!important; border-radius:5px!important; padding:8px 20px; font-size:13px; box-shadow:none!important; }
    .btn-approve::before { display:none!important; }
    .btn-approve:hover { background:#15803d!important; border-color:#15803d!important; transform:none!important; box-shadow:none!important; }
    .btn-reject { background:#fff!important; color:#c8102e!important; border:1px solid #fecaca!important; border-radius:5px!important; padding:8px 20px; font-size:13px; box-shadow:none!important; }
    .btn-reject::before { display:none!important; }
    .btn-reject:hover { background:#fef2f2!important; border-color:#c8102e!important; transform:none!important; box-shadow:none!important; }
    .empty-state { padding:60px 20px; }
    .empty-state svg { width:60px; height:60px; }
    .reviewed-info { border-top:1px solid #f0f2f5; color:#9aacbb; font-size:12px; }
    .reviewed-info::before { display:none!important; }
    .modal { backdrop-filter:none; }
    .modal-content { border-radius:6px!important; padding:28px; max-width:480px; box-shadow:0 8px 32px rgba(0,0,0,.18)!important; animation:none!important; }
    .modal-content h3 { color:#1a2332!important; font-size:15px; }
    .modal-content h3::before { display:none!important; }
    .modal-content textarea { border:1px solid #d1d5db!important; border-radius:5px!important; padding:10px 12px; }
    .modal-content textarea:focus { border-color:#c8102e!important; box-shadow:0 0 0 3px rgba(200,16,46,.08)!important; }
    .btn-cancel { background:#fff!important; color:#374151!important; border:1px solid #d1d5db!important; border-radius:5px!important; font-size:13px; padding:8px 18px; }
    .btn-cancel:hover { background:#f9fafb!important; transform:none!important; }
    .user-info-bar { background:#fff!important; border-bottom:1px solid #dde3ec!important; box-shadow:none!important; padding:10px 20px; }
    .user-info-text { font-size:13px; color:#6b7a8f; }
    .user-info-text strong { color:#1a2332; }
    .btn-logout { background:#c8102e!important; border-radius:5px!important; box-shadow:none!important; font-size:13px; padding:7px 16px; }
    .btn-logout:hover { background:#a80d26!important; transform:none!important; box-shadow:none!important; }
    </style>
</head>
<body>
    <div class="user-info-bar">
        <span class="user-info-text">Selamat datang, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong> (Administrator)</span>
        <div style="display: flex; gap: 10px;">
            <a href="home.php" class="btn" style="padding: 8px 18px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">← Dashboard</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1>Review Pendaftaran User</h1>
                <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 14px; position: relative; z-index: 1;">
                    Kelola dan review pendaftaran user baru
                </p>
            </div>
            <a href="home.php" class="btn-back">← Kembali ke Dashboard</a>
        </div>

        <div class="registrations-container">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('pending')">
                    Pending
                    <?php 
                    $pending = array_filter($registrations, function($r) { return $r['status'] === 'pending'; });
                    if (count($pending) > 0): 
                    ?>
                        <span class="tab-badge"><?php echo count($pending); ?></span>
                    <?php endif; ?>
                </div>
                <div class="tab" onclick="switchTab('approved')">
                    Approved
                </div>
                <div class="tab" onclick="switchTab('rejected')">
                    Rejected
                </div>
            </div>

            <div id="pending-tab" class="tab-content active">
                <?php
                $pending_regs = array_filter($registrations, function($r) { return $r['status'] === 'pending'; });
                if (count($pending_regs) > 0): 
                    foreach ($pending_regs as $reg): 
                ?>
                    <div class="reg-card">
                        <div class="reg-header">
                            <div class="reg-info">
                                <h3><?php echo htmlspecialchars($reg['full_name']); ?></h3>
                                <div class="reg-meta">
                                    Mendaftar pada: <?php echo date('d/m/Y H:i', strtotime($reg['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge status-pending">Pending</span>
                        </div>

                        <div class="reg-details">
                            <div class="detail-item">
                                <div class="detail-label">Username</div>
                                <div class="detail-value"><?php echo htmlspecialchars($reg['username']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($reg['email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Telepon</div>
                                <div class="detail-value"><?php echo htmlspecialchars($reg['phone']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Departemen</div>
                                <div class="detail-value"><?php echo htmlspecialchars($reg['department']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Jabatan</div>
                                <div class="detail-value"><?php echo htmlspecialchars($reg['position']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Daftar Sebagai</div>
                                <div class="detail-value">
                                    <?php if (($reg['requested_role'] ?? 'user') === 'manager_hsse'): ?>
                                        <span style="display:inline-block;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;font-size:12px;">&#9989; Manager Approval</span>
                                    <?php elseif (($reg['requested_role'] ?? 'user') === 'pengurus'): ?>
                                        <span style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-weight:700;font-size:12px;">&#128666; Pengurus Mobil Tangki</span>
                                    <?php else: ?>
                                        <span style="display:inline-block;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-weight:700;font-size:12px;">&#128100; User (Staff Internal)</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <div class="reason-box">
                            <div class="detail-label">Alasan Pendaftaran</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($reg['reason'])); ?></div>
                        </div>

                        <div class="reg-actions">
                            <button class="btn-approve" onclick="approveRegistration(<?php echo $reg['id']; ?>, '<?php echo htmlspecialchars($reg['full_name']); ?>')">
                                Approve
                            </button>
                            <button class="btn-reject" onclick="showRejectModal(<?php echo $reg['id']; ?>, '<?php echo htmlspecialchars($reg['full_name']); ?>')">
                                Reject
                            </button>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else: 
                ?>
                    <div class="empty-state">
                        <p style="font-size: 20px; margin-bottom: 8px; font-weight: 700; color: #2c3e50;">Tidak ada pendaftaran pending</p>
                        <p style="color: #6c757d;">Semua pendaftaran sudah direview</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="approved-tab" class="tab-content">
                <?php 
                $approved_regs = array_filter($registrations, function($r) { return $r['status'] === 'approved'; });
                if (count($approved_regs) > 0): 
                    foreach ($approved_regs as $reg): 
                ?>
                    <div class="reg-card">
                        <div class="reg-header">
                            <div class="reg-info">
                                <h3><?php echo htmlspecialchars($reg['full_name']); ?></h3>
                                <div class="reg-meta">
                                    <?php echo htmlspecialchars($reg['email']); ?> • 
                                    <?php echo htmlspecialchars($reg['department']); ?>
                                </div>
                            </div>
                            <span class="status-badge status-approved">Approved</span>
                        </div>
                        <div class="reviewed-info">
                            Disetujui oleh <?php echo htmlspecialchars($reg['reviewed_by_name'] ?? 'Admin'); ?> 
                            pada <?php echo date('d/m/Y H:i', strtotime($reg['reviewed_at'])); ?>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else: 
                ?>
                    <div class="empty-state">
                        <p style="font-size: 20px; margin-bottom: 8px; font-weight: 700; color: #2c3e50;">Belum ada pendaftaran yang diapprove</p>
                        <p style="color: #6c757d;">Pendaftaran yang disetujui akan muncul di sini</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="rejected-tab" class="tab-content">
                <?php 
                $rejected_regs = array_filter($registrations, function($r) { return $r['status'] === 'rejected'; });
                if (count($rejected_regs) > 0): 
                    foreach ($rejected_regs as $reg): 
                ?>
                    <div class="reg-card">
                        <div class="reg-header">
                            <div class="reg-info">
                                <h3><?php echo htmlspecialchars($reg['full_name']); ?></h3>
                                <div class="reg-meta">
                                    <?php echo htmlspecialchars($reg['email']); ?> • 
                                    <?php echo htmlspecialchars($reg['department']); ?>
                                </div>
                            </div>
                            <span class="status-badge status-rejected">Rejected</span>
                        </div>
                        <?php if ($reg['rejection_reason']): ?>
                            <div class="reason-box">
                                <div class="detail-label">Alasan Penolakan</div>
                                <div class="detail-value"><?php echo nl2br(htmlspecialchars($reg['rejection_reason'])); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="reviewed-info">
                            Ditolak oleh <?php echo htmlspecialchars($reg['reviewed_by_name'] ?? 'Admin'); ?> 
                            pada <?php echo date('d/m/Y H:i', strtotime($reg['reviewed_at'])); ?>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else: 
                ?>
                    <div class="empty-state">
                        <p style="font-size: 20px; margin-bottom: 8px; font-weight: 700; color: #2c3e50;">Belum ada pendaftaran yang ditolak</p>
                        <p style="color: #6c757d;">Pendaftaran yang ditolak akan muncul di sini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Tolak Pendaftaran</h3>
            <p id="rejectUserName" style="color: #666; margin-bottom: 16px;"></p>
            <textarea id="rejectionReason" placeholder="Masukkan alasan penolakan..."></textarea>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeRejectModal()">Batal</button>
                <button class="btn-reject" onclick="confirmReject()">Tolak Pendaftaran</button>
            </div>
        </div>
    </div>

    <script>
        let currentRejectId = null;

        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(t => {
                t.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        async function approveRegistration(id, name) {
            if (!confirm(`Approve pendaftaran dari ${name}?`)) {
                return;
            }

            try {
                const response = await fetch('process-approve.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id, action: 'approve'})
                });

                const result = await response.json();

                if (result.success) {
                    alert('Pendaftaran berhasil diapprove!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Terjadi kesalahan. Silakan coba lagi.');
            }
        }

        function showRejectModal(id, name) {
            currentRejectId = id;
            document.getElementById('rejectUserName').textContent = `User: ${name}`;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            currentRejectId = null;
        }

        async function confirmReject() {
            const reason = document.getElementById('rejectionReason').value.trim();

            if (!reason) {
                alert('Alasan penolakan harus diisi!');
                return;
            }

            try {
                const response = await fetch('process-approve.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: currentRejectId, 
                        action: 'reject',
                        reason: reason
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Pendaftaran berhasil ditolak!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Terjadi kesalahan. Silakan coba lagi.');
            }

            closeRejectModal();
        }

        // Close modal when clicking outside
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>
