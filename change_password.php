<?php
require_once 'config.php';
check_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = '請填寫所有欄位';
        } elseif ($new_password !== $confirm_password) {
            $error = '新密碼與確認密碼不符';
        } elseif (strlen($new_password) < 6) {
            $error = '新密碼長度至少需要 6 個字元';
        } else {
            // 驗證目前密碼
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $user['password'])) {
                // 更新密碼
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $success = '密碼修改成功';
                } else {
                    $error = '密碼修改失敗，請稍後再試';
                }
                $update_stmt->close();
            } else {
                $error = '目前密碼錯誤';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密碼 - <?php echo SITE_NAME; ?></title>
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
            <h2>修改密碼</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>安全性設定</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label for="current_password">目前密碼</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">新密碼</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   required
                                   minlength="6">
                            <small>密碼長度至少 6 個字元</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">確認新密碼</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   minlength="6">
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">更新密碼</button>
                            <a href="index.php" class="btn btn-secondary">取消</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>安全性建議</h3>
                </div>
                <div class="card-body">
                    <ul style="line-height: 2; color: #34495e;">
                        <li>使用至少 8 個字元的強密碼</li>
                        <li>混合使用大小寫字母、數字和特殊符號</li>
                        <li>避免使用容易猜測的密碼（如生日、姓名等）</li>
                        <li>定期更換密碼以提高安全性</li>
                        <li>不要與他人分享您的密碼</li>
                    </ul>
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
