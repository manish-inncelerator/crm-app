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
    'verify' => false 
]);

// Auth0 configuration
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

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
    
    $is_admin = (bool)$dbUser['is_admin'];

} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

// Print HTML start
html_start('Knowledge Base - Fayyaz Travels CRM', ['assets/css/knowledgebase.css']);
?>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="kb-container">
            <div class="content-header">
                <h1>Knowledge Base</h1>
                <p class="text-secondary">Official bank accounts and payment details for Fayyaz Travels Pte Ltd.</p>
            </div>

            <!-- Singapore Payments Section -->
            <section class="kb-section">
                <div class="section-header">
                    <i class="bi bi-geo-alt-fill"></i>
                    <h2>Singapore Payments</h2>
                </div>

                <div class="kb-card">
                    <div class="kb-card-header">
                        <h3 class="kb-card-title">Local Bank Transfers & PAYNOW</h3>
                        <span class="kb-card-badge">SGD</span>
                    </div>
                    <div class="kb-table-container">
                        <table class="kb-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Details</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Payments by Cheque</strong></td>
                                    <td>Payable to: <span class="detail-value">Fayyaz Travels Pte Ltd</span></td>
                                    <td><button class="copy-btn" onclick="copyToClipboard('Fayyaz Travels Pte Ltd')"><i class="bi bi-copy"></i></button></td>
                                </tr>
                                <tr>
                                    <td><strong>PAYNOW</strong></td>
                                    <td>UEN: <span class="detail-value">201010203DFTD</span></td>
                                    <td>
                                        <button class="copy-btn" onclick="copyToClipboard('201010203DFTD')" title="Copy UEN"><i class="bi bi-copy"></i></button>
                                        <a href="assets/images/paynow-qr.png" download="paynow-qr.png" class="copy-btn ms-2" title="Download QR Code">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>UOB Bank</strong></td>
                                    <td>
                                        Account: <span class="detail-value">357-303-2266</span><br>
                                        Code: <span class="detail-value">7375</span> | Branch: <span class="detail-value">018</span><br>
                                        SWIFT: <span class="detail-value">UOVBSGSG</span>
                                    </td>
                                    <td><button class="copy-btn" onclick="copyToClipboard('357-303-2266')"><i class="bi bi-copy"></i></button></td>
                                </tr>
                                <tr>
                                    <td><strong>DBS Bank</strong></td>
                                    <td>
                                        Account: <span class="detail-value">107-902401-7</span><br>
                                        Code: <span class="detail-value">7171</span> | Branch: <span class="detail-value">107</span><br>
                                        SWIFT: <span class="detail-value">DBSSSGSG</span>
                                    </td>
                                    <td><button class="copy-btn" onclick="copyToClipboard('107-902401-7')"><i class="bi bi-copy"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- International Bank Details Section -->
            <section class="kb-section">
                <div class="section-header">
                    <i class="bi bi-globe"></i>
                    <h2>International Bank Details</h2>
                </div>

                <div class="row">
                    <!-- UK - GBP -->
                    <div class="col-lg-6">
                        <div class="kb-card">
                            <div class="kb-card-header">
                                <h3 class="kb-card-title">United Kingdom (Modulr FS)</h3>
                                <span class="kb-card-badge">GBP</span>
                            </div>
                            <table class="kb-table">
                                <tr>
                                    <td class="detail-label">Account Name</td>
                                    <td>FAYYAZ TRAVELS PTE. LTD.</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Account Number</td>
                                    <td class="detail-value">00959162</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Sort Code</td>
                                    <td class="detail-value">040085</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">IBAN</td>
                                    <td class="detail-value">GB56MODR04008500959162</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">SWIFT</td>
                                    <td class="detail-value">MODRGB21</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- USA - USD -->
                    <div class="col-lg-6">
                        <div class="kb-card">
                            <div class="kb-card-header">
                                <h3 class="kb-card-title">USA (Community Federal Savings)</h3>
                                <span class="kb-card-badge">USD</span>
                            </div>
                            <table class="kb-table">
                                <tr>
                                    <td class="detail-label">Account Number</td>
                                    <td class="detail-value">8488858126</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">ACH Routing</td>
                                    <td class="detail-value">026073150</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Fedwire Routing</td>
                                    <td class="detail-value">026073008</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">SWIFT</td>
                                    <td class="detail-value">CMFGUS33</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Type</td>
                                    <td>Checking</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Hong Kong - HKD -->
                    <div class="col-lg-6">
                        <div class="kb-card">
                            <div class="kb-card-header">
                                <h3 class="kb-card-title">Hong Kong (Standard Chartered)</h3>
                                <span class="kb-card-badge">HKD</span>
                            </div>
                            <table class="kb-table">
                                <tr>
                                    <td class="detail-label">Account Number</td>
                                    <td class="detail-value">47412422538</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Bank Code</td>
                                    <td class="detail-value">003</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Branch Code</td>
                                    <td class="detail-value">474</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">SWIFT</td>
                                    <td class="detail-value">SCBLHKHH</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- UAE - AED -->
                    <div class="col-lg-6">
                        <div class="kb-card">
                            <div class="kb-card-header">
                                <h3 class="kb-card-title">UAE (Standard Chartered Dubai)</h3>
                                <span class="kb-card-badge">AED</span>
                            </div>
                            <table class="kb-table">
                                <tr>
                                    <td class="detail-label">IBAN</td>
                                    <td class="detail-value">AE840446498900000000973</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">SWIFT</td>
                                    <td class="detail-value">SCBLAEAD</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Location</td>
                                    <td>United Arab Emirates</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Canada - CAD -->
                    <div class="col-lg-6">
                        <div class="kb-card">
                            <div class="kb-card-header">
                                <h3 class="kb-card-title">Canada (Digital Commerce Bank)</h3>
                                <span class="kb-card-badge">CAD</span>
                            </div>
                            <table class="kb-table">
                                <tr>
                                    <td class="detail-label">Account Number</td>
                                    <td class="detail-value">972721732</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Transit Number</td>
                                    <td class="detail-value">10009</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Inst. Number</td>
                                    <td class="detail-value">352</td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Interac Email</td>
                                    <td class="detail-value">fayyaztravelssg@gmail.com</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Payment Links Section -->
            <section class="kb-section">
                <div class="section-header">
                    <i class="bi bi-link-45deg"></i>
                    <h2>Payment Links</h2>
                </div>

                <div class="payment-links-grid">
                    <div class="payment-card">
                        <div class="payment-card-icon">
                            <i class="bi bi-lightning-fill"></i>
                        </div>
                        <div class="payment-card-info">
                            <h4>Flywire</h4>
                            <p>Global payment solution for international transfers.</p>
                        </div>
                        <div class="payment-card-footer">
                            <span class="badge bg-primary">2.5% flat fee</span>
                            <span class="text-secondary ms-2 small">Total amount</span>
                        </div>
                    </div>

                    <div class="payment-card">
                        <div class="payment-card-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="payment-card-info">
                            <h4>Taza Pay</h4>
                            <p>Cross-border payments for businesses.</p>
                        </div>
                        <div class="payment-card-footer">
                            <div><span class="badge bg-info text-dark">2.5% fee</span> <small class="text-secondary">Inside Singapore</small></div>
                            <div class="mt-1"><span class="badge bg-warning text-dark">3.5% fee</span> <small class="text-secondary">Outside Singapore</small></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show a brief toast or notification if available
        alert('Copied: ' + text);
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
