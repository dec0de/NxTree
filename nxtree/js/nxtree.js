(function () {
    'use strict';

    const sidebarWidthStorageKey = 'nxtree.sidebarWidth';

    function endpoint(path) {
        if (window.OC && typeof window.OC.generateUrl === 'function') {
            return window.OC.generateUrl('/apps/nxtree' + path);
        }

        return '/apps/nxtree' + path;
    }

    function requestHeaders() {
        const headers = { Accept: 'application/json' };
        if (window.OC && window.OC.requestToken) {
            headers.requesttoken = window.OC.requestToken;
        }
        return headers;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const app = document.getElementById('nxtree-app');
        if (!app) {
            return;
        }

        const fileToggle = document.getElementById('nxtree-file-toggle');
        const fileMenu = document.getElementById('nxtree-file-menu');
        const newTreeButton = document.getElementById('nxtree-new-tree');
        const loadTreeButton = document.getElementById('nxtree-load-tree');
        const backTreeButton = document.getElementById('nxtree-back-tree');
        const saveTreeButton = document.getElementById('nxtree-save-tree');
        const importFilesButton = document.getElementById('nxtree-import-files');
        const exportFilesButton = document.getElementById('nxtree-export-files');
        const treeList = document.getElementById('nxtree-tree-list');
        const treeEmpty = document.getElementById('nxtree-tree-empty');
        const libraryPathEl = document.getElementById('nxtree-library-path');
        const treeEl = document.getElementById('nxtree-tree');
        const dividerEl = document.getElementById('nxtree-divider');
        const titleEl = document.getElementById('nxtree-node-title');
        const contentEl = document.getElementById('nxtree-node-content');
        const previewEl = document.getElementById('nxtree-node-preview');
        const editModeButton = document.getElementById('nxtree-edit-mode');
        const saveStateEl = document.getElementById('nxtree-save-state');
        const revisionEl = document.getElementById('nxtree-revision');
        const statusEl = document.getElementById('nxtree-status');
        const addNodeButton = document.getElementById('nxtree-add-node');
        const deleteNodeButton = document.getElementById('nxtree-delete-node');
        const sortAscButton = document.getElementById('nxtree-sort-asc');
        const sortDescButton = document.getElementById('nxtree-sort-desc');
        const expandButton = document.getElementById('nxtree-expand-branch');
        const collapseButton = document.getElementById('nxtree-collapse-branch');
        const undoButton = document.getElementById('nxtree-undo');
        const searchToggle = document.getElementById('nxtree-search-toggle');
        const searchPanel = document.getElementById('nxtree-search-panel');
        const searchPanelHeader = searchPanel.querySelector('.nxtree-search-panel-header');
        const searchClose = document.getElementById('nxtree-search-close');
        const searchInput = document.getElementById('nxtree-search-input');
        const searchTitle = document.getElementById('nxtree-search-title');
        const searchContent = document.getElementById('nxtree-search-content');
        const searchCase = document.getElementById('nxtree-search-case');
        const searchRegex = document.getElementById('nxtree-search-regex');
        const searchResults = document.getElementById('nxtree-search-results');
        const filesPanel = document.getElementById('nxtree-files-panel');
        const filesPanelHeader = filesPanel.querySelector('.nxtree-files-panel-header');
        const filesTitle = document.getElementById('nxtree-files-title');
        const filesClose = document.getElementById('nxtree-files-close');
        const filesUp = document.getElementById('nxtree-files-up');
        const filesPathEl = document.getElementById('nxtree-files-path');
        const filesList = document.getElementById('nxtree-files-list');
        const filesExportFields = document.getElementById('nxtree-files-export-fields');
        const filesFilenameLabel = document.getElementById('nxtree-files-filename-label');
        const filesFilename = document.getElementById('nxtree-files-filename');
        const filesSave = document.getElementById('nxtree-files-save');

        let trees = [];
        let selectedTreeId = null;
        let currentTree = null;
        let selectedNodeId = null;
        let editorMode = 'preview';
        let draggedNodeId = null;
        let saveTimer = null;
        let isSaving = false;
        let saveQueued = false;
        let syncTimer = null;
        let isSyncing = false;
        let remoteChangePending = false;
        let filesMode = 'import';
        let filesCurrentPath = '/';
        let filesParentPath = null;
        let currentLibraryPath = '/NxTree';
        let directoryTreeId = null;
        let directoryTargetFolderId = null;
        let previousTreeId = null;
        const undoStack = [];
        const collapsedIds = new Set();

        function setStatus(message) {
            statusEl.textContent = message;
        }

        function setSaveState(message) {
            saveStateEl.textContent = message;
        }

        function updateBackButton() {
            const disabled = previousTreeId === null;
            backTreeButton.disabled = disabled;
            backTreeButton.hidden = disabled;
        }

        function isDirectoryTreeLoaded() {
            return currentTree && (currentTree.isDirectoryTree === true || currentTree.isDirectoryTree === 1 || currentTree.isDirectoryTree === '1');
        }

        function isDirectoryFileNode(node) {
            return isDirectoryTreeLoaded() && node && node.nodeKind === 'tree_file' && node.linkedTreeId;
        }

        function initDivider() {
            const storedWidth = localStorage.getItem(sidebarWidthStorageKey);
            if (storedWidth) {
                app.style.setProperty('--nxtree-sidebar-width', storedWidth);
            }
            if (!dividerEl) {
                return;
            }
            dividerEl.addEventListener('pointerdown', event => {
                if (window.matchMedia('(max-width: 800px)').matches) {
                    return;
                }
                event.preventDefault();
                dividerEl.classList.add('dragging');
                app.classList.add('resizing-sidebar');
                dividerEl.setPointerCapture(event.pointerId);

                function onMove(moveEvent) {
                    const rect = app.getBoundingClientRect();
                    const width = Math.min(Math.max(240, moveEvent.clientX - rect.left), Math.max(320, rect.width * 0.7));
                    const value = `${Math.round(width)}px`;
                    app.style.setProperty('--nxtree-sidebar-width', value);
                    localStorage.setItem(sidebarWidthStorageKey, value);
                }

                function onUp(upEvent) {
                    dividerEl.classList.remove('dragging');
                    app.classList.remove('resizing-sidebar');
                    dividerEl.releasePointerCapture(upEvent.pointerId);
                    dividerEl.removeEventListener('pointermove', onMove);
                    dividerEl.removeEventListener('pointerup', onUp);
                    dividerEl.removeEventListener('pointercancel', onUp);
                }

                dividerEl.addEventListener('pointermove', onMove);
                dividerEl.addEventListener('pointerup', onUp);
                dividerEl.addEventListener('pointercancel', onUp);
            });
        }

        function makePanelDraggable(panel, header) {
            header.addEventListener('pointerdown', event => {
                if (event.target.closest('button')) {
                    return;
                }
                const rect = panel.getBoundingClientRect();
                const offsetX = event.clientX - rect.left;
                const offsetY = event.clientY - rect.top;
                panel.classList.add('dragging');
                header.setPointerCapture(event.pointerId);

                function onMove(moveEvent) {
                    const maxLeft = window.innerWidth - panel.offsetWidth - 8;
                    const maxTop = window.innerHeight - panel.offsetHeight - 8;
                    const left = Math.min(Math.max(8, moveEvent.clientX - offsetX), Math.max(8, maxLeft));
                    const top = Math.min(Math.max(8, moveEvent.clientY - offsetY), Math.max(8, maxTop));
                    panel.style.left = `${left}px`;
                    panel.style.top = `${top}px`;
                    panel.style.right = 'auto';
                }

                function onUp(upEvent) {
                    panel.classList.remove('dragging');
                    header.releasePointerCapture(upEvent.pointerId);
                    header.removeEventListener('pointermove', onMove);
                    header.removeEventListener('pointerup', onUp);
                    header.removeEventListener('pointercancel', onUp);
                }

                header.addEventListener('pointermove', onMove);
                header.addEventListener('pointerup', onUp);
                header.addEventListener('pointercancel', onUp);
            });
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function safeUrl(value) {
            try {
                const url = new URL(value, window.location.href);
                return ['http:', 'https:', 'mailto:'].includes(url.protocol) ? url.href : '';
            } catch (error) {
                return '';
            }
        }

        function renderInlineMarkdown(value) {
            const placeholders = [];
            function hold(html) {
                placeholders.push(html);
                return `\u0000${placeholders.length - 1}\u0000`;
            }

            value = String(value).replace(/`([^`]+)`/g, (match, code) => hold(`<code>${escapeHtml(code)}</code>`));
            value = value.replace(/\[([^\]]+)\]\(([^\s)]+)\)/g, (match, label, href) => {
                const url = safeUrl(href);
                return url ? hold(`<a href="${escapeHtml(url)}" target="_blank" rel="noreferrer noopener">${escapeHtml(label)}</a>`) : label;
            });
            value = escapeHtml(value)
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/__([^_]+)__/g, '<strong>$1</strong>')
                .replace(/(^|\W)\*([^*]+)\*/g, '$1<em>$2</em>')
                .replace(/(^|\W)_([^_]+)_/g, '$1<em>$2</em>')
                .replace(/~~([^~]+)~~/g, '<del>$1</del>');
            return value.replace(/\u0000(\d+)\u0000/g, (match, index) => placeholders[Number(index)] || '');
        }

        function isMarkdownBlockStart(line) {
            return /^\s*(```|#{1,6}\s+|([-*_])\s*\2\s*\2\s*$|>|[-*+]\s+|\d+\.\s+)/.test(line);
        }

        function renderMarkdown(markdown) {
            const lines = String(markdown || '').replace(/\r\n?/g, '\n').split('\n');
            const html = [];
            let i = 0;

            while (i < lines.length) {
                const line = lines[i];
                if (line.trim() === '') {
                    i++;
                    continue;
                }

                if (/^\s*```/.test(line)) {
                    const code = [];
                    i++;
                    while (i < lines.length && !/^\s*```/.test(lines[i])) {
                        code.push(lines[i]);
                        i++;
                    }
                    if (i < lines.length) {
                        i++;
                    }
                    html.push(`<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`);
                    continue;
                }

                const heading = line.match(/^\s*(#{1,6})\s+(.+)$/);
                if (heading) {
                    const level = heading[1].length;
                    html.push(`<h${level}>${renderInlineMarkdown(heading[2])}</h${level}>`);
                    i++;
                    continue;
                }

                if (/^\s*([-*_])\s*\1\s*\1\s*$/.test(line)) {
                    html.push('<hr>');
                    i++;
                    continue;
                }

                if (/^\s*>/.test(line)) {
                    const quote = [];
                    while (i < lines.length && /^\s*>/.test(lines[i])) {
                        quote.push(lines[i].replace(/^\s*>\s?/, ''));
                        i++;
                    }
                    html.push(`<blockquote>${renderMarkdown(quote.join('\n'))}</blockquote>`);
                    continue;
                }

                const listMatch = line.match(/^\s*(?:([-*+])|(\d+)\.)\s+(.+)$/);
                if (listMatch) {
                    const ordered = Boolean(listMatch[2]);
                    const tag = ordered ? 'ol' : 'ul';
                    const items = [];
                    while (i < lines.length) {
                        const item = lines[i].match(/^\s*(?:([-*+])|(\d+)\.)\s+(.+)$/);
                        if (!item || Boolean(item[2]) !== ordered) {
                            break;
                        }
                        let content = item[3];
                        const task = content.match(/^\[( |x|X)\]\s+(.+)$/);
                        if (task) {
                            const checked = task[1].toLowerCase() === 'x' ? ' checked' : '';
                            content = `<input type="checkbox" disabled${checked}>${renderInlineMarkdown(task[2])}`;
                        } else {
                            content = renderInlineMarkdown(content);
                        }
                        items.push(`<li>${content}</li>`);
                        i++;
                    }
                    html.push(`<${tag}>${items.join('')}</${tag}>`);
                    continue;
                }

                const paragraph = [line];
                i++;
                while (i < lines.length && lines[i].trim() !== '' && !isMarkdownBlockStart(lines[i])) {
                    paragraph.push(lines[i]);
                    i++;
                }
                html.push(`<p>${renderInlineMarkdown(paragraph.join('\n')).replace(/\n/g, '<br>')}</p>`);
            }

            return html.join('\n');
        }

        function buildNodeTree() {
            if (!currentTree || !Array.isArray(currentTree.nodes)) {
                return [];
            }
            const byParent = new Map();
            currentTree.nodes.forEach(node => {
                const key = node.parentId === null ? 'root' : String(node.parentId);
                if (!byParent.has(key)) {
                    byParent.set(key, []);
                }
                byParent.get(key).push({ ...node, children: [] });
            });
            byParent.forEach(nodes => nodes.sort((left, right) => (left.sortOrder - right.sortOrder) || (left.id - right.id)));
            const byId = new Map();
            byParent.forEach(nodes => nodes.forEach(node => byId.set(String(node.id), node)));
            byId.forEach(node => {
                node.children = byParent.get(String(node.id)) || [];
            });
            return byParent.get('root') || [];
        }

        function findNode(id, nodes = buildNodeTree()) {
            for (const node of nodes) {
                if (String(node.id) === String(id)) {
                    return node;
                }
                const found = findNode(id, node.children || []);
                if (found) {
                    return found;
                }
            }
            return null;
        }

        function findPath(id, nodes = buildNodeTree(), path = []) {
            for (const node of nodes) {
                const nextPath = path.concat(node.title || 'Untitled node');
                if (String(node.id) === String(id)) {
                    return nextPath;
                }
                const found = findPath(id, node.children || [], nextPath);
                if (found) {
                    return found;
                }
            }
            return null;
        }

        function selectedNode() {
            return selectedNodeId === null ? null : findNode(selectedNodeId);
        }

        function nodeContains(node, id) {
            return (node.children || []).some(child => String(child.id) === String(id) || nodeContains(child, id));
        }

        function collapseSubtree(node) {
            collapsedIds.add(String(node.id));
            (node.children || []).forEach(child => collapseSubtree(child));
        }

        function expandSubtree(node) {
            collapsedIds.delete(String(node.id));
            (node.children || []).forEach(child => expandSubtree(child));
        }

        function renderTreeList() {
            if (!treeList || !treeEmpty || !libraryPathEl) {
                return;
            }
            treeList.textContent = '';
            currentLibraryPath = normaliseLibraryPath(currentLibraryPath);
            libraryPathEl.textContent = currentLibraryPath;

            const folders = new Set();
            const files = [];
            trees.forEach(tree => {
                const folder = treeLibraryFolder(tree);
                if (folder === currentLibraryPath) {
                    files.push(tree);
                    return;
                }

                const child = childFolderName(folder, currentLibraryPath);
                if (child !== null) {
                    folders.add(child);
                }
            });

            treeEmpty.hidden = folders.size > 0 || files.length > 0;
            if (currentLibraryPath !== '/') {
                treeList.appendChild(libraryFolderButton('..', parentPath(currentLibraryPath), 'Up one folder'));
            }

            Array.from(folders).sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' })).forEach(folder => {
                const path = joinLibraryPath(currentLibraryPath, folder);
                treeList.appendChild(libraryFolderButton(folder, path, path));
            });

            files.sort((left, right) => treeLibraryName(left).localeCompare(treeLibraryName(right), undefined, { sensitivity: 'base' })).forEach(tree => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'nxtree-tree-item';
                button.classList.toggle('active', tree.id === selectedTreeId);
                button.title = treeTooltip(tree);

                const title = document.createElement('span');
                title.className = 'nxtree-tree-item-title';
                title.textContent = `${treeLibraryName(tree)}.nxtree`;
                button.appendChild(title);

                const meta = document.createElement('span');
                meta.className = 'nxtree-tree-item-meta';
                meta.textContent = treeMeta(tree);
                button.appendChild(meta);
                button.addEventListener('click', () => {
                    selectedTreeId = tree.id;
                    renderTreeList();
                    loadTree(tree.id);
                });
                treeList.appendChild(button);
            });
        }

        function libraryFolderButton(name, path, metaText) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'nxtree-tree-item nxtree-library-folder';

            const title = document.createElement('span');
            title.className = 'nxtree-tree-item-title';
            title.textContent = name === '..' ? '..' : `${name}/`;
            button.appendChild(title);

            const meta = document.createElement('span');
            meta.className = 'nxtree-tree-item-meta';
            meta.textContent = metaText;
            button.appendChild(meta);
            button.addEventListener('click', () => {
                currentLibraryPath = normaliseLibraryPath(path);
                renderTreeList();
            });

            return button;
        }

        function normaliseLibraryPath(path) {
            let normalized = String(path || '/NxTree').replace(/\\/g, '/').replace(/\/+/g, '/');
            if (!normalized.startsWith('/')) {
                normalized = '/' + normalized;
            }
            normalized = normalized.replace(/\/$/, '');
            return normalized === '' ? '/' : normalized;
        }

        function joinLibraryPath(base, name) {
            return normaliseLibraryPath(`${normaliseLibraryPath(base)}/${name}`);
        }

        function childFolderName(folder, base) {
            folder = normaliseLibraryPath(folder);
            base = normaliseLibraryPath(base);
            if (folder === base) {
                return null;
            }
            const prefix = base === '/' ? '/' : `${base}/`;
            if (!folder.startsWith(prefix)) {
                return null;
            }
            const remainder = folder.slice(prefix.length).split('/').filter(Boolean);
            return remainder.length > 0 ? remainder[0] : null;
        }

        function treeLibraryFolder(tree) {
            if (tree.libraryPath) {
                return normaliseLibraryPath(tree.libraryPath);
            }
            if (tree.sourceFilePath) {
                return normaliseLibraryPath(parentPath(tree.sourceFilePath));
            }
            if (tree.lastExportFolderPath) {
                return normaliseLibraryPath(tree.lastExportFolderPath);
            }
            return '/NxTree';
        }

        function treeLibraryName(tree) {
            return String(tree.libraryName || tree.title || 'Untitled tree').replace(/\.(nxtree|mtre)$/i, '').trim() || 'Untitled tree';
        }

        function directoryFileName(node) {
            const title = String(node.title || '').replace(/\.(nxtree|mtre)$/i, '').trim();
            if (title !== '' && title !== 'Untitled tree') {
                return title;
            }

            const linkedTree = trees.find(tree => String(tree.id) === String(node.linkedTreeId));
            return linkedTree ? treeLibraryName(linkedTree) : 'Untitled tree';
        }

        function treeMeta(tree) {
            if (tree.libraryPath) {
                return `NxTree database · Revision ${tree.revision || 0}`;
            }
            if (tree.sourceFilePath) {
                return `Imported from ${tree.sourceFilePath}`;
            }
            if (tree.lastExportFolderPath) {
                return `Last exported to ${tree.lastExportFolderPath}`;
            }
            return `Revision ${tree.revision || 0} · NxTree database`;
        }

        function treeTooltip(tree) {
            const lines = [tree.title || 'Untitled tree'];
            if (tree.libraryPath) {
                lines.push(`Library folder: ${tree.libraryPath}`);
            }
            if (tree.libraryName) {
                lines.push(`Library name: ${tree.libraryName}.nxtree`);
            }
            if (tree.sourceFilePath) {
                lines.push(`Source: ${tree.sourceFilePath}`);
            }
            if (tree.lastExportFolderPath) {
                lines.push(`Export folder: ${tree.lastExportFolderPath}`);
            }
            lines.push(`Revision: ${tree.revision || 0}`);
            lines.push(`Tree id: ${tree.id}`);
            return lines.join('\n');
        }

        function refreshTreeSummary(tree) {
            const existing = trees.find(item => item.id === tree.id);
            if (existing) {
                existing.title = tree.title;
                existing.revision = tree.revision;
                existing.updatedAt = tree.updatedAt;
                existing.sourceFilePath = tree.sourceFilePath;
                existing.lastExportFolderPath = tree.lastExportFolderPath;
                existing.libraryPath = tree.libraryPath;
                existing.libraryName = tree.libraryName;
            }
            renderTreeList();
        }

        function renderTree() {
            treeEl.textContent = '';
            const roots = buildNodeTree();
            if (roots.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'nxtree-empty';
                empty.textContent = currentTree ? 'No nodes loaded.' : 'Create or import a tree.';
                treeEl.appendChild(empty);
                return;
            }

            function add(node, ancestorHasNext, isLast, isRoot) {
                const row = document.createElement('div');
                row.className = 'nxtree-tree-row';
                row.draggable = !isRoot;
                row.dataset.nodeId = String(node.id);
                row.addEventListener('dragstart', event => {
                    if (isRoot) {
                        event.preventDefault();
                        return;
                    }
                    draggedNodeId = node.id;
                    row.classList.add('dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(node.id));
                });
                row.addEventListener('dragend', () => {
                    draggedNodeId = null;
                    clearDropClasses();
                    row.classList.remove('dragging');
                });
                row.addEventListener('dragover', event => {
                    if (!draggedNodeId || String(draggedNodeId) === String(node.id)) {
                        return;
                    }
                    const dragged = findNode(draggedNodeId);
                    if (dragged && nodeContains(dragged, node.id)) {
                        return;
                    }
                    event.preventDefault();
                    const mode = getDropMode(event, row);
                    clearDropClasses();
                    row.classList.add(`drop-${mode}`);
                });
                row.addEventListener('dragleave', event => {
                    if (!row.contains(event.relatedTarget)) {
                        row.classList.remove('drop-before', 'drop-inside', 'drop-after');
                    }
                });
                row.addEventListener('drop', event => {
                    if (!draggedNodeId) {
                        return;
                    }
                    event.preventDefault();
                    const mode = getDropMode(event, row);
                    clearDropClasses();
                    moveNode(draggedNodeId, node.id, mode);
                });
                const guides = document.createElement('span');
                guides.className = 'nxtree-tree-guides';
                ancestorHasNext.forEach(hasNext => {
                    const guide = document.createElement('span');
                    guide.className = `nxtree-tree-guide ${hasNext ? 'continue' : 'blank'}`;
                    guides.appendChild(guide);
                });
                if (!isRoot) {
                    const connector = document.createElement('span');
                    connector.className = `nxtree-tree-guide ${isLast ? 'elbow' : 'tee'}`;
                    guides.appendChild(connector);
                }

                const children = node.children || [];
                const isCollapsed = collapsedIds.has(String(node.id));
                row.appendChild(guides);
                if (children.length > 0) {
                    const toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'nxtree-tree-toggle';
                    toggle.textContent = isCollapsed ? '+' : '-';
                    toggle.title = isCollapsed ? 'Expand branch' : 'Collapse branch';
                    toggle.addEventListener('click', event => {
                        event.stopPropagation();
                        selectNode(node.id);
                        if (collapsedIds.has(String(node.id))) {
                            collapsedIds.delete(String(node.id));
                        } else {
                            collapseSubtree(node);
                        }
                        renderTree();
                    });
                    row.appendChild(toggle);
                } else {
                    const spacer = document.createElement('span');
                    spacer.className = 'nxtree-tree-toggle-spacer';
                    row.appendChild(spacer);
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'nxtree-tree-label';
                const isTreeFile = isDirectoryFileNode(node);
                row.classList.toggle('nxtree-directory-file-row', isTreeFile);
                row.classList.toggle('nxtree-directory-folder-row', isDirectoryTreeLoaded() && !isTreeFile);
                button.textContent = isTreeFile ? `${directoryFileName(node)}.nxtree` : (node.title || 'Untitled node');
                button.classList.toggle('active', String(node.id) === String(selectedNodeId));
                button.addEventListener('click', () => {
                    if (isTreeFile) {
                        directoryTargetFolderId = node.parentId || currentTree.rootNodeId;
                        selectedTreeId = node.linkedTreeId;
                        loadTree(node.linkedTreeId);
                        return;
                    }
                    selectNode(node.id);
                });
                row.appendChild(button);
                treeEl.appendChild(row);

                if (isCollapsed) {
                    return;
                }
                const childAncestors = isRoot ? ancestorHasNext : ancestorHasNext.concat(!isLast);
                children.forEach((child, index) => add(child, childAncestors, index === children.length - 1, false));
            }

            roots.forEach((node, index) => add(node, [], index === roots.length - 1, roots.length === 1));
        }

        function clearDropClasses() {
            treeEl.querySelectorAll('.drop-before, .drop-inside, .drop-after').forEach(row => {
                row.classList.remove('drop-before', 'drop-inside', 'drop-after');
            });
        }

        function getDropMode(event, row) {
            const rect = row.getBoundingClientRect();
            const y = event.clientY - rect.top;
            if (y < rect.height * 0.28) {
                return 'before';
            }
            if (y > rect.height * 0.72) {
                return 'after';
            }
            return 'inside';
        }

        function renderSelectedNode() {
            const node = selectedNode();
            if (!node) {
                titleEl.value = '';
                contentEl.value = '';
                previewEl.innerHTML = '';
                titleEl.disabled = true;
                contentEl.disabled = true;
                return;
            }
            const isTreeFile = isDirectoryFileNode(node);
            titleEl.disabled = false;
            contentEl.disabled = isTreeFile;
            titleEl.value = isTreeFile ? directoryFileName(node) : (node.title || '');
            contentEl.value = isTreeFile ? 'Virtual NxTree file. Click it in the tree to load the linked database tree.' : (node.contentMarkdown || '');
            contentEl.hidden = editorMode !== 'edit';
            previewEl.hidden = editorMode === 'edit';
            const preview = isTreeFile ? `Virtual NxTree file linked to database tree ${node.linkedTreeId}.` : (node.contentMarkdown || 'This node is stored in the NxTree database. Editing will use revisioned operations in the next milestone.');
            previewEl.innerHTML = renderMarkdown(preview);
        }

        function selectNode(id) {
            selectedNodeId = id;
            const node = selectedNode();
            if (isDirectoryTreeLoaded() && node && !isDirectoryFileNode(node)) {
                directoryTargetFolderId = node.id;
            }
            renderTree();
            renderSelectedNode();
        }

        function setEditorMode(mode) {
            editorMode = mode;
            editModeButton.textContent = mode === 'edit' ? 'Preview' : 'Edit';
            editModeButton.classList.toggle('active', mode === 'edit');
            editModeButton.setAttribute('aria-pressed', mode === 'edit' ? 'true' : 'false');
            renderSelectedNode();
        }

        function markDirty() {
            setSaveState('Saving...');
            if (saveTimer) {
                clearTimeout(saveTimer);
            }
            saveTimer = setTimeout(saveSelectedNode, 650);
        }

        function hasPendingLocalEdit() {
            return saveTimer !== null || isSaving || saveQueued;
        }

        function updateSelectedNodeFromEditor() {
            if (!currentTree || !Array.isArray(currentTree.nodes) || selectedNodeId === null) {
                return null;
            }
            const node = currentTree.nodes.find(item => String(item.id) === String(selectedNodeId));
            if (!node) {
                return null;
            }
            node.title = titleEl.value;
            node.contentMarkdown = contentEl.value;
            return node;
        }

        function scheduleSelectedNodeSave() {
            const node = updateSelectedNodeFromEditor();
            if (!node) {
                return;
            }
            if (isDirectoryFileNode(node)) {
                node.contentMarkdown = '';
                return;
            }
            renderTree();
            if (editorMode === 'preview') {
                previewEl.innerHTML = renderMarkdown(node.contentMarkdown || '');
            }
            markDirty();
        }

        function saveSelectedNode() {
            if (saveTimer) {
                clearTimeout(saveTimer);
                saveTimer = null;
            }
            if (!currentTree || selectedNodeId === null) {
                return;
            }
            if (isSaving) {
                saveQueued = true;
                return;
            }
            const node = updateSelectedNodeFromEditor();
            if (!node) {
                return;
            }
            if (isDirectoryFileNode(node)) {
                node.contentMarkdown = '';
            }
            const body = new URLSearchParams();
            body.set('title', node.title || 'Untitled node');
            body.set('contentMarkdown', node.contentMarkdown || '');
            body.set('baseRevision', String(currentTree.revision));
            isSaving = true;
            setSaveState('Saving...');
            fetch(endpoint('/nodes/' + encodeURIComponent(selectedNodeId)), {
                method: 'PUT',
                headers: requestHeaders(),
                body,
            })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not save node');
                    }
                    return data;
                }))
                .then(data => {
                    const previousSelection = selectedNodeId;
                    currentTree = data.tree;
                    selectedNodeId = previousSelection;
                    revisionEl.textContent = `Revision ${currentTree.revision}`;
                    refreshTreeSummary(currentTree);
                    renderTree();
                    setSaveState('Saved');
                    setStatus('Saved node');
                    runSearch();
                })
                .catch(error => {
                    setSaveState('Save failed');
                    setStatus(error.message);
                })
                .finally(() => {
                    isSaving = false;
                    if (saveQueued) {
                        saveQueued = false;
                        markDirty();
                    } else if (remoteChangePending) {
                        remoteChangePending = false;
                        pollTreeSync();
                    }
                });
        }

        function applyTreeResult(data, preferredSelection) {
            currentTree = data.tree;
            remoteChangePending = false;
            if (preferredSelection && findNode(preferredSelection)) {
                selectedNodeId = preferredSelection;
            } else if (!findNode(selectedNodeId)) {
                selectedNodeId = currentTree.rootNodeId;
            }
            revisionEl.textContent = `Revision ${currentTree.revision}`;
            refreshTreeSummary(currentTree);
            renderTree();
            renderSelectedNode();
            runSearch();
        }

        function pushUndoState() {
            if (!currentTree) {
                return;
            }
            updateSelectedNodeFromEditor();
            undoStack.push({
                tree: JSON.parse(JSON.stringify(currentTree)),
                selectedNodeId,
            });
            if (undoStack.length > 50) {
                undoStack.shift();
            }
        }

        function clearUndoState() {
            undoStack.length = 0;
        }

        function undoLastStructureChange() {
            if (!currentTree) {
                setStatus('No tree loaded');
                return;
            }

            const previous = undoStack[undoStack.length - 1];
            if (!previous) {
                setStatus('Nothing to undo');
                return;
            }

            const body = new URLSearchParams();
            body.set('baseRevision', String(currentTree.revision));
            body.set('snapshot', JSON.stringify(previous.tree));
            setStatus('Undoing last tree change...');
            fetch(endpoint('/trees/' + encodeURIComponent(currentTree.id) + '/restore'), {
                method: 'POST',
                headers: requestHeaders(),
                body,
            }).then(response => response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.error || 'Could not undo tree change');
                }
                return data;
            })).then(data => {
                undoStack.pop();
                applyTreeResult(data, previous.selectedNodeId);
                setStatus('Undid last tree change');
            }).catch(error => setStatus(error.message));
        }

        function startTreeSync() {
            if (syncTimer) {
                clearInterval(syncTimer);
            }
            syncTimer = window.setInterval(pollTreeSync, 5000);
        }

        function pollTreeSync() {
            if (!currentTree || isSyncing) {
                return;
            }
            const treeId = currentTree.id;
            const revision = currentTree.revision || 0;
            isSyncing = true;
            fetch(endpoint('/trees/' + encodeURIComponent(treeId) + '/sync?sinceRevision=' + encodeURIComponent(revision)), { headers: requestHeaders() })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not sync tree');
                    }
                    return data;
                }))
                .then(data => {
                    if (!currentTree || String(currentTree.id) !== String(treeId) || !data.changed) {
                        return;
                    }
                    if (hasPendingLocalEdit()) {
                        remoteChangePending = true;
                        setStatus('Tree changed elsewhere. Save or reload before continuing.');
                        return;
                    }
                    clearUndoState();
                    applyTreeResult(data, selectedNodeId);
                    setStatus('Loaded remote tree changes');
                })
                .catch(error => {
                    setStatus(error.message);
                })
                .finally(() => {
                    isSyncing = false;
                });
        }

        function postOperation(path, body) {
            if (!currentTree) {
                return Promise.reject(new Error('No tree loaded'));
            }
            body.set('baseRevision', String(currentTree.revision));
            setStatus('Updating tree...');
            return fetch(endpoint(path), {
                method: 'POST',
                headers: requestHeaders(),
                body,
            }).then(response => response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.error || 'Could not update tree');
                }
                return data;
            }));
        }

        function addNode() {
            const parent = selectedNode();
            if (!parent) {
                setStatus('Select a parent node first');
                return;
            }
            if (isDirectoryFileNode(parent)) {
                setStatus('Virtual files cannot contain folders');
                return;
            }
            pushUndoState();
            postOperation('/nodes/' + encodeURIComponent(parent.id) + '/children', new URLSearchParams())
                .then(data => {
                    const newNode = data.tree.nodes.reduce((latest, node) => latest === null || node.id > latest.id ? node : latest, null);
                    collapsedIds.delete(String(parent.id));
                    applyTreeResult(data, newNode ? newNode.id : parent.id);
                    setEditorMode('edit');
                    titleEl.focus();
                    titleEl.select();
                    setStatus('Added node');
                })
                .catch(error => {
                    undoStack.pop();
                    setStatus(error.message);
                });
        }

        function deleteNode() {
            const node = selectedNode();
            if (!node) {
                setStatus('Select a node to delete');
                return;
            }
            if (String(node.id) === String(currentTree.rootNodeId)) {
                setStatus('The root node cannot be deleted');
                return;
            }
            if ((node.children || []).length > 0 && !window.confirm('Delete this node and all child nodes?')) {
                return;
            }
            if (isDirectoryFileNode(node) && !window.confirm(`Remove ${node.title || 'this virtual file'}.nxtree from the library? The database tree will not be deleted.`)) {
                return;
            }
            pushUndoState();
            const nextSelection = node.parentId || currentTree.rootNodeId;
            postOperation('/nodes/' + encodeURIComponent(node.id) + '/delete', new URLSearchParams())
                .then(data => {
                    collapsedIds.delete(String(node.id));
                    applyTreeResult(data, nextSelection);
                    setStatus('Deleted node');
                })
                .catch(error => {
                    undoStack.pop();
                    setStatus(error.message);
                });
        }

        function sortChildren(direction) {
            const node = selectedNode();
            if (!node) {
                setStatus('Select a branch to sort');
                return;
            }
            const body = new URLSearchParams();
            body.set('direction', direction);
            pushUndoState();
            postOperation('/nodes/' + encodeURIComponent(node.id) + '/sort', body)
                .then(data => {
                    applyTreeResult(data, node.id);
                    setStatus(direction === 'desc' ? 'Sorted branch Z-A' : 'Sorted branch A-Z');
                })
                .catch(error => {
                    undoStack.pop();
                    setStatus(error.message);
                });
        }

        function moveNode(nodeId, targetId, mode) {
            const body = new URLSearchParams();
            body.set('targetId', String(targetId));
            body.set('mode', mode);
            pushUndoState();
            postOperation('/nodes/' + encodeURIComponent(nodeId) + '/move', body)
                .then(data => {
                    if (mode === 'inside') {
                        collapsedIds.delete(String(targetId));
                    }
                    applyTreeResult(data, nodeId);
                    setStatus('Moved node');
                })
                .catch(error => {
                    undoStack.pop();
                    setStatus(error.message);
                });
        }

        function expandSelectedBranch() {
            const node = selectedNode();
            if (!node) {
                setStatus('Select a branch to expand');
                return;
            }
            expandSubtree(node);
            renderTree();
            setStatus('Expanded branch');
        }

        function collapseSelectedBranch() {
            const node = selectedNode();
            if (!node) {
                setStatus('Select a branch to collapse');
                return;
            }
            collapseSubtree(node);
            renderTree();
            setStatus('Collapsed branch');
        }

        function loadTree(treeId) {
            setStatus('Loading tree...');
            return fetch(endpoint('/trees/' + encodeURIComponent(treeId)), { headers: requestHeaders() })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not load tree');
                    }
                    return data;
                }))
                .then(data => {
                    currentTree = data.tree;
                    if (isDirectoryTreeLoaded()) {
                        directoryTreeId = currentTree.id;
                        directoryTargetFolderId = currentTree.rootNodeId;
                    }
                    selectedNodeId = currentTree.rootNodeId;
                    clearUndoState();
                    collapsedIds.clear();
                    remoteChangePending = false;
                    revisionEl.textContent = `Revision ${currentTree.revision}`;
                    setSaveState('Saved');
                    renderTree();
                    setEditorMode('preview');
                    runSearch();
                    startTreeSync();
                    setStatus(`Loaded ${currentTree.title || 'Untitled tree'} (${currentTree.nodes.length} node(s)).`);
                })
                .catch(error => setStatus(error.message));
        }

        function loadTrees() {
            return fetch(endpoint('/trees'), { headers: requestHeaders() })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Could not load trees');
                    }
                    return response.json();
                })
                .then(data => {
                    trees = Array.isArray(data.trees) ? data.trees : [];
                    renderTreeList();
                    if (trees.length > 0 && selectedTreeId === null) {
                        selectedTreeId = trees[0].id;
                        renderTreeList();
                        return loadTree(trees[0].id);
                    }
                    if (trees.length === 0) {
                        renderTree();
                        setStatus('Create or import your first database-backed tree.');
                    }
                    return null;
                })
                .catch(error => setStatus(error.message));
        }

        function createTree() {
            const title = window.prompt('Tree title', 'Untitled tree');
            if (title === null) {
                return;
            }
            const body = new URLSearchParams();
            body.set('title', title);
            newTreeButton.disabled = true;
            setStatus('Creating tree...');
            fetch(endpoint('/trees'), { method: 'POST', headers: requestHeaders(), body })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not create tree');
                    }
                    return data;
                }))
                .then(data => {
                    trees.unshift(data.tree);
                    selectedTreeId = data.tree.id;
                    renderTreeList();
                    return loadTree(data.tree.id);
                })
                .catch(error => setStatus(error.message))
                .finally(() => {
                    newTreeButton.disabled = false;
                    fileMenu.hidden = true;
                });
        }

        function importTree(file) {
            if (!file) {
                return;
            }
            const body = new FormData();
            body.append('file', file);
            newTreeButton.disabled = true;
            setStatus(`Importing ${file.name}...`);
            fetch(endpoint('/import'), { method: 'POST', headers: requestHeaders(), body })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not import tree');
                    }
                    return data;
                }))
                .then(data => {
                    trees = trees.filter(tree => tree.id !== data.tree.id);
                    trees.unshift(data.tree);
                    selectedTreeId = data.tree.id;
                    renderTreeList();
                    currentTree = data.tree;
                    selectedNodeId = currentTree.rootNodeId;
                    collapsedIds.clear();
                    remoteChangePending = false;
                    revisionEl.textContent = `Revision ${currentTree.revision}`;
                    setSaveState('Saved');
                    renderTree();
                    setEditorMode('preview');
                    startTreeSync();
                    setStatus(`Imported ${data.tree.nodes.length} node(s) from ${file.name}.`);
                })
                .catch(error => setStatus(error.message))
                .finally(() => {
                    newTreeButton.disabled = false;
                    fileMenu.hidden = true;
                });
        }

        function importTreeFromFiles() {
            openFilesPanel('import', currentTree && currentTree.sourceFilePath ? parentPath(currentTree.sourceFilePath) : '/');
        }

        function importTreeFromFilesPath(path) {
            const body = new URLSearchParams();
            body.set('path', path);
            newTreeButton.disabled = true;
            importFilesButton.disabled = true;
            setStatus(`Importing ${path} from Nextcloud Files...`);
            fetch(endpoint('/import/files'), { method: 'POST', headers: requestHeaders(), body })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not import from Nextcloud Files');
                    }
                    return data;
                }))
                .then(data => {
                    trees = trees.filter(tree => tree.id !== data.tree.id);
                    trees.unshift(data.tree);
                    selectedTreeId = data.tree.id;
                    renderTreeList();
                    currentTree = data.tree;
                    selectedNodeId = currentTree.rootNodeId;
                    collapsedIds.clear();
                    remoteChangePending = false;
                    revisionEl.textContent = `Revision ${currentTree.revision}`;
                    setSaveState('Saved');
                    renderTree();
                    setEditorMode('preview');
                    startTreeSync();
                    setStatus(`Imported ${data.tree.nodes.length} node(s) from ${path}.`);
                })
                .catch(error => setStatus(error.message))
                .finally(() => {
                    newTreeButton.disabled = false;
                    importFilesButton.disabled = false;
                    fileMenu.hidden = true;
                    filesPanel.hidden = true;
                });
        }

        function exportMtre() {
            if (!currentTree) {
                setStatus('Open a tree before exporting');
                return;
            }
            const params = selectedNodeId === null ? '' : '?nodeId=' + encodeURIComponent(selectedNodeId);
            window.location.href = endpoint('/trees/' + encodeURIComponent(currentTree.id) + '/export/mtre' + params);
            fileMenu.hidden = true;
            setStatus('Exporting selected branch as .mtre...');
        }

        function parentPath(path) {
            const normalized = String(path || '').replace(/\\/g, '/').replace(/\/+/g, '/');
            const parts = normalized.split('/').filter(Boolean);
            parts.pop();
            return '/' + parts.join('/');
        }

        function defaultExportFolder() {
            if (!currentTree) {
                return '/NxTree';
            }
            if (currentTree.libraryPath) {
                return currentTree.libraryPath;
            }
            if (currentTree.lastExportFolderPath) {
                return currentTree.lastExportFolderPath;
            }
            if (currentTree.sourceFilePath) {
                return parentPath(currentTree.sourceFilePath);
            }
            return '/NxTree';
        }

        function openTreeLibrary() {
            if (currentTree && !isDirectoryTreeLoaded()) {
                previousTreeId = currentTree.id;
                updateBackButton();
            }
            setStatus('Loading NxTree Library...');
            fetch(endpoint('/directory'), {
                method: 'POST',
                headers: requestHeaders(),
                body: new URLSearchParams(),
            })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not load NxTree Library');
                    }
                    return data;
                }))
                .then(data => {
                    currentTree = data.tree;
                    directoryTreeId = currentTree.id;
                    selectedTreeId = currentTree.id;
                    selectedNodeId = currentTree.rootNodeId;
                    directoryTargetFolderId = currentTree.rootNodeId;
                    collapsedIds.clear();
                    remoteChangePending = false;
                    revisionEl.textContent = `Revision ${currentTree.revision}`;
                    setSaveState('Saved');
                    renderTreeList();
                    renderTree();
                    setEditorMode('preview');
                    startTreeSync();
                    fileMenu.hidden = true;
                    setStatus('Loaded NxTree Library. Select a folder as the Save target or click a .nxtree file to open it.');
                })
                .catch(error => setStatus(error.message));
        }

        function returnToPreviousTree() {
            if (previousTreeId === null) {
                setStatus('No previous tree to return to');
                return;
            }
            const treeId = previousTreeId;
            previousTreeId = null;
            updateBackButton();
            fileMenu.hidden = true;
            setStatus('Returning to previous tree...');
            loadTree(treeId);
        }

        function saveTreeToLibrary() {
            if (!currentTree) {
                setStatus('Open a tree before saving');
                return;
            }
            if (isDirectoryTreeLoaded()) {
                setStatus('The NxTree Library saves itself automatically');
                return;
            }
            const body = new URLSearchParams();
            body.set('libraryName', treeLibraryName(currentTree));
            if (directoryTargetFolderId !== null) {
                body.set('folderNodeId', String(directoryTargetFolderId));
            }
            saveTreeButton.disabled = true;
            setStatus(`Saving ${treeLibraryName(currentTree)}.nxtree to NxTree Library...`);
            fetch(endpoint('/trees/' + encodeURIComponent(currentTree.id) + '/directory'), {
                method: 'POST',
                headers: requestHeaders(),
                body,
            })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not save tree');
                    }
                    return data;
                }))
                .then(data => {
                    directoryTreeId = data.tree.id;
                    renderTreeList();
                    setStatus(`Saved ${treeLibraryName(currentTree)}.nxtree to NxTree Library.`);
                })
                .catch(error => setStatus(error.message))
                .finally(() => {
                    saveTreeButton.disabled = false;
                });
        }

        function exportMtreToFiles() {
            if (!currentTree) {
                setStatus('Open a tree before exporting');
                return;
            }
            openFilesPanel('export', defaultExportFolder());
        }

        function suggestedExportFilename() {
            const node = selectedNode();
            return ((node && node.title) || currentTree.title || 'nxtree').replace(/[^A-Za-z0-9._ -]+/g, '-') + '.mtre';
        }

        function exportMtreToFilesPath(folderPath, filename) {
            const body = new URLSearchParams();
            body.set('folderPath', folderPath);
            body.set('filename', filename);
            if (selectedNodeId !== null) {
                body.set('nodeId', String(selectedNodeId));
            }
            exportFilesButton.disabled = true;
            setStatus(`Saving selected branch to ${folderPath}...`);
            fetch(endpoint('/trees/' + encodeURIComponent(currentTree.id) + '/export/files'), {
                method: 'POST',
                headers: requestHeaders(),
                body,
            })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not export to Nextcloud Files');
                    }
                    return data;
                }))
                .then(data => {
                    if (data.tree) {
                        currentTree = data.tree;
                        refreshTreeSummary(currentTree);
                    }
                    fileMenu.hidden = true;
                    filesPanel.hidden = true;
                    setStatus(`Saved selected branch to ${data.path}.`);
                })
                .catch(error => setStatus(error.message))
                .finally(() => {
                    exportFilesButton.disabled = false;
                });
        }

        function openFilesPanel(mode, path) {
            filesMode = mode;
            filesTitle.textContent = mode === 'export' ? 'Export To Nextcloud Files' : 'Import From Nextcloud Files';
            filesExportFields.hidden = mode !== 'export';
            filesFilenameLabel.hidden = false;
            filesSave.textContent = 'Save Here';
            if (mode === 'export') {
                filesFilename.value = suggestedExportFilename();
            }
            if (mode !== 'export') {
                filesFilename.value = '';
            }
            filesPanel.hidden = false;
            browseFiles(path || '/', mode === 'export');
        }

        function browseFiles(path, createFolder) {
            setStatus('Loading Nextcloud Files...');
            const query = '?path=' + encodeURIComponent(path || '/') + (createFolder ? '&createFolder=1' : '');
            fetch(endpoint('/files/browse' + query), { headers: requestHeaders() })
                .then(response => response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not browse Nextcloud Files');
                    }
                    return data;
                }))
                .then(data => {
                    filesCurrentPath = data.path || '/';
                    filesParentPath = data.parent || null;
                    renderFilesList(Array.isArray(data.entries) ? data.entries : []);
                    setStatus(filesMode === 'export' ? `Choose export folder: ${filesCurrentPath}` : `Choose .mtre file from ${filesCurrentPath}`);
                })
                .catch(error => setStatus(error.message));
        }

        function renderFilesList(entries) {
            filesPathEl.textContent = filesCurrentPath;
            filesUp.disabled = filesParentPath === null;
            filesList.textContent = '';
            const visibleEntries = filesMode === 'import' ? entries : entries.filter(entry => entry.type === 'folder');
            if (visibleEntries.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'nxtree-empty';
                empty.textContent = filesMode === 'import' ? 'No folders or .mtre files here.' : 'No subfolders here.';
                filesList.appendChild(empty);
                return;
            }
            visibleEntries.forEach(entry => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `nxtree-files-entry ${entry.type === 'folder' ? 'folder' : 'file'}`;
                button.textContent = `${entry.type === 'folder' ? 'Folder: ' : 'File: '}${entry.name}`;
                button.addEventListener('click', () => {
                    if (entry.type === 'folder') {
                        browseFiles(entry.path, false);
                    } else if (filesMode === 'import') {
                        importTreeFromFilesPath(entry.path);
                    }
                });
                filesList.appendChild(button);
            });
        }

        function runSearch() {
            const query = searchInput.value.trim();
            searchResults.textContent = '';
            if (!query || !currentTree || !Array.isArray(currentTree.nodes)) {
                return;
            }
            const options = {
                titles: searchTitle.checked,
                content: searchContent.checked,
                caseSensitive: searchCase.checked,
                regex: searchRegex.checked,
            };
            currentTree.nodes.forEach(node => {
                const matches = [];
                if (options.titles && textMatches(node.title || '', query, options.caseSensitive, options.regex)) {
                    matches.push('title');
                }
                if (options.content && textMatches(node.contentMarkdown || '', query, options.caseSensitive, options.regex)) {
                    matches.push('content');
                }
                if (matches.length === 0) {
                    return;
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'nxtree-search-result';
                button.textContent = `${(findPath(node.id) || [node.title || 'Untitled node']).join(' / ')} (${matches.join(', ')})`;
                button.addEventListener('click', () => {
                    let current = findNode(node.id);
                    while (current && current.parentId !== null) {
                        collapsedIds.delete(String(current.parentId));
                        current = findNode(current.parentId);
                    }
                    selectNode(node.id);
                    searchPanel.hidden = true;
                });
                searchResults.appendChild(button);
            });
        }

        function textMatches(haystack, query, caseSensitive, regex) {
            if (!regex) {
                return caseSensitive ? haystack.includes(query) : haystack.toLowerCase().includes(query.toLowerCase());
            }
            try {
                return new RegExp(query, caseSensitive ? '' : 'i').test(haystack);
            } catch (error) {
                return false;
            }
        }

        fileToggle.addEventListener('click', () => {
            fileMenu.hidden = !fileMenu.hidden;
        });
        newTreeButton.addEventListener('click', createTree);
        loadTreeButton.addEventListener('click', openTreeLibrary);
        backTreeButton.addEventListener('click', returnToPreviousTree);
        saveTreeButton.addEventListener('click', saveTreeToLibrary);
        importFilesButton.addEventListener('click', importTreeFromFiles);
        exportFilesButton.addEventListener('click', exportMtreToFiles);
        editModeButton.addEventListener('click', () => setEditorMode(editorMode === 'edit' ? 'preview' : 'edit'));
        titleEl.addEventListener('input', scheduleSelectedNodeSave);
        contentEl.addEventListener('input', scheduleSelectedNodeSave);
        addNodeButton.addEventListener('click', addNode);
        deleteNodeButton.addEventListener('click', deleteNode);
        sortAscButton.addEventListener('click', () => sortChildren('asc'));
        sortDescButton.addEventListener('click', () => sortChildren('desc'));
        expandButton.addEventListener('click', expandSelectedBranch);
        collapseButton.addEventListener('click', collapseSelectedBranch);
        undoButton.addEventListener('click', undoLastStructureChange);
        searchToggle.addEventListener('click', () => {
            searchPanel.hidden = !searchPanel.hidden;
            if (!searchPanel.hidden) {
                searchInput.focus();
                runSearch();
            }
        });
        searchClose.addEventListener('click', () => {
            searchPanel.hidden = true;
        });
        filesClose.addEventListener('click', () => {
            filesPanel.hidden = true;
        });
        filesUp.addEventListener('click', () => {
            if (filesParentPath !== null) {
                browseFiles(filesParentPath, false);
            }
        });
        filesSave.addEventListener('click', () => {
            exportMtreToFilesPath(filesCurrentPath, filesFilename.value || suggestedExportFilename());
        });
        searchInput.addEventListener('input', runSearch);
        [searchTitle, searchContent, searchCase, searchRegex].forEach(input => {
            input.addEventListener('change', runSearch);
        });

        initDivider();
        makePanelDraggable(searchPanel, searchPanelHeader);
        makePanelDraggable(filesPanel, filesPanelHeader);
        loadTrees();
        updateBackButton();
        app.dataset.ready = 'true';
    });
})();
