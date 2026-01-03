<?php
/**
 * ISO14001 環境管理系統 - 登出處理
 * 檔案名稱: logout.php
 * 版本: 1.0.0
 * 建立日期: 2026-01-03
 * 
 * 功能說明:
 * - 安全地登出使用者
 * - 清除所有 Session 資料
 * - 刪除 Session Cookie
 * - 銷毀 Session
 * - 防止 Session 劫持
 * - 導向登入頁面
 */

// 引入設定檔
require_once 'config.php';

// 記錄登出資訊（可選）
if (isset($_SESSION['username'])) {
    error_log("User logged out: " . $_SESSION['username'] . " from IP: " . $_SERVER['REMOTE_ADDR']);
}

// 步驟 1: 清除所有 Session 變數
$_SESSION = array();

// 步驟 2: 刪除 Session Cookie
// 如果要完全銷毀 Session，也要刪除 Session Cookie
// 注意: 這將銷毀 Session，而不僅僅是 Session 資料
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),           // Cookie 名稱
        '',                       // 空值
        time() - 42000,          // 過期時間（設為過去的時間）
        '/',                      // Cookie 路徑
        '',                       // Cookie 網域
        false,                    // 是否僅透過 HTTPS（根據您的設定調整）
        true                      // HttpOnly flag（防止 JavaScript 存取）
    );
}

// 步驟 3: 銷毀 Session
session_destroy();

// 步驟 4: 清除任何可能的快取
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 步驟 5: 導向登入頁面
header('Location: login.php');
exit();
?>
