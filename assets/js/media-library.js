/**
 * aGo Files, Media Library Folder Sidebar
 *
 * Injects a folder sidebar into the WordPress media library (list view + grid view).
 * Supports: folder tree, drag & drop, create/rename/delete, bulk move, context menu.
 */
(function ($) {
    'use strict';

    const CFG       = window.agoFiles || {};
    const API       = CFG.restUrl || '';
    const NONCE     = CFG.nonce || '';
    const TAXONOMY  = CFG.taxonomy || 'ago_media_folder';
    const I18N      = CFG.i18n || {};
    let folders     = CFG.folders || [];
    let uncatCount  = CFG.uncategorizedCount || 0;
    let totalCount  = CFG.totalCount || 0;
    let activeFolder = '';
    let contextMenu  = null;

    /* ═══════════════════════════════════════════
     *  API helpers
     * ═══════════════════════════════════════════ */

    function api(endpoint, method, body) {
        const opts = {
            method: method || 'GET',
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(API + endpoint, opts).then(r => r.json());
    }

    function refreshData() {
        return api('/folders').then(data => {
            folders    = data.folders || [];
            uncatCount = data.uncategorizedCount || 0;
            totalCount = data.totalCount || 0;
        });
    }

    /* ═══════════════════════════════════════════
     *  Utility
     * ═══════════════════════════════════════════ */

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function getUrlParam(key) {
        return new URLSearchParams(window.location.search).get(key) || '';
    }

    /* ═══════════════════════════════════════════
     *  Sidebar HTML rendering
     * ═══════════════════════════════════════════ */

    function buildSidebar() {
        const sidebar = document.createElement('div');
        sidebar.className = 'ago-files-sidebar';

        // Header
        const header = document.createElement('div');
        header.className = 'ago-files-sidebar-header';
        header.textContent = 'Folders';
        sidebar.appendChild(header);

        // "All Files"
        sidebar.appendChild(buildFolderEl({
            id: '',
            name: I18N.allFiles || 'All Files',
            count: totalCount,
            _icon: '\uD83D\uDCC1', // folder icon
            _special: 'all',
        }, 0));

        // "Uncategorized"
        sidebar.appendChild(buildFolderEl({
            id: 'uncategorized',
            name: I18N.uncategorized || 'Uncategorized',
            count: uncatCount,
            _icon: '\uD83D\uDCC2', // open folder
            _special: 'uncategorized',
        }, 0));

        // Separator
        const sep = document.createElement('div');
        sep.className = 'ago-files-separator';
        sidebar.appendChild(sep);

        // Folder tree
        appendFolderNodes(sidebar, folders, 0);

        // New folder input
        const createRow = document.createElement('div');
        createRow.className = 'ago-files-new-folder';
        createRow.innerHTML =
            '<input type="text" placeholder="' + esc(I18N.folderName || 'Folder name') + '">' +
            '<button type="button">+</button>';
        sidebar.appendChild(createRow);

        const input = createRow.querySelector('input');
        const btn   = createRow.querySelector('button');
        btn.addEventListener('click', () => createNewFolder(input, 0, sidebar));
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') createNewFolder(input, 0, sidebar);
        });

        return sidebar;
    }

    function appendFolderNodes(container, items, depth) {
        items.forEach(f => {
            const el = buildFolderEl(f, depth);
            container.appendChild(el);

            // Children container (collapsible)
            if (f.children && f.children.length) {
                const childWrap = document.createElement('div');
                childWrap.className = 'ago-files-children';
                childWrap.dataset.parent = f.id;
                appendFolderNodes(childWrap, f.children, depth + 1);
                container.appendChild(childWrap);
            }
        });
    }

    function buildFolderEl(f, depth) {
        const el = document.createElement('div');
        el.className = 'ago-files-folder';
        el.dataset.id = f.id !== undefined ? f.id : '';
        el.dataset.depth = depth;

        if (f._special) el.dataset.special = f._special;

        // Active state
        if (String(f.id) === String(activeFolder) || (f._special === 'all' && activeFolder === '')) {
            el.classList.add('active');
        }

        // Toggle arrow
        const toggle = document.createElement('span');
        toggle.className = 'folder-toggle' + ((f.children && f.children.length) ? ' expanded' : ' no-children');
        toggle.textContent = '\u25B6'; // right triangle
        if (f.children && f.children.length) {
            toggle.addEventListener('click', e => {
                e.stopPropagation();
                toggleChildren(el, toggle);
            });
        }
        if (!f._special) el.appendChild(toggle);

        // Icon
        const icon = document.createElement('span');
        icon.className = 'folder-icon';
        icon.textContent = f._icon || '\uD83D\uDCC1'; // folder emoji
        el.appendChild(icon);

        // Name
        const name = document.createElement('span');
        name.className = 'folder-name';
        name.textContent = f.name;
        el.appendChild(name);

        // Count
        const count = document.createElement('span');
        count.className = 'folder-count';
        count.textContent = f.count !== undefined ? f.count : '';
        el.appendChild(count);

        // Click → filter
        el.addEventListener('click', () => selectFolder(String(f.id)));

        // Context menu (only on real folders)
        if (!f._special) {
            el.addEventListener('contextmenu', e => {
                e.preventDefault();
                e.stopPropagation();
                showContextMenu(e.clientX, e.clientY, f, el);
            });
        }

        // Drag & drop target (not for "All Files")
        if (f._special !== 'all') {
            el.addEventListener('dragover', e => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                el.classList.add('drag-over');
            });
            el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
            el.addEventListener('drop', e => {
                e.preventDefault();
                el.classList.remove('drag-over');
                handleDrop(e, f.id);
            });
        }

        return el;
    }

    function toggleChildren(folderEl, toggleEl) {
        const sidebar = folderEl.closest('.ago-files-sidebar');
        const folderId = folderEl.dataset.id;
        const childWrap = sidebar.querySelector('.ago-files-children[data-parent="' + folderId + '"]');
        if (!childWrap) return;

        const isExpanded = toggleEl.classList.contains('expanded');
        if (isExpanded) {
            childWrap.style.display = 'none';
            toggleEl.classList.remove('expanded');
        } else {
            childWrap.style.display = '';
            toggleEl.classList.add('expanded');
        }
    }

    /* ═══════════════════════════════════════════
     *  Folder selection / filtering
     * ═══════════════════════════════════════════ */

    function selectFolder(folderId) {
        activeFolder = folderId;

        // Update active state in sidebar
        document.querySelectorAll('.ago-files-folder').forEach(el => {
            const id = el.dataset.id;
            const special = el.dataset.special;
            if (String(id) === String(folderId) || (special === 'all' && folderId === '')) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });

        if (isListView()) {
            // List view: reload page with query param
            const url = new URL(window.location.href);
            if (folderId === '') {
                url.searchParams.delete('ago_folder');
            } else {
                url.searchParams.set('ago_folder', folderId);
            }
            window.location.href = url.toString();
        } else {
            // Grid view: re-query via wp.media
            filterGridView(folderId);
        }
    }

    function filterGridView(folderId) {
        if (!wp || !wp.media || !wp.media.frame) return;

        const library = wp.media.frame.state &&
                        wp.media.frame.state().get &&
                        wp.media.frame.state().get('library');

        if (library) {
            if (folderId === '') {
                library.props.unset('ago_folder');
            } else {
                library.props.set('ago_folder', folderId);
            }
            library.reset();
            library.props.set({ ignore: (+ new Date()) }); // force refresh
        } else {
            // Fallback: trigger a new AJAX query by modifying the attachments browser props
            triggerGridRefresh(folderId);
        }
    }

    function triggerGridRefresh(folderId) {
        // For the upload.php grid mode, the media library is a standalone app
        if (wp && wp.media && wp.media.frame) {
            const content = wp.media.frame.content;
            if (content && content.get) {
                const view = content.get();
                if (view && view.collection) {
                    if (folderId === '') {
                        delete view.collection.props.attributes.ago_folder;
                        view.collection.props.unset('ago_folder');
                    } else {
                        view.collection.props.set('ago_folder', folderId);
                    }
                    view.collection.reset();
                    view.collection.props.set({ ignore: (+ new Date()) });
                }
            }
        }
    }

    function isListView() {
        // upload.php in list mode has .wp-list-table
        return document.querySelector('body.upload-php') !== null &&
               document.querySelector('.wp-list-table.media') !== null &&
               !document.querySelector('.media-grid-view');
    }

    /* ═══════════════════════════════════════════
     *  Context menu
     * ═══════════════════════════════════════════ */

    function showContextMenu(x, y, folder, el) {
        closeContextMenu();

        contextMenu = document.createElement('div');
        contextMenu.className = 'ago-files-context-menu';

        // Rename
        const renameBtn = document.createElement('button');
        renameBtn.textContent = I18N.rename || 'Rename';
        renameBtn.addEventListener('click', () => { closeContextMenu(); startInlineRename(el, folder); });
        contextMenu.appendChild(renameBtn);

        // Create subfolder
        const subBtn = document.createElement('button');
        subBtn.textContent = I18N.createSub || 'Create Subfolder';
        subBtn.addEventListener('click', () => { closeContextMenu(); startInlineSubfolder(el, folder); });
        contextMenu.appendChild(subBtn);

        // Separator
        const sep = document.createElement('div');
        sep.className = 'ago-ctx-separator';
        contextMenu.appendChild(sep);

        // Delete
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'ago-ctx-delete';
        deleteBtn.textContent = I18N.delete || 'Delete';
        deleteBtn.addEventListener('click', () => {
            closeContextMenu();
            if (confirm(I18N.deleteConfirm || 'Delete this folder? Files will not be deleted.')) {
                api('/folders/' + folder.id, 'DELETE').then(() => rebuildSidebars());
            }
        });
        contextMenu.appendChild(deleteBtn);

        // Position
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        document.body.appendChild(contextMenu);

        // Adjust if off-screen
        const rect = contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) contextMenu.style.left = (x - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) contextMenu.style.top = (y - rect.height) + 'px';

        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', closeContextMenu, { once: true });
            document.addEventListener('contextmenu', closeContextMenu, { once: true });
        }, 10);
    }

    function closeContextMenu() {
        if (contextMenu && contextMenu.parentNode) {
            contextMenu.parentNode.removeChild(contextMenu);
        }
        contextMenu = null;
    }

    /* ═══════════════════════════════════════════
     *  Inline rename
     * ═══════════════════════════════════════════ */

    function startInlineRename(el, folder) {
        const nameEl = el.querySelector('.folder-name');
        if (!nameEl) return;
        const oldName = nameEl.textContent;

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'ago-files-rename-input';
        input.value = oldName;
        nameEl.replaceWith(input);
        input.focus();
        input.select();

        function finish() {
            const newName = input.value.trim();
            if (newName && newName !== oldName) {
                api('/folders/' + folder.id, 'PUT', { name: newName }).then(() => rebuildSidebars());
            } else {
                revert();
            }
        }

        function revert() {
            const span = document.createElement('span');
            span.className = 'folder-name';
            span.textContent = oldName;
            if (input.parentNode) input.replaceWith(span);
        }

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); finish(); }
            if (e.key === 'Escape') { e.preventDefault(); revert(); }
        });
        input.addEventListener('blur', finish);
    }

    /* ═══════════════════════════════════════════
     *  Inline subfolder creation
     * ═══════════════════════════════════════════ */

    function startInlineSubfolder(el, parentFolder) {
        const sidebar = el.closest('.ago-files-sidebar');
        if (!sidebar) return;

        // Find or create children container after this folder element
        let childWrap = sidebar.querySelector('.ago-files-children[data-parent="' + parentFolder.id + '"]');
        if (!childWrap) {
            childWrap = document.createElement('div');
            childWrap.className = 'ago-files-children';
            childWrap.dataset.parent = parentFolder.id;
            el.after(childWrap);
        }

        const row = document.createElement('div');
        row.className = 'ago-files-inline-create';
        const depth = parseInt(el.dataset.depth || '0', 10) + 1;
        row.style.paddingLeft = (12 + depth * 16) + 'px';

        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = I18N.folderName || 'Folder name';
        row.appendChild(input);
        childWrap.insertBefore(row, childWrap.firstChild);
        input.focus();

        function finish() {
            const name = input.value.trim();
            if (name) {
                api('/folders', 'POST', { name, parent: parentFolder.id }).then(() => {
                    row.remove();
                    rebuildSidebars();
                });
            } else {
                row.remove();
            }
        }

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); finish(); }
            if (e.key === 'Escape') { e.preventDefault(); row.remove(); }
        });
        input.addEventListener('blur', finish);
    }

    /* ═══════════════════════════════════════════
     *  Create new folder (from sidebar footer input)
     * ═══════════════════════════════════════════ */

    function createNewFolder(input, parent, sidebar) {
        const name = (input.value || '').trim();
        if (!name) return;

        api('/folders', 'POST', { name, parent: parent || 0 }).then(res => {
            if (res.error) {
                alert(res.error);
                return;
            }
            input.value = '';
            rebuildSidebars();
        });
    }

    /* ═══════════════════════════════════════════
     *  Rebuild all sidebars after data change
     * ═══════════════════════════════════════════ */

    function rebuildSidebars() {
        refreshData().then(() => {
            document.querySelectorAll('.ago-files-sidebar').forEach(old => {
                const newSidebar = buildSidebar();
                old.replaceWith(newSidebar);
            });
        });
    }

    /* ═══════════════════════════════════════════
     *  Drag & Drop
     * ═══════════════════════════════════════════ */

    function initDragOnListView() {
        const table = document.querySelector('.wp-list-table.media');
        if (!table) return;

        table.querySelectorAll('tbody tr').forEach(row => {
            row.draggable = true;
            row.addEventListener('dragstart', e => {
                const id = getAttachmentIdFromRow(row);
                if (!id) return;

                // Collect checked items for bulk drag
                const checked = getCheckedAttachmentIds();
                const ids = checked.length > 1 && checked.includes(id) ? checked : [id];

                e.dataTransfer.setData('text/plain', JSON.stringify(ids));
                e.dataTransfer.effectAllowed = 'move';
                row.classList.add('ago-dragging');
            });
            row.addEventListener('dragend', () => row.classList.remove('ago-dragging'));
        });
    }

    function initDragOnGridView() {
        // Monitor for newly rendered attachment items
        const observer = new MutationObserver(() => {
            document.querySelectorAll('.attachments-browser .attachment:not([data-ago-drag])').forEach(el => {
                el.setAttribute('data-ago-drag', '1');
                el.draggable = true;
                el.addEventListener('dragstart', e => {
                    const id = getAttachmentIdFromGridItem(el);
                    if (!id) return;

                    // Check if multiple are selected
                    const selected = getSelectedGridIds();
                    const ids = selected.length > 1 && selected.includes(id) ? selected : [id];

                    e.dataTransfer.setData('text/plain', JSON.stringify(ids));
                    e.dataTransfer.effectAllowed = 'move';
                    el.classList.add('ago-dragging');
                });
                el.addEventListener('dragend', () => el.classList.remove('ago-dragging'));
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function handleDrop(e, folderId) {
        let ids = [];
        try {
            ids = JSON.parse(e.dataTransfer.getData('text/plain'));
        } catch (err) {
            return;
        }
        if (!ids.length || folderId === '') return;

        // "uncategorized" means unassign, we need a different approach
        if (folderId === 'uncategorized') {
            // Remove from all folders: set terms to empty
            ids.forEach(id => {
                $.ajax({
                    url: API.replace('/ago-files/v1', '') + '/wp/v2/media/' + id,
                    method: 'POST',
                    headers: { 'X-WP-Nonce': NONCE },
                    contentType: 'application/json',
                    data: JSON.stringify({ ago_media_folder: [] }),
                });
            });
            setTimeout(rebuildSidebars, 500);
            return;
        }

        api('/move', 'POST', { attachment_ids: ids, folder_id: parseInt(folderId, 10) }).then(() => {
            rebuildSidebars();
            // Refresh the media view if in grid
            if (!isListView() && wp && wp.media && wp.media.frame) {
                const lib = wp.media.frame.state && wp.media.frame.state().get && wp.media.frame.state().get('library');
                if (lib) {
                    lib.reset();
                    lib.props.set({ ignore: (+ new Date()) });
                }
            }
        });
    }

    function getAttachmentIdFromRow(row) {
        // WP list table row ID format: post-{ID}
        const id = row.id || '';
        const m = id.match(/^post-(\d+)$/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function getCheckedAttachmentIds() {
        const ids = [];
        document.querySelectorAll('.wp-list-table.media tbody input[name="media[]"]:checked').forEach(cb => {
            ids.push(parseInt(cb.value, 10));
        });
        return ids;
    }

    function getAttachmentIdFromGridItem(el) {
        // Grid attachment data-id attribute
        const id = el.getAttribute('data-id');
        return id ? parseInt(id, 10) : 0;
    }

    function getSelectedGridIds() {
        const ids = [];
        document.querySelectorAll('.attachments-browser .attachment[aria-checked="true"], .attachments-browser .attachment.selected').forEach(el => {
            const id = getAttachmentIdFromGridItem(el);
            if (id) ids.push(id);
        });
        return ids;
    }

    /* ═══════════════════════════════════════════
     *  Bulk move (toolbar addition)
     * ═══════════════════════════════════════════ */

    function addBulkMoveToToolbar() {
        // For list view, add after the bulk actions
        const bulkActions = document.querySelector('.tablenav.top .actions.bulkactions');
        if (!bulkActions) return;

        const wrap = document.createElement('div');
        wrap.className = 'ago-files-bulk-move';
        wrap.style.display = 'inline-flex';

        const select = document.createElement('select');
        select.innerHTML = '<option value="">' + esc(I18N.moveTo || 'Move to folder') + '</option>';
        addFolderOptions(select, folders, 0);
        wrap.appendChild(select);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'button';
        btn.textContent = I18N.bulkMove || 'Move';
        btn.addEventListener('click', () => {
            const folderId = parseInt(select.value, 10);
            if (!folderId) return;
            const ids = getCheckedAttachmentIds();
            if (!ids.length) return;

            api('/move', 'POST', { attachment_ids: ids, folder_id: folderId }).then(() => {
                rebuildSidebars();
                window.location.reload();
            });
        });
        wrap.appendChild(btn);

        bulkActions.parentNode.insertBefore(wrap, bulkActions.nextSibling);
    }

    function addFolderOptions(select, items, depth) {
        items.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.id;
            opt.textContent = '\u00A0\u00A0'.repeat(depth) + f.name;
            select.appendChild(opt);
            if (f.children && f.children.length) {
                addFolderOptions(select, f.children, depth + 1);
            }
        });
    }

    /* ═══════════════════════════════════════════
     *  Inject sidebar into List View (upload.php table mode)
     * ═══════════════════════════════════════════ */

    function injectListView() {
        activeFolder = getUrlParam('ago_folder');

        const form = document.querySelector('#posts-filter') || document.querySelector('.wp-list-table.media');
        if (!form) return;

        // Build wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'ago-files-wrap';

        const sidebar = buildSidebar();
        wrapper.appendChild(sidebar);

        // Move the existing content into the wrapper
        const contentWrap = document.createElement('div');
        contentWrap.style.flex = '1';
        contentWrap.style.minWidth = '0';
        contentWrap.style.overflow = 'auto';

        // We wrap the form (which contains the table, pagination, etc.)
        const parent = form.parentNode;
        parent.insertBefore(wrapper, form);
        contentWrap.appendChild(form);
        wrapper.appendChild(contentWrap);

        // Init drag on table rows
        initDragOnListView();
        addBulkMoveToToolbar();
    }

    /* ═══════════════════════════════════════════
     *  Inject sidebar into Grid View (upload.php grid mode)
     * ═══════════════════════════════════════════ */

    function injectGridView() {
        activeFolder = getUrlParam('ago_folder');

        function tryInject() {
            const browser = document.querySelector('.media-frame-content .attachments-browser')
                         || document.querySelector('.attachments-browser');
            if (!browser) return false;
            if (browser.querySelector('.ago-files-sidebar')) return true; // already done

            // Wait until the browser has real content (attachments or the uploader-inline)
            const hasContent = browser.querySelector('.attachments') ||
                               browser.querySelector('.uploader-inline') ||
                               browser.querySelector('.media-toolbar');
            if (!hasContent) return false;

            try {
                var sidebar = buildSidebar();
                browser.insertBefore(sidebar, browser.firstChild);
                browser.style.display = 'flex';

                initDragOnGridView();
                hookMediaAjax();
            } catch(err) {
                return false;
            }
            return true;
        }

        // Poll + observe until the media grid is fully ready
        const check = setInterval(() => {
            var result = tryInject();
            if (result) { clearInterval(check); if (observer) observer.disconnect(); }
        }, 300);

        const observer = new MutationObserver(() => {
            if (tryInject()) { clearInterval(check); observer.disconnect(); }
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Safety cleanup after 20 seconds
        setTimeout(() => { clearInterval(check); observer.disconnect(); }, 20000);
    }

    function hookMediaAjax() {
        if (!wp || !wp.media || !wp.media.ajax) return;

        const origAjax = wp.media.ajax;
        wp.media.ajax = function (action, options) {
            if (typeof action === 'object') {
                options = action;
                action = undefined;
            }
            if (options && options.data && options.data.query) {
                if (activeFolder !== '') {
                    options.data.query.ago_folder = activeFolder;
                } else {
                    delete options.data.query.ago_folder;
                }
            }
            if (action) {
                return origAjax.call(this, action, options);
            }
            return origAjax.call(this, options);
        };
    }

    /* ═══════════════════════════════════════════
     *  Inject sidebar into Media Modal (post.php, post-new.php)
     * ═══════════════════════════════════════════ */

    function injectMediaModal() {
        if (!wp || !wp.media) return;

        // Hook into media frame open
        const origOpen = wp.media.view.Modal.prototype.open;
        wp.media.view.Modal.prototype.open = function () {
            origOpen.apply(this, arguments);

            // Wait for the browser to render inside the modal
            setTimeout(() => {
                const modal = this.$el ? this.$el[0] : null;
                if (!modal) return;

                const browser = modal.querySelector('.attachments-browser');
                if (!browser || browser.querySelector('.ago-files-sidebar')) return;

                const sidebar = buildSidebar();
                browser.insertBefore(sidebar, browser.firstChild);
                browser.style.display = 'flex';

                initDragOnGridView();
                hookMediaAjax();
            }, 300);
        };
    }

    /* ═══════════════════════════════════════════
     *  Initialization
     * ═══════════════════════════════════════════ */

    function init() {
        const body = document.body;

        if (body.classList.contains('upload-php')) {
            const isList = document.querySelector('.wp-list-table.media') !== null ||
                           getUrlParam('mode') === 'list';

            if (isList) {
                injectListView();
            } else {
                injectGridView();
            }
        } else {
            injectMediaModal();
        }

        // Global: close context menu on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeContextMenu();
        });
    }

    // Boot, call init immediately since script loads in footer after DOM is ready
    if (document.readyState === 'loading') {
        $(document).ready(init);
    } else {
        init();
    }

})(jQuery);
