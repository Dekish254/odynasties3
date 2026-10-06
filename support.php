<?php
require 'config/config.php';
if(is_member() && $_SERVER['REQUEST_METHOD']==='POST'){
  $titleReq=trim($_POST['title']??''); $category=trim($_POST['category']??'Other'); $description=trim($_POST['description']??'');
  $allowed=['Financial','Medical','Emergency','Education','Family','Other'];
  if($titleReq===''||$description==='') flash('error','Please provide a title and describe the support you need.');
  elseif(!in_array($category,$allowed,true)) flash('error','Invalid support category.');
  else{$pdo->prepare('INSERT INTO support_requests(user_id,title,category,description) VALUES(?,?,?,?)')->execute([(int)$_SESSION['member_user']['id'],$titleReq,$category,$description]);flash('success','Your support request has been posted. The admin can now review it.');}
  header('Location: support.php');exit;
}
$rows=$pdo->query("SELECT sr.*,u.name,u.profile_picture FROM support_requests sr LEFT JOIN users u ON u.id=sr.user_id WHERE sr.status IN ('open','in_progress') ORDER BY sr.created_at DESC")->fetchAll();
$title='Community Support Requests'; require 'includes/header.php'; ?>
<section class="section"><div class="container"><div class="admin-heading"><div><h1>Community Support Requests</h1><p class="muted">Members can ask for support and the community can see requests that are still open.</p></div></div>
<?php if(is_member()): ?><div class="formwrap" style="max-width:none"><h2>Make a Support Request</h2><form method="post"><div class="formgrid"><div class="field"><label>Request title</label><input name="title" required placeholder="What support do you need?"></div><div class="field"><label>Category</label><select name="category"><?php foreach(['Financial','Medical','Emergency','Education','Family','Other'] as $c): ?><option><?=$c?></option><?php endforeach; ?></select></div></div><div class="field"><label>Explain the request</label><textarea name="description" required placeholder="Tell the community how they can help..."></textarea></div><button class="btn">Post Support Request</button></form></div><?php else: ?><div class="notice-box">Log in as a member to make a support request.</div><?php endif; ?>
<div class="support-grid"><?php if(!$rows): ?><div class="card"><div class="cardbody"><p class="muted">There are no open support requests right now.</p></div></div><?php endif; ?><?php foreach($rows as $r): ?><article class="card support-card"><div class="cardbody"><div class="support-person"><img src="<?=e(profile_image_url($r['profile_picture']??null))?>" alt=""><div><strong><?=e($r['name']?:'Community Member')?></strong><div class="muted smalltext"><?=e(date('d M Y',strtotime($r['created_at'])))?> · <?=e($r['category'])?></div></div></div><h3><?=e($r['title'])?></h3><p><?=nl2br(e($r['description']))?></p><span class="status <?=e($r['status'])?>"><?=e(str_replace('_',' ',ucfirst($r['status'])))?></span></div></article><?php endforeach; ?></div></div></section><?php require 'includes/footer.php'; ?>
