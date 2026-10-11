<?php
require "includes/init.php";
require_login();
$uid = user_id();
$stmt = $conn->prepare(
    "SELECT t.*,u.fullname,c.name AS category_name FROM bookmarks b JOIN trips t ON b.trip_id=t.trip_id JOIN users u ON t.user_id=u.user_id JOIN categories c ON t.category_id=c.category_id WHERE b.user_id=? AND t.visibility='public' AND t.moderation_status='visible' ORDER BY b.created_at DESC,t.trip_id DESC",
);
$stmt->bind_param("i", $uid);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$page_title = "ทริปที่สนใจ";
require "includes/header.php";
?>
<section class="page-heading"><p class="eyebrow">SAVED INSPIRATIONS</p><h1>ทริปที่เก็บไว้เป็นแรงบันดาลใจ</h1><p class="muted">ทริปที่ถูกลบหรือเปลี่ยนเป็นส่วนตัวจะไม่แสดงในหน้านี้</p></section>
<?php require "includes/cards.php"; ?>
<?php if (
    $trips
): ?><section class="panel saved-list"><h2>จัดการรายการที่บันทึก</h2><?php foreach (
    $trips
    as $t
): ?><form method="post" action="bookmark.php" class="saved-row"><?php csrf_field(); ?><input type="hidden" name="trip_id" value="<?= (int) $t[
    "trip_id"
] ?>"><input type="hidden" name="action" value="remove"><a href="trip_detail.php?id=<?= (int) $t[
    "trip_id"
] ?>"><?= h(
    $t["title"],
) ?></a><button class="btn secondary small">ยกเลิกการบันทึก</button></form><?php endforeach; ?></section><?php endif; ?>
<?php require "includes/footer.php"; ?>
