<?php
// 資料庫連線設定
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'iso14001_system');

// 系統設定
define('SITE_NAME', 'ISO14001 環境管理系統');
define('SITE_URL', 'http://localhost/iso14001');

// Session 設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // 如使用 HTTPS 請改為 1

// 啟動 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 建立資料庫連線
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("資料庫連線失敗: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("系統錯誤: " . $e->getMessage());
}

// 檢查是否登入
function check_login() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: login.php');
        exit();
    }
}

// 產生 CSRF Token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 驗證 CSRF Token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 清理輸入資料
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// 格式化日期
function format_date($date) {
    if (empty($date)) return '-';
    return date('Y-m-d', strtotime($date));
}

// 格式化日期時間
function format_datetime($datetime) {
    if (empty($datetime)) return '-';
    return date('Y-m-d H:i:s', strtotime($datetime));
}

// 狀態翻譯
function translate_status($status) {
    $statuses = [
        'draft' => '草稿',
        'in_review' => '審核中',
        'completed' => '已完成'
    ];
    return $statuses[$status] ?? $status;
}

// 影響程度翻譯
function translate_impact_level($level) {
    $levels = [
        'high' => '高',
        'medium' => '中',
        'low' => '低'
    ];
    return $levels[$level] ?? $level;
}

// 嚴重程度翻譯
function translate_severity($severity) {
    $severities = [
        'positive_high' => '正面影響-高',
        'positive_medium' => '正面影響-中',
        'positive_low' => '正面影響-低',
        'neutral' => '中性',
        'negative_low' => '負面影響-低',
        'negative_medium' => '負面影響-中',
        'negative_high' => '負面影響-高'
    ];
    return $severities[$severity] ?? $severity;
}

// 影響類型翻譯
function translate_impact_type($type) {
    $types = [
        'economic' => '經濟',
        'social' => '社會',
        'environmental' => '環境'
    ];
    return $types[$type] ?? $type;
}

// 成功訊息
function set_success_message($message) {
    $_SESSION['success_message'] = $message;
}

// 錯誤訊息
function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

// 顯示訊息
function display_messages() {
    $output = '';
    
    if (isset($_SESSION['success_message'])) {
        $output .= '<div class="alert alert-success">' . 
                   htmlspecialchars($_SESSION['success_message']) . 
                   '</div>';
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        $output .= '<div class="alert alert-error">' . 
                   htmlspecialchars($_SESSION['error_message']) . 
                   '</div>';
        unset($_SESSION['error_message']);
    }
    
    return $output;
}

// 取得目前使用者資訊
function get_current_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null
    ];
}
?>