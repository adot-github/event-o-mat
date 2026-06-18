<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = 'wp_evtmgr_wordings_default';
$public_dir = get_stylesheet_directory() . '/db-custom/event-registration/public';

$result = null;
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wordings_scan_action'])) {
    if (!isset($_POST['wordings_scan_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wordings_scan_nonce'])), 'wordings_scan')) {
        $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } elseif (!is_dir($public_dir)) {
        $errors[] = 'Public-Verzeichnis nicht gefunden: ' . esc_html($public_dir);
    } else {
        // Collect all PHP files recursively from public/
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($public_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $php_files = array();
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $abs_path      = $file->getPathname();
                $rel_path      = ltrim(str_replace($public_dir, '', $abs_path), DIRECTORY_SEPARATOR . '/');
                $template_name = str_replace(array(DIRECTORY_SEPARATOR, '\\'), '/', pathinfo($rel_path, PATHINFO_DIRNAME) . '/' . pathinfo($rel_path, PATHINFO_FILENAME));
                $template_name = ltrim($template_name, '/.');

                $php_files[$template_name] = file_get_contents($abs_path);
            }
        }

        // Fetch all wording records
        $wordings = $wpdb->get_results(
            "SELECT id, str_var_string FROM {$table_name}",
            ARRAY_A
        );

        $details = array();

        foreach ($wordings as $wording) {
            $id         = (int) $wording['id'];
            $var_string = $wording['str_var_string'];

            if ($var_string === null || $var_string === '') {
                continue;
            }

            $search_str = "['" . $var_string . "']";

            $total_count = 0;
            $found_in    = array();

            foreach ($php_files as $template_name => $content) {
                $count = substr_count($content, $search_str);
                if ($count > 0) {
                    $total_count += $count;
                    $found_in[]   = $template_name;
                }
            }

            $templates_str = implode(',', $found_in);

            $wpdb->update(
                $table_name,
                array(
                    'int_num_of_occurences' => $total_count,
                    'str_template'          => $templates_str,
                ),
                array('id' => $id),
                array('%d', '%s'),
                array('%d')
            );

            $details[] = array(
                'id'             => $id,
                'str_var_string' => $var_string,
                'search_str'     => $search_str,
                'count'          => $total_count,
                'templates'      => $templates_str,
            );
        }

        $result = array(
            'success' => true,
            'updated' => count($details),
            'details' => $details,
        );
    }
}
?>

<div class="container-xxl py-4">
    <h1 class="h3 mb-4">Wordings: Vorkommen in Templates scannen</h1>

    <p class="text-muted mb-4">
        Durchsucht alle PHP-Dateien in <code><?php echo esc_html($public_dir); ?></code>
        nach dem Wert aus <code>str_var_string</code> und aktualisiert
        <code>int_num_of_occurences</code> und <code>str_template</code>.
    </p>

    <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($result['success'])) : ?>
        <div class="alert alert-success">
            <?php echo (int) $result['updated']; ?> Datensätze wurden aktualisiert.
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>str_var_string</th>
                        <th class="text-center">int_num_of_occurences</th>
                        <th>str_template</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['details'] as $row) : ?>
                        <tr>
                            <td><?php echo (int) $row['id']; ?></td>
                            <td><code><?php echo esc_html($row['str_var_string']); ?></code><br><small class="text-muted"><?php echo esc_html($row['search_str']); ?></small></td>
                            <td class="text-center">
                                <?php if ($row['count'] > 0) : ?>
                                    <span class="badge bg-success"><?php echo (int) $row['count']; ?></span>
                                <?php else : ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row['templates']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <?php wp_nonce_field('wordings_scan', 'wordings_scan_nonce'); ?>
            <input type="hidden" name="wordings_scan_action" value="1">
            <button type="submit" class="btn btn-primary rounded-pill">
                Scan &amp; Aktualisierung starten
            </button>
        </div>
    </form>
</div>
