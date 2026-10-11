<?php
require_once __DIR__.'/common.php';
$q=mb_substr(astr('q'),0,100);$status=astr('status');$id=max(0,(int)astr('id','0'));$page=apage();
$message=apost(function(){
 $id=aid('trip_id');$reason=areason();$action=text_value('action',20,true);$expected=text_value('expected_status',20,true);
 if(!in_array($action,['hide','restore'],true)) throw new RuntimeException('คำสั่งไม่ถูกต้อง');
 $rows=aq("SELECT visibility,moderation_status FROM trips WHERE trip_id=? FOR UPDATE",'i',[$id]);
 if(!$rows || $rows[0]['visibility']!=='public') throw new RuntimeException('ไม่พบทริปสาธารณะ ทริปอาจถูกลบหรือเปลี่ยนเป็นส่วนตัว');
 $before=$rows[0]['moderation_status'];$after=$action==='hide'?'hidden':'visible';
 if($expected!==$before || $before===$after) throw new RuntimeException('สถานะเปลี่ยนไปแล้ว กรุณาโหลดรายการล่าสุด');
 aq('UPDATE trips SET moderation_status=?,moderation_reason=? WHERE trip_id=?','ssi',[$after,$action==='hide'?$reason:null,$id]);
 alog($action==='hide'?'hide_trip':'restore_trip','trip',$id,$reason,$before,$after);
},'trips.php?'.http_build_query(['q'=>$q,'status'=>$status,'id'=>$id,'page'=>$page]));
$where="t.visibility='public'";$types='';$params=[];
if($q!==''){$where.=' AND (t.title LIKE ? OR u.fullname LIKE ?)';$types.='ss';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
if(in_array($status,['visible','hidden'],true)){$where.=' AND t.moderation_status=?';$types.='s';$params[]=$status;}
if($id){$where.=' AND t.trip_id=?';$types.='i';$params[]=$id;}
$params[]=($page-1)*20;$types.='i';
$rows=aq("SELECT t.trip_id,t.title,t.created_at,t.moderation_status,t.moderation_reason,u.fullname FROM trips t JOIN users u ON u.user_id=t.user_id WHERE $where ORDER BY t.created_at DESC,t.trip_id DESC LIMIT 21 OFFSET ?",$types,$params);
$next=count($rows)>20;if($next)array_pop($rows);
$page_title='จัดการโพสต์ทริป';$page_intro='ตรวจสอบเฉพาะโพสต์สาธารณะ ซ่อนหรือคืนสถานะโดยระบุเหตุผล';require 'header.php';
?>
<section class="panel"><form class="filters" method="get"><label>ค้นหาทริปหรือผู้โพสต์<input name="q" value="<?= h($q) ?>" maxlength="100"></label><label>สถานะ<select name="status"><option value="">ทั้งหมด</option><option value="visible" <?= $status==='visible'?'selected':'' ?>>เผยแพร่</option><option value="hidden" <?= $status==='hidden'?'selected':'' ?>>ถูกซ่อน</option></select></label><button class="btn">ค้นหา</button><a class="btn secondary" href="trips.php">ล้างตัวกรอง</a></form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>ทริป / ผู้โพสต์</th><th>วันที่โพสต์</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody><?php foreach($rows as $r): $hidden=$r['moderation_status']==='hidden'; ?><tr><td><a href="../trip_detail.php?id=<?= (int)$r['trip_id'] ?>"><?= h($r['title']) ?></a><p class="muted"><?= h($r['fullname']) ?> · #<?= (int)$r['trip_id'] ?></p></td><td><?= h($r['created_at']) ?></td><td><span class="badge"><?= $hidden?'ถูกซ่อน':'เผยแพร่' ?></span><p class="admin-log-reason"><?= h($r['moderation_reason']??'') ?></p></td><td><form method="post" class="reason-form"><?php csrf_field(); ?><input type="hidden" name="trip_id" value="<?= (int)$r['trip_id'] ?>"><input type="hidden" name="action" value="<?= $hidden?'restore':'hide' ?>"><input type="hidden" name="expected_status" value="<?= h($r['moderation_status']) ?>"><label>เหตุผล (ไม่เกิน 500 ตัวอักษร)<textarea name="reason" maxlength="500" required></textarea></label><button class="btn small <?= $hidden?'':'danger' ?>"><?= $hidden?'คืนสถานะโพสต์':'ซ่อนโพสต์' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if(!$rows): ?><p class="empty">ไม่พบโพสต์สาธารณะตามเงื่อนไข</p><?php endif; ?><?php apager($next,$page); ?></section><?php require 'footer.php'; ?>
