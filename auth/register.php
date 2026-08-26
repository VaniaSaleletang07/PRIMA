<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - PRIMA</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --pertamina-red: #c8102e;
            --pertamina-red-dark: #8f0c21;
            --navy: #0d1f35;
            --navy-light: #16324f;
        }

        html, body {
            min-height: 100%;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background:
                linear-gradient(160deg, rgba(6, 14, 25, 0.88) 0%, rgba(6, 14, 25, 0.78) 45%, rgba(90, 10, 24, 0.62) 100%),
                url('foto/bgpertamina.png') center 30% / cover no-repeat fixed;
            background-color: var(--navy);
            min-height: 100vh;
            display: flex;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* Subtle extra vignette in the corners for a polished corporate hero feel */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(ellipse at center, transparent 45%, rgba(4, 9, 16, 0.55) 100%);
        }

        .register-container {
            position: relative;
            z-index: 1;
            margin: auto;
            background: transparent;
            border-radius: 18px;
            box-shadow: 0 40px 90px rgba(0, 0, 0, 0.45), 0 4px 24px rgba(0, 0, 0, 0.18);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            display: flex;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        /* Thin tricolor accent strip identifying the corporate brand */
        .register-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--navy) 0%, var(--navy) 72%, var(--pertamina-red) 100%);
            z-index: 2;
        }

        /* ============ LEFT / CORPORATE BRAND PANEL ============ */
        .register-left {
            flex: 0 0 36%;
            position: relative;
            background: linear-gradient(160deg, rgba(13, 31, 53, 0.82) 0%, rgba(13, 31, 53, 0.74) 58%, rgba(22, 50, 79, 0.68) 82%, rgba(143, 12, 33, 0.62) 100%);
            backdrop-filter: blur(8px) saturate(1.15);
            -webkit-backdrop-filter: blur(8px) saturate(1.15);
            color: white;
            overflow: hidden;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            height: 100%;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Diagonal corporate texture overlay */
        .register-left::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background-image: repeating-linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.05) 0px,
                rgba(255, 255, 255, 0.05) 2px,
                transparent 2px,
                transparent 26px
            );
            pointer-events: none;
        }
        .register-left::after {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            right: -100px;
            bottom: -100px;
            border-radius: 50%;
            border: 1px solid rgba(200, 16, 46, 0.35);
            pointer-events: none;
            z-index: 1;
        }

        .logo-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 16px;
            align-self: center;
            margin-bottom: 22px;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(6px);
        }

        .logo-box {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .logo-box:hover {
            transform: translateY(-4px) scale(1.04);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.32);
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-divider {
            width: 1px;
            height: 38px;
            background: rgba(255, 255, 255, 0.28);
            flex-shrink: 0;
        }

        .register-left h1 {
            position: relative;
            font-size: 25px;
            margin-bottom: 14px;
            font-weight: 800;
            letter-spacing: -0.3px;
            line-height: 1.25;
            text-align: center;
        }

        .register-left p.brand-desc {
            position: relative;
            font-size: 13.5px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.88);
            text-align: center;
        }

        .brand-footer {
            position: relative;
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.62);
            padding-top: 22px;
            margin-top: 26px;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
        }

        /* ============ RIGHT / FORM PANEL ============ */
        .register-right {
            flex: 1;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(22px) saturate(1.2);
            -webkit-backdrop-filter: blur(22px) saturate(1.2);
            padding: 44px 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-header-text {
            margin-bottom: 26px;
        }

        .register-header-text h2 {
            color: #ffffff;
            text-shadow: 0 2px 14px rgba(0, 0, 0, 0.45);
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }

        .register-header-text p {
            color: #cdd7e3;
            font-size: 13.5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            color: #cdd7e3;
            font-weight: 700;
            margin-bottom: 7px;
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .required {
            color: #ff6b7d;
            text-transform: none;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #dde3ec;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
            background: #f8fafc;
            color: #1a2332;
        }

        .form-textarea {
            min-height: 90px;
            resize: vertical;
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: #a7b2c0;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--navy);
            background: white;
            box-shadow: 0 0 0 3px rgba(13, 31, 53, 0.1);
        }

        .form-hint {
            font-size: 11.5px;
            color: #b8c2d1;
            margin-top: 5px;
        }

        .form-hint strong {
            color: #dde3ec;
        }

        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(120deg, var(--navy) 0%, var(--navy-light) 55%, var(--pertamina-red) 145%);
            background-size: 170% 170%;
            background-position: 0% 50%;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 6px;
        }

        .btn-register:hover {
            background-position: 100% 50%;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(13, 31, 53, 0.35);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13.5px;
            display: none;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .login-link {
            text-align: center;
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
        }

        .login-link p {
            color: #cdd7e3;
            font-size: 13.5px;
        }

        .login-link a {
            color: #ff8a9c;
            text-decoration: none;
            font-weight: 700;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* ========================================
           RESPONSIVE MOBILE DESIGN
           ======================================== */

        @media (max-width: 968px) {
            .register-container {
                margin: 20px;
                border-radius: 14px;
            }

            .brand-content, .register-right {
                padding: 36px 30px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .register-container {
                flex-direction: column;
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }

            .register-left {
                flex: 0 0 auto;
            }

            .brand-content {
                padding: 30px 24px;
            }

            .register-left h1 {
                font-size: 21px;
            }

            .register-right {
                padding: 30px 24px;
                max-height: none;
                overflow-y: visible;
            }

            .register-header-text h2 {
                font-size: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-label {
                font-size: 12px;
            }

            .form-input, .form-textarea {
                font-size: 14px;
                padding: 11px 13px;
            }

            .btn-register {
                padding: 14px;
                font-size: 15px;
            }

            .form-hint {
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .register-left h1 {
                font-size: 19px;
            }

            .logo-box {
                width: 48px;
                height: 48px;
            }

            .register-header-text h2 {
                font-size: 18px;
            }

            .form-input, .form-textarea {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <div class="brand-content">

            <h1>Daftar Akun<br>PRIMA</h1>
            <p class="brand-desc">Ajukan pendaftaran akun untuk mengakses portal manajemen checklist inspeksi perpanjangan Kartu Izin Masuk (KIM) mobil tangki BBM PT Pertamina Patra Niaga. Akun akan aktif setelah disetujui admin.</p>

            <div class="brand-footer">
                &copy; 2026 PT Pertamina Patra Niaga &mdash; Health, Safety, Security &amp; Environment
            </div>
            </div>
        </div>

        <div class="register-right">
            <div class="register-header-text">
                <h2>Daftar Akun Baru</h2>
                <p>Isi formulir di bawah untuk mengajukan pendaftaran akun</p>
            </div>

            <div id="alertBox" class="alert"></div>

            <form id="registerForm" method="POST" action="../auth/process-register.php">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="username">
                            Username <span class="required">*</span>
                        </label>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Contoh: john_doe" required minlength="4" maxlength="50">
                        <div class="form-hint">Minimal 4 karakter, tanpa spasi</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">
                            Email <span class="required">*</span>
                        </label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="email@pertamina.com" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">
                            Password <span class="required">*</span>
                        </label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Minimal 6 karakter" required minlength="6">
                        <div class="form-hint">Minimal 6 karakter</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">
                            Konfirmasi Password <span class="required">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Ulangi password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">
                        Nama Lengkap <span class="required">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name" class="form-input" 
                           placeholder="Contoh: John Doe" required maxlength="100">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="phone">
                            Nomor Telepon <span class="required">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="08123456789" required maxlength="20">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="department">
                            Departemen <span class="required">*</span>
                        </label>
                        <input type="text" id="department" name="department" class="form-input" 
                               placeholder="Contoh: Operations" required maxlength="100">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="position">
                        Jabatan <span class="required">*</span>
                    </label>
                    <input type="text" id="position" name="position" class="form-input" 
                           placeholder="Contoh: Operator" required maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label" for="requested_role">
                        Daftar Sebagai <span class="required">*</span>
                    </label>
                    <select id="requested_role" name="requested_role" class="form-input" required>
                        <option value="user">User — Staff Internal Pertamina</option>
                        <option value="manager_hsse">Manager — Approval Akhir Checklist</option>
                        <option value="pengurus">Pengurus Mobil Tangki — Kontraktor / Transporter</option>
                    </select>
                    <div class="form-hint">Pilih <strong>Manager</strong> untuk akun pemberi approval akhir, atau <strong>Pengurus Mobil Tangki</strong> untuk kontraktor/pengelola armada.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reason">
                        Alasan Pendaftaran <span class="required">*</span>
                    </label>
                    <textarea id="reason" name="reason" class="form-textarea" 
                              placeholder="Jelaskan mengapa Anda membutuhkan akses ke sistem ini..." 
                              required maxlength="500"></textarea>
                    <div class="form-hint">Maksimal 500 karakter</div>
                </div>

                <button type="submit" class="btn-register">Ajukan Pendaftaran</button>
            </form>

            <div class="login-link">
                <p>Sudah punya akun? <a href="../auth/login.php">Login di sini</a></p>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('registerForm');
        const alertBox = document.getElementById('alertBox');

        // Check for error messages
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            const error = urlParams.get('error');
            let message = 'Terjadi kesalahan. Silakan coba lagi.';

            switch(error) {
                case 'exists':
                    message = 'Username atau email sudah terdaftar!';
                    break;
                case 'pending':
                    message = 'Pendaftaran Anda sedang menunggu persetujuan admin. Silakan tunggu.';
                    break;
                case 'password':
                    message = 'Password tidak cocok!';
                    break;
                case 'invalid':
                    message = 'Data yang Anda masukkan tidak valid!';
                    break;
            }

            alertBox.textContent = message;
            alertBox.className = 'alert alert-error';
            alertBox.style.display = 'block';
        }

        // Form validation
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;

            // Check password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alertBox.textContent = 'Password dan konfirmasi password tidak cocok!';
                alertBox.className = 'alert alert-error';
                alertBox.style.display = 'block';
                document.getElementById('confirm_password').focus();
                return;
            }

            // Check username format
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                e.preventDefault();
                alertBox.textContent = 'Username hanya boleh mengandung huruf, angka, dan underscore!';
                alertBox.className = 'alert alert-error';
                alertBox.style.display = 'block';
                document.getElementById('username').focus();
                return;
            }
        });

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#c00';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
    </script>
</body>
</html>
