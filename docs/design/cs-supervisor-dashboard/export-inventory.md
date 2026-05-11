# Uploaded Export Inventory

Source: CS Supervisor Dashboard zip uploaded in ChatGPT.

The export contained the newest Operations Dashboard HTML, two older duplicate HTML exports, duplicated upload folders, an exported CSS file, a logo file, and one PNG screenshot.

Repo handling:

- Keep design exports under docs only.
- Do not place exported CSS into assets/css.
- Do not enqueue reference HTML in WordPress.
- Do not copy duplicate upload folders into the plugin.
- Use the Queue tab layout as a visual target only.

Implementation note:

The CS Queue tab should be built in the Slate Ops CS dashboard using the existing PHP, REST, JavaScript, and CSS structure. The design export is not the production source of truth.
