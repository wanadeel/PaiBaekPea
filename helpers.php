<?php
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
function user_id()
{
    return (int) ($_SESSION["user_id"] ?? 0);
}
function go($url)
{
    header("Location: " . $url);
    exit();
}
function csrf_field()
{
    echo '<input type="hidden" name="csrf" value="' .
        h($_SESSION["csrf"]) .
        '">';
}
function check_csrf()
{
    $token = $_POST["csrf"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["csrf"], $token)) {
        http_response_code(403);
        exit("คำขอหมดอายุ กรุณาเปิดหน้าแบบฟอร์มใหม่");
    }
}
function require_login()
{
    if (!user_id()) {
        $next = basename($_SERVER["SCRIPT_NAME"]);
        if (isset($_GET["id"])) {
            $next .= "?id=" . (int) $_GET["id"];
        } elseif (isset($_GET["trip_id"])) {
            $next .= "?trip_id=" . (int) $_GET["trip_id"];
        }
        $_SESSION["next"] = $next;
        go("login.php");
    }
}
function safe_next($next)
{
    return preg_match(
        "/\A(?:index|my_trips|bookmarks|add_trip|edit_trip|trip_detail|add_place|edit_place|place_view|profile|edit_profile)\.php(?:\?(?:id|trip_id)=\d+)?\z/",
        (string) $next,
    )
        ? $next
        : "my_trips.php";
}
function flash($message)
{
    $_SESSION["flash"] = $message;
}
function not_found()
{
    http_response_code(404);
    $page_title = "ไม่พบข้อมูล";
    require __DIR__ . "/header.php";
    echo '<section class="panel empty"><h1>ไม่พบข้อมูลหรือไม่มีสิทธิ์เข้าถึง</h1><p>เรื่องราวอาจถูกลบหรือเปลี่ยนเป็นส่วนตัว</p><a class="btn" href="index.php">กลับไปสำรวจ</a></section>';
    require __DIR__ . "/footer.php";
    exit();
}
function has_admin_access()
{
    global $conn;

    $adminId = (int) ($_SESSION["admin_user_id"] ?? 0);

    if ($adminId < 1 || $adminId !== user_id()) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT role, account_status
         FROM users
         WHERE user_id = ?"
    );
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $account &&
        $account["role"] === "admin" &&
        $account["account_status"] === "active";
}
function get_trip($trip_id, $owner_only = false)
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT t.*, u.fullname, u.username, u.avatar,
                c.name AS category_name
         FROM trips t
         JOIN users u ON t.user_id = u.user_id
         JOIN categories c ON t.category_id = c.category_id
         WHERE t.trip_id = ?"
    );

    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip) {
        not_found();
        exit;
    }

    $owner =
        user_id() > 0 &&
        (int) $trip["user_id"] === user_id();

    // หน้าแก้ไขและลบยังให้เฉพาะเจ้าของ
    if ($owner_only && !$owner) {
        not_found();
        exit;
    }

    // เจ้าของยังอ่านทริปของตนเองได้
    if ($owner) {
        return $trip;
    }

    // แอดมินไม่มีสิทธิ์อ่านทริปส่วนตัวของผู้อื่น
    if ($trip["visibility"] !== "public") {
        not_found();
        exit;
    }

    // ทริปสาธารณะที่ถูกซ่อน ให้แอดมินเปิดตรวจได้
    if (
        $trip["moderation_status"] !== "visible" &&
        !has_admin_access()
    ) {
        not_found();
        exit;
    }

    return $trip;
}
function get_place($place_id, $owner_only = false)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM trip_places WHERE place_id=?");
    $stmt->bind_param("i", $place_id);
    $stmt->execute();
    $place = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$place) {
        not_found();
    }
    $trip = get_trip((int) $place["trip_id"], $owner_only);
    return [$place, $trip];
}
function photos_for($place_id)
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT * FROM place_photos WHERE place_id=? ORDER BY photo_id",
    );
    $stmt->bind_param("i", $place_id);
    $stmt->execute();
    $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $photos;
}
function text_value($name, $max, $required = false)
{
    $value = $_POST[$name] ?? "";
    if (!is_string($value)) {
        throw new RuntimeException("รูปแบบข้อมูลไม่ถูกต้อง");
    }
    $value = trim($value);
    if (($required && $value === "") || mb_strlen($value) > $max) {
        throw new RuntimeException(
            "กรุณากรอกข้อมูลให้ครบและไม่เกินความยาวที่กำหนด",
        );
    }
    return $value;
}
function valid_date($value)
{
    if (!is_string($value)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    return $d &&
        $d->format("Y-m-d") === $value &&
        $value >= "1900-01-01" &&
        $value <= "2200-12-31";
}
function trip_input()
{
    global $conn;
    $title = text_value("title", 150, true);
    $description = text_value("description", 5000);
    $start = text_value("start_date", 10, true);
    $end = text_value("end_date", 10, true);
    $visibility = text_value("visibility", 10, true);
    $category = (int) ($_POST["category_id"] ?? 0);
    if (!valid_date($start) || !valid_date($end) || $start > $end) {
        throw new RuntimeException("ช่วงวันที่เดินทางไม่ถูกต้อง");
    }
    if (!in_array($visibility, ["public", "private"], true)) {
        throw new RuntimeException("กรุณาเลือกความเป็นส่วนตัว");
    }
    $stmt = $conn->prepare(
        "SELECT category_id FROM categories WHERE category_id=?",
    );
    $stmt->bind_param("i", $category);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException("ไม่พบประเภทการท่องเที่ยว");
    }
    $stmt->close();
    return [$title, $description, $start, $end, $visibility, $category];
}
function place_input($trip)
{
    $name = text_value("name", 150, true);
    $province = text_value("province", 100, true);
    $date = text_value("visit_date", 10, true);
    $story = text_value("story", 10000);
    $order = (int) ($_POST["sort_order"] ?? 1);

    // ยังไม่ระบุประเทศได้ เพื่อรองรับข้อมูลเดิม
    $country = strtoupper(trim(text_value("country_code", 2)));

    // ประเทศที่เปิดให้เลือกในแบบฟอร์มขั้นนี้
    $allowedCountries = [
        "TH", "JP", "KR", "CN", "SG", "MY",
        "VN", "LA", "ID", "FR", "GB", "US"
    ];

    if (
        $country !== "" &&
        !in_array($country, $allowedCountries, true)
    ) {
        throw new RuntimeException("กรุณาเลือกประเทศจากรายการ");
    }

    if (
        !valid_date($date) ||
        $date < $trip["start_date"] ||
        $date > $trip["end_date"]
    ) {
        throw new RuntimeException("วันที่ไปเยือนต้องอยู่ในช่วงการเดินทาง");
    }

    if ($order < 1 || $order > 1000) {
        throw new RuntimeException("ลำดับต้องอยู่ระหว่าง 1–1000");
    }

    return [
        $name,
        $province,
        $date,
        $story,
        $order,
        $country === "" ? null : $country
    ];
}
function error_message($error)
{
    if ($error instanceof mysqli_sql_exception) {
        error_log($error->getMessage());
        return "บันทึกฐานข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง";
    }
    return $error instanceof RuntimeException
        ? $error->getMessage()
        : "เกิดข้อผิดพลาด กรุณาลองอีกครั้ง";
}
function clean_files($files)
{
    foreach (array_unique($files) as $file) {
        if (!$file) {
            continue;
        }
        $path = UPLOAD_DIR . "/" . basename($file);
        if (is_file($path) && !unlink($path)) {
            error_log("ลบไฟล์ไม่ได้: " . $path);
        }
    }
}
function upload_photo($file, &$created, $required = false)
{
    $error = (int) ($file["error"] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE && !$required) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException("กรุณาเลือกรูป หรือรูปอัปโหลดไม่สำเร็จ");
    }
    if (
        (int) $file["size"] > 5 * 1024 * 1024 ||
        !is_uploaded_file($file["tmp_name"])
    ) {
        throw new RuntimeException("ไฟล์รูปแต่ละไฟล์ต้องไม่เกิน 5 MB");
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
    $types = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
    ];
    $dimensions = @getimagesize($file["tmp_name"]);
    if (
        !isset($types[$mime]) ||
        !$dimensions ||
        $dimensions[0] * $dimensions[1] > 25000000
    ) {
        throw new RuntimeException(
            "รองรับ JPG PNG WebP และขนาดภาพไม่เกิน 25 ล้านพิกเซล",
        );
    }
    $name = bin2hex(random_bytes(24)) . "." . $types[$mime];
    if (!move_uploaded_file($file["tmp_name"], UPLOAD_DIR . "/" . $name)) {
        throw new RuntimeException("เขียนไฟล์รูปภาพไม่ได้");
    }
    $created[] = $name;
    return $name;
}
function add_photos($place_id, &$created)
{
    global $conn;
    $files = $_FILES["photos"] ?? [];
    $errors = $files["error"] ?? [];
    $count = count(photos_for($place_id));
    foreach ($errors as $i => $error) {
        if ((int) $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (++$count > 10) {
            throw new RuntimeException("แต่ละสถานที่มีรูปได้ไม่เกิน 10 รูป");
        }
        $file = [
            "error" => $error,
            "size" => $files["size"][$i] ?? 0,
            "tmp_name" => $files["tmp_name"][$i] ?? "",
        ];
        $filename = upload_photo($file, $created, true);
        $stmt = $conn->prepare(
            "INSERT INTO place_photos(place_id,filename) VALUES(?,?)",
        );
        $stmt->bind_param("is", $place_id, $filename);
        $stmt->execute();
        $stmt->close();
    }
}
function bookmark_trip($trip_id)
{
    global $conn;

    $uid = user_id();
    $trip_id = (int) $trip_id;

    if ($uid < 1 || $trip_id < 1) {
        return false;
    }

    // ตรวจสิทธิ์ในคำสั่งบันทึก:
    // ต้องเป็นทริปสาธารณะ ไม่ถูกซ่อน และไม่ใช่ทริปตนเอง
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO bookmarks (user_id, trip_id)
         SELECT ?, t.trip_id
         FROM trips t
         WHERE t.trip_id = ?
           AND t.user_id <> ?
           AND t.visibility = 'public'
           AND t.moderation_status = 'visible'"
    );

    $stmt->bind_param("iii", $uid, $trip_id, $uid);
    $stmt->execute();
    $inserted = $stmt->affected_rows > 0;
    $stmt->close();

    if ($inserted) {
        return true;
    }

    // ถ้าบันทึกไว้แล้ว ถือว่าสำเร็จเมื่อยังเข้าถึงทริปได้
    $stmt = $conn->prepare(
        "SELECT b.trip_id
         FROM bookmarks b
         JOIN trips t ON t.trip_id = b.trip_id
         WHERE b.user_id = ?
           AND b.trip_id = ?
           AND t.user_id <> ?
           AND t.visibility = 'public'
           AND t.moderation_status = 'visible'"
    );

    $stmt->bind_param("iii", $uid, $trip_id, $uid);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool) $exists;
}
