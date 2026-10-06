<?php require_once __DIR__.'/../config/config.php'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title ?? 'Odynasties') ?></title><link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>"></head><body>
<header class="top"><div class="container nav"><a class="brand" href="<?=base_url('')?>"><img src="<?=base_url('assets/images/logo.svg')?>" alt="Odynasties"> <span>Odynasties<small>ONE BLOOD GROUP. ONE COMMUNITY. ONE DYNASTY.</small></span></a><button class="menu" onclick="document.body.classList.toggle('open')">☰</button><nav>
<?php if(is_admin()): ?>
  <a href="<?=base_url('admin/')?>">Dashboard</a>
  <a href="<?=base_url('admin/members.php')?>">Members</a>
  <a href="<?=base_url('admin/online-users.php')?>">Online Users</a>
  <a href="<?=base_url('admin/administrators.php')?>">Administrators</a>
  <a href="<?=base_url('admin/reports.php')?>">Reports</a>
  <a href="<?=base_url('admin/support.php')?>">Support Requests</a>
  <a href="<?=base_url('admin/donations.php')?>">Donations</a>
  <a href="<?=base_url('admin/events.php')?>">Events</a>
  <a href="<?=base_url('admin/news.php')?>">News</a>
  <a href="<?=base_url('admin/messages.php')?>">Messages</a>
  <a href="<?=base_url('logout.php?role=admin')?>">Logout</a>
<?php elseif(is_member()): ?>
  <a href="<?=base_url('about.php')?>">About</a>
  <a href="<?=base_url('members.php')?>">Members</a>
  <a href="<?=base_url('donate.php')?>">Blood Donation</a>
  <a href="<?=base_url('support.php')?>">Support Requests</a>
  <a href="<?=base_url('events.php')?>">Events</a>
  <a href="<?=base_url('news.php')?>">News</a>
  <a href="<?=base_url('contact.php')?>">Contact</a>
  <a href="<?=base_url('profile.php')?>">My Profile</a>
  <a href="<?=base_url('logout.php?role=member')?>">Logout</a>
<?php else: ?>
  <a href="<?=base_url('')?>">Login</a>
  <a class="join" href="<?=base_url('register.php')?>">Join Now</a>
<?php endif; ?></nav></div></header>
<?php show_flash(); ?>
