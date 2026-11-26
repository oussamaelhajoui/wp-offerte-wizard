# Offerte Wizard: ontwerp

Datum: 2026-11-25. Status: ontwerp goedgekeurd door gebruiker, spec wacht op review.

## Doel
Een WordPress-plugin (draait op een live Divi-site, afbouwmegens.nl) die de offerte-aanvraagflow van stukadoorsclub.nl/signup nabouwt, in de branding van afbouwmegens.nl, met één extra functie: een offerte aanvragen op basis van een bouwplan (PDF of afbeelding) waaruit de m² worden afgelezen.

## Plaatsing
- Shortcode `[offerte_wizard]`: werkt in een Divi Code- of Tekstmodule in een column.
- `[offerte_wizard fullpage="1"]`: schermvullend zonder thema-header en -footer.
- Attributen per snippet: `mode="auto|ai|tekst|handmatig"`, `bouwplan="aan|uit"`.
- Optie in de instellingen: maak de pagina "Offerte aanvragen" aan of vervang de inhoud van `/offerte-aanvragen`.

## Branding (afbouwmegens.nl)
- Lettertypen: Manrope (koppen), Open Sans (tekst).
- Kleuren: goud `#c39455` (accent), donkerblauw `#07172f` (tekst, knoppen), `#eef5fb` (vlakken), wit.
- Pill-knoppen, kaarten met 16px radius.
- Logo: instelbaar in de plugin; standaard `/wp-content/uploads/2026/08/LOGO-AFBOUW-MEGENS@0.5x.webp`.
- Kleuren, logo en teksten zijn in de instellingen aan te passen.

## Flow
Stappen (id, vraag, gedrag):
1. `welkom`: uitleg (prijs, beschikbaarheid, klusdagen, checklist), knop "Start nu".
2. `type-woning`: Nieuwbouw of Renovatie (enkelvoudig, verplicht).
3. `m2`: "Ja, ik weet het ongeveer", "Nee, help mij even" of (nieuw) "Ik heb een bouwplan".
4. Tak Ja: `m2-wand`, `m2-plafond`, `type-plafond` (Punt dak, Plat dak, Appartement), `schilderen`.
5. Tak Nee: `wand-ondergrond` (meerkeuze: Baksteen of Gevel, Gipsplaten of Korrels, Beton), `schilderen`. Geen m²- of daktype-stappen, geen prijs.
6. Tak Bouwplan: `bouwplan-upload`, `bouwplan-controle` (m² per ruimte, aanpasbaar), daarna zoals tak Ja met ingevulde waarden.
7. `schilderen`: "Graag!" of "Niet nodig". Beide gaan naar de kalender.
8. `kalender`: bereikkeuze (eerste klik start, tweede klik einde). Verleden dagen uitgeschakeld, vandaag kiesbaar, onbeperkt vooruit. Zonder keuze: "Selecteer een periode".
9. `contactgegevens`: voornaam, achternaam, e-mail, telefoon, straat en huisnummer, plaats, postcode. Alles verplicht ("Vul alle velden in"), plus formaatcontrole op e-mail, telefoon en postcode.
10. `beschrijving`: vrije tekst, optioneel.
11. Laadscherm, daarna `overzicht`.

Overzicht: periode, type, werkzaamheden (Stucwerk, en Schilderwerk als gekozen), m² wanden, plafonds en totaal, prijsindicatie per onderdeel, beschikbaarheidstekst met plaatsnaam, schatting klusdagen en droogtijd, checklist.

Algemeen gedrag:
- Elke keuzestap zonder keuze toont "Maak een keuze om door te gaan!".
- Terug-knop op elke stap, voortgangsbalk.
- Antwoorden staan in sessionStorage; verversen herstelt de stap.
- Stap `m2-wand` en `m2-plafond`: getal wordt geparsed (komma of punt, eenheid genegeerd). Leeg of ongeldig vraagt opnieuw. Dit voorkomt de "€NaN" en lege velden uit de bron.

Bron (stukadoorsclub.nl): contactformulier alleen op validatie getest, niet verstuurd. Het overzicht van de Nee-tak en van "Niet nodig" is daarom afgeleid, niet waargenomen.

## Mobiel (harde eis)
- Mobile-first opzet; getest op 375 px breed en op tablet, in de snippet-modus (in een Divi-column) en fullpage.
- Geen horizontaal scrollen; zijmarge 16 px; aanraakdoelen minimaal 48 px hoog.
- Terug en Doorgaan staan vast onderaan het scherm, zodat ze altijd binnen duim-bereik zijn en niet onder het toetsenbord verdwijnen.
- Invoervelden gebruiken de juiste toetsenborden (`inputmode="decimal"` voor m², `type="email"`, `type="tel"`) en `autocomplete`-attributen; lettergrootte minimaal 16 px zodat iOS niet inzoomt.
- Kalender: bereikkeuze met grote dagcellen, werkt met tikken; de maandwissel is groot genoeg om te raken.
- Bouwplan uploaden: op telefoon ook foto maken of kiezen uit de galerij (`accept` + `capture`), afbeeldingen worden vóór upload verkleind in de browser.
- Controlescherm per ruimte is een gestapelde lijst, geen brede tabel.
- Respecteert `prefers-reduced-motion`; stappen wisselen zonder zware animaties.

## Prijsindicatie
- Per onderdeel een range (min en max) per m², inclusief arbeid, materiaal en BTW.
- Schilderwerk: afgeleid uit voorbeeld, ca. 17,57 tot 20,50 per m² (400 m² geeft 7.028 tot 8.199).
- Stucwerk wand en plafond: tijdelijke standaardwaarden, door de eigenaar aan te passen.
- Tak Nee: geen prijs, tekst "we berekenen dit later samen".
- Afronden op hele euro's. Lege of nul-m² geeft geen prijs.

## Bouwplan-route
Upload: PDF, JPG, PNG, WEBP; maximaal 15 MB (instelbaar); controle op MIME en extensie.

Modi:
- **AI**: pagina's gaan naar de Claude- of OpenAI-API (key in instellingen). De API geeft per ruimte afmetingen terug. Klant geeft toestemming via een vinkje. Provider, model en key instelbaar.
- **Tekst**: server-side extractie van maatvoering-tekst uit vector-PDF's, zonder externe dienst.
- **Handmatig**: alleen bijlage in de mail; klant vult m² zelf in of laat leeg.
- **Auto** (standaard): AI als er een key is, anders tekst, anders handmatig. Mislukt een modus, dan valt de plugin terug op handmatig. De aanvraag gaat altijd door.

Berekening (in PHP, getest): plafond = som vloeroppervlak per ruimte; wanden = omtrek × plafondhoogte (standaard 2,6 m, instelbaar). Resultaat per ruimte in het controlescherm, door de klant aanpasbaar voor verzenden.

## Aanvraag en mail
- REST-endpoint `offerte-wizard/v1`: `submit` en `analyse`. Nonce, honeypot, rate limit per IP, sanitizing en servervalidatie.
- Mail via `wp_mail` naar het ontvangeradres uit de instellingen: alle antwoorden, berekende m² en prijsindicatie, bouwplan als bijlage, plus Reply-To klant.
- Optionele bevestigingsmail naar de klant.
- Opslag als privé custom post type "Offerte-aanvragen" in wp-admin.
- Uploads in een afgeschermde map met willekeurige bestandsnamen en een `.htaccess`/index-bescherming; verwijder na instelbaar aantal dagen.
- Cookiebot is aanwezig op de site: de plugin laadt geen trackers.

## Instellingen (wp-admin)
Ontvanger en afzender, bevestigingsmail aan of uit, prijzen per m², plafondhoogte, standaardmodus, AI-provider, model en key (nooit in pagina-output of logs), uploadlimiet en bewaartermijn, logo, kleuren, teksten, paginabeheer.

## Techniek
- Plugin-map `offerte-wizard/` in deze repo, los van de Divi5-MCP-plugin.
- Vanilla JS en CSS zonder build-stap. PHP-klassen: Settings, Pricing, AreaCalculator, PlanExtractor (AI, tekst, handmatig), Submission, Mailer, Rest, Shortcode, Admin.
- Wizard-definitie (stappen, takken, validatie) staat als data in PHP en wordt als JSON aan de front-end gegeven, zodat stappen zonder JS-wijziging aan te passen zijn.
- PHPUnit-tests: Pricing, AreaCalculator, getalparsing, takkenlogica, validatie.
- Oplevering: installeerbare zip. Op de live site wordt niets geïnstalleerd zonder expliciet akkoord van de gebruiker.

## Buiten scope
Klusoverzicht-account, online betalen, kalenderkoppeling, meertaligheid.

## Wijzigingen tijdens het bouwen
- Eigen repo (`Projects/offerte-plugin`), los van de Divi5-MCP-repo.
- Stijlen: Shadow DOM met eigen tag-namen (`offerte-wizard`, `ow-host`) in plaats van een CSS-prefix, zodat thema-CSS er niet in kan.
- Nonce vervangen door een sessietoken (HMAC, opgehaald via REST, pas na 3 seconden geldig, 24 uur geldig): een nonce op een gecachte pagina verloopt voor anonieme bezoekers.
- Pagina-aanmaak: de beheerpagina maakt een conceptpagina aan; bestaande pagina's worden nooit vervangen.
- Bouwplan-tak gaat na de controlestap rechtstreeks naar het daktype (de controlestap vervangt de m²-stappen).
- Zonder uitgelezen ruimtes zijn de m² na een bouwplan-upload optioneel; dan wordt geen prijs getoond.
- Getest in een echte WordPress (Docker): alle drie de takken, upload met tekst-PDF, mail met bijlage, validatie, 375 px breed, vijandige thema-CSS, instellingen opslaan. De AI-route is alleen met nagebootste HTTP-antwoorden getest, niet tegen de echte Claude- of OpenAI-API.

## Goedkeuringsflow (toegevoegd na oplevering 1.0.0)
- Instelbaar: prijs tonen aan de klant (aan/uit); mail naar de klant (uit, ontvangstbevestiging zonder prijs, of meteen de offerte); twee knoppen in de mail aan de eigenaar (offerte direct sturen, aanvraag openen), elk aan/uit.
- Knop "Offerte sturen": ondertekende link (HMAC, 30 dagen geldig) naar een bevestigingspagina; pas een POST verstuurt, zodat mailscanners niets per ongeluk versturen. Een tweede keer meldt "al verstuurd".
- wp-admin, per aanvraag: eigen prijs (vanaf/tot of vast bedrag) met toelichting versturen, opnieuw versturen, of "zelf afgehandeld". Status per aanvraag: nieuw, verstuurd, afgehandeld.
- Niets doen = er gaat niets naar de klant.

## Open punten
- Stucwerkprijzen per m² moeten door de eigenaar worden bevestigd.
- Of de plugin de bestaande pagina `/offerte-aanvragen` vervangt of ernaast staat, wordt bij installatie gekozen.

## Periodekeuze (1.4.0)
Voor de kalender staat de stap `periode` ("Weet je al in welke periode?"): "Ik weet het" gaat door naar het datumbereik; "Op korte termijn", "Binnen 3 maanden" en "3-6 maanden" slaan de kalender over. De gekozen termijn staat in overzicht en mails; datums van een eerdere keuze worden genegeerd. De welkomstpagina beschrijft in eerste persoon wat de klant van ons krijgt (scherpe prijs, samen naar beschikbaarheid kijken, klusduur, checklist).

## Onderdelen (1.5.0)
Stap `onderdelen` na het type woning: alleen wanden, alleen plafonds of beide. Alleen wanden slaat m² plafond en daktype over; alleen plafonds slaat m² wanden over. Bouwplan-controle toont alleen de relevante maten. Een niet-gekozen oppervlak telt nergens mee: ook niet als de uitlezing het wel gaf. Prijs, overzicht ("Stucwerk (wanden)") en mail ("Te stucen: wanden") volgen de keuze. Regels in de flow-definitie hebben meerdere voorwaarden (`all`), identiek geëvalueerd in PHP en JS.

## Eindscherm en klantmail (1.7.0)
Drie onafhankelijke instellingen: (1) eindscherm: prijsrange met instelbare toelichting en optionele eigen extra tekst, of een contact-bericht met instelbare titel en tekst; (2) mail naar de klant: uit of ontvangstbevestiging, en los daarvan met of zonder prijs (`mail_price`); (3) knoppen in de mail aan de eigenaar. Oude waarden `customer_mail` = `ontvangst`/`direct` worden bij het lezen omgezet naar `aan` + `mail_price` 0/1; eerdere standaardteksten van het contact-bericht worden vervangen door de nieuwe standaard.
