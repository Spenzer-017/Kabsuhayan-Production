<!-- PHP Logic (Authentication, Session, etc.) -->
<?php 
  /*
    browse.php - CvSU Marketplace Browse Page
    Shows all listings with filters and infinite scroll.
  */

  session_start();

  require_once "includes/db.php";
  
  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  $user = $_SESSION['user'] ?? null; 
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = "browse"; 
  $pageTitle = "Browse Listings";
  include "includes/header.php";
?>

<!-- PHP Database Query -->
<?php
  // Categories & Conditions
  $categories = ['All', 'Books', 'Electronics', 'Supplies', 'Clothing', 'Food', 'Accessories', 'Other'];
  $conditions = ['Any Condition', 'New', 'Like New', 'Good', 'Fair', 'N/A'];

  // Active filters
  $active_cat = $_GET['category'] ?? 'All';
  $active_sort = $_GET['sort'] ?? 'newest';
  $search_q = $_GET['q'] ?? '';
?>

<!-- Browse Body -->
<div class="browse-page">
 
  <!-- Page title -->
  <div class="browse-header">
    <h1>Browse Listings</h1>
    <p>Find what you need from fellow CvSU students.</p>
  </div>
 
  <!-- Filter bar -->
  <div class="filter-bar">
    <!-- Search -->
    <div class="filter-search">
      <?= $searchIcon ?>
      <input type="text" id="searchInput" placeholder="Search listings…" value="<?= htmlspecialchars($search_q) ?>" />
    </div>
 
    <!-- Condition filter -->
    <select class="filter-select" id="conditionFilter">
      <?php foreach ($conditions as $con): ?>
        <option value="<?= $con ?>"><?= $con ?></option>
      <?php endforeach; ?>
    </select>
 
    <!-- Sort -->
    <select class="filter-select" id="sortFilter">
      <option value="newest" <?= $active_sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
      <option value="price_asc" <?= $active_sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
      <option value="price_desc" <?= $active_sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
    </select>
  </div>
 
  <!-- Category tabs -->
  <div class="category-tabs">
    <?php foreach ($categories as $cat): ?>
      <a href="?category=<?= urlencode($cat) ?>&q=<?= urlencode($search_q) ?>&sort=<?= urlencode($active_sort) ?>"
         class="cat-tab <?= $active_cat === $cat ? 'active' : '' ?>">
        <?= htmlspecialchars($cat) ?>
      </a>
    <?php endforeach; ?>
  </div>
 
  <!-- Result count (Updated by JS script) -->
  <p class="result-count" id="resultCount">
    Showing <span id="shownCount">0</span> listings
  </p>
 
  <!-- Listings grid (Card listings are put here by JS script) -->
  <div class="listings-grid" id="listingsGrid"></div>
 
  <!-- Empty state (Shown when no results match) -->
  <div class="empty-state" id="emptyState" style="display:none;">
    <div class="empty-icon"><?= $searchIcon ?></div>
    <h3>No listings found</h3>
    <p>Try a different search term or category.</p>
  </div>
 
  <!-- Sentiner: Invisible element at the bottom that triggers the next fetch -->
  <div id="scroll-sentinel"></div>
 
  <!-- Loading animation -->
  <div class="loader" id="loader">
    <div class="loader-dots">
      <span></span><span></span><span></span>
    </div>
  </div>
 
  <!-- Shown when all items are loaded -->
  <div id="end-message">You've seen all listings!</div>
 
</div>

<script>
  // Card Icons
  const userIcon = <?= json_encode($userIcon) ?>;
  const locationIcon = <?= json_encode($locationIcon) ?>;
  const noImgIcon = <?= json_encode($imgNotAvailableIcon) ?>;

  // DOM references
  const grid = document.getElementById('listingsGrid');
  const loader = document.getElementById('loader');
  const endMsg = document.getElementById('end-message');
  const emptyState = document.getElementById('emptyState');
  const shownCount = document.getElementById('shownCount');
  const searchInput = document.getElementById('searchInput');
  const condFil = document.getElementById('conditionFilter');
  const sortFil = document.getElementById('sortFilter');
  const sentinel = document.getElementById('scroll-sentinel');

  // Pagination state 
  let currentPage = 1;
  let isLoading = false;
  let isDone = false;
  let totalShown = 0;

  // Build a single listing card 
  function buildCard(item) {
    const imgHtml = item.img ? `<img src="uploads/${item.img}" loading="lazy">` : noImgIcon;

    return `
      <a href="listing.php?id=${item.id}" class="listing-card">
        <div class="listing-img">
          <span class="condition-badge">${item.condition}</span>
          ${imgHtml}
        </div>
        <div class="listing-content">
          <div class="listing-category">${item.category}</div>
          <div class="listing-title">${item.title}</div>
          <div class="listing-price">&#8369;${(Number(item.price) || 0).toLocaleString()}</div>
          <div class="listing-other-info">
            <span>${userIcon}&nbsp;<span class="info-text">${item.seller}</span></span>
            <span>${locationIcon}&nbsp;<span class="info-text">${item.location}</span></span>
          </div>
        </div>
      </a>`;
  }

  // Read current filter values 
  function getFilters() {
    const params = new URLSearchParams(window.location.search);
    return {
      category: params.get('category')  || 'All',
      condition: condFil.value,
      sort: sortFil.value,
      q: searchInput.value.trim(),
    };
  }

  // Fetch one page of results from the server 
  async function fetchPage() {
    if (isLoading || isDone) return;

    isLoading = true;
    loader.style.display = 'block';

    const f = getFilters();
    const qs = new URLSearchParams({
      page: currentPage,
      limit: 12,
      category: f.category,
      condition: f.condition,
      sort: f.sort,
      q: f.q,
    });

    try {
      const res  = await fetch(`api/get-listings.php?${qs}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      // First page, if no results show empty state
      if (currentPage === 1 && data.items.length === 0) {
        emptyState.style.display = 'block';
        shownCount.textContent = '0';
        loader.style.display = 'none';
        isLoading = false;
        return;
      }

      // Append cards to grid
      data.items.forEach(item => {
        grid.insertAdjacentHTML('beforeend', buildCard(item));
      });

      totalShown += data.items.length;
      shownCount.textContent = totalShown;
      currentPage++;

      // No more pages
      if (!data.has_more) {
        isDone = true;
        if (totalShown > 0) endMsg.style.display = 'block';
        observer.disconnect();
      }

    } catch (err) {
      console.error('Failed to load listings:', err);
    }

    loader.style.display = 'none';
    isLoading = false;
  }

  // Reset grid and fetch from page 1 
  function resetAndFetch() {
    grid.innerHTML = '';
    emptyState.style.display = 'none';
    endMsg.style.display = 'none';
    currentPage = 1;
    isLoading = false;
    isDone = false;
    totalShown = 0;
    shownCount.textContent = '0';

    // Re-attach observer in case it was disconnected after the previous "all loaded"
    observer.observe(sentinel);

    fetchPage();
  }

  // IntersectionObserver: Fires whenever the sentinel enters the viewport.
  const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) fetchPage();
  }, {
    root: null,
    rootMargin: '300px 0px',
    threshold: 0,
  });

  observer.observe(sentinel);

  // Filter events 
  // Debounce search: wait 350ms after the user stops typing before fetching
  let searchTimer;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(resetAndFetch, 350);
  });

  condFil.addEventListener('change', resetAndFetch);
  sortFil.addEventListener('change', resetAndFetch);

  // Initial load 
  fetchPage();
</script>
 
<?php include 'includes/footer.php'; ?>