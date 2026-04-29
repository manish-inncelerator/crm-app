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

try {
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }
    $is_admin = (bool)($dbUser['role'] === 'admin');
} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

// Fetch all KB data
$sections = $database->select('kb_sections', '*', ['ORDER' => ['display_order' => 'ASC']]);
foreach ($sections as &$section) {
    $section['cards'] = $database->select('kb_cards', '*', [
        'section_id' => $section['id'],
        'ORDER' => ['display_order' => 'ASC']
    ]);
    foreach ($section['cards'] as &$card) {
        $card['items'] = $database->select('kb_items', '*', [
            'card_id' => $card['id'],
            'ORDER' => ['display_order' => 'ASC']
        ]);
    }
}

html_start('Knowledge Base - Fayyaz Travels CRM', ['assets/css/knowledgebase.css']);
?>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="kb-container">
            <div class="content-header d-flex justify-content-between align-items-center">
                <div>
                    <h1>Knowledge Base</h1>
                    <p class="text-secondary">Official bank accounts and payment details for Fayyaz Travels Pte Ltd.</p>
                </div>
                <?php if ($is_admin): ?>
                    <a href="manage-kb.php" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-1"></i> Manage KB
                    </a>
                <?php endif; ?>
            </div>

            <?php foreach ($sections as $sec): ?>
                <section class="kb-section">
                    <div class="section-header">
                        <i class="<?php echo htmlspecialchars($sec['icon'] ?: 'bi-info-circle'); ?>"></i>
                        <h2><?php echo htmlspecialchars($sec['title']); ?></h2>
                    </div>

                    <div class="row g-4">
                        <?php foreach ($sec['cards'] as $card): ?>
                            <?php 
                            $colClass = count($sec['cards']) === 1 ? 'col-12' : 'col-lg-6';
                            if ($card['type'] === 'grid') $colClass = 'col-12'; 
                            ?>
                            <div class="<?php echo $colClass; ?>">
                                <div class="kb-card h-100">
                                    <div class="kb-card-header">
                                        <div>
                                            <h3 class="kb-card-title"><?php echo htmlspecialchars($card['title']); ?></h3>
                                            <?php if ($card['subtitle']): ?>
                                                <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($card['subtitle']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($card['badge']): ?>
                                            <span class="kb-card-badge"><?php echo htmlspecialchars($card['badge']); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($card['type'] === 'table'): ?>
                                        <div class="kb-table-container">
                                            <table class="kb-table">
                                                <tbody>
                                                    <?php foreach ($card['items'] as $item): ?>
                                                        <tr>
                                                            <td class="detail-label"><strong><?php echo htmlspecialchars($item['label']); ?></strong></td>
                                                            <td class="detail-value"><?php echo $item['value']; ?></td>
                                                            <td style="width: 50px;">
                                                                <?php if ($item['is_copyable']): ?>
                                                                    <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars(strip_tags(str_replace('<br>', ' ', $item['value']))); ?>')">
                                                                        <i class="bi bi-copy"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="payment-links-grid p-3">
                                            <?php foreach ($card['items'] as $item): ?>
                                                <div class="payment-card mb-0">
                                                    <div class="payment-card-icon">
                                                        <i class="bi bi-lightning-fill"></i>
                                                    </div>
                                                    <div class="payment-card-info">
                                                        <h4><?php echo htmlspecialchars($item['label']); ?></h4>
                                                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                                                    </div>
                                                    <div class="payment-card-footer mt-auto">
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($item['value']); ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
