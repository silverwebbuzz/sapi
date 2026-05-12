<?php
/**
 * ONE-OFF MIGRATION: adds packing slip columns to every existing
 * invoices_<shop> table.
 *
 * USAGE:
 *   1. Upload this file to the project root.
 *   2. Visit https://sapi.silverwebbuzz.com/migrate-packing-slip.php?key=<MIGRATION_KEY>
 *      (set MIGRATION_KEY below — change it before deploying)
 *   3. Review the per-table report.
 *   4. DELETE THIS FILE FROM THE SERVER once done.
 *
 * Safe to re-run: each ALTER is wrapped to detect "column already exists"
 * and is reported as "already present" rather than an error.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// ---- security: simple shared-key gate so the URL isn't just open ----
const MIGRATION_KEY = 'packing-slip-2026-05-12';
if (!isset($_GET['key']) || !hash_equals(MIGRATION_KEY, $_GET['key'])) {
    http_response_code(403);
    die('Forbidden. Append ?key=<MIGRATION_KEY> matching the constant in this file.');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Packing slip column migration ===\n\n";

$conn = DB::getInstance();

// Find every invoices_* table.
$tables = [];
$res = $conn->query("SHOW TABLES LIKE 'invoices\\_%'");
if (!$res) {
    die("Could not list tables: " . $conn->error);
}
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}
$res->close();

if (empty($tables)) {
    echo "No invoices_* tables found. Nothing to do.\n";
    exit;
}

echo "Found " . count($tables) . " invoices_* tables.\n\n";

$summary = ['migrated' => 0, 'already' => 0, 'failed' => 0];

foreach ($tables as $table) {
    echo "→ $table … ";

    // Quote backtick-safe (table name comes from SHOW TABLES, already safe but be explicit).
    $safe = str_replace('`', '', $table);
    $tableQ = "`$safe`";

    // Check existing columns so we don't fail on re-run.
    $cols = [];
    $colsRes = $conn->query("SHOW COLUMNS FROM $tableQ");
    if ($colsRes) {
        while ($c = $colsRes->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
        $colsRes->close();
    }

    $alters = [];
    if (!isset($cols['packing_slip_pdf'])) {
        $alters[] = "ADD COLUMN `packing_slip_pdf` LONGTEXT NULL";
    }
    if (!isset($cols['packing_slip_status'])) {
        $alters[] = "ADD COLUMN `packing_slip_status` ENUM('pending','generated') NOT NULL DEFAULT 'pending'";
    }

    if (empty($alters)) {
        echo "already present, skipped.\n";
        $summary['already']++;
        continue;
    }

    $sql = "ALTER TABLE $tableQ " . implode(', ', $alters);
    if ($conn->query($sql) === true) {
        echo "migrated (" . count($alters) . " column" . (count($alters) === 1 ? '' : 's') . ").\n";
        $summary['migrated']++;
    } else {
        echo "FAILED: " . $conn->error . "\n";
        $summary['failed']++;
    }
}

echo "\n=== Summary ===\n";
echo "Migrated:        {$summary['migrated']}\n";
echo "Already present: {$summary['already']}\n";
echo "Failed:          {$summary['failed']}\n";

if ($summary['failed'] === 0) {
    echo "\nAll good. DELETE THIS FILE FROM THE SERVER NOW.\n";
} else {
    echo "\nSome tables failed. Inspect the errors above and re-run after fixing.\n";
}
