# Changelog

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
