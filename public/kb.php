<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT * FROM kb_articles WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $sql .= " AND category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM kb_articles ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex justify-content-between align-items-center mb-4" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h1 class="h3 mb-0">Knowledge Base</h1>
    <?php if(has_role('admin') || has_role('technician')): ?>
    <a href="kb_manage.php" class="btn btn-primary"><i class="fas fa-plus"></i> Manage Articles</a>
    <?php endif; ?>
</div>

<div class="card border-secondary border-opacity-10 bg-dark p-4 mb-4">
    <form method="GET" class="grid-3" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem;">
        <div class="input-group">
            <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-white-50"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control bg-transparent border-secondary border-opacity-25 text-white placeholder-secondary" placeholder="Search articles..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <select name="category" class="form-select bg-transparent border-secondary border-opacity-25 text-white-50">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
    <?php if (count($articles) > 0): ?>
        <?php foreach ($articles as $article): ?>
        <div class="card border-secondary border-opacity-10 bg-dark p-4 hover-scale" style="transition: transform 0.2s;">
            <div class="badge bg-secondary bg-opacity-10 text-white-50 border border-secondary border-opacity-25 mb-3"><?php echo htmlspecialchars($article['category']); ?></div>
            <h3 class="h5 mb-2"><a href="kb_view.php?id=<?php echo $article['id']; ?>" class="text-white text-decoration-none stretched-link"><?php echo htmlspecialchars($article['title']); ?></a></h3>
            <p class="text-white-50 small mb-0">
                <?php echo substr(strip_tags($article['content']), 0, 100) . '...'; ?>
            </p>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-span-2 text-center py-5" style="grid-column: 1 / -1;">
            <div class="text-white-50 opacity-25 mb-3"><i class="fas fa-book-open fa-3x"></i></div>
            <h6 class="text-white">No articles found</h6>
            <p class="text-white-50 small mb-0">Try adjusting your search terms</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
