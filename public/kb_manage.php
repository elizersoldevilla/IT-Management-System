<?php
require_once __DIR__ . '/../views/header.php';
require_login();

if (!has_role('admin') && !has_role('technician')) {
    redirect('kb.php');
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $delete_id = $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM kb_articles WHERE id = ?");
    $stmt->execute([$delete_id]);
    log_action($pdo, $_SESSION['user_id'], 'DELETE_KB', "Deleted KB Article #$delete_id");
    redirect('kb_manage.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    verify_csrf();
    $title = clean_input($_POST['title']);
    $category = clean_input($_POST['category']);
    $content = $_POST['content']; 
    
    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        if ($id) {
            $old_article = $pdo->prepare("SELECT title, category, content FROM kb_articles WHERE id = ?");
            $old_article->execute([$id]);
            $old = $old_article->fetch();

            $changes = [];
            if ($old) {
                if ($old['title'] !== $title) {
                    $changes[] = 'Title: ' . ($old['title'] ?: '(none)') . ' → ' . ($title ?: '(none)');
                }
                if ($old['category'] !== $category) {
                    $changes[] = 'Category: ' . ($old['category'] ?: '(none)') . ' → ' . ($category ?: '(none)');
                }
                if ($old['content'] !== $content) {
                    $changes[] = 'Content updated';
                }
            }

            $stmt = $pdo->prepare("UPDATE kb_articles SET title = ?, category = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $category, $content, $id]);
            $change_details = empty($changes) ? 'No field changes' : implode('; ', $changes);
            log_action($pdo, $_SESSION['user_id'], 'UPDATE_KB', "Updated KB Article: $title ($change_details)");
            $success = "Article updated!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO kb_articles (title, category, content, author_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $category, $content, $_SESSION['user_id']]);
            log_action($pdo, $_SESSION['user_id'], 'CREATE_KB', "Created KB Article: $title");
            $success = "Article created!";
        }
    }
}


$article = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM kb_articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
}


$stmt = $pdo->query("SELECT * FROM kb_articles ORDER BY created_at DESC");
$articles = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h1 class="h3 mb-0">Manage Knowledge Base</h1>
    <a href="kb.php" class="btn btn-secondary">View Public KB</a>
</div>

<div class="grid-2" style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    
    <div class="card border-secondary border-opacity-10 bg-dark p-4">
        <h2 class="h5 mb-3 text-white"><?php echo $article ? 'Edit Article' : 'Create New Article'; ?></h2>
        <?php if ($error): ?><div class="bg-danger text-white p-2 rounded mb-3"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="bg-success text-white p-2 rounded mb-3"><?php echo $success; ?></div><?php endif; ?>
        
        <form method="POST" action="?action=<?php echo $article ? 'edit&id='.$id : 'create'; ?>">
            <?php csrf_field(); ?>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-white-50">Title</label>
                <input type="text" name="title" class="form-control bg-transparent border-secondary border-opacity-25 text-white" value="<?php echo htmlspecialchars($article['title'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-white-50">Category</label>
                <input type="text" name="category" class="form-control bg-transparent border-secondary border-opacity-25 text-white" value="<?php echo htmlspecialchars($article['category'] ?? ''); ?>" list="cat_list" required>
                <datalist id="cat_list">
                    <option value="General">
                    <option value="Hardware">
                    <option value="Software">
                    <option value="Network">
                    <option value="Troubleshooting">
                </datalist>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium text-white-50">Content</label>
                <textarea name="content" class="form-control bg-transparent border-secondary border-opacity-25 text-white" rows="10" required><?php echo htmlspecialchars($article['content'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?php echo $article ? 'Update Article' : 'Publish Article'; ?></button>
            <?php if ($article): ?>
                <a href="kb_manage.php" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    
    <div class="card border-secondary border-opacity-10 bg-dark p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="bg-darker">
                    <tr>
                        <th class="text-white-50 border-secondary border-opacity-25">Title</th>
                        <th class="text-white-50 border-secondary border-opacity-25">Category</th>
                        <th class="text-white-50 border-secondary border-opacity-25">Date</th>
                        <th class="text-white-50 border-secondary border-opacity-25">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                    <tr>
                        <td class="text-white border-secondary border-opacity-25"><?php echo htmlspecialchars($a['title']); ?></td>
                        <td class="border-secondary border-opacity-25"><span class="badge bg-secondary"><?php echo htmlspecialchars($a['category']); ?></span></td>
                        <td class="text-sm text-white-50 border-secondary border-opacity-25"><?php echo format_date($a['created_at']); ?></td>
                        <td class="border-secondary border-opacity-25">
                            <a href="?action=edit&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this article?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo $a['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
