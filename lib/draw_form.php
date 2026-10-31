<?php
// lib/draw_form.php
// Shared by admin/draws.php (create) and admin/draw-manage.php (edit).
// Requires lib/draw_trigger.php to be loaded first (DRAW_NO_DEADLINE).

if (!defined('MAX_TARGET_ENTRIES')) define('MAX_TARGET_ENTRIES', 1000000);

function drawCategories(): array {
    return ['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'];
}

/**
 * Validate the draw form shared by create and edit.
 * Timed:   needs start + end, end after start.
 * Entries: needs start + a target number of entries; the end date is an OPTIONAL deadline.
 * @return array{0: ?string, 1: array} [error, values]
 */
function validateDrawForm(string $type, int $currentEntries = 0): array {
    $v = [
        'title' => trim($_POST['title']         ?? ''),
        'desc'  => trim($_POST['description']   ?? ''),
        'rules' => trim($_POST['rules']         ?? ''),
        'prize' => trim($_POST['prize_details'] ?? ''),
        'cat'   => trim($_POST['category']      ?? ''),
        'start' => $_POST['start_date']         ?? '',
        'end'   => trim($_POST['end_date']      ?? ''),
        'target'=> null,
    ];
    if ($v['title'] === '' || $v['start'] === '') return ['Title and start date are required.', $v];

    if ($type === 'entries') {
        $t = trim((string)($_POST['target_entries'] ?? ''));
        if (!preg_match('/^\d+$/', $t) || (int)$t < 2 || (int)$t > MAX_TARGET_ENTRIES) {
            return ['Enter how many entries trigger the draw (a whole number from 2 to ' . number_format(MAX_TARGET_ENTRIES) . ').', $v];
        }
        if ($currentEntries > 0 && (int)$t <= $currentEntries) {
            return ["This draw already has $currentEntries entries. The target must be higher, or use End Draw.", $v];
        }
        $v['target'] = (int)$t;
        if ($v['end'] === '') {
            $v['end'] = DRAW_NO_DEADLINE;                       // no deadline: only the entry target ends it
        } elseif (strtotime($v['end']) <= strtotime($v['start'])) {
            return ['The deadline must be after the start date.', $v];
        }
    } else {
        if ($v['end'] === '') return ['Title, start date and end date are required.', $v];
        if (strtotime($v['end']) <= strtotime($v['start'])) return ['End date must be after start date.', $v];
    }
    return [null, $v];
}

/** Save the uploaded banner (if any) and return its file name, or null. */
function uploadDrawBanner(): ?string {
    if (empty($_FILES['banner_image']['name'])) return null;
    $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return null;
    $name = 'banner-'.uniqid().'.'.$ext;
    move_uploaded_file($_FILES['banner_image']['tmp_name'], UPLOAD_PATH.$name);
    return $name;
}