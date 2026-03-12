<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
?>

<div class="section">
    <h2><?php p($l->t('WebDAV Impersonate – Group Mappings')); ?></h2>
    <p class="settings-hint">
        <?php p($l->t('Define which groups can impersonate which target groups and which WebDAV actions are allowed.')); ?>
    </p>

    <!-- Tabelle -->
    <table id="wdi-table" style="width:100%; border-collapse:collapse; margin-bottom:1rem;">
        <thead>
            <tr style="border-bottom:2px solid var(--color-border);">
                <th style="text-align:left; padding:10px;"><?php p($l->t('Impersonator Group')); ?></th>
                <th style="text-align:left; padding:10px;"><?php p($l->t('Imitatee Group')); ?></th>
                <th style="text-align:left; padding:10px;"><?php p($l->t('Allowed Actions')); ?></th>
                <th style="width:60px; padding:10px;"></th>
            </tr>
        </thead>
        <tbody id="wdi-tbody">
            <tr id="wdi-empty-row">
                <td colspan="4" style="padding:16px; text-align:center; color:var(--color-text-lighter);">
                    <?php p($l->t('No mappings configured yet.')); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <button id="wdi-btn-add" class="button">
        + <?php p($l->t('Add Mapping')); ?>
    </button>
    <button id="wdi-btn-save" class="button primary" style="margin-left:8px;">
        <?php p($l->t('Save')); ?>
    </button>
    <span id="wdi-save-msg" style="display:none; margin-left:12px; color:var(--color-success);">
        ✓ <?php p($l->t('Saved successfully')); ?>
    </span>
</div>

<!-- ── Modal ───────────────────────────────────────────────────────────── -->
<div id="wdi-modal" style="
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,0.5); z-index:9999;
    align-items:center; justify-content:center;">

    <div style="
        background:var(--color-main-background);
        border-radius:var(--border-radius-large, 8px);
        padding:28px; min-width:420px; max-width:520px; width:90%;
        box-shadow:0 8px 32px rgba(0,0,0,0.3);">

        <h3 style="margin-top:0; margin-bottom:20px;">
            <?php p($l->t('Add Group Mapping')); ?>
        </h3>

        <!-- Impersonator Group -->
        <label style="display:block; font-weight:600; margin-bottom:4px;">
            <?php p($l->t('Impersonator Group')); ?>
        </label>
        <select id="wdi-sel-imp" style="width:100%; padding:8px; margin-bottom:16px;
            border:1px solid var(--color-border); border-radius:var(--border-radius);
            background:var(--color-main-background); color:var(--color-main-text);">
            <option value=""><?php p($l->t('-- Select group --')); ?></option>
        </select>

        <!-- Imitatee Group -->
        <label style="display:block; font-weight:600; margin-bottom:4px;">
            <?php p($l->t('Imitatee Group')); ?>
        </label>
        <select id="wdi-sel-imi" style="width:100%; padding:8px; margin-bottom:16px;
            border:1px solid var(--color-border); border-radius:var(--border-radius);
            background:var(--color-main-background); color:var(--color-main-text);">
            <option value=""><?php p($l->t('-- Select group --')); ?></option>
        </select>

        <!-- WebDAV Actions -->
        <label style="display:block; font-weight:600; margin-bottom:8px;">
            <?php p($l->t('Allowed WebDAV Actions')); ?>
        </label>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:24px;">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" id="wdi-act-read" value="read" checked>
                <span>
                    <strong>read</strong><br>
                    <small style="color:var(--color-text-lighter);">GET, PROPFIND, HEAD</small>
                </span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" id="wdi-act-write" value="write">
                <span>
                    <strong>write</strong><br>
                    <small style="color:var(--color-text-lighter);">PUT, MKCOL, COPY, MOVE</small>
                </span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" id="wdi-act-delete" value="delete">
                <span>
                    <strong>delete</strong><br>
                    <small style="color:var(--color-text-lighter);">DELETE</small>
                </span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" id="wdi-act-lock" value="lock">
                <span>
                    <strong>lock</strong><br>
                    <small style="color:var(--color-text-lighter);">LOCK, UNLOCK</small>
                </span>
            </label>
        </div>

        <!-- Buttons -->
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button id="wdi-modal-cancel" class="button">
                <?php p($l->t('Cancel')); ?>
            </button>
            <button id="wdi-modal-confirm" class="button primary">
                <?php p($l->t('Add Mapping')); ?>
            </button>
        </div>
    </div>
</div>

<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
(function () {
    var groups   = [];
    var mappings = [];
    var ACTIONS  = ['read', 'write', 'delete', 'lock'];

    var tbody    = document.getElementById('wdi-tbody');
    var emptyRow = document.getElementById('wdi-empty-row');
    var modal    = document.getElementById('wdi-modal');
    var selImp   = document.getElementById('wdi-sel-imp');
    var selImi   = document.getElementById('wdi-sel-imi');

    function getCheckedActions() {
        return ACTIONS.filter(function (a) {
            return document.getElementById('wdi-act-' + a).checked;
        });
    }

    function resetModal() {
        selImp.value = '';
        selImi.value = '';
        ACTIONS.forEach(function (a) {
            document.getElementById('wdi-act-' + a).checked = (a === 'read');
        });
    }

    function renderTable() {
        Array.from(tbody.querySelectorAll('tr[data-idx]')).forEach(function (tr) { tr.remove(); });
        if (mappings.length === 0) { emptyRow.style.display = ''; return; }
        emptyRow.style.display = 'none';
        mappings.forEach(function (m, i) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-idx', i);
            tr.style.borderBottom = '1px solid var(--color-border)';
            var badges = m.actions.map(function (a) {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;margin-right:4px;'
                    + 'background:var(--color-primary-element-light);color:var(--color-primary-element);">' + escHtml(a) + '</span>';
            }).join('');
            tr.innerHTML =
                '<td style="padding:10px;">' + escHtml(m.impersonator) + '</td>' +
                '<td style="padding:10px;">' + escHtml(m.imitatee) + '</td>' +
                '<td style="padding:10px;">' + badges + '</td>' +
                '<td style="padding:10px;"><button class="button" onclick="WDI.del(' + i + ')">✕</button></td>';
            tbody.appendChild(tr);
        });
    }

    function fillSelect(sel, disabledIds) {
        sel.innerHTML = '<option value=""><?php p($l->t('-- Select group --')); ?></option>';
        groups.forEach(function (g) {
            var opt = document.createElement('option');
            opt.value = g.id;
            opt.textContent = g.displayName;
            if (disabledIds.indexOf(g.id) !== -1) opt.disabled = true;
            sel.appendChild(opt);
        });
    }

    function openModal() {
        var usedImp = mappings.map(function (m) { return m.impersonator; });
        var usedImi = mappings.map(function (m) { return m.imitatee; });
        fillSelect(selImp, usedImp);
        fillSelect(selImi, usedImi);
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
        resetModal();
    }

    function confirmAdd() {
        var imp     = selImp.value.trim();
        var imi     = selImi.value.trim();
        var actions = getCheckedActions();
        if (!imp || !imi) { alert('<?php p($l->t('Please select both groups.')); ?>'); return; }
        if (imp === imi)  { alert('<?php p($l->t('Impersonator and Imitatee must be different groups.')); ?>'); return; }
        if (!actions.length) { alert('<?php p($l->t('Please select at least one action.')); ?>'); return; }
        mappings.push({ impersonator: imp, imitatee: imi, actions: actions });
        closeModal();
        renderTable();
    }

    function saveMappings() {
        fetch(OC.generateUrl('/apps/webdavimpersonate/api/mappings'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'requesttoken': OC.requestToken },
            body: JSON.stringify({ mappings: mappings })
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            var msg = document.getElementById('wdi-save-msg');
            msg.style.display = 'inline';
            setTimeout(function () { msg.style.display = 'none'; }, 3000);
        })
        .catch(function () { alert('<?php p($l->t('Error saving mappings.')); ?>'); });
    }

    function init() {
        fetch('/ocs/v1.php/cloud/groups?format=json', {
            credentials: 'include',
            headers: { 'OCS-APIRequest': 'true' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var raw = (data.ocs && data.ocs.data && data.ocs.data.groups) ? data.ocs.data.groups : [];
            groups = raw.map(function (g) { return { id: g, displayName: g }; });
            return fetch(OC.generateUrl('/apps/webdavimpersonate/api/mappings'), {
                credentials: 'include'
            });
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            mappings = data.mappings || [];
            renderTable();
        })
        .catch(function (e) { console.error('WDI:', e); });
    }

    window.WDI = {
        del: function (i) {
            if (confirm('<?php p($l->t('Delete this mapping?')); ?>')) {
                mappings.splice(i, 1);
                renderTable();
            }
        }
    };

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.getElementById('wdi-btn-add').onclick     = openModal;
    document.getElementById('wdi-btn-save').onclick    = saveMappings;
    document.getElementById('wdi-modal-cancel').onclick  = closeModal;
    document.getElementById('wdi-modal-confirm').onclick = confirmAdd;
    modal.onclick = function (e) { if (e.target === modal) closeModal(); };

    init();
}());
</script>
