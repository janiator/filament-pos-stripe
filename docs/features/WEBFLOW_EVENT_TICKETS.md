# Webflow CMS & Event Tickets

## Overview

The project includes a **Filament Webflow** package (`packages/filament-webflow`) for integrating Webflow CMS with the POS, and an **Event Tickets** add-on for selling simple event tickets with Webflow-driven content and Stripe payment links. Webflow site linking is managed through **Add-ons**: you activate an add-on (e.g. Webflow CMS or Event Tickets) for a store, then link Webflow site keys to that add-on.

---

## Add-ons

Add-ons are per-store modules that gate access to features. Available types: **Webflow CMS**, **Event Tickets**, **Gift Cards**, **Payment Links**, **Transfers**, **Workflows**, **POS**. You must enable an add-on for a store before the related menu items and features appear.

1. In Filament, **select a store** (tenant) first.
2. Go to **Settings → Add-ons**. You see one card per add-on type, each with a short description and status (On/Off).
3. Click **Enable** on a card to turn that add-on on for the store. Only one add-on per type per store is allowed.
4. Once an add-on is active, the related navigation and features become visible: **Webflow CMS** for Webflow/Event Tickets (there is no separate Event Tickets menu; event ticket configuration is on the CMS item edit page when the collection has **Use for event tickets**); **Gift Cards**, **Payment Links**, **Transfers** under Payments; **Workflows** under Automation; **POS** (sessions, devices, terminals, receipts, etc.). On the Add-ons page, enabled Webflow-capable add-ons show **Add site** and **Manage sites**; other types show an **Open X** link to the main screen for that feature.

---

## How do I…?

### How do I connect a Webflow site to the store?

1. **Activate an add-on** that supports Webflow (see **Add-ons** above): e.g. create and activate a “Webflow CMS” or “Event Tickets” add-on for the store. The Webflow CMS menu only appears when the store has at least one such active add-on.
2. In Filament, **select the store** (tenant). Go to **Webflow CMS → Webflow Sites**.
3. On the Webflow Sites list page, click the **Create** button (top right).
4. Fill in:
   - **Name** – e.g. your site or client name.
   - **Webflow Site ID** – from Webflow: Site settings → General → Site ID (or from the site URL in the designer).
   - **API Token** – from Webflow: Account → Integrations → API access → create token (needs CMS read/write if you push data).
   - **Domain** (optional).
   - **Active** – leave on to use the site.
5. Save. Then open the site again and use **Discover collections** in the **CMS Collections** section to fetch all CMS collections from Webflow. Activate the collections you need. To use a collection for **Event Tickets**, set **Use for event tickets** on that collection (see below).

### How do I manage dynamic CMS (collection items)?

1. Connect a Webflow site (see above). Open it (click the site name or **Edit**), then in the **CMS Collections** tab click the **Discover collections** button to fetch collections from Webflow; toggle **Activate** on the collections you want to manage. When you activate a collection, an initial **pull from Webflow** is queued automatically so items sync without a separate manual pull.
2. In the same **CMS Collections** table, click **Manage items** on a collection row to open the CMS items page for that collection.
3. You are on the **Webflow CMS Items** page for that collection:
   - **Table**: lists all synced items (columns come from the collection schema; columns are toggleable, filters available for Published/Draft).
   - **Pull from Webflow**: header action to sync items from Webflow into the app (run the job). Image and MultiImage fields are downloaded into local media so the edit form can show and replace them. **Queue required:** a queue worker must be running (`php artisan queue:work` or Horizon) for image downloads to run. Images are stored on the **public** disk (`storage/app/public`); ensure `php artisan storage:link` has been run so they are served at `/storage/...`.
   - **Edit** (row): opens a dedicated **Edit** page with a CMS-like form: fields are generated from the collection schema (PlainText, RichText, Number, Switch, DateTime, Email, Phone, Link, Option, etc.) with proper labels and validation.
   - **Push to Webflow** (row or bulk): send changed data to Webflow and optionally publish.
4. On the **Edit** page you can save changes locally, then use **Push to Webflow** to sync and publish. To get items from Webflow first, use **Pull from Webflow** on the list, then edit and push as needed. If images do not appear after a pull: (1) ensure a queue worker is running (`php artisan queue:work` or Horizon); (2) run `php artisan storage:link` so the public disk is served; (3) check logs for `WebflowItem: syncing media` or `failed to download image` to confirm the job ran and whether downloads failed.

### How do I define which collection has events?

In **Webflow CMS → Webflow Sites**, open a site and go to the **CMS Collections** tab. For the collection that holds your events (e.g. “Arrangementers”), use the **Use for event tickets** action. A **field-mapping modal** opens: you choose which CMS field (by slug) to use for each ticket data (name, slug, description, event date/time, venue, ticket 1/2 available and sold, sold-out, payment link IDs, etc.). Options are built from the collection schema (run **Discover collections** first so schema is available). You can leave a field as "— None —" to use the default slug. After saving the mapping, that collection becomes the event tickets collection. Only one collection per store can be marked; setting it on another collection clears the flag on the previous one. To change the mapping later, use **Configure field mapping** on the same row. To stop using the collection for events, use **Unset as event tickets**. This collection is then used for **Sync from Webflow** (and the Artisan import when no `--collection` is given) and limits the **Webflow CMS item** dropdown when creating an event. If no collection is marked, the app falls back to the first active collection for the store and shows items from all active collections in the dropdown.

### How do I manage events (event tickets)?

Events are managed from **Webflow CMS** only: there is no separate Event Tickets menu. Event content (name, date, venue, description, image) comes from Webflow; you configure ticket types and payment links on the **CMS item edit** page when the collection has **Use for event tickets** enabled.

1. **Select a store** and ensure a Webflow-capable add-on is active (Settings → Add-ons): “Webflow CMS” or “Event Tickets”.
2. **Connect Webflow** and **pull your events collection** (see above): link a Webflow site, activate the collection you use for events, set **Use for event tickets** on that collection (CMS Collections tab), and run **Pull from Webflow** so items exist in the app.
3. **Edit an event (combined form):** Go to **Webflow CMS → [Site] → [Collection] → Manage items**. Click **Edit** on a row. The edit page shows the CMS fields plus an **Event ticket** section (event details, Ticket 1/2, payment links, max to sell, **amount sold** (editable so you can correct or override), archived). Configure payment links (existing or create new), max to sell, and optionally override amount sold; save. Use **Sync from Webflow** in the header to refresh event content from the CMS item. When you save, the linked payment links’ **quantity_max** and **quantity_sold** are updated so you can run reports and restrictions on payment links directly.
4. **Sync from Webflow:** On the **CMS items** table for a collection that has **Use for event tickets**, use the **Sync from Webflow** header action to create or update Event Ticket records from the collection. Optionally tick “Pull from Webflow first”. Then open each item to set or change payment links.
5. The table shows an **Event ticket** column (Linked / —) for items in an events collection. Editing the item is the only way to configure the linked event ticket.
6. When customers pay via the Stripe payment link, sold counts and sold-out state update automatically; **ConnectedPaymentLink** records get **quantity_sold** incremented (and **quantity_max** set from the event ticket when you save the form) so you can report or restrict by payment link. Sold counts and sold-out state also sync back to Webflow using the collection's **field mapping** (e.g. Billett 1/2 Solgte, Utsolgt) so the site can show availability without extra client-side logic.

**What happens when you save event ticket data on a CMS item?**  
Saving the edit page creates or updates an `EventTicket` linked to that Webflow item (`webflow_item_id`). The linked **ConnectedPaymentLink** rows (for ticket 1 and 2) are updated with **quantity_max** (from "Max to sell") and **quantity_sold** (from "Amount sold") so reports and restrictions can use payment-link-level data. The job **PushTicketCountsToWebflow** runs when ticket sales or availability change (e.g. after a webhook): it pushes the ticket’s sold counts and sold-out state into that item’s `field_data` in Webflow using the collection's **field mapping** (e.g. `billett-1-solgte`, `billett-2-solgte`, `utsolgt`) and publishes the item. Your Webflow site can then display live availability from those CMS fields without extra client logic.

**Data cleanup:** Deleting a Webflow site removes that site’s collections and items (DB cascade) and deletes any EventTicket records that referenced those items. Disabling the **last** Webflow-capable add-on (Webflow CMS or Event Tickets) for a store deletes all Webflow sites (and their collections and items) for that store and the EventTickets that referenced those items.

---

## Package: `positiv/filament-webflow`

- **Location**: `packages/filament-webflow/`
- **Registration**: Plugin is registered in `AppPanelProvider`; package is required in root `composer.json` via path repository.

### Features

- **Webflow sites**: Connect a Webflow site to an **add-on** (per store) with API token; discover CMS collections. The add-on must be active before the Webflow CMS menu and site linking are available.
- **Navigation**: Under **Webflow CMS**, each connected site appears as a menu item with its **active collections** as children; clicking a site opens the site edit page, clicking a collection opens the CMS items table for that collection.
- **Dynamic collection items**: View and edit CMS items in Filament via **Webflow CMS Items** page (`?collection={id}`); table columns are driven by the collection; **Edit** opens a dedicated edit page with a schema-driven form (field types: PlainText, RichText, Number, Switch, DateTime, Email, Phone, Link, Option, **Image**, **MultiImage**, etc.). When the collection has **Use for event tickets**, the edit page also shows an **Event ticket** section (event details, Ticket 1/2, payment links, sold counts). Image and MultiImage fields use **Spatie Media Library**: uploads are stored on the item; on save, media URLs are synced into `field_data` so **Push to Webflow** receives the correct URLs. Images from Webflow (or stored in `field_data`) are shown as URLs/thumbnails in the table, not as `[object Object]`.
- **Sync**: Pull items from Webflow (job `PullWebflowItems`), push changes (job `PushWebflowItem`); actions available on the collection items page. Activating a collection in the site’s CMS Collections tab auto-queues an initial pull.
- **Database notifications**: The app panel has [Filament database notifications](https://filamentphp.com/docs/4.x/notifications/database-notifications) enabled; Webflow actions (pull queued, collections discovered, pushed to Webflow, saved) are sent both as toasts and to the database so they appear in the notification bell and persist.

### Database (package migrations)

- `addons` (app) – store_id, type (e.g. webflow_cms, event_tickets), is_active
- `webflow_sites` – store_id, webflow_site_id, api_token (encrypted), name, domain, is_active
- `webflow_collections` – webflow_site_id, webflow_collection_id, name, slug, schema (JSON), field_mapping (JSON, for event ticket CMS field slugs), is_active, use_for_event_tickets, last_synced_at
- `webflow_items` – webflow_collection_id, webflow_item_id, field_data (JSON), is_published, is_archived, is_draft, last_synced_at

### Tenant scoping

- `WebflowSite` belongs to **Store** (`store_id`). The WebflowSiteResource scopes the query by `store_id` and only registers navigation when the store has an active add-on that supports Webflow (`AddonType::typesWithWebflow()`).

---

## Event Tickets Add-on

- **Model**: `App\Models\EventTicket` (table `event_tickets`)
- **Resource**: `App\Filament\Resources\EventTickets\EventTicketResource` (hidden from navigation; event ticket form is embedded in the CMS item edit page when the collection has **Use for event tickets**)

### Flow

1. **Setup**: Activate a Webflow-capable add-on for the store (Settings → Add-ons). Connect a Webflow site (Webflow CMS → Webflow Sites), discover collections, activate the collection you use for events, set **Use for event tickets** on that collection (CMS Collections tab), and run **Pull from Webflow** so items exist.
2. **Create or sync events:** Use **Sync from Webflow** on the CMS items table (when the collection has **Use for event tickets**) to create/update EventTicket records from the collection. Then open each item (**Edit**) to set ticket types and payment links (existing or create new).
3. **Configuration**: Edit the CMS item (Webflow CMS → [Site] → [Collection] → Manage items → Edit). The **Event ticket** section lets you change payment links, availability, or use **Sync from Webflow** in the header to refresh content from the CMS item.
4. **Sales**: Customers use the Stripe payment link on the Webflow site. On `charge.succeeded`, `HandleChargeWebhook` finds the `EventTicket` by payment link ID, increments the correct ticket sold count and the **ConnectedPaymentLink**'s **quantity_sold**, updates sold-out state, and dispatches `PushTicketCountsToWebflow`.
5. **Webflow sync**: Job `PushTicketCountsToWebflow` updates the linked Webflow item’s field data using the collection's **field mapping** (e.g. Billett 1/2 Solgte, Utsolgt) and publishes the item so the site shows sold-out state without client-side checks.

### Artisan command

```bash
php artisan event-tickets:import-from-webflow <store_id_or_slug> [--collection=] [--pull]
```

- `--pull`: Runs `PullWebflowItems` for the collection before importing.
- `--collection`: Webflow collection ID; if omitted, the first active collection for the store’s Webflow site is used.

### Webflow field mapping

When you set **Use for event tickets** on a collection, a **field-mapping modal** lets you choose which CMS field (slug) maps to each logical key (name, slug, description, image, event_date, event_time, venue, ticket_1_available, ticket_1_sold, ticket_2_available, ticket_2_sold, is_sold_out, payment_link_id_1/2, price_id_1/2). Defaults match the Halvorsen Arrangementers-style schema:

| Logical key             | Default slug              |
|-------------------------|---------------------------|
| ticket_1_available       | billett-1-tilgjengelig   |
| ticket_1_sold            | billett-1-solgte         |
| ticket_2_available       | billett-2-tilgjengelig   |
| ticket_2_sold            | billett-2-solgte         |
| is_sold_out              | utsolgt                  |

Mapping is stored on `webflow_collections.field_mapping` and used by `PushTicketCountsToWebflow` and `MapWebflowItemToEventTicketData`. Use **Configure field mapping** on the collection row to change it anytime.

---

## Relevant files

- **Package**: `packages/filament-webflow/src/` (WebflowPlugin, WebflowApiClient, WebflowSiteResource, WebflowCollectionItemsPage, PullWebflowItems, PushWebflowItem, DiscoverCollections, Support/EventTicketFieldMapping.php, CollectionsRelationManager for field-mapping modal)
- **App**: `app/Models/Addon.php`, `app/Models/ConnectedPaymentLink.php` (quantity_max, quantity_sold for event-ticket payment links), `app/Enums/AddonType.php`, `app/Filament/Resources/Addons/`, `app/Models/EventTicket.php`, `app/Filament/Pages/WebflowItemEditPage.php` (combined CMS + event ticket form, syncs payment link quantities), `app/Filament/Resources/EventTickets/`, `app/Actions/EventTickets/` (MapWebflowItemToEventTicketData, ImportEventTicketsFromWebflowCollection, CreateEventTicketPaymentLink), `app/Actions/Webhooks/HandleChargeWebhook.php` (event ticket handling, increments ConnectedPaymentLink.quantity_sold), `app/Jobs/PushTicketCountsToWebflow.php`, `app/Console/Commands/ImportEventTicketsFromWebflow.php`
