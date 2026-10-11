<?php
require "includes/init.php";
if (user_id()) {
    go("my_trips.php");
}
$message = "";
$username = "";
$email = "";
$fullname = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    try {
        $username = text_value("username", 30, true);
        $email = strtolower(text_value("email", 190, true));
        $fullname = text_value("fullname", 100, true);
        $password = $_POST["password"] ?? "";
        if (!is_string($password)) {
            throw new RuntimeException("รหัสผ่านไม่ถูกต้อง");
        }
        $confirm = (string) ($_POST["confirm_password"] ?? "");
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            throw new RuntimeException(
                "ชื่อผู้ใช้เป็นอักษรอังกฤษ ตัวเลข หรือ _ จำนวน 3–30 ตัว",
            );
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("อีเมลไม่ถูกต้อง");
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new RuntimeException("รหัสผ่านต้องมี 8–72 ไบต์");
        }
        if ($password !== $confirm) {
            throw new RuntimeException("รหัสผ่านทั้งสองช่องไม่ตรงกัน");
        }
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE username=? OR email=?",
        );
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows) {
            throw new RuntimeException("ชื่อผู้ใช้หรืออีเมลนี้มีผู้ใช้งานแล้ว");
        }
        $check->close();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $bio = "";
        $stmt = $conn->prepare(
            "INSERT INTO users(username,email,password,fullname,bio) VALUES(?,?,?,?,?)",
        );
        $stmt->bind_param(
            "sssss",
            $username,
            $email,
            $hashed_password,
            $fullname,
            $bio,
        );
        $stmt->execute();
        $stmt->close();
        flash("สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบเพื่อทำรายการต่อ");
        go("login.php");
    } catch (Throwable $error) {
        $message =
            $error instanceof mysqli_sql_exception && $error->getCode() === 1062
                ? "ชื่อผู้ใช้หรืออีเมลซ้ำ"
                : error_message($error);
    }
}
$page_title = "สมัครสมาชิก";
require "includes/header.php";
?>
<div class="pbb-register-layout">

    <!-- วิดีโอด้านซ้าย -->
    <div class="pbb-register-media">
        <video autoplay muted loop playsinline 
               preload="metadata"
               aria-label="บรรยากาศการเดินทางคลิปที่ 1">
            <source src="vedios/ex2.mp4" type="video/mp4">
            เบราว์เซอร์ของคุณไม่รองรับวิดีโอ
        </video>

        <video autoplay muted loop playsinline 
               preload="metadata"
               aria-label="บรรยากาศการเดินทางคลิปที่ 2">
            <source src="vedios/ex5.mp4" type="video/mp4">
            เบราว์เซอร์ของคุณไม่รองรับวิดีโอ
        </video>

        <video autoplay muted loop playsinline 
               preload="metadata"
               aria-label="บรรยากาศการเดินทางคลิปที่ 2">
            <source src="vedios/ex6.mp4" type="video/mp4">
            เบราว์เซอร์ของคุณไม่รองรับวิดีโอ
        </video>
    </div>

    <!-- ฟอร์มสมัครสมาชิก -->
     <div class="pbb-register-form-side">
        <section class="panel auth-panel">
            <p class="eyebrow">JOIN THE JOURNEY</p>
            <h1>สมัครสมาชิก</h1>
            <p class="muted">เริ่มบันทึกเรื่องราวของคุณ</p>

            <?php if (!empty($message)): ?>
                <p class="error" role="alert">
                    <?= h($message) ?>
                </p>
            <?php endif; ?>

            <form method="post">
                <?php csrf_field(); ?>

                <label for="username">ชื่อผู้ใช้</label>
                <input
                    id="username"
                    name="username"
                    value="<?= h($username) ?>"
                    pattern="[A-Za-z0-9_]{3,30}"
                    maxlength="30"
                    autocomplete="username"
                    required
                >

                <label for="fullname">ชื่อที่แสดง</label>
                <input
                    id="fullname"
                    name="fullname"
                    value="<?= h($fullname) ?>"
                    maxlength="100"
                    required
                >

                <label for="email">อีเมล</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="<?= h($email) ?>"
                    maxlength="190"
                    autocomplete="email"
                    required
                >

                <label for="password">รหัสผ่าน</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    required
                >

                <label for="confirm">ยืนยันรหัสผ่าน</label>
                <input
                    id="confirm"
                    name="confirm_password"
                    type="password"
                    autocomplete="new-password"
                    required
                >

                <button class="btn full" type="submit">
                    สมัครสมาชิก
                </button>
            </form>

            <p class="auth-link">
                มีบัญชีแล้ว?
                <a href="login.php">เข้าสู่ระบบ</a>
            </p>
        </section>
    </div>
</div>

<?php require "includes/footer.php"; ?>