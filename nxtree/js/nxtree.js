(function () {
    'use strict';

    function endpoint(path) {
        if (window.OC && typeof window.OC.generateUrl === 'function') {
            return window.OC.generateUrl('/apps/nxtree' + path);
        }

        return '/apps/nxtree' + path;
    }

    function requestHeaders() {
        var headers = {
            'Accept': 'application/json'
        };

        if (window.OC && window.OC.requestToken) {
            headers.requesttoken = window.OC.requestToken;
        }

        return headers;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('nxtree-app');
        if (!app) {
            return;
        }

        var newTreeButton = document.getElementById('nxtree-new-tree');
        var importFile = document.getElementById('nxtree-import-file');
        var treeList = document.getElementById('nxtree-tree-list');
        var nodeList = document.getElementById('nxtree-node-list');
        var emptyState = document.getElementById('nxtree-tree-empty');
        var currentTitle = document.getElementById('nxtree-current-title');
        var status = document.getElementById('nxtree-status');
        var editor = document.getElementById('nxtree-editor');
        var nodeTitle = document.getElementById('nxtree-node-title');
        var nodeContent = document.getElementById('nxtree-node-content');
        var nodePreview = document.getElementById('nxtree-node-preview');
        var editModeButton = document.getElementById('nxtree-edit-mode');
        var revision = document.getElementById('nxtree-revision');
        var trees = [];
        var selectedTreeId = null;
        var currentTree = null;
        var selectedNodeId = null;
        var editorMode = 'preview';

        function setStatus(message) {
            if (status) {
                status.textContent = message;
            }
        }

        function renderTrees() {
            treeList.textContent = '';
            emptyState.hidden = trees.length > 0;

            trees.forEach(function (tree) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'nxtree-tree-item';
                button.classList.toggle('active', tree.id === selectedTreeId);
                button.textContent = tree.title || 'Untitled tree';
                button.title = 'Open tree';
                button.addEventListener('click', function () {
                    selectedTreeId = tree.id;
                    renderTrees();
                    loadTree(tree.id);
                });
                treeList.appendChild(button);
            });
        }

        function markdownToHtml(markdown) {
            var escaped = String(markdown || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>');

            return escaped.split(/\n{2,}/).map(function (paragraph) {
                return '<p>' + paragraph.replace(/\n/g, '<br>') + '</p>';
            }).join('');
        }

        function renderNodes() {
            nodeList.textContent = '';
            if (!currentTree || !Array.isArray(currentTree.nodes) || currentTree.nodes.length === 0) {
                nodeList.textContent = 'No nodes loaded.';
                return;
            }

            var byParent = {};
            currentTree.nodes.forEach(function (node) {
                var key = node.parentId === null ? 'root' : String(node.parentId);
                if (!byParent[key]) {
                    byParent[key] = [];
                }
                byParent[key].push(node);
            });

            function addNode(node, depth) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'nxtree-node-item';
                button.style.paddingLeft = String(28 + depth * 18) + 'px';
                button.style.setProperty('--nxtree-line-left', String(12 + depth * 18) + 'px');
                button.classList.toggle('active', node.id === selectedNodeId);
                button.textContent = node.title || 'Untitled node';
                button.addEventListener('click', function () {
                    selectedNodeId = node.id;
                    renderNodes();
                    renderSelectedNode();
                });
                nodeList.appendChild(button);

                (byParent[String(node.id)] || []).forEach(function (child) {
                    addNode(child, depth + 1);
                });
            }

            (byParent.root || []).forEach(function (node) {
                addNode(node, 0);
            });
        }

        function selectedNode() {
            if (!currentTree || !Array.isArray(currentTree.nodes)) {
                return null;
            }

            return currentTree.nodes.find(function (node) {
                return node.id === selectedNodeId;
            }) || null;
        }

        function renderSelectedNode() {
            var node = selectedNode();
            editor.hidden = !node;
            if (!node) {
                return;
            }

            nodeTitle.value = node.title || '';
            nodeContent.value = node.contentMarkdown || '';
            nodeContent.hidden = editorMode !== 'edit';
            nodePreview.hidden = editorMode === 'edit';
            nodePreview.innerHTML = markdownToHtml(node.contentMarkdown || 'This root node is stored in the NxTree database. Node editing is the next milestone.');
        }

        function setEditorMode(mode) {
            editorMode = mode;
            editModeButton.textContent = mode === 'edit' ? 'Preview' : 'Edit';
            editModeButton.setAttribute('aria-pressed', mode === 'edit' ? 'true' : 'false');
            renderSelectedNode();
        }

        function loadTree(treeId) {
            setStatus('Loading tree...');
            return fetch(endpoint('/trees/' + encodeURIComponent(treeId)), {
                headers: requestHeaders()
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not load tree');
                    }

                    return data;
                });
            }).then(function (data) {
                currentTree = data.tree;
                selectedNodeId = currentTree.rootNodeId;
                currentTitle.textContent = currentTree.title || 'Untitled tree';
                revision.textContent = 'Revision ' + currentTree.revision;
                renderNodes();
                setEditorMode('preview');
                setStatus('Loaded database tree revision ' + currentTree.revision + '.');
            }).catch(function (error) {
                setStatus(error.message);
            });
        }

        function loadTrees() {
            return fetch(endpoint('/trees'), {
                headers: requestHeaders()
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not load trees');
                }

                return response.json();
            }).then(function (data) {
                trees = Array.isArray(data.trees) ? data.trees : [];
                if (trees.length > 0 && selectedTreeId === null) {
                    selectedTreeId = trees[0].id;
                    loadTree(trees[0].id);
                }
                renderTrees();
                setStatus(trees.length > 0 ? 'Loaded ' + trees.length + ' tree(s).' : 'Create your first database-backed tree.');
            }).catch(function (error) {
                setStatus(error.message);
            });
        }

        function createTree() {
            var title = window.prompt('Tree title', 'Untitled tree');
            if (title === null) {
                return;
            }

            var body = new URLSearchParams();
            body.set('title', title);
            newTreeButton.disabled = true;
            setStatus('Creating tree...');

            fetch(endpoint('/trees'), {
                method: 'POST',
                headers: requestHeaders(),
                body: body
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not create tree');
                    }

                    return data;
                });
            }).then(function (data) {
                trees.unshift(data.tree);
                selectedTreeId = data.tree.id;
                renderTrees();
                loadTree(data.tree.id);
            }).catch(function (error) {
                setStatus(error.message);
            }).finally(function () {
                newTreeButton.disabled = false;
            });
        }

        function importTree(file) {
            if (!file) {
                return;
            }

            var body = new FormData();
            body.append('file', file);
            newTreeButton.disabled = true;
            importFile.disabled = true;
            setStatus('Importing ' + file.name + '...');

            fetch(endpoint('/import'), {
                method: 'POST',
                headers: requestHeaders(),
                body: body
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || 'Could not import tree');
                    }

                    return data;
                });
            }).then(function (data) {
                trees = trees.filter(function (tree) {
                    return tree.id !== data.tree.id;
                });
                trees.unshift(data.tree);
                selectedTreeId = data.tree.id;
                currentTree = data.tree;
                selectedNodeId = currentTree.rootNodeId;
                currentTitle.textContent = currentTree.title || 'Imported tree';
                revision.textContent = 'Revision ' + currentTree.revision;
                renderTrees();
                renderNodes();
                setEditorMode('preview');
                setStatus('Imported ' + data.tree.nodes.length + ' node(s) from ' + file.name + '.');
            }).catch(function (error) {
                setStatus(error.message);
            }).finally(function () {
                newTreeButton.disabled = false;
                importFile.disabled = false;
                importFile.value = '';
            });
        }

        newTreeButton.addEventListener('click', createTree);
        importFile.addEventListener('change', function () {
            importTree(importFile.files && importFile.files.length > 0 ? importFile.files[0] : null);
        });
        editModeButton.addEventListener('click', function () {
            setEditorMode(editorMode === 'edit' ? 'preview' : 'edit');
        });
        loadTrees();
        app.dataset.ready = 'true';
    });
})();
