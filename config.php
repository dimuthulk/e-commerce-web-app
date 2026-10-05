<?php
// Server එක Local ද Live ද කියලා අඳුරගැනීම
$is_local = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);

if ($is_local) {
    // Local (XAMPP) Credentials
    $db_host = 'localhost:3307';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'dimuthu_ecommerce';
} else {
    // InfinityFree Credentials ("image_2fd762.png" එකට අනුව)
    $db_host = 'sql101.infinityfree.com';
    $db_user = 'if0_43097265';
    $db_pass = 'k0ZU7F3bRO'; // මෙතනට ඔයාගේ ඇත්ත පාස්වර්ඩ් එක දාන්න
    $db_name = 'if0_43097265_dimuthu_ecommerce';
}

try {
    // PDO හරහා Database එකට Connect වීම
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // Error Mode එක Exception විදිහට සකස් කිරීම
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>