<?php
require_once 'config.php';
check_login();

// 統計資料
$total_assessments = $conn->query("SELECT COUNT(*) as count FROM regulation_assessments")->fetch_assoc()['count'];
$draft_count = $conn->query("SELECT COUNT(*) as count FROM regulation_assessments WHERE status = 'draft'")->fetch_assoc()['count'];
$review_count = $conn->query("SELECT COUNT(*) as count FROM regulation_assessments WHERE status = 'in_review'")->fetch_assoc()['count'];
$completed_count = $conn->query("SELECT COUNT(*) as count FROM regulation_assessments WHERE status = 'completed'")->fetch_assoc()['count'];

// 最近的評估
$recent_query = "SELECT ra.*, u.username 
                 FROM regulation_assessments ra 
                 LEFT JOIN users u ON ra.created_by = u.id 
                 ORDER BY ra.created_at DESC 
                 LIMIT 5";
$recent_assessments = $conn->query($recent_query);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>主控台 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
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
            <h2>系統儀表板</h2>
            
            <?php echo display_messages(); ?>
            
            <div class="dashboard-grid">
                <div class="stat-card">
                    <h4>總評估數</h4>
                    <div class="number"><?php echo $total_assessments; ?></div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                    <h4>草稿</h4>
                    <div class="number"><?php echo $draft_count; ?></div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <h4>審核中</h4>
                    <div class="number"><?php echo $review_count; ?></div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
                    <h4>已完成</h4>
                    <div class="number"><?php echo $completed_count; ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>快速操作</h3>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        <a href="assessment_form.php" class="btn btn-primary">
                            新增法規評估
                        </a>
                        <a href="assessment_list.php" class="btn btn-secondary">
                            瀏覽所有評估
                        </a>
                        <a href="consultation.php" class="btn btn-success">
                            管理公開諮詢
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>最近的評估項目</h3>
                </div>
                <div class="card-body">
                    <?php if ($recent_assessments->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>法規名稱</th>
                                        <th>法規編號</th>
                                        <th>評估日期</th>
                                        <th>狀態</th>
                                        <th>建立者</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $recent_assessments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['regulation_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['regulation_number'] ?: '-'); ?></td>
                                            <td><?php echo format_date($row['assessment_date']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $row['status'] === 'draft' ? 'draft' : ($row['status'] === 'in_review' ? 'review' : 'completed'); ?>">
                                                    <?php echo translate_status($row['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['username'] ?: '-'); ?></td>
                                            <td>
                                                <a href="assessment_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                                    查看
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #7f8c8d; text-align: center; padding: 20px;">尚無評估記錄</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>系統說明</h3>
                </div>
                <div class="card-body">
                    <p style="line-height: 1.8; color: #34495e;">
                        本系統依據 ISO 14001 環境管理系統標準開發，提供完整的法規變更衝擊影響評估功能。
                        系統涵蓋必要性分析、替代方案評估、利害關係者分析、成本效益分析，
                        並透過公開諮詢程序全面評估政策對經濟、社會與環境之影響，
                        確保法規制定的合理性與正當性。
                    </p>
                    <div style="margin-top: 15px; padding: 15px; background: #ecf0f1; border-radius: 6px;">
                        <strong>主要功能：</strong>
                        <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8;">
                            <li>法規變更評估管理</li>
                            <li>必要性分析與替代方案評估</li>
                            <li>利害關係者影響評估</li>
                            <li>成本效益分析</li>
                            <li>經濟、社會、環境影響評估</li>
                            <li>公開諮詢程序管理</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
