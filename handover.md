# NxTree Handover Prompt

Use this as the starter context for a fresh opencode session working on NxTree.

## Project Identity

We are building **NxTree**, a separate Nextcloud app and GitHub repository from MeeTree.

- Local path: `/Users/theo/git/nextcloud/NxTree`
- GitHub repo: `https://github.com/dec0de/NxTree`
- App id: `nxtree`
- Visible name: `NxTree`
- PHP namespace: `OCA\NxTree`
- License: `AGPL-3.0-or-later`
- Current repo status: only project seed files exist (`README.md`, `.gitignore`, `LICENSE`, this handover file)

NxTree is not installable yet. The first development milestone is an installable Nextcloud app skeleton.

## Relationship To MeeTree

MeeTree is the existing stable/simple app:

- Local path: `/Users/theo/git/nextcloud/TreeView`
- GitHub repo: `https://github.com/dec0de/MeeTree`
- App id: `meetree`
- Visible name: `MeeTree`
- Live storage: `.mtre` files in Nextcloud Files
- Current purpose: file-based single-user/tree-note editor with import/export

NxTree should reuse ideas and code from MeeTree where useful, but it must not inherit MeeTree's whole-file autosave architecture.

Do not modify MeeTree from the NxTree session unless explicitly asked. If copying/adapting code from MeeTree, read it as reference and apply changes only inside NxTree.

## Mission

Build a true multiuser tree-note app for Nextcloud that feels like a classic TreePad/Jreepad-style outliner but uses a collaboration-safe architecture.

NxTree should make it safe for multiple people to view and edit the same tree without silent overwrites.

The first collaboration target is safe multiuser editing with visible synchronization, not Google Docs-style character-by-character text collaboration on day one.

## Core Product Principles

- Database-backed live storage.
- `.mtre` is import/export only, not live storage.
- No single shared JSON file as the source of truth.
- No last-save-wins behavior.
- All mutations go through revisioned operations.
- Polling-based sync first; real-time push can come later.
- Node content is Markdown.
- Start with safe per-operation conflict handling.
- Add presence and soft locks before considering CRDT text editing.
- Keep MeeTree stable and simple; NxTree is the collaborative app.

## Important Lessons From MeeTree

MeeTree implemented many useful UI and format ideas:

- Split tree/editor layout.
- Draggable tree/editor divider.
- Compact tree action toolbar.
- Drag/drop nodes before, after, or inside other nodes.
- Undo for tree structure edits.
- Expand/collapse branch state.
- Whole-tree Expand/Collapse actions.
- Markdown node content with preview by default and a toggleable `Edit` button.
- Search in titles/content.
- HJT and CTD import/export codecs.
- `.mtre` native JSON format for portable file-based trees.
- Standalone browser preview pattern.

MeeTree also revealed what not to use for NxTree:

- Do not use whole-document autosave as the live collaboration model.
- Do not store live state in one `.mtre` file.
- Do not rely on Nextcloud file locking as collaboration.
- Do not use `/MeeTree/state.json`-style visible state files.
- Do not accept last-save-wins overwrites.

## Architecture Decision

Use the **Nextcloud database** as the live source of truth.

Rejected alternatives:

- Recursive directory subtree per tree: attractive but risky because huge directories, non-atomic branch moves, ordering problems, sync conflicts, and crash recovery are hard.
- One `.mtre` file per tree as live storage: simple but unsafe for multiuser; whole-file overwrites cause data loss.
- Collabora/Nextcloud Office reuse: not a good fit because Collabora collaborates on office document formats, not custom tree data.

## Planned Database Model

Initial tables:

```text
nxtree_trees
nxtree_nodes
nxtree_operations
```

Later table:

```text
nxtree_presence
```

Suggested table responsibilities:

```text
nxtree_trees
- id
- owner_user_id
- title
- root_node_id
- revision
- created_at
- updated_at
- deleted_at

nxtree_nodes
- id
- tree_id
- parent_id
- sort_order
- title
- content_markdown
- version
- created_at
- updated_at
- deleted_at

nxtree_operations
- id
- tree_id
- revision
- user_id
- type
- payload_json
- created_at

nxtree_presence
- tree_id
- node_id
- user_id
- mode
- last_seen
```

Use soft deletes (`deleted_at`) for trees/nodes initially. Avoid irreversible hard deletes until there is a recovery/export story.

## Operation Model

Clients send changes as operations against a known base revision.

Example:

```json
{
  "treeId": "123",
  "baseRevision": 42,
  "type": "renameNode",
  "nodeId": "abc",
  "payload": {
    "title": "New title"
  }
}
```

Server flow:

- Validate user permissions.
- Check current tree revision.
- Reject stale/conflicting operations when unsafe.
- Apply mutation in a database transaction.
- Increment tree revision.
- Store operation.
- Return new revision and applied operation.

Client sync flow:

```text
GET /sync?treeId=123&sinceRevision=42
```

The client applies remote operations after its last known revision. Start with polling every 2-5 seconds. Real-time push/SSE/WebSockets can be evaluated later.

## Conflict Strategy

Goal: no silent overwrites.

Easy operations should be accepted when independent:

- User A edits node 1.
- User B edits node 2.
- Both can be accepted.

Conflicting operations should be rejected clearly:

- User A deletes a node.
- User B edits the same node.
- Reject B with a clear `Node was deleted elsewhere` style message.

For Markdown node content, start with revision checks and/or soft locks. Do not implement CRDT text editing in the first milestone.

## What To Reuse From MeeTree

Reference/copy/adapt these from MeeTree as needed:

- CSS layout and visual language.
- Tree rendering approach.
- Drag/drop interaction ideas.
- Markdown renderer and preview/edit toggle.
- Search UI concepts.
- HJT codec.
- CTD codec.
- `.mtre` normalization/export logic.
- Package release script conventions.

Rename all ids/classes/names from `meetree` to `nxtree`, and PHP namespace from `OCA\MeeTree` to `OCA\NxTree`.

## What Not To Reuse From MeeTree

Avoid copying these architectural assumptions:

- `activeFile.path` as live state.
- `/MeeTree/state.json`.
- Whole-document JSON autosave.
- File-browser-open as the primary live storage model.
- Last active file in user-visible Files.
- Save-to-same-file behavior.

NxTree may import/export `.mtre`, but database rows are the live state.

## First Milestone: Installable Skeleton

The first concrete goal is to make NxTree installable and visible in Nextcloud.

Create this structure:

```text
nxtree/
  appinfo/
    info.xml
    routes.php
  lib/
    Controller/
      PageController.php
  templates/
    index.php
  js/
    nxtree.js
  css/
    nxtree.css
  img/
    app.svg
  CHANGELOG.md
  LICENSE
  README.md
  scripts/
    package-release.sh
```

Skeleton behavior:

- `php occ app:enable nxtree` works.
- Nextcloud sidebar shows `NxTree`.
- Clicking `NxTree` opens a placeholder page.
- Page loads `nxtree.css` and `nxtree.js`.
- No database tables required yet unless adding migration scaffolding at the same time.

Use current Nextcloud app patterns already used in MeeTree:

- `info.xml` with id `nxtree`, name `NxTree`, namespace `NxTree`.
- Route `nxtree.page.index`.
- `PageController` with `#[NoAdminRequired]`.
- Template renders a basic app shell.

## Second Milestone: Database Migration

After the skeleton loads, add migrations for:

- `nxtree_trees`
- `nxtree_nodes`
- `nxtree_operations`

Keep schema minimal and test install/upgrade carefully. Use Nextcloud-supported migration APIs.

## Third Milestone: First Database Tree CRUD

Implement basic endpoints and UI:

- create tree
- list trees
- load tree snapshot
- add node
- rename node
- edit node content
- delete node softly
- move/reorder node

Initially this can be single-browser but must use operation endpoints internally so the multiuser sync model is not bolted on later.

## Fourth Milestone: Polling Sync

Add:

- `GET /sync?treeId=...&sinceRevision=...`
- client polling
- applying remote operations to the local UI
- stale operation rejection messages

## Import/Export

`.mtre` should remain a portability format.

- Import `.mtre` into a database-backed tree.
- Export a database tree to `.mtre`.
- Reuse MeeTree codecs where practical for HJT/CTD later.

## Development Guidelines

- Keep changes small and testable.
- Do not start with full CRDT collaboration.
- Prioritize safe conflict behavior over fancy real-time editing.
- Preserve user data first.
- Add release packaging once the app skeleton exists.
- Use ASCII in files unless existing file conventions require otherwise.
- Avoid committing private key/certificate material.

## Useful MeeTree Reference Paths

From the MeeTree repo at `/Users/theo/git/nextcloud/TreeView`:

```text
meetree/appinfo/info.xml
meetree/appinfo/routes.php
meetree/lib/Controller/PageController.php
meetree/lib/Controller/DocumentController.php
meetree/lib/Service/DocumentService.php
meetree/lib/Service/HjtCodec.php
meetree/lib/Service/CtdCodec.php
meetree/templates/index.php
meetree/js/meetree.js
meetree/css/meetree.css
meetree/img/app.svg
meetree/scripts/package-release.sh
```

Use these as references only. NxTree should be architecturally database-first.

## Suggested First Prompt For New Session

```text
We are working in /Users/theo/git/nextcloud/NxTree on the new NxTree app.

NxTree is a separate Nextcloud app/repo from MeeTree. It should become a database-backed collaborative hierarchical notes app.

App id: nxtree
Visible name: NxTree
PHP namespace: OCA\NxTree
GitHub repo: https://github.com/dec0de/NxTree

MeeTree is the existing file-based .mtre app at /Users/theo/git/nextcloud/TreeView. Reuse ideas/code only where useful, but do not modify MeeTree and do not reuse its whole-file autosave model.

First milestone: create an installable Nextcloud app skeleton in an nxtree/ folder, with info.xml, routes, PageController, template, css/js, icon, changelog, and package-release script. The app should enable with php occ app:enable nxtree and show a placeholder NxTree page in Nextcloud.
```
