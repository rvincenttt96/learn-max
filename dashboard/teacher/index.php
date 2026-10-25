<?php
$title = 'Dashboard';
require_once 'layout.php';
require_once '../../includes/db.php';
?>

<link href="../../assets/css/teacher/dashboard.css?v=<?= time() ?>" rel="stylesheet">

<div class="row g-4" >
    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="classes.php" class="text-decoration-none">
            <div class="dashboard-card bg-class h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 96l0 320c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64C46.3 64 32 78.3 32 96zm120 64a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm0 96a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm0 96a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm40-192c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M64 64C46.3 64 32 78.3 32 96l0 320c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64zM0 96C0 60.7 28.7 32 64 32l320 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zm104 64a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm88 0c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm-64-72a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm-24 72a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">My Class List</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="quiz-scores.php" class="text-decoration-none">
            <div class="dashboard-card bg-quiz h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 256l0 144c0 26.5 21.5 48 48 48l368 0 0-80c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-265.6-54.4 76.2c-6 8.4-15.7 13.4-26 13.4l-6.7 0c-5.8 0-11.5-1.6-16.5-4.6L256 153.6 256 368c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-201.6-54.4 76.2c-6 8.4-15.7 13.4-26 13.4L32 256zm96 16c0-8.8 7.2-16 16-16s16 7.2 16 16l0 96c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-96zm192-64c0-8.8 7.2-16 16-16s16 7.2 16 16l0 160c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-160z"/><path fill="currentColor" d="M16 32c8.8 0 16 7.2 16 16l0 352c0 26.5 21.5 48 48 48l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L80 480c-44.2 0-80-35.8-80-80L0 48c0-8.8 7.2-16 16-16zM144 256c8.8 0 16 7.2 16 16l0 96c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-96c0-8.8 7.2-16 16-16zM256 144l0 224c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-224c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 48c8.8 0 16 7.2 16 16l0 160c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-160c0-8.8 7.2-16 16-16zM448 80l0 288c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-288c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Quiz Marks</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="quiz-builder.php" class="text-decoration-none">
            <div class="dashboard-card bg-quizbuilder h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path opacity=".4" fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-96 0c-17.7 0-32-14.3-32-32L256 0 64 0zM112 232c-13.3 0-24 10.7-24 24s10.7 24 24 24l160 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-160 0zm-24 120c0 13.3 10.7 24 24 24l160 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-160 0c-13.3 0-24 10.7-24 24z"/><path fill="currentColor" d="M256 0l0 96c0 17.7 14.3 32 32 32l96 0L256 0zM88 256c0-13.3 10.7-24 24-24l160 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-160 0c-13.3 0-24-10.7-24-24zm24 72c-13.3 0-24 10.7-24 24s10.7 24 24 24l160 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-160 0z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Quiz Builder</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="attendance.php" class="text-decoration-none">
            <div class="dashboard-card bg-attendance h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M48.5 576C57.3 576 64.5 568.8 64.5 560L64.5 544C64.5 464.5 129 400 208.5 400L304.5 400C384 400 448.5 464.5 448.5 544L448.5 560C448.5 568.8 455.7 576 464.5 576L48.5 576zM352.5 192C352.5 245 309.5 288 256.5 288C203.5 288 160.5 245 160.5 192C160.5 139 203.5 96 256.5 96C309.5 96 352.5 139 352.5 192z"/><path fill="currentColor" d="M304.5 368C401.7 368 480.5 446.8 480.5 544L480.5 560C480.5 568.8 473.3 576 464.5 576C455.7 576 448.5 568.8 448.5 560L448.5 544C448.5 464.5 384 400 304.5 400L208.5 400C129 400 64.5 464.5 64.5 544L64.5 560C64.5 568.8 57.3 576 48.5 576C39.7 576 32.5 568.8 32.5 560L32.5 544C32.5 446.8 111.3 368 208.5 368L304.5 368zM595.6 170.2C600.8 163.1 610.8 161.5 617.9 166.7C625 171.9 626.6 181.9 621.4 189L515.8 334.2C513 338 508.7 340.4 504 340.7C499.3 341 494.7 339.3 491.4 335.9L437 279.6C430.9 273.2 431 263.1 437.4 257C443.8 250.9 453.9 251.1 460 257.4L501.2 300L595.6 170.2zM256.5 320C185.8 320 128.5 262.7 128.5 192C128.5 121.3 185.8 64 256.5 64C327.2 64 384.5 121.3 384.5 192C384.5 262.7 327.2 320 256.5 320zM256.5 96C203.5 96 160.5 139 160.5 192C160.5 245 203.5 288 256.5 288C309.5 288 352.5 245 352.5 192C352.5 139 309.5 96 256.5 96z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Roll Call</h5>
            </div>
        </a>
    </div>
<div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="session-plan.php" class="text-decoration-none">
            <div class="dashboard-card bg-session h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 560"><path opacity=".4" fill="currentColor" d=""/><path fill="currentColor" d="M448.4 96l-384 0c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l197.8 0c-4.1 9-7.1 18.5-8.7 28.3l-.6 3.7-188.5 0c-35.3 0-64-28.7-64-64l0-256c0-35.3 28.7-64 64-64l384 0c35.3 0 64 28.7 64 64l0 76.1c-10.8 0-21.6 1.5-32 4.4l0-80.6c0-17.7-14.3-32-32-32zM96.4 304c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm16-112l288 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-288 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zM341.5 440.1c-4.7 4.7-7.8 10.8-8.9 17.4l-8.5 50.8 50.8-8.5c6.6-1.1 12.6-4.2 17.4-8.9l102.2-102.2-50.7-50.7-102.2 102.2zM466.3 315.3l50.7 50.7 20.7-20.7c14-14 14-36.7 0-50.7s-36.7-14-50.7 0l-20.7 20.7zM301 452.2c2.2-13.2 8.4-25.3 17.9-34.7L464.4 272c26.5-26.5 69.5-26.5 96 0s26.5 69.5 0 96L414.9 513.5c-9.4 9.4-21.6 15.7-34.7 17.9l-74.6 12.4c-.8 .1-1.6 .2-2.4 .2-8.2 0-14.8-6.6-14.8-14.8 0-.8 .1-1.6 .2-2.4L301 452.2z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Session Plan</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="assignments.php" class="text-decoration-none">
            <div class="dashboard-card bg-assignment h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path opacity=".4" fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-96 0c-17.7 0-32-14.3-32-32L256 0 64 0z"/><path fill="currentColor" d="M256 0l0 96c0 17.7 14.3 32 32 32l96 0L256 0zM136 240c-13.3 0-24 10.7-24 24s10.7 24 24 24l48 0 0 72c0 13.3 10.7 24 24 24s24-10.7 24-24l0-72 48 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-48 0 0-48c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 48-48 0z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Assignment</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="term-plan.php" class="text-decoration-none">
            <div class="dashboard-card bg-termplan h-100">
                <div class="card-icon">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 96c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64L32 96z"/><path fill="currentColor" d="M256 96c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96zM32 320c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96zm224 0c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Term Plan</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="error-gravity.php" class="text-decoration-none">
            <div class="dashboard-card bg-error h-100">
                <div class="card-icon">
                   <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 256a224 224 0 1 0 448 0 224 224 0 1 0 -448 0zM280 360a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zM232.5 152.9c-.8-13.5 10-24.9 23.5-24.9s24.3 11.4 23.5 24.9l-7.6 136c-.5 8.5-7.5 15.1-16 15.1s-15.5-6.6-16-15.1l-7.6-136z"/><path fill="currentColor" d="M256 512a256 256 0 1 1 0-512 256 256 0 1 1 0 512zm0-480a224 224 0 1 0 0 448 224 224 0 1 0 0-448zm0 352a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm0-256c13.5 0 24.3 11.4 23.5 24.9l-7.6 136c-.5 8.5-7.5 15.1-16 15.1s-15.5-6.6-16-15.1l-7.6-136c-.8-13.5 10-24.9 23.5-24.9z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">Error Gravity</h5>
            </div>
        </a>
    </div>
 

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="observation-forms.php" class="text-decoration-none">
            <div class="dashboard-card bg-observation h-100">
                <div class="card-icon">
                   <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 64l0 384c0 17.7 14.3 32 32 32l256 0c17.7 0 32-14.3 32-32l0-256-96 0c-35.3 0-64-28.7-64-64l0-96-128 0C46.3 32 32 46.3 32 64zM96 272c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M64 32l128 0 0 96c0 35.3 28.7 64 64 64l96 0 0 256c0 17.7-14.3 32-32 32L64 480c-17.7 0-32-14.3-32-32L32 64c0-17.7 14.3-32 32-32zM224 45.3L338.7 160 256 160c-17.7 0-32-14.3-32-32l0-82.7zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm48 256c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0zm0 96c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0z"/></svg>
                    </span>
                </div>
                <h5 class="mb-1">TOC Forms</h5>
            </div>
        </a>
    </div>

    <!-- اضافه شدن بخش Mind Lingo -->
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

</div>

<?php require_once 'layout-end.php'; ?>