<?php
require_once __DIR__.'/common.php';
$status=astr('status','pending');$page=apage();
$message=apost(function(){
 $id=aid('report_id');$reason=areason();$after=text_value('status',20,true);$expected=text_value('expected_status',20,true);
 if(!in_array($after,['pending','resolved','dismissed'],true))throw new RuntimeException('สถานะไม่ถูกต้อง');
 $r=aq('SELECT status FROM reports WHERE report_id=? FOR UPDATE','i',[$id]);
 if(!$r || $r[0]['status']!==$expected || $r[0]['status']===$after)throw new RuntimeException('สถานะเปลี่ยนไปแล้วหรือเป็นสถานะเดิม กรุณาโหลดหน้าใหม่');
 global $admin;
 aq('UPDATE reports SET status=?,resolution=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE report_id=?','ssii',[$after,$reason,(int)$admin['user_id'],$id]);
 alog('review_report','report',$id,$reason,$r[0]['status'],$after);
},'reports.php?'.http_build_query(['status'=>$status,'page'=>$page]));
$where='1=1';$params=[];$types='';if(in_array($status,['pending','resolved','dismissed'],true)){$where='r.status=?';$types='s';$params[]=$status;}
$params[]=($page-1)*20;$types.='i';
$rows=aq("SELECT r.*,u.fullname reporter_name FROM reports r JOIN users u ON u.user_id=r.reporter_id WHERE $where ORDER BY r.created_at DESC,r.report_id DESC LIMIT 21 OFFSET ?",$types,$params);
$next=count($rows)>20;if($next)array_pop($rows);$labels=['pending'=>'รอตรวจสอบ','resolved'=>'ดำเนินการแล้ว','dismissed'=>'ยุติรายงาน'];
$page_title='รายงานปัญหา';$page_intro='เปิดตรวจรายการที่ถูกรายงาน แล้วบันทึกผลการดำเนินการ';require 'header.php';
?>
<section class="panel"><form class="filters" method="get"><label>สถานะ<select name="status"><option value="all">ทั้งหมด</option><?php foreach($labels as $k=>$v): ?><option value="<?= h($k) ?>" <?= $status===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></label><button class="btn">แสดงรายงาน</button></form></section>
<section class="panel"><?php if(!$rows): ?><p class="empty">ไม่มีรายงานตามเงื่อนไข</p><?php endif; ?><?php foreach($rows as $r): ?><article class="review"><div class="section-top"><h2>รายงาน #<?= (int)$r['report_id'] ?> · <?= $r['target_type']==='trip'?'ทริป':'สมาชิก' ?> #<?= (int)$r['target_id'] ?></h2><span class="badge"><?= h($labels[$r['status']]) ?></span></div><p class="muted">ผู้รายงาน <?= h($r['reporter_name']) ?> · <?= h($r['created_at']) ?></p><p class="wrap-text"><?= h($r['reason']) ?></p><div class="actions"><?php if($r['target_type']==='trip'): ?><a class="btn secondary small" href="../trip_detail.php?id=<?= (int)$r['target_id'] ?>">เปิดทริป</a><a class="btn secondary small" href="trips.php?id=<?= (int)$r['target_id'] ?>">จัดการโพสต์</a><?php else: ?><a class="btn secondary small" href="../profile.php?id=<?= (int)$r['target_id'] ?>">เปิดโปรไฟล์</a><a class="btn secondary small" href="members.php?id=<?= (int)$r['target_id'] ?>">จัดการสมาชิก</a><?php endif; ?></div><p class="muted">ถ้ารายการถูกลบหรือทริปเปลี่ยนเป็นส่วนตัว จะเปิดดูไม่ได้ ประวัติรายงานยังคงอยู่</p><?php if($r['resolution']): ?><p class="wrap-text">ผลเดิม: <?= h($r['resolution']) ?></p><?php endif; ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>"><input type="hidden" name="expected_status" value="<?= h($r['status']) ?>"><label>สถานะใหม่<select name="status"><?php foreach($labels as $k=>$v): ?><option value="<?= h($k) ?>" <?= $r['status']===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></label><label>ผลการตรวจสอบ / เหตุผล<textarea name="reason" maxlength="500" required></textarea></label><button class="btn">บันทึกผล</button></form></article><?php endforeach; ?><?php apager($next,$page); ?></section><?php require 'footer.php'; ?>
