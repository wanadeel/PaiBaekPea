<?php
require "includes/init.php";
if (user_id()) {
    go("my_trips.php");
}
$message = "";
$email = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    // จำกัดการลองผิดภายใน Session นี้ (ไม่ใช่ distributed/IP rate limiter)
    $attempt = $_SESSION["login_attempt"] ?? [
        "count" => 0,
        "until" => time() + 900,
    ];
    if ($attempt["until"] < time()) {
        $attempt = ["count" => 0, "until" => time() + 900];
    }
    if ($attempt["count"] >= 10) {
        $message = "ลองเข้าสู่ระบบหลายครั้ง กรุณารอ 15 นาที";
    } else {
        $email = strtolower(trim((string) ($_POST["email"] ?? "")));
        $password = (string) ($_POST["password"] ?? "");
        $stmt = $conn->prepare(
            "SELECT user_id,fullname,password,account_status FROM users WHERE email=?",
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (
            $user &&
            $user["account_status"] === "active" &&
            strlen($password) <= 72 &&
            password_verify($password, $user["password"])
        ) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = (int) $user["user_id"];
            $_SESSION["fullname"] = $user["fullname"];
            $_SESSION["csrf"] = bin2hex(random_bytes(32));
            unset($_SESSION["login_attempt"]);
            $next = safe_next($_SESSION["next"] ?? "my_trips.php");
            unset($_SESSION["next"]);
            $pending = (int) ($_SESSION["pending_bookmark"] ?? 0);
            unset($_SESSION["pending_bookmark"]);
            if ($pending) {
                flash(
                    bookmark_trip($pending)
                        ? "เข้าสู่ระบบและบันทึกทริปแล้ว"
                        : "เข้าสู่ระบบแล้ว แต่ทริปที่เลือกไม่สามารถบันทึกได้",
                );
                $next = "bookmarks.php";
            } else {
                flash("เข้าสู่ระบบสำเร็จ");
            }
            go($next);
        }
        $attempt["count"]++;
        $_SESSION["login_attempt"] = $attempt;
        $message = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
$page_title = "เข้าสู่ระบบ";
require "includes/header.php";
?>
<div class="pbb-login-layout">

    <!-- ด้านซ้าย: ชื่อเว็บไซต์และวิดีโอ -->
    <section class="pbb-login-media" aria-labelledby="login-brand">
        <h2 id="login-brand">PAIBEAKPEA</h2>

        <video
            class="pbb-login-video"
            autoplay muted loop playsinline 
            preload="metadata"
            aria-label="วิดีโอแนะนำการเดินทางคลิปที่ 1"
        >
            <source src="vedios/ex5.mp4" type="video/mp4">
            เบราว์เซอร์ของคุณไม่รองรับวิดีโอ
        </video>

        <video
            class="pbb-login-video"
            autoplay muted loop playsinline 
            preload="metadata"
            aria-label="วิดีโอแนะนำการเดินทางคลิปที่ 2"
        >
            <source src="vedios/ex6.mp4" type="video/mp4">
            เบราว์เซอร์ของคุณไม่รองรับวิดีโอ
        </video>
    </section>

    <!-- ด้านขวา: กล่องล็อกอินเดิม -->
    <div class="pbb-login-form-side">
<section class="panel auth-panel pbb-login-card"><p class="eyebrow">WELCOME TO AROUND THE WORLD</p><h1>ยินดีต้อนรับสู่โลกของนักเดินทาง</h1><p class="muted">มาสร้างเรื่องราวแห่งความทรงจำไปด้วยกัน</p>
<?php if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?></p><?php endif; ?>
<form method="post"><?php csrf_field(); ?>
<label for="email">อีเมล</label><input id="email" name="email" type="email" value="<?= h(
    $email,
) ?>" autocomplete="email" required>
<label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button class="btn full">เข้าสู่ระบบ</button></form><p class="auth-link">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p></section>
</div>
</div>
<?php require "includes/footer.php"; ?>
