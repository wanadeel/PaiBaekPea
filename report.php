<?php
require 'includes/init.php';require_login();
$type=$_POST['target_type']??$_GET['type']??'';$rawId=$_POST['target_id']??$_GET['id']??null;
$id=filter_var($rawId,FILTER_VALIDATE_INT);
if(!is_string($type)||!in_array($type,['trip','user'],true)||!$id||$id<1)not_found();
if($type==='trip'){
 $target=get_trip($id);if($target['visibility']!=='public'||$target['moderation_status']!=='visible'||(int)$target['user_id']===user_id())not_found();$label=$target['title'];
}else{
 $s=$conn->prepare('SELECT fullname FROM users WHERE user_id=?');$s->bind_param('i',$id);$s->execute();$target=$s->get_result()->fetch_assoc();$s->close();
 if(!$target||$id===user_id())not_found();$label=$target['fullname'];
}
$message='';$reason='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$inTransaction=false;
 try{
  $reason=text_value('reason',1000,true);$uid=user_id();
  $conn->begin_transaction();$inTransaction=true;
  // Serialize this member's submissions, enforce current suspension status and rate limit.
  $s=$conn->prepare('SELECT account_status FROM users WHERE user_id=? FOR UPDATE');$s->bind_param('i',$uid);$s->execute();$account=$s->get_result()->fetch_assoc();$s->close();
  if(!$account||$account['account_status']!=='active')throw new RuntimeException('บัญชีไม่มีสิทธิ์ส่งรายงาน');
  $s=$conn->prepare("SELECT COUNT(*) n FROM reports WHERE reporter_id=? AND (created_at>=CURRENT_TIMESTAMP - INTERVAL 1 HOUR)");$s->bind_param('i',$uid);$s->execute();$n=$s->get_result()->fetch_assoc()['n'];$s->close();
  if($n>=10)throw new RuntimeException('ส่งรายงานครบ 10 เรื่องในชั่วโมงนี้แล้ว กรุณาลองภายหลัง');
  $s=$conn->prepare("SELECT report_id FROM reports WHERE reporter_id=? AND target_type=? AND target_id=? AND status='pending'");$s->bind_param('isi',$uid,$type,$id);$s->execute();$duplicate=$s->get_result()->fetch_assoc();$s->close();
  if($duplicate)throw new RuntimeException('คุณส่งรายงานรายการนี้แล้ว และอยู่ระหว่างตรวจสอบ');
  $s=$conn->prepare('INSERT INTO reports(reporter_id,target_type,target_id,reason) VALUES(?,?,?,?)');$s->bind_param('isis',$uid,$type,$id,$reason);$s->execute();$s->close();$conn->commit();$inTransaction=false;
  flash('ส่งรายงานให้ผู้ดูแลตรวจสอบแล้ว');go($type==='trip'?'trip_detail.php?id='.$id:'profile.php?id='.$id);
 }catch(Throwable $e){if($inTransaction)$conn->rollback();$message=error_message($e);}
}
$page_title='รายงานปัญหา';require 'includes/header.php';
?>
<section class="panel"><h1>รายงาน<?= $type==='trip'?'โพสต์ทริป':'สมาชิก' ?></h1><p><?= h($label) ?></p><?php if($message): ?><p class="error" role="alert"><?= h($message) ?></p><?php endif; ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="target_type" value="<?= h($type) ?>"><input type="hidden" name="target_id" value="<?= (int)$id ?>"><label for="report-reason">อธิบายปัญหาที่พบ (ไม่เกิน 1,000 ตัวอักษร)</label><textarea id="report-reason" name="reason" maxlength="1000" rows="5" required><?= h($reason) ?></textarea><div class="actions"><button class="btn">ส่งรายงาน</button><a class="btn secondary" href="<?= $type==='trip'?'trip_detail.php':'profile.php' ?>?id=<?= (int)$id ?>">ยกเลิก</a></div></form></section>
<?php require 'includes/footer.php'; ?>
