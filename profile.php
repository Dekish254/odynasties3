<?php
require 'config/config.php'; require_member();
$id=(int)$_SESSION['member_user']['id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='profile'){
    $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??''); $blood=$_POST['blood_group']??''; $location=trim($_POST['location']??''); $bio=trim($_POST['bio']??'');
    $allowed=['O+','O-','A+','A-','B+','B-','AB+','AB-',''];
    if($name===''){flash('error','Name is required.');}
    elseif(!in_array($blood,$allowed,true)){flash('error','Invalid blood group.');}
    else{
      $picture=$_SESSION['member_user']['profile_picture']??null;
      if(!empty($_FILES['profile_picture']['name'])){
        if($_FILES['profile_picture']['error']!==UPLOAD_ERR_OK){flash('error','Could not upload the profile picture.');}
        else{
          $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($_FILES['profile_picture']['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
          if(!$ext || $_FILES['profile_picture']['size']>3*1024*1024){flash('error','Use a JPG, PNG or WEBP image up to 3 MB.');}
          else{ $new=bin2hex(random_bytes(12)).'.'.$ext; $dest=__DIR__.'/uploads/profiles/'.$new; if(move_uploaded_file($_FILES['profile_picture']['tmp_name'],$dest)){ if($picture && is_file(__DIR__.'/uploads/profiles/'.basename($picture))) @unlink(__DIR__.'/uploads/profiles/'.basename($picture)); $picture=$new; } else flash('error','Could not save the profile picture.'); }
        }
      }
      if(empty($_SESSION['flash']) || $_SESSION['flash'][0]!=='error'){
        $pdo->prepare('UPDATE users SET name=?,phone=?,blood_group=?,location=?,bio=?,profile_picture=? WHERE id=? AND role="member"')->execute([$name,$phone?:null,$blood?:null,$location?:null,$bio?:null,$picture,$id]);
        $_SESSION['member_user']['name']=$name; $_SESSION['member_user']['blood_group']=$blood; $_SESSION['member_user']['profile_picture']=$picture;
        flash('success','Your profile has been updated.');
      }
    }
    header('Location: profile.php'); exit;
  }
  if($action==='password'){
    $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
    $s=$pdo->prepare('SELECT password_hash FROM users WHERE id=? AND role="member"');$s->execute([$id]);$u=$s->fetch();
    if(!$u||!password_verify($current,$u['password_hash'])) flash('error','Your current password is incorrect.');
    elseif(strlen($new)<8) flash('error','New password must be at least 8 characters.');
    elseif($new!==$confirm) flash('error','The new passwords do not match.');
    else{$pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$id]);flash('success','Your password has been changed successfully.');}
    header('Location: profile.php');exit;
  }
}
$s=$pdo->prepare('SELECT * FROM users WHERE id=? AND role="member"');$s->execute([$id]);$member=$s->fetch();
$mustChange=(bool)($member['must_change_password']??false); $title='My Profile'; require 'includes/header.php';
?>
<section class="section"><div class="container profile-layout"><div class="formwrap profile-card">
<div class="profile-head"><img class="profile-photo" src="<?=e(profile_image_url($member['profile_picture']??null))?>" alt="Profile picture"><div><h1>My Profile</h1><p class="muted">Manage your own account. Member accounts cannot manage other members.</p></div></div>
<?php if($mustChange): ?><div class="flash error" style="width:100%;margin:15px 0">Your administrator reset your password. Use the temporary password as your current password, then create your own password.</div><?php endif; ?>
<h2>Update Profile</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="profile"><div class="formgrid"><div class="field"><label>Full name</label><input name="name" value="<?=e($member['name'])?>" required></div><div class="field"><label>Phone</label><input name="phone" value="<?=e($member['phone'])?>"></div><div class="field"><label>Blood group</label><select name="blood_group"><option value="">Not specified</option><?php foreach(['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $bg): ?><option value="<?=$bg?>" <?=$member['blood_group']===$bg?'selected':''?>><?=$bg?></option><?php endforeach; ?></select></div><div class="field"><label>Location</label><input name="location" value="<?=e($member['location']??'')?>" placeholder="Town / county"></div></div><div class="field"><label>Short bio</label><textarea name="bio" placeholder="Tell the community a little about yourself"><?=e($member['bio']??'')?></textarea></div><div class="field"><label>Profile picture</label><input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG or WEBP, maximum 3 MB.</small></div><button class="btn">Save Profile</button></form>
<hr style="border:0;border-top:1px solid #e2e7ea;margin:28px 0"><h2>Change Password</h2><form method="post"><input type="hidden" name="action" value="password"><div class="field"><label>Current password</label><input type="password" name="current_password" required></div><div class="field"><label>New password</label><input type="password" name="new_password" minlength="8" required></div><div class="field"><label>Confirm new password</label><input type="password" name="confirm_password" minlength="8" required></div><button class="btn">Change Password</button></form><p style="margin-top:20px"><a class="mini-btn" href="logout.php?role=member">Log Out</a></p>
</div></div></section><?php require 'includes/footer.php'; ?>
