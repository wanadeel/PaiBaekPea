<?php
require "includes/init.php";
require_login();
$trip_id = (int) ($_GET["trip_id"] ?? 0);
$trip = get_trip($trip_id, true);
$place = [];
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $created = [];
    $transaction = false;
    try {
        [$name, $province, $visit, $story, $sort $country] = place_input($trip);
        $stmt = $conn->prepare(
    "INSERT INTO trip_places
        (trip_id, name, province, visit_date, story, sort_order, country_code)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "issssis",
    $trip_id,
    $name,
    $province,
    $visit,
    $story,
    $sort,
    $country
);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()["total"] >= 20) {
            throw new RuntimeException("ทริปหนึ่งมีสถานที่ได้ไม่เกิน 20 แห่ง");
        }
        $stmt->close();
        $conn->begin_transaction();
        $transaction = true;
        $stmt = $conn->prepare(
            "INSERT INTO trip_places(trip_id,name,province,visit_date,story,sort_order) VALUES(?,?,?,?,?,?)",
        );
        $stmt->bind_param(
            "issssi",
            $trip_id,
            $name,
            $province,
            $visit,
            $story,
            $sort,
        );
        $stmt->execute();
        $place_id = $conn->insert_id;
        $stmt->close();
        add_photos($place_id, $created);
        $conn->commit();
        $transaction = false;
        flash("เพิ่มสถานที่แล้ว");
        go("trip_detail.php?id=" . $trip_id);
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }
        clean_files($created);
        $message = error_message($error);
    }
}
$page_title = "เพิ่มสถานที่";
require "includes/header.php";
?>
<section class="page-heading"><h1>เพิ่มสถานที่ในทริป</h1><p><?= h(
    $trip["title"],
) ?></p><p class="muted">วันที่ต้องอยู่ระหว่าง <?= h(
    $trip["start_date"],
) ?> ถึง <?= h($trip["end_date"]) ?></p></section>
<?php if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?> · กรุณาเลือกไฟล์รูปใหม่อีกครั้ง</p><?php endif; ?>
<form class="panel" method="post" enctype="multipart/form-data"><?php
csrf_field();
require "includes/place_fields.php";
?><div class="actions"><a class="btn secondary" href="trip_detail.php?id=<?= $trip_id ?>">ยกเลิก</a><button class="btn">บันทึกสถานที่</button></div></form>
<?php require "includes/footer.php"; ?>
