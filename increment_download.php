<?php
/**
 * Incrémente le compteur de téléchargements d'une attestation
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$attestation_id = $data['id'] ?? 0;

if ($attestation_id) {
    $stmt = $pdo->prepare("UPDATE attestations SET nb_telechargements = nb_telechargements + 1 WHERE id_attestation = ?");
    $stmt->execute([$attestation_id]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>