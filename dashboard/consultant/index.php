<?php
$title = 'Dashboard';
require_once 'layout.php';
require_once '../../includes/db.php';
?>

<div class="row g-4">
    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="mind-lingo.php" class="text-decoration-none">
            <div class="dashboard-card bg-remark h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M184 0c30.9 0 58.1 17.3 72 43.5C270 17.3 297.1 0 328 0c48.6 0 88 39.4 88 88c0 10.9-2 21.3-5.7 31c34.9 14.8 59.7 49 59.7 89c0 36.4-20.4 68.3-50.6 84.1c3.1 10.1 4.6 20.8 4.6 31.9c0 57.4-46.6 104-104 104c-11.8 0-23.1-2-33.6-5.6c-17 19.8-41.9 33.6-69.8 33.6c-28.5 0-54-14.3-71-34.6c-10.4 3.3-21.6 5.2-33.3 5.2c-57.4 0-104-46.6-104-104c0-11.6 1.9-22.8 5.4-33.4C18 317 0 286.7 0 252c0-43.1 27.9-79 66.5-92.4C63 148.9 61 137.6 61 126c0-50.8 41.2-92 92-92c10.7 0 21 1.8 30.6 5.1C194.2 19 216 0 241 0z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Mind Lingo</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="psych-tests.php" class="text-decoration-none">
            <div class="dashboard-card bg-quiz h-100">
                <div class="card-icon">
                    <span class="nav-icon">🧩</span>
                </div>
                <h5 class="mb-1">Psych Tests</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="students-info.php" class="text-decoration-none">
            <div class="dashboard-card bg-class h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M64 80c-8.8 0-16 7.2-16 16l0 320c0 8.8 7.2 16 16 16l272 0 0-80c0-26.5 21.5-48 48-48l80 0 0-208c0-8.8-7.2-16-16-16L64 80z"/><path fill="currentColor" d="M48 96c0-26.5 21.5-48 48-48l384 0c26.5 0 48 21.5 48 48l0 237.5c0 17-6.7 33.3-18.7 45.3L413.3 474.7c-12 12-28.3 18.7-45.3 18.7L96 493.4C69.5 493.4 48 471.9 48 445.4L48 96z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Students Information</h5>
            </div>
        </a>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>
