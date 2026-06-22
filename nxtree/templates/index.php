<?php /** @var array $_ */ ?>

<div id="nxtree-app">
    <aside class="nxtree-sidebar">
        <header class="nxtree-header">
            <h2>NxTree</h2>
            <button type="button" id="nxtree-file-toggle">File</button>
            <button type="button" id="nxtree-search-toggle">Search</button>
        </header>
        <div id="nxtree-file-menu" class="nxtree-file-actions" hidden>
            <button type="button" id="nxtree-new-tree">Create New Tree</button>
            <label class="button" for="nxtree-import-file">Import .mtre</label>
            <input id="nxtree-import-file" type="file" accept=".mtre,application/json" />
        </div>
        <div class="nxtree-tree-list-panel">
            <strong>Trees</strong>
            <p id="nxtree-tree-empty" class="nxtree-empty">No trees yet.</p>
            <nav id="nxtree-tree-list" class="nxtree-tree-list" aria-label="NxTree trees"></nav>
        </div>
        <div class="nxtree-tree-actions" aria-label="Tree actions">
            <button type="button" id="nxtree-add-node" title="New child node">+ Node</button>
            <button type="button" id="nxtree-delete-node">Delete</button>
            <button type="button" id="nxtree-sort-asc">Sort A-Z</button>
            <button type="button" id="nxtree-sort-desc">Sort Z-A</button>
            <button type="button" id="nxtree-expand-branch" title="Expand selected branch">Expand</button>
            <button type="button" id="nxtree-collapse-branch" title="Collapse selected branch">Collapse</button>
        </div>
        <nav id="nxtree-tree" class="nxtree-tree" aria-label="NxTree document tree"></nav>
    </aside>
    <div id="nxtree-divider" class="nxtree-divider" role="separator" aria-orientation="vertical" aria-label="Resize tree panel"></div>
    <main class="nxtree-editor">
        <div class="nxtree-editor-toolbar">
            <input id="nxtree-node-title" type="text" disabled placeholder="Node title" />
            <button type="button" id="nxtree-edit-mode" class="nxtree-mode-button" aria-pressed="false" title="Switch to edit mode">Edit</button>
            <span id="nxtree-save-state" class="nxtree-save-state">Saved</span>
            <span id="nxtree-revision" class="nxtree-revision"></span>
        </div>
        <textarea id="nxtree-node-content" disabled spellcheck="true" placeholder="Write Markdown content here"></textarea>
        <article id="nxtree-node-preview" class="nxtree-markdown-preview" hidden></article>
        <p id="nxtree-status" role="status">Loading trees...</p>
    </main>
    <section id="nxtree-search-panel" class="nxtree-search-panel" hidden>
        <header class="nxtree-search-panel-header">
            <strong>Search</strong>
            <button type="button" id="nxtree-search-close" aria-label="Close search">Close</button>
        </header>
        <div class="nxtree-search">
            <input id="nxtree-search-input" type="search" placeholder="Search loaded tree" />
            <div id="nxtree-search-results" class="nxtree-search-results"></div>
        </div>
    </section>
</div>
