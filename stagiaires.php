<?php
/**
 * Gestion des stagiaires (Secrétaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Gestion des stagiaires - School-Connection";

// =============================================
// TRAITEMENT DE L'ENVOI DE MESSAGE
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['danger'] = 'Token de sécurité invalide';
        redirect('stagiaires.php');
    }
    
    $destinataire_id = (int)($_POST['destinataire_id'] ?? 0);
    $message = cleanInput($_POST['message'] ?? '');
    $sujet = cleanInput($_POST['sujet'] ?? '');
    
    if (empty($message)) {
        $_SESSION['flash']['danger'] = 'Le message ne peut pas être vide';
        redirect('stagiaires.php');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier si une conversation existe déjà
        $stmt = $pdo->prepare("
            SELECT c.id_conversation 
            FROM conversations c
            JOIN participants_conversation p1 ON c.id_conversation = p1.id_conversation
            JOIN participants_conversation p2 ON c.id_conversation = p2.id_conversation
            WHERE c.type_conversation = 'individuelle'
            AND p1.id_utilisateur = ? 
            AND p2.id_utilisateur = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $destinataire_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $conversation_id = $existing['id_conversation'];
        } else {
            // Créer une nouvelle conversation
            $stmt = $pdo->prepare("INSERT INTO conversations (type_conversation, date_creation) VALUES ('individuelle', NOW())");
            $stmt->execute();
            $conversation_id = $pdo->lastInsertId();
            
            // Ajouter les participants
            $stmt = $pdo->prepare("INSERT INTO participants_conversation (id_conversation, id_utilisateur) VALUES (?, ?), (?, ?)");
            $stmt->execute([$conversation_id, $_SESSION['user_id'], $conversation_id, $destinataire_id]);
        }
        
        // Insérer le message
        $stmt = $pdo->prepare("
    INSERT INTO messages (id_conversation, id_expediteur, id_destinataire, contenu, date_envoi, est_lu, type)
    VALUES (?, ?, ?, ?, NOW(), 0, 'message')
");
$stmt->execute([$conversation_id, $_SESSION['user_id'], $destinataire_id, $message]);
        // Mettre à jour la date du dernier message
        $pdo->prepare("UPDATE conversations SET date_dernier_message = NOW() WHERE id_conversation = ?")
            ->execute([$conversation_id]);
        
        $pdo->commit();
        
        $_SESSION['flash']['success'] = "Message envoyé avec succès. Le stagiaire le verra dans sa messagerie.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash']['danger'] = "Erreur lors de l'envoi : " . $e->getMessage();
        error_log("Erreur envoi message: " . $e->getMessage());
    }
    
    redirect('stagiaires.php');
}

// Filtres
$statut = $_GET['statut'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$search = $_GET['search'] ?? '';

// Récupérer les filières pour le filtre
$filieres = $pdo->query("SELECT DISTINCT filiere FROM stagiaires WHERE filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

// Construction de la requête
$where = ["1=1"];
$params = [];

if (!empty($statut)) {
    $where[] = "s.statut_inscription = ?";
    $params[] = $statut;
}

if (!empty($filiere)) {
    $where[] = "s.filiere = ?";
    $params[] = $filiere;
}

if (!empty($search)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR s.filiere LIKE ? OR s.theme_stage LIKE ?)";
    $searchTerm = "%$search%";
    for ($i = 0; $i < 5; $i++) $params[] = $searchTerm;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Comptage total
$countSql = "SELECT COUNT(*) FROM stagiaires s JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des stagiaires
$sql = "
    SELECT u.*, s.filiere, s.niveau_etude, s.etablissement, s.theme_stage, 
           s.date_debut, s.date_fin, s.statut_inscription,
           (SELECT COUNT(*) FROM taches WHERE id_stagiaire = u.id_utilisateur) as nb_taches,
           (SELECT COUNT(*) FROM taches WHERE id_stagiaire = u.id_utilisateur AND statut = 'termine') as taches_terminees,
           (SELECT COUNT(*) FROM documents WHERE id_stagiaire = u.id_utilisateur) as nb_documents,
           e.id_encadreur, eu.nom as encadreur_nom, eu.prenom as encadreur_prenom
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    LEFT JOIN relations_encadrement r ON s.id_stagiaire = r.id_stagiaire AND r.statut = 'active'
    LEFT JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.date_creation DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stagiaires = $stmt->fetchAll();

include '../../includes/header.php';
?>

<style>
    .container-fluid:first-of-type {
        margin-top: 20px;
        padding-top: 10px;
    }
    
    @media (max-width: 768px) {
        .container-fluid:first-of-type {
            margin-top: 10px;
        }
    }
    
    .btn-message {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
    }
    
    .btn-message:hover {
        background-color: #e0a800;
        border-color: #e0a800;
        color: #212529;
    }
    
    .modal-message {
        border-radius: 20px;
    }
    
    .modal-message .modal-header {
        background: linear-gradient(135deg, #ffc107, #ffb347);
        color: white;
        border-radius: 20px 20px 0 0;
    }
    
    .modal-message .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Gestion des stagiaires</h1>
                    <p class="text-muted">Consultez et gérez tous les stagiaires</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= key($_SESSION['flash']) === 'success' ? 'success' : (key($_SESSION['flash']) === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
            <?= current($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= e($search) ?>" placeholder="Nom, email, filière, thème...">
                        </div>
                        <div class="col-md-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous</option>
                                <option value="en_attente" <?= $statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="termine" <?= $statut === 'termine' ? 'selected' : '' ?>>Terminé</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filiere" class="form-label">Filière</label>
                            <select class="form-select" id="filiere" name="filiere">
                                <option value="">Toutes</option>
                                <?php foreach ($filieres as $f): ?>
                                    <option value="<?= e($f) ?>" <?= $filiere === $f ? 'selected' : '' ?>>
                                        <?= e($f) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des stagiaires -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Stagiaire</th>
                                    <th>Filière</th>
                                    <th>Stage</th>
                                    <th>Encadreur</th>
                                    <th>Progression</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                 </thead>
                            <tbody>
                                <?php foreach ($stagiaires as $s): 
                                    $progression = $s['nb_taches'] > 0 ? round(($s['taches_terminees'] / $s['nb_taches']) * 100) : 0;
                                ?>
                                 <tr>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= getPhotoUrl($s['photo']) ?>" 
                                                 alt="" class="rounded-circle me-2" width="40" height="40">
                                            <div>
                                                <strong><?= e($s['prenom'] . ' ' . $s['nom']) ?></strong>
                                                <small class="d-block text-muted"><?= e($s['email']) ?></small>
                                            </div>
                                        </div>
                                     </td>
                                    <td class="align-middle">
                                        <strong><?= e($s['filiere']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= e($s['niveau_etude'] ?? '') ?></small>
                                     </td>
                                    <td class="align-middle">
                                        <small><?= e(truncate($s['theme_stage'], 50)) ?></small>
                                        <br>
                                        <small class="text-muted">
                                            <?= format_date($s['date_debut']) ?> - <?= format_date($s['date_fin']) ?>
                                        </small>
                                     </td>
                                    <td class="align-middle">
                                        <?php if ($s['id_encadreur']): ?>
                                            <?= e($s['encadreur_prenom'] . ' ' . $s['encadreur_nom']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                     </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $progression ?>%;" 
                                                     aria-valuenow="<?= $progression ?>" 
                                                     aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small><?= $progression ?>%</small>
                                        </div>
                                        <small class="text-muted">
                                            <?= $s['taches_terminees'] ?>/<?= $s['nb_taches'] ?> tâches
                                        </small>
                                     </td>
                                    <td class="align-middle">
                                        <span class="badge bg-<?= get_status_badge($s['statut_inscription']) ?>">
                                            <?= $s['statut_inscription'] ?>
                                        </span>
                                     </td>
                                    <td class="align-middle">
                                        <div class="btn-group">
                                            <a href="stagiaire-voir.php?id=<?= $s['id_utilisateur'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                           
                                            <button type="button" 
                                                    class="btn btn-sm btn-message" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#messageModal"
                                                    data-id="<?= $s['id_utilisateur'] ?>"
                                                    data-name="<?= e($s['prenom'] . ' ' . $s['nom']) ?>"
                                                    title="Envoyer un message">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </div>
                                     </td>
                                  </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($stagiaires)): ?>
                                  <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        Aucun stagiaire trouvé
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
                                <a class="page-link" href="?page=<?= $page - 1 ?>&statut=<?= urlencode($statut) ?>&filiere=<?= urlencode($filiere) ?>&search=<?= urlencode($search) ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&statut=<?= urlencode($statut) ?>&filiere=<?= urlencode($filiere) ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&statut=<?= urlencode($statut) ?>&filiere=<?= urlencode($filiere) ?>&search=<?= urlencode($search) ?>">
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

<!-- Modal Envoyer un message -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-message">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="send_message" value="1">
                <input type="hidden" name="destinataire_id" id="destinataire_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-envelope me-2"></i>Envoyer un message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>À : <strong id="destinataire_name"></strong></p>
                    
                    <div class="mb-3">
                        <label for="sujet" class="form-label">Sujet (optionnel)</label>
                        <input type="text" class="form-control" id="sujet" name="sujet" 
                               placeholder="Ex: Information importante">
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" 
                                  placeholder="Écrivez votre message ici..." required></textarea>
                    </div>
                    
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i>
                        Le stagiaire recevra ce message dans sa messagerie (icône enveloppe). Il pourra vous répondre.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-paper-plane me-1"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Remplir le modal avec les données du stagiaire
document.querySelectorAll('[data-bs-target="#messageModal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        document.getElementById('destinataire_id').value = id;
        document.getElementById('destinataire_name').textContent = name;
        document.getElementById('sujet').value = '';
        document.getElementById('message').value = '';
    });
});
</script>

<?php include '../../includes/footer.php'; ?>