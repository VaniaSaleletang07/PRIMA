<?php
// backup/index.php
// Halaman daftar file backup database

$backupDir = __DIR__;
$files = array_filter(scandir($backupDir), function($f) {
    return !in_array($f, ['.', '..', 'index.php', 'index.html', 'README.md']) && preg_match('/\.(sql|zip|gz|bak)$/i', $f);
});

?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Backup Database</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; color: #333; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 32px; }
        h1 { color: #007bff; font-size: 24px; margin-bottom: 18px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 10px; }
        .empty { color: #888; font-style: italic; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .file-info { font-size: 13px; color: #666; margin-left: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Backup Database</h1>
        <p>Daftar file backup database yang tersedia:</p>
        <ul>
            <?php if (count($files) === 0): ?>
                <li class="empty">Belum ada file backup database di folder ini.</li>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($file); ?>" download><?php echo htmlspecialchars($file); ?></a>
                        <span class="file-info">
                            (<?php echo number_format(filesize($backupDir . DIRECTORY_SEPARATOR . $file)/1024, 2); ?> KB,
                            <?php echo date('d-m-Y H:i', filemtime($backupDir . DIRECTORY_SEPARATOR . $file)); ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <p>Untuk membuat backup, gunakan fitur "Backup Sekarang" di halaman System Settings atau upload file backup ke folder ini.</p>
        <a href="../system-settings.php">&larr; Kembali ke System Settings</a>
    </div>
</body>
</html>
