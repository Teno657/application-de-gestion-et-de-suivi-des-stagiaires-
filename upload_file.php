<?php
/**
 * AJAX: Upload de fichier
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier la connexion
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

// Vérifier le token CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

// Vérifier si un fichier a été uploadé
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucun fichier uploadé']);
    exit;
}

$type = $_POST['type'] ?? 'document'; // photo, document, attestation
$subdir = $_POST['subdir'] ?? '';

// Déterminer le dossier de destination
$upload_dir = UPLOADS_PATH;
switch ($type) {
    case 'photo':
        $upload_dir .= 'photos/';
        $allowed_types = ALLOWED_IMAGES;
        $max_size = 2 * 1024 * 1024; // 2 Mo
        break;
        
    case 'attestation':
        $upload_dir .= 'attestations/';
        $allowed_types = ['application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5 Mo
        break;
        
    case 'document':
    default:
        $upload_dir .= 'documents/';
        if (!empty($subdir)) {
            $upload_dir .= $subdir . '/';
        }
        $allowed_types = ALLOWED_DOCUMENTS;
        $max_size = 10 * 1024 * 1024; // 10 Mo
        break;
}

// Upload du fichier
$result = upload_file($_FILES['file'], $upload_dir, $allowed_types, $max_size);

if ($result['success']) {
    // Journaliser l'upload
    log_action(
        $_SESSION['user_id'],
        'UPLOAD',
        "Upload du fichier: {$result['original_name']}",
        'creation',
        'documents'
    );
    
    echo json_encode([
        'success' => true,
        'filename' => $result['filename'],
        'path' => $result['path'],
        'size' => $result['size'],
        'size_formatted' => format_filesize($result['size']),
        'original_name' => $result['original_name']
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $result['message']
    ]);
}
?>