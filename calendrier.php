<?php
/**
 * Page des notifications - Stagiaire
 */
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle - STAGIAIRE
require_login();
if ($_SESSION['user_role'] != 'stagiaire') {
    redirect('../index.php');
}

$page_title = "Mes notifications - School-Connection";
$user_id = $_SESSION['user_id'];

// =============================================
// TRAITEMENT DES ACTIONS
// =============================================

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET est_lue = 1, date_lecture = NOW() 
            WHERE id_notification = ? AND id_utilisateur = ?
        ");
        $stmt->execute([$id, $user_id]);
        
        $_SESSION['flash']['success'] = "Notification marquée comme lue";
        
    } catch (Exception $e) {
        $_SESSION['flash']['danger'] = "Erreur lors de la mise à jour";
    }
    
    redirect('notifications.php');
}

// Marquer toutes les notifications comme lues
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET est_lue = 1, date_lecture = NOW() 
            WHERE id_utilisateur = ? AND est_lue = 0
        ");
        $stmt->execute([$user_id]);
        
        $count = $stmt->rowCount();
        $_SESSION['flash']['success'] = "$count notification(s) marquée(s) comme lue(s)";
        
    } catch (Exception $e) {
        $_SESSION['flash']['danger'] = "Erreur lors de la mise à jour";
    }
    
    redirect('notifications.php');
}

// Supprimer une notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE id_notification = ? AND id_utilisateur = ?
        ");
        $stmt->execute([$id, $user_id]);
        
        $_SESSION['flash']['success'] = "Notification supprimée";
        
    } catch (Exception $e) {
        $_SESSION['flash']['danger'] = "Erreur lors de la suppression";
    }
    
    redirect('notifications.php');
}

// =============================================
// AFFICHAGE DES NOTIFICATIONS
// =============================================

// Filtres
$type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construction de la requête
$where = ["id_utilisateur = ?"];
$params = [$user_id];

if ($type === 'unread') {
    $where[] = "est_lue = 0";
} elseif ($type === 'read') {
    $where[] = "est_lue = 1";
}

if (!empty($search)) {
    $where[] = "(titre LIKE ? OR message LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Compter le total
$countSql = "SELECT COUNT(*) FROM notifications WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupérer les notifications
$sql = "
    SELECT * FROM notifications
    WHERE " . implode(' AND ', $where) . "
    ORDER BY date_creation DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Statistiques
$stmt1 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ?");
$stmt1->execute([$user_id]);
$total_all = $stmt1->fetchColumn();

$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND est_lue = 0");
$stmt2->execute([$user_id]);
$non_lues = $stmt2->fetchColumn();

$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND est_lue = 1");
$stmt3->execute([$user_id]);
$lues = $stmt3->fetchColumn();

$stats = [
    'total' => $total_all,
    'non_lues' => $non_lues,
    'lues' => $lues
];

include '../../includes/header.php';
?>

<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 30px;
    }
    
    .notifications-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 30px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.3);
        overflow: hidden;
        animation: slideUp 0.5s ease;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .notifications-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 30px;
        color: white;
    }
    
    .notification-item {
        border-bottom: 1px solid #e0e0e0;
        padding: 20px;
        transition: all 0.3s;
    }
    
    .notification-item:hover {
        background: #f8f9fa;
    }
    
    .notification-item.unread {
        background: rgba(102, 126, 234, 0.05);
        border-left: 4px solid #667eea;
    }
    
    .notification-icon {
        width: 45px;
        height: 45px;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }
    
    .notification-title {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .notification-message {
        color: #6c757d;
        font-size: 0.9rem;
        white-space: pre-line;
    }
    
    .notification-date {
        font-size: 0.75rem;
        color: #999;
    }
    
    .btn-mark-read, .btn-delete {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
        transition: all 0.3s;
    }
    
    .btn-mark-read {
        color: #667eea;
    }
    
    .btn-mark-read:hover {
        text-decoration: underline;
    }
    
    .btn-delete {
        color: #dc3545;
    }
    
    .btn-delete:hover {
        text-decoration: underline;
    }
    
    .stats-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
        border: 2px solid transparent;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .stats-card.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #f8f9ff, white);
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 800;
    }
    
    .stats-label {
        font-size: 0.9rem;
        color: #666;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #ddd;
        margin-bottom: 20px;
    }
    
    .filter-tag {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 50px;
        background: #f0f0f0;
        color: #666;
        transition: all 0.3s;
        cursor: pointer;
        margin-right: 10px;
        text-decoration: none;
    }
    
    .filter-tag:hover, .filter-tag.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .btn-gradient {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-gradient:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }
</style>

<div class="notifications-container">
    <div class="glass-card">
        <div class="notifications-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Mes notifications
                    </h2>
                    <p class="mb-0 mt-2 opacity-75">Toutes vos notifications en un seul endroit</p>
                </div>
                <?php if ($stats['non_lues'] > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-outline-light" onclick="return confirm('Marquer toutes les notifications comme lues ?')">
                        <i class="fas fa-check-double me-2"></i>Tout marquer lu
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="row g-3 p-4">
            <div class="col-md-4">
                <a href="?type=all" class="text-decoration-none">
                    <div class="stats-card <?= $type === 'all' ? 'active' : '' ?>">
                        <div class="stats-number"><?= $stats['total'] ?></div>
                        <div class="stats-label">Toutes</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="?type=unread" class="text-decoration-none">
                    <div class="stats-card <?= $type === 'unread' ? 'active' : '' ?>">
                        <div class="stats-number text-primary"><?= $stats['non_lues'] ?></div>
                        <div class="stats-label">Non lues</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="?type=read" class="text-decoration-none">
                    <div class="stats-card <?= $type === 'read' ? 'active' : '' ?>">
                        <div class="stats-number text-success"><?= $stats['lues'] ?></div>
                        <div class="stats-label">Lues</div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Recherche -->
        <div class="px-4 pb-3">
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <!-- Liste des notifications -->
        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h4>Aucune notification</h4>
                    <p>Vous n'avez pas encore de notifications.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <?php
                    $icon = 'fa-bell';
                    if ($notif['type_notification'] == 'calendar') $icon = 'fa-calendar-alt';
                    if ($notif['type_notification'] == 'message') $icon = 'fa-envelope';
                    if ($notif['type_notification'] == 'task') $icon = 'fa-tasks';
                    if ($notif['type_notification'] == 'evaluation') $icon = 'fa-star';
                    if ($notif['type_notification'] == 'validation') $icon = 'fa-check-circle';
                    ?>
                    <div class="notification-item <?= $notif['est_lue'] == 0 ? 'unread' : '' ?>" id="notif-<?= $notif['id_notification'] ?>">
                        <div class="d-flex">
                            <div class="me-3">
                                <div class="notification-icon">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="notification-title">
                                            <?= htmlspecialchars($notif['titre']) ?>
                                            <?php if ($notif['est_lue'] == 0): ?>
                                                <span class="badge bg-primary ms-2">Nouveau</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-message">
                                            <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                        </div>
                                        <div class="notification-date mt-2">
                                            <i class="far fa-clock me-1"></i>
                                            <?= time_elapsed_string($notif['date_creation']) ?>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <?php if ($notif['est_lue'] == 0): ?>
                                            <a href="?mark_read=<?= $notif['id_notification'] ?>&type=<?= htmlspecialchars($type) ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" 
                                               class="btn-mark-read me-2" title="Marquer comme lu">
                                                <i class="fas fa-check"></i> Lu
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $notif['id_notification'] ?>&type=<?= htmlspecialchars($type) ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" 
                                           class="btn-delete" title="Supprimer"
                                           onclick="return confirm('Supprimer cette notification ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php if (!empty($notif['lien'])): ?>
                                    <div class="mt-2">
                                        <a href="<?= APP_URL . '/' . ltrim($notif['lien'], '/') ?>" class="btn btn-sm btn-gradient">
                                            <i class="fas fa-arrow-right me-1"></i>Voir le détail
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="p-4">
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>