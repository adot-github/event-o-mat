<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WEX_MAX_VARNAME')) define('WEX_MAX_VARNAME', 150);
if (!defined('WEX_MAX_TREE'))    define('WEX_MAX_TREE',     80);

$wex_apply = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['wex_apply_action'])
    && isset($_POST['wex_nonce'])
    && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wex_nonce'])), 'wex_apply')
);

$wex_scan_dirs = [
    get_stylesheet_directory() . '/db-custom/event-registration/public',
    get_stylesheet_directory() . '/db-custom/event-registration/classes',
];

// ── helpers ───────────────────────────────────────────────────────────────────

if (!function_exists('wex_label_to_snake')) {
    function wex_label_to_snake(string $text): string
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
}

if (!function_exists('wex_shorten_at_underscore')) {
    function wex_shorten_at_underscore(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) return $s;
        $sub = mb_substr($s, 0, $max);
        $pos = mb_strrpos($sub, '_');
        return $pos !== false ? mb_substr($sub, 0, $pos) : $sub;
    }
}

if (!function_exists('wex_truncate_at_space')) {
    function wex_truncate_at_space(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) return $text;
        $sub = mb_substr($text, 0, $max);
        $pos = mb_strrpos($sub, ' ');
        return $pos !== false ? mb_substr($sub, 0, $pos) : $sub;
    }
}

// ── DB helpers ────────────────────────────────────────────────────────────────

if (!function_exists('wex_db_lookup')) {
    function wex_db_lookup(string $str_var_string): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, str_var_name FROM wp_evtmgr_wordings_default WHERE str_var_string = %s LIMIT 1',
                $str_var_string
            ),
            ARRAY_A
        );
        return $row ?: null;
    }
}

if (!function_exists('wex_db_insert')) {
    function wex_db_insert(array $fields): int
    {
        global $wpdb;
        $wpdb->insert(
            'wp_evtmgr_wordings_default',
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
}

// ── HTML output helper ────────────────────────────────────────────────────────

if (!function_exists('wex_badge_row')) {
    function wex_badge_row(string $bg, string $label, string $detail, string $inner, string $key): void
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
}

// ── wording resolver ──────────────────────────────────────────────────────────

$_wex_wording_cache = [];

if (!function_exists('wex_resolve_wording')) {
    function wex_resolve_wording(string $inner_text, array &$stats, bool $apply): ?string
    {
        global $_wex_wording_cache;

        $inner_text = trim($inner_text);
        if ($inner_text === '') return null;

        $snake     = wex_label_to_snake($inner_text);
        $short_key = wex_shorten_at_underscore($snake, WEX_MAX_VARNAME);

        if ($snake === '') return null;

        if (isset($_wex_wording_cache[$snake])) {
            $var_name = $_wex_wording_cache[$snake];
            wex_badge_row('bg-secondary', 'reuse', '', $inner_text, $var_name);
            return $var_name;
        }

        $row = wex_db_lookup($snake);

        if ($row) {
            $stats['found']++;
            $_wex_wording_cache[$snake] = $row['str_var_name'];
            wex_badge_row('bg-success', 'DB', 'id=' . $row['id'], $inner_text, $row['str_var_name']);
            return $row['str_var_name'];
        }

        $data = [
            'str_backup'           => $short_key,
            'str_var_name'         => $short_key,
            'str_var_string'       => $snake,
            'str_var_string_short' => $short_key,
            'str_text_for_tree'    => wex_truncate_at_space($inner_text, WEX_MAX_TREE),
            'str_text_de'          => $inner_text,
        ];

        if ($apply) {
            try {
                $new_id = wex_db_insert($data);
                $stats['inserted']++;
                $_wex_wording_cache[$snake] = $short_key;
                wex_badge_row('bg-primary', 'INSERT', 'id=' . $new_id, $inner_text, $short_key);
            } catch (RuntimeException $e) {
                wex_badge_row('bg-danger', 'ERROR', $e->getMessage(), $inner_text, $short_key);
            }
        } else {
            $stats['would_insert']++;
            $_wex_wording_cache[$snake] = $short_key;
            wex_badge_row('bg-info text-dark', 'NEW', '', $inner_text, $short_key);
        }

        return $short_key;
    }
}

// ── scan dir label ─────────────────────────────────────────────────────────────

$wex_wp_root_norm = rtrim(str_replace('\\', '/', ABSPATH), '/');
$wex_scan_dir_rel = implode(', ', array_map(
    fn($d) => ltrim(str_replace($wex_wp_root_norm, '', str_replace('\\', '/', $d)), '/'),
    $wex_scan_dirs
));

$wex_current_url = admin_url('admin.php?page=wordings-extract');

?>
<div class="container-xxl py-4">

    <div class="d-flex align-items-center gap-3 mb-4">
        <h1 class="mb-0 h3">Extract Wordings</h1>
        <a href="<?php echo esc_url($wex_current_url); ?>"
           class="btn btn-sm btn-outline-primary rounded-pill <?php echo !$wex_apply ? 'active' : ''; ?>">Dry Run</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Apply all changes to the database and source files?')">
            <?php wp_nonce_field('wex_apply', 'wex_nonce'); ?>
            <input type="hidden" name="wex_apply_action" value="1">
            <button type="submit"
                    class="btn btn-sm btn-danger rounded-pill <?php echo $wex_apply ? 'active' : ''; ?>">Apply</button>
        </form>
        <a href="<?php echo esc_url($wex_current_url . ($wex_apply ? '&rescan=1' : '')); ?>"
           class="btn btn-sm btn-outline-primary rounded-pill ms-auto">&#8635; Rescan</a>
    </div>

    <?php if ($wex_apply) : ?>
    <div class="alert alert-danger mb-4">
        <strong>APPLY MODE</strong> &mdash; DB records are being inserted and source files are being rewritten.
    </div>
    <?php else : ?>
    <div class="alert alert-info mb-4">
        <strong>DRY RUN</strong> &mdash; No changes will be written. Click <strong>Apply</strong> to commit.
    </div>
    <?php endif; ?>

    <p class="text-muted small mb-4">Scanning: <code><?php echo esc_html($wex_scan_dir_rel); ?></code></p>

<?php

// ── scan ──────────────────────────────────────────────────────────────────────

$wex_stats = [
    'found'        => 0,
    'inserted'     => 0,
    'would_insert' => 0,
    'replaced'     => 0,
    'skipped'      => 0,
    'files'        => 0,
];

$WEX_RE_QUOTED = '/\'\$([^\'\£\r\n]+)£\'/u';
$WEX_RE_BARE   = '/\$([^\$£\r\n\'\"]+)£/u';

$wex_wp_root = rtrim(str_replace('\\', '/', ABSPATH), '/');

$wex_iterator = new AppendIterator();
foreach ($wex_scan_dirs as $wex_scan_dir) {
    $wex_iterator->append(new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wex_scan_dir, FilesystemIterator::SKIP_DOTS)
    ));
}

foreach ($wex_iterator as $fileinfo) {
    if (strtolower($fileinfo->getExtension()) !== 'php') continue;

    $path    = $fileinfo->getPathname();
    $content = file_get_contents($path);
    $rel     = ltrim(str_replace(['\\', $wex_wp_root], ['/', ''], $path), '/');

    if (mb_strpos($content, '£') === false) continue;

    $new_content  = $content;
    $file_changed = false;

    ob_start();

    // Pass 1: quoted form  '$text£'
    preg_match_all($WEX_RE_QUOTED, $content, $q_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($q_matches as $m) {
        $full_match = $m[0][0];
        $offset     = $m[0][1];
        $inner_text = $m[1][0];

        $prefix = substr($content, max(0, $offset - 5), 5);
        if (preg_match('/\?\?\s*$/', $prefix)) {
            $wex_stats['skipped']++;
            wex_badge_row('bg-warning text-dark', 'SKIP', 'fallback', $inner_text, '??');
            continue;
        }

        $var_name = wex_resolve_wording($inner_text, $wex_stats, $wex_apply);
        if ($var_name === null) continue;

        $replacement  = "\$wordings['{$var_name}'] ?? '{$var_name}'";
        $new_content  = str_replace($full_match, $replacement, $new_content);
        $file_changed = true;
        $wex_stats['replaced']++;
    }

    // Pass 2: bare form  $text£  (still present after pass 1)
    preg_match_all($WEX_RE_BARE, $new_content, $b_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($b_matches as $m) {
        $full_match = $m[0][0];
        $inner_text = $m[1][0];

        $var_name = wex_resolve_wording($inner_text, $wex_stats, $wex_apply);
        if ($var_name === null) continue;

        $replacement  = "<?php echo \$wordings['{$var_name}'] ?? '{$var_name}'; ?>";
        $new_content  = str_replace($full_match, $replacement, $new_content);
        $file_changed = true;
        $wex_stats['replaced']++;
    }

    $rows_html = ob_get_clean();

    if ($file_changed && $wex_apply) {
        file_put_contents($path, $new_content);
        $wex_stats['files']++;
    }

    $row_border = '';
    $footer     = '';
    if ($file_changed) {
        if ($wex_apply) {
            $row_border = 'border-primary';
            $footer     = '<div class="px-3 py-2 border-top text-primary small fw-semibold">&#10003; File written.</div>';
        } else {
            $row_border = 'border-info';
            $footer     = '<div class="px-3 py-2 border-top text-info small">File would be updated (dry run).</div>';
        }
    }

    ?>
    <div class="col-12 mb-3 border rounded <?php echo esc_attr($row_border); ?>">
        <div class="px-3 py-2 border-bottom">
            <span class="font-monospace" style="font-size:.9em"><?php echo esc_html($rel); ?></span>
        </div>
        <?php if ($rows_html) : ?>
        <div class="p-0">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3" style="width:90px">Type</th>
                        <th style="width:80px">Detail</th>
                        <th>Label</th>
                        <th>Key</th>
                    </tr>
                </thead>
                <tbody><?php echo $rows_html; ?></tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php echo $footer; ?>
    </div>
    <?php
}

// ── summary ───────────────────────────────────────────────────────────────────

$wex_summary = [
    ['Found in DB',                                              $wex_stats['found'],        'success'],
    [$wex_apply ? 'Inserted' : 'Would insert', $wex_apply ? $wex_stats['inserted'] : $wex_stats['would_insert'], $wex_apply ? 'primary' : 'info'],
    ['Fallbacks skipped',                                        $wex_stats['skipped'],      'warning'],
    ['Replacements',                                             $wex_stats['replaced'],     'dark'],
    ['Files changed',                                            $wex_stats['files'],        'secondary'],
];

?>

    <hr class="my-4">
    <h2 class="h5 mb-3">Summary</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($wex_summary as [$label, $value, $color]) : ?>
        <div class="col-auto">
            <div class="card text-center border-<?php echo esc_attr($color); ?>" style="min-width:130px">
                <div class="card-body py-2 px-3">
                    <div class="h2 text-<?php echo esc_attr($color); ?> mb-0"><?php echo (int) $value; ?></div>
                    <div class="text-muted small"><?php echo esc_html($label); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$wex_apply) : ?>
    <div class="alert alert-secondary">
        <strong>Dry run complete.</strong> No files or DB records have been changed.
        Click <strong>Apply</strong> above to commit all changes.
    </div>
    <?php else : ?>
    <div class="alert alert-success">
        <strong>Done.</strong>
        <?php echo (int) $wex_stats['inserted']; ?> DB record<?php echo $wex_stats['inserted'] !== 1 ? 's' : ''; ?> inserted,
        <?php echo (int) $wex_stats['files']; ?> file<?php echo $wex_stats['files'] !== 1 ? 's' : ''; ?> rewritten.
    </div>
    <?php endif; ?>

</div>
