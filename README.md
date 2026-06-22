# NxTree

NxTree is a Nextcloud app for collaborative hierarchical notes.

NxTree starts from lessons learned in MeeTree, but it is not a fork of MeeTree's storage model. MeeTree remains the simple file-based `.mtre` editor. NxTree is designed database-first so multiple users can safely work in the same tree without silent overwrites.

## Status

NxTree currently provides a runnable database-backed app version with a current MeeTree-style UI shell. You can enable the app, create database-backed trees, open and switch recent trees from the File menu with source-path disambiguation, organize database trees into virtual library folders chosen from existing Nextcloud folders, import `.mtre` files from an in-app Nextcloud Files browser, export the selected branch with a Nextcloud Files destination/filename browser, view stored nodes with tree connector lines and branch expand/collapse, edit node titles and MeeTree-compatible Markdown content, change tree structure with add/delete/sort/drag-drop operations, undo recent structure mistakes when safe, and receive polling-based remote updates. The Files browser starts at a known source folder when available and otherwise falls back to the Files root.

## Installation

Clone or unpack this repository into your Nextcloud custom apps directory, keeping the app folder name lowercase:

```sh
git clone https://github.com/dec0de/NxTree.git
cp -R NxTree/nxtree /path/to/nextcloud/custom_apps/nxtree
php occ app:enable nxtree
```

For a release archive, use:

```sh
./nxtree/scripts/package-release.sh
```

The archive is written to `build/nxtree-<version>.tar.gz` and contains a top-level `nxtree/` directory.

## Mission

Build a true multiuser tree-note app for Nextcloud that feels as direct as a classic TreePad/Jreepad-style outliner, while respecting Nextcloud users, sharing, permissions, backups, and deployment constraints.

NxTree should make it safe for multiple people to view and edit the same tree. The first goal is multiuser safety and visible synchronization, not Google Docs-style character-by-character editing on day one.

## Product Principles

- Database-backed live storage, not one shared JSON file.
- `.mtre` import/export for portability and backups.
- Revisioned operations to prevent silent overwrites.
- Polling-based synchronization first; real-time push can come later.
- Node-level Markdown content with preview/edit toggle.
- Clear conflict handling instead of hidden last-save-wins behavior.
- Presence and soft locks before full CRDT text editing.
- MeeTree remains stable and simple; NxTree can evolve as the collaborative app.

## Initial Architecture

NxTree will use the Nextcloud database as its live source of truth.

Initial tables:

```text
nxtree_trees
nxtree_nodes
nxtree_operations
```

Core concepts:

- `nxtree_trees` stores tree title, owner, root node, revision, and timestamps.
- `nxtree_trees` also stores imported source file paths and last export folders for Nextcloud Files workflows.
- `nxtree_nodes` stores parent, ordering, title, Markdown content, and soft-delete state.
- `nxtree_operations` stores every mutation with revision and user id.
- `nxtree_presence` will store who is viewing or editing a tree/node. This is planned after the first CRUD and sync endpoints.

## Operation Model

Clients send changes as operations against a known base revision:

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

The server applies operations transactionally:

- validate permissions
- check current tree revision
- reject conflicting stale operations
- apply the mutation
- increment tree revision
- store the operation
- return the new revision

Clients poll for remote changes:

```text
GET /apps/nxtree/trees/123/sync?sinceRevision=42
```

This gives NxTree safe multiuser editing before adding more advanced real-time infrastructure.

## Development Roadmap

### Phase 1: App Skeleton

- Nextcloud app id: `nxtree`
- Visible name: `NxTree`
- PHP namespace: `OCA\NxTree`
- Basic app page, routes, controllers, services, CSS, and JavaScript.
- Database migration scaffolding.
- Release packaging script.
- Create, list, and open database-backed trees.
- Render root nodes in a first tree/editor UI shell.
- Import `.mtre` files into database-backed trees.
- Use a current MeeTree-style full-screen UI shell with resizable divider and connector-line tree rendering.
- Edit node titles and Markdown content through revisioned database operations.

### Phase 2: Database Tree Editor

- Create tree.
- Add, delete, rename, move, and sort nodes.
- Edit Markdown node content.
- Preview/edit toggle.
- Search titles and content.

### Phase 3: Revisioned Multiuser Safety

- All mutations use revisioned operations.
- No silent overwrites.
- Clear stale-operation and conflict messages.

### Phase 4: Polling Sync

- Clients poll for operations after their last known revision.
- Other users' changes appear without a full reload.
- Preserve local selection and editor state where possible.
- Defer applying remote snapshots while an autosave is pending or in flight.

### Phase 5: Presence And Soft Locks

- Show who is viewing/editing a tree or node.
- Add heartbeat endpoint.
- Optional node-level edit locks for Markdown content.

### Phase 6: Import/Export

- Import `.mtre` into database-backed trees.
- Export database trees to `.mtre`.
- Export selected branches to `.mtre`.
- Import/export through Nextcloud Files paths.
- Organize database trees into virtual library folders derived from existing Nextcloud folders.
- Reuse MeeTree HJT/CTD codecs where practical.

### Phase 7: Real-Time Text Collaboration

- Evaluate CRDT/Yjs-style editing for Markdown node content.
- Keep this optional until the operation model is stable.

## Reuse From MeeTree

NxTree can reuse or adapt:

- split tree/editor layout
- tree rendering patterns
- drag/drop UX
- Markdown rendering and preview/edit toggle ideas
- search UI patterns
- HJT and CTD codecs
- packaging conventions

NxTree should not reuse:

- whole-document autosave
- `activeFile.path`
- `/MeeTree/state.json`
- `.mtre` as live storage
- last-save-wins behavior

## App Store Notes

- App id: `nxtree`
- Visible name: `NxTree`
- PHP namespace: `OCA\NxTree`
- License: `AGPL-3.0-or-later`
- Current Nextcloud compatibility: 33
- Release archive root folder: `nxtree/`
