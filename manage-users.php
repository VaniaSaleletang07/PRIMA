<?php
/**
 * Manage Users
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

$user = getCurrentUser();

// Get all users (except admin)
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT 
            id, username, full_name, email, phone, department, position, 
            role, status, created_at, last_login, login_attempts, locked_until
        FROM users 
        WHERE username != 'admin' 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch(Exception $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Users - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f5f5f5;
        }        
        .user-info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .user-info-text {
            font-size: 14px;
            color: #666;
        }
        
        .user-info-text strong {
            color: #333;
            font-weight: 600;
        }
        
        .btn-logout {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: #c8102e;
            margin: 0;
            font-size: 24px;
        }

        .btn-back {
            background: #666;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: #444;
        }

        .users-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .table-header h2 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }

        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8f8f8;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .users-table tr:hover {
            background: #fafafa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: #e7f3ff;
            color: #004085;
        }

        .btn-action {
            padding: 6px 12px;
            margin: 0 3px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background: #0056b3;
        }

        .btn-toggle {
            background: #28a745;
            color: white;
        }

        .btn-toggle.deactivate {
            background: #ffc107;
        }

        .btn-toggle:hover {
            opacity: 0.8;
        }

        .btn-reset {
            background: #dc3545;
            color: white;
        }

        .btn-reset:hover {
            background: #bd2130;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-confirm {
            background: #c8102e;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .user-info-detail {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .last-login {
            font-size: 12px;
            color: #999;
        }

        /* ========================================
           RESPONSIVE MOBILE DESIGN
           ======================================== */

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

            .btn-back {
                width: 100%;
                font-size: 13px;
                padding: 10px 15px;
            }

            .users-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .users-table {
                min-width: 800px;
                font-size: 12px;
            }

            .users-table th,
            .users-table td {
                padding: 10px;
                font-size: 12px;
            }

            .badge {
                font-size: 10px;
                padding: 4px 8px;
            }

            .action-btns {
                flex-direction: column;
                gap: 5px;
            }

            .action-btn {
                width: 100%;
                font-size: 11px;
                padding: 6px 10px;
            }

            .modal-content {
                width: 95%;
                margin: 20px auto;
                padding: 20px;
            }

            .modal-actions {
                flex-direction: column;
            }

            .btn-cancel, .btn-confirm {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 20px;
            }

            .users-table {
                min-width: 700px;
                font-size: 11px;
            }
        }
    </style>
    <style>
    /* ── CORPORATE OVERRIDE ── */
    body { background:#f0f2f5; font-family:"Segoe UI",-apple-system,BlinkMacSystemFont,Arial,sans-serif; color:#1a2332; font-size:14px; }
    .user-info-bar { background:#fff!important; border-bottom:1px solid #dde3ec!important; padding:10px 20px; box-shadow:none; }
    .user-info-text { font-size:13px; color:#6b7a8f; }
    .user-info-text strong { color:#1a2332; }
    .btn-logout { background:#c8102e!important; border-radius:5px!important; box-shadow:none; font-size:13px; padding:7px 16px; }
    .btn-logout:hover { background:#a80d26!important; transform:none; }
    .page-container { max-width:1280px; margin:0 auto; padding:28px 20px 48px; }
    .page-header { background:#fff; border:1px solid #dde3ec; border-top:3px solid #c8102e; border-radius:6px; padding:20px 26px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; box-shadow:none; }
    .page-header h1 { color:#1a2332!important; font-size:18px; font-weight:700; margin:0; }
    .btn-back { background:#fff; color:#374151; padding:8px 16px; border-radius:5px; border:1px solid #d1d5db; font-size:13px; font-weight:500; }
    .btn-back:hover { background:#f9fafb; }
    .users-table { background:#fff; border:1px solid #dde3ec; border-radius:6px; box-shadow:none; }
    .table-header { padding:14px 22px; background:#fafbfc; border-bottom:1px solid #dde3ec; }
    .table-header h2 { font-size:13px; font-weight:700; color:#6b7a8f; text-transform:uppercase; letter-spacing:.5px; }
    .users-table th { background:#1e2a3b!important; color:#94a3b8!important; font-size:11px; padding:11px 14px; border-bottom:none; }
    .users-table td { font-size:13px; padding:11px 14px; }
    .users-table tr:hover td { background:#f8fafc; }
    .status-badge { border-radius:4px; font-size:11px; }
    .status-active   { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
    .status-inactive { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
    .status-pending  { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
    .btn-action { border-radius:4px; font-size:12px; padding:5px 10px; }
    .btn-edit   { background:#1d4ed8; }
    .btn-edit:hover { background:#1e40af; }
    .btn-toggle { background:#16a34a; }
    .btn-toggle.deactivate { background:#d97706; }
    .btn-reset  { background:#c8102e; }
    .btn-reset:hover { background:#a80d26; }
    .modal-content { border-radius:6px!important; box-shadow:0 8px 32px rgba(0,0,0,.18); }
    .btn-confirm { background:#c8102e; border-radius:4px; }
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
            <h1>Kelola Users</h1>
            <a href="home.php" class="btn-back">← Kembali ke Dashboard</a>
        </div>

        <div class="users-table">
            <div class="table-header">
                <h2>Daftar Pengguna Terdaftar</h2>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                    <p>Belum ada user terdaftar</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Departemen</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr data-user-id="<?= $u['id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                                    <?php if (isset($u['login_attempts']) && $u['login_attempts'] > 0): ?>
                                        <div class="user-info-detail" style="color: #dc3545;">
                                            <?= $u['login_attempts'] ?> failed login(s)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                    <?php if (!empty($u['phone'])): ?>
                                        <div class="user-info-detail"><?= htmlspecialchars($u['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?= htmlspecialchars($u['department']) ?>
                                    <?php if ($u['position']): ?>
                                        <div class="user-info-detail"><?= htmlspecialchars($u['position']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge"><?= strtoupper($u['role']) ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $u['status'] ?>">
                                        <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['last_login']): ?>
                                        <div class="last-login">
                                            <?= date('d/m/Y H:i', strtotime($u['last_login'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="last-login" style="color: #ccc;">Belum pernah login</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-action btn-edit" onclick="editUser(<?= $u['id'] ?>)">
                                        Edit
                                    </button>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <button class="btn-action btn-toggle deactivate" onclick="toggleUserStatus(<?= $u['id'] ?>, 'inactive')">
                                            Nonaktifkan
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-action btn-toggle" onclick="toggleUserStatus(<?= $u['id'] ?>, 'active')">
                                            Aktifkan
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-action btn-reset" onclick="resetPassword(<?= $u['id'] ?>)">
                                        Reset Pass
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
            </div>
            <form id="editForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="form-group">
                    <label>Nama Lengkap *</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Telepon</label>
                    <input type="text" id="edit_phone" name="phone">
                </div>
                <div class="form-group">
                    <label>Departemen</label>
                    <input type="text" id="edit_department" name="department">
                </div>
                <div class="form-group">
                    <label>Jabatan</label>
                    <input type="text" id="edit_position" name="position">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="edit_role" name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="pengurus">Pengurus Kendaraan</option>
                        <option value="hsse">Petugas HSSE</option>
                        <option value="manager_hsse">Manager</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Batal</button>
                    <button type="submit" class="btn-confirm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
            </div>
            <form id="resetForm">
                <input type="hidden" id="reset_user_id" name="user_id">
                <div class="form-group">
                    <label>Password Baru *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeResetModal()">Batal</button>
                    <button type="submit" class="btn-confirm">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edit User
        async function editUser(userId) {
            try {
                const response = await fetch(`get-user.php?id=${userId}`);
                const result = await response.json();
                
                if (result.success) {
                    const user = result.data;
                    document.getElementById('edit_user_id').value = user.id;
                    document.getElementById('edit_full_name').value = user.full_name;
                    document.getElementById('edit_email').value = user.email;
                    document.getElementById('edit_phone').value = user.phone || '';
                    document.getElementById('edit_department').value = user.department || '';
                    document.getElementById('edit_position').value = user.position || '';
                    document.getElementById('edit_role').value = user.role;
                    
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert('Gagal memuat data user');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat data');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('process-edit-user.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Data user berhasil diupdate!');
                    location.reload();
                } else {
                    alert(result.message || 'Gagal update data user');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            }
        });

        // Toggle User Status
        async function toggleUserStatus(userId, newStatus) {
            const action = newStatus === 'active' ? 'mengaktifkan' : 'menonaktifkan';
            
            if (!confirm(`Yakin ingin ${action} user ini?`)) return;
            
            try {
                const response = await fetch('process-toggle-user.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({user_id: userId, status: newStatus})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`User berhasil di${action}!`);
                    location.reload();
                } else {
                    alert(result.message || 'Gagal mengubah status user');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            }
        }

        // Reset Password
        function resetPassword(userId) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('resetModal').style.display = 'block';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        document.getElementById('resetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                alert('Password dan konfirmasi password tidak cocok!');
                return;
            }
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('process-reset-password.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Password berhasil direset!');
                    closeResetModal();
                } else {
                    alert(result.message || 'Gagal reset password');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
