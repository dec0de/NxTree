<?php /** @var array $_ */ ?>

<div id="nxtree-app" class="nxtree-app">
    <aside class="nxtree-sidebar" aria-label="NxTree navigation">
        <div class="nxtree-brand">
            <span class="nxtree-logo" aria-hidden="true"></span>
            <div>
                <h2>NxTree</h2>
                <p>Collaborative tree notes</p>
            </div>
        </div>

        <button id="nxtree-new-tree" class="nxtree-primary" type="button">New tree</button>
        <label class="nxtree-import-button" for="nxtree-import-file">Import .mtre</label>
        <input id="nxtree-import-file" class="nxtree-file-input" type="file" accept=".mtre,application/json" />

        <div class="nxtree-tree-actions" aria-label="Tree actions">
            <button type="button" id="nxtree-add-node" title="New child node" disabled>+ Node</button>
            <button type="button" id="nxtree-delete-node" disabled>Delete</button>
            <button type="button" id="nxtree-sort-asc" disabled>Sort A-Z</button>
            <button type="button" id="nxtree-sort-desc" disabled>Sort Z-A</button>
            <button type="button" id="nxtree-expand-branch" title="Expand selected branch" disabled>Expand</button>
            <button type="button" id="nxtree-collapse-branch" title="Collapse selected branch" disabled>Collapse</button>
        </div>

        <div class="nxtree-panel nxtree-tree-panel">
            <h3>Your trees</h3>
            <p id="nxtree-tree-empty" class="nxtree-empty">No trees yet.</p>
            <nav id="nxtree-tree-list" class="nxtree-tree-list" aria-label="NxTree trees"></nav>
        </div>

        <div class="nxtree-panel nxtree-node-panel">
            <h3>Current tree</h3>
            <nav id="nxtree-node-list" class="nxtree-node-list" aria-label="NxTree nodes"></nav>
        </div>
    </aside>

    <main class="nxtree-main">
        <section class="nxtree-hero" id="nxtree-welcome">
            <p class="nxtree-kicker">Database-backed trees</p>
            <h1 id="nxtree-current-title">Create or select a tree</h1>
            <p>
                NxTree now creates live tree records in the Nextcloud database. Each new tree starts with
                a root node and a revisioned create operation, avoiding whole-file autosave from day one.
            </p>
            <p id="nxtree-status" class="nxtree-status" role="status">Loading trees...</p>
        </section>

        <section class="nxtree-editor" id="nxtree-editor" hidden>
            <div class="nxtree-editor-toolbar">
                <input id="nxtree-node-title" type="text" disabled placeholder="Node title" />
                <button type="button" id="nxtree-edit-mode" aria-pressed="false">Edit</button>
                <span id="nxtree-revision" class="nxtree-revision"></span>
            </div>
            <textarea id="nxtree-node-content" disabled placeholder="Markdown editing lands in the next milestone."></textarea>
            <article id="nxtree-node-preview" class="nxtree-node-preview"></article>
        </section>

        <section class="nxtree-cards" aria-label="NxTree principles">
            <article>
                <h2>No silent overwrites</h2>
                <p>Every mutation will be applied against a known tree revision.</p>
            </article>
            <article>
                <h2>Markdown nodes</h2>
                <p>Tree nodes will contain Markdown content with preview/edit modes.</p>
            </article>
            <article>
                <h2>Selected branch controls</h2>
                <p>Tree actions will follow MeeTree's selected-branch behavior as node editing lands.</p>
            </article>
        </section>
    </main>
</div>
