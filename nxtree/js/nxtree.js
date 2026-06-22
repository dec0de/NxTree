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
        const importFile = document.getElementById('nxtree-import-file');
        const treeList = document.getElementById('nxtree-tree-list');
        const treeEmpty = document.getElementById('nxtree-tree-empty');
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
        const collapsedIds = new Set();

        function setStatus(message) {
            statusEl.textContent = message;
        }

        function setSaveState(message) {
            saveStateEl.textContent = message;
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

        function markdownToHtml(markdown) {
            const escaped = String(markdown || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/^### (.*)$/gm, '<h3>$1</h3>')
                .replace(/^## (.*)$/gm, '<h2>$1</h2>')
                .replace(/^# (.*)$/gm, '<h1>$1</h1>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>');

            return escaped.split(/\n{2,}/).map(paragraph => {
                if (/^<h[1-3]>/.test(paragraph)) {
                    return paragraph;
                }
                return `<p>${paragraph.replace(/\n/g, '<br>')}</p>`;
            }).join('');
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
            treeList.textContent = '';
            treeEmpty.hidden = trees.length > 0;
            trees.forEach(tree => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'nxtree-tree-item';
                button.classList.toggle('active', tree.id === selectedTreeId);
                button.textContent = tree.title || 'Untitled tree';
                button.addEventListener('click', () => {
                    selectedTreeId = tree.id;
                    renderTreeList();
                    loadTree(tree.id);
                });
                treeList.appendChild(button);
            });
        }

        function refreshTreeSummary(tree) {
            const existing = trees.find(item => item.id === tree.id);
            if (existing) {
                existing.title = tree.title;
                existing.revision = tree.revision;
                existing.updatedAt = tree.updatedAt;
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
                button.textContent = node.title || 'Untitled node';
                button.classList.toggle('active', String(node.id) === String(selectedNodeId));
                button.addEventListener('click', () => selectNode(node.id));
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
            titleEl.disabled = false;
            contentEl.disabled = false;
            titleEl.value = node.title || '';
            contentEl.value = node.contentMarkdown || '';
            contentEl.hidden = editorMode !== 'edit';
            previewEl.hidden = editorMode === 'edit';
            const preview = node.contentMarkdown || 'This node is stored in the NxTree database. Editing will use revisioned operations in the next milestone.';
            previewEl.innerHTML = markdownToHtml(preview);
        }

        function selectNode(id) {
            selectedNodeId = id;
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
            renderTree();
            if (editorMode === 'preview') {
                previewEl.innerHTML = markdownToHtml(node.contentMarkdown || '');
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
                .catch(error => setStatus(error.message));
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
            const nextSelection = node.parentId || currentTree.rootNodeId;
            postOperation('/nodes/' + encodeURIComponent(node.id) + '/delete', new URLSearchParams())
                .then(data => {
                    collapsedIds.delete(String(node.id));
                    applyTreeResult(data, nextSelection);
                    setStatus('Deleted node');
                })
                .catch(error => setStatus(error.message));
        }

        function sortChildren(direction) {
            const node = selectedNode();
            if (!node) {
                setStatus('Select a branch to sort');
                return;
            }
            const body = new URLSearchParams();
            body.set('direction', direction);
            postOperation('/nodes/' + encodeURIComponent(node.id) + '/sort', body)
                .then(data => {
                    applyTreeResult(data, node.id);
                    setStatus(direction === 'desc' ? 'Sorted branch Z-A' : 'Sorted branch A-Z');
                })
                .catch(error => setStatus(error.message));
        }

        function moveNode(nodeId, targetId, mode) {
            const body = new URLSearchParams();
            body.set('targetId', String(targetId));
            body.set('mode', mode);
            postOperation('/nodes/' + encodeURIComponent(nodeId) + '/move', body)
                .then(data => {
                    if (mode === 'inside') {
                        collapsedIds.delete(String(targetId));
                    }
                    applyTreeResult(data, nodeId);
                    setStatus('Moved node');
                })
                .catch(error => setStatus(error.message));
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
                    selectedNodeId = currentTree.rootNodeId;
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
            importFile.disabled = true;
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
                    importFile.disabled = false;
                    importFile.value = '';
                    fileMenu.hidden = true;
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
        importFile.addEventListener('change', () => importTree(importFile.files && importFile.files.length > 0 ? importFile.files[0] : null));
        editModeButton.addEventListener('click', () => setEditorMode(editorMode === 'edit' ? 'preview' : 'edit'));
        titleEl.addEventListener('input', scheduleSelectedNodeSave);
        contentEl.addEventListener('input', scheduleSelectedNodeSave);
        addNodeButton.addEventListener('click', addNode);
        deleteNodeButton.addEventListener('click', deleteNode);
        sortAscButton.addEventListener('click', () => sortChildren('asc'));
        sortDescButton.addEventListener('click', () => sortChildren('desc'));
        expandButton.addEventListener('click', expandSelectedBranch);
        collapseButton.addEventListener('click', collapseSelectedBranch);
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
        searchInput.addEventListener('input', runSearch);
        [searchTitle, searchContent, searchCase, searchRegex].forEach(input => {
            input.addEventListener('change', runSearch);
        });

        initDivider();
        makePanelDraggable(searchPanel, searchPanelHeader);
        loadTrees();
        app.dataset.ready = 'true';
    });
})();
