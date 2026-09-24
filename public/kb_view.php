<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('kb.php');
}

$stmt = $pdo->prepare("SELECT k.*, u.full_name FROM kb_articles k JOIN users u ON k.author_id = u.id WHERE k.id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    redirect('kb.php');
}
?>

<div class="mb-4">
    <a href="kb.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Knowledge Base</a>
</div>

<div class="card border-secondary border-opacity-10 bg-dark p-5" style="max-width: 800px; margin: 0 auto;">
    <div class="mb-4 border-bottom border-secondary border-opacity-25 pb-4">
        <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 mb-3"><?php echo htmlspecialchars($article['category']); ?></div>
        <h1 class="h2 mb-2 text-white"><?php echo htmlspecialchars($article['title']); ?></h1>
        <div class="text-sm text-white-50">
            By <span class="text-white"><?php echo htmlspecialchars($article['full_name']); ?></span> • <?php echo format_date($article['created_at']); ?>
        </div>
    </div>
    
    <div class="prose text-white-50" style="line-height: 1.8;">
        <?php echo nl2br(htmlspecialchars($article['content'])); ?>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
