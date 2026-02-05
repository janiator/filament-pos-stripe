# Additional Compliance Requirements from Skatteetaten FAQ

## Overview

This document captures important compliance clarifications and requirements from the [Skatteetaten FAQ on cash register systems](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/kassasystem/sporsmal-og-svar-om-nye-kassasystemer/) that are not explicitly stated in the official Kassasystemforskriften but provide critical operational guidance.

**Reference:** [Skatteetaten FAQ - Spørsmål og svar om nye kassasystemer](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/kassasystem/sporsmal-og-svar-om-nye-kassasystemer/)

---

## 1. Daily Z-Report Production Requirement

### FAQ Clarification

**Question:** "Z-rapporten skal utføres hver dag, men hva med utskrift? Skal denne skrives ut til papir eller PDF idet man utfører Z-rapporten, eller er det nok at Z-rapporten utføres (og får løpenummer), men kan hentes frem og skrives på et senere tidspunkt?"

**Answer:** "Den bokføringspliktiges bruk av kassasystemet vil bli regulert i bokføringsforskriften. I likhet med dagens regler skal det foretas daglige kassaoppgjør. I forslag til ny § 5a-14 skal Z-rapporten enten skrives ut på papir eller lagres elektronisk i kassasystemet. Dette medfører at dagsoppgjøret kan gjøres elektronisk. **Rapporten må derfor produseres ferdig hver dag og det er ikke tilstrekkelig at disse kan produseres ved en eventuell kontroll.**"

### Key Requirement

✅ **Z-reports must be PRODUCED daily** - Not just available for later production. The report must be generated and stored (either printed or electronically) at the end of each day.

### Implementation Status

- ✅ System allows Z-reports to be generated daily
- ✅ Z-reports can be stored electronically
- ✅ Z-reports can be exported as PDF
- ⚠️ **Recommendation:** Consider adding automated daily Z-report generation reminders or enforcement

---

## 2. Z-Report Per Cash Point Per Day

### FAQ Clarification

**Question:** "Kan det være en felles Z-rapport for alle kassapunktene i en virksomhet, eller skal det være Z-rapporter pr. kassapunkt, eventuelt pr. operatør?"

**Answer:** "En Z-rapport viser et sammendrag av registreringene i kassasystemet i løpet av en dag. Etter at Z-rapport er produsert, skal registreringene i kassasystemet nullstilles slik at de ikke kommer med på neste Z-rapport. **Det skal kun være én Z-rapport pr. kassapunkt pr. dag (dagsrapport).**"

### Key Requirements

1. **One Z-report per cash point per day** - Not a combined report for all cash points
2. **One Z-report per operator** - If operators have separate cash drawers, each operator needs their own Z-report
3. **Data must be reset** - After Z-report is produced, registrations must be reset so they don't appear on the next Z-report

### Implementation Status

- ✅ System generates Z-reports per session (which maps to cash point/operator)
- ✅ Each session can have its own Z-report
- ✅ System tracks sessions separately
- ⚠️ **Note:** Ensure sessions are properly closed daily to align with this requirement

---

## 3. Multiple Operators with Separate Cash Drawers

### FAQ Clarification

**Question:** "Hvordan skal inngående vekselkasse registreres dersom flere operatører har egne «kassaskuffer»?"

**Answer:** "I slike tilfeller skal vekselkassen registreres for hver enkelt operatør. Vekselkassen skal være registrert før operatøren tar i bruk kassasystemet til å registrere salg."

### Key Requirements

1. **Separate opening balance per operator** - Each operator with their own cash drawer must register their own opening balance
2. **Register before use** - Opening balance must be registered before the operator starts using the system
3. **Daily reconciliation per operator** - Each operator must reconcile their own cash at end of day

### Implementation Status

- ✅ System supports opening balance per session
- ✅ Each session can have its own opening balance
- ✅ System tracks cash per session/operator
- ✅ Z-reports show operator-specific data

---

## 4. Z-Report Must Be Produced Even If No Activity

### FAQ Clarification

**Question:** "Må det produseres daglige Z-rapporter også for kassapunkt som ikke har vært i bruk i løpet av dagen?"

**Answer:** "Nei, dersom det ikke er foretatt registreringer, skuffeåpninger eller annen bruk fra det aktuelle kassapunktet, er det ikke krav om å produsere en Z-rapport og foreta dagsoppgjør for kassapunktet."

### Key Requirement

✅ **Z-reports are NOT required** for cash points that had no activity (no registrations, no drawer opens, no other use) during the day.

### Implementation Status

- ✅ System only generates Z-reports for sessions that have activity
- ✅ This aligns with the FAQ guidance

---

## 5. Manual Cash Drawer Opening (Nødåpning)

### FAQ Clarification

**Question:** "Skal manuell åpning ("nødåpning") av kassaskuffen registreres?"

**Answer:** "Det er ikke krav om at manuell åpning av kassaskuff registreres, med mindre kassasystemet har funksjonalitet for dette. [...] Når kassaskuffen "nødåpnes" vil dette kunne skyldes strømbrudd e.l., hvor kassaskuffen må åpnes "manuelt" ved bruk av en særskilt knapp, nøkkel e.l. Opplysningene om at kassaskuffen har blitt åpnet, vil da ikke kunne fremgå av elektronisk journal, og kreves da heller ikke spesifisert i X- og Z-rapport. **I den grad bruk av "nødåpningsknapp" kan registreres i elektronisk journal, skal slik bruk inngå oppsummerte tall over skuffåpninger.**"

### Key Requirements

1. **Manual opening not required to be logged** - If system doesn't have functionality to detect manual opening (e.g., due to power failure), it doesn't need to be logged
2. **If system CAN detect it** - If the system has functionality to register manual opening, it MUST be included in the drawer opening counts in X- and Z-reports

### Implementation Status

- ✅ System tracks drawer opens via events
- ✅ Nullinnslag (drawer opens without sale) are tracked
- ⚠️ **Note:** Manual emergency opens that can't be detected by the system are not required to be logged

---

## 6. Cash Withdrawals and Deposits

### FAQ Clarification

**Question:** "Hvordan skal kontantinnskudd og kontantuttak fra kassen registreres, og skal dette rapporteres som en egen post i X- og Z-rapport?"

**Answer:** "Det er ikke noe krav om at inn- og utbetalinger må registreres i kassasystemet, men det er heller ikke forbud mot en slik funksjon. **Dersom inn- og utbetalinger registreres på kassapunktet må slike betalinger spesifiseres i X- og Z-rapport på antall, type og beløp**, jf. kassasystemforskrifta § 2-8-2 annet ledd siste punktum."

### Key Requirements

1. **Optional to register** - Cash withdrawals/deposits don't have to be registered in the system
2. **If registered, must be in reports** - If the system has functionality to register withdrawals/deposits, they MUST appear in X- and Z-reports with count, type, and amount
3. **Separate documentation required** - If not registered in system, separate documentation must be created (per bokføringsforskriften § 5-3-8)

### Implementation Status

- ✅ **Implemented:** System has functionality to register cash withdrawals and deposits (API: `POST /pos-sessions/{id}/cash-withdrawal`, `POST /pos-sessions/{id}/cash-deposit`). Events are stored as PosEvent (13028 = withdrawal, 13029 = deposit) and appear in X- and Z-reports with count, type, and amount per § 2-8-2.

---

## 7. Parked Receipts (Parkerte Bonger)

### FAQ Clarification

**Question:** "Hva menes med parkerte bonger?"

**Answer:** "Etter kassasystemforskrifta § 2-8-3 annet ledd skal det ikke være mulig å utarbeide en Z-rapport uten at alle salg er avsluttet. [...] Det er ikke noe i veien for å ha en funksjon for å «parkere bonger» i kassasystemet. Bongen må imidlertid avsluttes enten ved å bli registrert som ordinært kontantsalg eller ved at salget avbrytes før det utarbeides en Z-rapport. **Parkerte bonger kan altså ikke stå i systemet til neste dag.**"

### Key Requirements

1. **Cannot generate Z-report with parked receipts** - All sales must be completed before Z-report can be generated
2. **Parked receipts must be resolved** - Either completed as sale or cancelled before Z-report
3. **Cannot carry over to next day** - Parked receipts cannot remain in the system until the next day

### Implementation Status

- ✅ System requires sessions to be closed before Z-reports can be generated
- ✅ All transactions must be completed before session closure
- ✅ This aligns with the requirement

---

## 8. Price Inquiries (Prisundersøkelser)

### FAQ Clarification

**Question:** "Hva menes med "prisundersøkelse" som skal fremgå av X og Z-rapport?"

**Answer:** "Med prisundersøkelse menes skanning av vare eller inntasting av varekode for å undersøke pris, uten at varen blir registrert som varelinje i en salgskvittering. X- og Z rapport skal inneholde antall prisundersøkelser og spesifisert på varegruppe og beløp, jf. kassasystemforskrifta § 2-8-2 bokstav t, jf. § 2-8-3."

### Key Requirements

1. **Must be logged** - Price inquiries (scanning/inputting item code to check price without sale) must be logged
2. **Must appear in reports** - Count and amount must be specified by product group in X- and Z-reports
3. **Purpose** - To prevent price inquiries from being used instead of registering sales

### Implementation Status

- ⚠️ **Current Status:** System does not currently track price inquiries
- ⚠️ **Recommendation:** If price inquiry functionality is added, it must be logged and appear in reports

---

## 9. Line Corrections (Linjekorreksjoner)

### FAQ Clarification

**Question:** "Skal både økning og reduksjon av antall enheter vises som linjekorreksjon i X- og Z-rapport?"

**Answer:** "Kun reduksjon av antall skal vises som linjekorreksjon. [...] Skattedirektoratet legger til grunn at økning av antall ikke skal anses som feilslag, og da heller ikke som en linjekorreksjon som skal fremgå av X- og Z-rapport."

### Key Requirements

1. **Only reductions count** - Only reductions in quantity count as line corrections
2. **Increases don't count** - Increases in quantity are not considered line corrections
3. **Must specify type and amount** - Line corrections must be specified by type and amount in X- and Z-reports

### Implementation Status

- ⚠️ **Current Status:** System does not currently track line corrections separately
- ⚠️ **Recommendation:** If line correction functionality is needed, only reductions should be counted

---

## 10. Discounts (Rabatter)

### FAQ Clarification

**Question:** "Når kreves rabatter spesifisert i X- og Z-rapport?"

**Answer:** "Det er kun rabatt som gis på kassapunktet (prisen korrigeres manuelt i kassen) som skal spesifiseres i X- og Z rapport. Rabatter som blir korrigert automatisk i kassasystemet, f.eks. kampanjer som "ta 3 betal for 2" skal dermed ikke spesifiseres."

### Key Requirements

1. **Manual discounts only** - Only manually applied discounts at the cash point need to be specified
2. **Automatic discounts excluded** - Automatically applied discounts (campaigns, promotions) don't need to be specified
3. **Must show count and amount** - Manual discounts must show count and amount in X- and Z-reports

### Implementation Status

- ⚠️ **Current Status:** System does not currently distinguish between manual and automatic discounts
- ⚠️ **Recommendation:** If discount tracking is needed, ensure only manual discounts are counted for reporting

---

## 11. X-Report Must Be Fully Logged in Electronic Journal

### FAQ Clarification

**Question:** "Dersom det blir generert en X-rapport, skal X-rapporten da i sin helhet logges i elektronisk journal?"

**Answer:** "Ja, hele rapporten skal vises i elektronisk journal, ikke bare tidspunktet for generering."

### Key Requirement

✅ **Complete X-report must be logged** - The entire X-report content must be stored in the electronic journal, not just the timestamp of generation.

### Implementation Status

- ✅ System logs X-report generation as event 13008
- ⚠️ **Recommendation:** Ensure the complete report data is included in the event data for electronic journal compliance

---

## 12. Cash Drawer Must Provide Feedback

### FAQ Clarification

**Question:** "Kassasystemet får ikke noe tilbakemelding fra kasseskuffen når den er åpen eller ikke. Er det ok?"

**Answer:** "Nei, dette er ikke i henhold til regelverket. Etter kassasystemforskrifta § 2-6 femte ledd er det et krav om at det ikke skal være mulig å registrere salg i kassasystemet dersom integrert kassaskuff er åpen. Dersom kassasystemet ikke har en sperre for dette, vil systemet ha en funksjon som ikke er tillatt. Det må derfor innarbeides en funksjon i programmet som gir kassasystemet beskjed dersom kassaskuffen er åpen."

### Key Requirement

✅ **Cash drawer must provide status feedback** - The system must know when the drawer is open and prevent sales registration when open.

### Implementation Status

- ✅ System tracks drawer open/close events
- ✅ System can detect drawer state
- ✅ This aligns with the requirement

---

## Summary of Additional Requirements

### Critical Daily Operations

1. ✅ **Z-reports must be PRODUCED daily** (not just available)
2. ✅ **One Z-report per cash point per day**
3. ✅ **One Z-report per operator** (if separate cash drawers)
4. ✅ **Data must reset after Z-report** (so it doesn't appear on next report)

### Optional but Important Features

1. ⚠️ **Price inquiries** - If implemented, must be logged and appear in reports
2. ⚠️ **Line corrections** - If implemented, only reductions count
3. ⚠️ **Manual discounts** - If implemented, must be specified in reports
4. ⚠️ **Cash withdrawals/deposits** - If implemented, must appear in reports

### System Requirements

1. ✅ **Complete X-report in electronic journal** - Full report data must be logged
2. ✅ **Cash drawer feedback** - System must know drawer state
3. ✅ **No parked receipts at day end** - All sales must be completed before Z-report

---

## References

- [Skatteetaten FAQ - Spørsmål og svar om nye kassasystemer](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/kassasystem/sporsmal-og-svar-om-nye-kassasystemer/)
- [Kassasystemforskriften FOR-2015-12-18-1616](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)




