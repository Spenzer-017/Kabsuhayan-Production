<!-- PHP Logic (Authentication, Session, etc.) -->
<?php 
  /*
    index.php - CvSU Marketplace Home Page
    Visible to both guests and logged-in users.
  */

  session_start();

  require_once "includes/db.php";
  
  $user = $_SESSION['user'] ?? null; 
?>

<!-- PHP UI/UX Logic -->
<?php  
  $activePage = "home"; 
  $pageTitle = "Home";
  include "includes/header.php"; 
?>

<!-- PHP Database Query -->
<?php
  // PHP Icons Variable To Use With Categories
  $icons = [
    'Books' => $bookIcon,
    'Electronics' => $electronicsIcon,
    'Supplies' => $suppliesIcon,
    'Clothing' => $clothesIcon,
    'Food' => $foodIcon,
    'Accessories' => $accessoriesIcon,
  ];

  // Features Database Query
  $stmt = $pdo->query("
    SELECT 
      items.item_id,
      items.title,
      items.price,
      items.image_path,
      items.meetup_location AS location,
      categories.name AS category,
      users.name AS seller
    FROM items
    JOIN users ON items.seller_id = users.id
    JOIN categories ON items.category_id = categories.category_id
    WHERE items.status = 'active'
    ORDER BY items.created_at DESC
    LIMIT 8
  ");

  $featured = $stmt->fetchAll();
  
  // Categories Database Query
  $stmt = $pdo->query("
    SELECT 
      categories.category_id,
      categories.name,
      COUNT(CASE WHEN items.status = 'active' THEN 1 END) AS count
    FROM categories
    LEFT JOIN items ON items.category_id = categories.category_id
    WHERE categories.name != 'Other'
    GROUP BY categories.category_id
  ");

  $categories = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero">
  <h1>CvSU Student Marketplace</h1>
  <p>Buy and sell within the Cavite State University community - safe, easy, and free.</p>
 
  <!-- Show different CTA depending on login state -->
  <?php if ($user): ?>
    <!-- Search bar -->
    <form class="hero-search" action="browse.php" method="GET">
      <input type="text" name="q" placeholder="Search for books, gadgets, supplies, etc." />
      <button type="submit">Search</button>
    </form>

    <div class="hero-logged-in">
      Welcome back, <strong><?= htmlspecialchars($user['name']) ?></strong>! |
      <a href="dashboard.php" style="color: #C0B87A; font-weight: 600;">Go to Dashboard</a>
    </div>
  <?php else: ?>
    <div class="hero-actions">
      <a href="signup.php" class="btn-hero-primary">Create Free Account</a>
      <a href="#featured-listings-part" class="btn-hero-secondary">Browse Listings</a>
    </div>
  <?php endif; ?>
</section>
 
<!-- Browse by Categories Section -->
<div class="home-section-full">
  <div class="inner">
    <div class="section-heading">
      <h2>Browse by Category</h2>
      <a <?= ($user) ? 'href="browse.php"' : 'href="login.php"' ?>>View all</a>
    </div>
    <div class="categories-grid">
      <?php foreach ($categories as $cat): ?>
        <a href="<?= ($user) ? 'browse.php?category=' . urlencode($cat['name']) : 'login.php' ?>" class="category-card">
          <div class="category-icon"><?= $icons[$cat['name']] ?? '' ?></div>
          <div class="category-label"><?= htmlspecialchars($cat['name']) ?></div>
          <div class="category-count"><?= htmlspecialchars($cat['count']) ?> listings</div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
 
<!-- Featured Listings Section -->
<div class="home-section" id="featured-listings-part">
  <div class="section-heading">
    <h2>Featured Listings</h2>
    <a href="browse.php">View all</a>
  </div>
  <div class="listings-grid">
    <?php foreach ($featured as $item): ?>
      <a href="listing.php?id=<?= urlencode($item['item_id']) ?>" class="listing-card">
        <div class="listing-img">
          <?php $imgFile = "uploads/" . $item['image_path']; ?>
          <?php if (!empty($item['image_path']) && file_exists($imgFile)): ?>
            <img src="<?= htmlspecialchars($imgFile) ?>" alt="Item Image">
          <?php else: ?>
            <?= $imgNotAvailableIcon ?>
          <?php endif; ?>
        </div>
        <div class="listing-content">
          <div class="listing-category"><?= htmlspecialchars($item['category']) ?></div>
          <div class="listing-title"><?= htmlspecialchars(strlen($item['title']) > 30 ? substr($item['title'], 0, 30) . '...' : $item['title']) ?></div>
          <div class="listing-price">&#8369;<?= number_format($item['price']) ?></div>
          <div class="listing-other-info">
            <span><?= $userIcon ?> &nbsp; <span class="info-text"><?= htmlspecialchars($item['seller']) ?></span></span>
            <span><?= $locationIcon ?> &nbsp; <span class="info-text"><?= htmlspecialchars($item['location']) ?></span></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
 
<!-- How It Works Section -->
<div class="home-section-full">
  <div class="inner">
    <div class="section-heading" style="justify-content: center; margin-bottom: 32px;">
      <h2>How It Works</h2>
    </div>
    <div class="how-it-works">
      <div class="step">
        <div class="step-number">1</div>
        <h3>Create an Account</h3>
        <p>Sign up for free using your CvSU student email. It only takes a minute.</p>
      </div>
      <div class="step">
        <div class="step-number">2</div>
        <h3>Post or Browse</h3>
        <p>List an item to sell or browse listings from fellow students.</p>
      </div>
      <div class="step">
        <div class="step-number">3</div>
        <h3>Finish Transaction</h3>
        <p>Message the seller or buyer, agree on a meetup spot, and make the deal.</p>
      </div>
    </div>
  </div>
</div>
 
<!-- Bottom Call To Action (For Guests Only) -->
<?php if (!$user): ?>
  <div class="home-section" style="padding-top: 48px; padding-bottom: 48px;">
    <div class="cta-banner">
      <h2>Ready to buy or sell?</h2>
      <p>Join with us and other CvSU students already using the marketplace.</p>
      <div class="cta-buttons">
        <a href="signup.php" class="btn-cta-primary">Get Started</a>
        <a href="#featured-listings-part" class="btn-cta-outline">Browse First</a>
      </div>
    </div>
  </div>
<?php endif; ?>
 
<?php include 'includes/footer.php'; ?>