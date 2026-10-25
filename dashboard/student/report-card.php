<?php
require_once '../../includes/auth.php';
require_role('student'); 

require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/quizzes.php';
term_ensure_schema($pdo);
quizzes_ensure_schema($pdo);

$user_id = $_SESSION['user_id'];
$current_page = 'report-card.php';
$title = 'Report Card';

// دریافت اطلاعات پایه زبان‌آموز (نام و نام کاربری/کد زبان‌آموزی)
$stmt_user = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$student_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

require_once 'layout.php';

// کوئری جامع دریافت نمرات، اطلاعات کلاس، ترم و مدرس (هم ترم جاری و هم ترم‌های قبل)
$stmt = $pdo->prepare("
    SELECT 
        c.id as class_id,
        c.title as class_title,
        c.teacher_id,
        u_teacher.full_name as teacher_name,
        t.title as term_title,
        t.is_archived,
        t.starts_on,
        " . quizzes_sql_columns('sq') . ",
        ss.supervisor_score,
        ss.is_submitted,
        ss.submitted_at,
        ml.communication_dev, ml.learning_eng, ml.learning_cons, ml.personal_growth
    FROM classes c
    JOIN terms t ON c.term_id = t.id
    JOIN class_students cs ON c.id = cs.class_id
    LEFT JOIN users u_teacher ON c.teacher_id = u_teacher.id
    LEFT JOIN student_quizzes sq ON cs.user_id = sq.user_id AND c.id = sq.class_id
    LEFT JOIN supervisor_scores ss ON cs.user_id = ss.user_id AND c.id = ss.class_id
    LEFT JOIN mind_lingo ml ON cs.user_id = ml.student_id AND c.id = ml.class_id
    WHERE cs.user_id = ? AND c.is_deleted = 0 AND t.is_deleted = 0
    ORDER BY t.created_at DESC, c.title
");
$stmt->execute([$user_id]);
$grades = $stmt->fetchAll();
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<link href="../../assets/css/student/student-report-card.css?v=<?= time() ?>" rel="stylesheet">

<div class="card">
  <div class="card-header">
    <h1 class="mb-0">کارنامه من</h1>
  </div>
  <div class="card-body">
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" href="#active-terms" data-bs-toggle="tab">ترم جاری</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#archived-terms" data-bs-toggle="tab">ترم‌های قبلی</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="active-terms">
            <?php
            $active_grades = array_filter($grades, function($grade) {
                if (!empty($grade['is_archived'])) {
                    return false;
                }
                $start = $grade['starts_on'] ?? null;
                if ($start && $start !== '0000-00-00' && $start > date('Y-m-d')) {
                    return false;
                }
                return true;
            });
            ?>
            
            <?php if (empty($active_grades)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 20px;">📊</div>
                        <h4>نمره‌ای برای نمایش وجود ندارد</h4>
                        <p class="text-muted">نمرات شما پس از ثبت توسط معلم نمایش داده می‌شود.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>کلاس</th>
                                        <?php foreach (quizzes_config() as $q): ?>
                                            <th><?= htmlspecialchars($q['report']) ?><br>(<?= (int)$q['max'] ?>)</th>
                                        <?php endforeach; ?>
                                        <th>جمع<br>(100)</th>
                                        <th>نمره سوپروایزر</th>
                                        <th>وضعیت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_grades as $grade):
                                        $t = quizzes_grade_totals($grade);
                                        $quizzes = $t['quizzes'];
                                        $has_quiz_scores = $t['has_quiz_scores'];
                                        $is_submitted = $t['is_submitted'];
                                        $gen_data = htmlspecialchars(json_encode([
                                            'student_name' => $student_info['full_name'],
                                            'student_id' => $student_info['username'],
                                            'class_name' => $grade['class_title'],
                                            'teacher_name' => $grade['teacher_name'] ?? 'N/A',
                                            'quiz_total' => $t['quiz_total'],
                                            'class_activity' => $t['activity'],
                                            'presence' => $t['presence'],
                                            'sup_score' => (int)$t['supervisor_score'],
                                            'final_score' => $t['final_score'],
                                            'ml_score' => $t['ml_score']
                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($grade['class_title']) ?></td>
                                        <?php if ($has_quiz_scores): ?>
                                            <?php foreach ($quizzes as $quiz): ?>
                                                <td><?= quizzes_filled($quiz) ? number_format($quiz, 0) : '-' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($t['class_total'], 0) ?></td>
                                            <td><?= $t['supervisor_score'] !== null ? number_format($t['supervisor_score'], 0) : '-' ?></td>
                                            <td>
                                                <?php if ($is_submitted): ?>
                                                    <span class="badge bg-success">تأیید شده</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">در انتظار تأیید</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="openReportModal(<?= $gen_data ?>)">
                                                    Generate ReportCard
                                                </button>
                                            </td>
                                        <?php else: ?>
                                            <td colspan="<?= quizzes_count() + 4 ?>" class="text-center text-muted">نمراتی ثبت نشده است</td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="archived-terms">
            <?php
            $archived_grades = array_filter($grades, function($grade) {
                return !empty($grade['is_archived']);
            });
            ?>
            
            <?php if (empty($archived_grades)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <h4>ترم قبلی‌ای وجود ندارد</h4>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>ترم</th>
                                        <th>کلاس</th>
                                        <?php foreach (quizzes_config() as $q): ?>
                                            <th><?= htmlspecialchars($q['report']) ?><br>(<?= (int)$q['max'] ?>)</th>
                                        <?php endforeach; ?>
                                        <th>جمع<br>(100)</th>
                                        <th>نمره سوپروایزر</th>
                                        <th>وضعیت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archived_grades as $grade):
                                        $t = quizzes_grade_totals($grade);
                                        $quizzes = $t['quizzes'];
                                        $has_quiz_scores = $t['has_quiz_scores'];
                                        $is_submitted = $t['is_submitted'];
                                        $gen_data = htmlspecialchars(json_encode([
                                            'student_name' => $student_info['full_name'],
                                            'student_id' => $student_info['username'],
                                            'class_name' => $grade['class_title'],
                                            'teacher_name' => $grade['teacher_name'] ?? 'N/A',
                                            'quiz_total' => $t['quiz_total'],
                                            'class_activity' => $t['activity'],
                                            'presence' => $t['presence'],
                                            'sup_score' => (int)$t['supervisor_score'],
                                            'final_score' => $t['final_score'],
                                            'ml_score' => $t['ml_score']
                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($grade['term_title']) ?></td>
                                        <td><?= htmlspecialchars($grade['class_title']) ?></td>
                                        <?php if ($has_quiz_scores): ?>
                                            <?php foreach ($quizzes as $quiz): ?>
                                                <td><?= quizzes_filled($quiz) ? number_format($quiz, 0) : '-' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= number_format($t['class_total'], 0) ?></td>
                                            <td><?= $t['supervisor_score'] !== null ? number_format($t['supervisor_score'], 0) : '-' ?></td>
                                            <td>
                                                <?php if ($is_submitted): ?>
                                                    <span class="badge bg-success">تأیید شده</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">در انتظار تأیید</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="openReportModal(<?= $gen_data ?>)">
                                                    Generate ReportCard
                                                </button>
                                            </td>
                                        <?php else: ?>
                                            <td colspan="<?= quizzes_count() + 4 ?>" class="text-center text-muted">نمراتی ثبت نشده است</td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<style>
    #reportCardModal .modal-content {
        background: transparent;
        border: none;
    }
    #reportCardModal .modal-body {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px;
    }
    
    .download-btn {
        background: linear-gradient(45deg, #e94560, #ff2e63);
        color: white; border: none; padding: 12px 25px; border-radius: 12px;
        font-size: 1rem; font-weight: 700; cursor: pointer; margin-bottom: 20px;
        box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4); transition: transform 0.2s;
        display: flex; align-items: center; gap: 8px; z-index: 10;
    }

    .report-canvas {
        width: 100%; max-width: 600px; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif;
        background: linear-gradient(145deg, #0c0a12 0%, #161022 100%);
        border-radius: 24px; padding: 30px; color: #fff;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        position: relative; overflow: hidden;
    }

    .rc-glow {
        position: absolute; width: 200px; height: 200px;
        background: #e94560; filter: blur(100px); opacity: 0.3;
        top: -50px; left: -50px; border-radius: 50%; z-index: 0;
    }
    .rc-glow.right { background: #0f3460; top: auto; bottom: -50px; right: -50px; left: auto; }
    .rc-content { position: relative; z-index: 1; }

    .rc-header { text-align: center; margin-bottom: 30px; }
    .rc-logo-box {
        display: inline-flex; align-items: center; justify-content: center;
        background: #ffffff; width: 90px; height: 90px; border-radius: 50%;
        margin-bottom: 15px; box-shadow: 0 10px 25px rgba(233, 69, 96, 0.5);
        border: 3px solid #e94560; padding: 5px; overflow: hidden;
    }
    .rc-logo-box img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; }
    .rc-header h1 { font-size: 1.6rem; font-weight: 900; letter-spacing: 1px; margin-bottom: 5px; }
    .rc-header p { color: #8e8e93; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; }

    .rc-student-info {
        display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
        background: rgba(255, 255, 255, 0.03); border-radius: 16px; padding: 20px;
        margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .rc-info-item p { color: #8e8e93; font-size: 0.8rem; margin-bottom: 3px; }
    .rc-info-item h4 { font-size: 1.1rem; font-weight: 700; color: #fff; margin:0;}

    .rc-grades-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .rc-grade-card {
        background: rgba(255, 255, 255, 0.03); border-radius: 16px; padding: 15px;
        text-align: center; border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .rc-grade-card p { color: #8e8e93; font-size: 0.75rem; font-weight: 500; margin-bottom: 8px; text-transform: uppercase;}
    .rc-grade-card h2 { font-size: 1.5rem; font-weight: 900; margin:0; }
    .rc-grade-card h2 span { font-size: 0.9rem; color: #8e8e93; font-weight: 500; }

    .rc-highlight-card {
        grid-column: span 3; display: flex; justify-content: space-between; align-items: center;
        background: linear-gradient(135deg, rgba(233, 69, 96, 0.2), rgba(15, 52, 96, 0.2));
        border: 1px solid rgba(233, 69, 96, 0.3); padding: 20px; border-radius: 16px;
    }
    .rc-final-score { font-size: 2.5rem; font-weight: 900; color: #e94560; text-shadow: 0 0 20px rgba(233, 69, 96, 0.5); }

    .rc-stats-section { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
    .rc-stats-card {
        background: rgba(255, 255, 255, 0.03); border-radius: 16px; padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.05); display: flex; flex-direction: column;
    }
    .rc-stats-card h3 { font-size: 1rem; color: #fff; margin-bottom: 20px; text-align: center;}

    /* ساختار ستون‌های چارت */
    .rc-chart-area { display: flex; align-items: flex-end; justify-content: space-between; gap: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); margin: 0 5px; }
    .rc-chart-col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; }
    .rc-chart-val { font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; color: #fff;}
    .rc-chart-track { width: 100%; height: 90px; display: flex; align-items: flex-end; }
    .rc-chart-fill { width: 100%; background: #0f3460; border-radius: 4px 4px 0 0; }
    .rc-chart-fill.current { background: linear-gradient(180deg, #e94560 0%, #0f3460 100%); box-shadow: 0 0 15px rgba(233,69,96,0.3); }
    .rc-chart-labels { display: flex; justify-content: space-between; gap: 15px; margin: 8px 5px 0 5px; }
    .rc-chart-label { flex: 1; text-align: center; font-size: 0.75rem; color: #8e8e93; font-weight: 500;}

    /* ساختار رینگ گرافیکی روانشناسی مایندلینگو */
    .rc-ml-ring { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; }
    .rc-ml-inner { width: 90px; height: 90px; border-radius: 50%; background: #12121C; display: flex; align-items: center; justify-content: center; }
    
    .rc-footer {
        display: flex; justify-content: space-between; align-items: center;
        border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 20px;
    }
    .rc-verified {
        background: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 8px 15px;
        border-radius: 30px; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 6px;
        border: 1px solid rgba(46, 204, 113, 0.2);
    }
</style>

<div class="modal fade" id="reportCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                
                <button class="download-btn" onclick="downloadReport()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                        <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                    </svg>
                    Save as Image
                </button>

                <div class="report-canvas" id="export-target">
                    <div class="rc-glow"></div>
                    <div class="rc-glow right"></div>

                    <div class="rc-content">
                        
                        <div class="rc-header">
                            <div class="rc-logo-box">
                                <img src="../../assets/images/2000-2000.jpg" alt="Ghazal Academy Logo" id="academy-logo">
                            </div>
                            <h1>GHAZAL ACADEMY</h1>
                            <p>Term Performance Report</p>
                        </div>

                        <div class="rc-student-info">
                            <div class="rc-info-item">
                                <p>Learner</p>
                                <h4 id="rc-student-name"></h4>
                            </div>
                            <div class="rc-info-item">
                                <p>Class / Level</p>
                                <h4 id="rc-class-name"></h4>
                            </div>
                            <div class="rc-info-item">
                                <p>Student ID</p>
                                <h4 id="rc-student-id"></h4>
                            </div>
                            <div class="rc-info-item">
                                <p>Instructor</p>
                                <h4 id="rc-instructor"></h4>
                            </div>
                        </div>

                        <div class="rc-grades-grid">
                            <div class="rc-grade-card">
                                <p>Quiz Total</p>
                                <h2 id="rc-quiz">0 <span>/ 66</span></h2>
                            </div>
                            <div class="rc-grade-card">
                                <p>Class Activity</p>
                                <h2 id="rc-activity">0 <span>/ 30</span></h2>
                            </div>
                            <div class="rc-grade-card">
                                <p>Presence</p>
                                <h2 id="rc-presence">0 <span>/ 4</span></h2>
                            </div>
                            
                            <div class="rc-highlight-card">
                                <div style="text-align: left;">
                                    <p style="color: #e94560; margin-bottom: 5px; font-size: 0.8rem; font-weight: 700;">SUPERVISOR MARK</p>
                                    <h2 style="font-size: 1.8rem;" id="rc-sup">0 <span style="font-size: 1rem; color: #fff;">/ 100</span></h2>
                                </div>
                                <div style="text-align: right;">
                                    <p style="color: #e94560; margin-bottom: 5px; font-size: 0.8rem; font-weight: 700;">FINAL SCORE</p>
                                    <div class="rc-final-score" id="rc-final">0</div>
                                </div>
                            </div>
                        </div>

                        <div class="rc-stats-section">
                            <div class="rc-stats-card">
                                <h3>📈 Term Progress</h3>
                                <div class="rc-chart-area">
                                    <div class="rc-chart-col">
                                        <span class="rc-chart-val">82</span>
                                        <div class="rc-chart-track"><div class="rc-chart-fill" style="height: 82%;"></div></div>
                                    </div>
                                    <div class="rc-chart-col">
                                        <span class="rc-chart-val">88</span>
                                        <div class="rc-chart-track"><div class="rc-chart-fill" style="height: 88%;"></div></div>
                                    </div>
                                    <div class="rc-chart-col">
                                        <span class="rc-chart-val" id="rc-chart-current-val">0</span>
                                        <div class="rc-chart-track"><div class="rc-chart-fill current" id="rc-chart-current-fill" style="height: 0%;"></div></div>
                                    </div>
                                </div>
                                <div class="rc-chart-labels">
                                    <span class="rc-chart-label">Term -2</span>
                                    <span class="rc-chart-label">Term -1</span>
                                    <span class="rc-chart-label">Current</span>
                                </div>
                            </div>

                            <div class="rc-stats-card" style="align-items: center; justify-content: center;">
                                <h3 style="margin-bottom: 5px;">🧠 Mind Lingo</h3>
                                <p style="color: #8e8e93; font-size: 0.75rem; text-align: center; margin-bottom: 15px;">Psychological Assessment</p>
                                
                                <div class="rc-ml-ring" id="rc-ml-ring">
                                    <div class="rc-ml-inner">
                                        <span id="rc-ml-text" style="font-weight: 800; font-size: 0.75rem; letter-spacing: 1px; text-transform: uppercase;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rc-footer">
                            <div class="date">
                                <p style="margin:0;">Issued Date</p>
                                <h5 style="margin:0; color:#fff;"><?= date("F j, Y") ?></h5>
                            </div>
                            <div class="rc-verified">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                                </svg>
                                Officially Verified
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    function openReportModal(data) {
        // پر کردن فیلدهای اطلاعات فردی و نمرات به صورت داینامیک
        document.getElementById('rc-student-name').innerText = data.student_name;
        document.getElementById('rc-student-id').innerText = data.student_id;
        document.getElementById('rc-class-name').innerText = data.class_name;
        document.getElementById('rc-instructor').innerText = data.teacher_name;
        
        document.getElementById('rc-quiz').innerHTML = data.quiz_total + ' <span>/ 66</span>';
        document.getElementById('rc-activity').innerHTML = data.class_activity + ' <span>/ 30</span>';
        document.getElementById('rc-presence').innerHTML = data.presence + ' <span>/ 4</span>';
        
        document.getElementById('rc-sup').innerHTML = data.sup_score + ' <span style="font-size: 1rem; color: #fff;">/ 100</span>';
        document.getElementById('rc-final').innerText = data.final_score;

        // تنظیم داینامیک ارتفاع ستون ترم جاری در نمودار پیشرفت
        document.getElementById('rc-chart-current-val').innerText = data.final_score;
        document.getElementById('rc-chart-current-fill').style.height = data.final_score + '%';

        // پیاده‌سازی منطق سیستم توصیفی و رینگ رنگی Mind Lingo
        let mlScore = data.ml_score;
        let mlDesc = 'PENDING';
        let mlColor = '#6c757d';

        if (mlScore > 0) {
            if (mlScore <= 15) { mlDesc = 'EMERGING'; mlColor = '#e74c3c'; }
            else if (mlScore <= 25) { mlDesc = 'DEVELOPING'; mlColor = '#f39c12'; }
            else if (mlScore <= 34) { mlDesc = 'PROGRESSING'; mlColor = '#3498db'; }
            else { mlDesc = 'STRONG'; mlColor = '#2ecc71'; }
        }

        let percentage = (mlScore / 40) * 100;
        document.getElementById('rc-ml-ring').style.background = `conic-gradient(${mlColor} ${percentage}%, #0f3460 0)`;
        
        const mlTextElement = document.getElementById('rc-ml-text');
        mlTextElement.innerText = mlDesc;
        mlTextElement.style.color = mlColor;

        // فراخوانی و نمایش بوتیسترپ مودال
        new bootstrap.Modal(document.getElementById('reportCardModal')).show();
    }

    function downloadReport() {
        const btn = document.querySelector('.download-btn');
        btn.innerHTML = 'Processing...';
        btn.disabled = true;

        const element = document.getElementById('export-target');
        
        // اطمینان از هندل شدن CORS لوگو برای اکتیو شدن متد html2canvas روی هاست
        const img = document.getElementById('academy-logo');
        if(img && !img.src.startsWith('data:')) {
           img.crossOrigin = "Anonymous";
        }
        
        html2canvas(element, {
            scale: 2, 
            backgroundColor: '#0f0c29',
            useCORS: true,
            allowTaint: true
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Ghazal_Academy_Report.jpg';
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            link.click();
            
            btn.innerHTML = 'Save as Image';
            btn.disabled = false;
        }).catch(err => {
            console.error("Error generating image:", err);
            alert("خطا در ایجاد تصویر. لطفاً مجددا تلاش کنید.");
            btn.innerHTML = 'Save as Image';
            btn.disabled = false;
        });
    }
</script>

<?php require_once 'layout-end.php'; ?>