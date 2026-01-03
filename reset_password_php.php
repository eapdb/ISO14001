<?php
/**
 * 重置管理員密碼工具
 * 使用方式: 
 * 1. 將此檔案上傳到與 config.php 同一目錄
 * 2. 訪問 http://your-domain/iso14001/reset_admin_password.php
 * 3. 重置完成後立即刪除此檔案！
 */

// 引入設定檔
require_once 'config.php';

// 設定新密碼（預設為 admin）
$new_password = 'admin';
$username = 'admin';

// 使用 bcrypt 加密密碼
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<!DOCTYPE html>";
echo "<html lang='zh-TW'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>重置管理員密碼</title>";
echo "<style>
    body {
        font-family: 'Microsoft JhengHei', Arial, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .container {
        background: white;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        max-width: 600px;
    }
    h1 {
        color: #2c3e50;
        margin-bottom: 20px;
    }
    .success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 6px;
        margin: 20px 0;
        border: 1px solid #c3e6cb;
    }
    .error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 6px;
        margin: 20px 0;
        border: 1px solid #f5c6cb;
    }
    .info {
        background: #d1ecf1;
        color: #0c5460;
        padding: 15px;
        border-radius: 6px;
        margin: 20px 0;
        border: 1px solid #bee5eb;
    }
    .warning {
        background: #fff3cd;
        color: #856404;
        padding: 15px;
        border-radius: 6px;
        margin: 20px 0;
        border: 1px solid #ffeaa7;
    }
    code {
        background: #f8f9fa;
        padding: 2px 8px;
        border-radius: 3px;
        font-family: monospace;
    }
    .btn {
        display: inline-block;
        padding: 12px 24px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        margin-top: 20px;
    }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>🔐 重置管理員密碼</h1>";

try {
    // 檢查使用者是否存在
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // 使用者存在，更新密碼
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmt->bind_param("ss", $hashed_password, $username);
        
        if ($stmt->execute()) {
            echo "<div class='success'>";
            echo "<strong>✅ 密碼重置成功！</strong><br><br>";
            echo "帳號: <code>admin</code><br>";
            echo "密碼: <code>admin</code><br>";
            echo "</div>";
            
            echo "<div class='info'>";
            echo "<strong>下一步：</strong><br>";
            echo "1. 訪問 <a href='login.php'>登入頁面</a><br>";
            echo "2. 使用上述帳號密碼登入<br>";
            echo "3. 登入後立即修改密碼<br>";
            echo "</div>";
            
            echo "<div class='warning'>";
            echo "<strong>⚠️ 重要安全提示：</strong><br>";
            echo "請立即刪除此檔案 (reset_admin_password.php)！<br>";
            echo "此檔案存在會造成安全風險。";
            echo "</div>";
            
            // 顯示加密後的密碼（供驗證）
            echo "<details style='margin-top: 20px;'>";
            echo "<summary style='cursor: pointer; color: #7f8c8d;'>顯示技術資訊</summary>";
            echo "<div style='margin-top: 10px; font-size: 12px; color: #7f8c8d;'>";
            echo "加密後的密碼: <code style='word-break: break-all;'>$hashed_password</code><br>";
            echo "加密方式: bcrypt (PASSWORD_DEFAULT)<br>";
            echo "資料庫連線: 正常";
            echo "</div>";
            echo "</details>";
            
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ 更新失敗</strong><br>";
            echo "錯誤訊息: " . $stmt->error;
            echo "</div>";
        }
        
        $stmt->close();
        
    } else {
        // 使用者不存在，新增使用者
        echo "<div class='info'>找不到 admin 使用者，正在建立...</div>";
        
        $insert_stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $insert_stmt->bind_param("ss", $username, $hashed_password);
        
        if ($insert_stmt->execute()) {
            echo "<div class='success'>";
            echo "<strong>✅ 管理員帳號建立成功！</strong><br><br>";
            echo "帳號: <code>admin</code><br>";
            echo "密碼: <code>admin</code><br>";
            echo "</div>";
            
            echo "<div class='warning'>";
            echo "<strong>⚠️ 請立即刪除此檔案並登入系統修改密碼！</strong>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ 建立失敗</strong><br>";
            echo "錯誤訊息: " . $insert_stmt->error;
            echo "</div>";
        }
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
    echo "<a href='login.php' class='btn'>前往登入頁面</a>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ 系統錯誤</strong><br>";
    echo "錯誤訊息: " . $e->getMessage();
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>請檢查：</strong><br>";
    echo "1. 資料庫連線設定是否正確 (config.php)<br>";
    echo "2. 資料庫 iso14001_system 是否存在<br>";
    echo "3. users 資料表是否已建立";
    echo "</div>";
}

$conn->close();

echo "</div>";
echo "</body>";
echo "</html>";
?>
