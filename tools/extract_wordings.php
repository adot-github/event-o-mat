<?php
/**
 * extract_wordings.php
 *
 * Scans every PHP file under ../public for wording placeholders in the form:
 *
 *   '$Label text here£'   (single-quoted PHP string)
 *   $Label text here£     (bare text in HTML, outside PHP tags)
 *
 * For each placeholder found the tool:
 *   1. Converts the inner text to a snake_case key (German umlauts included).
 *   2. Looks up wp_evtmgr_wordings WHERE str_var_string = <key>.
 *      - Found    → uses the existing str_var_name as the array key.
 *      - Not found → inserts a new record and uses str_var_name as the key.
 *   3. Replaces the placeholder in the PHP file:
 *      - Quoted form '$text£' → $wordings['key'] ?? ''
 *      - Bare HTML form $text£ → <?php echo $wordings['key'] ?? ''; ?>
 *
 * Skipped:  ?? '$text£'  (already-converted fallback strings)
 *
 * Usage (browser):
 *   .../tools/extract_wordings.php              -- dry run
 *   .../tools/extract_wordings.php?action=apply -- apply DB inserts + file rewrites
 */

declare(strict_types=1);

define('APPLY',       isset($_GET['action']) && $_GET['action'] === 'apply');
define('SCAN_DIR',    dirname(__DIR__) . '/public');
define('MAX_VARNAME', 150);
define('MAX_TREE',     80);

// ── Bootstrap WordPress ───────────────────────────────────────────────────────

$wp_load = dirname(__FILE__, 7) . '/wp-load.php';

if (!is_readable($wp_load)) {
    echo '<!DOCTYPE html><html><body class="p-4"><div class="alert alert-danger m-4">Cannot find wp-load.php at: '
        . htmlspecialchars($wp_load) . '</div></body></html>';
    exit(1);
}

ob_start();
require_once $wp_load;
ob_end_clean();

// ── helpers ───────────────────────────────────────────────────────────────────

function label_to_snake(string $text): string
{
    static $map = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        'à' => 'a',  'á' => 'a',  'â' => 'a',
        'è' => 'e',  'é' => 'e',  'ê' => 'e',
        'ì' => 'i',  'í' => 'i',  'î' => 'i',
        'ò' => 'o',  'ó' => 'o',  'ô' => 'o',
        'ù' => 'u',  'ú' => 'u',  'û' => 'u',
        'ç' => 'c',  'ñ' => 'n',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_');
}

function shorten_at_underscore(string $s, int $max): string
{
    if (mb_strlen($s) <= $max) return $s;
    $sub = mb_substr($s, 0, $max);
    $pos = mb_strrpos($sub, '_');
    return $pos !== false ? mb_substr($sub, 0, $pos) : $sub;
}

function truncate_at_space(string $text, int $max): string
{
    if (mb_strlen($text) <= $max) return $text;
    $sub = mb_substr($text, 0, $max);
    $pos = mb_strrpos($sub, ' ');
    return $pos !== false ? mb_substr($sub, 0, $pos) : $sub;
}

// ── DB helpers ────────────────────────────────────────────────────────────────

function db_lookup(string $str_var_string): ?array
{
    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT id, str_var_name FROM wp_evtmgr_wordings WHERE str_var_string = %s LIMIT 1',
            $str_var_string
        ),
        ARRAY_A
    );
    return $row ?: null;
}

function db_insert(array $fields): int
{
    global $wpdb;
    $wpdb->insert(
        'wp_evtmgr_wordings',
        [
            'str_backup'           => $fields['str_backup'],
            'str_var_name'         => $fields['str_var_name'],
            'str_var_string'       => $fields['str_var_string'],
            'str_var_string_short' => $fields['str_var_string_short'],
            'str_text_for_tree'    => $fields['str_text_for_tree'],
            'str_text_de'          => $fields['str_text_de'],
            'dtm_date_created'     => current_time('mysql'),
            'dtm_date_updated'     => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );
    if ($wpdb->last_error) {
        throw new RuntimeException('DB insert failed: ' . $wpdb->last_error);
    }
    return (int) $wpdb->insert_id;
}

// ── HTML output helper ────────────────────────────────────────────────────────

function badge_row(string $bg, string $label, string $detail, string $inner, string $key): void
{
    printf(
        '<tr>'
        . '<td class="align-middle ps-3" style="width:90px"><span class="badge %s">%s</span></td>'
        . '<td class="align-middle text-muted font-monospace" style="width:80px;font-size:.8em">%s</td>'
        . '<td class="align-middle">%s</td>'
        . '<td class="align-middle text-primary font-monospace" style="font-size:.8em">%s</td>'
        . "</tr>\n",
        $bg,
        htmlspecialchars($label),
        htmlspecialchars($detail),
        htmlspecialchars($inner),
        htmlspecialchars($key)
    );
}

// ── wording resolver ──────────────────────────────────────────────────────────

// In-memory cache: snake_key → var_name. Populated even during dry-run so
// repeated labels show [reuse] instead of [NEW] on every occurrence.
$_wording_cache = [];

function resolve_wording(string $inner_text, array &$stats): ?string
{
    global $_wording_cache;

    $inner_text = trim($inner_text);
    if ($inner_text === '') return null;

    $snake     = label_to_snake($inner_text);
    $short_key = shorten_at_underscore($snake, MAX_VARNAME);

    if ($snake === '') return null;

    if (isset($_wording_cache[$snake])) {
        $var_name = $_wording_cache[$snake];
        badge_row('bg-secondary', 'reuse', '', $inner_text, $var_name);
        return $var_name;
    }

    $row = db_lookup($snake);

    if ($row) {
        $stats['found']++;
        $_wording_cache[$snake] = $row['str_var_name'];
        badge_row('bg-success', 'DB', 'id=' . $row['id'], $inner_text, $row['str_var_name']);
        return $row['str_var_name'];
    }

    $data = [
        'str_backup'           => $short_key,
        'str_var_name'         => $short_key,
        'str_var_string'       => $snake,
        'str_var_string_short' => $short_key,
        'str_text_for_tree'    => truncate_at_space($inner_text, MAX_TREE),
        'str_text_de'          => $inner_text,
    ];

    if (APPLY) {
        try {
            $new_id = db_insert($data);
            $stats['inserted']++;
            $_wording_cache[$snake] = $short_key;
            badge_row('bg-primary', 'INSERT', 'id=' . $new_id, $inner_text, $short_key);
        } catch (RuntimeException $e) {
            badge_row('bg-danger', 'ERROR', $e->getMessage(), $inner_text, $short_key);
        }
    } else {
        $stats['would_insert']++;
        $_wording_cache[$snake] = $short_key;
        badge_row('bg-info text-dark', 'NEW', '', $inner_text, $short_key);
    }

    return $short_key;
}

// ── page ──────────────────────────────────────────────────────────────────────

$scan_dir_rel = ltrim(
    str_replace(
        rtrim(str_replace('\\', '/', ABSPATH), '/'),
        '',
        str_replace('\\', '/', SCAN_DIR)
    ),
    '/'
);

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extract Wordings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
    </style>
</head>
<body>
<div class="container-xl py-4">

    <div class="d-flex align-items-center gap-3 mb-4">
        <h1 class="mb-0 h3">Extract Wordings</h1>
        <a href="?"
           class="btn btn-sm btn-outline-secondary <?= !APPLY ? 'active' : '' ?>">Dry Run</a>
        <a href="?action=apply"
           class="btn btn-sm btn-danger <?= APPLY ? 'active' : '' ?>"
           <?= !APPLY ? 'onclick="return confirm(\'Apply all changes to the database and source files?\')"' : '' ?>>Apply</a>
        <a href="<?= APPLY ? '?action=apply' : '?' ?>"
           class="btn btn-sm btn-outline-primary ms-auto">&#8635; Rescan</a>
    </div>

    <?php if (APPLY): ?>
    <div class="alert alert-danger mb-4">
        <strong>APPLY MODE</strong> &mdash; DB records are being inserted and source files are being rewritten.
    </div>
    <?php else: ?>
    <div class="alert alert-info mb-4">
        <strong>DRY RUN</strong> &mdash; No changes will be written. Click <strong>Apply</strong> to commit.
    </div>
    <?php endif; ?>

    <p class="text-muted small mb-4">Scanning: <code><?= htmlspecialchars($scan_dir_rel) ?></code></p>

<?php

// ── scan ──────────────────────────────────────────────────────────────────────

$stats = [
    'found'        => 0,
    'inserted'     => 0,
    'would_insert' => 0,
    'replaced'     => 0,
    'skipped'      => 0,
    'files'        => 0,
];

$RE_QUOTED = '/\'\$([^\'\£\r\n]+)£\'/u';
$RE_BARE   = '/\$([^\$£\r\n\'\"]+)£/u';

$wp_root = rtrim(str_replace('\\', '/', ABSPATH), '/');

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(SCAN_DIR, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileinfo) {
    if (strtolower($fileinfo->getExtension()) !== 'php') continue;

    $path    = $fileinfo->getPathname();
    $content = file_get_contents($path);
    $rel     = ltrim(str_replace(['\\', $wp_root], ['/', ''], $path), '/');

    if (mb_strpos($content, '£') === false) continue;

    $new_content  = $content;
    $file_changed = false;

    // Buffer row output for this file so we can wrap it in a card.
    ob_start();

    // Pass 1: quoted form  '$text£'
    preg_match_all($RE_QUOTED, $content, $q_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($q_matches as $m) {
        $full_match = $m[0][0];
        $offset     = $m[0][1];
        $inner_text = $m[1][0];

        $prefix = substr($content, max(0, $offset - 5), 5);
        if (preg_match('/\?\?\s*$/', $prefix)) {
            $stats['skipped']++;
            badge_row('bg-warning text-dark', 'SKIP', 'fallback', $inner_text, '??');
            continue;
        }

        $var_name = resolve_wording($inner_text, $stats);
        if ($var_name === null) continue;

        $replacement  = "\$wordings['{$var_name}'] ?? ''";
        $new_content  = str_replace($full_match, $replacement, $new_content);
        $file_changed = true;
        $stats['replaced']++;
    }

    // Pass 2: bare form  $text£  (still present after pass 1)
    preg_match_all($RE_BARE, $new_content, $b_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($b_matches as $m) {
        $full_match = $m[0][0];
        $inner_text = $m[1][0];

        $var_name = resolve_wording($inner_text, $stats);
        if ($var_name === null) continue;

        $replacement  = "<?php echo \$wordings['{$var_name}'] ?? ''; ?>";
        $new_content  = str_replace($full_match, $replacement, $new_content);
        $file_changed = true;
        $stats['replaced']++;
    }

    $rows_html = ob_get_clean();

    if ($file_changed && APPLY) {
        file_put_contents($path, $new_content);
        $stats['files']++;
    }

    // Render file card
    $card_border = '';
    $footer      = '';
    if ($file_changed) {
        if (APPLY) {
            $card_border = 'border-primary';
            $footer      = '<div class="card-footer text-primary small fw-semibold">&#10003; File written.</div>';
        } else {
            $card_border = 'border-info';
            $footer      = '<div class="card-footer text-info small">File would be updated (dry run).</div>';
        }
    }

    ?>
    <div class="card mb-3 <?= $card_border ?>">
        <div class="card-header">
            <span class="font-monospace" style="font-size:.9em"><?= htmlspecialchars($rel) ?></span>
        </div>
        <?php if ($rows_html): ?>
        <div class="p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:90px">Type</th>
                        <th style="width:80px">Detail</th>
                        <th>Label</th>
                        <th>Key</th>
                    </tr>
                </thead>
                <tbody><?= $rows_html ?></tbody>
            </table>
        </div>
        <?php endif; ?>
        <?= $footer ?>
    </div>
    <?php
}

// ── summary ───────────────────────────────────────────────────────────────────

$summary = [
    ['Found in DB',                                $stats['found'],        'success'],
    [APPLY ? 'Inserted' : 'Would insert',          APPLY ? $stats['inserted'] : $stats['would_insert'], APPLY ? 'primary' : 'info'],
    ['Fallbacks skipped',                          $stats['skipped'],      'warning'],
    ['Replacements',                               $stats['replaced'],     'dark'],
    ['Files changed',                              $stats['files'],        'secondary'],
];

?>

    <hr class="my-4">
    <h2 class="h5 mb-3">Summary</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($summary as [$label, $value, $color]): ?>
        <div class="col-auto">
            <div class="card text-center border-<?= $color ?>" style="min-width:130px">
                <div class="card-body py-2 px-3">
                    <div class="h2 fw-bold text-<?= $color ?> mb-0"><?= $value ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!APPLY): ?>
    <div class="alert alert-secondary">
        <strong>Dry run complete.</strong> No files or DB records have been changed.
        Click <strong>Apply</strong> above to commit all changes.
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <strong>Done.</strong>
        <?= $stats['inserted'] ?> DB record<?= $stats['inserted'] !== 1 ? 's' : '' ?> inserted,
        <?= $stats['files'] ?> file<?= $stats['files'] !== 1 ? 's' : '' ?> rewritten.
    </div>
    <?php endif; ?>

</div>
</body>
</html>
