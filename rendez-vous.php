<?php
/**
 * Gestion des rendez-vous (Encadreur)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Rendez-vous - School-Connection";
$user_id = $_SESSION['user_id'];

// Filtres
$statut = $_GET['statut'] ?? '';
$stagiaire_id = (int)($_GET['stagiaire'] ?? 0);
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construction de la requête
$where = ["r.id_encadreur = ?"];
$params = [$user_id];

// Filtre par statut
if (!empty($statut)) {
    $where[] = "r.statut = ?";
    $params[] = $statut;
} elseif (!$show_all) {
    // Par défaut, on exclut les terminés SAUF si show_all=1
    $where[] = "r.statut != 'termine'";
}

if ($stagiaire_id > 0) {
    $where[] = "r.id_stagiaire = ?";
    $params[] = $stagiaire_id;
}

// Comptage total
$countSql = "
    SELECT COUNT(*) 
    FROM rendez_vous r
    WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute(array_slice($params, 0, count($where)));
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des rendez-vous
$sql = "
    SELECT r.*, u.nom, u.prenom, u.email, u.photo,
           s.filiere
    FROM rendez_vous r
    JOIN stagiaires s ON r.id_stagiaire = s.id_stagiaire
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.date_rdv ASC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rendez_vous = $stmt->fetchAll();

// Récupérer la liste des stagiaires pour le filtre
$stagiaires = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE s.id_encadreur = ?
    ORDER BY u.nom, u.prenom
");
$stagiaires->execute([$user_id]);
$liste_stagiaires = $stagiaires->fetchAll();

include '../../includes/header.php';
?>

<style>
    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 20px;
        border-radius: 50px;
        background: #f0f0f0;
        color: #666;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    .filter-btn:hover, .filter-btn.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .badge-termine {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .rdv-termine {
        opacity: 0.8;
        background: #f8f9fa;
    }
    
    .rdv-termine:hover {
        opacity: 1;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Rendez-vous</h1>
                    <p class="text-muted">Planifiez et suivez vos rendez-vous avec les stagiaires</p>
                </div>
                <a href="rendez-vous-ajouter.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nouveau rendez-vous
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filtres rapides -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="filter-buttons">
                            <a href="?show_all=0" class="filter-btn <?= !$show_all && empty($statut) ? 'active' : '' ?>">
                                <i class="fas fa-calendar-alt me-1"></i>En cours
                            </a>
                            <a href="?show_all=1" class="filter-btn <?= $show_all && empty($statut) ? 'active' : '' ?>">
                                <i class="fas fa-history me-1"></i>Tous (incl. terminés)
                            </a>
                            <a href="?statut=propose" class="filter-btn <?= $statut === 'propose' ? 'active' : '' ?>">
                                <i class="fas fa-clock me-1"></i>Proposés
                            </a>
                            <a href="?statut=confirme" class="filter-btn <?= $statut === 'confirme' ? 'active' : '' ?>">
                                <i class="fas fa-check-circle me-1"></i>Confirmés
                            </a>
                            <a href="?statut=termine" class="filter-btn <?= $statut === 'termine' ? 'active' : '' ?>">
                                <i class="fas fa-check-double me-1"></i>Terminés
                            </a>
                            <a href="?statut=annule" class="filter-btn <?= $statut === 'annule' ? 'active' : '' ?>">
                                <i class="fas fa-ban me-1"></i>Annulés
                            </a>
                        </div>
                        <div>
                            <form method="GET" class="d-flex gap-2">
                                <input type="hidden" name="show_all" value="<?= $show_all ? '1' : '0' ?>">
                                <select name="stagiaire" class="form-select" style="width: auto;">
                                    <option value="0">Tous les stagiaires</option>
                                    <?php foreach ($liste_stagiaires as $s): ?>
                                        <option value="<?= $s['id_utilisateur'] ?>" <?= $stagiaire_id == $s['id_utilisateur'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filtrer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-primary">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_encadreur = ? AND statut = 'confirme'");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                        ?>
                    </h3>
                    <small class="text-muted">Confirmés</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-warning">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_encadreur = ? AND statut = 'propose'");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                        ?>
                    </h3>
                    <small class="text-muted">En attente</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-success">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_encadreur = ? AND statut = 'termine'");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                        ?>
                    </h3>
                    <small class="text-muted">Terminés</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h3 class="mb-0 text-danger">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_encadreur = ? AND statut = 'annule'");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                        ?>
                    </h3>
                    <small class="text-muted">Annulés</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des rendez-vous -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Stagiaire</th>
                                    <th>Titre</th>
                                    <th>Date et heure</th>
                                    <th>Durée</th>
                                    <th>Lieu/Lien</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rendez_vous as $r): 
                                    $is_termine = $r['statut'] == 'termine';
                                    $is_passe = strtotime($r['date_rdv']) < time() && !$is_termine;
                                ?>
                                <tr class="<?= $is_termine ? 'rdv-termine' : '' ?> <?= $is_passe ? 'table-warning' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= getPhotoUrl($r['photo']) ?>" 
                                                 alt="" class="rounded-circle me-2" width="30" height="30" style="object-fit: cover;">
                                            <div>
                                                <strong><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($r['filiere'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($r['titre']) ?></td>
                                    <td>
                                        <?= format_datetime($r['date_rdv'], 'd/m/Y H:i') ?>
                                        <?php if ($is_passe && !$is_termine): ?>
                                            <br><span class="badge bg-warning">Passé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $r['duree'] ?> min</td>
                                    <td>
                                        <?php if ($r['lien_visio']): ?>
                                            <a href="<?= htmlspecialchars($r['lien_visio']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-video"></i> Visio
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($r['lieu'] ?? '—') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statut_labels = [
                                            'propose' => ['class' => 'warning', 'label' => '📝 Proposé'],
                                            'confirme' => ['class' => 'success', 'label' => '✅ Confirmé'],
                                            'termine' => ['class' => 'info', 'label' => '✔️ Terminé'],
                                            'annule' => ['class' => 'danger', 'label' => '❌ Annulé']
                                        ];
                                        $s = $statut_labels[$r['statut']] ?? ['class' => 'secondary', 'label' => $r['statut']];
                                        ?>
                                        <span class="badge bg-<?= $s['class'] ?>">
                                            <?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="rendez-vous-ajouter.php?edit=<?= $r['id_rdv'] ?>" 
                                               class="btn btn-sm btn-outline-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($r['statut'] != 'termine' && $r['statut'] != 'annule'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-success btn-complete" 
                                                    data-id="<?= $r['id_rdv'] ?>"
                                                    title="Marquer comme terminé">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($rendez_vous)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <?php if ($statut == 'termine'): ?>
                                            <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                                            Aucun rendez-vous terminé
                                        <?php elseif ($statut == 'propose'): ?>
                                            <i class="fas fa-clock fa-2x mb-2 d-block"></i>
                                            Aucun rendez-vous proposé
                                        <?php elseif ($statut == 'confirme'): ?>
                                            <i class="fas fa-calendar-check fa-2x mb-2 d-block"></i>
                                            Aucun rendez-vous confirmé
                                        <?php elseif ($statut == 'annule'): ?>
                                            <i class="fas fa-ban fa-2x mb-2 d-block"></i>
                                            Aucun rendez-vous annulé
                                        <?php elseif ($show_all): ?>
                                            <i class="fas fa-calendar-alt fa-2x mb-2 d-block"></i>
                                            Aucun rendez-vous trouvé
                                        <?php else: ?>
                                            <i class="fas fa-calendar-alt fa-2x mb-2 d-block"></i>
                                            Aucun rendez-vous en cours
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&statut=<?= urlencode($statut) ?>&stagiaire=<?= $stagiaire_id ?>&show_all=<?= $show_all ? '1' : '0' ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&statut=<?= urlencode($statut) ?>&stagiaire=<?= $stagiaire_id ?>&show_all=<?= $show_all ? '1' : '0' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&statut=<?= urlencode($statut) ?>&stagiaire=<?= $stagiaire_id ?>&show_all=<?= $show_all ? '1' : '0' ?>">
                                    Suivant
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour marquer comme terminé -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="rendez-vous-terminer.php">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id_rdv" id="completeRdvId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Marquer le rendez-vous comme terminé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-complete').forEach(btn => {
    btn.addEventListener('click', function() {
        const rdvId = this.dataset.id;
        document.getElementById('completeRdvId').value = rdvId;
        new bootstrap.Modal(document.getElementById('completeModal')).show();
    });
});
</script>

<?php include '../../includes/footer.php'; ?>