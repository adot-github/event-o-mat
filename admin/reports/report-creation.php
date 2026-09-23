
<style>
.event-report-table-scroll {
    width: 100%;
    max-width: 100%;
    overflow: auto;
    cursor: grab;
    border: 0px solid #dee2e6;
    border-radius: 0.375rem;
    background: #fff;
}

.event-report-table-scroll.is-dragging {
    cursor: grabbing;
    user-select: none;
}

.event-report-table {
    min-width: 1200px;
    margin-bottom: 0;
    white-space: nowrap;
}

.event-report-table td,
.event-report-table th {
    vertical-align: top;
}
</style>

<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/../classes/class-reports.php';
require_once dirname(__DIR__) . '/../classes/class-event-registration.php';
require_once dirname(__DIR__) . '/../classes/class-evtmgr-events.php';

$report_title = isset($report_title) && trim((string) $report_title) !== ''
    ? (string) $report_title
    : 'Report';

$report_file_name = isset($report_file_name) && trim((string) $report_file_name) !== ''
    ? (string) $report_file_name
    : 'report';

$report_table = isset($report_table)
    ? (string) $report_table
    : '';

$report_fields = isset($report_fields) && is_array($report_fields)
    ? $report_fields
    : array();

$report_custom_sql = isset($report_custom_sql)
    ? (string) $report_custom_sql
    : '';

$report_owner_column = isset($report_owner_column) && trim((string) $report_owner_column) !== ''
    ? (string) $report_owner_column
    : 'fky_event_uid';

/*
 * IMPORTANT:
 * Do not cast this to string.
 * It may be an array:
 *
 * $report_order_by = array(
 *     'str_last_name'  => 'ASC',
 *     'str_first_name' => 'ASC',
 * );
 */
$report_order_by = isset($report_order_by)
    ? $report_order_by
    : '';

$report_order_direction = isset($report_order_direction)
    ? (string) $report_order_direction
    : 'ASC';

$fields_as_list = isset($fields_as_list) && is_array($fields_as_list)
    ? $fields_as_list
    : array();

$event_registration = new Event_Registration_Context();
$event_uid = $event_registration->get_cookie_event_uid(true);

$reports = new Event_Registration_Reports();

$rows = $reports->get_report_rows(array(
    'table'           => $report_table,
    'fields'          => $report_fields,
    'custom_sql'      => $report_custom_sql,
    'event_uid'       => $event_uid,
    'owner_column'    => $report_owner_column,
    'order_by'        => $report_order_by,
    'order_direction' => $report_order_direction,
));

$record_count = is_array($rows) ? count($rows) : 0;

$event_title = '';

if ($event_uid !== '' && class_exists('Evtmgr_Events')) {
    $event_obj = new Evtmgr_Events();
    $event     = $event_obj->get_events_by_event_uid($event_uid, 'de');

    if (is_array($event) && !empty($event)) {
        $event_title = $event['str_event_name_']
            ?? $event['str_event_name_de']
            ?? '';
    }
}

?>

<div class="container-xxl py-4 event-report-page">

    <h1 class="h3 mb-2">
        <?php echo esc_html($report_title); ?>
    </h1>

    <?php if ($event_title !== '') : ?>
        <h2 class="h5 mb-3">
            <?php echo esc_html($event_title); ?>
        </h2>
    <?php endif; ?>

    <p class="mb-1">
        Event Uid:
        <strong><?php echo esc_html($event_uid); ?></strong>
    </p>

    <p class="">
        Anzahl Datensätze:
        <strong><?php echo esc_html((string) $record_count); ?></strong>
    </p>

    <div class="mb-3">
        <button type="button" class="btn btn-success rounded-pill" id="event-report-download-excel">
            Excel herunterladen
        </button>

        <button type="button" class="btn btn-success rounded-pill ms-4" id="event-report-download-csv">
            CSV herunterladen
        </button>

        <button type="button" class="btn btn-primary rounded-pill ms-4" id="event-report-download-word">
            Word herunterladen
        </button>
    </div>

    <?php $reports->render_table($rows, $report_fields, $fields_as_list); ?>

</div>

<style>
    /* Break out of Bootstrap's container-xxl max-width so the (horizontally
       scrollable) report table can use the full admin content width. */
    .event-report-page {
        max-width: 100%;
    }

    .event-report-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow: auto;
        cursor: grab;
        border: 0px solid #dee2e6;
        border-radius: 0.375rem;
        background: #fff;
        max-height: 75vh;
    }

    .event-report-table-scroll.is-dragging {
        cursor: grabbing;
        user-select: none;
    }

    .event-report-table {
        min-width: 1200px;
        margin-bottom: 0;
        white-space: nowrap;
    }

    .event-report-table td,
    .event-report-table th {
        vertical-align: top;
    }

    .event-report-table ul {
        white-space: normal;
    }
</style>

<script>
(function () {
    const table = document.getElementById('event-report-table');
    const reportFileBaseName = <?php echo wp_json_encode(
        ($event_uid !== '' ? ($report_file_name . '-' . $event_uid) : $report_file_name)
            . '-' . current_time('Y-m-d')
    ); ?>;

    function downloadFile(filename, content, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();

        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function tableToHtmlDocument() {
        if (!table) {
            return '';
        }

        return `
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8">
            </head>
            <body>
                ${table.outerHTML}
            </body>
            </html>
        `;
    }

    function csvEscape(value) {
        const stringValue = String(value ?? '');

        if (/["\r\n;]/.test(stringValue)) {
            return '"' + stringValue.replace(/"/g, '""') + '"';
        }

        return stringValue;
    }

    function tableToCsv() {
        if (!table) {
            return '';
        }

        const lines = [];

        table.querySelectorAll('tr').forEach(function (row) {
            const cells = Array.from(row.children).map(function (cell) {
                const listItems = cell.querySelectorAll('li');
                let text;

                if (listItems.length) {
                    text = Array.from(listItems).map(function (li) {
                        return li.textContent.trim();
                    }).join('; ');
                } else {
                    text = cell.textContent.trim();
                }

                return csvEscape(text);
            });

            lines.push(cells.join(';'));
        });

        // Leading BOM so Excel recognizes UTF-8 (umlauts, accents) correctly.
        return String.fromCharCode(0xFEFF) + lines.join('\r\n');
    }

    const excelButton = document.getElementById('event-report-download-excel');
    const csvButton = document.getElementById('event-report-download-csv');
    const wordButton = document.getElementById('event-report-download-word');

    if (excelButton) {
        excelButton.addEventListener('click', function () {
            downloadFile(
                reportFileBaseName + '.xls',
                tableToHtmlDocument(),
                'application/vnd.ms-excel;charset=utf-8'
            );
        });
    }

    if (csvButton) {
        csvButton.addEventListener('click', function () {
            downloadFile(
                reportFileBaseName + '.csv',
                tableToCsv(),
                'text/csv;charset=utf-8;'
            );
        });
    }

    if (wordButton) {
        wordButton.addEventListener('click', function () {
            downloadFile(
                reportFileBaseName + '.doc',
                tableToHtmlDocument(),
                'application/msword;charset=utf-8'
            );
        });
    }

    document.querySelectorAll('.js-report-table-scroll').forEach(function (wrapper) {
        let isDown = false;
        let startX = 0;
        let startY = 0;
        let scrollLeft = 0;
        let scrollTop = 0;

        wrapper.addEventListener('mousedown', function (event) {
            isDown = true;
            wrapper.classList.add('is-dragging');

            startX = event.pageX - wrapper.offsetLeft;
            startY = event.pageY - wrapper.offsetTop;
            scrollLeft = wrapper.scrollLeft;
            scrollTop = wrapper.scrollTop;
        });

        wrapper.addEventListener('mouseleave', function () {
            isDown = false;
            wrapper.classList.remove('is-dragging');
        });

        wrapper.addEventListener('mouseup', function () {
            isDown = false;
            wrapper.classList.remove('is-dragging');
        });

        wrapper.addEventListener('mousemove', function (event) {
            if (!isDown) {
                return;
            }

            event.preventDefault();

            const x = event.pageX - wrapper.offsetLeft;
            const y = event.pageY - wrapper.offsetTop;

            wrapper.scrollLeft = scrollLeft - (x - startX);
            wrapper.scrollTop = scrollTop - (y - startY);
        });
    });
})();
</script>
