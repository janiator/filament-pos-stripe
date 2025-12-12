# Z Reports List View i FlutterFlow

## Oversikt

Denne guiden viser hvordan du integrerer en liste over alle Z-rapporter (lukkede sesjoner) for en POS-enhet i FlutterFlow-appen. Widget-en lar brukere se alle tidligere lukkede sesjoner og vise Z-rapporten for hver sesjon.

## Funksjonalitet

Widget-en:
- Henter alle lukkede sesjoner (Z-rapporter) for en spesifikk POS-enhet
- Viser dem i en søkbar liste med sesjonsnummer, lukketid, antall transaksjoner og totalbeløp
- Lar brukere trykke på en sesjon for å vise Z-rapporten i en modal
- Støtter pull-to-refresh for å oppdatere listen

## Steg-for-steg implementasjon

### Steg 1: Legg til Custom Widgets

**Viktig:** Du må legge til begge widgets for at dette skal fungere:

1. **Legg til `PosReportWebView` widget først:**
   - Opprett Custom Widget: `PosReportWebView`
   - Fil: `lib/custom_widgets/pos_report_webview.dart`
   - Se [POS Reports WebView Guide](./POS_REPORTS_WEBVIEW.md) for detaljer

2. **Legg til `ZReportsListView` widget:**
   - Opprett Custom Widget: `ZReportsListView`
   - Fil: `lib/custom_widgets/z_reports_list_view.dart`
   - Åpne filen: `docs/flutterflow/custom-widgets/z_reports_list_view.dart`
   - Kopier hele innholdet
   - Lim inn i FlutterFlow Custom Widget-editor

### Steg 2: Widget Parametere

Widget-en krever følgende parametere:

- **apiBaseUrl** (String, required) - Din API base URL
- **authToken** (String, required) - Brukerens autentiseringstoken
- **storeSlug** (String, required) - Butikkens slug (f.eks. "my-store")
- **posDeviceId** (int, required) - ID til POS-enheten
- **width** (double, optional) - Bredde på widget
- **height** (double, optional) - Høyde på widget

### Steg 3: Bruk i FlutterFlow

1. **Dra inn Custom Widget-komponenten**
2. **Velg `ZReportsListView`**
3. **Sett parametere:**
   - **apiBaseUrl**: `FFAppConstants().apiBaseUrl` (hvis lagret i App Constants) eller `FFAppState().apiBaseUrl` (hvis lagret i App State)
   - **authToken**: `FFAppState().authToken`
   - **storeSlug**: `FFAppState().currentStoreSlug` eller `FFAppState().storeSlug`
   - **posDeviceId**: `FFAppState().activePosDevice.id` eller `FFAppState().posDeviceId`
   - **width** og **height**: (valgfritt, eller la dem være null for å fylle tilgjengelig plass)

**Merk:** Sørg for at følgende er konfigurert:
- `apiBaseUrl` - Din API base URL (f.eks. `https://pos-stripe.share.visivo.no`) - kan lagres i App Constants eller App State
- `authToken` - Brukerens autentiseringstoken - lagres typisk i App State
- `storeSlug` - Butikkens slug - lagres typisk i App State
- `posDeviceId` - ID til POS-enheten - lagres typisk i App State

## Eksempler

### Eksempel 1: Vis Z-rapporter på en dedikert side

1. **Opprett en side:** "Z Reports"
2. **Legg til AppBar** med tittel "Z-rapporter"
3. **Legg til Custom Widget:**
   ```dart
   ZReportsListView(
     apiBaseUrl: FFAppConstants().apiBaseUrl,
     authToken: FFAppState().authToken,
     storeSlug: FFAppState().currentStoreSlug,
     posDeviceId: FFAppState().activePosDevice.id,
     width: MediaQuery.of(context).size.width,
     height: MediaQuery.of(context).size.height - AppBar height,
   )
   ```

### Eksempel 2: Vis Z-rapporter i en modal

```dart
// I en Action eller Button onClick
showModalBottomSheet(
  context: context,
  isScrollControlled: true,
  builder: (context) => SizedBox(
    height: MediaQuery.of(context).size.height * 0.9,
    child: ZReportsListView(
      apiBaseUrl: FFAppConstants().apiBaseUrl,
      authToken: FFAppState().authToken,
      storeSlug: FFAppState().currentStoreSlug,
      posDeviceId: FFAppState().activePosDevice.id,
    ),
  ),
);
```

### Eksempel 3: Legg til navigasjon fra hovedmenyen

1. **I hovedmenyen eller innstillinger:**
   - Legg til en **Button** eller **ListTile** med tekst "Z-rapporter"
   - Sett **onTap** til å navigere til "Z Reports"-siden

## Widget Funksjonalitet

### Listevisning

Widget-en viser hver lukket sesjon med:
- **Sesjonsnummer** - Unikt nummer for sesjonen
- **Lukketid** - Når sesjonen ble lukket (dato og klokkeslett)
- **Antall transaksjoner** - Totalt antall transaksjoner i sesjonen
- **Totalbeløp** - Totalt beløp for sesjonen (formatert som kr)

### Vis Z-rapport

Når brukeren trykker på en sesjon:
1. Åpnes en modal med Z-rapporten
2. Modal viser Z-rapporten ved hjelp av `PosReportWebView` widget
3. Brukeren kan lukke modalen for å gå tilbake til listen

### Pull-to-Refresh

Brukere kan dra ned for å oppdatere listen med nye lukkede sesjoner.

## API-endepunkt

Widget-en bruker følgende API-endepunkt:

**GET** `/api/pos-sessions?status=closed&pos_device_id={deviceId}&per_page=50`

**Query Parameters:**
- `status` - Filter by status (`closed` for Z-rapporter)
- `pos_device_id` - Filter by device ID
- `per_page` - Number of results per page (default: 50)

**Response:**
```json
{
  "sessions": [
    {
      "id": 123,
      "session_number": "000001",
      "status": "closed",
      "opened_at": "2025-12-10T08:00:00+01:00",
      "closed_at": "2025-12-10T18:00:00+01:00",
      "transaction_count": 45,
      "total_amount": 200000,
      "session_device": {
        "id": 1,
        "device_name": "Main Cash Register"
      },
      "session_user": {
        "id": 5,
        "name": "John Doe"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 50,
    "total": 100
  }
}
```

## Feilhåndtering

Widget-en håndterer følgende feil:

- **401 Unauthorized:** "Authentication failed. Please log in again."
- **403 Forbidden:** "You do not have access to this device."
- **Andre HTTP-feil:** Viser feilmelding fra API eller HTTP statuskode
- **Nettverksfeil:** Viser feilmelding med detaljer
- **Tom liste:** Viser melding "Ingen Z-rapporter funnet"

## Viktige notater

### Z-rapporter vs X-rapporter

- **Z-rapporter** er kun tilgjengelige for **lukkede** sesjoner
- **X-rapporter** er kun tilgjengelige for **åpne** sesjoner
- Når en sesjon lukkes, genereres automatisk en Z-rapport

### Sesjon status

- Widget-en henter kun sesjoner med `status = 'closed'`
- Åpne sesjoner vises ikke i listen
- Hver lukket sesjon har en tilhørende Z-rapport

### Paginering

- Widget-en henter opp til 50 lukkede sesjoner per side
- For å vise flere sesjoner, kan du legge til paginering eller øke `per_page` parameteren

## Feilsøking

### Listen er tom

- **Sjekk enhet ID:** Verifiser at `posDeviceId` er korrekt
- **Sjekk status:** Sørg for at det finnes lukkede sesjoner for enheten
- **Sjekk tilgang:** Sørg for at brukeren har tilgang til enheten

### Autentiseringsfeil

- **Token utløpt:** Logg inn på nytt for å få nytt token
- **Ugyldig token:** Sjekk at token er korrekt formatert
- **Ingen tilgang:** Sjekk at brukeren har tilgang til butikken og enheten

### Z-rapport vises ikke

- **Sjekk sesjon ID:** Verifiser at sesjon ID er korrekt
- **Sjekk sesjon status:** Sørg for at sesjonen er lukket
- **Sjekk butikk slug:** Verifiser at butikk slug matcher sesjonens butikk

## Avhengigheter

Widget-en krever:
- **`PosReportWebView` widget** (må legges til først - se [POS Reports WebView Guide](./POS_REPORTS_WEBVIEW.md))
- `http` pakke (for API-kall) - legges til i `pubspec.yaml`:
  ```yaml
  dependencies:
    http: ^1.1.0
  ```
- `dart:convert` (for JSON parsing) - inkludert i Dart SDK

## Referanser

- [POS Reports WebView Guide](./POS_REPORTS_WEBVIEW.md)
- [POS Sessions API Documentation](../api/POS_SESSIONS_API.md)
- [Filament Embedding Guide](./FILAMENT_IFRAME_EMBEDDING.md)

