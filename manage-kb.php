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
    header('Location: login.php');
    exit;
}

$dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
if (!$dbUser || !($dbUser['is_admin'] ?? false)) {
    header('Location: dashboard.php');
    exit;
}
$is_admin = true;

// Fetch all data for management
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

html_start('Manage Knowledge Base');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .kb-admin-section {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    .kb-admin-card {
        background: rgba(255, 255, 255, 0.03);
        border-left: 4px solid var(--primary-color);
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 8px;
    }
    .kb-admin-item {
        background: rgba(0, 0, 0, 0.1);
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .action-btns {
        display: flex;
        gap: 0.5rem;
    }
    .drag-handle {
        cursor: move;
        color: #666;
    }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Manage Knowledge Base</h2>
                    <p class="text-muted">Structure your bank details, payment links, and instructions.</p>
                </div>
                <button class="btn btn-primary" onclick="openSectionModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Section
                </button>
            </div>

            <?php foreach ($sections as $sec): ?>
                <div class="kb-admin-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">
                            <i class="<?php echo htmlspecialchars($sec['icon']); ?> me-2"></i>
                            <?php echo htmlspecialchars($sec['title']); ?>
                        </h4>
                        <div class="action-btns">
                            <button class="btn btn-sm btn-outline-primary" onclick="openCardModal(<?php echo $sec['id']; ?>)">
                                <i class="bi bi-plus-lg"></i> Add Card
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="openSectionModal(<?php echo htmlspecialchars(json_encode($sec)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('section', <?php echo $sec['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="ps-4 border-start">
                        <?php foreach ($sec['cards'] as $card): ?>
                            <div class="kb-admin-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($card['title']); ?></h5>
                                        <?php if ($card['subtitle']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($card['subtitle']); ?></small>
                                        <?php endif; ?>
                                        <span class="badge bg-info text-dark ms-2"><?php echo $card['type']; ?></span>
                                        <?php if ($card['badge']): ?>
                                            <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($card['badge']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="action-btns">
                                        <button class="btn btn-sm btn-link text-white" onclick="openItemModal(<?php echo $card['id']; ?>)">
                                            <i class="bi bi-plus-circle"></i> Add Item
                                        </button>
                                        <button class="btn btn-sm btn-link text-white" onclick="openCardModal(<?php echo $sec['id']; ?>, <?php echo htmlspecialchars(json_encode($card)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-danger" onclick="deleteItem('card', <?php echo $card['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="kb-items-list mt-3">
                                    <?php foreach ($card['items'] as $item): ?>
                                        <div class="kb-admin-item">
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['label']); ?>:</strong>
                                                <span class="ms-2"><?php echo htmlspecialchars(substr(strip_tags($item['value']), 0, 50)); ?>...</span>
                                            </div>
                                            <div class="action-btns">
                                                <button class="btn btn-sm btn-link text-white" onclick="openItemModal(<?php echo $card['id']; ?>, <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-link text-danger" onclick="deleteItem('item', <?php echo $item['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Section Modal -->
<div class="modal fade" id="sectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Section Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sectionForm">
                <input type="hidden" name="id" id="sec_id">
                <input type="hidden" name="action" id="sec_action" value="add_section">
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="sec_title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Icon (Bootstrap Icon Class)</label>
                        <input type="text" name="icon" id="sec_icon" class="form-control" placeholder="bi-info-circle">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" id="sec_order" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Card Modal -->
<div class="modal fade" id="cardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Card Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="cardForm">
                <input type="hidden" name="id" id="card_id">
                <input type="hidden" name="section_id" id="card_sec_id">
                <input type="hidden" name="action" id="card_action" value="add_card">
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="card_title" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Badge (e.g. SGD, USD)</label>
                        <input type="text" name="badge" id="card_badge" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Subtitle (Optional)</label>
                        <input type="text" name="subtitle" id="card_subtitle" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Layout Type</label>
                        <select name="type" id="card_type" class="form-select">
                            <option value="table">Table (Account Details)</option>
                            <option value="grid">Grid (Payment Links)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" id="card_order" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="itemForm">
                <input type="hidden" name="id" id="item_id">
                <input type="hidden" name="card_id" id="item_card_id">
                <input type="hidden" name="action" id="item_action" value="add_item">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Label / Method Name</label>
                        <input type="text" name="label" id="item_label" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" id="item_order" class="form-control" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Value / Account Info (HTML allowed)</label>
                        <textarea name="value" id="item_value" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="col-12 grid-only">
                        <label class="form-label">Description (For Grid Type)</label>
                        <textarea name="description" id="item_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12 table-only">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_copyable" value="1" id="item_copyable">
                            <label class="form-check-label">Show Copy Button</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let sectionModal, cardModal, itemModal;

document.addEventListener('DOMContentLoaded', () => {
    sectionModal = new bootstrap.Modal(document.getElementById('sectionModal'));
    cardModal = new bootstrap.Modal(document.getElementById('cardModal'));
    itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
});

function openSectionModal(data = null) {
    document.getElementById('sectionForm').reset();
    if (data) {
        document.getElementById('sec_id').value = data.id;
        document.getElementById('sec_title').value = data.title;
        document.getElementById('sec_icon').value = data.icon;
        document.getElementById('sec_order').value = data.display_order;
        document.getElementById('sec_action').value = 'edit_section';
    } else {
        document.getElementById('sec_action').value = 'add_section';
    }
    sectionModal.show();
}

function openCardModal(secId, data = null) {
    document.getElementById('cardForm').reset();
    document.getElementById('card_sec_id').value = secId;
    if (data) {
        document.getElementById('card_id').value = data.id;
        document.getElementById('card_title').value = data.title;
        document.getElementById('card_subtitle').value = data.subtitle;
        document.getElementById('card_badge').value = data.badge;
        document.getElementById('card_type').value = data.type;
        document.getElementById('card_order').value = data.display_order;
        document.getElementById('card_action').value = 'edit_card';
    } else {
        document.getElementById('card_action').value = 'add_card';
    }
    cardModal.show();
}

function openItemModal(cardId, data = null) {
    document.getElementById('itemForm').reset();
    document.getElementById('item_card_id').value = cardId;
    if (data) {
        document.getElementById('item_id').value = data.id;
        document.getElementById('item_label').value = data.label;
        document.getElementById('item_value').value = data.value;
        document.getElementById('item_desc').value = data.description;
        document.getElementById('item_copyable').checked = data.is_copyable == 1;
        document.getElementById('item_order').value = data.display_order;
        document.getElementById('item_action').value = 'edit_item';
    } else {
        document.getElementById('item_action').value = 'add_item';
    }
    itemModal.show();
}

async function handleFormSubmit(e, modal) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Handle checkbox
    if (e.target.id === 'itemForm') {
        data.is_copyable = document.getElementById('item_copyable').checked ? 1 : 0;
    }

    try {
        const response = await fetch('api/kb-actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Operation failed');
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred');
    }
}

document.getElementById('sectionForm').addEventListener('submit', (e) => handleFormSubmit(e, sectionModal));
document.getElementById('cardForm').addEventListener('submit', (e) => handleFormSubmit(e, cardModal));
document.getElementById('itemForm').addEventListener('submit', (e) => handleFormSubmit(e, itemModal));

async function deleteItem(type, id) {
    if (!confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) return;
    
    try {
        const response = await fetch('api/kb-actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: `delete_${type}`, id: id })
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Delete failed');
        }
    } catch (err) {
        console.error(err);
    }
}
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
