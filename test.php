<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Koneksi Database - Checklist E-KIM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #C8102E;
            border-bottom: 3px solid #C8102E;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #C8102E;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background: #007A3D;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn:hover {
            background: #005A2D;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔧 Test Koneksi Database</h1>
        <p>PRIMA (Pertamina Checklist Mobil Tangki)</p>
        
        <?php
        require_once 'config.php';
        
        echo '<div class="status info">Testing koneksi ke database...</div>';
        
        try {
            $db = Database::getInstance()->getConnection();
            
            echo '<div class="status success">✅ Koneksi Database BERHASIL!</div>';
            
            // Test query
            $stmt = $db->query("SELECT VERSION() as version");
            $version = $stmt->fetch();
            
            echo "<p><strong>MySQL Version:</strong> {$version['version']}</p>";
            
            // Check tables
            echo '<h2>📊 Tables di Database:</h2>';
            $stmt = $db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo '<table>';
                echo '<tr><th>No</th><th>Nama Tabel</th><th>Jumlah Baris</th></tr>';
                
                foreach ($tables as $index => $table) {
                    $countStmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
                    $count = $countStmt->fetch()['count'];
                    echo "<tr><td>" . ($index + 1) . "</td><td>$table</td><td>$count rows</td></tr>";
                }
                
                echo '</table>';
            } else {
                echo '<div class="status error">⚠️ Tidak ada tabel! Silakan import database.sql</div>';
            }
            
            // Test data
            $stmt = $db->query("SELECT COUNT(*) as total FROM formulir_checklist");
            $total = $stmt->fetch()['total'];
            
            echo "<h2>📝 Data Summary:</h2>";
            echo "<p><strong>Total Formulir:</strong> $total</p>";
            
            if ($total > 0) {
                $stmt = $db->query("SELECT * FROM v_formulir_lengkap ORDER BY created_at DESC LIMIT 5");
                $recent = $stmt->fetchAll();
                
                echo '<h3>Data Terbaru (5 terakhir):</h3>';
                echo '<table>';
                echo '<tr><th>Nomor Polisi</th><th>Transport</th><th>Tanggal</th><th>Progress</th></tr>';
                
                foreach ($recent as $row) {
                    echo "<tr>";
                    echo "<td>{$row['nomor_polisi']}</td>";
                    echo "<td>{$row['nama_transport']}</td>";
                    echo "<td>{$row['tanggal_pemeriksaan']}</td>";
                    echo "<td>{$row['persentase_baik']}%</td>";
                    echo "</tr>";
                }
                
                echo '</table>';
            }
            
            echo '<div class="status success">';
            echo '<h3>✅ Sistem Siap Digunakan!</h3>';
            echo '<a href="index.html" class="btn">📝 Form Input</a>';
            echo '<a href="list.php" class="btn">📋 Lihat Data</a>';
            echo '</div>';
            
        } catch(Exception $e) {
            echo '<div class="status error">';
            echo '<h3>❌ Koneksi Database GAGAL!</h3>';
            echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
            echo '<h4>Langkah Troubleshooting:</h4>';
            echo '<ol>';
            echo '<li>Pastikan MySQL di XAMPP sudah running</li>';
            echo '<li>Cek konfigurasi database di <code>config.php</code></li>';
            echo '<li>Buat database <code>checklist_ekim</code> di phpMyAdmin</li>';
            echo '<li>Import file <code>database.sql</code></li>';
            echo '</ol>';
            echo '</div>';
        }
        ?>
        
        <hr>
        <p style="text-align: center; color: #666; margin-top: 30px;">
            <small>© 2026 Pertamina Patra Niaga | Checklist E-KIM System v1.0</small>
        </p>
    </div>
</body>
</html>
