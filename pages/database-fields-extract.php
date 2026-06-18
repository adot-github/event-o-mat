<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class_database_fields.php';

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dbf_extract_action'])) {
    if (!isset($_POST['dbf_extract_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dbf_extract_nonce'])), 'dbf_extract')) {
        $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } else {
        $result = (new Evtmgr_Database_Fields())->extract();
    }
}
?>

<div class="container-xxl py-4">
    <h1 class="h3 mb-4">Database Fields: Felder extrahieren</h1>

    <p class="text-muted mb-4">
        Liest alle Tabellen mit <code>evtmgr</code> im Namen aus und fügt fehlende Felder
        in <code>wp_evtmgr_database_fields</code> ein
        (<code>str_table_name</code>, <code>str_frm_field_name</code>).
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

    <?php if ($result !== null) : ?>
        <div class="alert alert-success">
            <strong><?php echo (int) $result['inserted']; ?></strong> Felder eingefügt &nbsp;·&nbsp;
            <strong><?php echo (int) $result['skipped']; ?></strong> bereits vorhanden
        </div>

        <?php if (!empty($result['details'])) : ?>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tabelle</th>
                        <th>Feld</th>
                        <th class="text-center" style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['details'] as $row) :
                        $status = $row['status'];
                        $badge  = match($status) {
                            'inserted' => 'bg-primary',
                            'skipped'  => 'bg-secondary',
                            default    => 'bg-danger',
                        };
                        $label  = match($status) {
                            'inserted' => 'Eingefügt',
                            'skipped'  => 'Vorhanden',
                            default    => 'Fehler',
                        };
                    ?>
                        <tr>
                            <td class="font-monospace"><?php echo esc_html($row['table']); ?></td>
                            <td class="font-monospace"><?php echo esc_html($row['field']); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo esc_attr($badge); ?>"><?php echo esc_html($label); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <?php wp_nonce_field('dbf_extract', 'dbf_extract_nonce'); ?>
            <input type="hidden" name="dbf_extract_action" value="1">
            <button type="submit" class="btn btn-primary rounded-pill">
                Felder extrahieren
            </button>
        </div>
    </form>
</div>
