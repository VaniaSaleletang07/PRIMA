<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PRIMA (Pertamina Checklist Mobil Tangki)</title>
    <script>
    // Auto-fix absolute path issues from InfinityFree
    (function() {
      var url = window.location.href;
      if (url.indexOf('/home/vol8_1/') !== -1 || url.indexOf('/htdocs/') !== -1) {
        var match = url.match(/([^\/]+\.(?:php|html).*?)$/);
        if (match) {
          window.location.replace(window.location.origin + '/' + match[1]);
        }
      }
    })();
    </script>
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

        .login-container {
            position: relative;
            z-index: 1;
            margin: auto;
            background: transparent;
            border-radius: 18px;
            box-shadow: 0 40px 90px rgba(0, 0, 0, 0.45), 0 4px 24px rgba(0, 0, 0, 0.18);
            overflow: hidden;
            max-width: 1020px;
            width: 100%;
            display: flex;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        /* Thin tricolor accent strip identifying the corporate brand */
        .login-container::before {
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
        .login-left {
            flex: 1;
            position: relative;
            background: linear-gradient(160deg, rgba(13, 31, 53, 0.82) 0%, rgba(13, 31, 53, 0.74) 58%, rgba(22, 50, 79, 0.68) 82%, rgba(143, 12, 33, 0.62) 100%);
            backdrop-filter: blur(1px) saturate(1.15);
            -webkit-backdrop-filter: blur(8px) saturate(1.15);
            color: white;
            overflow: hidden;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            height: 100%;
            padding: 56px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Diagonal corporate texture overlay */
        .login-left::before {
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
        .login-left::after {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            right: -110px;
            bottom: -110px;
            border-radius: 50%;
            border: 1px solid rgba(200, 16, 46, 0.35);
            pointer-events: none;
            z-index: 1;
        }

        .brand-kicker {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            align-self: flex-start;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.85);
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 28px;
        }
        .brand-kicker::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.25);
        }

        .logo-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 16px;
            align-self: center;
            margin-bottom: 14px;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(6px);
        }

        .logo-box {
            width: 64px;
            height: 64px;
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
            height: 40px;
            background: rgba(255, 255, 255, 0.28);
            flex-shrink: 0;
        }

        .logo-plus {
            font-size: 15px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.55);
            flex-shrink: 0;
        }

        .brand-lockup-caption {
            position: relative;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 26px;
        }

        .login-left h1 {
            position: relative;
            font-size: 30px;
            margin-bottom: 14px;
            font-weight: 800;
            letter-spacing: -0.3px;
            line-height: 1.25;
        }

        .login-left p.brand-desc {
            position: relative;
            font-size: 14.5px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.88);
            max-width: 420px;
            margin-bottom: 34px;
        }

        .brand-features {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 36px;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.92);
        }

        .brand-feature .feat-icon {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-footer {
            position: relative;
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.62);
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
        }

        /* ============ RIGHT / FORM PANEL ============ */
        .login-right {
            flex: 1;
            background: rgba(255, 255, 255, 0);
            backdrop-filter: blur(22px) saturate(1.2);
            -webkit-backdrop-filter: blur(22px) saturate(1.2);
            padding: 56px 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 34px;
        }

        .login-header h2 {
            color: #ffffff;
            text-shadow: 0 2px 14px rgba(0, 0, 0, 0.45);
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }

        .login-header p {
            color: #90a0b3;
            font-size: 13.5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #90a0b3;
            font-weight: 700;
            margin-bottom: 7px;
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .form-input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #dde3ec;
            border-radius: 8px;
            font-size: 14.5px;
            transition: all 0.2s;
            font-family: inherit;
            background: #f8fafc;
            color: #1a2332;
        }

        .form-input::placeholder {
            color: #a7b2c0;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--navy);
            background: white;
            box-shadow: 0 0 0 3px rgba(13, 31, 53, 0.1);
        }

        .btn-login {
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

        .btn-login:hover {
            background-position: 100% 50%;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(13, 31, 53, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 13.5px;
            display: none;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .security-note {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 20px;
            font-size: 11.5px;
            color: #90a0b3;
        }

        .register-link {
            text-align: center;
            margin-top: 26px;
            padding-top: 24px;
            border-top: 1px solid #edf1f6;
        }

        .register-link p {
            color: #90a0b3;
            font-size: 13.5px;
        }

        .register-link a {
            color: var(--pertamina-red);
            text-decoration: none;
            font-weight: 700;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* ========================================
           RESPONSIVE MOBILE DESIGN
           ======================================== */

        @media (max-width: 968px) {
            .login-container {
                margin: 20px;
                border-radius: 14px;
            }

            .brand-content, .login-right {
                padding: 40px 34px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .login-container {
                flex-direction: column;
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }

            .login-left {
                min-height: auto;
            }

            .brand-content {
                padding: 34px 24px;
            }

            .login-left h1 {
                font-size: 22px;
            }

            .login-left p.brand-desc {
                font-size: 13px;
            }

            .brand-features {
                display: none;
            }

            .logo-container {
                padding: 10px 16px;
                gap: 12px;
            }

            .logo-box {
                width: 52px;
                height: 52px;
            }

            .login-right {
                padding: 34px 24px;
            }

            .login-header h2 {
                font-size: 21px;
            }

            .login-header p {
                font-size: 13px;
            }

            .form-input {
                font-size: 14px;
                padding: 12px 14px;
            }

            .btn-login {
                padding: 14px;
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            .login-left h1 {
                font-size: 19px;
            }

            .logo-box {
                width: 46px;
                height: 46px;
            }

            .login-header h2 {
                font-size: 19px;
            }

            .form-label {
                font-size: 12px;
            }

            .form-input {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="brand-content">

            <div class="logo-container">
                <div class="logo-box">
                    <img src="foto/Logo HSSE 2022.png" alt="HSSE 2022">
                </div>
                <span class="logo-divider"></span>
                <div class="logo-box">
                    <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="Pertamina">
                </div>
            </div>

            <h1>PRIMA<br>Pertamina Checklist Mobil Tangki</h1>
            <p class="brand-desc">Portal manajemen checklist inspeksi perpanjangan Kartu Izin Masuk (KIM) untuk pemeriksaan mobil tangki BBM PT Pertamina Patra Niaga.</p>

            <div class="brand-footer">
                &copy; 2026 PT Pertamina Patra Niaga &mdash; Health, Safety, Security &amp; Environment
            </div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Selamat Datang Kembali</h2>
                <p>Masuk ke akun Anda untuk melanjutkan</p>
            </div>

            <div id="alertBox" class="alert"></div>

            <form id="loginForm" method="POST" action="../auth/process-login.php">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-input" 
                           placeholder="Masukkan username" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="Masukkan password" required>
                </div>

                <button type="submit" class="btn-login">Masuk</button>
            </form>

            <div class="security-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Koneksi Anda aman &amp; data dienkripsi
            </div>

            <div class="register-link">
                <p>Belum punya akun? <a href="../auth/register.php">Daftar di sini</a></p>
            </div>
        </div>
    </div>

    <script>
        // Check for error/success messages in URL
        const urlParams = new URLSearchParams(window.location.search);
        const alertBox = document.getElementById('alertBox');

        if (urlParams.has('error')) {
            const error = urlParams.get('error');
            let message = 'Terjadi kesalahan. Silakan coba lagi.';

            switch(error) {
                case 'invalid':
                    message = 'Username atau password salah!';
                    break;
                case 'pending':
                    message = 'Akun Anda sedang menunggu persetujuan admin. Silakan bersabar.';
                    break;
                case 'rejected':
                    message = 'Pendaftaran Anda ditolak oleh admin. Silakan daftar ulang atau hubungi admin.';
                    break;
                case 'inactive':
                    message = 'Akun Anda belum diaktifkan. Silakan hubungi admin.';
                    break;
                case 'timeout':
                    message = 'Sesi Anda telah berakhir. Silakan login kembali.';
                    break;
                case 'locked':
                    message = 'Akun terkunci karena terlalu banyak percobaan login. Silakan coba lagi nanti.';
                    break;
            }

            alertBox.textContent = message;
            alertBox.className = 'alert alert-error';
            alertBox.style.display = 'block';
        }

        if (urlParams.has('success')) {
            const success = urlParams.get('success');
            let message = '';

            switch(success) {
                case 'registered':
                    message = 'Pendaftaran berhasil! Silakan tunggu approval dari admin.';
                    break;
                case 'logout':
                    message = 'Anda telah berhasil logout.';
                    break;
            }

            if (message) {
                alertBox.textContent = message;
                alertBox.className = 'alert alert-success';
                alertBox.style.display = 'block';
            }
        }

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alertBox.textContent = 'Username dan password harus diisi!';
                alertBox.className = 'alert alert-error';
                alertBox.style.display = 'block';
            }
        });
    </script>
</body>
</html>
