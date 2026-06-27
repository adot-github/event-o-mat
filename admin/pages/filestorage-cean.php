<?php

$wp_load = dirname(__FILE__, 8) . '/wp-load.php';

if (!file_exists($wp_load)) {
    die('wp-load.php not found: ' . htmlspecialchars($wp_load, ENT_QUOTES, 'UTF-8'));
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('edit_posts')) {
    wp_die('Keine Berechtigung.');
}

require_once dirname(__DIR__) . '/../classes/class-event-registration.php';
require_once dirname(__DIR__) . '/../classes/class-pdf-creation.php';

$pdf_creator        = new Event_Registration_Pdf_Creation(__DIR__);
$event_registration = new Event_Registration_Context();
$event_uid          = $event_registration->get_cookie_event_uid(true);

$storage_base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'file-storage';

/* --- Collect files per type-folder ----------------------------------- */

$type_folders = [];

if (is_dir($storage_base)) {
    foreach (scandir($storage_base) as $type) {
        if ($type === '.' || $type === '..') {
            continue;
        }

        $type_path  = $storage_base . DIRECTORY_SEPARATOR . $type;
        $event_path = $type_path . DIRECTORY_SEPARATOR . $event_uid;

        if (!is_dir($type_path) || !is_dir($event_path)) {
            continue;
        }

        $files = [];

        foreach (scandir($event_path) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (is_file($event_path . DIRECTORY_SEPARATOR . $file)) {
                $files[] = $file;
            }
        }

        $type_folders[$type] = [
            'path'  => $event_path,
            'files' => $files,
        ];
    }
}

$total_files = array_sum(array_map(static fn($t) => count($t['files']), $type_folders));

/* --- Handle deletion -------------------------------------------------- */

$do_delete = $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['confirm_delete'])
    && $_POST['confirm_delete'] === '1';

$deleted = [];
$errors  = [];

if ($do_delete) {
    check_admin_referer('filestorage_clean_' . $event_uid);

    foreach ($type_folders as $type => $data) {
        foreach ($data['files'] as $file) {
            $full_path = $data['path'] . DIRECTORY_SEPARATOR . $file;

            if (@unlink($full_path)) {
                $deleted[] = $type . '/' . $event_uid . '/' . $file;
            } else {
                $errors[] = $type . '/' . $event_uid . '/' . $file;
            }
        }
    }

    /* Refresh file list after deletion */
    foreach (array_keys($type_folders) as $type) {
        $type_folders[$type]['files'] = array_values(array_filter(
            $type_folders[$type]['files'],
            static fn($f) => in_array($type . '/' . $event_uid . '/' . $f, $errors, true)
        ));
    }
}

/* --- Output ----------------------------------------------------------- */

$pdf_creator->show_page_header('Dateiablage bereinigen');

?>
<h1 class="mb-3">Dateiablage bereinigen</h1>

<p><strong>Event:</strong> <?php echo esc_html($event_uid); ?></p>

<?php if ($do_delete) : ?>

    <?php if (!empty($deleted)) : ?>
        <div class="alert alert-success mb-3">
            <strong><?php echo count($deleted); ?> Datei(en) gelöscht.</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($deleted as $f) : ?>
                    <li><?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger mb-3">
            <strong>Folgende Dateien konnten nicht gelöscht werden:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $f) : ?>
                    <li><?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif (empty($errors)) : ?>
        <div class="alert alert-info mb-3">
            Die Ordner bleiben bestehen und können erneut befüllt werden.
        </div>
    <?php endif; ?>

<?php elseif (empty($type_folders) || $total_files === 0) : ?>

    <div class="alert alert-info mb-3">
        Keine Dateien für Event <strong><?php echo esc_html($event_uid); ?></strong> gefunden.
    </div>

<?php else : ?>

    <p>
        Folgende <strong><?php echo esc_html($total_files); ?> Datei(en)</strong>
        in <strong><?php echo count($type_folders); ?> Unterordner(n)</strong> werden gelöscht.
        Die Ordner selbst bleiben bestehen.
    </p>

    <table class="table table-bordered table-sm mb-4" style="max-width:900px;">
        <thead class="table-light">
            <tr>
                <th>Unterordner</th>
                <th>Dateien</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($type_folders as $type => $data) : ?>
                <tr>
                    <td style="white-space:nowrap;vertical-align:top;">
                        <strong><?php echo esc_html($type . '/' . $event_uid); ?></strong>
                    </td>
                    <td>
                        <?php if (empty($data['files'])) : ?>
                            <em style="color:#888;">Keine Dateien</em>
                        <?php else : ?>
                            <?php foreach ($data['files'] as $file) : ?>
                                <?php echo esc_html($file); ?><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <?php wp_nonce_field('filestorage_clean_' . $event_uid); ?>
        <input type="hidden" name="confirm_delete" value="1">
        <button type="submit"
                class="btn btn-danger"
                onclick="return confirm('Alle <?php echo esc_js($total_files); ?> Datei(en) für «<?php echo esc_js($event_uid); ?>» wirklich löschen?')">
            Alle <?php echo esc_html($total_files); ?> Datei(en) löschen
        </button>
    </form>

<?php endif; ?>

<?php $pdf_creator->show_page_footer(); ?>
