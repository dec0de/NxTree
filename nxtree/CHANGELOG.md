# Changelog

## 0.8.1

- Export the currently selected branch and its descendants as `.mtre`.
- Keep full-tree export available by selecting the root node.
- Add File menu helper text explaining the selected-branch export behavior.

## 0.8.0

- Add `.mtre` export for database-backed trees.
- Add a File menu export action for the currently open tree.
- Preserve NxTree node order and Markdown content in exported MeeTree-compatible JSON.

## 0.7.0

- Add polling sync for open trees using revisioned operation history.
- Add a tree-scoped sync endpoint that returns operations and a refreshed tree snapshot when revisions advance.
- Preserve selection and collapsed state while applying remote changes where possible.
- Avoid overwriting active local autosaves when remote changes arrive.

## 0.6.4

- Re-release the white Nextcloud navigation icon foreground so online installs can update from earlier icon builds.

## 0.6.3

- Use explicit white foreground for the Nextcloud navigation icon to match MeeTree's working app-bar behavior.

## 0.6.2

- Restore `currentColor` for the app navigation icon.
- Insert new child nodes directly under the selected parent as the first child.
- Port MeeTree search popup options for title/content, case-sensitive, and regex search.
- Make the search popup draggable and resizable.

## 0.6.1

- Use explicit white foreground for the Nextcloud navigation icon.

## 0.6.0

- Add database-backed tree structure editing.
- Add child node creation under the selected branch.
- Add soft-delete for selected nodes and descendants.
- Add direct-child branch sorting A-Z and Z-A.
- Add drag/drop move before, inside, and after target nodes.
- Store structure changes as revisioned operations.

## 0.5.0

- Add editable node titles and Markdown content.
- Save node edits through revisioned database operations with stale-revision conflict checks.
- Update tree revisions and operation history when nodes are edited.
- Add debounced autosave state in the editor toolbar.

## 0.4.0

- Replace the early placeholder UI with a current MeeTree-style full-screen shell.
- Add compact File and Search controls, resizable tree/editor divider, and editor/status layout.
- Render database-backed nodes with MeeTree-style tree connector lines and branch expand/collapse controls.
- Keep NxTree independent while adapting the proven MeeTree visual structure.

## 0.3.1

- Use `currentColor` for the app navigation icon so Nextcloud can theme it correctly.

## 0.3.0

- Add `.mtre` import from local uploads.
- Convert imported MeeTree documents into database-backed NxTree trees and nodes.
- Record imported trees as revisioned `importTree` operations.
- Render imported node hierarchy in the NxTree tree pane.

## 0.2.0

- Add first runnable database-backed NxTree UI.
- Add tree detail endpoint for loading root-node snapshots.
- Render created trees and their root nodes in an independent MeeTree-inspired shell.
- Add Markdown preview/edit toggle in preparation for node editing.

## 0.1.0

- Add installable Nextcloud app skeleton.
- Target Nextcloud 33 for initial development.
- Add initial database migration for trees, nodes, and revisioned operations.
- Add placeholder NxTree page, navigation entry, app icon, CSS, and JavaScript.
- Add App Store metadata and release packaging script.
