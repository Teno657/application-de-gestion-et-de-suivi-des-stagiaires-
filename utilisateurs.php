<?php
/**
 * Gestion des utilisateurs (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Gestion des utilisateurs - School-Connection";

// Filtres
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

// Construction de la requête
$where = ["1=1"];
$params = [];

if (!empty($role_filter)) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($search)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Comptage total
$countSql = "SELECT COUNT(*) FROM utilisateurs u WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des utilisateurs
$sql = "
    SELECT u.*, 
           CASE 
               WHEN u.role = 'stagiaire' THEN s.filiere
               WHEN u.role LIKE 'encadreur_%' THEN e.specialite
               WHEN u.role = 'secretaire' THEN sec.service
               ELSE NULL
           END as detail
    FROM utilisateurs u
    LEFT JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire AND u.role = 'stagiaire'
    LEFT JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur AND u.role LIKE 'encadreur_%'
    LEFT JOIN secretaires sec ON u.id_utilisateur = sec.id_secretaire AND u.role = 'secretaire'
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.date_creation DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3">Gestion des utilisateurs</h1>
            <p class="text-muted">Gérez tous les utilisateurs de la plateforme</p>
        </div>
    </div>
    
    <!-- Filtres et actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= e($search) ?>" placeholder="Nom, email...">
                        </div>
                        <div class="col-md-3">
                            <label for="role" class="form-label">Filtrer par rôle</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">Tous les rôles</option>
                                <option value="stagiaire" <?= $role_filter === 'stagiaire' ? 'selected' : '' ?>>Stagiaires</option>
                                <option value="encadreur_pro" <?= $role_filter === 'encadreur_pro' ? 'selected' : '' ?>>Encadreurs Pro</option>
                                <option value="encadreur_acro" <?= $role_filter === 'encadreur_acro' ? 'selected' : '' ?>>Encadreurs Académiques</option>
                                <option value="secretaire" <?= $role_filter === 'secretaire' ? 'selected' : '' ?>>Secrétaires</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrateurs</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                        <div class="col-md-3 d-flex align-items-end justify-content-end">
                            <a href="utilisateur-ajouter.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> Nouvel utilisateur
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des utilisateurs -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Rôle</th>
                                    <th>Contact</th>
                                    <th>Détail</th>
                                    <th>Statut</th>
                                    <th>Inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= getPhotoUrl($user['photo']) ?>" 
                                                 alt="" class="rounded-circle me-2" width="40" height="40">
                                            <div>
                                                <strong><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong>
                                                <small class="d-block text-muted"><?= e($user['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 
                                            ($user['role'] === 'secretaire' ? 'warning' : 
                                            (strpos($user['role'], 'encadreur') !== false ? 'info' : 'primary')) ?>">
                                            <?= get_role_label($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e($user['telephone'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= e($user['detail'] ?? '—') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($user['est_actif']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactif</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['est_bloque']): ?>
                                            <span class="badge bg-danger">Bloqué</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= format_date($user['date_creation'], 'd/m/Y') ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="utilisateur-voir.php?id=<?= $user['id_utilisateur'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="utilisateur-modifier.php?id=<?= $user['id_utilisateur'] ?>" 
                                               class="btn btn-sm btn-outline-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger btn-delete" 
                                                    data-id="<?= $user['id_utilisateur'] ?>"
                                                    data-type="utilisateur"
                                                    data-name="<?= e($user['prenom'] . ' ' . $user['nom']) ?>"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        Aucun utilisateur trouvé
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
                                <a class="page-link" href="?page=<?= $page - 1 ?>&role=<?= urlencode($role_filter) ?>&search=<?= urlencode($search) ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&role=<?= urlencode($role_filter) ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&role=<?= urlencode($role_filter) ?>&search=<?= urlencode($search) ?>">
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

<script>
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        Swal.fire({
            title: 'Confirmation',
            html: `Êtes-vous sûr de vouloir supprimer l'utilisateur <strong>${name}</strong> ?<br>
                   Cette action est irréversible.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f72585',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `utilisateur-supprimer.php?id=${id}`;
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>