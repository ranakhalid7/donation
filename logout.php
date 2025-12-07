<?php
require_once 'config.php';

// تسجيل الخروج
session_destroy();

// حذف ملفات تعريف الارتباط
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// إعادة التوجيه إلى الصفحة الرئيسية
header('Location: index.php');
exit();
?>
