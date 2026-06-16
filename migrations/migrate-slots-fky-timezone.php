<?php
/**
 * One-time migration: populate wp_evtmgr_slots.fky_timezone_id
 * from wp_evtmgr_timezones.str_slots (parent timezones only).
 *
 * Run once via WP-CLI:
 *   wp eval-file migrate-slots-fky-timezone.php
 *
 * Or include temporarily in functions.php and remove afterwards.
 *
 * The column fky_timezone_id must already exist in wp_evtmgr_slots.
 * If not, run first:
 *   ALTER TABLE wp_evtmgr_slots
 *       ADD COLUMN fky_timezone_id INT NOT NULL DEFAULT 0;
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// ── 1. Ensure column exists ───────────────────────────────────────────────────

$cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}evtmgr_slots LIKE 'fky_timezone_id'");
if (empty($cols)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}evtmgr_slots ADD COLUMN fky_timezone_id INT NOT NULL DEFAULT 0");
    echo "Column fky_timezone_id added.\n";
} else {
    echo "Column fky_timezone_id already exists.\n";
}

// ── 2. Read all parent timezones that have str_slots set ──────────────────────

$parent_timezones = $wpdb->get_results(
    "SELECT id, str_slots
     FROM {$wpdb->prefix}evtmgr_timezones
     WHERE fky_parent_timezone_id = 0
       AND str_slots IS NOT NULL
       AND TRIM(str_slots) != ''",
    ARRAY_A
);

if (empty($parent_timezones)) {
    echo "No parent timezones with str_slots found. Nothing to migrate.\n";
    return;
}

$updated = 0;
$skipped = 0;
$conflicts = array();

foreach ($parent_timezones as $tz) {
    $timezone_id = (int) $tz['id'];
    $slot_ids    = array_values(array_filter(array_map('intval', explode(',', $tz['str_slots']))));

    foreach ($slot_ids as $slot_id) {
        if ($slot_id <= 0) {
            continue;
        }

        // Check if slot already has a different timezone assigned.
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT fky_timezone_id FROM {$wpdb->prefix}evtmgr_slots WHERE id = %d",
            $slot_id
        ));

        if ($existing > 0 && $existing !== $timezone_id) {
            $conflicts[] = "Slot {$slot_id}: already has timezone {$existing}, tried to set {$timezone_id} → skipped.";
            $skipped++;
            continue;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'evtmgr_slots',
            array('fky_timezone_id' => $timezone_id),
            array('id'              => $slot_id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            $updated++;
        }
    }
}

echo "Migration complete.\n";
echo "Updated: {$updated} slot(s).\n";
echo "Skipped (conflict): {$skipped} slot(s).\n";

if (!empty($conflicts)) {
    echo "\nConflicts (review and fix manually):\n";
    foreach ($conflicts as $msg) {
        echo "  - {$msg}\n";
    }
}
