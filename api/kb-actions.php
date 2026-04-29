<?php
require_once '../database.php';
require_once '../vendor/autoload.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;

session_start();

// Auth0 check
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here'
);
$auth0 = new Auth0($config);

$user = $auth0->getUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
if (!$dbUser || !($dbUser['is_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? '';

try {
    switch ($action) {
        // --- SECTIONS ---
        case 'add_section':
            $database->insert('kb_sections', [
                'title' => $data['title'],
                'icon' => $data['icon'] ?? 'bi-info-circle',
                'display_order' => $data['display_order'] ?? 0
            ]);
            echo json_encode(['success' => true, 'id' => $database->id()]);
            break;

        case 'edit_section':
            $database->update('kb_sections', [
                'title' => $data['title'],
                'icon' => $data['icon'],
                'display_order' => $data['display_order']
            ], ['id' => $data['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_section':
            // Recursive delete? For safety, we just delete the section. 
            // In a real app, we'd delete cards and items too.
            $database->delete('kb_sections', ['id' => $data['id']]);
            $database->delete('kb_cards', ['section_id' => $data['id']]);
            echo json_encode(['success' => true]);
            break;

        // --- CARDS ---
        case 'add_card':
            $database->insert('kb_cards', [
                'section_id' => $data['section_id'],
                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? NULL,
                'badge' => $data['badge'] ?? NULL,
                'type' => $data['type'] ?? 'table',
                'display_order' => $data['display_order'] ?? 0
            ]);
            echo json_encode(['success' => true, 'id' => $database->id()]);
            break;

        case 'edit_card':
            $database->update('kb_cards', [
                'title' => $data['title'],
                'subtitle' => $data['subtitle'],
                'badge' => $data['badge'],
                'type' => $data['type'],
                'display_order' => $data['display_order']
            ], ['id' => $data['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_card':
            $database->delete('kb_cards', ['id' => $data['id']]);
            $database->delete('kb_items', ['card_id' => $data['id']]);
            echo json_encode(['success' => true]);
            break;

        // --- ITEMS ---
        case 'add_item':
            $database->insert('kb_items', [
                'card_id' => $data['card_id'],
                'label' => $data['label'],
                'value' => $data['value'],
                'description' => $data['description'] ?? NULL,
                'is_copyable' => $data['is_copyable'] ?? 0,
                'display_order' => $data['display_order'] ?? 0
            ]);
            echo json_encode(['success' => true, 'id' => $database->id()]);
            break;

        case 'edit_item':
            $database->update('kb_items', [
                'label' => $data['label'],
                'value' => $data['value'],
                'description' => $data['description'],
                'is_copyable' => $data['is_copyable'],
                'display_order' => $data['display_order']
            ], ['id' => $data['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_item':
            $database->delete('kb_items', ['id' => $data['id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
