<?php
require_once __DIR__.'/common.php';
$message=apost(function(){
 $action=text_value('action',20,true);$name=text_value('name',100,true);$reason=areason();
 if($action==='create'){
   aq('INSERT INTO categories(name) VALUES(?)','s',[$name]);global $conn;$id=(int)$conn->insert_id;
   alog('create_category','category',$id,$reason,null,null);
 } elseif($action==='edit'){
   $id=aid('category_id');$expected=text_value('expected_name',100,true);
   $rows=aq('SELECT name FROM categories WHERE category_id=? FOR UPDATE','i',[$id]);
   if(!$rows || $rows[0]['name']!==$expected)throw new RuntimeException('หมวดหมู่เปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่');
   aq('UPDATE categories SET name=? WHERE category_id=?','si',[$name,$id]);
   // Preserve full old/new names without exceeding admin_logs status VARCHAR(50).
   $detail=mb_substr($rows[0]['name'].' → '.$name.' | '.$reason,0,500);
   alog('edit_category','category',$id,$detail,null,null);
 } else throw new RuntimeException('คำสั่งไม่ถูกต้อง');
},'categories.php');
$rows=aq('SELECT c.category_id,c.name,COUNT(t.trip_id) total FROM categories c LEFT JOIN trips t ON t.category_id=c.category_id GROUP BY c.category_id,c.name ORDER BY c.category_id');
$page_title='จัดการหมวดหมู่';$page_intro='เพิ่มหรือแก้ไขประเภททริป โดยยังเชื่อมกับโพสต์เดิม';require 'header.php';
?>
<section class="panel"><h2>เพิ่มหมวดหมู่</h2><form method="post" class="filters"><?php csrf_field(); ?><input type="hidden" name="action" value="create"><label>ชื่อหมวดหมู่<input name="name" maxlength="100" required></label><label>เหตุผล<input name="reason" maxlength="500" required></label><button class="btn">เพิ่มหมวดหมู่</button></form></section>
<section class="panel"><h2>หมวดหมู่ทั้งหมด</h2><?php foreach($rows as $r): ?><form class="filters review" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="category_id" value="<?= (int)$r['category_id'] ?>"><input type="hidden" name="expected_name" value="<?= h($r['name']) ?>"><label>ชื่อหมวดหมู่ · <?= afmt($r['total']) ?> ทริป<input name="name" value="<?= h($r['name']) ?>" maxlength="100" required></label><label>เหตุผลที่แก้ไข<input name="reason" maxlength="500" required></label><button class="btn secondary">บันทึกชื่อ</button></form><?php endforeach; ?></section><?php require 'footer.php'; ?>
