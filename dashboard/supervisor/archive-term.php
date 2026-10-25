<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/jdate.php';
require_once '../../includes/terms.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $term_id = (int)($input['term_id'] ?? 0);
    $action = $input['action'] ?? 'archive';

    if (!$term_id) {
        throw new Exception('شناسه ترم نامعتبر است.');
    }

    $term = term_load($pdo, $term_id);
    if (!$term) {
        throw new Exception('ترم یافت نشد.');
    }

    if ($action == 'restore') {
        $stmt = $pdo->prepare("UPDATE terms SET is_archived = 0 WHERE id = ?");
        $stmt->execute([$term_id]);
        $term = term_load($pdo, $term_id);
        if ($term) {
            term_mirror_accounting($pdo, $term, 'active');
        }
        echo json_encode(['success' => true, 'message' => 'ترم با موفقیت از بایگانی بازگردانی شد.']);
    } else {
        $stmt = $pdo->prepare("SELECT year FROM terms WHERE id = ?");
        $stmt->execute([$term_id]);
        $current_year = $stmt->fetchColumn();

        if (!$current_year || $current_year === '1404') {
            $current_year = jdate('Y');
        }

        $term['year'] = $current_year;
        $dest = term_pick_forward_destination($pdo, $term_id);
        $moved = 0;
        $forward_note = '';
        if ($dest) {
            $moved = term_forward_marked_classes($pdo, $term_id, (int)$dest['id']);
            if ($moved > 0) {
                $dest_title = function_exists('term_english_title')
                    ? term_english_title($dest['title'] ?? '')
                    : (string)($dest['title'] ?? '');
                $forward_note = ' ' . $moved . ' کلاس Forward با همین وضعیت به «' . $dest_title . '» منتقل شد. شهریه حسابداری همان ترم شروع ماند.';
            }
        }

        $stmt = $pdo->prepare("UPDATE terms SET is_archived = 1, year = ? WHERE id = ?");
        $stmt->execute([$current_year, $term_id]);
        term_complete_accounting($term);
        echo json_encode(['success' => true, 'message' => 'ترم با موفقیت بایگانی شد.' . $forward_note]);
    }

    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
