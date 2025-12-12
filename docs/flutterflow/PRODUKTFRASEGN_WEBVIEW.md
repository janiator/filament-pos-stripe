# Produktfråsegn WebView i FlutterFlow

## Oversikt

Denne guiden viser hvordan du integrerer produktfråsegna i FlutterFlow-appen ved hjelp av en WebView-komponent.

## API-endepunkt

Produktfråsegna kan vises via følgende endepunkt:

**GET** `/api/product-declaration/display`

Dette endepunktet returnerer en fullstendig HTML-side som er optimalisert for visning i WebView.

## Steg-for-steg implementasjon

### Steg 1: Legg til WebView-komponent

1. **Åpne FlutterFlow-appen** og naviger til siden der du vil vise produktfråsegna
2. **Legg til WebView-komponent:**
   - Dra en **WebView**-komponent fra komponentbiblioteket
   - Plasser den der du vil ha den (f.eks. i en fullskjermsvisning eller i en modal)

### Steg 2: Konfigurer WebView

1. **Velg WebView-komponenten**
2. **I Properties-panelet:**
   - **Initial URL:** Sett til din API-endepunkt
     ```
     https://din-domain.com/api/product-declaration/display
     ```
   - **JavaScript Mode:** Aktiver hvis du trenger JavaScript-støtte (vanligvis ikke nødvendig)
   - **Allows Back Navigation:** Aktiver hvis du vil tillate tilbake-navigasjon
   - **Progress Indicator:** Aktiver for å vise lasteindikator

### Steg 3: Legg til autentisering

WebView må sende autentiserings-token med forespørselen. Dette kan gjøres på to måter:

#### Metode 1: Legg til Authorization Header (Anbefalt)

1. **Opprett en Custom Action** for å sette WebView URL med headers:

```dart
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

Future<String> getProductDeclarationUrl(String baseUrl, String token) async {
  // Returner URL-en - WebView vil automatisk bruke token fra app state
  return '$baseUrl/api/product-declaration/display';
}
```

2. **I WebView-komponenten:**
   - Bruk en **Custom Action** for å sette initial URL
   - Pass inn base URL og token fra app state

#### Metode 2: Bruk Signed URL (Hvis støttet)

Hvis backend støtter signed URLs, kan du generere en signert URL som inkluderer autentisering.

### Steg 4: Håndter autentisering i WebView

Siden WebView ikke automatisk sender headers, må du enten:

**Alternativ A: Bruk WebView Controller med Headers**

1. **Opprett en Custom Widget** for WebView med custom headers:

```dart
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

class ProductDeclarationWebView extends StatefulWidget {
  final String baseUrl;
  final String token;

  const ProductDeclarationWebView({
    Key? key,
    required this.baseUrl,
    required this.token,
  }) : super(key: key);

  @override
  State<ProductDeclarationWebView> createState() => _ProductDeclarationWebViewState();
}

class _ProductDeclarationWebViewState extends State<ProductDeclarationWebView> {
  late final WebViewController _controller;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            // Page started loading
          },
          onPageFinished: (String url) {
            // Page finished loading
          },
        ),
      )
      ..loadRequest(
        Uri.parse('${widget.baseUrl}/api/product-declaration/display'),
        headers: {
          'Authorization': 'Bearer ${widget.token}',
          'Content-Type': 'application/json',
        },
      );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Produktfråsegn'),
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
```

**Alternativ B: Modifiser Backend til å akseptere Token i Query Parameter**

Hvis du ikke kan bruke headers, kan du modifisere backend til å akseptere token som query parameter:

```
/api/product-declaration/display?token=YOUR_TOKEN
```

### Steg 5: Full implementasjon med Custom Widget

1. **Opprett en ny Custom Widget** i FlutterFlow:
   - Navn: `ProductDeclarationWebView`
   - Fil: `lib/custom_widgets/product_declaration_webview.dart`

2. **Lim inn koden fra filen:**
   - Åpne filen: `docs/flutterflow/custom-widgets/product_declaration_webview.dart`
   - Kopier hele innholdet
   - Lim inn i FlutterFlow Custom Widget-editor

3. **Viktig:** Widget-en krever følgende parametere:
   - **apiBaseUrl** (String, required) - Din API base URL
   - **authToken** (String, required) - Brukerens autentiseringstoken
   - **width** (double, optional) - Bredde på widget
   - **height** (double, optional) - Høyde på widget

4. **I FlutterFlow:**
   - Dra inn **Custom Widget**-komponenten
   - Velg `ProductDeclarationWebView`
   - Sett **apiBaseUrl** til: `FFAppConstants().apiBaseUrl` (hvis lagret i App Constants) eller `FFAppState().apiBaseUrl` (hvis lagret i App State)
   - Sett **authToken** til: `FFAppState().authToken`
   - Sett **width** og **height** (eller la dem være null for å fylle tilgjengelig plass)

**Merk:** Sørg for at følgende er konfigurert:
- `apiBaseUrl` - Din API base URL (f.eks. `https://pos-stripe.share.visivo.no`) - kan lagres i App Constants eller App State
- `authToken` - Brukerens autentiseringstoken - lagres typisk i App State

### Steg 6: Legg til navigasjon

1. **Opprett en knapp eller menyvalg** for å åpne produktfråsegna
2. **Naviger til siden** med WebView-komponenten
3. **Eller vis i en modal:**

```dart
// I en Action eller Button onClick
showModalBottomSheet(
  context: context,
  isScrollControlled: true,
  builder: (context) => SizedBox(
    height: MediaQuery.of(context).size.height * 0.9,
    child: ProductDeclarationWebView(
      apiBaseUrl: FFAppConstants().apiBaseUrl, // eller FFAppState().apiBaseUrl
      authToken: FFAppState().authToken,
    ),
  ),
);
```

## Alternativ: Bruk InAppWebView (Hvis du trenger mer kontroll)

Hvis du trenger mer avansert funksjonalitet, kan du bruke `flutter_inappwebview`:

1. **Legg til pakken** i `pubspec.yaml`:
```yaml
dependencies:
  flutter_inappwebview: ^6.0.0
```

2. **Bruk InAppWebView** i stedet for standard WebView for bedre header-støtte.

## Feilsøking

### WebView viser ikke innhold

- **Sjekk autentisering:** Sørg for at token sendes korrekt
- **Sjekk URL:** Verifiser at URL-en er korrekt
- **Sjekk CORS:** Hvis du får CORS-feil, må backend tillate requests fra appen
- **Sjekk nettverk:** Test at API-endepunktet fungerer i nettleseren først

### Autentisering fungerer ikke

- **Sjekk headers:** Verifiser at Authorization-header sendes
- **Test i Postman:** Test API-endepunktet med token i Postman først
- **Sjekk token:** Verifiser at token er gyldig og ikke utløpt

### WebView laster ikke

- **Sjekk JavaScript:** Aktiver JavaScript Mode hvis nødvendig
- **Sjekk nettverksrettigheter:** Sørg for at appen har internett-tilgang
- **Sjekk SSL:** Hvis du bruker HTTPS, sjekk at sertifikatet er gyldig

## Eksempel: Full implementasjon i FlutterFlow

### 1. Opprett en side: "Product Declaration"

1. **Legg til AppBar** med tittel "Produktfråsegn"
2. **Legg til Custom Widget:**
   - Velg `ProductDeclarationWebView`
   - Sett **apiBaseUrl** til: `FFAppConstants().apiBaseUrl` (eller `FFAppState().apiBaseUrl` hvis lagret i App State)
   - Sett **authToken** til: `FFAppState().authToken`
   - Sett **width** til: `MediaQuery.of(context).size.width`
   - Sett **height** til: `MediaQuery.of(context).size.height - AppBar height`

### 2. Legg til navigasjon fra meny eller innstillinger

1. **I menyen eller innstillinger:**
   - Legg til en **Button** eller **ListTile**
   - Sett **onTap** til å navigere til "Product Declaration"-siden

### 3. Alternativ: Vis i modal

1. **Opprett en Action:**
   - Navn: `ShowProductDeclaration`
   - Type: Custom Action
   - Kode:
   ```dart
   showModalBottomSheet(
     context: context,
     isScrollControlled: true,
     backgroundColor: Colors.transparent,
     builder: (context) => Container(
       height: MediaQuery.of(context).size.height * 0.9,
       decoration: const BoxDecoration(
         color: Colors.white,
         borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
       ),
       child: Column(
         children: [
           // Header med lukk-knapp
           Container(
             padding: const EdgeInsets.all(16),
             child: Row(
               mainAxisAlignment: MainAxisAlignment.spaceBetween,
               children: [
                 const Text(
                   'Produktfråsegn',
                   style: TextStyle(
                     fontSize: 20,
                     fontWeight: FontWeight.bold,
                   ),
                 ),
                 IconButton(
                   icon: const Icon(Icons.close),
                   onPressed: () => Navigator.pop(context),
                 ),
               ],
             ),
           ),
           // WebView
           Expanded(
             child: ProductDeclarationWebView(
               apiBaseUrl: FFAppConstants().apiBaseUrl, // eller FFAppState().apiBaseUrl
               authToken: FFAppState().authToken,
             ),
           ),
         ],
       ),
     ),
   );
   ```

## Testing

1. **Test i FlutterFlow Preview:**
   - Bygg og kjør appen
   - Naviger til produktfråsegn-siden
   - Verifiser at innholdet lastes

2. **Test på enhet:**
   - Deploy til test-enhet
   - Test med faktisk API
   - Verifiser autentisering fungerer

3. **Test nettverksfeil:**
   - Test uten internett
   - Test med ugyldig token
   - Test med feil URL

## Relaterte dokumenter

- [Produktfråsegn API Dokumentasjon](../compliance/PRODUKTFRASEGN_USAGE.md)
- [FlutterFlow Custom Actions Guide](./FLUTTERFLOW_IMPLEMENTATION_GUIDE.md)

