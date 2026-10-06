<?php
require 'config/config.php'; require_member();
$title='Odynasties — Community Home';

/* Members active in the last 5 minutes. Admin sessions are deliberately excluded
   from this member-facing list. */
$onlineStmt = $pdo->query("
  SELECT u.id,u.name,u.profile_picture,MAX(ls.last_seen) AS last_seen
  FROM login_sessions ls
  INNER JOIN users u ON u.id=ls.user_id
  WHERE ls.role='member'
    AND ls.revoked_at IS NULL
    AND u.role='member'
    AND u.status='active'
    AND ls.last_seen >= (NOW() - INTERVAL 5 MINUTE)
  GROUP BY u.id,u.name,u.profile_picture
  ORDER BY MAX(ls.last_seen) DESC
  LIMIT 30
");
$onlineMembers = $onlineStmt->fetchAll();
require 'includes/header.php';
?>
<style>
.member-hero{background:linear-gradient(135deg,#071722,#132b38);color:#fff;border-radius:20px;padding:44px 42px;margin-bottom:28px;position:relative;overflow:hidden}
.member-hero:after{content:'O';position:absolute;right:35px;top:-50px;font-size:240px;font-weight:900;color:rgba(255,255,255,.04);line-height:1}
.member-hero h1{font-size:clamp(34px,5vw,58px);margin:0 0 10px;position:relative;z-index:1}.member-hero p{max-width:760px;font-size:18px;line-height:1.7;color:#d9e6eb;position:relative;z-index:1}
.member-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.member-ad{background:#fff;border:1px solid #e1e8eb;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(12,31,41,.06);display:flex;flex-direction:column}.member-ad .icon{font-size:34px;margin-bottom:10px}.member-ad h2{margin:0 0 8px;font-size:21px}.member-ad p{color:#52636c;line-height:1.6;flex:1}.member-ad .btn{align-self:flex-start;margin-top:12px}.member-strip{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-top:22px}.member-strip .panel{border-radius:16px;padding:24px;background:#f7fafb;border:1px solid #e2eaed}.member-strip h2{margin-top:0}.member-note{background:#fff6f6;border-left:5px solid #d71920;padding:18px;border-radius:10px;margin-top:24px;color:#4d555a}

.online-section{margin-top:28px;background:#fff;border:1px solid #e1e8eb;border-radius:18px;padding:24px;box-shadow:0 8px 24px rgba(12,31,41,.06)}
.online-heading{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:18px}
.online-heading h2{margin:0}.online-count{font-size:13px;color:#4f626c;background:#eef7f1;border-radius:999px;padding:7px 12px}
.online-list{display:flex;flex-wrap:wrap;gap:16px}
.online-person{display:flex;align-items:center;gap:11px;min-width:190px;padding:10px 14px;border:1px solid #e6ecef;border-radius:14px;background:#fbfcfd}
.online-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 1px 7px rgba(0,0,0,.14)}
.online-name{font-weight:800;color:#182932}.online-status{font-size:12px;color:#26834a;margin-top:2px}.online-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#26a269;margin-right:5px}
.empty-online{color:#60717a;margin:0}
@media(max-width:900px){.member-grid{grid-template-columns:1fr 1fr}}@media(max-width:620px){.member-grid,.member-strip{grid-template-columns:1fr}.member-hero{padding:30px 24px}}
</style>
<section class="section"><div class="container">
  <div class="member-hero">
    <h1>Welcome to Odynasties, <?=e($_SESSION['member_user']['name'] ?? 'Member')?>.</h1>
    <p>You are part of a community built around connection, blood donation, support, sharing and giving back. Explore the opportunities below and take part in the Odynasties community.</p>
  </div>

  <section class="online-section">
    <div class="online-heading">
      <h2>Members Online</h2>
      <span class="online-count"><?=count($onlineMembers)?> online now</span>
    </div>
    <?php if($onlineMembers): ?>
      <div class="online-list">
        <?php foreach($onlineMembers as $person): ?>
          <div class="online-person">
            <img class="online-avatar" src="<?=e(profile_image_url($person['profile_picture']))?>" alt="<?=e($person['name'])?>">
            <div>
              <div class="online-name"><?=e($person['name'])?></div>
              <div class="online-status"><span class="online-dot"></span>Online now</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty-online">No other members are currently active.</p>
    <?php endif; ?>
  </section>

  <div class="member-grid">
    <article class="member-ad"><div class="icon">🩸</div><h2>Donate Blood & Save Lives</h2><p>Be ready to donate when someone needs help. Learn about blood donation, register as a donor and help strengthen the community.</p><a class="btn" href="donate.php">Blood Donation</a></article>
    <article class="member-ad"><div class="icon">❤️</div><h2>Support Odynasties</h2><p>Every contribution counts. Support the project with whatever you can afford and help the community grow, serve and reach more people.</p><a class="btn" href="donate.php">Support the Project</a></article>
    <article class="member-ad"><div class="icon">🤝</div><h2>Community Support</h2><p>Need assistance or know someone who does? Make a support request, see community requests and stand with other members.</p><a class="btn" href="support.php">Support Requests</a></article>
    <article class="member-ad"><div class="icon">📅</div><h2>Join Community Events</h2><p>Keep up with meetups, blood drives, campaigns and other Odynasties activities.</p><a class="btn" href="events.php">View Events</a></article>
    <article class="member-ad"><div class="icon">📰</div><h2>Latest Odynasties News</h2><p>Read community updates, announcements, achievements and stories from the Odynasties family.</p><a class="btn" href="news.php">Read News</a></article>
    <article class="member-ad"><div class="icon">👥</div><h2>Connect With Members</h2><p>Discover the community and connect with people who share the same blood group and the same spirit of support.</p><a class="btn" href="members.php">View Members</a></article>
  </div>

  <div class="member-strip">
    <div class="panel"><h2>Share Your Story</h2><p>Have an experience, achievement or testimony you would like the community to hear? Share it and help encourage others.</p><a class="mini-btn" href="contact.php">Contact Odynasties</a></div>
    <div class="panel"><h2>Need to Update Your Details?</h2><p>Your profile is kept separate from this community home. Update your name, phone, blood group, bio, profile picture or password only when you choose.</p><a class="mini-btn" href="profile.php">My Profile</a></div>
  </div>

  <div class="member-note"><strong>Remember:</strong> Your profile is not automatically opened when you log in. Use <strong>My Profile</strong> in the navigation whenever you want to manage your personal information.</div>
</div></section>
<?php require 'includes/footer.php'; ?>
