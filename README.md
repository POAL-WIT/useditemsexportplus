# Used Items Export Plus

Fork of [pluginsGLPI/useditemsexport](https://github.com/pluginsGLPI/useditemsexport)
with a self-service confirmation workflow on top of the original PDF export
feature.

## What's new compared to the upstream

- **Self-service entry** in the helpdesk menu: the user sees the list of
  assets currently assigned to them and can confirm them in one click.
- **One-shot "Confirm and generate PDF"** action: the confirmation is final
  and the generated PDF embeds an acknowledgement block
  *"Confirmed by `<user>` on `<datetime>`"*.
- **Lock after confirmation**: while a confirmed export exists, the user
  cannot generate a new one — only download the existing PDF.
- **Admin overview** (central interface) listing every confirmed export
  across users, with download and per-user **Reset** buttons.
- Reset deletes both the export row and the linked Document, allowing the
  user to generate a new confirmed export.

## Requirements

- GLPI >= 10.0.1, < 10.0.99
- PHP >= 7.4

## Install

1. Download the latest release archive and extract it under your GLPI
   `plugins/` directory so the layout is:

   ```
   <glpi>/plugins/useditemsexportplus/
   ```

2. In GLPI, go to **Setup → Plugins**, click **Install** then **Enable**
   on *Used items export plus*.

3. Grant the right `Used items export plus` (READ / CREATE / PURGE) to the
   profiles that should access the admin overview and the per-user tab.

4. The helpdesk menu entry is shown to every logged-in user as soon as the
   plugin is enabled in the configuration page (no extra right needed for
   the self-service flow).

## Configuration

**Setup → General → Used items export plus**

- *Active* — master switch for the plugin.
- *Footer text* — printed at the bottom of every generated PDF.
- *Orientation* / *Format* — PDF layout (Portrait/Landscape, A3/A4/A5).

## Pages

| Actor | URL / Entry point | What it does |
|---|---|---|
| Self-service user | `/plugins/useditemsexportplus/front/myassets.php` (helpdesk menu) | View own assets and confirm + generate PDF |
| Admin / technician | Central menu → *Used items export plus* | View all confirmed exports, download PDF, reset |
| Admin (per-user) | User profile → tab *Used items export plus* | View / reset the confirmed export for one user |

## Data model

- `glpi_plugin_useditemsexportplus_configs` — plugin configuration.
- `glpi_plugin_useditemsexportplus_exports` — one row per confirmed export
  (effectively one row per user, until an admin resets it):
  - `users_id`, `refnumber`, `documents_id`, `date_mod`, `date_ack`.

## Relationship with the upstream

This is a renamed fork (distinct plugin name, distinct DB tables) and is
**not** a drop-in upgrade for `useditemsexport`. The two plugins can coexist
on the same GLPI instance during a migration phase.

## License

This project is a fork of a project released under the GNU Affero General
Public License v3 and is distributed under the same terms. See
[LICENSE](LICENSE) for the full text.

## Maintainer

Alessandro Paoli
