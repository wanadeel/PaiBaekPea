<?php
require_once __DIR__.'/auth.php';
$admin = require_admin();
function aq($sql, $types='', $values=[]) {
    global $conn;
    $s=$conn->prepare($sql);
    if ($types !== '') $s->bind_param($types, ...$values);
    $s->execute(); $result=$s->get_result();
    $rows=$result ? $result->fetch_all(MYSQLI_ASSOC) : []; $s->close(); return $rows;
}
function av($sql,$types='',$values=[]) { $rows=aq($sql,$types,$values); return $rows ? array_values($rows[0])[0] : 0; }
function astr($key,$default='') { $v=$_GET[$key] ?? $default; return is_string($v) ? trim($v) : $default; }
function apage() { return max(1,min(100000,(int)astr('page','1'))); }
function aid($key) { $id=filter_var($_POST[$key] ?? null,FILTER_VALIDATE_INT); if (!$id || $id<1) throw new RuntimeException('รหัสรายการไม่ถูกต้อง'); return $id; }
function areason() { return text_value('reason',500,true); }
function alog($action,$type,$id,$reason,$before,$after) {
    global $admin;
    aq('INSERT INTO admin_logs(admin_id,action,target_type,target_id,reason,before_status,after_status) VALUES(?,?,?,?,?,?,?)','ississs',[(int)$admin['user_id'],$action,$type,$id,$reason,$before,$after]);
}
function actor_lock() {
    global $admin;
    $rows=aq('SELECT role,account_status FROM users WHERE user_id=? FOR UPDATE','i',[(int)$admin['user_id']]);
    if (!$rows || $rows[0]['role']!=='admin' || $rows[0]['account_status']!=='active') throw new RuntimeException('ไม่มีสิทธิ์ดำเนินการ');
}
function apost($callback,$destination) {
    global $conn;
    if ($_SERVER['REQUEST_METHOD']!=='POST') return '';
    check_csrf();
    try {
        $conn->begin_transaction(); actor_lock(); $callback(); $conn->commit();
        $_SESSION['admin_notice']='บันทึกข้อมูลและประวัติเรียบร้อยแล้ว';
        header('Location: '.$destination, true, 303); exit;
    } catch (Throwable $e) {
        $conn->rollback(); error_log($e->getMessage());
        return $e instanceof mysqli_sql_exception ? 'บันทึกไม่ได้ กรุณาตรวจข้อมูลซ้ำหรือโครงสร้างฐานข้อมูล' : ($e instanceof RuntimeException ? $e->getMessage() : 'เกิดข้อผิดพลาด กรุณาลองใหม่');
    }
}
function afmt($value) { return number_format((float)$value); }
function abytes($value) { return $value >= 1073741824 ? number_format($value/1073741824,2).' GB' : number_format($value/1048576,2).' MB'; }
function apager($hasNext,$page) {
    $params=$_GET; unset($params['page']);
    echo '<nav class="actions" aria-label="เปลี่ยนหน้า">';
    if($page>1) echo '<a class="btn secondary" href="?'.h(http_build_query($params+['page'=>$page-1])).'">← ก่อนหน้า</a>';
    echo '<span>หน้า '.(int)$page.'</span>';
    if($hasNext) echo '<a class="btn secondary" href="?'.h(http_build_query($params+['page'=>$page+1])).'">ถัดไป →</a>';
    echo '</nav>';
}
function arange() {
    $today=new DateTimeImmutable('today'); $preset=astr('period','month'); $error='';
    $end=$today;
    switch($preset) {
        case 'today': $start=$today; break;
        case 'week': $start=$today->modify('-6 days'); break;
        case 'year': $start=$today->modify('first day of January this year'); break;
        case 'custom':
            $s=astr('from');$e=astr('to');
            if (!valid_date($s)||!valid_date($e)||$s>$e || $e>$today->format('Y-m-d') || (new DateTimeImmutable($s))->diff(new DateTimeImmutable($e))->days>1826) {
                $error='กรุณาเลือกวันที่เริ่ม–สิ้นสุดให้ถูกต้อง ไม่เกินวันนี้ และช่วงไม่เกิน 5 ปี';
                $start=$today->modify('first day of this month'); $preset='month';
            } else { $start=new DateTimeImmutable($s);$end=new DateTimeImmutable($e); }
            break;
        default: $preset='month'; $start=$today->modify('first day of this month');
    }
    return [$preset,$start,$end,$error];
}
function achart($series,$kind,$label) {
    if (!$series) { echo '<p class="empty">ยังไม่มีข้อมูล</p>';return; }
    $count=count($series);$max=max(1,...array_values($series));$points=[];$i=0;
    echo '<svg class="chart" viewBox="0 0 720 250" role="img" aria-label="'.h($label).'">';
    for($g=0;$g<=4;$g++) { $y=210-$g*45;echo '<line class="gridline" x1="50" y1="'.$y.'" x2="700" y2="'.$y.'"/><text x="3" y="'.($y+4).'">'.h(number_format($max*$g/4,1)).'</text>'; }
    foreach($series as $date=>$n) {
        $step=640/max(1,$count);$x=50+($i+0.5)*$step;$height=180*$n/$max;$y=210-$height;
        $points[]=$x.','.$y;
        if($kind==='bar') echo '<rect class="bar" x="'.($x-$step*.32).'" y="'.$y.'" width="'.max(1,$step*.64).'" height="'.$height.'"><title>'.h($date.' : '.$n).'</title></rect>';
        else echo '<circle class="dot" cx="'.$x.'" cy="'.$y.'" r="3"><title>'.h($date.' : '.$n).'</title></circle>';
        if($i%max(1,(int)ceil($count/8))===0) echo '<text text-anchor="middle" x="'.$x.'" y="236">'.h($date).'</text>';
        $i++;
    }
    if($kind!=='bar') echo '<polyline class="trend" points="'.h(implode(' ',$points)).'"/>';
    echo '</svg><details><summary>ดูตัวเลขในตาราง</summary><div class="table-wrap"><table><thead><tr><th>ช่วงเวลา</th><th>จำนวน</th></tr></thead><tbody>';
    foreach($series as $date=>$n) echo '<tr><td>'.h($date).'</td><td>'.afmt($n).'</td></tr>';
    echo '</tbody></table></div></details>';
}
