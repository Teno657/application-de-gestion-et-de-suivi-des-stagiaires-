<?php
/**
 * Upload de document (Stagiaire)
 * Version corrigée - sans colonne description
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('stagiaire');

$page_title = "Uploader un document - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer l'encadreur du stagiaire pour la notification
$stmt = $pdo->prepare("
    SELECT e.id_encadreur, u.nom, u.prenom, u.email
    FROM stagiaires s
    LEFT JOIN encadreurs e ON s.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE s.id_stagiaire = ?
");
$stmt->execute([$user_id]);
$encadreur = $stmt->fetch();

// Traitement du formulaire
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $type_document = $_POST['type_document'] ?? 'autre';
        
        // Vérifier qu'un fichier a été uploadé
        if (empty($_FILES['document']['name'])) {
            $error = 'Veuillez sélectionner un fichier.';
        } else {
            // Configuration de l'upload
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/school-connection/assets/uploads/documents/';
            
            // Créer le dossier s'il n'existe pas
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Créer les sous-dossiers
            $subdirs = ['cv', 'lettres', 'rapports'];
            foreach ($subdirs as $subdir) {
                if (!is_dir($upload_dir . $subdir)) {
                    mkdir($upload_dir . $subdir, 0755, true);
                }
            }
            
            // Déterminer le sous-dossier selon le type de document
            $subfolder = '';
            switch ($type_document) {
                case 'cv':
                    $subfolder = 'cv/';
                    break;
                case 'lettre_motivation':
                    $subfolder = 'lettres/';
                    break;
                case 'rapport_stage':
                    $subfolder = 'rapports/';
                    break;
                default:
                    $subfolder = '';
            }
            
            // Générer un nom unique pour le fichier
            $extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
            
            if (!in_array($extension, $allowed_extensions)) {
                $error = 'Type de fichier non autorisé. Types acceptés: ' . implode(', ', $allowed_extensions);
            } elseif ($_FILES['document']['size'] > 10 * 1024 * 1024) {
                $error = 'Le fichier ne doit pas dépasser 10 Mo.';
            } else {
                $unique_name = uniqid() . '_' . time() . '.' . $extension;
                $target_path = $upload_dir . $subfolder . $unique_name;
                $db_path = 'assets/uploads/documents/' . $subfolder . $unique_name;
                
                // Déplacer le fichier
                if (move_uploaded_file($_FILES['document']['tmp_name'], $target_path)) {
                    // 🔧 CORRECTION: Insertion sans la colonne description
                    $stmt = $pdo->prepare("
                        INSERT INTO documents 
                        (id_utilisateur, nom_fichier, chemin, taille, type_fichier, type_document, date_upload)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $result = $stmt->execute([
                        $user_id,
                        $_FILES['document']['name'],
                        $db_path,
                        $_FILES['document']['size'],
                        $_FILES['document']['type'],
                        $type_document
                    ]);
                    
                    if ($result) {
                        $doc_id = $pdo->lastInsertId();
                        
                        // Envoyer une notification à l'encadreur si existant
                        if ($encadreur && $encadreur['id_encadreur']) {
                            try {
                                $notification_message = $_SESSION['user_prenom'] . " " . $_SESSION['user_nom'] . " a uploadé un nouveau document: " . $_FILES['document']['name'];
                                
                                // Vérifier si la table notifications existe
                                $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
                                if ($stmt->rowCount() > 0) {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO notifications (id_utilisateur, titre, message, type_notification, lien, est_lue, date_creation)
                                        VALUES (?, 'Nouveau document uploadé', ?, 'document', 'dashboard/encadreur/documents.php', 0, NOW())
                                    ");
                                    $stmt->execute([$encadreur['id_encadreur'], $notification_message]);
                                }
                            } catch (Exception $e) {
                                // Ignorer les erreurs de notification
                            }
                        }
                        
                        $success = 'Document uploadé avec succès ! Votre encadreur a été notifié.';
                        
                        // Rediriger après 2 secondes
                        header('refresh:2;url=documents.php');
                    } else {
                        $error = 'Erreur lors de l\'enregistrement en base de données.';
                    }
                } else {
                    $error = 'Erreur lors du déplacement du fichier.';
                }
            }
        }
    }
}

include '../../includes/header.php';
?>

<style>
    :root {
        --primary: #667eea;
        --secondary: #764ba2;
    }
    
    .hero-upload {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 25px;
        padding: 40px;
        margin-bottom: 30px;
    }
    
    .upload-card {
        background: white;
        border-radius: 25px;
        padding: 35px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .dropzone {
        border: 2px dashed #e5e7eb;
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .dropzone:hover, .dropzone.drag-over {
        border-color: var(--primary);
        background: #f8f9ff;
    }
    
    .form-control, .form-select {
        border-radius: 12px;
        padding: 12px 16px;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    
    .btn-submit {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: none;
        padding: 14px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102,126,234,0.4);
    }
    
    .file-info {
        margin-top: 10px;
        font-size: 0.85rem;
        color: #6b7280;
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-upload">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-upload me-3"></i>
                    Uploader un document
                </h1>
                <p class="lead text-white-50 mb-0">
                    Partagez vos documents avec votre encadreur
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="documents.php" class="btn btn-light btn-lg rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>
                    Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="upload-card">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $success ?>
                        <br><small>Redirection en cours...</small>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                  
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-file-alt text-primary me-2"></i>Fichier *
                        </label>
                        <div class="dropzone" id="dropzone">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <p class="mb-2">Glissez-déposez votre fichier ici ou <strong>cliquez pour parcourir</strong></p>
                            <small class="text-muted">Formats acceptés: PDF, DOC, DOCX, JPG, PNG (max 10 Mo)</small>
                            <input type="file" name="document" id="fileInput" style="display: none;" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                            <div class="file-info mt-2" id="fileInfo"></div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Votre encadreur recevra une notification dès que vous uploaderez un document.
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-submit text-white flex-grow-1">
                            <i class="fas fa-upload me-2"></i>
                            Uploader le document
                        </button>
                        <a href="documents.php" class="btn btn-outline-secondary px-4">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    
    // Gérer le clic sur la zone de drop
    dropzone.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Gérer le drag & drop
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });
    
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('drag-over');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileInfo(files[0]);
        }
    });
    
    // Gérer la sélection de fichier
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            updateFileInfo(fileInput.files[0]);
        }
    });
    
    function updateFileInfo(file) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        fileInfo.innerHTML = `
            <i class="fas fa-check-circle text-success me-1"></i>
            Fichier sélectionné: <strong>${file.name}</strong> (${sizeMB} Mo)
        `;
    }
</script>

<?php include '../../includes/footer.php'; ?>