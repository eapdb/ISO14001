<?php
/**
 * 資料庫檢查工具
 * 用於診斷登入問題
 */

require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html lang='zh-TW'>";
echo "<head><meta charset='UTF-8'><title>資料庫檢查</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: white; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 資料庫診斷工具</h1>";

// 1. 檢查資料庫連線
echo "<h2>1. 資料庫連線</h2>";
if ($conn->connect_error) {
    echo "<p class='error'>❌ 連線失敗: " . $conn->connect_error . "</p>";
} else {
    echo "<p class='success'>✅ 資料庫連線成功</p>";
}

// 2. 檢查 users 表是否存在
echo "<h2>2. 檢查 users 資料表</h2>";
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows > 0) {
    echo "<p class='success'>✅ users 資料表存在</p>";
} else {
    echo "<p class='error'>❌ users 資料表不存在！請執行 database.sql</p>";
}

// 3. 檢查 admin 使用者
echo "<h2>3. 檢查 admin 使用者</h2>";
$user_check = $conn->query("SELECT id, username, password, created_at, last_login FROM users WHERE username = 'admin'");
if ($user_check->num_rows > 0) {
    echo "<p class='success'>✅ admin 使用者存在</p>";
    $user = $user_check->fetch_assoc();
    echo "<pre>";
    echo "使用者 ID: " . $user['id'] . "\n";
    echo "帳號: " . $user['username'] . "\n";
    echo "密碼（加密）: " . substr($user['password'], 0, 50) . "...\n";
    echo "建立時間: " . $user['created_at'] . "\n";
    echo "最後登入: " . ($user['last_login'] ?: '尚未登入') . "\n";
    echo "</pre>";
    
    // 4. 測試密碼驗證
    echo "<h2>4. 密碼驗證測試</h2>";
    $test_password = 'admin';
    if (password_verify($test_password, $user['password'])) {
        echo "<p class='success'>✅ 密碼 'admin' 驗證成功！</p>";
        echo "<p class='info'>登入應該可以正常使用。如果還是無法登入，請檢查：</p>";
        echo "<ul>";
        echo "<li>瀏覽器 Cookie 是否啟用</li>";
        echo "<li>Session 目錄是否可寫入</li>";
        echo "<li>config.php 中的 session 設定</li>";
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ 密碼 'admin' 驗證失敗！</p>";
        echo "<p class='info'>密碼可能不正確，請執行 reset_admin_password.php 重置密碼</p>";
        
        // 顯示正確的密碼 hash
        echo "<h3>正確的密碼 Hash 應該是：</h3>";
        $correct_hash = password_hash('admin', PASSWORD_DEFAULT);
        echo "<pre>" . $correct_hash . "</pre>";
        
        echo "<p><strong>修正方式：</strong></p>";
        echo "<p>執行以下 SQL 指令：</p>";
        echo "<pre>UPDATE users SET password = '$correct_hash' WHERE username = 'admin';</pre>";
    }
} else {
    echo "<p class='error'>❌ admin 使用者不存在！</p>";
    echo "<p class='info'>請執行以下 SQL 指令建立管理員：</p>";
    $new_hash = password_hash('admin', PASSWORD_DEFAULT);
    echo "<pre>INSERT INTO users (username, password) VALUES ('admin', '$new_hash');</pre>";
}

// 5. 檢查所有使用者
echo "<h2>5. 所有使用者列表</h2>";
$all_users = $conn->query("SELECT id, username, created_at FROM users");
if ($all_users->num_rows > 0) {
    echo "<pre>";
    printf("%-5s %-20s %-20s\n", "ID", "帳號", "建立時間");
    echo str_repeat("-", 50) . "\n";
    while ($u = $all_users->fetch_assoc()) {
        printf("%-5s %-20s %-20s\n", $u['id'], $u['username'], $u['created_at']);
    }
    echo "</pre>";
} else {
    echo "<p class='error'>❌ 沒有任何使用者</p>";
}

// 6. 提供修正連結
echo "<h2>6. 快速修正</h2>";
echo "<p><a href='reset_admin_password.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>重置管理員密碼</a></p>";

echo "<hr>";
echo "<p style='color: #999; font-size: 12px;'>完成檢查後請刪除此檔案</p>";

$conn->close();
echo "</body></html>";
?>
