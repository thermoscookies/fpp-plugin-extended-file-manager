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
    #efm-app .efm-table { table-layout: fixed; margin-bottom: 0; }
    #efm-app .efm-table th:first-child,
    #efm-app .efm-table td:first-child { width: 3.5rem; text-align: center; }
    #efm-app .efm-table th:last-child,
    #efm-app .efm-table td:last-child { width: 8rem; }
    #efm-app .efm-name { overflow-wrap: anywhere; word-break: break-word; }
    #efm-app .efm-folder-row td { background: var(--bs-tertiary-bg, rgba(128, 128, 128, .12)); }
    #efm-app .efm-folder-link { display: flex; width: 100%; align-items: center; gap: .5rem; text-align: left; }
    #efm-app .efm-folder-icon,
    #efm-app .efm-video-icon { flex: 0 0 1.25rem; text-align: center; }
    #efm-app .efm-folder-icon { color: var(--bs-warning, #f0ad4e); }
    #efm-app .efm-video-icon { color: var(--bs-info, #5bc0de); }
    #efm-app .efm-message:empty { display: none; }
    @media (max-width: 575.98px) {
        #efm-app .efm-toolbar .efm-playlist,
        #efm-app .efm-toolbar .btn { width: 100%; }
        #efm-app .efm-selection-count { min-width: 0; }
        #efm-app .efm-table th:last-child,
        #efm-app .efm-table td:last-child { width: 5.5rem; }
        #efm-app .efm-table th:first-child,
        #efm-app .efm-table td:first-child { width: 3rem; }
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
                </div>
            </div>
        </div>
    </div>

    <div class="efm-browser p-2 p-sm-3">
        <nav id="efm-breadcrumb" class="efm-breadcrumbs mb-3" aria-label="Video folders"></nav>
        <div id="efm-message" class="efm-message alert mb-3" role="status" aria-live="polite"></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle efm-table">
                <thead><tr><th scope="col"><input id="efm-all" type="checkbox" aria-label="Select all visible videos"></th><th scope="col">Name</th><th scope="col">Type</th></tr></thead>
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
        const visible = [...document.querySelectorAll('.efm-file')];
        const checked = visible.filter((input) => input.checked).length;
        const all = $('efm-all');
        all.checked = visible.length > 0 && checked === visible.length;
        all.indeterminate = checked > 0 && checked < visible.length;
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
        current = path || '';
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
            return '<tr><td><input class="efm-file" type="checkbox" value="' + escapeHtml(item.path) + '" aria-label="Select ' + escapeHtml(item.name) + '"' + checked + '></td><td class="efm-name"><span class="efm-video-icon me-1" aria-hidden="true">▸</span>' + escapeHtml(item.name) + '</td><td><span class="badge badge-secondary">Video</span></td></tr>';
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
        selected.clear();
        document.querySelectorAll('.efm-file').forEach((input) => { input.checked = false; });
        updateSelectionUi();
        showMessage('Selection cleared.', 'secondary');
    });

    $('efm-playlist').addEventListener('change', updateSelectionUi);

    $('efm-add').addEventListener('click', async () => {
        const paths = [...selected];
        if (!paths.length) return;
        $('efm-add').disabled = true;
        showMessage('Adding selected videos to the playlist…', 'info');
        try {
            const response = await fetch(base + 'add', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({playlist: $('efm-playlist').value, paths})});
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Unable to update the playlist.');
            showMessage('Added ' + data.added + ' video(s); skipped ' + data.skipped + ' duplicate(s).', 'success');
        } catch (error) {
            showMessage(error.message || 'Unable to update the playlist.', 'danger');
        }
        updateSelectionUi();
    });

    Promise.all([loadPlaylists(), loadFolder('')]).catch((error) => showMessage(error.message || 'Unable to load the file manager.', 'danger'));
})();
</script>
