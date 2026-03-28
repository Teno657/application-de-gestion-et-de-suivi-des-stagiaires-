<?php
/**
 * Logs d'activité (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Logs d'activité - School-Connection";

// Filtres
$type_action = $_GET['type_action'] ?? '';
$utilisateur_id = (int)($_GET['utilisateur_id'] ?? 0);
$date_debut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// Construction de la requête
$where = ["1=1"];
$params = [];

if (!empty($type_action)) {
    $where[] = "l.type_action = ?";
    $params[] = $type_action;
}

if ($utilisateur_id > 0) {
    $where[] = "l.id_utilisateur = ?";
    $params[] = $utilisateur_id;
}

if (!empty($date_debut) && !empty($date_fin)) {
    $where[] = "DATE(l.date_action) BETWEEN ? AND ?";
    $params[] = $date_debut;
    $params[] = $date_fin;
}

if (!empty($search)) {
    $where[] = "(l.action LIKE ? OR l.description LIKE ? OR l.ip_adresse LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Comptage total
$countSql = "SELECT COUNT(*) FROM logs_activite l WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des logs
$sql = "
    SELECT l.*, u.nom, u.prenom, u.email, u.role, u.photo
    FROM logs_activite l
    LEFT JOIN utilisateurs u ON l.id_utilisateur = u.id_utilisateur
    WHERE " . implode(' AND ', $where) . "
    ORDER BY l.date_action DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Statistiques des logs
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM logs_activite")->fetchColumn(),
    'aujourd_hui' => $pdo->query("SELECT COUNT(*) FROM logs_activite WHERE DATE(date_action) = CURDATE()")->fetchColumn(),
    'cette_semaine' => $pdo->query("SELECT COUNT(*) FROM logs_activite WHERE YEARWEEK(date_action) = YEARWEEK(NOW())")->fetchColumn(),
    'ce_mois' => $pdo->query("SELECT COUNT(*) FROM logs_activite WHERE MONTH(date_action) = MONTH(NOW()) AND YEAR(date_action) = YEAR(NOW())")->fetchColumn(),
];

// Types d'actions disponibles
$types_actions = $pdo->query("SELECT DISTINCT type_action FROM logs_activite ORDER BY type_action")->fetchAll(PDO::FETCH_COLUMN);

// Liste des utilisateurs pour le filtre
$utilisateurs = $pdo->query("SELECT id_utilisateur, nom, prenom, email FROM utilisateurs ORDER BY nom, prenom")->fetchAll();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Logs d'activité</h1>
                    <p class="text-muted">Historique complet des actions sur la plateforme</p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-danger" onclick="exportLogs()">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearLogs()">
                        <i class="fas fa-trash"></i> Nettoyer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques rapides -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['total'] ?></h2>
                    <small>Total des logs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['aujourd_hui'] ?></h2>
                    <small>Aujourd'hui</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['cette_semaine'] ?></h2>
                    <small>Cette semaine</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['ce_mois'] ?></h2>
                    <small>Ce mois</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="type_action" class="form-label">Type d'action</label>
                            <select class="form-select" id="type_action" name="type_action">
                                <option value="">Tous</option>
                                <?php foreach ($types_actions as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $type_action === $type ? 'selected' : '' ?>>
                                        <?= e($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="utilisateur_id" class="form-label">Utilisateur</label>
                            <select class="form-select" id="utilisateur_id" name="utilisateur_id">
                                <option value="0">Tous</option>
                                <?php foreach ($utilisateurs as $u): ?>
                                    <option value="<?= $u['id_utilisateur'] ?>" <?= $utilisateur_id === $u['id_utilisateur'] ? 'selected' : '' ?>>
                                        <?= e($u['prenom'] . ' ' . $u['nom'] . ' (' . $u['email'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_debut" class="form-label">Date début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?= $date_debut ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_fin" class="form-label">Date fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?= $date_fin ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= e($search) ?>" placeholder="Action, description, IP...">
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                            <a href="logs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tableau des logs -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date/Heure</th>
                                    <th>Utilisateur</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>IP</th>
                                    <th>Table</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <?= format_datetime($log['date_action'], 'd/m/Y H:i:s') ?>
                                    </td>
                                    <td>
                                        <?php if ($log['id_utilisateur']): ?>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= getPhotoUrl($log['photo'] ?? '') ?>" 
                                                     alt="" class="rounded-circle me-2" width="30" height="30">
                                                <div>
                                                    <?= e($log['prenom'] . ' ' . $log['nom']) ?>
                                                    <br>
                                                    <small class="text-muted"><?= get_role_label($log['role']) ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Système</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= e($log['action']) ?></span>
                                    </td>
                                    <td><?= e($log['description']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $log['type_action'] === 'connexion' ? 'success' : 
                                            ($log['type_action'] === 'deconnexion' ? 'warning' : 
                                            ($log['type_action'] === 'creation' ? 'info' : 
                                            ($log['type_action'] === 'modification' ? 'primary' : 
                                            ($log['type_action'] === 'suppression' ? 'danger' : 'secondary')))) 
                                        ?>">
                                            <?= e($log['type_action']) ?>
                                        </span>
                                    </td>
                                    <td><small><?= $log['ip_adresse'] ?></small></td>
                                    <td>
                                        <?php if ($log['table_concernee']): ?>
                                            <small><?= e($log['table_concernee']) ?> #<?= $log['id_enregistrement'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        Aucun log trouvé
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
                                <a class="page-link" href="?page=<?= $page - 1 ?>&type_action=<?= urlencode($type_action) ?>&utilisateur_id=<?= $utilisateur_id ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>&search=<?= urlencode($search) ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&type_action=<?= urlencode($type_action) ?>&utilisateur_id=<?= $utilisateur_id ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&type_action=<?= urlencode($type_action) ?>&utilisateur_id=<?= $utilisateur_id ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>&search=<?= urlencode($search) ?>">
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
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export-logs.php?' + params.toString();
}

function clearLogs() {
    Swal.fire({
        title: 'Nettoyer les logs',
        text: 'Êtes-vous sûr de vouloir supprimer tous les logs ? Cette action est irréversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f72585',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, tout supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'clear-logs.php';
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>