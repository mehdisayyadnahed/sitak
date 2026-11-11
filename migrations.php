<?php
/**
 * اجرای خودکار migrationهای شماره‌دار.
 * هر فایل فقط یک‌بار اجرا می‌شود و اجرای مجدد سایت امن است.
 */
$conn->query("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(120) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

$lock = $conn->query("SELECT GET_LOCK('sitak_schema_migrations', 15)");
if (!$lock || (int)$lock->fetch_row()[0] !== 1) die('Database migration is locked.');
try {
    $migration_dir = __DIR__ . '/migrations';
    $files = glob($migration_dir . '/*.php') ?: [];
    sort($files, SORT_STRING);
    $applied = [];
    $am = $conn->query("SELECT version FROM schema_migrations");
    if ($am) {
        while ($row = $am->fetch_row()) $applied[] = (string)$row[0];
    }
    foreach ($files as $file) {
        $version = basename($file, '.php');
        if (in_array($version, $applied, true)) continue;
        $migration = require $file;
        if (!is_callable($migration)) throw new RuntimeException("Invalid migration: " . $version);
        $migration($conn);
        $record = $conn->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $record->bind_param("s", $version);
        if (!$record->execute()) throw new RuntimeException($record->error);
    }
} catch (Throwable $e) {
    error_log('[SiTak migration] ' . $e->getMessage());
    die('Database update failed. Please restore the backup and contact support.');
} finally {
    $conn->query("DO RELEASE_LOCK('sitak_schema_migrations')");
}
