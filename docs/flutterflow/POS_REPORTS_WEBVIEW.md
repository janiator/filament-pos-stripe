# POS Reports WebView i FlutterFlow

## Oversikt

Denne guiden viser hvordan du integrerer POS-rapporter (X-rapport og Z-rapport) i FlutterFlow-appen ved hjelp av en WebView-komponent.

## Rapporter

### X-rapport
- **Beskrivelse:** Daglig salgsrapport for åpen kassesesjon
- **Bruksområde:** Kan genereres når som helst for å se status på en åpen sesjon
- **Sesjon status:** Må være `open`

### Z-rapport
- **Beskrivelse:** Sluttrapport ved dagslutt
- **Bruksområde:** Genereres når en sesjon lukkes
- **Sesjon status:** Må være `closed`

## API-endepunkter

Rapportene kan vises via følgende embed-routes:

**X-rapport:** `/app/store/{tenant}/pos-sessions/{sessionId}/x-report/embed`
**Z-rapport:** `/app/store/{tenant}/pos-sessions/{sessionId}/z-report/embed`

Disse endepunktene returnerer en fullstendig HTML-side som er optimalisert for visning i WebView.

## Steg-for-steg implementasjon

### Steg 1: Legg til Custom Widget

1. **Opprett en ny Custom Widget** i FlutterFlow:
   - Navn: `PosReportWebView`
   - Fil: `lib/custom_widgets/pos_report_webview.dart`

2. **Lim inn koden fra filen:**
   - Åpne filen: `docs/flutterflow/custom-widgets/pos_report_webview.dart`
   - Kopier hele innholdet
   - Lim inn i FlutterFlow Custom Widget-editor

### Steg 2: Widget Parametere

Widget-en krever følgende parametere:

- **apiBaseUrl** (String, required) - Din API base URL
- **authToken** (String, required) - Brukerens autentiseringstoken
- **storeSlug** (String, required) - Butikkens slug (f.eks. "my-store")
- **sessionId** (int, required) - ID til POS-sesjonen
- **reportType** (String, required) - Type rapport: `"x"` eller `"z"`
- **width** (double, optional) - Bredde på widget
- **height** (double, optional) - Høyde på widget

### Steg 3: Bruk i FlutterFlow

1. **Dra inn Custom Widget-komponenten**
2. **Velg `PosReportWebView`**
3. **Sett parametere:**
   - **apiBaseUrl**: `FFAppConstants().apiBaseUrl` (hvis lagret i App Constants) eller `FFAppState().apiBaseUrl` (hvis lagret i App State)
   - **authToken**: `FFAppState().authToken`
   - **storeSlug**: `FFAppState().currentStoreSlug` eller `FFAppState().storeSlug`
   - **sessionId**: ID til POS-sesjonen (f.eks. `FFAppState().currentPosSession.id`)
   - **reportType**: `"x"` for X-rapport eller `"z"` for Z-rapport
   - **width** og **height**: (valgfritt, eller la dem være null for å fylle tilgjengelig plass)

**Merk:** Sørg for at følgende er konfigurert:
- `apiBaseUrl` - Din API base URL (f.eks. `https://pos-stripe.share.visivo.no`) - kan lagres i App Constants eller App State
- `authToken` - Brukerens autentiseringstoken - lagres typisk i App State
- `storeSlug` - Butikkens slug - lagres typisk i App State
- `sessionId` - ID til POS-sesjonen du vil vise rapport for

## Eksempler

### Eksempel 1: Vis X-rapport for nåværende sesjon

```dart
PosReportWebView(
  apiBaseUrl: FFAppConstants().apiBaseUrl,
  authToken: FFAppState().authToken,
  storeSlug: FFAppState().currentStoreSlug,
  sessionId: FFAppState().currentPosSession.id,
  reportType: 'x',
)
```

### Eksempel 2: Vis Z-rapport i en modal

```dart
// I en Action eller Button onClick
showModalBottomSheet(
  context: context,
  isScrollControlled: true,
  builder: (context) => SizedBox(
    height: MediaQuery.of(context).size.height * 0.9,
    child: PosReportWebView(
      apiBaseUrl: FFAppConstants().apiBaseUrl,
      authToken: FFAppState().authToken,
      storeSlug: FFAppState().currentStoreSlug,
      sessionId: selectedSessionId, // ID fra valgt sesjon
      reportType: 'z',
    ),
  ),
);
```

### Eksempel 3: Vis X-rapport på en dedikert side

1. **Opprett en side:** "X Report"
2. **Legg til AppBar** med tittel "X-rapport"
3. **Legg til Custom Widget:**
   ```dart
   PosReportWebView(
     apiBaseUrl: FFAppConstants().apiBaseUrl,
     authToken: FFAppState().authToken,
     storeSlug: FFAppState().currentStoreSlug,
     sessionId: FFAppState().currentPosSession.id,
     reportType: 'x',
     width: MediaQuery.of(context).size.width,
     height: MediaQuery.of(context).size.height - AppBar height,
   )
   ```

## Autentisering

Widget-en bruker Filament authentication flow:
1. Token sendes til `/filament-auth/{token}` route
2. Route autentiserer brukeren og oppretter en web session
3. Brukeren redirectes til embed-routen for rapporten
4. Rapport vises i WebView

## Feilhåndtering

Widget-en håndterer følgende feil:

- **401 Unauthorized:** "Authentication failed. Please log in again."
- **403 Forbidden:** "You do not have access to this report."
- **404 Not Found:** "Report or session not found."
- **Andre HTTP-feil:** Viser HTTP statuskode

## Viktige notater

### X-rapport
- Kan kun genereres for **åpne** sesjoner
- Sesjonen forblir åpen etter at X-rapporten er generert
- Brukes for å se status på en pågående sesjon

### Z-rapport
- Kan kun vises for **lukkede** sesjoner
- Genereres automatisk når en sesjon lukkes
- Brukes for dagsluttrapportering

### Sesjon status
- Sørg for at sesjonen har riktig status før du prøver å vise rapporten
- X-rapport krever `status = 'open'`
- Z-rapport krever `status = 'closed'`

## Feilsøking

### WebView viser ikke innhold

- **Sjekk autentisering:** Sørg for at token er gyldig og ikke utløpt
- **Sjekk sesjon status:** Verifiser at sesjonen har riktig status (open for X, closed for Z)
- **Sjekk tilgang:** Sørg for at brukeren har tilgang til butikken
- **Sjekk URL:** Verifiser at URL-en er korrekt konstruert

### Autentiseringsfeil

- **Token utløpt:** Logg inn på nytt for å få nytt token
- **Ugyldig token:** Sjekk at token er korrekt formatert
- **Ingen tilgang:** Sjekk at brukeren har tilgang til butikken

### Rapport ikke funnet

- **Sjekk sesjon ID:** Verifiser at sesjon ID er korrekt
- **Sjekk butikk slug:** Verifiser at butikk slug matcher sesjonens butikk
- **Sjekk sesjon status:** Sørg for at sesjonen har riktig status for rapporttypen

## Alternativ: Direkte API-integrasjon

Hvis du foretrekker å bygge en egen UI i stedet for å bruke embed-routen, kan du bruke API-endepunktene direkte:

### Generer X-rapport via API

```dart
// Custom Action: generateXReport
Future<Map<String, dynamic>> generateXReport(
  int sessionId,
  String apiBaseUrl,
  String authToken,
) async {
  final response = await http.post(
    Uri.parse('$apiBaseUrl/api/pos-sessions/$sessionId/x-report'),
    headers: {
      'Authorization': 'Bearer $authToken',
      'Content-Type': 'application/json',
    },
  );
  
  return jsonDecode(response.body);
}
```

### Generer Z-rapport via API

```dart
// Custom Action: generateZReport
Future<Map<String, dynamic>> generateZReport(
  int sessionId,
  String apiBaseUrl,
  String authToken,
) async {
  final response = await http.post(
    Uri.parse('$apiBaseUrl/api/pos-sessions/$sessionId/z-report'),
    headers: {
      'Authorization': 'Bearer $authToken',
      'Content-Type': 'application/json',
    },
  );
  
  return jsonDecode(response.body);
}
```

**Merk:** Z-rapport lukker sesjonen automatisk når den genereres.

## Referanser

- [POS Sessions API Documentation](../api/POS_SESSIONS_API.md)
- [Filament Embedding Guide](./FILAMENT_IFRAME_EMBEDDING.md)
- [Product Declaration WebView Guide](./PRODUKTFRASEGN_WEBVIEW.md)

