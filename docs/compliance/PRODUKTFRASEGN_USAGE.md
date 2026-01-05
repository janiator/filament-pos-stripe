# Produktfråsegn - Bruksveiledning

## Oversikt

Produktfråsegn-systemet lar deg administrere og vise produktfråsegner (produktdeklarasjoner) for kassasystemet. Hver butikk (tenant) kan ha sin egen produktfråsegn som kan vises i POS-systemet.

## Funksjoner

### 1. Administrasjon via Filament

Produktfråsegner administreres gjennom Filament admin-panelet:

- **Navigasjon:** Compliance → Produktfråsegn
- **Opprett ny:** Klikk "Ny" for å opprette en ny produktfråsegn
- **Rediger:** Klikk på en eksisterende produktfråsegn for å redigere
- **Aktiv status:** Kun én aktiv produktfråsegn per butikk

### 2. Felter i produktfråsegn

- **Butikk:** Butikken produktfråsegna gjelder for (automatisk satt basert på tenant)
- **Produktnavn:** Navn på kassasystemet (standard: "POS Stripe Backend - Kassasystem")
- **Leverandør:** Navn på leverandør
- **Versjon:** Versjonsnummer (f.eks. "1.0.0")
- **Versjonsidentifikasjon:** Unik identifikasjon (f.eks. "POS-STRIPE-BACKEND-1.0.0")
- **Dato:** Dato for produktfråsegna
- **Aktiv:** Om produktfråsegna er aktiv (kun én aktiv per butikk)
- **Innhold:** Full produktfråsegn i Markdown-format

### 3. Standardinnhold

Når du oppretter en ny produktfråsegn, lastes standardinnholdet automatisk fra `docs/compliance/PRODUKTFRASEGN.md`. Du kan deretter redigere innholdet etter behov.

### 4. API-endepunkter

#### Hent produktfråsegn (JSON)

**GET** `/api/product-declaration`

Returnerer aktiv produktfråsegn for gjeldende butikk som JSON.

**Response:**
```json
{
  "data": {
    "id": 1,
    "product_name": "POS Stripe Backend - Kassasystem",
    "vendor_name": "Leverandør navn",
    "version": "1.0.0",
    "version_identification": "POS-STRIPE-BACKEND-1.0.0",
    "declaration_date": "2025-12-12",
    "content": "# Produktfråsegn...",
    "created_at": "2025-12-12T12:00:00.000+01:00",
    "updated_at": "2025-12-12T12:00:00.000+01:00"
  }
}
```

#### Vis produktfråsegn (HTML)

**GET** `/api/product-declaration/display`

Returnerer produktfråsegna som en HTML-side som kan embeddes i POS-systemet.

**Bruksområde:**
- Visning i POS-applikasjonen
- Print/PDF-generering
- WebView i FlutterFlow

**Autentisering:**
Krever autentisert bruker med tilgang til butikken.

## Integrasjon i POS (FlutterFlow)

### Visning i POS-applikasjonen

1. **Hent produktfråsegn via API:**
   ```dart
   final response = await http.get(
     Uri.parse('$baseUrl/api/product-declaration'),
     headers: {
       'Authorization': 'Bearer $token',
       'Content-Type': 'application/json',
     },
   );
   ```

2. **Vis i WebView:**
   ```dart
   WebView(
     initialUrl: '$baseUrl/api/product-declaration/display',
     // ... WebView konfigurasjon
   )
   ```

3. **Eller konverter Markdown til HTML:**
   - Bruk en Markdown-bibliotek (f.eks. `flutter_markdown`)
   - Vis innholdet i en scrollbar widget

### Eksempel: FlutterFlow Custom Action

```dart
Future<void> showProductDeclaration() async {
  try {
    final response = await FFAppState().apiClient.get(
      '/api/product-declaration',
    );
    
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body)['data'];
      final content = data['content'];
      
      // Vis i WebView eller konverter Markdown til HTML
      // ...
    }
  } catch (e) {
    // Håndter feil
  }
}
```

## Markdown-støtte

Produktfråsegna støtter standard Markdown-syntaks:

- **Headers:** `# H1`, `## H2`, `### H3`
- **Bold:** `**tekst**`
- **Italic:** `*tekst*`
- **Lists:** `- item` eller `1. item`
- **Code:** `` `code` `` eller ` ```code block``` ``
- **Links:** `[tekst](url)`
- **Horizontal rules:** `---`

**Merk:** For produksjon, vurder å installere et dedikert Markdown-bibliotek (f.eks. `league/commonmark`) for bedre støtte.

## Best Practices

1. **Én aktiv per butikk:** Sørg for at kun én produktfråsegn er aktiv per butikk
2. **Versjonskontroll:** Oppdater versjonsnummer og versjonsidentifikasjon ved endringer
3. **Dato:** Oppdater dato når produktfråsegna endres
4. **Innhold:** Hold innholdet oppdatert med faktiske systemfunksjoner
5. **Backup:** Lag backup av produktfråsegner før større endringer

## Feilsøking

### Ingen aktiv produktfråsegn funnet

- Sjekk at det finnes en produktfråsegn for butikken
- Sjekk at produktfråsegna er markert som aktiv
- Sjekk at brukeren har tilgang til butikken

### Markdown vises ikke korrekt

- Sjekk Markdown-syntaks
- Vurder å installere et dedikert Markdown-bibliotek
- Test i HTML-visningen først (`/api/product-declaration/display`)

## Relaterte dokumenter

- [Produktfråsegn mal](./PRODUKTFRASEGN.md) - Standard mal for produktfråsegn
- [Kassasystemforskriften Compliance](./KASSASYSTEMFORSKRIFTEN_COMPLIANCE.md) - Juridisk compliance




