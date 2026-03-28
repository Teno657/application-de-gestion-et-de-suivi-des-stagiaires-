<?php
/**
 * Téléchargement sécurisé d'attestation
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!is_logged_in()) {
    header('HTTP/1.0 403 Forbidden');
    die('Accès non autorisé');
}

$attestation_id = (int)($_GET['id'] ?? 0);

if (!$attestation_id) {
    header('HTTP/1.0 404 Not Found');
    die('Attestation non trouvée');
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, s.id_stagiaire
        FROM attestations a
        JOIN stagiaires s ON a.id_stagiaire = s.id_stagiaire
        WHERE a.id_attestation = ?
    ");
    $stmt->execute([$attestation_id]);
    $attestation = $stmt->fetch();
    
    if (!$attestation) {
        header('HTTP/1.0 404 Not Found');
        die('Attestation non trouvée');
    }
    
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/school-connection/' . $attestation['chemin_fichier'];
    
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        die('Fichier non trouvé');
    }
    
    // Incrémenter le compteur de téléchargements
    $stmt = $pdo->prepare("UPDATE attestations SET nb_telechargements = nb_telechargements + 1 WHERE id_attestation = ?");
    $stmt->execute([$attestation_id]);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attestation_' . $attestation['numero_attestation'] . '.pdf"');
    header('Content-Length: ' . filesize($file_path));
    
    ob_clean();
    flush();
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    die('Erreur: ' . $e->getMessage());
}
?>