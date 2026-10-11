<?php
require __DIR__ . '/includes/init.php';

require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedPath = null;

    try {
        if (empty($_POST) && empty($_FILES)) {
            throw new RuntimeException(
                'ไฟล์อาจใหญ่เกินค่าที่ PHP อนุญาต กรุณาลองคลิปขนาดเล็กก่อน'
            );
        }

        check_csrf();

        $title = text_value('title', 150, true);
        $caption = text_value('caption', 1000);
        $video = $_FILES['video'] ?? null;

        if (!$video || !is_array($video)) {
            throw new RuntimeException('กรุณาเลือกคลิปวิดีโอ');
        }

        $uploadError = $video['error'] ?? null;

        if (!is_int($uploadError)) {
            throw new RuntimeException('ข้อมูลไฟล์ไม่ถูกต้อง');
        }

        if (
            $uploadError === UPLOAD_ERR_INI_SIZE ||
            $uploadError === UPLOAD_ERR_FORM_SIZE
        ) {
            throw new RuntimeException(
                'ไฟล์ใหญ่เกินค่าที่เซิร์ฟเวอร์อนุญาต กรุณาลองคลิปขนาดเล็กก่อน'
            );
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'อัปโหลดไม่สำเร็จ กรุณาเลือกไฟล์แล้วลองใหม่'
            );
        }

        $tmp = $video['tmp_name'] ?? '';

        if (!is_string($tmp) || !is_uploaded_file($tmp)) {
            throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
        }

        $size = filesize($tmp);

        if ($size === false || $size < 1 || $size > 50 * 1024 * 1024) {
            throw new RuntimeException(
                'คลิปต้องมีขนาดมากกว่า 0 และไม่เกิน 50 MB'
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);

        $allowed = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException(
                'รองรับเฉพาะ MP4 และ WebM หากเป็น MOV ให้แปลงเป็น MP4 ก่อน'
            );
        }

        $directory = UPLOAD_DIR . '/story-videos';

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0750, true) &&
            !is_dir($directory)
        ) {
            throw new RuntimeException('สร้างโฟลเดอร์เก็บคลิปไม่ได้');
        }

        $filename = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $destination = $directory . '/' . $filename;

        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException(
                'บันทึกคลิปไม่ได้ กรุณาตรวจสิทธิ์โฟลเดอร์เก็บไฟล์'
            );
        }

        $savedPath = $destination;
        $memberId = user_id();

        $stmt = $conn->prepare(
            'INSERT INTO stories
                (user_id, title, caption, filename, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'issssi',
            $memberId,
            $title,
            $caption,
            $filename,
            $mime,
            $size
        );

        $stmt->execute();
        $stmt->close();

        // บันทึกสำเร็จแล้ว ไม่ต้องลบไฟล์
        $savedPath = null;

        go('stories.php');
    } catch (Throwable $e) {
        if ($savedPath !== null && is_file($savedPath)) {
            unlink($savedPath);
        }

        if (
            $e instanceof RuntimeException &&
            !($e instanceof mysqli_sql_exception)
        ) {
            $error = $e->getMessage();
        } else {
            error_log('Story upload: ' . $e->getMessage());
            $error = 'บันทึกไม่สำเร็จ กรุณาตรวจตาราง stories และการตั้งค่าเซิร์ฟเวอร์';
        }
    }
}

$page_title = 'เพิ่มสตอรี่';
require __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <h1>เพิ่มสตอรี่</h1>
    <p>เพิ่มคลิปเรื่องราวการเดินทางของคุณ</p>

    <?php if ($error !== ''): ?>
    <p role="alert"><?= h($error) ?></p>
<?php endif; ?>

    <form action="story_upload.php"
          method="post"
          enctype="multipart/form-data">

        <?php csrf_field(); ?>

        <p>
            <label for="story-title">ชื่อสตอรี่</label>
            <input
                id="story-title"
                type="text"
                name="title"
                maxlength="150"
                placeholder="เช่น เช้าวันใหม่ที่เชียงใหม่"
                required
            >
        </p>

        <p>
            <label for="story-caption">คำบรรยาย</label>
            <textarea
                id="story-caption"
                name="caption"
                maxlength="1000"
                rows="4"
                placeholder="เล่าเรื่องราวของคลิปนี้"
            ></textarea>
        </p>

        <p>
            <label for="story-video">เลือกคลิปวิดีโอ</label>
            <input
                id="story-video"
                type="file"
                name="video"
                accept="video/mp4,video/webm"
                required
            >
        </p>

        <p>รองรับไฟล์ MP4 และ WebM</p>

        <button type="submit" class="btn"> อัปโหลดสตอรี่ </button>
        <a href="stories.php" class="btn">
            กลับหน้าสตอรี่
        </a>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>