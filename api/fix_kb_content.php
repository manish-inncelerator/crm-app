<?php
require_once '../database.php';

header('Content-Type: application/json');

try {
    // 1. Find the Payment Links section
    $section = $database->get('kb_sections', '*', ['title' => 'Payment Links']);
    if (!$section) {
        echo json_encode(['success' => false, 'error' => 'Payment Links section not found']);
        exit;
    }

    // 2. Create a single card for all platforms
    $database->insert('kb_cards', [
        'section_id' => $section['id'],
        'title' => 'Payment Platforms',
        'type' => 'grid',
        'display_order' => 1
    ]);
    $new_card_id = $database->id();

    // 3. Add the items in the requested format
    $database->insert('kb_items', [
        [
            'card_id' => $new_card_id,
            'label' => 'Flywire',
            'value' => '2.5% flat on total amount whether inside country or outside.',
            'description' => 'Global payment solution for international transfers.',
            'display_order' => 1
        ],
        [
            'card_id' => $new_card_id,
            'label' => 'Taza Pay',
            'value' => '2.5% flat on total amount if inside country and 3.5% if outside Singapore',
            'description' => 'Cross-border payments for businesses.',
            'display_order' => 2
        ]
    ]);

    // 4. Delete the old redundant cards
    // We look for cards in the Payment Links section that are NOT the new one
    $database->delete('kb_cards', [
        'AND' => [
            'section_id' => $section['id'],
            'id[!]' => $new_card_id
        ]
    ]);

    echo json_encode(['success' => true, 'message' => 'Payment link data consolidated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
