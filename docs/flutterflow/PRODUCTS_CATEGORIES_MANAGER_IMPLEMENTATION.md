# ProductsCategoriesManager – implementation instructions

This document describes how to use the updated **ProductsCategoriesManager** custom widget that loads **Varegruppekode (SAF-T)** options from the API instead of hardcoded values.

## What changed

1. **State**  
   - Added `_visibleArticleGroupCodes` (list of `{ code, name }` from the store API).

2. **Data loading**  
   - Added `_loadVisibleArticleGroupCodes()` which calls `GET /api/stores/{storeSlug}` and reads `store.visible_article_group_codes`.  
   - This is run in parallel with products, categories, vendors, and quantity units in `_loadData()`.

3. **Varegruppekode dropdown**  
   - Replaced the long hardcoded list of SAF-T codes with items built from `_visibleArticleGroupCodes`.  
   - If the product’s current `article_group_code` is not in the visible list (e.g. code was later hidden in POS), one extra option is shown: `"{code} (ikke synlig i POS)"` so the value is still displayable and editable.

4. **New product default**  
   - When creating a new product, the default article group code is the first visible code if the list is non-empty, otherwise `'04999'`.

5. **UI**  
   - Tab bar built with a small `_buildTab()` helper to avoid repetition (optional refactor).

---

## File location in this repo

Reference copy of the full widget (for diff/copy into FlutterFlow):

- **`docs/flutterflow/ProductsCategoriesManager.dart`**

Do **not** edit FlutterFlow project files from this repo; use this file as the source of truth and apply changes in FlutterFlow.

---

## How to apply in FlutterFlow

### Option A: Replace the custom widget file

1. In your FlutterFlow project, open the custom widget **ProductsCategoriesManager** (e.g. under **Custom Code** → **Widgets**).
2. Replace the entire Dart content of that widget with the content of **`docs/flutterflow/ProductsCategoriesManager.dart`** from this repo.
3. Adjust the **top imports** (the block that starts with `// Automatic FlutterFlow imports`) if your project uses different paths. Do not remove or change the automatic-import block; only fix paths if FlutterFlow reports missing imports.
4. Save and run **Run Mode** or **Test Mode** to confirm the Products/Categories manager loads and the Varegruppekode dropdown is filled from the API.

### Option B: Apply only the article-group changes

If you prefer to keep your current file and only change the article-group behaviour:

1. **Add state**  
   After the other list state variables (e.g. after `_selectedQuantityUnitId`), add:
   ```dart
   List<Map<String, dynamic>> _visibleArticleGroupCodes = [];
   ```

2. **Extend `_loadData()`**  
   In the `Future.wait([...])` call, add:
   ```dart
   _loadVisibleArticleGroupCodes(),
   ```

3. **Add `_loadVisibleArticleGroupCodes()`**  
   Copy the full `_loadVisibleArticleGroupCodes()` method from **`docs/flutterflow/ProductsCategoriesManager.dart`** (it uses `GET ${apiBaseUrl}/api/stores/${storeSlug}` and sets `_visibleArticleGroupCodes` from `store.visible_article_group_codes`).

4. **Replace the Varegruppekode dropdown**  
   Find the `DropdownButtonFormField<String?>` whose label is **"Varegruppekode (SAF-T)"** and whose `items` list is the long list of `DropdownMenuItem<String>` (04001 … 04999). Replace that whole `DropdownButtonFormField<String?>` with the one from **`docs/flutterflow/ProductsCategoriesManager.dart`** (the one whose `items` are built from `_visibleArticleGroupCodes` and the optional “ikke synlig i POS” entry).

5. **Default for new product**  
   In `_newProduct()`, where you set `_selectedArticleGroupCode`, change to:
   ```dart
   _selectedArticleGroupCode = _visibleArticleGroupCodes.isNotEmpty
       ? (_visibleArticleGroupCodes.first['code'] as String?)
       : '04999';
   ```

---

## API requirements

- The widget expects the **store** response to include **`visible_article_group_codes`**: an array of objects with **`code`** and **`name`** (e.g. `{ "code": "04003", "name": "Varesalg" }`).
- That is returned by:
  - **`GET /api/stores/{slug}`**
  - **`GET /api/stores/current`**
- The backend only includes codes that are **active** and **Visible in POS** (per article group code in Filament).  
- The widget is written to use **`widget.storeSlug`** and call **`GET /api/stores/${widget.storeSlug}`**. If your screen only has the “current” store and no slug, change the URL to **`${widget.apiBaseUrl}/api/stores/current`** and keep the same headers and response parsing (`store.visible_article_group_codes`).

---

## Widget parameters

The widget must receive:

- **apiBaseUrl** – base URL of your API (e.g. `https://your-api.example.com`).
- **authToken** – Bearer token for `Authorization`.
- **storeSlug** – slug of the store (e.g. `jobberiet-as`) so the client can call `GET /api/stores/{storeSlug}`.  
  If you only have the current store, pass a dummy slug and switch the load to `GET /api/stores/current` as above.

---

## Testing

1. Open the screen that embeds **ProductsCategoriesManager** and ensure **apiBaseUrl**, **authToken**, and **storeSlug** (or equivalent) are passed in.
2. Open **Produkter** → **Nytt produkt** or edit an existing product.
3. **Varegruppekode (SAF-T)** should list only the codes returned in `visible_article_group_codes` for that store.
4. In Filament, turn “Visible in POS” off for one article group code; reload the app and open the product form again. That code should disappear from the dropdown. If a product already had that code, it should still appear as “{code} (ikke synlig i POS)”.
