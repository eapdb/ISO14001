<?php
require_once 'config.php';
check_login();

// 處理刪除
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM regulation_assessments WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        set_success_message('評估已成功刪除');
    } else {
        set_error_message('刪除失敗');
    }
    $stmt->close();
    header('Location: assessment_list.php');
    exit();
}

// 取得篩選條件
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// 建立查詢
$query = "SELECT ra.*, u.username 
          FROM regulation_assessments ra 
          LEFT JOIN users u ON ra.created_by = u.id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $query .= " AND ra.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (ra.regulation_name LIKE ? OR ra.regulation_number LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$query .= " ORDER BY ra.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>評估清單 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <script>
        function confirmDelete(id, name) {
            if (confirm('確定要刪除「' + name + '」嗎？\n此操作將同時刪除所有相關評估資料。')) {
                window.location.href = 'assessment_list.php?delete=' + id;
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
            <h2>法規評估清單</h2>
            
            <?php echo display_messages(); ?>
            
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <a href="assessment_form.php" class="btn btn-primary">新增評估</a>
                    </div>
                    
                    <form method="GET" action="" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <input type="text" 
                               name="search" 
                               placeholder="搜尋法規名稱或編號..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        
                        <select name="status" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="">所有狀態</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>草稿</option>
                            <option value="in_review" <?php echo $status_filter === 'in_review' ? 'selected' : ''; ?>>審核中</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>已完成</option>
                        </select>
                        
                        <button type="submit" class="btn btn-secondary">搜尋</button>
                        <a href="assessment_list.php" class="btn btn-secondary">清除</a>
                    </form>
                    
                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>法規名稱</th>
                                        <th>法規編號</th>
                                        <th>生效日期</th>
                                        <th>評估日期</th>
                                        <th>狀態</th>
                                        <th>建立者</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['regulation_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['regulation_number'] ?: '-'); ?></td>
                                            <td><?php echo format_date($row['effective_date']); ?></td>
                                            <td><?php echo format_date($row['assessment_date']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $row['status'] === 'draft' ? 'draft' : ($row['status'] === 'in_review' ? 'review' : 'completed'); ?>">
                                                    <?php echo translate_status($row['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['username'] ?: '-'); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 5px;">
                                                    <a href="assessment_detail.php?id=<?php echo $row['id']; ?>" 
                                                       class="btn btn-secondary" 
                                                       style="padding: 6px 12px; font-size: 12px;">
                                                        查看
                                                    </a>
                                                    <a href="assessment_form.php?id=<?php echo $row['id']; ?>" 
                                                       class="btn btn-success" 
                                                       style="padding: 6px 12px; font-size: 12px;">
                                                        編輯
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['regulation_name'], ENT_QUOTES); ?>')" 
                                                            class="btn btn-danger" 
                                                            style="padding: 6px 12px; font-size: 12px;">
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
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <p style="font-size: 18px; margin-bottom: 10px;">尚無評估記錄</p>
                            <p>點擊「新增評估」開始建立第一筆法規評估</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php 
$stmt->close();
$conn->close(); 
?>
