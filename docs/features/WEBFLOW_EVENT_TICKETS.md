# Webflow CMS & Event Tickets

## Overview

The project includes a **Filament Webflow** package (`packages/filament-webflow`) for integrating Webflow CMS with the POS, and an **Event Tickets** add-on for selling simple event tickets with Webflow-driven content and Stripe payment links. Webflow site linking is managed through **Add-ons**: you activate an add-on (e.g. Webflow CMS or Event Tickets) for a store, then link Webflow site keys to that add-on.

---

## Add-ons

Add-ons are per-store modules (e.g. **Webflow CMS**, **Event Tickets**). You must activate an add-on for a store before you can use the related features or link Webflow sites.

1. In Filament, **select a store** (tenant) first.
2. Go to **Settings → Add-ons** and click **Create**.
3. Choose **Type** (e.g. “Webflow CMS” or “Event Tickets”) and leave **Active** on. Save.
4. Only one addon per type per store is allowed. Once the add-on is active, the **Webflow CMS** menu (and **Event Tickets** under Payments, for that type) becomes visible for that store, and you can link Webflow site keys (see below).

---

## How do I…?

### How do I connect a Webflow site to the store?

1. **Activate an add-on** that supports Webflow (see **Add-ons** above): e.g. create and activate a “Webflow CMS” or “Event Tickets” add-on for the store. The Webflow CMS menu only appears when the store has at least one such active add-on.
2. In Filament, **select the store** (tenant). Go to **Webflow CMS → Webflow Sites**.
3. On the Webflow Sites list page, click the **Create** button (top right).
4. Fill in:
   - **Add-on** – select the active add-on (Webflow CMS or Event Tickets) this site belongs to.
   - **Name** – e.g. your site or client name.
   - **Webflow Site ID** – from Webflow: Site settings → General → Site ID (or from the site URL in the designer).
   - **API Token** – from Webflow: Account → Integrations → API access → create token (needs CMS read/write if you push data).
   - **Domain** (optional).
   - **Active** – leave on to use the site.
5. Save. Then open the site again and use **Discover collections** in the **CMS Collections** section to fetch all CMS collections from Webflow. Activate the collections you need (e.g. “Arrangementers” for events).

### How do I manage dynamic CMS (collection items)?

1. Connect a Webflow site (see above). Open it (click the site name or **Edit**), then in the **CMS Collections** tab click the **Discover collections** button to fetch collections from Webflow; toggle **Activate** on the collections you want to manage. When you activate a collection, an initial **pull from Webflow** is queued automatically so items sync without a separate manual pull.
2. In the same **CMS Collections** table, click **Manage items** on a collection row to open the CMS items page for that collection.
3. You are on the **Webflow CMS Items** page for that collection:
   - **Table**: lists all synced items (columns come from the collection schema; columns are toggleable, filters available for Published/Draft).
   - **Pull from Webflow**: header action to sync items from Webflow into the app (run the job). Image and MultiImage fields are downloaded into local media so the edit form can show and replace them. **Queue required:** a queue worker must be running (`php artisan queue:work` or Horizon) for image downloads to run. Images are stored on the **public** disk (`storage/app/public`); ensure `php artisan storage:link` has been run so they are served at `/storage/...`.
   - **Edit** (row): opens a dedicated **Edit** page with a CMS-like form: fields are generated from the collection schema (PlainText, RichText, Number, Switch, DateTime, Email, Phone, Link, Option, etc.) with proper labels and validation.
   - **Push to Webflow** (row or bulk): send changed data to Webflow and optionally publish.
4. On the **Edit** page you can save changes locally, then use **Push to Webflow** to sync and publish. To get items from Webflow first, use **Pull from Webflow** on the list, then edit and push as needed. If images do not appear after a pull: (1) ensure a queue worker is running (`php artisan queue:work` or Horizon); (2) run `php artisan storage:link` so the public disk is served; (3) check logs for `WebflowItem: syncing media` or `failed to download image` to confirm the job ran and whether downloads failed.

### How do I manage events (event tickets)?

1. **Select a store** and ensure the **Event Tickets** add-on is active (Settings → Add-ons). The **Event Tickets** menu under Payments only appears when that add-on is active.
2. **Connect Webflow** and **manage CMS** (see above): activate a “Webflow CMS” or “Event Tickets” add-on, then link a Webflow site and activate the events collection (e.g. “Arrangementers”). Optionally **Pull from Webflow** on that collection.
3. **Create or import event tickets:**
   - **Create** (manual): In Filament go to **Payments → Event Tickets** and click the **Create** button (top right). Fill in event details, ticket types, Stripe payment link/price IDs, and link to a Webflow CMS item.
   - **Import** (from Webflow): Run:
     ```bash
     php artisan event-tickets:import-from-webflow <store_slug_or_id> [--collection=<webflow_collection_id>] [--pull]
     ```
     This creates/updates **Event Ticket** records and links them to Webflow CMS items. Use `--pull` to sync from Webflow before importing.
4. On **Payments → Event Tickets** you can:
   - Edit **event details** (name, date, venue, etc.) and **Ticket 1 / Ticket 2** (labels, availability, payment link ID, price ID).
   - Link the event to a **Webflow CMS item** (dropdown: items from your store’s Webflow collections).
   - Set **Sold out** / **Archived** as needed.
5. When customers pay via the Stripe payment link, sold counts and sold-out state update automatically and sync back to Webflow (Billett 1/2 Solgte, Utsolgt) so the site can show availability without extra client-side logic.

---

## Package: `positiv/filament-webflow`

- **Location**: `packages/filament-webflow/`
- **Registration**: Plugin is registered in `AppPanelProvider`; package is required in root `composer.json` via path repository.

### Features

- **Webflow sites**: Connect a Webflow site to an **add-on** (per store) with API token; discover CMS collections. The add-on must be active before the Webflow CMS menu and site linking are available.
- **Navigation**: Under **Webflow CMS**, each connected site appears as a menu item with its **active collections** as children; clicking a site opens the site edit page, clicking a collection opens the CMS items table for that collection.
- **Dynamic collection items**: View and edit CMS items in Filament via **Webflow CMS Items** page (`?collection={id}`); table columns are driven by the collection; **Edit** opens a dedicated edit page with a schema-driven form (field types: PlainText, RichText, Number, Switch, DateTime, Email, Phone, Link, Option, **Image**, **MultiImage**, etc.). Image and MultiImage fields use **Spatie Media Library**: uploads are stored on the item; on save, media URLs are synced into `field_data` so **Push to Webflow** receives the correct URLs. Images from Webflow (or stored in `field_data`) are shown as URLs/thumbnails in the table, not as `[object Object]`.
- **Sync**: Pull items from Webflow (job `PullWebflowItems`), push changes (job `PushWebflowItem`); actions available on the collection items page. Activating a collection in the site’s CMS Collections tab auto-queues an initial pull.
- **Database notifications**: The app panel has [Filament database notifications](https://filamentphp.com/docs/4.x/notifications/database-notifications) enabled; Webflow actions (pull queued, collections discovered, pushed to Webflow, saved) are sent both as toasts and to the database so they appear in the notification bell and persist.

### Database (package migrations)

- `addons` (app) – store_id, type (e.g. webflow_cms, event_tickets), is_active
- `webflow_sites` – addon_id, webflow_site_id, api_token (encrypted), name, domain, is_active
- `webflow_collections` – webflow_site_id, webflow_collection_id, name, slug, schema (JSON), is_active, last_synced_at
- `webflow_items` – webflow_collection_id, webflow_item_id, field_data (JSON), is_published, is_archived, is_draft, last_synced_at

### Tenant scoping

- `WebflowSite` belongs to an **Addon** (`addon_id`); Addon belongs to Store. The app’s `Store` model defines `webflowSites()` via `hasManyThrough` (Store → Addon → WebflowSite). The package’s `WebflowSite` model defines `addon()` and `store()` (via addon); the WebflowSiteResource scopes the query by `addon.store_id` and only registers navigation when the store has an active add-on that supports Webflow.

---

## Event Tickets Add-on

- **Model**: `App\Models\EventTicket` (table `event_tickets`)
- **Resource**: `App\Filament\Resources\EventTickets\EventTicketResource` (navigation group: Payments)

### Flow

1. **Setup**: Activate the **Event Tickets** add-on for the store (Settings → Add-ons). Then connect a Webflow site in Filament (Webflow CMS → Webflow Sites; select the add-on when creating the site), discover collections, activate the “Arrangementers” (or equivalent) collection. Optionally run **Pull from Webflow** to sync items.
2. **Import**: Run `php artisan event-tickets:import-from-webflow {store_id_or_slug} [--pull]` to create/update `EventTicket` records from the Webflow collection and link them to `webflow_items`.
3. **Configuration**: In Filament (Event Tickets), set payment link IDs and price IDs (Stripe) and ticket availability per event.
4. **Sales**: Customers use the Stripe payment link on the Webflow site. On `charge.succeeded`, `HandleChargeWebhook` finds the `EventTicket` by payment link ID, increments the correct ticket sold count, updates sold-out state, and dispatches `PushTicketCountsToWebflow`.
5. **Webflow sync**: Job `PushTicketCountsToWebflow` updates the linked Webflow item’s field data (e.g. Billett 1/2 Solgte, Utsolgt) and publishes the item so the site shows sold-out state without client-side checks.

### Artisan command

```bash
php artisan event-tickets:import-from-webflow <store_id_or_slug> [--collection=] [--pull]
```

- `--pull`: Runs `PullWebflowItems` for the collection before importing.
- `--collection`: Webflow collection ID; if omitted, the first active collection for the store’s Webflow site is used.

### Webflow field mapping (Halvorsen Arrangementers)

| EventTicket field       | Webflow CMS (slug-style)   |
|--------------------------|----------------------------|
| ticket_1_available       | billett-1-tilgjengelig    |
| ticket_1_sold            | billett-1-solgte          |
| ticket_2_available       | billett-2-tilgjengelig    |
| ticket_2_sold            | billett-2-solgte          |
| is_sold_out              | utsolgt                   |

Exact slugs depend on the Webflow collection schema; adjust in `App\Jobs\PushTicketCountsToWebflow` if needed.

---

## Relevant files

- **Package**: `packages/filament-webflow/src/` (WebflowPlugin, WebflowApiClient, WebflowSiteResource, WebflowCollectionItemsPage, PullWebflowItems, PushWebflowItem, DiscoverCollections)
- **App**: `app/Models/Addon.php`, `app/Enums/AddonType.php`, `app/Filament/Resources/Addons/`, `app/Models/EventTicket.php`, `app/Filament/Resources/EventTickets/`, `app/Actions/Webhooks/HandleChargeWebhook.php` (event ticket handling), `app/Jobs/PushTicketCountsToWebflow.php`, `app/Console/Commands/ImportEventTicketsFromWebflow.php`
