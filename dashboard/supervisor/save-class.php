<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $term_id = (int)($_POST['term_id'] ?? 0);
        $class_title = trim($_POST['class_title'] ?? '');
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $class_type = $_POST['class_type'] ?? '';
        $online_link = trim($_POST['online_link'] ?? '');
        $schedule = trim($_POST['schedule'] ?? '');
        $schedule_type = $_POST['schedule_type'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];

        if (!$term_id || empty($class_title) || !$teacher_id || !in_array($class_type, ['online', 'offline', 'private']) || !in_array($schedule_type, ['odd', 'even'])) {
            throw new Exception('لطفاً تمامی فیلدهای ستاره‌دار را پر کنید.');
        }

        if ($class_type == 'online' && empty($online_link)) {
            throw new Exception('لینک کلاس آنلاین الزامی است.');
        }

        $stmt = $pdo->prepare("SELECT id FROM terms WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$term_id]);
        if (!$stmt->fetch()) {
            throw new Exception('ترم یافت نشد.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO classes (title, term_id, class_type, online_link, schedule, schedule_type, teacher_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $class_title,
            $term_id,
            $class_type,
            $online_link,
            $schedule,
            $schedule_type,
            $teacher_id,
            $_SESSION['user_id']
        ]);
        $class_id = $pdo->lastInsertId();

        if (!empty($student_ids)) {
            foreach ($student_ids as $user_id) {
                $user_id = (int)$user_id;
                if ($user_id > 0) {
                    $stmt = $pdo->prepare("INSERT INTO class_students (class_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$class_id, $user_id]);
                }
            }
        }

        $pdo->commit();

        try {
            require_once __DIR__ . '/../../includes/term-plan.php';
            term_plan_ensure_schema($pdo);
            if (function_exists('term_plan_apply_level_template')) {
                term_plan_apply_level_template($pdo, (int)$class_id, $class_title, (int)$_SESSION['user_id'], [
                    'id' => (int)$class_id,
                    'title' => $class_title,
                    'class_type' => $class_type,
                ]);
            }
        } catch (Throwable $e) {
            error_log('term_plan apply on save-class: ' . $e->getMessage());
        }

        try {
            require_once __DIR__ . '/../../includes/identity-sync.php';
            foreach ((array)$student_ids as $user_id) {
                identity_sync_student_classes($pdo, (int)$user_id);
            }
            if ($class_type === 'online' || $class_type === 'private' || $online_link !== '') {
                require_once __DIR__ . '/../../includes/alocom-access.php';
                require_once __DIR__ . '/../../includes/terms.php';
                $term = term_load($pdo, $term_id);
                if ($term && term_row_is_teaching($term)) {
                    $cls = [
                        'id' => (int)$class_id,
                        'title' => $class_title,
                        'class_type' => $class_type,
                        'online_link' => $online_link,
                        'schedule' => $schedule,
                        'term_id' => $term_id,
                    ];
                    $events = null;
                    foreach ((array)$student_ids as $user_id) {
                        $user_id = (int)$user_id;
                        if ($user_id <= 0) {
                            continue;
                        }
                        $st = $pdo->prepare("SELECT id, full_name, username, phone FROM users WHERE id = ? LIMIT 1");
                        $st->execute([$user_id]);
                        $student = $st->fetch(PDO::FETCH_ASSOC);
                        if (!$student) {
                            continue;
                        }
                        if ($events === null) {
                            $events = alocom_list_events();
                        }
                        alocom_enroll_panel_student_in_event($pdo, $student, $cls, $events);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('identity class sync: ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'کلاس با موفقیت ثبت شد.']);
        exit;
    }

    throw new Exception('درخواست نامعتبر.');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save Class Error: " . $e->getMessage());
    if ($e instanceof PDOException && function_exists('ghazal_error_report')) {
        ghazal_error_report('DB-SAVE', 'ثبت کلاس در دیتابیس ناموفق بود.', ghazal_error_trim($e->getMessage()));
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
