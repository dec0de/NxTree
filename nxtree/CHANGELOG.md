# Changelog

## 0.9.24

- Hide the `.nxtree` extension from virtual file labels in the directory tree.

## 0.9.23

- Add a small App Store screenshot thumbnail for app list previews in Nextcloud.

## 0.9.22

- Document the virtual directory tree model used for database-backed NxTree files and folders.

## 0.9.21

- Preserve single line breaks in Markdown preview text.

## 0.9.20

- Restore visible bullets and numbers for Markdown lists in preview.

## 0.9.19

- Preserve repeated blank lines as visible spacing in Markdown preview.
- Use a monospace font for Markdown preview text.

## 0.9.18

- Use the shared TreeMarkdown renderer based on markdown-it so Markdown preview matches Nextcloud `.md` rendering more closely.
- Align Markdown preview styling with MeeTree for compatible tree content display.
- Fall back safely if the Markdown renderer script is unavailable.

## 0.9.17

- Downsize the App Store screenshot for faster loading.

## 0.9.16

- Add an App Store screenshot for the NxTree interface.

## 0.9.15

- Preserve line indentation, tabs, repeated spaces, and line breaks in Markdown preview paragraphs.

## 0.9.14

- Reserve `_directory01_` so users cannot create or save a tree/file with the internal directory-tree name.

## 0.9.13

- Move Back out of the File menu so users can return from `_directory01_` immediately after Load.

## 0.9.12

- Add a Back action after Load so users can return from `_directory01_` to the previously open tree without choosing a file.

## 0.9.11

- Keep directory Folder/File prefixes off branch expand/collapse controls.
- Display virtual file names from the linked tree title when the directory entry has only a placeholder title.

## 0.9.10

- Restrict virtual folder/file rendering and behavior to the special `_directory01_` directory tree only.

## 0.9.9

- Replace the virtual Tree Library browser with a special `_directory01_` NxTree database tree.
- Represent virtual folders and `.nxtree` files as directory tree nodes with linked database-tree metadata.

## 0.9.8

- Replace Organize Tree with Load and Save actions for a file-like virtual Tree Library.
- Store virtual tree filenames alongside virtual folders so database trees can appear as NxTree files.

## 0.9.7

- Replace browser download from the File menu with Organize Tree for choosing a virtual Tree Library folder from existing Nextcloud folders.
- Store the chosen virtual library folder on database trees for future file-like organization.

## 0.9.6

- Fix Recent Trees rows so the title and source metadata render as two readable lines instead of clipping together.

## 0.9.5

- Wire the Undo toolbar button for restoring deleted nodes and recent local structure changes.
- Restore undo changes through revisioned database operations so undo stays multiuser-safe.
- Improve Recent Trees row spacing so title and source metadata remain readable.

## 0.9.4

- Rename the tree switcher to Recent Trees.
- Show each tree's source path, export folder, or database revision under its title.
- Add tooltips with source path, export folder, revision, and internal tree id to disambiguate same-name imports.

## 0.9.3

- Start Files import browsing at the Nextcloud Files root unless the current tree has a known source folder.
- Create the default export folder when opening the export browser if needed.
- Fall back to the Files root when a requested browser folder does not exist.

## 0.9.2

- Add an in-app Nextcloud Files browser for `.mtre` import.
- Add an in-app Nextcloud Files browser for selected-branch export.
- Let users navigate folders before importing or choosing the export destination.
- Let users choose the export filename before saving to Nextcloud Files.

## 0.9.1

- Move the tree switcher from the permanent sidebar into the File menu.
- Reclaim left-pane space for branch actions and the loaded tree outline.

## 0.9.0

- Add Nextcloud Files based `.mtre` import by server-side path.
- Add Nextcloud Files based selected-branch `.mtre` export.
- Remember imported source file paths and last export folders for each database tree.
- Default exports to the imported file's folder when known, otherwise `/NxTree`.
- Keep browser upload/download as fallback actions.

## 0.8.2

- Replace NxTree's minimal Markdown preview renderer with MeeTree-compatible rendering.
- Improve paragraph and blank-line handling in node previews.
- Add support for MeeTree-style headings, lists, blockquotes, code blocks, task checkboxes, links, and inline formatting.

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
