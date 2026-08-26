<?php
/**
 * Export Data to Excel
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireLogin();

require_once '../config/config.php';

$user = getCurrentUser();

try {
    $db = Database::getInstance()->getConnection();
    
    // Get filter parameters
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $dateFrom = isset($_GET['dateFrom']) ? sanitizeInput($_GET['dateFrom']) : '';
    $dateTo = isset($_GET['dateTo']) ? sanitizeInput($_GET['dateTo']) : '';
    $jenisKendaraan = isset($_GET['jenis']) ? sanitizeInput($_GET['jenis']) : '';
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (f.nomor_polisi LIKE :search OR f.nama_transport LIKE :search OR f.nomor_urut LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    
    if (!empty($dateFrom)) {
        $where .= " AND f.tanggal_pemeriksaan >= :dateFrom";
        $params[':dateFrom'] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $where .= " AND f.tanggal_pemeriksaan <= :dateTo";
        $params[':dateTo'] = $dateTo;
    }
    
    if (!empty($jenisKendaraan) && ($jenisKendaraan === 'SPBU' || $jenisKendaraan === 'INDUSTRI')) {
        $where .= " AND f.jenis_kendaraan = :jenis";
        $params[':jenis'] = $jenisKendaraan;
    }
    
    // Get data
    $stmt = $db->prepare("
        SELECT 
            f.id, f.jenis_kendaraan, f.nomor_urut, f.merk_mobil,
            f.nama_transport, f.nomor_polisi, f.tanggal_terakhir,
            f.produk_kapasitas, f.tanggal_pemeriksaan, f.ekim_valid_until,
            f.status_gate, f.status_upload, f.nama_pemeriksa,
            f.tanggal_pemeriksa, f.created_at, f.updated_at, f.created_by,
            COUNT(ci.id) as total_items,
            SUM(CASE WHEN ci.is_baik = TRUE THEN 1 ELSE 0 END) as total_baik,
            SUM(CASE WHEN ci.is_tidak = TRUE THEN 1 ELSE 0 END) as total_tidak,
            ROUND((SUM(CASE WHEN ci.is_baik = TRUE THEN 1 ELSE 0 END) / COUNT(ci.id) * 100), 2) as persentase_baik
        FROM formulir_checklist f
        LEFT JOIN checklist_items ci ON f.id = ci.formulir_id
        $where 
        GROUP BY f.id
        ORDER BY f.tanggal_pemeriksaan DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Set headers for Excel download
    $filename = 'Checklist_EKIM_' . date('Y-m-d_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Log audit
    $user_name = $_SESSION['username'] ?? 'System';
    logAudit(null, 'EXPORT', $user_name, "Export data ke Excel (" . count($data) . " records)");
    
    // Output HTML table with Excel-friendly styling
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        
        th {
            background-color: #C8102E;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 10px 8px;
            border: 1px solid #000;
            vertical-align: middle;
            white-space: nowrap;
        }
        
        td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            vertical-align: middle;
        }
        
        td.center {
            text-align: center;
        }
        
        td.right {
            text-align: right;
        }
        
        .header-title {
            font-size: 16pt;
            font-weight: bold;
            color: #C8102E;
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            font-size: 12pt;
            margin-bottom: 20px;
        }
        
        .badge-spbu {
            background-color: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-industri {
            background-color: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-approved {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .progress-good {
            background-color: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        
        .progress-medium {
            background-color: #fff3cd;
            color: #856404;
            font-weight: bold;
        }
        
        .progress-bad {
            background-color: #f8d7da;
            color: #721c24;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header-title">DATA CHECKLIST E-KIM</div>
    <div class="header-subtitle">Pertamina Patra Niaga - Sistem Inspeksi Perpanjangan Kartu Ijin Masuk</div>
    <div style="margin-bottom: 10px; font-size: 10pt; color: #666;">
        Tanggal Export: <?php echo date('d/m/Y H:i:s'); ?> | 
        Total Data: <?php echo count($data); ?> records
        <?php if (!empty($jenisKendaraan)): ?>
        | Filter: <?php echo htmlspecialchars(getJenisKendaraanLabel($jenisKendaraan)); ?>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="40">No</th>
                <th width="80">Jenis</th>
                <th width="80">No. Urut</th>
                <th width="100">Nomor Polisi</th>
                <th width="150">Nama Transport</th>
                <th width="100">Merk Mobil</th>
                <th width="100">Tgl Periksa</th>
                <th width="100">EKIM Valid</th>
                <th width="80">Status</th>
                <th width="80">Progress</th>
                <th width="120">Produk/Kapasitas</th>
                <th width="100">Tgl Terakhir</th>
                <th width="80">Status Gate</th>
                <th width="100">Status Upload</th>
                <th width="120">Nama Pemeriksa</th>
                <th width="100">Tgl Pemeriksa</th>
                <th width="60">Total Item</th>
                <th width="60">Baik</th>
                <th width="60">Tidak</th>
                <th width="80">% Baik</th>
                <th width="100">Dibuat Oleh</th>
                <th width="150">Dibuat Pada</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($data as $row): 
                $jenis = $row['jenis_kendaraan'] ?? 'SPBU';
                $persentase = (float)($row['persentase_baik'] ?? 0);
                
                // Determine progress class
                $progressClass = 'progress-bad';
                if ($persentase >= 90) {
                    $progressClass = 'progress-good';
                } elseif ($persentase >= 70) {
                    $progressClass = 'progress-medium';
                }
            ?>
            <tr>
                <td class="center"><?php echo $no++; ?></td>
                <td class="center">
                    <span class="badge-<?php echo strtolower($jenis); ?>">
                        <?php echo htmlspecialchars(getJenisKendaraanLabel($jenis)); ?>
                    </span>
                </td>
                <td class="center"><?php echo htmlspecialchars($row['nomor_urut'] ?? '-'); ?></td>
                <td class="center"><strong><?php echo htmlspecialchars($row['nomor_polisi'] ?? '-'); ?></strong></td>
                <td><?php echo htmlspecialchars($row['nama_transport'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['merk_mobil'] ?? '-'); ?></td>
                <td class="center"><?php echo $row['tanggal_pemeriksaan'] ? date('d/m/Y', strtotime($row['tanggal_pemeriksaan'])) : '-'; ?></td>
                <td class="center"><?php echo $row['ekim_valid_until'] ? date('d/m/Y', strtotime($row['ekim_valid_until'])) : '-'; ?></td>
                <td class="center">
                    <?php if ($row['status_gate'] === 'Approved'): ?>
                        <span class="status-approved">APPROVED</span>
                    <?php else: ?>
                        <span class="status-pending">PENDING</span>
                    <?php endif; ?>
                </td>
                <td class="center <?php echo $progressClass; ?>">
                    <?php echo number_format($persentase, 1); ?>%
                </td>
                <td><?php echo htmlspecialchars($row['produk_kapasitas'] ?? '-'); ?></td>
                <td class="center"><?php echo $row['tanggal_terakhir'] ? date('d/m/Y', strtotime($row['tanggal_terakhir'])) : '-'; ?></td>
                <td class="center"><?php echo htmlspecialchars($row['status_gate'] ?? '-'); ?></td>
                <td class="center"><?php echo htmlspecialchars($row['status_upload'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($row['nama_pemeriksa'] ?? '-'); ?></td>
                <td class="center"><?php echo $row['tanggal_pemeriksa'] ? date('d/m/Y', strtotime($row['tanggal_pemeriksa'])) : '-'; ?></td>
                <td class="center"><?php echo htmlspecialchars($row['total_items'] ?? '0'); ?></td>
                <td class="center"><?php echo htmlspecialchars($row['total_baik'] ?? '0'); ?></td>
                <td class="center"><?php echo htmlspecialchars($row['total_tidak'] ?? '0'); ?></td>
                <td class="center"><strong><?php echo number_format($persentase, 1); ?>%</strong></td>
                <td><?php echo htmlspecialchars($row['created_by'] ?? '-'); ?></td>
                <td class="center"><?php echo $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; font-size: 9pt; color: #666;">
        <p><strong>Keterangan:</strong></p>
        <ul>
            <li>Progress ≥90% = Baik (Hijau)</li>
            <li>Progress 70-89% = Sedang (Kuning)</li>
            <li>Progress <70% = Perlu Perhatian (Merah)</li>
        </ul>
    </div>
</body>
</html>
    <?php
    
    exit;
    
} catch(Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    die('Gagal export data: ' . $e->getMessage());
}
?>
