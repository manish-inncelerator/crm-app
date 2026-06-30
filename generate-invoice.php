<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;

session_start();

$httpClient = new Client(['verify' => false]);
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);
$auth0 = new Auth0($config);

$user = $auth0->getUser();
if (!$user) {
    die("Unauthorized.");
}

$dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
if (!$dbUser) {
    die("User not found.");
}

// Either accept an invoice_id directly, or a ticket_id
$invoiceId = $_GET['id'] ?? null;
$ticketId = $_GET['ticket_id'] ?? null;

if ($invoiceId) {
    $invoice = $database->get('invoices', '*', ['id' => $invoiceId]);
    if (!$invoice) die("Invoice not found.");
    $ticket = $database->get('tickets_unified', '*', ['id' => $invoice['ticket_id']]);
} elseif ($ticketId) {
    $ticket = $database->get('tickets_unified', '*', ['id' => $ticketId]);
    if (!$ticket) die("Ticket not found.");
    
    // Find existing invoice or create one
    $invoice = $database->get('invoices', '*', ['ticket_id' => $ticketId]);
    if (!$invoice) {
        $database->insert('invoices', [
            'ticket_id' => $ticketId,
            'user_id' => $ticket['user_id'],
            'amount' => $ticket['amount'],
            'currency' => $ticket['currency'] ?: 'USD',
            'status' => 'ISSUED',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $invoiceId = $database->id();
        $invoice = $database->get('invoices', '*', ['id' => $invoiceId]);
    }
} else {
    die("No invoice or ticket specified.");
}

$client = $database->get('users', '*', ['id' => $invoice['user_id']]);
if (!$client) die("Client not found.");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #INV-<?= str_pad($invoice['id'], 5, '0', STR_PAD_LEFT) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .invoice-container { max-width: 800px; margin: 3rem auto; background: #fff; padding: 4rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .invoice-header { display: flex; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 2rem; margin-bottom: 2rem; }
        .invoice-logo { font-size: 2rem; font-weight: 800; color: #2563eb; letter-spacing: -1px; }
        .invoice-title { font-size: 2.5rem; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 0.5rem; text-align: right; }
        .invoice-meta { text-align: right; color: #64748b; }
        
        .section-title { font-size: 0.85rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem; }
        
        .client-info { background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin-bottom: 2.5rem; border: 1px solid #f1f5f9; }
        
        .invoice-table th { background: #f1f5f9; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 1rem; }
        .invoice-table td { padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .total-row { display: flex; justify-content: flex-end; margin-top: 2rem; }
        .total-box { background: #f8fafc; padding: 1.5rem 2rem; border-radius: 8px; min-width: 300px; border: 1px solid #e2e8f0; }
        .total-line { display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 1rem; color: #64748b; }
        .total-final { display: flex; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #e2e8f0; font-size: 1.5rem; font-weight: 800; color: #0f172a; }
        
        .footer-note { text-align: center; margin-top: 4rem; color: #94a3b8; font-size: 0.9rem; border-top: 1px solid #f1f5f9; padding-top: 2rem; }
        
        @media print {
            body { background: #fff; }
            .invoice-container { box-shadow: none; margin: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-bold shadow"><i class="bi bi-printer-fill me-2"></i> Print / Save as PDF</button>
        <button onclick="window.close()" class="btn btn-light border px-4 py-2 fw-bold shadow-sm ms-2">Close</button>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div>
                <div class="invoice-logo">Fayyaz Travels.</div>
                <div class="text-muted mt-2">123 Business Avenue<br>Finance District, NY 10001<br>billing@fayyaz.travel</div>
            </div>
            <div>
                <div class="invoice-title">RECEIPT</div>
                <div class="invoice-meta">
                    <strong>Invoice #:</strong> INV-<?= str_pad($invoice['id'], 5, '0', STR_PAD_LEFT) ?><br>
                    <strong>Date Issued:</strong> <?= date('F j, Y', strtotime($invoice['created_at'])) ?><br>
                    <strong>Status:</strong> <span class="badge bg-success ms-1"><?= $invoice['status'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="section-title">Billed To</div>
                <div class="client-info">
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($client['name']) ?></h5>
                    <div class="text-muted"><?= htmlspecialchars($client['email']) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="section-title">Reference Ticket</div>
                <div class="client-info bg-white border">
                    <div class="fw-bold">#<?= str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($ticket['subtype']) ?></div>
                    <div class="text-muted small">Status: <?= $ticket['status'] ?></div>
                </div>
            </div>
        </div>
        
        <table class="table invoice-table mt-2">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="fw-bold text-dark"><?= ucfirst($ticket['type']) ?> Ticket Processing</div>
                        <div class="text-muted small mt-1"><?= htmlspecialchars($ticket['description'] ?? 'Standard processing fee or refund value.') ?></div>
                    </td>
                    <td class="text-end fw-bold text-dark">$<?= number_format($invoice['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="total-row">
            <div class="total-box">
                <div class="total-line">
                    <span>Subtotal</span>
                    <span>$<?= number_format($invoice['amount'], 2) ?></span>
                </div>
                <div class="total-line">
                    <span>Tax (0%)</span>
                    <span>$0.00</span>
                </div>
                <div class="total-final">
                    <span>Total Paid</span>
                    <span>$<?= number_format($invoice['amount'], 2) ?> <?= htmlspecialchars($invoice['currency']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="footer-note">
            <p class="fw-bold mb-1">Thank you for your business!</p>
            <p class="mb-0">If you have any questions regarding this receipt, please contact our support team.</p>
        </div>
    </div>

</body>
</html>
