(function () {
    'use strict';

    const cfg = window.agofilesAdmin || {};
    const API = cfg.restUrl || '';
    const NONCE = cfg.nonce || '';
    let settings = cfg.settings || {};
    let folders = cfg.folders || [];

    function api(endpoint, method, body) {
        const opts = {
            method: method || 'GET',
            headers: {
                'X-WP-Nonce': NONCE,
                'Content-Type': 'application/json',
            },
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(API + endpoint, opts).then(r => r.json());
    }

    function showStatus(msg, type) {
        const el = document.getElementById('ago-files-status');
        if (!el) return;
        el.textContent = msg;
        el.className = type;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3000);
    }

    function initSettings() {
        document.querySelectorAll('.ago-switch input[data-key]').forEach(input => {
            const key = input.dataset.key;
            input.checked = !!settings[key];
        });

        const saveBtn = document.getElementById('ago-save-settings');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveSettings);
        }
    }

    function saveSettings() {
        const data = {};
        document.querySelectorAll('.ago-switch input[data-key]').forEach(input => {
            data[input.dataset.key] = input.checked;
        });

        api('/settings', 'POST', data).then(res => {
            if (res.saved) {
                settings = res.settings;
                showStatus('Settings saved.', 'success');
            } else {
                showStatus('Error saving settings.', 'error');
            }
        }).catch(() => showStatus('Error saving settings.', 'error'));
    }

    function initFolderManagement() {
        renderFolderTree();
        populateParentSelect();

        const createBtn = document.getElementById('ago-create-folder');
        if (createBtn) {
            createBtn.addEventListener('click', createFolder);
        }

        const nameInput = document.getElementById('ago-new-folder-name');
        if (nameInput) {
            nameInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') createFolder();
            });
        }
    }

    function createFolder() {
        const nameInput = document.getElementById('ago-new-folder-name');
        const parentSelect = document.getElementById('ago-new-folder-parent');
        const name = (nameInput.value || '').trim();
        const parent = parseInt(parentSelect.value, 10) || 0;

        if (!name) {
            nameInput.focus();
            return;
        }

        api('/folders', 'POST', { name, parent }).then(res => {
            if (res.error) {
                showStatus(res.error, 'error');
                return;
            }
            nameInput.value = '';
            refreshFolders();
            showStatus('Folder created.', 'success');
        }).catch(() => showStatus('Error creating folder.', 'error'));
    }

    function refreshFolders() {
        api('/folders', 'GET').then(res => {
            folders = res.folders || [];
            renderFolderTree();
            populateParentSelect();
        });
    }

    function renderFolderTree() {
        const container = document.getElementById('ago-folder-tree-admin');
        if (!container) return;

        const emptyMsg = container.querySelector('.ago-empty-folders');

        if (!folders.length) {
            container.innerHTML = '';
            if (emptyMsg) {
                container.appendChild(emptyMsg);
                emptyMsg.style.display = 'block';
            }
            return;
        }

        if (emptyMsg) emptyMsg.style.display = 'none';

        const html = buildAdminFolderHTML(folders, 0);
        container.innerHTML = html;

        container.querySelectorAll('.ago-admin-rename').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                startRename(btn.closest('.ago-admin-folder-item'));
            });
        });

        container.querySelectorAll('.ago-admin-delete').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const id = parseInt(btn.dataset.id, 10);
                const name = btn.dataset.name;
                if (confirm('Delete folder "' + name + '"? Files will not be deleted.')) {
                    api('/folders/' + id, 'DELETE').then(res => {
                        if (res.success) {
                            refreshFolders();
                            showStatus('Folder deleted.', 'success');
                        } else {
                            showStatus(res.error || 'Error.', 'error');
                        }
                    });
                }
            });
        });
    }

    function buildAdminFolderHTML(items, depth) {
        let html = '';
        items.forEach(f => {
            const indent = depth * 20;
            html += '<div class="ago-admin-folder-item" data-id="' + f.id + '" style="padding-left:' + (12 + indent) + 'px">';
            html += '<span class="ago-admin-folder-icon dashicons dashicons-portfolio"></span>';
            html += '<span class="ago-admin-folder-name">' + escHtml(f.name) + '</span>';
            html += '<span class="ago-admin-folder-count">(' + f.count + ')</span>';
            html += '<span class="ago-admin-folder-actions">';
            html += '<button class="ago-admin-rename" data-id="' + f.id + '" data-name="' + escAttr(f.name) + '">Rename</button>';
            html += '<button class="ago-admin-delete ago-delete-btn" data-id="' + f.id + '" data-name="' + escAttr(f.name) + '">Delete</button>';
            html += '</span>';
            html += '</div>';
            if (f.children && f.children.length) {
                html += buildAdminFolderHTML(f.children, depth + 1);
            }
        });
        return html;
    }

    function startRename(item) {
        const id = parseInt(item.dataset.id, 10);
        const nameEl = item.querySelector('.ago-admin-folder-name');
        const oldName = nameEl.textContent;

        const input = document.createElement('input');
        input.type = 'text';
        input.value = oldName;
        input.className = 'ago-rename-input';

        nameEl.replaceWith(input);
        input.focus();
        input.select();

        function finish() {
            const newName = input.value.trim();
            if (newName && newName !== oldName) {
                api('/folders/' + id, 'PUT', { name: newName }).then(res => {
                    if (res.success) {
                        refreshFolders();
                        showStatus('Folder renamed.', 'success');
                    } else {
                        showStatus(res.error || 'Error.', 'error');
                        revert();
                    }
                }).catch(() => { showStatus('Error.', 'error'); revert(); });
            } else {
                revert();
            }
        }

        function revert() {
            const span = document.createElement('span');
            span.className = 'ago-admin-folder-name';
            span.textContent = oldName;
            if (input.parentNode) input.replaceWith(span);
        }

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); finish(); }
            if (e.key === 'Escape') { e.preventDefault(); revert(); }
        });
        input.addEventListener('blur', finish);
    }

    function populateParentSelect() {
        const select = document.getElementById('ago-new-folder-parent');
        if (!select) return;

        const val = select.value;
        select.innerHTML = '<option value="0">(No parent)</option>';
        addParentOptions(select, folders, 0);
        select.value = val;
    }

    function addParentOptions(select, items, depth) {
        items.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.id;
            opt.textContent = '\u00A0\u00A0'.repeat(depth) + f.name;
            select.appendChild(opt);
            if (f.children && f.children.length) {
                addParentOptions(select, f.children, depth + 1);
            }
        });
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escAttr(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSettings();
        initFolderManagement();
    });
})();
