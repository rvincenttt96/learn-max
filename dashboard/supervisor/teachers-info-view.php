<?php
$title = 'Teacher Profile';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/teachers-info.php';
require_role('supervisor');

$id = (int)($_GET['id'] ?? 0);
$teacher = $id ? teachers_info_find($pdo, $id) : null;
if (!$teacher) {
    echo '<div class="alert alert-danger">استاد پیدا نشد.</div>';
    echo '<a class="btn btn-light" href="teachers-info.php">بازگشت</a>';
    require_once 'layout-end.php';
    return;
}
$terms = teachers_info_profile_by_term($pdo, (int)$teacher['id']);
?>

<style>
.si-hero { background:rgba(18,14,40,.9); border:1px solid rgba(211,47,47,.28); border-radius:16px; padding:18px 20px; color:#fff; margin-bottom:18px; }
.si-term { background:rgba(18,14,40,.88); border:1px solid rgba(255,255,255,.08); border-radius:16px; color:#fff; margin-bottom:16px; overflow:hidden; }
.si-term h5 { margin:0; }
.si-term .head { background:linear-gradient(90deg,#7b1a1a,#d32f2f); padding:12px 16px; display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.si-term .body { padding:16px; }
.si-table { color:#fff; margin:0; }
.si-table thead th { background:rgba(255,255,255,.06); white-space:nowrap; }
.si-empty { text-align:center; padding:40px 12px; color:#cfc9d8; }
.si-pill { display:inline-block; background:rgba(255,255,255,.08); border-radius:999px; padding:4px 10px; font-size:.85rem; }
</style>

<div class="si-hero d-flex flex-wrap justify-content-between gap-3 align-items-center">
    <div>
        <div class="text-white-50 small mb-1">Teachers Information</div>
        <h4 class="mb-1"><?= htmlspecialchars(trim($teacher['first_name'] . ' ' . $teacher['last_name'])) ?></h4>
        <div>نام کاربری: <strong><?= htmlspecialchars($teacher['username']) ?></strong></div>
    </div>
    <a class="btn btn-outline-light" href="teachers-info.php">بازگشت به لیست</a>
</div>

<?php if (!$terms): ?>
    <div class="si-term">
        <div class="si-empty">
            <h5>کلاسی ثبت نشده</h5>
            <p class="mb-0">این استاد هنوز در هیچ ترم و کلاسی ثبت نشده است.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($terms as $term): ?>
    <div class="si-term">
        <div class="head">
            <h5><?= htmlspecialchars($term['term_title']) ?></h5>
            <small><?= count($term['classes']) ?> کلاس</small>
        </div>
        <div class="body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered si-table align-middle">
                    <thead>
                        <tr>
                            <th>کلاس</th>
                            <th>نوع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($term['classes'] as $class): ?>
                        <tr>
                            <td><?= htmlspecialchars($class['title'] !== '' ? $class['title'] : 'بدون عنوان') ?><?= class_live_dot($class) ?></td>
                            <td>
                                <?php if ($class['class_type_label'] !== ''): ?>
                                    <span class="si-pill"><?= htmlspecialchars($class['class_type_label']) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once 'layout-end.php'; ?>
