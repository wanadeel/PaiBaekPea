<?php
require_once __DIR__ . "/common.php";

$qInput = $_GET["q"] ?? "";
$q = is_string($qInput) ? trim($qInput) : "";
$q = mb_substr($q, 0, 100);
$filterId = max(0, (int)astr("id", "0"));

$pageInput = $_GET["page"] ?? 1;
$page = filter_var($pageInput, FILTER_VALIDATE_INT);
$page = max(1, min(100000, $page ?: 1));

$message = "";
$success = $_SESSION["admin_member_notice"] ?? "";
unset($_SESSION["admin_member_notice"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $transaction = false;

    try {
        $targetId = filter_var(
            $_POST["user_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = $_POST["action"] ?? "";
        $reasonInput = $_POST["reason"] ?? "";
        $expectedStatus = $_POST["expected_status"] ?? "";

        if (
            !$targetId ||
            $targetId < 1 ||
            !is_string($action) ||
            !in_array($action, ["suspend", "restore"], true)
        ) {
            throw new RuntimeException("ข้อมูลคำสั่งไม่ถูกต้อง");
        }

        if (!is_string($reasonInput)) {
            throw new RuntimeException("เหตุผลไม่ถูกต้อง");
        }

        $reason = trim($reasonInput);

        if ($reason === "" || mb_strlen($reason) > 500) {
            throw new RuntimeException(
                "กรุณาระบุเหตุผลไม่เกิน 500 ตัวอักษร"
            );
        }

        if ($targetId === (int) $admin["user_id"]) {
            throw new RuntimeException(
                "ไม่สามารถเปลี่ยนสถานะบัญชีของตนเองจากหน้านี้"
            );
        }

        $conn->begin_transaction();
        $transaction = true;

        // ตรวจสิทธิ์ผู้ดำเนินการอีกครั้งก่อนแก้ไขข้อมูล
        $actorId = (int) $admin["user_id"];

        $stmt = $conn->prepare(
            "SELECT role, account_status
             FROM users
             WHERE user_id = ?
             FOR UPDATE"
        );
        $stmt->bind_param("i", $actorId);
        $stmt->execute();
        $actor = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$actor ||
            $actor["role"] !== "admin" ||
            $actor["account_status"] !== "active"
        ) {
            throw new RuntimeException("ไม่มีสิทธิ์ดำเนินการ");
        }

        // ล็อกข้อมูลสมาชิกระหว่างเปลี่ยนสถานะ
        $stmt = $conn->prepare(
            "SELECT role, account_status
             FROM users
             WHERE user_id = ?
             FOR UPDATE"
        );
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$target) {
            throw new RuntimeException("ไม่พบสมาชิก");
        }

        // รุ่นนี้จัดการสถานะเฉพาะสมาชิกทั่วไป
        if ($target["role"] !== "user") {
            throw new RuntimeException(
                "หน้านี้ไม่อนุญาตให้เปลี่ยนสถานะบัญชีแอดมิน"
            );
        }

        $beforeStatus = $target["account_status"];

        if (
            !is_string($expectedStatus) ||
            $expectedStatus !== $beforeStatus
        ) {
            throw new RuntimeException(
                "สถานะบัญชีเปลี่ยนไปแล้ว กรุณาตรวจรายการล่าสุดแล้วลองใหม่"
            );
        }

        $afterStatus = $action === "suspend"
            ? "suspended"
            : "active";

        if ($beforeStatus === $afterStatus) {
            throw new RuntimeException(
                "บัญชีอยู่ในสถานะที่เลือกแล้ว"
            );
        }

        // คืนสถานะแล้วล้างเหตุผลระงับปัจจุบัน
        // เหตุผลการดำเนินการยังคงอยู่ในประวัติ
        $suspensionReason = $action === "suspend"
            ? $reason
            : null;

        $stmt = $conn->prepare(
            "UPDATE users
             SET account_status = ?, suspension_reason = ?
             WHERE user_id = ? AND role = 'user'"
        );
        $stmt->bind_param(
            "ssi",
            $afterStatus,
            $suspensionReason,
            $targetId
        );
        $stmt->execute();
        $stmt->close();

        $logAction = $action === "suspend"
            ? "suspend_member"
            : "restore_member";

        $targetType = "user";

        $stmt = $conn->prepare(
            "INSERT INTO admin_logs
                (admin_id, action, target_type, target_id,
                 reason, before_status, after_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ississs",
            $actorId,
            $logAction,
            $targetType,
            $targetId,
            $reason,
            $beforeStatus,
            $afterStatus
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $transaction = false;

        $_SESSION["admin_member_notice"] = $action === "suspend"
            ? "ระงับบัญชีและบันทึกประวัติแล้ว"
            : "คืนสถานะบัญชีและบันทึกประวัติแล้ว";

        header(
            "Location: members.php?q=" . rawurlencode($q) .
            "&page=" . $page
        );
        exit;
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }

        if ($error instanceof mysqli_sql_exception) {
            error_log($error->getMessage());
            $message = "บันทึกข้อมูลไม่ได้ กรุณาลองใหม่อีกครั้ง";
        } elseif ($error instanceof RuntimeException) {
            $message = $error->getMessage();
        } else {
            error_log($error->getMessage());
            $message = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
        }
    }
}

// แสดงครั้งละ 20 บัญชี
// ดึงเพิ่มอีก 1 บัญชีเพื่อตรวจว่ามีหน้าถัดไปหรือไม่
$keyword = "%" . $q . "%";
$offset = ($page - 1) * 20;

$stmt = $conn->prepare(
    "SELECT user_id, username, fullname, email, role,
            account_status, suspension_reason, created_at
     FROM users
     WHERE (username LIKE ?
        OR fullname LIKE ?
        OR email LIKE ?) AND (?=0 OR user_id=?)
     ORDER BY user_id DESC
     LIMIT 21 OFFSET ?"
);
$stmt->bind_param(
    "sssiii",
    $keyword,
    $keyword,
    $keyword,
    $filterId,
    $filterId,
    $offset
);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hasNext = count($members) > 20;

if ($hasNext) {
    array_pop($members);
}
$page_title="จัดการสมาชิก";
require __DIR__."/header.php";
?>


    <?php if ($success !== ""): ?>
        <p class="notice" role="status">
            <?= h($success) ?>
        </p>
    <?php endif; ?>



    <section class="panel">
        <form class="filters" method="get" action="members.php">
            <label class="grow" for="member-q">
                ค้นหาสมาชิก
                <input
                    id="member-q"
                    name="q"
                    type="search"
                    maxlength="100"
                    value="<?= h($q) ?>"
                    placeholder="ชื่อผู้ใช้ ชื่อที่แสดง หรืออีเมล"
                >
            </label>

            <button class="btn" type="submit">ค้นหา</button>
            <a class="btn secondary" href="members.php">
                ล้างตัวกรอง
            </a>
        </form>
    </section>

    <section class="panel">
        <?php if (!$members): ?>
            <h2>ไม่พบสมาชิก</h2>
            <p>ลองเปลี่ยนคำค้นหา หรือกลับไปหน้าก่อนหน้า</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <caption>รายชื่อสมาชิก — หน้า <?= $page ?></caption>
                    <thead>
                    <tr>
                        <th scope="col">สมาชิก</th>
                        <th scope="col">อีเมล</th>
                        <th scope="col">วันที่สมัคร</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col">จัดการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $member): ?>
                        <?php
                        $memberId = (int) $member["user_id"];
                        $isSuspended =
                            $member["account_status"] === "suspended";
                        ?>
                        <tr>
                            <td>
                                <a href="../profile.php?id=<?= $memberId ?>"><strong><?= h($member["fullname"]) ?></strong></a>
                                <p class="muted">
                                    @<?= h($member["username"]) ?>
                                </p>
                                <?php if ($member["role"] === "admin"): ?>
                                    <span class="badge">แอดมิน</span>
                                <?php endif; ?>
                            </td>

                            <td><?= h($member["email"]) ?></td>
                            <td><?= h($member["created_at"]) ?></td>

                            <td>
                                <span class="badge">
                                    <?= $isSuspended
                                        ? "ถูกระงับ"
                                        : "ใช้งานปกติ" ?>
                                </span>

                                <?php if ($isSuspended): ?>
                                    <p class="admin-member-reason">
                                        <?= h($member["suspension_reason"] ?? "") ?>
                                    </p>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($member["role"] === "admin"): ?>
                                    <p class="muted">บัญชีแอดมิน</p>
                                <?php else: ?>
                                    <form
                                        method="post"
                                        class="admin-member-form"
                                        action="members.php?q=<?= h(rawurlencode($q)) ?>&amp;page=<?= $page ?>"
                                    >
                                        <?php csrf_field(); ?>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= $memberId ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="expected_status"
                                            value="<?= h($member["account_status"]) ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="<?= $isSuspended
                                                ? "restore"
                                                : "suspend" ?>"
                                        >

                                        <label for="reason-<?= $memberId ?>">
                                            <?= $isSuspended
                                                ? "เหตุผลที่คืนสถานะ"
                                                : "เหตุผลที่ระงับบัญชี" ?>
                                        </label>

                                        <textarea
                                            id="reason-<?= $memberId ?>"
                                            name="reason"
                                            rows="2"
                                            maxlength="500"
                                            required
                                        ></textarea>

                                        <button
                                            class="btn small <?= $isSuspended
                                                ? ""
                                                : "danger" ?>"
                                            type="submit"
                                        >
                                            <?= $isSuspended
                                                ? "คืนสถานะบัญชี"
                                                : "ระงับบัญชี" ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <nav class="actions" aria-label="หน้ารายชื่อสมาชิก">
            <?php if ($page > 1): ?>
                <a
                    class="btn secondary small"
                    href="members.php?q=<?= h(rawurlencode($q)) ?>&amp;page=<?= $page - 1 ?>"
                >
                    หน้าก่อนหน้า
                </a>
            <?php endif; ?>

            <?php if ($hasNext): ?>
                <a
                    class="btn secondary small"
                    href="members.php?q=<?= h(rawurlencode($q)) ?>&amp;page=<?= $page + 1 ?>"
                >
                    หน้าถัดไป
                </a>
            <?php endif; ?>
        </nav>
    </section>
<?php require __DIR__."/footer.php"; ?>
