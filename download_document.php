<?php
/**
 * Téléchargement sécurisé de document
 * Version avec recherche automatique du fichier
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier que l'utilisateur est connecté
if (!is_logged_in()) {
    header('HTTP/1.0 403 Forbidden');
    die('Accès non autorisé');
}

$document_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

if (!$document_id) {
    header('HTTP/1.0 404 Not Found');
    die('Document non trouvé');
}

try {
    // Récupérer les informations du document
    $stmt = $pdo->prepare("
        SELECT d.*, u.id_utilisateur as proprietaire_id
        FROM documents d
        JOIN utilisateurs u ON d.id_utilisateur = u.id_utilisateur
        WHERE d.id_document = ?
    ");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        header('HTTP/1.0 404 Not Found');
        die('Document non trouvé');
    }
    
    // Vérifier les permissions
    $has_access = false;
    
    if ($document['proprietaire_id'] == $user_id) {
        $has_access = true;
    }
    
    if (in_array($user_role, ['encadreur_pro', 'encadreur_acro'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM stagiaires 
            WHERE id_stagiaire = ? AND id_encadreur = ?
        ");
        $stmt->execute([$document['proprietaire_id'], $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $has_access = true;
        }
    }
    
    if ($user_role === 'secretaire' || (isset($_SESSION['est_admin']) && $_SESSION['est_admin'] === true)) {
        $has_access = true;
    }
    
    if (!$has_access) {
        header('HTTP/1.0 403 Forbidden');
        die('Accès non autorisé à ce document');
    }
    
    // 🔧 RECHERCHE AUTOMATIQUE DU FICHIER
    $file_found = false;
    $file_path = '';
    
    // Nettoyer le chemin stocké
    $stored_path = $document['chemin'];
    $filename = basename($stored_path);
    
    // Liste des chemins possibles
    $base_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/school-connection/assets/uploads/documents/',
        $_SERVER['DOCUMENT_ROOT'] . '/school-connection/assets/uploads/',
        $_SERVER['DOCUMENT_ROOT'] . '/school-connection/uploads/documents/',
        $_SERVER['DOCUMENT_ROOT'] . '/school-connection/uploads/',
    ];
    
    // Sous-dossiers possibles
    $subdirs = ['', 'cv/', 'lettres/', 'rapports/', 'photos/', 'documents/'];
    
    // Parcourir tous les chemins possibles
    foreach ($base_paths as $base) {
        foreach ($subdirs as $subdir) {
            $test_path = $base . $subdir . $filename;
            if (file_exists($test_path)) {
                $file_path = $test_path;
                $file_found = true;
                break 2;
            }
        }
    }
    
    // Si toujours pas trouvé, essayer avec le chemin stocké
    if (!$file_found) {
        $test_path = $_SERVER['DOCUMENT_ROOT'] . '/school-connection/' . $stored_path;
        if (file_exists($test_path)) {
            $file_path = $test_path;
            $file_found = true;
        }
    }
    
    // Dernier essai : chercher dans tout le dossier uploads
    if (!$file_found) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/school-connection/assets/uploads/';
        if (is_dir($upload_dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $filename) {
                    $file_path = $file->getPathname();
                    $file_found = true;
                    break;
                }
            }
        }
    }
    
    if (!$file_found) {
        header('HTTP/1.0 404 Not Found');
        echo "Fichier introuvable. Recherché: $filename<br>";
        echo "Chemin stocké: $stored_path<br>";
        echo "ID Document: $document_id";
        exit;
    }
    
    // Récupérer l'extension et le type MIME
    $extension = strtolower(pathinfo($document['nom_fichier'], PATHINFO_EXTENSION));
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'zip' => 'application/zip'
    ];
    
    $mime_type = $mime_types[$extension] ?? 'application/octet-stream';
    
    // Forcer le téléchargement
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $document['nom_fichier'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Nettoyer les buffers
    ob_clean();
    flush();
    
    // Envoyer le fichier
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    die('Erreur base de données: ' . $e->getMessage());
}
?>