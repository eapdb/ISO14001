<?php
require_once 'config.php';
check_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_error_message('無效的評估ID');
    header('Location: assessment_list.php');
    exit();
}

$assessment_id = (int)$_GET['id'];

// 取得基本資料
$stmt = $conn->prepare("SELECT ra.*, u.username FROM regulation_assessments ra LEFT JOIN users u ON ra.created_by = u.id WHERE ra.id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();
$assessment = $result->fetch_assoc();
$stmt->close();

if (!$assessment) {
    set_error_message('評估不存在');
    header('Location: assessment_list.php');
    exit();
}

// 取得必要性分析
$necessity = $conn->query("SELECT * FROM necessity_analysis WHERE assessment_id = $assessment_id")->fetch_assoc();

// 取得替代方案
$alternatives = $conn->query("SELECT * FROM alternatives WHERE assessment_id = $assessment_id ORDER BY id");

// 取得利害關係者
$stakeholders = $conn->query("SELECT * FROM stakeholder_assessment WHERE assessment_id = $assessment_id ORDER BY id");

// 取得成本效益
$cost_benefits = $conn->query("SELECT * FROM cost_benefit_analysis WHERE assessment_id = $assessment_id ORDER BY item_type, id");

// 取得影響評估
$impacts = $conn->query("SELECT * FROM impact_evaluation WHERE assessment_id = $assessment_id ORDER BY impact_type, id");

// 取得諮詢資料
$consultations = $conn->query("SELECT * FROM public_consultations WHERE assessment_id = $assessment_id ORDER BY id");

// 計算成本效益總計
$cost_total = $conn->query("SELECT SUM(amount) as total FROM cost_benefit_analysis WHERE assessment_id = $assessment_id AND item_type = 'cost'")->fetch_assoc()['total'] ?? 0;
$benefit_total = $conn->query("SELECT SUM(amount) as total FROM cost_benefit_analysis WHERE assessment_id = $assessment_id AND item_type = 'benefit'")->fetch_assoc()['total'] ?? 0;
$net_benefit = $benefit_total - $cost_total;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>評估詳情 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .info-row { display: flex; margin-bottom: 15px; border-bottom: 1px solid #ecf0f1; padding-bottom: 10px; }
        .info-label { font-weight: 600; color: #2c3e50; width: 150px; flex-shrink: 0; }
        .info-value { color: #34495e; flex: 1; }
        .summary-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .summary-label { font-weight: 600; }
        .summary-value { font-size: 18px; font-weight: bold; }
        .print-btn { float: right; }
        @media print {
            .navbar, .action-buttons, .print-btn, .footer { display: none; }
            .card { page-break-inside: avoid; }
        }
    </style>
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
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>法規評估詳細資料</h2>
                <button onclick="window.print()" class="btn btn-secondary print-btn">列印報告</button>
            </div>
            
            <?php echo display_messages(); ?>
            
            <!-- 基本資料 -->
            <div class="card">
                <div class="card-header">
                    <h3>基本資料</h3>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <div class="info-label">法規名稱:</div>
                        <div class="info-value"><?php echo htmlspecialchars($assessment['regulation_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">法規編號:</div>
                        <div class="info-value"><?php echo htmlspecialchars($assessment['regulation_number'] ?: '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">生效日期:</div>
                        <div class="info-value"><?php echo format_date($assessment['effective_date']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">評估日期:</div>
                        <div class="info-value"><?php echo format_date($assessment['assessment_date']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">狀態:</div>
                        <div class="info-value">
                            <span class="badge badge-<?php echo $assessment['status'] === 'draft' ? 'draft' : ($assessment['status'] === 'in_review' ? 'review' : 'completed'); ?>">
                                <?php echo translate_status($assessment['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">建立者:</div>
                        <div class="info-value"><?php echo htmlspecialchars($assessment['username'] ?: '-'); ?></div>
                    </div>
                    <div class="info-row" style="border: none;">
                        <div class="info-label">建立時間:</div>
                        <div class="info-value"><?php echo format_datetime($assessment['created_at']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- 必要性分析 -->
            <?php if ($necessity): ?>
            <div class="card">
                <div class="card-header">
                    <h3>必要性分析</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($necessity['problem_description'])): ?>
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 10px;">問題描述</h4>
                        <p style="line-height: 1.8; color: #34495e;"><?php echo nl2br(htmlspecialchars($necessity['problem_description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($necessity['legal_basis'])): ?>
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 10px;">法律依據</h4>
                        <p style="line-height: 1.8; color: #34495e;"><?php echo nl2br(htmlspecialchars($necessity['legal_basis'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($necessity['necessity_justification'])): ?>
                    <div>
                        <h4 style="color: #2c3e50; margin-bottom: 10px;">必要性論證</h4>
                        <p style="line-height: 1.8; color: #34495e;"><?php echo nl2br(htmlspecialchars($necessity['necessity_justification'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 替代方案 -->
            <?php if ($alternatives->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>替代方案評估</h3>
                </div>
                <div class="card-body">
                    <?php $alt_num = 1; while ($alt = $alternatives->fetch_assoc()): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <h4 style="color: #2c3e50; margin-bottom: 10px;">方案 <?php echo $alt_num++; ?>: <?php echo htmlspecialchars($alt['alternative_name']); ?></h4>
                        <?php if (!empty($alt['description'])): ?>
                        <p style="margin-bottom: 10px;"><strong>說明:</strong> <?php echo nl2br(htmlspecialchars($alt['description'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($alt['advantages'])): ?>
                        <p style="margin-bottom: 10px;"><strong>優點:</strong> <?php echo nl2br(htmlspecialchars($alt['advantages'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($alt['disadvantages'])): ?>
                        <p style="margin-bottom: 10px;"><strong>缺點:</strong> <?php echo nl2br(htmlspecialchars($alt['disadvantages'])); ?></p>
                        <?php endif; ?>
                        <p><strong>可行性評分:</strong> <?php echo $alt['feasibility_score']; ?> / 5</p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 利害關係者 -->
            <?php if ($stakeholders->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>利害關係者評估</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>名稱</th>
                                    <th>類型</th>
                                    <th>影響描述</th>
                                    <th>影響程度</th>
                                    <th>減緩措施</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($sh = $stakeholders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sh['stakeholder_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sh['stakeholder_type']); ?></td>
                                    <td><?php echo htmlspecialchars($sh['impact_description']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $sh['impact_level']; ?>">
                                            <?php echo translate_impact_level($sh['impact_level']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($sh['mitigation_measures']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 成本效益分析 -->
            <?php if ($cost_benefits->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>成本效益分析</h3>
                </div>
                <div class="card-body">
                    <div class="summary-box">
                        <div class="summary-item">
                            <span class="summary-label">總成本:</span>
                            <span class="summary-value" style="color: #e74c3c;">NT$ <?php echo number_format($cost_total, 2); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">總效益:</span>
                            <span class="summary-value" style="color: #27ae60;">NT$ <?php echo number_format($benefit_total, 2); ?></span>
                        </div>
                        <div class="summary-item" style="border-top: 2px solid #2c3e50; padding-top: 10px; margin-top: 10px;">
                            <span class="summary-label">淨效益:</span>
                            <span class="summary-value" style="color: <?php echo $net_benefit >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                                NT$ <?php echo number_format($net_benefit, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>項目名稱</th>
                                    <th>類型</th>
                                    <th>類別</th>
                                    <th>金額</th>
                                    <th>說明</th>
                                    <th>計算依據</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $cost_benefits->data_seek(0);
                                while ($cb = $cost_benefits->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cb['item_name']); ?></td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $cb['item_type'] === 'cost' ? '#e74c3c' : '#27ae60'; ?>; color: white;">
                                            <?php echo $cb['item_type'] === 'cost' ? '成本' : '效益'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($cb['category']); ?></td>
                                    <td>NT$ <?php echo number_format($cb['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($cb['description']); ?></td>
                                    <td><?php echo htmlspecialchars($cb['calculation_basis']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 影響評估 -->
            <?php if ($impacts->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>經濟、社會與環境影響評估</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>影響類型</th>
                                    <th>影響描述</th>
                                    <th>嚴重程度</th>
                                    <th>證據資料</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($imp = $impacts->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge" style="background: #3498db; color: white;">
                                            <?php echo translate_impact_type($imp['impact_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($imp['impact_description']); ?></td>
                                    <td><?php echo translate_severity($imp['severity_level']); ?></td>
                                    <td><?php echo htmlspecialchars($imp['evidence']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 公開諮詢 -->
            <?php if ($consultations->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>公開諮詢記錄</h3>
                </div>
                <div class="card-body">
                    <?php while ($cons = $consultations->fetch_assoc()): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <h4 style="color: #2c3e50; margin-bottom: 10px;"><?php echo htmlspecialchars($cons['consultation_title']); ?></h4>
                        <p><strong>諮詢期間:</strong> <?php echo format_date($cons['start_date']); ?> 至 <?php echo format_date($cons['end_date']); ?></p>
                        <p><strong>諮詢方式:</strong> <?php echo htmlspecialchars($cons['consultation_method']); ?></p>
                        <p><strong>參與人數:</strong> <?php echo $cons['participant_count']; ?> 人</p>
                        <?php if (!empty($cons['summary'])): ?>
                        <p><strong>摘要:</strong> <?php echo nl2br(htmlspecialchars($cons['summary'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 操作按鈕 -->
            <div class="action-buttons">
                <a href="assessment_form.php?id=<?php echo $assessment_id; ?>" class="btn btn-success">編輯評估</a>
                <a href="consultation.php?assessment_id=<?php echo $assessment_id; ?>" class="btn btn-primary">管理諮詢</a>
                <a href="assessment_list.php" class="btn btn-secondary">返回清單</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
