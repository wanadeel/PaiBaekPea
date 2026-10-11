<?php
require_once __DIR__ . "/common.php";

$pageInput = $_GET["page"] ?? 1;
$page = filter_var($pageInput, FILTER_VALIDATE_INT);
$page = max(1, min(100000, $page ?: 1));

$offset = ($page - 1) * 20;

$actionNames = [
    "suspend_member" => "ระงับบัญชีสมาชิก",
    "restore_member" => "คืนสถานะสมาชิก",
    "hide_trip" => "ซ่อนทริป",
    "restore_trip" => "คืนการเผยแพร่ทริป",
    "create_category" => "เพิ่มหมวดหมู่",
    "edit_category" => "แก้ไขหมวดหมู่",
    "disable_category" => "ปิดหมวดหมู่",
    "enable_category" => "เปิดหมวดหมู่",
    "review_report" => "จัดการรายงานปัญหา"
];

$targetNames = [
    "user" => "สมาชิก",
    "trip" => "ทริป",
    "category" => "หมวดหมู่",
    "report" => "รายงาน"
];

$statusNames = [
    "active" => "ใช้งานปกติ",
    "suspended" => "ถูกระงับ",
    "visible" => "เผยแพร่",
    "hidden" => "ถูกซ่อน",
    "enabled" => "เปิดใช้งาน",
    "disabled" => "ปิดใช้งาน",
    "pending" => "รอตรวจสอบ",
    "resolved" => "ดำเนินการแล้ว",
    "dismissed" => "ยุติรายงาน"
];

$stmt = $conn->prepare(
    "SELECT l.*, u.fullname AS admin_name
     FROM admin_logs l
     JOIN users u ON u.user_id = l.admin_id
     ORDER BY l.created_at DESC, l.log_id DESC
     LIMIT 21 OFFSET ?"
);
$stmt->bind_param("i", $offset);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hasNext = count($logs) > 20;

if ($hasNext) {
    array_pop($logs);
}
$page_title="ประวัติการจัดการ";
require __DIR__."/header.php";
?>


    <section class="panel">
        <?php if (!$logs): ?>
            <h2>ยังไม่มีประวัติในหน้านี้</h2>
            <p>
                ประวัติจะเพิ่มเมื่อแอดมินดำเนินการผ่านระบบ
                เช่น ระงับหรือคืนสถานะสมาชิก
            </p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <caption>
                        ประวัติการทำงาน — หน้า <?= $page ?>
                    </caption>
                    <thead>
                    <tr>
                        <th scope="col">วันเวลา</th>
                        <th scope="col">แอดมิน</th>
                        <th scope="col">การดำเนินการ</th>
                        <th scope="col">รายการที่จัดการ</th>
                        <th scope="col">สถานะเดิม → ใหม่</th>
                        <th scope="col">เหตุผล</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= h($log["created_at"]) ?></td>

                            <td>
                                <?= h($log["admin_name"]) ?>
                                <p class="muted">
                                    รหัส <?= (int) $log["admin_id"] ?>
                                </p>
                            </td>

                            <td>
                                <?= h(
                                    $actionNames[$log["action"]]
                                    ?? $log["action"]
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $targetNames[$log["target_type"]]
                                    ?? $log["target_type"]
                                ) ?>
                                #<?= (int) $log["target_id"] ?>
                            </td>

                            <td>
                                <?= h(
                                    $statusNames[$log["before_status"] ?? ""]
                                    ?? ($log["before_status"] ?? "—")
                                ) ?>
                                →
                                <?= h(
                                    $statusNames[$log["after_status"] ?? ""]
                                    ?? ($log["after_status"] ?? "—")
                                ) ?>
                            </td>

                            <td class="admin-log-reason">
                                <?= h($log["reason"]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <nav class="actions" aria-label="หน้าประวัติการทำงาน">
            <?php if ($page > 1): ?>
                <a
                    class="btn secondary small"
                    href="logs.php?page=<?= $page - 1 ?>"
                >
                    หน้าก่อนหน้า
                </a>
            <?php endif; ?>

            <?php if ($hasNext): ?>
                <a
                    class="btn secondary small"
                    href="logs.php?page=<?= $page + 1 ?>"
                >
                    หน้าถัดไป
                </a>
            <?php endif; ?>
        </nav>
    </section>
<?php require __DIR__."/footer.php"; ?>
