<?php
/**
 * ISO14001 環境管理系統 - 登入頁面
 * 檔案名稱: login.php
 * 版本: 1.0.0
 * 建立日期: 2026-01-03
 * 
 * 功能說明:
 * - 使用者登入驗證
 * - 密碼加密驗證（bcrypt）
 * - Session 管理
 * - SQL Injection 防護
 * - 防止 Session Fixation 攻擊
 * - 記錄最後登入時間
 */

// 引入設定檔
require_once 'config.php';

// 如果已經登入，直接導向首頁
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// 初始化錯誤訊息
$error = '';

// 處理登入表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 取得表單資料並清理
    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 驗證輸入
    if (empty($username) || empty($password)) {
        $error = '請輸入帳號和密碼';
    } else {
        
        try {
            // 使用 Prepared Statement 查詢使用者（防止 SQL Injection）
            $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 檢查是否找到使用者
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // 使用 password_verify 驗證密碼（bcrypt）
                if (password_verify($password, $user['password'])) {
                    
                    // 登入成功！建立 Session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['login_time'] = time();
                    
                    // 更新最後登入時間
                    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // 重新產生 Session ID（防止 Session Fixation 攻擊）
                    session_regenerate_id(true);
                    
                    // 設定成功訊息
                    set_success_message('登入成功！歡迎使用系統');
                    
                    // 導向主控台
                    header('Location: index.php');
                    exit();
                    
                } else {
                    // 密碼錯誤
                    $error = '帳號或密碼錯誤';
                    
                    // 記錄失敗的登入嘗試（可選）
                    error_log("Failed login attempt for user: $username from IP: " . $_SERVER['REMOTE_ADDR']);
                }
                
            } else {
                // 使用者不存在
                $error = '帳號或密碼錯誤';
                
                // 記錄失敗的登入嘗試（可選）
                error_log("Failed login attempt for non-existent user: $username from IP: " . $_SERVER['REMOTE_ADDR']);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            // 發生錯誤
            $error = '系統錯誤，請稍後再試';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>登入 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 額外的登入頁面樣式 */
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- 登入標題 -->
        <div class="login-header">
            <h2><?php echo SITE_NAME; ?></h2>
            <p>法規變更衝擊影響評估系統</p>
        </div>
        
        <!-- 錯誤訊息顯示 -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- 登入表單 -->
        <form method="POST" action="" autocomplete="off">
            
            <!-- 帳號輸入 -->
            <div class="form-group">
                <label for="username">帳號</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       autofocus
                       autocomplete="username"
                       placeholder="請輸入帳號"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <!-- 密碼輸入 -->
            <div class="form-group">
                <label for="password">密碼</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required
                       autocomplete="current-password"
                       placeholder="請輸入密碼">
            </div>
            
            <!-- 登入按鈕 -->
            <button type="submit" class="btn btn-primary btn-block">
                登入系統
            </button>
            
        </form>
        
        <!-- 預設帳號提示 -->
        <div style="margin-top: 20px; text-align: center; color: #7f8c8d; font-size: 14px;">
            <p><strong>預設帳號資訊</strong></p>
            <p style="margin-top: 8px;">帳號: <code style="background: #f8f9fa; padding: 2px 8px; border-radius: 3px;">admin</code></p>
            <p style="margin-top: 5px;">密碼: <code style="background: #f8f9fa; padding: 2px 8px; border-radius: 3px;">admin</code></p>
            <p style="margin-top: 10px; font-size: 12px; color: #e74c3c;">
                ⚠️ 登入後請立即修改密碼
            </p>
        </div>
        
        <!-- 系統資訊 -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ecf0f1; text-align: center; color: #95a5a6; font-size: 12px;">
            <p>ISO 14001 環境管理系統</p>
            <p style="margin-top: 5px;">Version 1.0.0 &copy; 2026</p>
        </div>
    </div>
    
    <!-- JavaScript: 表單驗證增強 -->
    <script>
        // 表單提交前的客戶端驗證
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            // 檢查是否為空
            if (!username || !password) {
                e.preventDefault();
                alert('請輸入帳號和密碼');
                return false;
            }
            
            // 檢查帳號長度
            if (username.length < 3) {
                e.preventDefault();
                alert('帳號長度至少需要 3 個字元');
                return false;
            }
            
            // 檢查密碼長度
            if (password.length < 4) {
                e.preventDefault();
                alert('密碼長度至少需要 4 個字元');
                return false;
            }
            
            return true;
        });
        
        // 防止重複提交
        let isSubmitting = false;
        document.querySelector('form').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            
            // 更改按鈕文字
            const btn = document.querySelector('button[type="submit"]');
            btn.textContent = '登入中...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
<?php 
// 關閉資料庫連線
$conn->close(); 
?>
