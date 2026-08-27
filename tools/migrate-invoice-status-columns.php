#!/usr/bin/env php
<?php
/**
 * Widens the status columns on every per-shop `invoices_*` table.
 *
 * Two columns were too narrow for the values the app actually writes:
 *
 *   email_status  enum('pending','sent')  — sendemail() writes 'failed' when a
 *                 send fails. Under MySQL strict mode that UPDATE errors out,
 *                 so a failed automatic email left the row reading "Pending"
 *                 forever and nobody could tell the send had failed.
 *
 *   order_status  enum('pending','paid','failed','refunded') — Shopify's
 *                 financial_status also returns 'authorized', 'partially_paid',
 *                 'partially_refunded', 'voided' and 'expired'. Under strict
 *                 mode the whole order INSERT failed for those orders, so no
 *                 invoice row existed and the automatic invoice email never ran.
 *
 * Run once after deploying:
 *     php tools/migrate-invoice-status-columns.php            # dry run
 *     php tools/migrate-invoice-status-columns.php --apply
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

$apply = in_array('--apply', $argv, true);

$tables = DBHelper::select(
    "SELECT TABLE_NAME FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'invoices\\_%'"
);

if (!$tables) {
    echo "No invoices_* tables found.\n";
    exit(0);
}

$failures = 0;

foreach ($tables as $row) {
    $table = $row['TABLE_NAME'];
    $sql = "ALTER TABLE `$table`
        MODIFY `email_status` enum('pending','sent','failed') DEFAULT 'pending',
        MODIFY `order_status` varchar(32) DEFAULT 'pending'";

    if (!$apply) {
        echo "would migrate: $table\n";
        continue;
    }

    if (DBHelper::createTable($sql) === false) {
        echo "FAILED: $table\n";
        $failures++;
    } else {
        echo "migrated: $table\n";
    }
}

if (!$apply) {
    echo "\nDry run — re-run with --apply to make the changes.\n";
}

exit($failures > 0 ? 1 : 0);
