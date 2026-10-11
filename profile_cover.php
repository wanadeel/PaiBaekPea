<?php
require __DIR__ . '/includes/init.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit;
}
$stmt = $conn->prepare('SELECT profile_cover FROM users WHERE user_id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$filename = $row['profile_cover'] ?? '';
if (!preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp)\z/', $filename)) {
    http_response_code(404);
    exit;
}
$path = UPLOAD_DIR . '/' . $filename;
if (!is_file($path) || is_link($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}
$types = ['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
session_write_close();
header('Content-Type: ' . $types[pathinfo($filename, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($path));
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    readfile($path);
}
exit;
