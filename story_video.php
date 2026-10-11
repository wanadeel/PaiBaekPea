<?php
require __DIR__ . '/includes/init.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    exit;
}

$stmt = $conn->prepare(
    "SELECT s.filename, s.mime_type
     FROM stories s
     JOIN users u ON u.user_id = s.user_id
     WHERE s.story_id = ?
       AND u.account_status = 'active'"
);

$stmt->bind_param('i', $id);
$stmt->execute();
$story = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (
    !$story ||
    !preg_match('/\A[a-f0-9]{48}\.(mp4|webm)\z/', $story['filename']) ||
    !in_array($story['mime_type'], ['video/mp4', 'video/webm'], true)
) {
    http_response_code(404);
    exit;
}

$path = UPLOAD_DIR . '/story-videos/' . $story['filename'];

if (!is_file($path) || is_link($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$handle = fopen($path, 'rb');

if ($handle === false) {
    http_response_code(404);
    exit;
}

$size = (int) fstat($handle)['size'];

if ($size < 1) {
    fclose($handle);
    http_response_code(404);
    exit;
}

$start = 0;
$end = $size - 1;
$partial = false;

header('Accept-Ranges: bytes');

// HEAD ส่งเฉพาะข้อมูลของไฟล์ ไม่ส่งเนื้อหาวิดีโอ
$range = $method === 'GET' ? ($_SERVER['HTTP_RANGE'] ?? '') : '';

if ($range !== '') {
    $valid = preg_match('/\Abytes=(\d*)-(\d*)\z/', $range, $matches);

    if (
        !$valid ||
        ($matches[1] === '' && $matches[2] === '')
    ) {
        fclose($handle);
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        header('Content-Length: 0');
        exit;
    }

    if ($matches[1] === '') {
        $suffix = (int) $matches[2];
        $start = max(0, $size - $suffix);

        if ($suffix < 1) {
            $start = $size;
        }
    } else {
        $start = (int) $matches[1];

        if ($matches[2] !== '') {
            $end = min((int) $matches[2], $size - 1);
        }
    }

    if ($start >= $size || $start > $end) {
        fclose($handle);
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        header('Content-Length: 0');
        exit;
    }

    $partial = true;
}

session_write_close();

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $story['mime_type']);
header('Content-Length: ' . ($end - $start + 1));

if ($partial) {
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

if ($method === 'HEAD') {
    fclose($handle);
    exit;
}

fseek($handle, $start);
$remaining = $end - $start + 1;

while (
    $remaining > 0 &&
    !feof($handle) &&
    !connection_aborted()
) {
    $chunk = fread($handle, min(1024 * 1024, $remaining));

    if ($chunk === false || $chunk === '') {
        break;
    }

    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}

fclose($handle);
exit;