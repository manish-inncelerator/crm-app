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
    redirectUri: 'http://localhost/crm/callback.php',
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
<style>
    /* Only override the close icon in dark mode */
    .dark-mode .modal .btn-close {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M2.146 2.146a.5.5 0 0 1 .708 0L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important;
    }

    .modal .btn-close:hover {
        opacity: 0.75;
    }
</style>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <h2 class="main-title">Create a Support Ticket</h2>
        <div class="ticket-widgets">
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#estimateModal">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="ticket-title">Create Estimates in QB</div>
                        <div class="ticket-desc">Request a new estimate creation in QuickBooks.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Convert Estimate to Invoice" data-type="Convert Estimate to Invoice">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="ticket-title">Convert Estimate to Invoice</div>
                        <div class="ticket-desc">Convert an estimate into an invoice after payment.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Create Payment Link" data-type="Create Payment Link">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-link"></i></div>
                        <div class="ticket-title">Create Payment Link</div>
                        <div class="ticket-desc">Generate a payment link in Flywire or Tazapay for customers.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Update Payments in QB" data-type="Update Payments in QB">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-receipt"></i></div>
                        <div class="ticket-title">Update Payments in QB</div>
                        <div class="ticket-desc">Update payments in QuickBooks and provide paid invoice to sales team.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Modify Estimates/Invoices" data-type="Modify Estimates/Invoices">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-edit"></i></div>
                        <div class="ticket-title">Modify Estimates/Invoices</div>
                        <div class="ticket-desc">Request modifications to existing estimates or invoices.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Customer Payment Follow-up" data-type="Customer Payment Follow-up">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-user-clock"></i></div>
                        <div class="ticket-title">Customer Payment Follow-up</div>
                        <div class="ticket-desc">Follow up with normal or corporate customers for payments.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#supplierModal">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-people-carry"></i></div>
                        <div class="ticket-title">Supplier Payment Follow-up</div>
                        <div class="ticket-desc">Follow up with suppliers for ticketing and package payments.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="QB Training (Estimates)" data-type="QB Training (Estimates)">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="ticket-title">Initial QB Training (Estimates)</div>
                        <div class="ticket-desc">Request initial training on QuickBooks for estimate creation.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Separate Payment Receipts" data-type="Separate Payment Receipts">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="ticket-title">Separate Payment Receipts</div>
                        <div class="ticket-desc">Request separate payment receipts for customers.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Share Bank Details" data-type="Share Bank Details">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-university"></i></div>
                        <div class="ticket-title">Share Bank Details</div>
                        <div class="ticket-desc">Share bank details file for accounts/customer payments.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Customers Refund" data-type="Customers Refund">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-undo-alt"></i></div>
                        <div class="ticket-title">Customers Refund</div>
                        <div class="ticket-desc">Request a refund for a customer payment.</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ticket-link" data-bs-toggle="modal" data-bs-target="#generalModal" data-title="Payment from Amex Card (CC)" data-type="Payment from Amex Card (CC)">
                <div class="ticket-widget">
                    <div class="ticket-main-content">
                        <div class="ticket-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="ticket-title">Payment from Amex Card (CC)</div>
                        <div class="ticket-desc">Log a payment received via Amex Card (Credit Card).</div>
                    </div>
                    <div class="ticket-chevron"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
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
                        <label class="form-label">Package/Flights/Visa/Insurance</label>
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
                    <div class="col-md-6">
                        <label class="form-label">Travel Date</label>
                        <input type="date" name="travel_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier Invoice Currency</label>
                        <input type="text" name="supplier_invoice_currency" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier Local Currency</label>
                        <input type="text" name="supplier_local_currency" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Type</label>
                        <select name="payment_type" class="form-select" required>
                            <option value="Deposit">Deposit</option>
                            <option value="Full Payment">Full Payment</option>
                            <option value="Balance Payment">Balance Payment</option>
                        </select>
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
                    <div class="col-md-6">
                        <label class="form-label">Bank Details</label>
                        <input type="text" name="bank_details" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier Invoice (PDF/Image)</label>
                        <input type="file" name="supplier_invoice" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer Paid Invoice</label>
                        <input type="file" name="customer_invoice" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer Payment Proof</label>
                        <input type="file" name="payment_proof" class="form-control" required>
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
    // Set modal title dynamically for general modal
    const generalModal = document.getElementById('generalModal');
    generalModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const title = button.getAttribute('data-title') || 'Create Ticket';
        const type = button.getAttribute('data-type') || '';
        generalModal.querySelector('.modal-title').textContent = title;
        generalModal.querySelector('form').setAttribute('data-ticket-subtype', type);
    });

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

            const formData = new FormData(this);
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
                case 'generalModal':
                    ticketType = 'general';
                    formData.append('ticket_subtype', this.getAttribute('data-ticket-subtype'));
                    break;
            }

            // Convert FormData to JSON object
            const data = {};
            formData.forEach((value, key) => {
                // Get TinyMCE content if it's a rich editor
                if (key === 'description' || key === 'estimate_message' || key === 'supplier_message') {
                    const editor = tinymce.get(key);
                    data[key] = editor ? editor.getContent() : value;
                } else {
                    data[key] = value;
                }
            });
            data.ticket_type = ticketType;

            try {
                const response = await fetch('api/create-ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
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
                    tinymce.get().forEach(editor => editor.setContent(''));
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