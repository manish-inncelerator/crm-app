<?php
require_once '../database.php';

header('Content-Type: application/json');

try {
    // 1. Create Tables
    $database->query("CREATE TABLE IF NOT EXISTS kb_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        icon VARCHAR(100) DEFAULT 'bi-info-circle',
        display_order INT DEFAULT 0
    )");

    $database->query("CREATE TABLE IF NOT EXISTS kb_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        subtitle VARCHAR(255) DEFAULT NULL,
        badge VARCHAR(50) DEFAULT NULL,
        type ENUM('table', 'grid') DEFAULT 'table',
        display_order INT DEFAULT 0
    )");

    $database->query("CREATE TABLE IF NOT EXISTS kb_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        label VARCHAR(255) DEFAULT NULL,
        value TEXT DEFAULT NULL,
        description TEXT DEFAULT NULL,
        is_copyable TINYINT(1) DEFAULT 0,
        display_order INT DEFAULT 0
    )");

    // 2. Check if already seeded
    $count = $database->count('kb_sections');
    if ($count > 0) {
        echo json_encode(['success' => true, 'message' => 'Database already seeded']);
        exit;
    }

    // 3. Seed Data
    // Section 1: Singapore Payments
    $database->insert('kb_sections', ['title' => 'Singapore Payments', 'icon' => 'bi-geo-alt-fill', 'display_order' => 1]);
    $sec1_id = $database->id();

    $database->insert('kb_cards', [
        'section_id' => $sec1_id,
        'title' => 'Local Bank Transfers & PAYNOW',
        'badge' => 'SGD',
        'type' => 'table',
        'display_order' => 1
    ]);
    $card1_id = $database->id();

    $database->insert('kb_items', [
        ['card_id' => $card1_id, 'label' => 'Payments by Cheque', 'value' => 'Payable to: Fayyaz Travels Pte Ltd', 'is_copyable' => 1, 'display_order' => 1],
        ['card_id' => $card1_id, 'label' => 'PAYNOW', 'value' => 'UEN: 201010203DFTD', 'is_copyable' => 1, 'display_order' => 2],
        ['card_id' => $card1_id, 'label' => 'UOB Bank', 'value' => 'Account: 357-303-2266<br>Code: 7375 | Branch: 018<br>SWIFT: UOVBSGSG', 'is_copyable' => 1, 'display_order' => 3],
        ['card_id' => $card1_id, 'label' => 'DBS Bank', 'value' => 'Account: 107-902401-7<br>Code: 7171 | Branch: 107<br>SWIFT: DBSSSGSG', 'is_copyable' => 1, 'display_order' => 4]
    ]);

    // Section 2: International Bank Details
    $database->insert('kb_sections', ['title' => 'International Bank Details', 'icon' => 'bi-globe', 'display_order' => 2]);
    $sec2_id = $database->id();

    // UK
    $database->insert('kb_cards', ['section_id' => $sec2_id, 'title' => 'United Kingdom (Modulr FS)', 'badge' => 'GBP', 'type' => 'table', 'display_order' => 1]);
    $card2_id = $database->id();
    $database->insert('kb_items', [
        ['card_id' => $card2_id, 'label' => 'Account Name', 'value' => 'FAYYAZ TRAVELS PTE. LTD.', 'is_copyable' => 1, 'display_order' => 1],
        ['card_id' => $card2_id, 'label' => 'Account Number', 'value' => '00959162', 'is_copyable' => 1, 'display_order' => 2],
        ['card_id' => $card2_id, 'label' => 'Sort Code', 'value' => '040085', 'is_copyable' => 1, 'display_order' => 3],
        ['card_id' => $card2_id, 'label' => 'IBAN', 'value' => 'GB56MODR04008500959162', 'is_copyable' => 1, 'display_order' => 4],
        ['card_id' => $card2_id, 'label' => 'SWIFT', 'value' => 'MODRGB21', 'is_copyable' => 1, 'display_order' => 5]
    ]);

    // USA
    $database->insert('kb_cards', ['section_id' => $sec2_id, 'title' => 'USA (Community Federal Savings)', 'badge' => 'USD', 'type' => 'table', 'display_order' => 2]);
    $card3_id = $database->id();
    $database->insert('kb_items', [
        ['card_id' => $card3_id, 'label' => 'Account Number', 'value' => '8488858126', 'is_copyable' => 1, 'display_order' => 1],
        ['card_id' => $card3_id, 'label' => 'ACH Routing', 'value' => '026073150', 'is_copyable' => 1, 'display_order' => 2],
        ['card_id' => $card3_id, 'label' => 'Fedwire Routing', 'value' => '026073008', 'is_copyable' => 1, 'display_order' => 3],
        ['card_id' => $card3_id, 'label' => 'SWIFT', 'value' => 'CMFGUS33', 'is_copyable' => 1, 'display_order' => 4],
        ['card_id' => $card3_id, 'label' => 'Type', 'value' => 'Checking', 'is_copyable' => 0, 'display_order' => 5]
    ]);

    // Hong Kong
    $database->insert('kb_cards', ['section_id' => $sec2_id, 'title' => 'Hong Kong (Standard Chartered)', 'badge' => 'HKD', 'type' => 'table', 'display_order' => 3]);
    $card4_id = $database->id();
    $database->insert('kb_items', [
        ['card_id' => $card4_id, 'label' => 'Account Number', 'value' => '47412422538', 'is_copyable' => 1, 'display_order' => 1],
        ['card_id' => $card4_id, 'label' => 'Bank Code', 'value' => '003', 'is_copyable' => 1, 'display_order' => 2],
        ['card_id' => $card4_id, 'label' => 'Branch Code', 'value' => '474', 'is_copyable' => 1, 'display_order' => 3],
        ['card_id' => $card4_id, 'label' => 'SWIFT', 'value' => 'SCBLHKHH', 'is_copyable' => 1, 'display_order' => 4]
    ]);

    // UAE
    $database->insert('kb_cards', ['section_id' => $sec2_id, 'title' => 'UAE (Standard Chartered Dubai)', 'badge' => 'AED', 'type' => 'table', 'display_order' => 4]);
    $card5_id = $database->id();
    $database->insert('kb_items', [
        ['card_id' => $card5_id, 'label' => 'IBAN', 'value' => 'AE840446498900000000973', 'is_copyable' => 1, 'display_order' => 1],
        ['card_id' => $card5_id, 'label' => 'SWIFT', 'value' => 'SCBLAEAD', 'is_copyable' => 1, 'display_order' => 2],
        ['card_id' => $card5_id, 'label' => 'Location', 'value' => 'United Arab Emirates', 'is_copyable' => 0, 'display_order' => 3]
    ]);

    // Canada
    $database->insert('kb_cards', ['section_id' => $sec2_id, 'title' => 'Canada (Digital Commerce Bank)', 'badge' => 'CAD', 'type' => 'table', 'display_order' => 5]);
    $card6_id = $database->id();
    $database->insert('kb_items', [
        ['card_id' => $card6_id, 'label' => 'Account Number', 'value' => '972721732', 'is_copyable' => 1, 'display_order' => 1],
        ['card_id' => $card6_id, 'label' => 'Transit Number', 'value' => '10009', 'is_copyable' => 1, 'display_order' => 2],
        ['card_id' => $card6_id, 'label' => 'Inst. Number', 'value' => '352', 'is_copyable' => 1, 'display_order' => 3],
        ['card_id' => $card6_id, 'label' => 'Interac Email', 'value' => 'fayyaztravelssg@gmail.com', 'is_copyable' => 1, 'display_order' => 4]
    ]);

    // Section 3: Payment Links
    $database->insert('kb_sections', ['title' => 'Payment Links', 'icon' => 'bi-link-45deg', 'display_order' => 3]);
    $sec3_id = $database->id();

    $database->insert('kb_cards', [
        'section_id' => $sec3_id,
        'title' => 'Payment Platforms',
        'type' => 'grid',
        'display_order' => 1
    ]);
    $card7_id = $database->id();

    $database->insert('kb_items', [
        [
            'card_id' => $card7_id, 
            'label' => 'Flywire', 
            'value' => '2.5% flat on total amount whether inside country or outside.', 
            'description' => 'Global payment solution for international transfers.', 
            'display_order' => 1
        ],
        [
            'card_id' => $card7_id, 
            'label' => 'Taza Pay', 
            'value' => '2.5% flat on total amount if inside country and 3.5% if outside Singapore', 
            'description' => 'Cross-border payments for businesses.', 
            'display_order' => 2
        ]
    ]);

    echo json_encode(['success' => true, 'message' => 'Database tables created and seeded successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
