<?php
require "includes/init.php";
require_login();
$uid = user_id();
$q = trim((string) ($_GET["q"] ?? ""));
$keyword = "%" . $q . "%";
$year = (int) ($_GET["year"] ?? 0);
$month = (int) ($_GET["month"] ?? 0);
$message = "";
$trips = [];
$first = "1900-01-01";
$last = "2200-12-31";
if (
    ($year && ($year < 1900 || $year > 2200)) ||
    $month < 0 ||
    $month > 12 ||
    ($month && !$year)
) {
    $message = "กรุณาเลือกปี ค.ศ. และเดือนให้ถูกต้อง";
} else {
    if ($year) {
        $first = sprintf("%04d-%02d-01", $year, $month ?: 1);
        $last = $month ? date("Y-m-t", strtotime($first)) : $year . "-12-31";
    }
    // ใช้ช่วงทับซ้อน ไม่ใช้ created_at
    $stmt = $conn->prepare(
        "SELECT t.*,u.fullname,c.name AS category_name FROM trips t JOIN users u ON t.user_id=u.user_id JOIN categories c ON t.category_id=c.category_id WHERE t.user_id=? AND t.start_date<=? AND t.end_date>=? AND (t.title LIKE ? OR EXISTS(SELECT 1 FROM trip_places p WHERE p.trip_id=t.trip_id AND (p.name LIKE ? OR p.province LIKE ?))) ORDER BY t.start_date DESC,t.trip_id DESC",
    );
    $stmt->bind_param(
        "isssss",
        $uid,
        $last,
        $first,
        $keyword,
        $keyword,
        $keyword,
    );
    $stmt->execute();
    $trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$page_title = "บันทึกของฉัน";
require "includes/header.php";
?>
<section class="page-heading journal-heading heading-row"><div><p class="eyebrow">YOUR TRAVEL JOURNAL</p><h1>บันทึกของฉัน</h1><p class="muted">ทุกจุดหมาย ทุกความทรงจำ ในพื้นที่ของคุณ</p></div><a class="btn" href="add_trip.php">+ สร้างทริป</a></section>
<form method="get" class="filters"><label class="grow">ค้นหา<input name="q" value="<?= h(
    $q,
) ?>" placeholder="ชื่อทริป สถานที่ จังหวัด"></label><label>ปี ค.ศ.<input type="number" name="year" min="1900" max="2200" value="<?= $year ?:
    "" ?>" placeholder="2026"></label><label>เดือน<select name="month"><option value="0">ทุกเดือน</option><?php for (
    $i = 1;
    $i <= 12;
    $i++
): ?><option value="<?= $i ?>" <?= $month === $i
    ? "selected"
    : "" ?>>เดือน <?= $i ?></option><?php endfor; ?></select></label><button class="btn">กรองข้อมูล</button><a class="btn secondary" href="my_trips.php">ล้าง</a></form>
<div class="section-heading"><h2>การเดินทางของคุณ</h2><span class="muted"><?= count(
    $trips,
) ?> ทริป</span></div>
<?php
if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?></p><?php else:require "includes/cards.php";endif;
require "includes/footer.php";

?>
