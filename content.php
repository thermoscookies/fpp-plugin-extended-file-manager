<style>
    #efm-app { width: 100%; }
    #efm-app .efm-toolbar,
    #efm-app .efm-browser { border: 1px solid var(--bs-border-color, rgba(128, 128, 128, .35)); border-radius: .375rem; }
    #efm-app .efm-toolbar { background: var(--bs-tertiary-bg, rgba(128, 128, 128, .12)); }
    #efm-app .efm-playlist { min-width: 14rem; }
    #efm-app .efm-selection-count { min-width: 8rem; }
    #efm-app .efm-breadcrumbs { white-space: normal; }
    #efm-app .efm-breadcrumbs .btn { font-weight: 500; }
    #efm-app .efm-breadcrumb-separator { color: var(--bs-secondary-color, #6c757d); padding: 0 .35rem; }
    #efm-app .efm-table { width: 100%; table-layout: fixed; margin-bottom: 0; }
    #efm-app .efm-select-col,
    #efm-app .efm-select-cell { width: 3.5rem; text-align: center; }
    #efm-app .efm-type-col,
    #efm-app .efm-type-cell { width: 8rem; text-align: right; white-space: nowrap; }
    #efm-app .efm-name-cell { min-width: 0; }
    #efm-app .efm-file-name { display: inline-block; max-width: calc(100% - 2rem); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
    #efm-app .efm-folder-row td { background: var(--bs-tertiary-bg, rgba(128, 128, 128, .12)); }
    #efm-app .efm-folder-link { display: flex; width: 100%; align-items: center; gap: .5rem; text-align: left; }
    #efm-app .efm-folder-icon,
    #efm-app .efm-video-icon { flex: 0 0 1.25rem; text-align: center; }
    #efm-app .efm-folder-icon { color: var(--bs-warning, #f0ad4e); }
    #efm-app .efm-video-icon { color: var(--bs-info, #5bc0de); }
    #efm-app .efm-message:empty { display: none; }
    #efm-app .efm-repair-panel { border: 1px solid var(--bs-border-color, rgba(128, 128, 128, .35)); border-radius: .375rem; background: var(--bs-tertiary-bg, rgba(128, 128, 128, .12)); }
    #efm-app .efm-repair-results { max-height: 20rem; overflow: auto; }
    #efm-app .efm-repair-results table { margin-bottom: 0; }
    @media (max-width: 575.98px) {
        #efm-app .efm-toolbar .efm-playlist,
        #efm-app .efm-toolbar .btn { width: 100%; }
        #efm-app .efm-selection-count { min-width: 0; }
        #efm-app .efm-select-col,
        #efm-app .efm-select-cell { width: 3rem; }
        #efm-app .efm-type-col,
        #efm-app .efm-type-cell { width: 5.5rem; }
    }
</style>

<div class="container-fluid px-0 mt-2" id="efm-app">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="mb-1">Extended File Manager</h2>
            <p class="text-muted mb-0">Browse video media folders and add selected files to an FPP playlist.</p>
        </div>
    </div>

    <div class="efm-toolbar p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg">
                <label for="efm-playlist" class="form-label mb-1">Add to playlist</label>
                <select id="efm-playlist" class="form-select efm-playlist" aria-label="Playlist"></select>
            </div>
            <div class="col-12 col-sm-auto">
                <div class="efm-selection-count text-muted small mb-2" aria-live="polite"><span id="efm-selected-count">0</span> videos selected</div>
                <div class="d-flex flex-wrap gap-2">
                    <button id="efm-clear" class="btn btn-outline-secondary" type="button" disabled>Clear selection</button>
                    <button id="efm-add" class="btn btn-primary" type="button" disabled>Add to playlist</button>
                    <button id="efm-repair" class="btn btn-outline-info" type="button" disabled>Repair Playlist Durations</button>
                </div>
            </div>
        </div>
    </div>

    <section id="efm-repair-panel" class="efm-repair-panel p-3 mb-3" hidden aria-labelledby="efm-repair-title">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h3 id="efm-repair-title" class="h5 mb-0">Repair Playlist Durations</h3>
            <span class="text-muted small">Dry-run preview; no changes are made until confirmed.</span>
        </div>
        <div id="efm-repair-results" class="efm-repair-results"></div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button id="efm-repair-cancel" class="btn btn-outline-secondary" type="button">Cancel</button>
            <button id="efm-repair-confirm" class="btn btn-warning" type="button" hidden>Confirm Repair</button>
        </div>
    </section>

    <div class="efm-browser p-2 p-sm-3">
        <nav id="efm-breadcrumb" class="efm-breadcrumbs mb-3" aria-label="Video folders"></nav>
        <div id="efm-message" class="efm-message alert mb-3" role="status" aria-live="polite"></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle efm-table">
                <colgroup><col class="efm-select-col"><col><col class="efm-type-col"></colgroup>
                <thead><tr><th class="efm-select-col" scope="col"><input id="efm-all" type="checkbox" aria-label="Select all visible videos"></th><th scope="col">Name</th><th class="efm-type-col" scope="col">Type</th></tr></thead>
                <tbody id="efm-list"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const base = 'api/plugin/fpp-plugin-extended-file-manager/';
    let current = '';
    const selected = new Set();
    let repairBusy = false;
    let repairPreview = null;
    const $ = (id) => document.getElementById(id);

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[character]));
    }

    function showMessage(text, type) {
        const message = $('efm-message');
        message.textContent = text || '';
        message.className = text ? 'efm-message alert alert-' + type + ' mb-3' : 'efm-message alert mb-3';
    }

    function updateSelectionUi() {
        const count = selected.size;
        $('efm-selected-count').textContent = count;
        $('efm-add').disabled = count === 0 || !$('efm-playlist').value;
        $('efm-clear').disabled = count === 0;
        $('efm-repair').disabled = repairBusy || !$('efm-playlist').value;
        $('efm-playlist').disabled = repairBusy;
        const visible = [...document.querySelectorAll('.efm-file')];
        const checked = visible.filter((input) => input.checked).length;
        const all = $('efm-all');
        all.checked = visible.length > 0 && checked === visible.length;
        all.indeterminate = checked > 0 && checked < visible.length;
    }

    function clearSelection() {
        selected.clear();
        document.querySelectorAll('.efm-file').forEach((input) => { input.checked = false; });
        updateSelectionUi();
    }

    function formatDuration(value) {
        if (value === null || value === undefined || value === '') return 'Missing';
        const seconds = Number(value);
        if (!Number.isFinite(seconds)) return 'Invalid';
        const rounded = Math.max(0, Math.round(seconds));
        const hours = Math.floor(rounded / 3600);
        const minutes = Math.floor((rounded % 3600) / 60);
        const secs = rounded % 60;
        const clock = (hours ? String(hours).padStart(2, '0') + ':' : '') + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        return Number(seconds.toFixed(3)) + ' s (' + clock + ')';
    }

    function formatTotals(info) {
        if (!info) return '<p class="text-muted mb-0">Playlist totals were not returned.</p>';
        return '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Totals</th><th>Current</th><th>Proposed</th></tr></thead><tbody><tr><th scope="row">Items</th><td>' + escapeHtml(info.beforeItems) + '</td><td>' + escapeHtml(info.afterItems) + '</td></tr><tr><th scope="row">Duration</th><td>' + escapeHtml(formatDuration(info.beforeDuration)) + '</td><td>' + escapeHtml(formatDuration(info.afterDuration)) + '</td></tr></tbody></table></div>';
    }

    function renderRepairResult(data) {
        const updates = Array.isArray(data.updates) ? data.updates : [];
        const errors = Array.isArray(data.errors) ? data.errors : [];
        const before = data.before || {};
        const after = data.after || {};
        const totals = {beforeItems: before.total_items || 0, afterItems: after.total_items || 0, beforeDuration: before.total_duration || 0, afterDuration: after.total_duration || 0};
        let html = '<p class="mb-2"><strong>' + escapeHtml(data.playlist || $('efm-playlist').value) + '</strong>: ' + (data.applied ? 'repair applied successfully.' : updates.length ? 'the following entries can be repaired.' : 'no missing video durations were found.') + '</p>' + formatTotals(totals);
        if (updates.length) {
            html += '<div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Section / entry</th><th>Media</th><th>Current</th><th>Proposed</th></tr></thead><tbody>';
            html += updates.map((item) => '<tr><td>' + escapeHtml(item.section + ' [' + item.index + ']') + '</td><td class="text-break">' + escapeHtml(item.path) + '</td><td>' + escapeHtml(formatDuration(item.currentDuration)) + '</td><td>' + escapeHtml(formatDuration(item.proposedDuration)) + '</td></tr>').join('');
            html += '</tbody></table></div>';
        }
        if (errors.length) html += '<div class="alert alert-danger mt-3 mb-0"><strong>Repair could not be completed:</strong><ul class="mb-0">' + errors.map((error) => '<li>' + escapeHtml(error) + '</li>').join('') + '</ul></div>';
        $('efm-repair-results').innerHTML = html;
        $('efm-repair-panel').hidden = false;
        $('efm-repair-confirm').hidden = data.applied || !data.ok || updates.length === 0;
        repairPreview = !data.applied && data.ok && updates.length ? data : null;
    }

    function renderRepairError(message, errors) {
        const details = Array.isArray(errors) && errors.length ? '<ul class="mb-0">' + errors.map((error) => '<li>' + escapeHtml(error) + '</li>').join('') + '</ul>' : '';
        $('efm-repair-results').innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(message || 'Unable to inspect playlist durations.') + details + '</div>';
        $('efm-repair-panel').hidden = false;
        $('efm-repair-confirm').hidden = true;
    }

    function setRepairBusy(busy, applying) {
        repairBusy = busy;
        $('efm-repair').textContent = busy ? (applying ? 'Applying Repair…' : 'Checking Durations…') : 'Repair Playlist Durations';
        $('efm-repair-cancel').disabled = busy;
        $('efm-repair-confirm').disabled = busy;
        updateSelectionUi();
    }

    async function requestRepair(apply) {
        if (repairBusy || !$('efm-playlist').value || (apply && !repairPreview)) return;
        const playlist = $('efm-playlist').value;
        const token = apply ? repairPreview.repairToken : null;
        if (apply && !token) {
            renderRepairError('The repair preview has expired. Run the dry-run again.');
            repairPreview = null;
            return;
        }
        setRepairBusy(true, apply);
        try {
            const body = {playlist};
            if (apply) { body.apply = true; body.repairToken = token; }
            const response = await fetch(base + 'repair', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
            const raw = await response.text();
            let data;
            try { data = raw ? JSON.parse(raw) : null; } catch (error) { throw new Error('The repair response was malformed.'); }
            if (!data || typeof data !== 'object') throw new Error('The repair response was malformed.');
            if (!response.ok || !data || data.ok === false) {
                renderRepairError(data && data.error ? data.error : 'Repair request failed.', data && data.errors);
                return;
            }
            renderRepairResult(data);
            if (data.applied) {
                showMessage('Playlist durations repaired successfully. Refreshing the file manager…', 'success');
                try {
                    await loadFolder(current);
                    showMessage('Playlist durations repaired successfully.', 'success');
                } catch (refreshError) {
                    showMessage('Repair succeeded, but the file manager could not refresh: ' + (refreshError.message || 'unknown error'), 'warning');
                }
            }
        } catch (error) {
            renderRepairError(error.message || 'Unable to contact the repair API.');
        } finally {
            setRepairBusy(false, false);
        }
    }

    function renderBreadcrumbs() {
        const parts = current ? current.split('/') : [];
        const crumbs = ['<button class="btn btn-link btn-sm p-0" type="button" data-path="">Videos</button>'];
        parts.forEach((part, index) => {
            const path = parts.slice(0, index + 1).join('/');
            crumbs.push('<span class="efm-breadcrumb-separator" aria-hidden="true">/</span><button class="btn btn-link btn-sm p-0" type="button" data-path="' + escapeHtml(path) + '">' + escapeHtml(part) + '</button>');
        });
        $('efm-breadcrumb').innerHTML = crumbs.join('');
    }

    async function loadPlaylists() {
        const response = await fetch('api/playlists');
        if (!response.ok) throw new Error('Unable to load FPP playlists.');
        const names = await response.json();
        $('efm-playlist').innerHTML = names.map((name) => '<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>').join('');
        updateSelectionUi();
    }

    async function loadFolder(path) {
        const next = path || '';
        if (next !== current) clearSelection();
        current = next;
        const response = await fetch(base + 'media?path=' + encodeURIComponent(current));
        const data = await response.json();
        if (!response.ok) {
            showMessage(data.error || 'Unable to list this media folder.', 'danger');
            return;
        }
        renderBreadcrumbs();
        $('efm-list').innerHTML = data.items.map((item) => {
            if (item.type === 'directory') {
                return '<tr class="efm-folder-row"><td></td><td class="efm-name" colspan="2"><button class="btn btn-link p-0 efm-folder-link" type="button" data-path="' + escapeHtml(item.path) + '"><span class="efm-folder-icon" aria-hidden="true">📁</span><span>' + escapeHtml(item.name) + '</span></button></td></tr>';
            }
            const checked = selected.has(item.path) ? ' checked' : '';
            return '<tr><td class="efm-select-cell"><input class="efm-file" type="checkbox" value="' + escapeHtml(item.path) + '" aria-label="Select ' + escapeHtml(item.name) + '"' + checked + '></td><td class="efm-name-cell"><span class="efm-video-icon me-1" aria-hidden="true">🎬</span><span class="efm-file-name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</span></td><td class="efm-type-cell"><span class="badge badge-secondary">Video</span></td></tr>';
        }).join('');
        updateSelectionUi();
    }

    document.addEventListener('click', (event) => {
        const path = event.target.closest('[data-path]');
        if (path) loadFolder(path.dataset.path);
    });

    $('efm-list').addEventListener('change', (event) => {
        if (!event.target.classList.contains('efm-file')) return;
        if (event.target.checked) selected.add(event.target.value); else selected.delete(event.target.value);
        updateSelectionUi();
    });

    $('efm-all').addEventListener('change', (event) => {
        document.querySelectorAll('.efm-file').forEach((input) => {
            input.checked = event.target.checked;
            if (event.target.checked) selected.add(input.value); else selected.delete(input.value);
        });
        updateSelectionUi();
    });

    $('efm-clear').addEventListener('click', () => {
        clearSelection();
        showMessage('Selection cleared.', 'secondary');
    });

    $('efm-playlist').addEventListener('change', () => {
        repairPreview = null;
        $('efm-repair-panel').hidden = true;
        updateSelectionUi();
    });

    $('efm-repair').addEventListener('click', () => requestRepair(false));
    $('efm-repair-cancel').addEventListener('click', () => {
        if (repairBusy) return;
        repairPreview = null;
        $('efm-repair-panel').hidden = true;
        $('efm-repair-confirm').hidden = true;
    });
    $('efm-repair-confirm').addEventListener('click', () => requestRepair(true));

    $('efm-add').addEventListener('click', async () => {
        const paths = [...selected];
        if (!paths.length) return;
        $('efm-add').disabled = true;
        showMessage('Adding selected videos to the playlist…', 'info');
        try {
            const response = await fetch(base + 'add', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({playlist: $('efm-playlist').value, paths})});
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Unable to update the playlist.');
            clearSelection();
            showMessage('Added ' + data.added + ' video(s); skipped ' + data.skipped + ' duplicate(s).', 'success');
        } catch (error) {
            showMessage(error.message || 'Unable to update the playlist.', 'danger');
        }
        updateSelectionUi();
    });

    Promise.all([loadPlaylists(), loadFolder('')]).catch((error) => showMessage(error.message || 'Unable to load the file manager.', 'danger'));
})();
</script>
