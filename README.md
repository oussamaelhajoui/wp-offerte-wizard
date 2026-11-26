# Offerte Wizard

WordPress-plugin: stap-voor-stap offerteaanvraag (flow nagebouwd van stukadoorsclub.nl/signup) in de huisstijl van afbouwmegens.nl, met een extra route om m² uit een bouwplan te laten uitlezen.

- **Plugin:** `offerte-wizard/` (de zip staat na het bouwen in `build/offerte-wizard.zip`)
- **Ontwerp:** `docs/superpowers/specs/2026-11-25-offerte-wizard-design.md`
- **Vereisten:** WordPress 6.0+, PHP 7.4+

## Installeren

1. wp-admin → Plugins → Nieuwe plugin → Plugin uploaden → `offerte-wizard.zip` → activeren.
2. Instellingen → Offerte Wizard: e-mailadres voor aanvragen, prijzen per m², eventueel een AI-key.
3. Plaatsen:
   - `[offerte_wizard]` in een Tekst-module in een column.
   - `[offerte_wizard fullpage="1"]` op een eigen pagina (schermvullend, over header en footer heen).
   - Per plaatsing: `mode="auto|ai|tekst|handmatig"` en `bouwplan="uit"`.
   - Zonder shortcode (Code-module): `<offerte-wizard data-offerte-wizard style="display:block"></offerte-wizard>`.

## Prijzen en wat de klant krijgt (alles instelbaar)

Instellingen → Offerte Wizard:

- **Prijzen per m²:** zes velden (stucwerk wanden, stucwerk plafonds en schilderwerk, elk van/tot).
Drie onafhankelijke keuzes:

1. **Eindscherm:** toon de prijsrange (met een instelbare toelichting en eventueel een eigen extra tekst in een opvallende kaart), of toon geen prijs maar een bericht dat we contact opnemen (titel en tekst instelbaar).
2. **Mail naar de klant:** geen mail, of een ontvangstbevestiging. Los daarvan kies je of de prijs in die mail staat: met prijs krijgt de klant meteen de offerte (en staat de aanvraag als "verstuurd" bij jou). Je kunt de prijs dus op het scherm verbergen en toch mailen, of andersom.
3. **Knoppen in jouw mail:** "Offerte sturen naar klant" (één klik plus een bevestigingspagina, met de berekende prijs) en "Aanvraag openen" (wp-admin). Elk aan of uit te zetten.

Jij krijgt de berekende prijs altijd in je eigen mail.

Per aanvraag, onder Offerte-aanvragen in wp-admin: eigen prijs invullen (één bedrag = vaste prijs, twee = van/tot) met persoonlijke toelichting en versturen, opnieuw versturen, of markeren als zelf afgehandeld. Doe je niets, dan gaat er niets naar de klant. De status (nieuw, verstuurd, afgehandeld) staat in de lijst.

## Afgeschermde stijl

De wizard draait in een Shadow DOM met eigen tag-namen. Thema-CSS (ook met `!important`) komt er niet in; de wizard-CSS komt er niet uit. Het script laadt alleen op pagina's waar de wizard staat.

## Bouwplan uitlezen

| Modus | Werking |
|---|---|
| `auto` (standaard) | AI als er een API-key is, anders tekstextractie, anders handmatig |
| `ai` | PDF of foto gaat naar Claude of OpenAI (key in de instellingen); klant moet toestemming vinken |
| `tekst` | leest "Woonkamer 24,5 m2" of "Keuken 3.50 x 2.80" uit de tekstlaag van een vector-PDF |
| `handmatig` | alleen bijlage in de mail |

Mislukt een modus, dan valt de plugin terug op handmatig; de aanvraag gaat altijd door.

## Updaten

**Handmatig (altijd mogelijk):** Plugins → Nieuwe plugin → Plugin uploaden → de nieuwe zip → "Huidige vervangen door geüploade versie". Instellingen en aanvragen blijven bewaard.

**Automatisch (vanaf 1.3.0 standaard aan):** de plugin kijkt naar de GitHub-releases van deze repo (`oussamaelhajoui/wp-offerte-wizard`). WordPress toont dan bij Plugins zelf "Update beschikbaar", met één klik bijwerken. Instellingen → Offerte Wizard → Updates bevat de update-URL (leeg = uit) en "Nu op updates controleren". Versies vóór 1.3.0 moeten één keer handmatig worden bijgewerkt.

Een nieuwe versie uitbrengen: verhoog het versienummer in `offerte-wizard.php` (twee plekken) en `readme.txt`, voeg een kopje met de wijzigingen toe en draai:

```powershell
.\scripts\release.ps1
```

Dat bouwt de zip en het update-bestand (versie, link, SHA-256), commit en pusht, en maakt een GitHub-release met beide bijlagen. Sites zien de update binnen 12 uur of direct via de knop. Een download waarvan de checksum niet klopt wordt niet geïnstalleerd. Zonder GitHub kan het ook: `.\scripts\build.ps1 -BaseUrl <https-map>` en de twee bestanden daar zetten.

## Ontwikkelen

Geen lokale PHP nodig, alles draait in Docker:

```powershell
.\scripts\test.ps1            # unit-tests
.\scripts\lint.ps1            # php -l
.\scripts\build.ps1           # lint + tests + build/offerte-wizard.zip
.\scripts\wp-test-up.ps1      # wegwerp-WordPress op http://localhost:8099 (admin/admin), mails naar .wp-test/mail/
.\scripts\wp-update-test.ps1  # test de update-functie (installeert uit de zip, biedt een volgende versie aan, test ook een foute checksum)
```
