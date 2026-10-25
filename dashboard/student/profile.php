<?php
$title = 'My Profile';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/parent-phones.php';
require_role('student');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$avatar_message = '';
$avatar_error = '';
$profile_message = trim((string)($_GET['success'] ?? ''));
$profile_error = trim((string)($_GET['error'] ?? ''));

users_ensure_parent_phone_columns($pdo);

$stmt = $pdo->prepare("SELECT username, full_name, phone, father_phone, mother_phone, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php?error=" . urlencode('کاربر یافت نشد.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $upload_dir = '../../uploads/avatars/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['avatar'];
    $avatar_error = '';

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $avatar_error = 'خطا در آپلود فایل.';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $avatar_error = 'حجم فایل نباید بیشتر از 2 مگابایت باشد.';
    } else {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $file_type = mime_content_type($file['tmp_name']);

        if (!in_array($file_type, $allowed_types, true)) {
            $avatar_error = 'فقط فرمت‌های JPG، PNG و WebP مجاز هستند.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'student_' . $user_id . '_' . time() . '.' . $ext;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                if (!empty($user['avatar']) && file_exists('../../' . ltrim($user['avatar'], '/'))) {
                    @unlink('../../' . ltrim($user['avatar'], '/'));
                }

                $avatar_url = '/uploads/avatars/' . $new_filename;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatar_url, $user_id]);

                $avatar_message = 'عکس پروفایل با موفقیت آپلود شد.';
                $user['avatar'] = $avatar_url;
            } else {
                $avatar_error = 'خطا در ذخیره فایل.';
            }
        }
    }
}
?>
<link href="../../assets/css/student/student-profile.css?v=<?= time() ?>" rel="stylesheet">

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">آپلود عکس پروفایل</h5>
            </div>
            <div class="card-body">
                <?php if ($avatar_message !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($avatar_message) ?></div>
                <?php endif; ?>
                <?php if ($avatar_error !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($avatar_error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">انتخاب عکس جدید</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*" required>
                        <div class="form-text">فرمت‌های مجاز: JPG, PNG, WebP — حداکثر 2MB</div>
                    </div>
                    <button type="submit" class="btn btn-success">آپلود عکس</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">اطلاعات پروفایل</h5>
            </div>
            <div class="card-body">
                <?php if ($profile_message !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($profile_message) ?></div>
                <?php endif; ?>
                <?php if ($profile_error !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($profile_error) ?></div>
                <?php endif; ?>
                <form method="POST" action="update-profile.php">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">نام کاربری</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نام و نام خانوادگی *</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="father_phone">شماره پدر</label>
                            <input type="tel" name="father_phone" id="father_phone" class="form-control" dir="ltr"
                                   inputmode="tel" autocomplete="tel"
                                   value="<?= htmlspecialchars($user['father_phone'] ?? '') ?>"
                                   placeholder="09123456789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mother_phone">شماره مادر</label>
                            <input type="tel" name="mother_phone" id="mother_phone" class="form-control" dir="ltr"
                                   inputmode="tel" autocomplete="tel"
                                   value="<?= htmlspecialchars($user['mother_phone'] ?? '') ?>"
                                   placeholder="09123456789">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">شماره تماس</label>
                            <input type="text" name="phone" class="form-control" dir="ltr"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="09123456789">
                        </div>
                    </div>
                    <p class="form-text mt-3 mb-0">شماره پدر و مادر را جدا وارد کنید تا آموزشگاه بتواند با اولیا تماس بگیرد.</p>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">به‌روزرسانی اطلاعات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">پیش‌نمایش</h5>
            </div>
            <div class="card-body text-center">
                <div class="profile-avatar-container mb-3">
                    <img src="<?= htmlspecialchars($user['avatar'] ?? '../../uploads/avatars/daweid-icon-7797704_640.png') ?>"
                         alt="عکس پروفایل"
                         class="profile-avatar"
                         width="150" height="150"
                         style="object-fit: cover; border-radius: 50%;">
                </div>
                <p class="mb-0"><?= htmlspecialchars($user['full_name']) ?></p>
                <small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>
