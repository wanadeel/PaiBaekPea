<?php
// Shared integration: account suspension + first-party aggregate analytics.
$conn->query("SET time_zone = '+07:00'");
$pbbAccount = null;
if (user_id()) {
    $s = $conn->prepare('SELECT user_id, role, account_status FROM users WHERE user_id=?');
    $id = user_id(); $s->bind_param('i', $id); $s->execute();
    $pbbAccount = $s->get_result()->fetch_assoc(); $s->close();
    if (!$pbbAccount || $pbbAccount['account_status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['admin_user_id'], $_SESSION['fullname'], $_SESSION['analytics_visit']);
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['flash'] = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแล';
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(403); exit('บัญชีถูกระงับ ไม่สามารถทำรายการได้');
        }
        $pbbAccount = null;
    }
}
function pbb_track_page($page, $tripId = null) {
    global $conn, $pbbAccount;
    static $tracked = false;
    $allowed = ['index.php','dashboard.php','my_trips.php','bookmarks.php','profile.php','trip_detail.php','place_view.php','login.php','register.php','add_trip.php','edit_trip.php','add_place.php','edit_place.php','edit_profile.php','report.php'];
    if ($tracked || $_SERVER['REQUEST_METHOD'] !== 'GET' || http_response_code() >= 400 || !in_array($page, $allowed, true) || ($pbbAccount['role'] ?? '') === 'admin') return;
    if (preg_match('/bot|crawler|spider|slurp|preview/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) return;
    $tracked = true;
    try {
        $token = $_COOKIE['pbb_visitor'] ?? '';
        if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            $token = bin2hex(random_bytes(32));
            $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            setcookie('pbb_visitor', $token, ['expires'=>time()+31536000,'path'=>$base ?: '/', 'httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
        $hash = hash('sha256', $token);
        $visit = $_SESSION['analytics_visit'] ?? [];
        $visitId = (int) ($visit['id'] ?? 0);
        if (!$visitId || ($visit['hash'] ?? '') !== $hash || time() - ($visit['last'] ?? 0) >= 1800) {
            $s = $conn->prepare('INSERT INTO analytics_visits(visitor_hash) VALUES (?)');
            $s->bind_param('s', $hash); $s->execute(); $visitId=(int)$conn->insert_id; $s->close();
        } else {
            $s=$conn->prepare('UPDATE analytics_visits SET last_seen=CURRENT_TIMESTAMP WHERE visit_id=?');
            $s->bind_param('i',$visitId); $s->execute(); $s->close();
        }
        $uid = user_id() ?: null;
        $s=$conn->prepare('INSERT INTO analytics_events(visit_id,visitor_hash,user_id,page_name,trip_id) VALUES(?,?,?,?,?)');
        $s->bind_param('isisi',$visitId,$hash,$uid,$page,$tripId); $s->execute(); $s->close();
        $_SESSION['analytics_visit']=['id'=>$visitId,'hash'=>$hash,'last'=>time()];
    } catch (Throwable $e) {
        // Tracking failure must not interrupt a visitor's page. Never invent counts.
        unset($_SESSION['analytics_visit']); error_log('PaiBaekPea analytics: '.$e->getMessage());
    }
}
