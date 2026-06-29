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

$httpClient = new Client([
    'verify' => false
]);

$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

$sessionStore = new SessionStore($config);
$auth0 = new Auth0($config);

try {
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }

    $is_admin = (bool)($dbUser['is_admin'] ?? false);
    if ($is_admin) {
        header('Location: tickets.php');
        exit;
    }

    $activeUsers = $database->select('users', ['id', 'name'], [
        'is_ex_employee' => 0,
        'ORDER' => ['name' => 'ASC']
    ]);

    $suppliers = $database->select('suppliers', ['id', 'name'], [
        'ORDER' => ['name' => 'ASC']
    ]);

} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Create Ticket');
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    :root {
        --primary-color: #2563eb;
        --primary-hover: #1d4ed8;
        --surface-color: #ffffff;
        --border-color: #e5e7eb;
        --text-main: #111827;
        --text-muted: #6b7280;
        --bg-color: #f3f4f6;
        --radius: 12px;
    }
    
    .dark-mode {
        --primary-color: #3b82f6;
        --primary-hover: #60a5fa;
        --surface-color: #1f2937;
        --border-color: #374151;
        --text-main: #f9fafb;
        --text-muted: #9ca3af;
        --bg-color: #111827;
    }

    body {
        background-color: var(--bg-color);
        color: var(--text-main);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .ticket-creation-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
        animation: fadeUp 0.5s ease-out;
    }

    .ticket-card {
        background: var(--surface-color);
        border-radius: var(--radius);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .ticket-header {
        padding: 2rem;
        border-bottom: 1px solid var(--border-color);
        background: linear-gradient(to right, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.02));
    }

    .ticket-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-main);
    }
    
    .ticket-header p {
        color: var(--text-muted);
        margin: 0;
    }

    .ticket-body {
        padding: 2.5rem 2rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid var(--border-color);
        padding: 0.75rem 1rem;
        background-color: var(--surface-color);
        color: var(--text-main);
        transition: all 0.2s;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .dark-mode .form-control, .dark-mode .form-select {
        background-color: #374151;
        color: #fff;
    }

    .dynamic-section {
        display: none;
        animation: fadeIn 0.4s ease-out;
    }

    .dynamic-section.active {
        display: block;
    }

    .btn-submit {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        padding: 0.75rem 2rem;
        border-radius: 8px;
        border: none;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
    }

    .btn-submit:hover {
        background-color: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.3);
    }

    .category-icon {
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Steps Indicator */
    .steps {
        display: flex;
        margin-bottom: 2rem;
        align-items: center;
    }
    .step {
        flex: 1;
        text-align: center;
        position: relative;
    }
    .step::after {
        content: '';
        position: absolute;
        top: 15px;
        right: -50%;
        width: 100%;
        height: 2px;
        background-color: var(--border-color);
        z-index: 1;
    }
    .step:last-child::after {
        display: none;
    }
    .step.active .step-icon {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .step.completed .step-icon {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .step.completed::after {
        background-color: var(--primary-color);
    }
    .step-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: var(--surface-color);
        border: 2px solid var(--border-color);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.5rem;
        position: relative;
        z-index: 2;
        font-weight: 600;
        transition: all 0.3s;
    }
    .step-text {
        font-size: 0.85rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    .step.active .step-text {
        color: var(--primary-color);
    }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="ticket-creation-container">
            <div class="ticket-card">
                <div class="ticket-header">
                    <h1>Create Support Ticket</h1>
                    <p>Select a category and provide the necessary details to submit your request.</p>
                </div>
                
                <div class="ticket-body">
                    <form id="unifiedTicketForm" enctype="multipart/form-data">
                        
                        <!-- Common Fields (Step 1) -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Ticket Category <span class="text-danger">*</span></label>
                                <select name="ticket_category" id="ticketCategory" class="form-select" required>
                                    <option value="" disabled selected>Select a category...</option>
                                    <optgroup label="Estimates & Invoices">
                                        <option value="estimate">Create Estimate in QB</option>
                                        <option value="invoice">Convert Estimate to Actual Invoice</option>
                                        <option value="modify_ticket">Modify Estimates/Invoices</option>
                                    </optgroup>
                                    <optgroup label="Payments">
                                        <option value="payment_link">Create Payment Link</option>
                                        <option value="payment_update">Update Payments in QB</option>
                                        <option value="amex_payment">Payment from Amex Card (CC)</option>
                                        <option value="refund">Customers Refund</option>
                                    </optgroup>
                                    <optgroup label="Suppliers">
                                        <option value="supplier">Supplier Payment Request</option>
                                        <option value="purchase_order">Request Purchase Order (PO)</option>
                                    </optgroup>
                                    <optgroup label="General & HR">
                                        <option value="offboarding">Employee Offboarding</option>
                                        <option value="share_bank">Share Bank Details</option>
                                        <option value="followup">Customer Payment Follow-up</option>
                                        <option value="general">Other Request</option>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Booking / Reference Number <span class="text-danger">*</span></label>
                                <input type="text" name="booking_reference" class="form-control" placeholder="e.g. FT-12345" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="LOW">Low - Normal processing</option>
                                    <option value="MEDIUM" selected>Medium - Standard priority</option>
                                    <option value="HIGH">High - Needs quick attention</option>
                                    <option value="URGENT">Urgent - Immediate action required</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4 border-secondary opacity-25">

                        <!-- Dynamic Sections Container -->
                        <div id="dynamicSections">
                            
                            <!-- Estimate Section -->
                            <div class="dynamic-section row g-4" id="section_estimate">
                                <div class="col-md-6">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Service Date</label>
                                    <input type="date" name="service_date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Total Amount</label>
                                    <input type="number" step="0.01" name="total_amount" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Package Details</label>
                                    <textarea name="package_details" class="form-control" rows="3"></textarea>
                                </div>
                            </div>

                            <!-- Invoice Section -->
                            <div class="dynamic-section row g-4" id="section_invoice">
                                <div class="col-md-6">
                                    <label class="form-label">Client Type</label>
                                    <select name="client_type" class="form-select" id="invoiceClientType">
                                        <option value="Individual">Individual Client</option>
                                        <option value="Corporate">Corporate Client</option>
                                        <option value="New Corporate">New Corporate Client</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Proof</label>
                                    <input type="file" name="payment_proof" class="form-control">
                                </div>
                            </div>
                            
                            <!-- Supplier Section -->
                            <div class="dynamic-section row g-4" id="section_supplier">
                                <div class="col-md-6">
                                    <label class="form-label">Supplier Name</label>
                                    <select name="supplier_id" class="form-select">
                                        <option value="">Select Supplier...</option>
                                        <?php foreach ($suppliers as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Type</label>
                                    <select name="payment_type" class="form-select">
                                        <option value="Deposit">Deposit</option>
                                        <option value="Full Payment">Full Payment</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Bank Details</label>
                                    <textarea name="bank_details" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Supplier Invoice</label>
                                    <input type="file" name="supplier_invoice" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Customer Invoice</label>
                                    <input type="file" name="customer_invoice" class="form-control">
                                </div>
                            </div>

                            <!-- Default / General / Description Section -->
                            <div class="dynamic-section active row g-4" id="section_description">
                                <div class="col-12">
                                    <label class="form-label">Detailed Description / Notes</label>
                                    <textarea name="description" id="mainDescription" class="form-control" rows="5" placeholder="Provide complete details about this request..."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Supporting Attachments (Optional)</label>
                                    <input type="file" name="supporting_image" class="form-control" multiple>
                                </div>
                            </div>

                        </div>

                        <div class="mt-5 text-end">
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-send me-2"></i> Submit Ticket
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('ticketCategory');
        const sections = document.querySelectorAll('.dynamic-section');
        
        categorySelect.addEventListener('change', function() {
            // Hide all sections first
            sections.forEach(s => s.classList.remove('active'));
            
            // Always show description
            document.getElementById('section_description').classList.add('active');
            
            // Show specific section based on value
            const val = this.value;
            if (val === 'estimate') {
                document.getElementById('section_estimate').classList.add('active');
            } else if (val === 'invoice') {
                document.getElementById('section_invoice').classList.add('active');
            } else if (val === 'supplier') {
                document.getElementById('section_supplier').classList.add('active');
            }
        });

        const form = document.getElementById('unifiedTicketForm');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';
            
            const formData = new FormData(this);
            // Append ticket_type manually if needed by the backend
            formData.append('ticket_type', document.getElementById('ticketCategory').value);
            
            try {
                // Determine API endpoint. If using the new unified logic:
                const response = await fetch('api/create-ticket.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showToast('Success', 'Ticket submitted successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'tickets.php';
                    }, 1500);
                } else {
                    showToast('Error', result.error || 'Failed to submit ticket', 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send me-2"></i> Submit Ticket';
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error', 'A network error occurred.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send me-2"></i> Submit Ticket';
            }
        });

        function showToast(title, message, type) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-title').textContent = title;
            document.getElementById('toast-message').textContent = message;
            
            toast.className = 'toast';
            if (type === 'success') toast.classList.add('bg-success', 'text-white');
            if (type === 'danger') toast.classList.add('bg-danger', 'text-white');
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }
    });
</script>
<?php include 'components/bottom_navbar.php'; ?>