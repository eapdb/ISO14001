<?php
require_once 'config.php';
check_login();

$assessment_id = $_GET['assessment_id'] ?? null;
$csrf_token = generate_csrf_token();

// 處理新增諮詢
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_consultation') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $aid = (int)$_POST['assessment_id'];
        $title = clean_input($_POST['consultation_title']);
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $method = clean_input($_POST['consultation_method']);
        $summary = clean_input($_POST['summary']);
        
        $stmt = $conn->prepare("INSERT INTO public_consultations (assessment_id, consultation_title, start_date, end_date, consultation_method, summary) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $aid, $title, $start, $end, $method, $summary);
        
        if ($stmt->execute()) {
            set_success_message('諮詢已成功建立');
        } else {
            set_error_message('建立失敗');
        }
        $stmt->close();
    }
}

// 處理新增意見
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_feedback') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $cid = (int)$_POST['consultation_id'];
        $submitter = clean_input($_POST['submitter_name']);
        $org = clean_input($_POST['submitter_organization']);
        $feedback = clean_input($_POST['feedback_content']);
        $response = clean_input($_POST['response']);
        
        $stmt = $conn->prepare("INSERT INTO consultation_feedback (consultation_id, submitter_name, submitter_organization, feedback_content, response) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $cid, $submitter, $org, $feedback, $response);
        
        if ($stmt->execute()) {
            // 更新參與人數
            $conn->query("UPDATE public_consultations SET participant_count = participant_count + 1 WHERE id = $cid");
            set_success_message('意見已成功記錄');
        } else {
            set_error_message('記錄失敗');
        }
        $stmt->close();
    }
}

// 處理刪除諮詢
if (isset($_GET['delete_consultation']) && is_numeric($_GET['delete_consultation'])) {
    $delete_id = (int)$_GET['delete_consultation'];
    $stmt = $conn->prepare("DELETE FROM public_consultations WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        set_success_message('諮詢已刪除');
    }
    $stmt->close();
    header('Location: consultation.php');
    exit();
}

// 取得所有評估清單（用於下拉選單）
$assessments = $conn->query("SELECT id, regulation_name FROM regulation_assessments ORDER BY created_at DESC");

// 取得諮詢清單
$consultation_query = "SELECT pc.*, ra.regulation_name 
                       FROM public_consultations pc 
                       LEFT JOIN regulation_assessments ra ON pc.assessment_id = ra.id";
if ($assessment_id && is_numeric($assessment_id)) {
    $consultation_query .= " WHERE pc.assessment_id = " . (int)$assessment_id;
}
$consultation_query .= " ORDER BY pc.start_date DESC";
$consultations = $conn->query($consultation_query);

// 取得選定諮詢的意見（如果有）
$selected_consultation_id = $_GET['consultation_id'] ?? null;
$feedbacks = null;
if ($selected_consultation_id && is_numeric($selected_consultation_id)) {
    $feedbacks = $conn->query("SELECT * FROM consultation_feedback WHERE consultation_id = " . (int)$selected_consultation_id . " ORDER BY submitted_at DESC");
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公開諮詢管理 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function confirmDelete(id, title) {
            if (confirm('確定要刪除「' + title + '」嗎？')) {
                window.location.href = 'consultation.php?delete_consultation=' + id;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1><?php echo SITE_NAME; ?></h1>
            <div class="nav-links">
                <a href="index.php">主控台</a>
                <a href="assessment_list.php">評估清單</a>
                <a href="consultation.php">公開諮詢</a>
                <a href="change_password.php">修改密碼</a>
                <a href="logout.php">登出</a>
            </div>
            <div class="user-info">
                <span>使用者: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </nav>
        
        <div class="content">
            <h2>公開諮詢程序管理</h2>
            
            <?php echo display_messages(); ?>
            
            <!-- 新增諮詢表單 -->
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>新增公開諮詢</h3>
                        <button onclick="toggleForm('add-consultation-form')" class="btn btn-primary">顯示/隱藏表單</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="add-consultation-form" style="display: none;">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add_consultation">
                            
                            <div class="form-group">
                                <label>關聯法規評估 *</label>
                                <select name="assessment_id" required>
                                    <option value="">請選擇...</option>
                                    <?php while ($a = $assessments->fetch_assoc()): ?>
                                    <option value="<?php echo $a['id']; ?>" <?php echo $assessment_id == $a['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($a['regulation_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>諮詢標題 *</label>
                                <input type="text" name="consultation_title" required>
                            </div>
                            
                            <div class="form-group">
                                <label>開始日期 *</label>
                                <input type="date" name="start_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label>結束日期 *</label>
                                <input type="date" name="end_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label>諮詢方式 *</label>
                                <input type="text" name="consultation_method" required placeholder="例如：公聽會、線上問卷、書面意見">
                            </div>
                            
                            <div class="form-group">
                                <label>摘要</label>
                                <textarea name="summary" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">建立諮詢</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 諮詢清單 -->
            <div class="card">
                <div class="card-header">
                    <h3>諮詢清單</h3>
                </div>
                <div class="card-body">
                    <?php if ($consultations->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>標題</th>
                                        <th>關聯法規</th>
                                        <th>諮詢期間</th>
                                        <th>方式</th>
                                        <th>參與人數</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $consultations->data_seek(0);
                                    while ($cons = $consultations->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cons['consultation_title']); ?></td>
                                        <td><?php echo htmlspecialchars($cons['regulation_name']); ?></td>
                                        <td><?php echo format_date($cons['start_date']) . ' ~ ' . format_date($cons['end_date']); ?></td>
                                        <td><?php echo htmlspecialchars($cons['consultation_method']); ?></td>
                                        <td><?php echo $cons['participant_count']; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="consultation.php?consultation_id=<?php echo $cons['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                                    查看意見
                                                </a>
                                                <button onclick="confirmDelete(<?php echo $cons['id']; ?>, '<?php echo htmlspecialchars($cons['consultation_title'], ENT_QUOTES); ?>')" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                                    刪除
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; padding: 40px; color: #7f8c8d;">尚無諮詢記錄</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 意見管理 -->
            <?php if ($selected_consultation_id): ?>
            <div class="card">
                <div class="card-header">
                    <h3>諮詢意見記錄</h3>
                </div>
                <div class="card-body">
                    <button onclick="toggleForm('add-feedback-form')" class="btn btn-success" style="margin-bottom: 20px;">新增意見</button>
                    
                    <div id="add-feedback-form" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                        <h4>新增諮詢意見</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add_feedback">
                            <input type="hidden" name="consultation_id" value="<?php echo $selected_consultation_id; ?>">
                            
                            <div class="form-group">
                                <label>提交者姓名 *</label>
                                <input type="text" name="submitter_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label>所屬組織</label>
                                <input type="text" name="submitter_organization">
                            </div>
                            
                            <div class="form-group">
                                <label>意見內容 *</label>
                                <textarea name="feedback_content" rows="4" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>回應說明</label>
                                <textarea name="response" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">提交意見</button>
                        </form>
                    </div>
                    
                    <?php if ($feedbacks && $feedbacks->num_rows > 0): ?>
                        <?php while ($fb = $feedbacks->fetch_assoc()): ?>
                        <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 6px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <div>
                                    <strong><?php echo htmlspecialchars($fb['submitter_name']); ?></strong>
                                    <?php if (!empty($fb['submitter_organization'])): ?>
                                        <span style="color: #7f8c8d;">（<?php echo htmlspecialchars($fb['submitter_organization']); ?>）</span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #7f8c8d; font-size: 14px;">
                                    <?php echo format_datetime($fb['submitted_at']); ?>
                                </div>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 10px;">
                                <strong style="display: block; margin-bottom: 8px; color: #2c3e50;">意見內容：</strong>
                                <p style="line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($fb['feedback_content'])); ?></p>
                            </div>
                            
                            <?php if (!empty($fb['response'])): ?>
                            <div style="background: #e8f5e9; padding: 15px; border-radius: 4px; border-left: 4px solid #27ae60;">
                                <strong style="display: block; margin-bottom: 8px; color: #27ae60;">回應說明：</strong>
                                <p style="line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($fb['response'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 20px; color: #7f8c8d;">此諮詢尚無意見記錄</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="assessment_list.php" class="btn btn-secondary">返回評估清單</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
