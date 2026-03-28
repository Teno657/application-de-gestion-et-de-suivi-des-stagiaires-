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
$type = $_GET['type'] ?? 'all'; // all, unread, read
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
    /* Styles pour la page notifications */
    .container-fluid:first-of-type {
        margin-top: 20px;
        padding-top: 10px;
    }
    
    @media (max-width: 768px) {
        .container-fluid:first-of-type {
            margin-top: 10px;
        }
    }
    
    .notification-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        margin-bottom: 15px;
        border-left: 4px solid #e0e0e0;
    }
    
    .notification-card.unread {
        border-left-color: #4361ee;
        background: linear-gradient(90deg, #f8f9ff 0%, white 100%);
    }
    
    .notification-card:hover {
        transform: translateX(5px);
        box-shadow: 0 10px 30px rgba(67, 97, 238, 0.15);
    }
    
    .notification-icon {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .notification-icon.success { background: rgba(76, 201, 240, 0.1); color: #4cc9f0; }
    .notification-icon.warning { background: rgba(248, 150, 30, 0.1); color: #f8961e; }
    .notification-icon.danger { background: rgba(247, 37, 133, 0.1); color: #f72585; }
    .notification-icon.info { background: rgba(67, 97, 238, 0.1); color: #4361ee; }
    
    .notification-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    
    .notification-message {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }
    
    .notification-time {
        font-size: 0.8rem;
        color: #999;
    }
    
    .notification-time i {
        margin-right: 5px;
    }
    
    .stats-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .stats-card.active {
        border-color: #4361ee;
        background: linear-gradient(135deg, #f8f9ff, white);
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }
    
    .stats-label {
        font-size: 0.9rem;
        color: #666;
        margin-top: 5px;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        background: transparent;
        border: none;
        cursor: pointer;
    }
    
    .btn-icon:hover {
        transform: scale(1.1);
    }
    
    .btn-read {
        color: #4cc9f0;
    }
    
    .btn-read:hover {
        background: rgba(76, 201, 240, 0.1);
        color: #3ba8d0;
    }
    
    .btn-delete {
        color: #f72585;
    }
    
    .btn-delete:hover {
        background: rgba(247, 37, 133, 0.1);
        color: #d4166e;
    }
    
    .filter-tag {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 50px;
        background: #f0f0f0;
        color: #666;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-right: 10px;
        text-decoration: none;
    }
    
    .filter-tag:hover, .filter-tag.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        transform: translateY(-2px);
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
    
    .empty-state h4 {
        color: #666;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #999;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">
                        <i class="fas fa-bell me-2 text-primary"></i>Mes notifications
                    </h1>
                    <p class="text-muted">Consultez et gérez toutes vos notifications</p>
                </div>
                <?php if ($stats['non_lues'] > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-outline-primary" onclick="return confirm('Marquer toutes les notifications comme lues ?')">
                    <i class="fas fa-check-double me-2"></i>Tout marquer comme lu
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <a href="?type=all" class="text-decoration-none">
                <div class="stats-card <?= $type === 'all' ? 'active' : '' ?>">
                    <div class="stats-number"><?= $stats['total'] ?></div>
                    <div class="stats-label">Toutes les notifications</div>
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
    
    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                        <div class="col-md-10">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" 
                                       name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Rechercher une notification...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Rechercher
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des notifications -->
    <div class="row">
        <div class="col-12">
            <?php if ($notifications): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-card p-3 <?= $notif['est_lue'] ? '' : 'unread' ?>" id="notif-<?= $notif['id_notification'] ?>">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon <?= $notif['type_notification'] ?> me-3">
                                <i class="fas fa-<?= 
                                    $notif['type_notification'] === 'success' ? 'check-circle' : 
                                    ($notif['type_notification'] === 'warning' ? 'exclamation-triangle' : 
                                    ($notif['type_notification'] === 'danger' ? 'times-circle' : 'info-circle')) 
                                ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="notification-title">
                                            <?= htmlspecialchars($notif['titre'] ?? 'Notification') ?>
                                            <?php if (!$notif['est_lue']): ?>
                                                <span class="badge bg-primary ms-2">Nouveau</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-message">
                                            <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                        </div>
                                        <div class="notification-time">
                                            <i class="far fa-clock"></i>
                                            <?= time_elapsed_string($notif['date_creation']) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if (!$notif['est_lue']): ?>
                                            <a href="?mark_read=<?= $notif['id_notification'] ?>&type=<?= htmlspecialchars($type) ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" 
                                               class="btn-icon btn-read" title="Marquer comme lu">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $notif['id_notification'] ?>&type=<?= htmlspecialchars($type) ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" 
                                           class="btn-icon btn-delete" title="Supprimer"
                                           onclick="return confirm('Supprimer cette notification ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php if ($notif['lien']): ?>
                                    <div class="mt-2">
                                        <?php 
                                        // 🔧 CORRECTION : Construire le lien correctement
                                        $lien = $notif['lien'];
                                        // Supprimer le slash au début s'il existe
                                        $lien = ltrim($lien, '/');
                                        ?>
                                        <a href="<?= APP_URL ?>/<?= $lien ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-arrow-right me-1"></i>Voir
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        </li>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h4>Aucune notification</h4>
                    <p>Vous n'avez aucune notification pour le moment.</p>
                    <?php if ($search || $type !== 'all'): ?>
                        <a href="notifications.php" class="btn btn-primary mt-3">
                            <i class="fas fa-sync me-2"></i>Voir toutes les notifications
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Marquer comme lu en AJAX
document.querySelectorAll('.mark-read-ajax').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        const id = this.dataset.id;
        const card = document.getElementById(`notif-${id}`);
        
        try {
            const response = await fetch(`ajax/mark_notification_read.php?id=${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            
            if (data.success) {
                card.classList.remove('unread');
                this.remove();
                const badge = document.querySelector('#notificationBadge');
                if (badge) {
                    let count = parseInt(badge.textContent) - 1;
                    if (count <= 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = count;
                    }
                }
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>