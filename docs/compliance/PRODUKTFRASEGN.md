# Produktfråsegn - Kassasystem

## 1. Generell informasjon

### 1.1 Produktnavn
POSitiv

### 1.2 Leverandør
Visivo AS

### 1.3 Versjon
1.0.2

### 1.4 Versjonsidentifikasjon
POSitiv-1.0.2

### 1.5 Dato for produktfråsegn
Dette dokumentet gjelder for alle versjoner av POSitiv kassasystem.

---

## 2. Systemskildring

### 2.1 Oversikt
Dette kassasystemet er et elektronisk kassasystem (POS-system) utviklet for å registrere kontantsalg og håndtere transaksjoner i henhold til norsk kassasystemforskrift (FOR-2015-12-18-1616). Systemet er bygget på Laravel 11 backend med FlutterFlow frontend og integrerer Stripe Terminal for betalingshåndtering.

### 2.2 Teknisk plattform
- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** FlutterFlow (Flutter/Dart)
- **Database:** MySQL/PostgreSQL
- **Betalingsterminal:** Stripe Terminal (integrert)
- **Arkitektur:** REST API-basert, multi-tenant

### 2.3 Systemkomponenter

#### 2.3.1 Kassapunkt
- Mobil enheter (iPad, Android-tabletter)
- Stasjonære enheter (PC, nettbrett)
- Hver enhet registreres som eget kassapunkt (PosDevice)
- Hvert kassapunkt kan ha egen kassaskuff og betalingsterminal

#### 2.3.2 Kassaskuff
- Integrert kassaskuff via Stripe Terminal eller ekstern kassaskuff
- Kassaskuffen kan åpnes ved kontantsalg
- Kassaskuffen kan åpnes uten salg (nullinnslag) - dette logges i elektronisk journal
- Systemet forhindrer registrering av salg når kassaskuffen er åpen

#### 2.3.3 Skriver
- Systemet støtter utskrift av kvitteringer
- Kvitteringer kan skrives ut til termalskriver eller PDF
- Alle kvitteringstyper støttes (salskvittering, returkvittering, kopi, STEB, foreløpig, treningskvittering, utleveringskvittering)

#### 2.3.4 Betalingsterminal
- Integrert Stripe Terminal for kortbetalinger
- Støtter kontaktløs betaling (NFC)
- Støtter chip og PIN
- Støtter også kontantbetaling og andre betalingsmetoder

---

## 3. Funksjonar som kassasystemet skal ha (§ 2-5)

### 3.1 Registrering av kontantsalg
- ✅ Fullstendig registrering av alle kontantsalg
- ✅ Registrering av varer og tjenester med mengde og pris
- ✅ Beregning av totalsum, mva og rabatter
- ✅ Registrering av betalingsmetode
- ✅ Knyttet til kassapunkt og operatør

### 3.2 Salskvittering (§ 2-8-4)
- ✅ Generering av salskvittering for hvert kontantsalg
- ✅ Fortløpende nummerering per butikk
- ✅ Inneholder:
  - Butikknavn og adresse
  - Kvitteringsnummer
  - Dato og klokkeslett
  - Transaksjons-ID
  - Varelinjer med mengde og pris
  - Delsum, mva og totalsum
  - Betalingsmetode
  - Kassabruker (operatør)
  - Sesjonsnummer

### 3.3 Returkvittering (§ 2-8-5)
- ✅ Generering av returkvittering ved retur
- ✅ Tydelig merket "Returkvittering" øverst på kvitteringen
- ✅ Fortløpende nummerering i egen nummerserie
- ✅ Referanse til opprinnelig salskvittering

### 3.4 Andre kvitteringstyper (§ 2-8-6, 2-8-7)
- ✅ Kopikvittering - merket "KOPI"
- ✅ STEB-kvittering - merket "STEB-kvittering"
- ✅ Foreløpig kvittering - merket "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP"
- ✅ Treningskvittering - merket "Treningskvittering – IKKJE KVITTERING FOR KJØP"
- ✅ Utleveringskvittering - merket "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
- ✅ Alle merkingene har minst 50% større font enn beløpstekst

### 3.5 X-rapport (§ 2-8-2)
- ✅ Generering av X-rapport som viser sammendrag av gjeldende sesjon
- ✅ X-rapporten lukker IKKE sesjonen
- ✅ Inneholder:
  - Antall transaksjoner
  - Totale beløp
  - Oppdeling på betalingsmetode
  - Kontantbeløp
  - Kortbeløp
  - Antall kassaskuffåpninger
  - Antall nullinnslag
  - Dato og klokkeslett for rapportgenerering

### 3.6 Z-rapport (§ 2-8-3)
- ✅ Generering av Z-rapport ved avslutning av sesjon
- ✅ Z-rapporten LUKKER sesjonen
- ✅ Inneholder:
  - Fullstendig sammendrag av alle transaksjoner
  - Forventet kontantbeløp
  - Faktisk kontantbeløp (ved opptelling)
  - Kontantavvik
  - Komplett transaksjonsliste
  - Alle hendelser i sesjonen
- ✅ Data nullstilles etter Z-rapport slik at de ikke kommer med på neste rapport

### 3.7 Elektronisk journal (§ 2-7)
- ✅ Alle transaksjoner logges i elektronisk journal
- ✅ Alle systemhendelser logges (PredefinedBasicID-13 event codes)
- ✅ Journalen er uforanderlig (transaksjoner kan ikke slettes eller endres)
- ✅ Journalen kan eksporteres i SAF-T format
- ✅ Journalen inneholder:
  - Alle salgstransaksjoner (event 13012)
  - Alle returtransaksjoner (event 13013)
  - Sesjonsåpning (event 13020)
  - Sesjonslukking (event 13021)
  - Kassaskuffåpninger (event 13005, 13006)
  - Nullinnslag (kassaskuffåpning uten salg)
  - X-rapportgenerering (event 13008)
  - Z-rapportgenerering (event 13009)
  - Brukerinnlogging/utlogging (event 13003, 13004)
  - Applikasjonsstart/stopp (event 13001, 13002)

### 3.8 Brukerautentisering (§ 2-5)
- ✅ Brukere må autentiseres før bruk av systemet
- ✅ Alle transaksjoner knyttes til autentisert bruker
- ✅ Brukerinnlogging og utlogging logges i elektronisk journal

### 3.9 Sesjonshåndtering
- ✅ Hver operatør starter egen sesjon
- ✅ Sesjoner må åpnes før registrering av salg
- ✅ Sesjoner må lukkes med Z-rapport
- ✅ Hver sesjon har eget sesjonsnummer
- ✅ Støtter flere operatører med egne kassaskuffer

### 3.10 SAF-T eksport
- ✅ Systemet kan eksportere elektronisk journal i SAF-T Cash Register format
- ✅ Eksporten inneholder alle påkrevde felt og koder
- ✅ Eksporten kan genereres for valgt tidsperiode

---

## 4. Funksjonar som kassasystemet ikkje skal ha (§ 2-6)

### 4.1 Sletting av transaksjoner
- ✅ Transaksjoner kan IKKE slettes
- ✅ Transaksjoner kan IKKE endres etter registrering
- ✅ Kun soft delete er mulig (for administrative formål, men transaksjonen forblir i journalen)

### 4.2 Omging av sikkerhetsfunksjoner
- ✅ Systemet forhindrer omgåelse av sikkerhetsfunksjoner
- ✅ Alle transaksjoner må gå gjennom normal registreringsprosess
- ✅ Ingen mulighet for å hoppe over validering eller logging

### 4.3 Deaktivering av logging
- ✅ Elektronisk journal kan IKKE deaktiveres
- ✅ Alle transaksjoner logges automatisk
- ✅ Logging kan ikke omgås

### 4.4 Registrering av salg når kassaskuff er åpen
- ✅ Systemet forhindrer registrering av salg når integrert kassaskuff er åpen
- ✅ Kassaskuffen må være lukket før nytt salg kan registreres

---

## 5. Krav til språk (§ 2-4)

### 5.1 Norsk språkstøtte
- ✅ Systemet støtter norsk språk
- ✅ Alle brukergrensesnitttekster er på norsk
- ✅ Alle kvitteringer er på norsk
- ✅ Alle rapporter er på norsk
- ✅ Alle feilmeldinger er på norsk

---

## 6. Kassaskuff (§ 2-2)

### 6.1 Integrert kassaskuff
- ✅ Kassaskuffen er integrert med kassasystemet
- ✅ Kassaskuffen åpnes automatisk ved kontantsalg
- ✅ Kassaskuffen kan åpnes manuelt (nullinnslag) - dette logges

### 6.2 Nullinnslag
- ✅ Åpning av kassaskuff uten salg (nullinnslag) logges i elektronisk journal
- ✅ Nullinnslag vises i X- og Z-rapporter
- ✅ Nullinnslag har egen event-kode i elektronisk journal

### 6.3 Tilbakemelding fra kassaskuff
- ✅ Systemet mottar tilbakemelding om kassaskuffens status (åpen/lukket)
- ✅ Systemet forhindrer registrering av salg når kassaskuffen er åpen

---

## 7. Skriver (§ 2-3)

### 7.1 Kvitteringsutskrift
- ✅ Systemet støtter utskrift av alle kvitteringstyper
- ✅ Kvitteringer kan skrives ut til termalskriver eller lagres som PDF
- ✅ Alle kvitteringer har fortløpende nummerering

### 7.2 Kvitteringsformat
- ✅ Alle kvitteringer følger krav i § 2-8
- ✅ Fontstørrelse for merking er minst 50% større enn beløpstekst
- ✅ Alle påkrevde felter er inkludert

---

## 8. Sikkerhet og dataintegritet

### 8.1 Transaksjonsintegritet
- ✅ Alle transaksjoner er uforanderlige etter registrering
- ✅ Transaksjoner kan ikke slettes eller endres
- ✅ Komplett revisjonsspor (audit trail)

### 8.2 Databeskyttelse
- ✅ Alle data krypteres i transit (HTTPS)
- ✅ Følsomme data krypteres i ro
- ✅ Tilgangskontroll basert på brukerroller

### 8.3 Backup og gjenoppretting
- ✅ Regelmessig backup av database
- ✅ Elektronisk journal kan gjenopprettes
- ✅ SAF-T eksport kan brukes til gjenoppretting

### 8.4 Digitale signaturer
- ✅ **Unntak**: Systemet er unntatt fra krav om digitale signaturer
- ✅ **Begrunnelse**: Leverandøren har driftsansvar og tilgangskontroll, og den bokføringspliktige har kun tilgang til brukergrensesnittet
- ✅ **Tilgangskontroll**: 
  - Den bokføringspliktige kan kun få tilgang til den elektroniske journalen gjennom applikasjonens funksjoner
  - Ingen direkte databasetilgang for den bokføringspliktige
  - Alle dataaksesser skjer gjennom autentiserte API-endepunkter eller web-grensesnitt
- ✅ **Driftsansvar**: Leverandøren kontrollerer:
  - Database-tilgang
  - Serverinfrastruktur
  - Applikasjonsdistribusjon
  - Alle systemoperasjoner
- 📋 **Referanse**: Se [Digital Signatures Requirements](DIGITAL_SIGNATURES_REQUIREMENTS.md) for detaljer om unntaket

---

## 9. Tekniske krav

### 9.1 Systemkrav
- **Backend:** PHP 8.2 eller høyere
- **Database:** MySQL 8.0+ eller PostgreSQL 13+
- **Webserver:** Nginx eller Apache
- **Frontend:** Flutter/Dart (FlutterFlow)

### 9.2 Nettverk
- ✅ Støtter både kablet og trådløst nettverk
- ✅ HTTPS påkrevd for all kommunikasjon
- ✅ API-basert arkitektur

### 9.3 Integrasjoner
- ✅ Stripe Terminal for betalingshåndtering
- ✅ REST API for frontend-integrasjon
- ✅ SAF-T eksport for skattemyndighetene

---

## 10. Multi-tenant støtte

### 10.1 Flere butikker
- ✅ Systemet støtter flere butikker (multi-tenant)
- ✅ Hver butikk har egen konfigurasjon
- ✅ Data er isolert per butikk

### 10.2 Flere kassapunkter
- ✅ Hver butikk kan ha flere kassapunkter
- ✅ Hvert kassapunkt kan ha egen kassaskuff
- ✅ Hvert kassapunkt genererer egne rapporter

---

## 11. Produktinformasjon

### 11.1 Leveringsomfang
- Backend API (Laravel)
- Frontend applikasjon (FlutterFlow)
- Dokumentasjon
- Teknisk støtte

### 11.2 Vedlikehold og oppdateringer
- Systemet kan oppdateres uten tap av data
- Elektronisk journal bevares ved oppdateringer
- Versjonskontroll av alle endringer

### 11.3 Støtte
- Teknisk støtte tilgjengelig
- Dokumentasjon tilgjengelig
- Oppdateringer og sikkerhetspatcher

---

## 12. Overensstemmelse med regelverk

### 12.1 Kassasystemforskriften
Dette kassasystemet er utviklet for å overholde:
- ✅ **Forskrift om krav til kassasystem** (FOR-2015-12-18-1616)
- ✅ **Lov om krav til kassasystem** (LOV-2015-06-19-58)

### 12.2 SAF-T Cash Register
- ✅ Systemet støtter SAF-T Cash Register eksport
- ✅ Alle påkrevde event-koder er implementert
- ✅ Alle påkrevde felt er inkludert

### 12.3 Skatteetatens krav
- ✅ Systemet oppfyller alle krav fra Skatteetaten
- ✅ Daglige Z-rapporter kan produseres
- ✅ Elektronisk journal kan eksporteres

---

## 13. Erklæring

Denne produktfråsegna dokumenterer at POSitiv kassasystem er utviklet og vedlikeholdt i henhold til kravene i kassasystemforskriften (FOR-2015-12-18-1616).

**Leverandør:** Visivo AS  
**Kontakt:** support@visivo.no  
**Systemversjon:** POSitiv 1.0.2

### Erklæring om overensstemmelse

Visivo AS erklærer at:

1. **Systeminformasjon**: Informasjonen i denne produktfråsegna er korrekt og oppdatert for POSitiv versjon 1.0.2.

2. **Regelverksoverensstemmelse**: POSitiv kassasystem oppfyller alle krav i kassasystemforskriften (FOR-2015-12-18-1616) og lov om krav til kassasystem (LOV-2015-06-19-58).

3. **Funksjonalitet**: Alle påkrevde funksjoner er implementert og fungerer i henhold til spesifikasjonene i denne produktfråsegna.

4. **Vedlikehold**: Systemet vedlikeholdes kontinuerlig for å sikre pågående overensstemmelse med regelverket.

5. **Driftsansvar**: Visivo AS har driftsansvar for systemet og kontrollerer all tilgang til elektronisk journal og systemdata.

Denne erklæringen gjelder for alle butikker som bruker POSitiv kassasystem levert og vedlikeholdt av Visivo AS.

---

## 14. Vedlegg

### 14.1 Teknisk dokumentasjon
- Systemarkitektur
- API-dokumentasjon
- Database-skjema
- Sikkerhetsdokumentasjon

### 14.2 Brukermanual
- Brukerveiledning for operatører
- Administrasjonsveiledning
- Feilsøkingsguide

---

**Versjon:** 1.0.2  
**Dokumentstatus:** Gjeldende  
**Sist oppdatert:** 2025-01-27

### Bruk av dette dokumentet

Denne produktfråsegna kan brukes til:
- Innlevering til Skatteetaten via Altinn
- Dokumentasjon av systemets overensstemmelse med regelverket
- Referanse ved skattemyndighetenes kontroller
- Intern dokumentasjon og opplæring

For spørsmål eller oppdateringer, kontakt Visivo AS på support@visivo.no.

