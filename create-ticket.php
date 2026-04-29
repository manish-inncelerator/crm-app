<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

// Start the session
session_start();

// Create a Guzzle client with SSL verification disabled for development
$httpClient = new Client([
    'verify' => false // Disable SSL verification for development
]);

// Auth0 configuration
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fyyz.link/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

// Create session store with configuration
$sessionStore = new SessionStore($config);

$auth0 = new Auth0($config);

// Debug information
writeLog('Create Ticket - Session data: ' . print_r($_SESSION, true));
writeLog('Create Ticket - Auth0 Configuration: ' . print_r([
    'domain' => $config->getDomain(),
    'clientId' => $config->getClientId(),
    'redirectUri' => $config->getRedirectUri()
], true));

try {
    // Get user info from Auth0
    $user = $auth0->getUser();
    writeLog('Create Ticket - Raw Auth0 user data: ' . print_r($user, true));

    if (!$user) {
        writeLog('Create Ticket - No user found in Auth0 session, redirecting to login', 'ERROR');
        header('Location: login.php');
        exit;
    }

    writeLog('Create Ticket - User data from Auth0: ' . print_r($user, true));

    // Get user data from database
    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        writeLog('Create Ticket - User not found in database with auth0_id: ' . $user['sub'], 'ERROR');
        header('Location: login.php');
        exit;
    }

    $is_admin = ($dbUser['role'] === 'admin');
    if ($is_admin) {
        writeLog('Create Ticket - Admin attempted to access create-ticket.php, redirecting to tickets.php');
        header('Location: tickets.php');
        exit;
    }

    writeLog('Create Ticket - User data from database: ' . print_r($dbUser, true));
} catch (\Exception $e) {
    writeLog('Create Ticket Error: ' . $e->getMessage(), 'ERROR');
    header('Location: login.php');
    exit;
}

html_start('Create Ticket');
?>

<script>
    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('dashboard-dark-mode', document.body.classList.contains('dark-mode'));
    }
    window.onload = function() {
        if (localStorage.getItem('dashboard-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    }
</script>
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    /* Only override the close icon in dark mode */
    .dark-mode .modal .btn-close {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M2.146 2.146a.5.5 0 0 1 .708 0L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important;
    }

    .modal .btn-close:hover {
        opacity: 0.75;
    }

    .dashboard-main-area {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
        animation: fadeUp 0.6s ease-out forwards;
    }
</style>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content dashboard-main-area">
        <?php include 'components/navbar.php'; ?>
        <div class="hero-greeting" style="margin-bottom: 2.5rem;">
            <div class="hero-info">
                <h1>Create a Support Ticket</h1>
                <p>Select the type of request below to get started and our team will process it shortly.</p>
            </div>
        </div>

        <div class="bento-grid">
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#estimateModal">
                <div class="bento-icon-wrapper"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                <h3>Create Estimates in QB</h3>
                <p>Request a new estimate creation in QuickBooks.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                <div class="bento-icon-wrapper"><i class="bi bi-arrow-left-right"></i></div>
                <h3>Actual Invoice</h3>
                <p>Request conversion of estimate into an actual invoice.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#poModal">
                <div class="bento-icon-wrapper"><i class="bi bi-cart-plus"></i></div>
                <h3>Purchase Order (PO)</h3>
                <p>Request a new Purchase Order for a supplier.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#paymentLinkModal">
                <div class="bento-icon-wrapper"><i class="bi bi-link-45deg"></i></div>
                <h3>Create Payment Link</h3>
                <p>Generate a payment link (Flywire/Tazapay/Airwallex).</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Update Payments in QB" data-type="Update Payments in QB">
                <div class="bento-icon-wrapper"><i class="bi bi-receipt"></i></div>
                <h3>Update Payments in QB</h3>
                <p>Update payments in QuickBooks and provide paid invoice to sales team.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Modify Estimates/Invoices" data-type="Modify Estimates/Invoices">
                <div class="bento-icon-wrapper"><i class="bi bi-pencil-square"></i></div>
                <h3>Modify Estimates/Invoices</h3>
                <p>Request modifications to existing estimates or invoices.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Customer Payment Follow-up" data-type="Customer Payment Follow-up">
                <div class="bento-icon-wrapper"><i class="bi bi-person-lines-fill"></i></div>
                <h3>Customer Payment Follow-up</h3>
                <p>Follow up with normal or corporate customers for payments.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#supplierModal">
                <div class="bento-icon-wrapper"><i class="bi bi-truck"></i></div>
                <h3>Supplier Payment Requests</h3>
                <p>Process payments for suppliers.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#offboardingModal">
                <div class="bento-icon-wrapper"><i class="bi bi-person-x"></i></div>
                <h3>Employee Offboarding</h3>
                <p>Complete requirements for resigning or duty release.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="QB Training (Estimates)" data-type="QB Training (Estimates)">
                <div class="bento-icon-wrapper"><i class="bi bi-easel"></i></div>
                <h3>Initial QB Training (Estimates)</h3>
                <p>Request initial training on QuickBooks for estimate creation.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Separate Payment Receipts" data-type="Separate Payment Receipts">
                <div class="bento-icon-wrapper"><i class="bi bi-receipt-cutoff"></i></div>
                <h3>Separate Payment Receipts</h3>
                <p>Request separate payment receipts for customers.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Share Bank Details" data-type="Share Bank Details">
                <div class="bento-icon-wrapper"><i class="bi bi-bank"></i></div>
                <h3>Share Bank Details</h3>
                <p>Share bank details file for accounts/customer payments.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#refundModal">
                <div class="bento-icon-wrapper"><i class="bi bi-arrow-counterclockwise"></i></div>
                <h3>Customers Refund</h3>
                <p>Request a refund for a customer payment.</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
            <a href="#" class="bento-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#amexModal">
                <div class="bento-icon-wrapper"><i class="bi bi-credit-card"></i></div>
                <h3>Payment from Amex Card (CC)</h3>
                <p>Log a payment received via Amex Card (Credit Card).</p>
                <div class="bento-badges mt-auto"><span class="bento-badge">Select &rarr;</span></div>
            </a>
        </div>
    </div>
</div>
<?php include 'components/bottom_navbar.php'; ?>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toast-title">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toast-message"></div>
    </div>
</div>

<!-- Estimate Modal -->
<div class="modal fade" id="estimateModal" tabindex="-1" aria-labelledby="estimateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="estimateModalLabel">Create Estimate Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Customer Name</label>
                        <input type="text" name="customer_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Billing Address</label>
                        <input type="text" name="billing_address" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Consultant Name</label>
                        <input type="text" name="consultant_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Service Date</label>
                        <input type="date" name="service_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type of Service (Package/Flights/Visa/Insurance)</label>
                        <input type="text" name="package_details" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Number of Persons</label>
                        <input type="number" name="number_of_persons" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rate per Person</label>
                        <input type="number" name="rate_per_person" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total Amount of Estimate</label>
                        <input type="number" name="total_amount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" required>
                            <option value="LOW">LOW</option>
                            <option value="MEDIUM">MEDIUM</option>
                            <option value="HIGH">HIGH</option>
                            <option value="URGENT">URGENT</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control rich-editor" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Any message on estimate</label>
                        <textarea name="estimate_message" class="form-control rich-editor"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Upload Supporting Image (optional)</label>
                        <input type="file" name="supporting_image" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalLabel">Supplier Payment Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="alert alert-info col-12 mb-0">
                        <strong>Important:</strong> All supplier invoices must be issued in their local currency:
                        <ul>
                            <li>Singapore suppliers: SGD</li>
                            <li>India suppliers: INR</li>
                            <li>Malaysia suppliers: RM</li>
                        </ul>
                        If a supplier cannot provide an invoice in their local currency, they must cover related bank charges.
                        <br><br>
                        <em>Note: If supplier payment is requested through the company credit card, all requirements below must still be fulfilled before approval.</em>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="supplier_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Travel Date</label>
                        <input type="date" name="travel_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Type</label>
                        <select name="payment_type" class="form-select" required>
                            <option value="Deposit">Deposit</option>
                            <option value="Full Payment">Full Payment</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Supplier Bank Details</label>
                        <input type="text" name="bank_details" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Complete Costing of the Deal (Sales, Cost, and Profit must be clearly stated)</label>
                        <textarea name="complete_costing" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer Invoice</label>
                        <input type="file" name="customer_invoice" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Proof</label>
                        <input type="file" name="payment_proof" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier Invoice</label>
                        <input type="file" name="supplier_invoice" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Any message/email from supplier</label>
                        <textarea name="supplier_message" class="form-control rich-editor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">Request Actual Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Client Type</label>
                        <select name="client_type" class="form-select" id="invoiceClientType" required>
                            <option value="">Select...</option>
                            <option value="Individual">Individual Client</option>
                            <option value="Corporate">Corporate Client (Existing Credit Terms)</option>
                            <option value="New Corporate">New Corporate Client</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 invoice-individual-field d-none">
                        <label class="form-label">Payment Proof</label>
                        <input type="file" name="payment_proof" class="form-control">
                    </div>
                    <div class="col-md-6 invoice-individual-field d-none">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="boss_confirmation" id="bossConfirmation">
                            <label class="form-check-label" for="bossConfirmation">
                                Confirmation from the Boss that payment has been credited to Fayyaz’s account
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6 invoice-new-corporate-field d-none">
                        <label class="form-label">Contract Copy</label>
                        <input type="file" name="contract_copy" class="form-control">
                    </div>
                    <div class="col-md-6 invoice-new-corporate-field d-none">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="boss_approval" id="bossApproval">
                            <label class="form-check-label" for="bossApproval">
                                Boss approval mandatory for new corporate clients
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Additional Information / Description</label>
                        <textarea name="description" class="form-control rich-editor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PO Modal -->
<div class="modal fade" id="poModal" tabindex="-1" aria-labelledby="poModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="poModalLabel">Request Purchase Order (PO)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="supplier_name" class="form-control" placeholder="e.g., Muhibbah, CTM" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client Name</label>
                        <input type="text" name="client_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">PO Date</label>
                        <input type="date" name="po_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rate</label>
                        <input type="number" step="0.01" name="rate" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description of Services/Items</label>
                        <textarea name="description" class="form-control" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Any message or instructions for the supplier</label>
                        <textarea name="supplier_instructions" class="form-control rich-editor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Link Modal -->
<div class="modal fade" id="paymentLinkModal" tabindex="-1" aria-labelledby="paymentLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentLinkModalLabel">Request Payment Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Client Name</label>
                        <input type="text" name="client_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (excluding charges)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Platform Preference</label>
                        <select name="platform" class="form-select" required>
                            <option value="Any">Any</option>
                            <option value="Flywire">Flywire</option>
                            <option value="Tazapay">Tazapay</option>
                            <option value="Airwallex">Airwallex</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Amex Modal -->
<div class="modal fade" id="amexModal" tabindex="-1" aria-labelledby="amexModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="amexModalLabel">Amex Credit Card Payment Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="alert alert-warning col-12 mb-0">
                        <strong>Note:</strong> Requests involving American Express credit card payments must be processed through the designated AMEX form. Please ensure the client has completed this form.
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Completed AMEX Form</label>
                        <input type="file" name="amex_form" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Additional Details / Description</label>
                        <textarea name="description" class="form-control rich-editor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="refundModalLabel">Request Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Reason for Refund</label>
                        <textarea name="reason" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer Invoice Copy</label>
                        <input type="file" name="customer_invoice" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Proof</label>
                        <input type="file" name="payment_proof" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier Invoice</label>
                        <input type="file" name="supplier_invoice" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Credit Note from Supplier or Supplier Refund Confirmation</label>
                        <input type="file" name="supplier_credit_note" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Customer Bank Account Details</label>
                        <textarea name="customer_bank_details" class="form-control" required></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="policy_confirmation" id="policyConfirmation" required>
                            <label class="form-check-label" for="policyConfirmation">
                                I confirm that the customer has been informed of the 14-day refund policy.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Offboarding Modal -->
<div class="modal fade" id="offboardingModal" tabindex="-1" aria-labelledby="offboardingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="offboardingModalLabel">Employee Offboarding Requirements</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="alert alert-warning col-12 mb-0">
                        <strong>Important:</strong> All tasks below must be completed before the last working day.
                    </div>
                    <div class="col-12">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="handover_estimates" id="handoverEstimates" required>
                            <label class="form-check-label" for="handoverEstimates">
                                Handover all open estimates to the assigned Travel Consultants (TCs)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="handover_payments" id="handoverPayments" required>
                            <label class="form-check-label" for="handoverPayments">
                                Handover pending (ongoing) supplier payments to assigned TCs
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="handover_receivables" id="handoverReceivables" required>
                            <label class="form-check-label" for="handoverReceivables">
                                Handover all receivables to assigned TCs
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="tracker_updated" id="trackerUpdated" required>
                            <label class="form-check-label" for="trackerUpdated">
                                Ensure Individual Tracker is fully updated
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" required>
                            <option value="LOW">LOW</option>
                            <option value="MEDIUM" selected>MEDIUM</option>
                            <option value="HIGH">HIGH</option>
                            <option value="URGENT">URGENT</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Additional Handover Notes (optional)</label>
                        <textarea name="description" class="form-control rich-editor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Offboarding Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- General Modal -->
<div class="modal fade" id="generalModal" tabindex="-1" aria-labelledby="generalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="generalModalLabel">Create Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control rich-editor" id="description" name="description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" required>
                            <option value="LOW">LOW</option>
                            <option value="MEDIUM">MEDIUM</option>
                            <option value="HIGH">HIGH</option>
                            <option value="URGENT">URGENT</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Supporting Image (optional)</label>
                        <input type="file" name="supporting_image" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Form toggle logic for Invoice Modal
    document.addEventListener('DOMContentLoaded', function() {
        const invoiceClientType = document.getElementById('invoiceClientType');
        if (invoiceClientType) {
            invoiceClientType.addEventListener('change', function() {
                const isIndividual = this.value === 'Individual';
                const isNewCorporate = this.value === 'New Corporate';
                
                document.querySelectorAll('.invoice-individual-field').forEach(el => {
                    el.classList.toggle('d-none', !isIndividual);
                    const input = el.querySelector('input');
                    if (input && input.type !== 'checkbox') input.required = isIndividual;
                });
                
                document.querySelectorAll('.invoice-new-corporate-field').forEach(el => {
                    el.classList.toggle('d-none', !isNewCorporate);
                    const input = el.querySelector('input');
                    if (input && input.type !== 'checkbox') input.required = isNewCorporate;
                });
            });
        }
    });

    // Set modal title dynamically for general modal
    const generalModal = document.getElementById('generalModal');
    if (generalModal) {
        generalModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const title = button.getAttribute('data-title') || 'Create Ticket';
            const type = button.getAttribute('data-type') || '';
            generalModal.querySelector('.modal-title').textContent = title;
            generalModal.querySelector('form').setAttribute('data-ticket-subtype', type);
        });
    }

    // Toast notification function
    function showToast(title, message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastTitle = document.getElementById('toast-title');
        const toastMessage = document.getElementById('toast-message');

        // Set toast style based on type
        toast.className = 'toast';
        if (type === 'success') {
            toast.classList.add('bg-success', 'text-white');
        } else if (type === 'error') {
            toast.classList.add('bg-danger', 'text-white');
        }

        // Set content
        toastTitle.textContent = title;
        toastMessage.textContent = message;

        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }

    // Handle form submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const modalId = this.closest('.modal').id;
            let ticketType = '';

            // Determine ticket type based on modal
            switch (modalId) {
                case 'estimateModal':
                    ticketType = 'estimate';
                    break;
                case 'supplierModal':
                    ticketType = 'supplier';
                    break;
                case 'invoiceModal':
                    ticketType = 'invoice';
                    break;
                case 'poModal':
                    ticketType = 'purchase_order';
                    break;
                case 'paymentLinkModal':
                    ticketType = 'payment_link';
                    break;
                case 'amexModal':
                    ticketType = 'amex_payment';
                    break;
                case 'refundModal':
                    ticketType = 'refund';
                    break;
                case 'offboardingModal':
                    ticketType = 'offboarding';
                    break;
                case 'generalModal':
                    ticketType = 'general';
                    break;
            }

            // Sync TinyMCE content back to textareas
            tinymce.get().forEach(editor => {
                const element = editor.getElement();
                if (this.contains(element)) {
                    element.value = editor.getContent();
                }
            });

            const formData = new FormData(this);
            formData.append('ticket_type', ticketType);
            if (ticketType === 'general') {
                formData.append('ticket_subtype', this.getAttribute('data-ticket-subtype'));
            }

            try {
                const response = await fetch('api/create-ticket.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Show success toast
                    showToast('Success', result.message);
                    // Close modal
                    bootstrap.Modal.getInstance(this.closest('.modal')).hide();
                    // Reset form
                    this.reset();
                    // Reset TinyMCE editors
                    tinymce.get().forEach(editor => {
                        const element = editor.getElement();
                        if (this.contains(element)) {
                            editor.setContent('');
                        }
                    });
                } else {
                    throw new Error(result.error || 'Failed to create ticket');
                }
            } catch (error) {
                // Show error toast
                showToast('Error', error.message, 'error');
                console.error('Error details:', error);
            }
        });
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.5.0/tinymce.min.js" integrity="sha512-OwyhkASccd6H2r1YXh1Wn6HDYWsaYqOBCoOPVQKl1vxEcSSVzMbYe0t2DfxG+ZeBXvPVEqUiS/52TCAz0kDysQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    tinymce.init({
        selector: '.rich-editor',
        plugins: 'lists link',
        toolbar: 'bold italic underline | bullist numlist | link',
        height: 300,
        menubar: false
    });
</script>
<?php include 'components/bottom_navbar.php'; ?>

<?php html_end(); ?>