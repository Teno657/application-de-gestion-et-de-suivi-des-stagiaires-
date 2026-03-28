<?php
/**
 * Paramètres généraux de l'application (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Paramètres - School-Connection";

// Récupérer tous les paramètres
$parametres = $pdo->query("SELECT * FROM parametres ORDER BY cle")->fetchAll();

// Regrouper par catégorie
$categories = [];
foreach ($parametres as $p) {
    $categorie = explode('_', $p['cle'])[0] ?? 'general';
    if (!isset($categories[$categorie])) {
        $categories[$categorie] = [];
    }
    $categories[$categorie][] = $p;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['danger'] = "Token de sécurité invalide";
        redirect('parametres.php');
    }
    
    if ($_POST['action'] === 'update') {
        // Mettre à jour les paramètres
        $updates = 0;
        foreach ($_POST['parametres'] as $id => $valeur) {
            $stmt = $pdo->prepare("UPDATE parametres SET valeur = ? WHERE id_parametre = ?");
            if ($stmt->execute([$valeur, $id])) {
                $updates++;
            }
        }
        
        log_action($_SESSION['user_id'], 'UPDATE_PARAMETRES', "Mise à jour de $updates paramètres", 'modification');
        $_SESSION['flash']['success'] = "$updates paramètre(s) mis à jour avec succès";
        redirect('parametres.php');
    }
    
    if ($_POST['action'] === 'new') {
        // Ajouter un nouveau paramètre
        $cle = cleanInput($_POST['cle'] ?? '');
        $valeur = cleanInput($_POST['valeur'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $type_donnee = $_POST['type_donnee'] ?? 'texte';
        $modifiable_par = $_POST['modifiable_par'] ?? 'admin';
        
        if (empty($cle)) {
            $_SESSION['flash']['danger'] = "La clé du paramètre est requise";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO parametres (cle, valeur, description, type_donnee, modifiable_par)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$cle, $valeur, $description, $type_donnee, $modifiable_par]);
                
                log_action($_SESSION['user_id'], 'CREATE_PARAMETRE', "Création du paramètre: $cle", 'creation', 'parametres', $pdo->lastInsertId());
                $_SESSION['flash']['success'] = "Paramètre ajouté avec succès";
            } catch (Exception $e) {
                $_SESSION['flash']['danger'] = "Erreur: " . $e->getMessage();
            }
        }
        redirect('parametres.php');
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Paramètres de l'application</h1>
                    <p class="text-muted">Configurez tous les paramètres du système</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newParamModal">
                    <i class="fas fa-plus"></i> Nouveau paramètre
                </button>
            </div>
        </div>
    </div>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= key($_SESSION['flash']) === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= current($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- Formulaire de paramètres -->
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="update">
        
        <?php foreach ($categories as $categorie => $params): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="card-title mb-0 text-uppercase text-primary"><?= e(ucfirst($categorie)) ?></h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <?php foreach ($params as $p): ?>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="param_<?= $p['id_parametre'] ?>" class="form-label">
                                <strong><?= e($p['cle']) ?></strong>
                                <?php if ($p['description']): ?>
                                    <i class="fas fa-info-circle text-muted ms-1" 
                                       data-bs-toggle="tooltip" 
                                       title="<?= e($p['description']) ?>"></i>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($p['type_donnee'] === 'booleen'): ?>
                                <select class="form-select" id="param_<?= $p['id_parametre'] ?>" 
                                        name="parametres[<?= $p['id_parametre'] ?>]">
                                    <option value="1" <?= $p['valeur'] == '1' ? 'selected' : '' ?>>Oui</option>
                                    <option value="0" <?= $p['valeur'] == '0' ? 'selected' : '' ?>>Non</option>
                                </select>
                                
                            <?php elseif ($p['type_donnee'] === 'nombre'): ?>
                                <input type="number" class="form-control" 
                                       id="param_<?= $p['id_parametre'] ?>"
                                       name="parametres[<?= $p['id_parametre'] ?>]"
                                       value="<?= e($p['valeur']) ?>">
                                       
                            <?php elseif ($p['type_donnee'] === 'json'): ?>
                                <textarea class="form-control font-monospace" 
                                          id="param_<?= $p['id_parametre'] ?>"
                                          name="parametres[<?= $p['id_parametre'] ?>]"
                                          rows="3"><?= e($p['valeur']) ?></textarea>
                                          
                            <?php elseif ($p['type_donnee'] === 'date'): ?>
                                <input type="date" class="form-control" 
                                       id="param_<?= $p['id_parametre'] ?>"
                                       name="parametres[<?= $p['id_parametre'] ?>]"
                                       value="<?= e($p['valeur']) ?>">
                            
                            <?php else: ?>
                                <input type="text" class="form-control" 
                                       id="param_<?= $p['id_parametre'] ?>"
                                       name="parametres[<?= $p['id_parametre'] ?>]"
                                       value="<?= e($p['valeur']) ?>">
                            <?php endif; ?>
                            
                            <small class="text-muted">
                                Modifiable par: <?= e($p['modifiable_par']) ?> | 
                                Dernière modif: <?= format_datetime($p['date_modification']) ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="text-end mb-5">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<!-- Modal Nouveau Paramètre -->
<div class="modal fade" id="newParamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="new">
                
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau paramètre</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cle" class="form-label">Clé du paramètre</label>
                        <input type="text" class="form-control" id="cle" name="cle" required
                               placeholder="ex: app_name, mail_host, maintenance_mode">
                        <small class="text-muted">Utilisez des lettres minuscules et underscores</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valeur" class="form-label">Valeur par défaut</label>
                        <input type="text" class="form-control" id="valeur" name="valeur">
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_donnee" class="form-label">Type de donnée</label>
                        <select class="form-select" id="type_donnee" name="type_donnee">
                            <option value="texte">Texte</option>
                            <option value="nombre">Nombre</option>
                            <option value="booleen">Booléen</option>
                            <option value="json">JSON</option>
                            <option value="date">Date</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modifiable_par" class="form-label">Modifiable par</label>
                        <select class="form-select" id="modifiable_par" name="modifiable_par">
                            <option value="admin">Admin uniquement</option>
                            <option value="secretaire">Admin et secrétaire</option>
                            <option value="tous">Tous les rôles</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialisation des tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});
</script>

<?php include '../../includes/footer.php'; ?>