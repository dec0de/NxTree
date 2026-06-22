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
            <button type="button" id="nxtree-import-files">Import from Files</button>
            <button type="button" id="nxtree-export-files" title="Save the selected branch to Nextcloud Files">Export to Files</button>
            <label class="button" for="nxtree-import-file">Upload .mtre</label>
            <input id="nxtree-import-file" type="file" accept=".mtre,application/json" />
            <button type="button" id="nxtree-export-mtre" title="Download the selected branch and its children">Download .mtre</button>
            <small>Files export defaults to imported folder or /NxTree</small>
            <div class="nxtree-tree-list-panel">
                <strong>Trees</strong>
                <p id="nxtree-tree-empty" class="nxtree-empty">No trees yet.</p>
                <nav id="nxtree-tree-list" class="nxtree-tree-list" aria-label="NxTree trees"></nav>
            </div>
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
            <div class="nxtree-search-options">
                <label><input id="nxtree-search-title" type="checkbox" checked /> Titles</label>
                <label><input id="nxtree-search-content" type="checkbox" checked /> Content</label>
                <label><input id="nxtree-search-case" type="checkbox" /> Match case</label>
                <label><input id="nxtree-search-regex" type="checkbox" /> Regex</label>
            </div>
            <div id="nxtree-search-results" class="nxtree-search-results"></div>
        </div>
    </section>
    <section id="nxtree-files-panel" class="nxtree-files-panel" hidden>
        <header class="nxtree-files-panel-header">
            <strong id="nxtree-files-title">Nextcloud Files</strong>
            <button type="button" id="nxtree-files-close" aria-label="Close files browser">Close</button>
        </header>
        <div class="nxtree-files-browser">
            <div class="nxtree-files-path-row">
                <button type="button" id="nxtree-files-up">Up</button>
                <span id="nxtree-files-path">/</span>
            </div>
            <div id="nxtree-files-list" class="nxtree-files-list"></div>
            <div id="nxtree-files-export-fields" class="nxtree-files-export-fields" hidden>
                <label>Filename <input id="nxtree-files-filename" type="text" /></label>
                <button type="button" id="nxtree-files-save">Save Here</button>
            </div>
        </div>
    </section>
</div>
