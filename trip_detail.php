<?php
require "includes/init.php";
$trip_id = (int) ($_GET["id"] ?? 0);
$trip = get_trip($trip_id);
$is_owner = (int) $trip["user_id"] === user_id();
$stmt = $conn->prepare(
    "SELECT * FROM trip_places WHERE trip_id=? ORDER BY sort_order,place_id",
);
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$places = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$uid = user_id();
$stmt = $conn->prepare(
    "SELECT user_id FROM bookmarks WHERE user_id=? AND trip_id=?",
);
$stmt->bind_param("ii", $uid, $trip_id);
$stmt->execute();
$saved = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();
$page_title = $trip["title"];
require "includes/header.php";
?>
<a class="back-link" href="index.php">← กลับไปสำรวจ</a>
<section class="page-heading"><p class="eyebrow"><?= h(
    $trip["category_name"],
) ?></p><h1><?= h($trip["title"]) ?></h1>
<p class="story"><?= h($trip["description"]) ?></p>
<div class="detail-meta"><a href="profile.php?id=<?= (int) $trip[
    "user_id"
] ?>">โดย <?= h($trip["fullname"]) ?></a><span class="muted"><?= h(
    $trip["start_date"],
) ?> — <?= h($trip["end_date"]) ?></span><span class="badge"><?= $trip[
    "visibility"
] === "private"
    ? "ส่วนตัว"
    : "สาธารณะ" ?></span></div>
<?php if($trip['moderation_status']==='hidden'): ?><p class="notice">โพสต์ถูกซ่อนโดยผู้ดูแล: <?= h($trip['moderation_reason'] ?? '') ?></p><?php endif; ?>
<div class="actions">
<?php if(user_id() && !$is_owner && $trip['visibility']==='public' && $trip['moderation_status']==='visible'): ?><a class="btn secondary" href="report.php?type=trip&amp;id=<?= $trip_id ?>">รายงานโพสต์</a><?php endif; ?>
<?php if (
    $is_owner
): ?><a class="btn secondary" href="edit_trip.php?id=<?= $trip_id ?>">แก้ไขทริป</a><a class="btn secondary" href="delete_trip.php?id=<?= $trip_id ?>">ลบทริป</a>
<?php else: ?><form method="post" action="bookmark.php"><?php csrf_field(); ?><input type="hidden" name="trip_id" value="<?= $trip_id ?>"><input type="hidden" name="action" value="<?= $saved
    ? "remove"
    : "add" ?>"><button class="btn"><?= $saved
    ? "บันทึกแล้ว · ยกเลิก"
    : "บันทึกทริป" ?></button></form><?php endif; ?>
</div></section>
<img class="detail-cover" src="media.php?trip_id=<?= $trip_id ?>" alt="<?= h(
    $trip["title"],
) ?>">
<div class="section-heading"><h2>เส้นทางความทรงจำ · <?= count(
    $places,
) ?> จุดหมาย</h2><?php if (
     $is_owner
 ): ?><a class="btn" href="add_place.php?trip_id=<?= $trip_id ?>">+ เพิ่มสถานที่</a><?php endif; ?></div>
<section class="timeline">
<?php foreach ($places as $i => $place): ?>
<article class="panel place-entry"><p class="eyebrow">จุดหมาย <?= $i +
    1 ?> · <?= h(
     $place["visit_date"],
 ) ?></p><h2><a href="place_view.php?id=<?= (int) $place["place_id"] ?>"><?= h(
    $place["name"],
) ?></a></h2><p class="muted"><?= h(
    $place["province"],
) ?></p><p class="story"><?= h($place["story"]) ?></p>
<div class="photo-grid"><?php foreach (
    photos_for((int) $place["place_id"])
    as $photo
): ?><a href="media.php?photo_id=<?= (int) $photo[
    "photo_id"
] ?>" target="_blank" rel="noopener"><img src="media.php?photo_id=<?= (int) $photo[
    "photo_id"
] ?>" alt="<?= h(
    $place["name"],
) ?>" loading="lazy"></a><?php endforeach; ?></div>
<?php if (
    $is_owner
): ?><div class="actions"><a class="btn secondary small" href="edit_place.php?id=<?= (int) $place[
    "place_id"
] ?>">แก้ไขสถานที่ / รูป</a><a class="btn secondary small" href="delete_place.php?id=<?= (int) $place[
    "place_id"
] ?>">ลบสถานที่</a></div><?php endif; ?>
</article><?php endforeach; ?></section>
<?php require "includes/footer.php"; ?>
