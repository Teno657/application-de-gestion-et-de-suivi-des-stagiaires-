<?php
/**
 * Gestion des encadreurs (Secrétaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Gestion des encadreurs - School-Connection";

// =============================================
// TRAITEMENT DE L'ENVOI DE MESSAGE
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['danger'] = 'Token de sécurité invalide';
        redirect('encadreurs.php');
    }
    
    $destinataire_id = (int)($_POST['destinataire_id'] ?? 0);
    $message = cleanInput($_POST['message'] ?? '');
    $sujet = cleanInput($_POST['sujet'] ?? '');
    
    if (empty($message)) {
        $_SESSION['flash']['danger'] = 'Le message ne peut pas être vide';
        redirect('encadreurs.php');
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
        
        // ✅ CRÉER UNE NOTIFICATION DANS LA MESSAGERIE (pas dans la cloche)
        // On insère directement un message dans la conversation, la messagerie affichera le compteur
        
        $pdo->commit();
        
        $_SESSION['flash']['success'] = "Message envoyé avec succès. L'encadreur le verra dans sa messagerie.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash']['danger'] = "Erreur lors de l'envoi : " . $e->getMessage();
        error_log("Erreur envoi message: " . $e->getMessage());
    }
    
    redirect('encadreurs.php');
}

// Filtres
$type = $_GET['type'] ?? '';
$disponible = $_GET['disponible'] ?? '';
$search = $_GET['search'] ?? '';

// Construction de la requête
$where = ["u.role IN ('encadreur_pro', 'encadreur_acro')"];
$params = [];

if (!empty($type)) {
    $where[] = "u.role = ?";
    $params[] = $type;
}

if ($disponible !== '') {
    $where[] = "e.disponible = ?";
    $params[] = $disponible;
}

if (!empty($search)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR e.profession LIKE ? OR e.specialite LIKE ? OR e.entreprise LIKE ?)";
    $searchTerm = "%$search%";
    for ($i = 0; $i < 6; $i++) $params[] = $searchTerm;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Comptage total
$countSql = "SELECT COUNT(*) FROM encadreurs e JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des encadreurs
$sql = "
    SELECT u.*, e.profession, e.specialite, e.entreprise, e.bio, e.disponible, 
           e.stagiaires_actuels, e.max_stagiaires,
           (SELECT COUNT(*) FROM relations_encadrement WHERE id_encadreur = u.id_utilisateur AND statut = 'active') as nb_stagiaires,
           (SELECT COUNT(*) FROM taches WHERE id_encadreur = u.id_utilisateur) as nb_taches
    FROM encadreurs e
    JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.nom, u.prenom
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$encadreurs = $stmt->fetchAll();

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
                    <h1 class="h3">Gestion des encadreurs</h1>
                    <p class="text-muted">Consultez la liste des encadreurs disponibles</p>
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
                                   value="<?= e($search) ?>" placeholder="Nom, profession, entreprise...">
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">Tous</option>
                                <option value="encadreur_pro" <?= $type === 'encadreur_pro' ? 'selected' : '' ?>>Professionnel</option>
                                <option value="encadreur_acro" <?= $type === 'encadreur_acro' ? 'selected' : '' ?>>Académique</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="disponible" class="form-label">Disponibilité</label>
                            <select class="form-select" id="disponible" name="disponible">
                                <option value="">Tous</option>
                                <option value="1" <?= $disponible === '1' ? 'selected' : '' ?>>Disponible</option>
                                <option value="0" <?= $disponible === '0' ? 'selected' : '' ?>>Non disponible</option>
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
    
    <!-- Liste des encadreurs -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Encadreur</th>
                                    <th>Profession</th>
                                    <th>Spécialité</th>
                                    <th>Entreprise</th>
                                    <th>Stagiaires</th>
                                    <th>Disponibilité</th>
                                    <th>Actions</th>
                                 </thead>
                            <tbody>
                                <?php foreach ($encadreurs as $e): ?>
                                 <tr>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= getPhotoUrl($e['photo']) ?>" 
                                                 alt="" class="rounded-circle me-2" width="40" height="40">
                                            <div>
                                                <strong><?= e($e['prenom'] . ' ' . $e['nom']) ?></strong>
                                                <small class="d-block text-muted"><?= e($e['email']) ?></small>
                                            </div>
                                        </div>
                                     </td>
                                    <td class="align-middle"><?= e($e['profession']) ?></td>
                                    <td class="align-middle"><?= e($e['specialite']) ?></td>
                                    <td class="align-middle"><?= e($e['entreprise']) ?></td>
                                    <td class="align-middle">
                                        <?= $e['stagiaires_actuels'] ?> / <?= $e['max_stagiaires'] ?>
                                        <div class="progress mt-1" style="height: 3px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($e['stagiaires_actuels'] / $e['max_stagiaires']) * 100 ?>%"></div>
                                        </div>
                                     </td>
                                    <td class="align-middle">
                                        <?php if ($e['disponible']): ?>
                                            <span class="badge bg-success">Disponible</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non disponible</span>
                                        <?php endif; ?>
                                     </td>
                                    <td class="align-middle">
                                        <div class="btn-group">
                                            <a href="encadreur-voir.php?id=<?= $e['id_utilisateur'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-message" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#messageModal"
                                                    data-id="<?= $e['id_utilisateur'] ?>"
                                                    data-name="<?= e($e['prenom'] . ' ' . $e['nom']) ?>"
                                                    title="Envoyer un message">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </div>
                                     </td>
                                  </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($encadreurs)): ?>
                                  <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        Aucun encadreur trouvé
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
                                <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= urlencode($type) ?>&disponible=<?= urlencode($disponible) ?>&search=<?= urlencode($search) ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&disponible=<?= urlencode($disponible) ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= urlencode($type) ?>&disponible=<?= urlencode($disponible) ?>&search=<?= urlencode($search) ?>">
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
                        L'encadreur recevra ce message dans sa messagerie (icône enveloppe).
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
// Remplir le modal avec les données de l'encadreur
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