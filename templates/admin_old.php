<?php
/**
 * @copyright 2025 Steffen Preuss <zqare@live.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

script('webdavimpersonate', 'admin');
style('webdavimpersonate', 'admin');

?>

<div class="section">
    <h2><?php p($l->t('WebDAV Impersonate Settings')); ?></h2>
</div>

<div class="section">
    <div class="section-header">
        <h3><?php p($l->t('Group Mappings')); ?></h3>
        <button id="addMappingBtn" class="primary"><?php p($l->t('Add Mapping')); ?></button>
    </div>

    <table class="table" id="mappingsTable">
        <thead>
            <tr>
                <th><?php p($l->t('Impersonator Group')); ?></th>
                <th><?php p($l->t('Imitatee Group')); ?></th>
                <th><?php p($l->t('Actions')); ?></th>
            </tr>
        </thead>
        <tbody id="mappingsBody">
            <!-- Mappings werden hier eingefügt -->
        </tbody>
    </table>
</div>

<!-- Popup Dialog für neue Mappings -->
<div id="mappingDialog" class="dialog" style="display: none;">
    <div class="dialog-overlay"></div>
    <div class="dialog-content">
        <div class="dialog-header">
            <h3><?php p($l->t('Add Group Mapping')); ?></h3>
            <button class="dialog-close" id="closeDialogBtn">&times;</button>
        </div>
        
        <div class="dialog-body">
            <div class="form-group">
                <label for="impersonatorSelect"><?php p($l->t('Impersonator Group')); ?></label>
                <select id="impersonatorSelect" class="form-select">
                    <option value=""><?php p($l->t('Select group...')); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="imitateeSelect"><?php p($l->t('Imitatee Group')); ?></label>
                <select id="imitateeSelect" class="form-select">
                    <option value=""><?php p($l->t('Select group...')); ?></option>
                </select>
            </div>
        </div>
        
        <div class="dialog-footer">
            <button id="cancelDialogBtn" class="secondary"><?php p($l->t('Cancel')); ?></button>
            <button id="saveDialogBtn" class="primary"><?php p($l->t('Add Mapping')); ?></button>
        </div>
    </div>
</div>

    <div class="section">
        <h3><?php p($l->t('Logging')); ?></h3>
        <p><?php p($l->t('Configure logging level for impersonation attempts')); ?></p>
        <select id="logLevel">
            <option value="debug" <?php echo $_['logLevel'] === 'debug' ? 'selected' : ''; ?>><?php p($l->t('Debug')); ?></option>
            <option value="info" <?php echo $_['logLevel'] === 'info' ? 'selected' : ''; ?>><?php p($l->t('Info')); ?></option>
            <option value="warning" <?php echo $_['logLevel'] === 'warning' ? 'selected' : ''; ?>><?php p($l->t('Warning')); ?></option>
            <option value="error" <?php echo $_['logLevel'] === 'error' ? 'selected' : ''; ?>><?php p($l->t('Error')); ?></option>
        </select>
    </div>

    <div class="section">
        <h3><?php p($l->t('Audit Log')); ?></h3>
        <p><?php p($l->t('View recent impersonation attempts')); ?></p>
        <div class="audit-log" id="auditLog">
            <p><?php p($l->t('Loading audit log...')); ?></p>
        </div>
        <button id="refreshLog" class="secondary"><?php p($l->t('Refresh Log')); ?></button>
    </div>

    <div class="section">
        <button id="saveSettings" class="primary"><?php p($l->t('Save Settings')); ?></button>
        <span id="saveStatus" class="save-status"></span>
    </div>
</div>

<style>
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table td {
    vertical-align: middle;
    padding: 8px;
    width: 45%;
}

.table td:last-child {
    width: 10%;
    text-align: center;
}

.empty-state {
    text-align: center;
    color: var(--color-text-lighter);
    padding: 2rem;
    font-style: italic;
}

/* Dialog Styles */
.dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
}

.dialog-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.dialog-content {
    position: relative;
    background-color: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    margin: 10% auto;
    overflow: hidden;
}

.dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--color-border);
    background-color: var(--color-background-dark);
}

.dialog-header h3 {
    margin: 0;
    color: var(--color-main-text);
}

.dialog-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-maxcontrast);
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-large);
}

.dialog-close:hover {
    background-color: var(--color-background-hover);
}

.dialog-body {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
    color: var(--color-main-text);
}

.form-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--color-border-maxcontrast);
    border-radius: var(--border-radius-large);
    background-color: var(--color-main-background);
    color: var(--color-main-text);
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--color-border);
    background-color: var(--color-background-dark);
}

.audit-log {
    background-color: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 1rem;
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.save-status {
    margin-left: 1rem;
    font-weight: bold;
}

.save-status.success {
    color: var(--color-success);
}

.save-status.error {
    color: var(--color-error);
}
</style>

<script>
let mappings = [];
let availableGroups = [];
let nextId = 0;

// Dialog Funktionen
function showDialog() {
    document.getElementById('mappingDialog').style.display = 'block';
    populateDialogSelects();
}

function hideDialog() {
    document.getElementById('mappingDialog').style.display = 'none';
    clearDialogForm();
}

function populateDialogSelects() {
    const impersonatorSelect = document.getElementById('impersonatorSelect');
    const imitateeSelect = document.getElementById('imitateeSelect');
    
    // Aktuelle Belegungen für 1:1 Validierung
    const usedImpersonators = mappings.map(m => m.impersonator).filter(Boolean);
    const usedImitatees = mappings.map(m => m.imitatee).filter(Boolean);
    
    // Impersonator Options
    impersonatorSelect.innerHTML = '<option value=""><?php p($l->t('Select group...')); ?></option>' +
        availableGroups.map(group => 
            `<option value="${group.id}" ${usedImpersonators.includes(group.id) ? 'disabled' : ''}>${group.displayName}</option>`
        ).join('');
    
    // Imitatee Options
    imitateeSelect.innerHTML = '<option value=""><?php p($l->t('Select group...')); ?></option>' +
        availableGroups.map(group => 
            `<option value="${group.id}" ${usedImitatees.includes(group.id) ? 'disabled' : ''}>${group.displayName}</option>`
        ).join('');
}

function clearDialogForm() {
    document.getElementById('impersonatorSelect').value = '';
    document.getElementById('imitateeSelect').value = '';
}

function saveFromDialog() {
    const impersonator = document.getElementById('impersonatorSelect').value;
    const imitatee = document.getElementById('imitateeSelect').value;
    
    if (!impersonator || !imitatee) {
        showError('Please select both groups');
        return;
    }
    
    // Mapping hinzufügen
    mappings.push({
        id: nextId++,
        impersonator: impersonator,
        imitatee: imitatee
    });
    
    renderMappings();
    hideDialog();
    showSuccess('Mapping added successfully');
}

// Group Mappings Funktionen
async function loadGroups() {
    try {
        const response = await fetch('<?php p(\OC::$server->getURLGenerator()->linkToRouteAbsolute('webdavimpersonate.mappings.get_groups')); ?>');
        const data = await response.json();
        availableGroups = data.groups;
    } catch (error) {
        console.error('Failed to load groups:', error);
    }
}

async function loadMappings() {
    try {
        const response = await fetch('<?php p(\OC::$server->getURLGenerator()->linkToRouteAbsolute('webdavimpersonate.mappings.get_mappings')); ?>');
        const data = await response.json();
        mappings = data.mappings.map(m => ({
            id: nextId++,
            impersonator: m.impersonator,
            imitatee: m.imitatee
        }));
        renderMappings();
    } catch (error) {
        console.error('Failed to load mappings:', error);
    }
}

function renderMappings() {
    const tbody = document.getElementById('mappingsBody');
    
    if (mappings.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="empty-state"><?php p($l->t('No mappings configured yet.')); ?></td></tr>';
        return;
    }
    
    tbody.innerHTML = mappings.map((mapping, index) => {
        const impersonatorGroup = availableGroups.find(g => g.id === mapping.impersonator);
        const imitateeGroup = availableGroups.find(g => g.id === mapping.imitatee);
        
        return `
        <tr data-id="${mapping.id}">
            <td>${impersonatorGroup ? impersonatorGroup.displayName : mapping.impersonator}</td>
            <td>${imitateeGroup ? imitateeGroup.displayName : mapping.imitatee}</td>
            <td>
                <button class="icon-delete" onclick="deleteMapping(${index})" aria-label="<?php p($l->t('Delete')); ?>">
                    <span class="hidden-visually"><?php p($l->t('Delete')); ?></span>
                </button>
            </td>
        </tr>
    `;
    }).join('');
}

function deleteMapping(index) {
    if (confirm('<?php p($l->t('Are you sure you want to delete this mapping?')); ?>')) {
        mappings.splice(index, 1);
        renderMappings();
        saveMappings();
    }
}

async function saveMappings() {
    try {
        const response = await fetch('<?php p(\OC::$server->getURLGenerator()->linkToRouteAbsolute('webdavimpersonate.mappings.save_mappings')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Requesttoken': '<?php p($_['requesttoken']); ?>'
            },
            body: JSON.stringify({
                mappings: mappings.map(m => ({
                    impersonator: m.impersonator,
                    imitatee: m.imitatee
                }))
            })
        });
        
        if (response.ok) {
            showSuccess('Mappings saved successfully');
        } else {
            throw new Error('Save failed');
        }
    } catch (error) {
        showError('Failed to save mappings');
    }
}

// Audit Log Funktionen
async function loadAuditLog() {
    const auditLog = document.getElementById('auditLog');
    
    try {
        const response = await fetch('<?php p(\OC::$server->getURLGenerator()->linkToRouteAbsolute('webdavimpersonate.audit.get_log')); ?>');
        const data = await response.json();
        
        if (data.logs && data.logs.length > 0) {
            auditLog.innerHTML = data.logs.map(log => 
                `<div>[${log.timestamp}] ${log.action}: ${log.caller} → ${log.target} (${log.method})</div>`
            ).join('');
        } else {
            auditLog.innerHTML = '<p><?php p($l->t('No audit log entries found')); ?></p>';
        }
    } catch (error) {
        auditLog.innerHTML = '<p><?php p($l->t('Failed to load audit log')); ?></p>';
    }
}

async function saveLogLevel() {
    const logLevel = document.getElementById('logLevel').value;
    const saveButton = document.getElementById('saveSettings');
    const statusSpan = document.getElementById('saveStatus');
    
    saveButton.disabled = true;
    statusSpan.textContent = '<?php p($l->t('Saving...')); ?>';
    statusSpan.className = 'save-status';
    
    try {
        const response = await fetch('<?php p(\OC::$server->getURLGenerator()->linkToRouteAbsolute('webdavimpersonate.config.save')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Requesttoken': '<?php p($_['requesttoken']); ?>'
            },
            body: JSON.stringify({
                impersonator_groups: '',
                imitatee_groups: '',
                log_level: logLevel
            })
        });
        
        if (response.ok) {
            statusSpan.textContent = '<?php p($l->t('Settings saved successfully')); ?>';
            statusSpan.className = 'save-status success';
            setTimeout(() => {
                statusSpan.textContent = '';
            }, 3000);
        } else {
            throw new Error('Save failed');
        }
    } catch (error) {
        statusSpan.textContent = '<?php p($l->t('Failed to save settings')); ?>';
        statusSpan.className = 'save-status error';
    } finally {
        saveButton.disabled = false;
    }
}

// Event Listener
function setupEventListeners() {
    // Dialog Events
    document.getElementById('addMappingBtn').addEventListener('click', showDialog);
    document.getElementById('closeDialogBtn').addEventListener('click', hideDialog);
    document.getElementById('cancelDialogBtn').addEventListener('click', hideDialog);
    document.getElementById('saveDialogBtn').addEventListener('click', saveFromDialog);
    
    // Overlay click zum Schließen
    document.querySelector('.dialog-overlay').addEventListener('click', hideDialog);
    
    // Andere Events
    document.getElementById('saveSettings').addEventListener('click', saveLogLevel);
    document.getElementById('refreshLog').addEventListener('click', loadAuditLog);
}

// Initialisierung
document.addEventListener('DOMContentLoaded', async () => {
    await loadGroups();
    await loadMappings();
    await loadAuditLog();
    setupEventListeners();
});

function showError(message) {
    if (typeof OC !== 'undefined' && OC.Notification) {
        OC.Notification.showTemporary(message, {type: 'error'});
    } else {
        alert(message);
    }
}

function showSuccess(message) {
    if (typeof OC !== 'undefined' && OC.Notification) {
        OC.Notification.showTemporary(message);
    } else {
        alert(message);
    }
}
</script>
