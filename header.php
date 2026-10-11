<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$trackingTrip = ($currentPage === 'trip_detail.php' && isset($trip) && $trip['visibility'] === 'public' && $trip['moderation_status'] === 'visible') ? (int)$trip['trip_id'] : null;
if (function_exists('pbb_track_page')) pbb_track_page($currentPage, $trackingTrip);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? "สำรวจทริป") ?> | PaiBaekPea</title>
    <link rel="stylesheet"
      href="style.css?v=<?= filemtime(__DIR__ . '/../style.css') ?>">
    <script src="nav.js" defer></script>
    <link rel="stylesheet" href="assets/country-carousel.css">
    <?php if ($currentPage === 'stories.php'): ?>
    <link
        rel="stylesheet"
        href="assets/stories.css?v=<?= (int) filemtime(__DIR__ . '/../assets/stories.css') ?>"
    >
<?php endif; ?>
</head>
<body>
    <!-- ด้านล่างนี้เป็นส่วนเมนูเดิม -->
<header class="site-header">
    <a class="brand" href="index.php"><strong>PaiBaekPea</strong><small>พักบั๊ก ไปแบกเป้</small></a>
    <nav aria-label="เมนูหลัก">
        <a href="stories.php"
    class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'stories.php' ? 'is-active' : '' ?>"
>
    สตอรี่
</a>
        <a href="index.php"
           class="nav-link <?= $currentPage === 'index.php' ? 'is-active' : '' ?>"> สำรวจ</a>
        <?php if (user_id()): ?>
            <?php if (($pbbAccount['role'] ?? '') === 'admin'): ?><a href="admin/index.php" class="nav-link">จัดการเว็บไซต์</a><?php endif; ?>
            <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'is-active' : '' ?>" > แดชบอร์ด</a>
            <a href="my_trips.php"
               class="nav-link <?= in_array($currentPage, ['my_trips.php', 'edit_trip.php']) ? 'is-active' : '' ?>">บันทึกของฉัน</a>
            <a href="bookmarks.php"
               class="nav-link <?= $currentPage === 'bookmarks.php' ? 'is-active' : '' ?>"> ทริปที่สนใจ</a>
            <a href="profile.php?id=<?= user_id() ?>"
               class="nav-link <?= in_array($currentPage, ['profile.php', 'edit_profile.php']) ? 'is-active' : '' ?>">โปรไฟล์</a>
            <a class="btn small" href="add_trip.php">+ สร้างทริป</a>
            <form method="post" action="logout.php"><?php csrf_field(); ?><button class="link-button">ออกจากระบบ</button></form>
        <?php else: ?>
            <a href="login.php"
               class="nav-link <?= $currentPage === 'login.php' ? 'is-active' : '' ?>">เข้าสู่ระบบ</a>
            <a class="btn small" href="register.php">สมัครสมาชิก</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php if (!empty($_SESSION["flash"])): ?>
<div class="notice" role="status"><?= h($_SESSION["flash"]) ?></div>
<?php unset($_SESSION["flash"]);endif; ?>
