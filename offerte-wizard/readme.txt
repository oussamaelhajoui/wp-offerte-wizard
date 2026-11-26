=== Offerte Wizard ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Stap-voor-stap offerteaanvraag met prijsindicatie en bouwplan-uitlezing.

== Installeren ==
1. Plugins > Nieuwe plugin > Plugin uploaden > offerte-wizard.zip > Activeren.
2. Instellingen > Offerte Wizard: vul het e-mailadres voor nieuwe aanvragen in en controleer de prijzen.
3. Zet [offerte_wizard] in een Tekst-module (in een column) of [offerte_wizard fullpage="1"] op een eigen pagina.

== Shortcode ==
[offerte_wizard]                      in een column of sectie
[offerte_wizard fullpage="1"]         schermvullend
[offerte_wizard mode="handmatig"]     mode: auto, ai, tekst, handmatig
[offerte_wizard bouwplan="uit"]       zonder bouwplan-optie
<offerte-wizard data-offerte-wizard style="display:block"></offerte-wizard>   zonder shortcode (bijv. Code-module)

== Offerte versturen ==
Instellingen > Offerte Wizard > "Na het versturen": kies of de klant de prijs ziet, welke mail de klant krijgt
(geen / ontvangstbevestiging / meteen de offerte) en welke knoppen in de mail aan jou staan.
Per aanvraag (menu Offerte-aanvragen): eigen prijs invullen en versturen, of markeren als zelf afgehandeld.

== Changelog ==
= 1.7.0 =
Eindscherm instelbaar: prijsrange (met eigen toelichting en extra tekst) of een bericht dat we contact opnemen (nieuwe, betere standaardtekst). Mail naar de klant: aan/uit, en los daarvan met of zonder prijs.
= 1.6.2 =
Teksten: geen verwijzing meer naar een telefoongesprek; in plaats daarvan "we nemen contact met je op".
= 1.6.1 =
Bericht als de prijs niet getoond wordt: nu een opvallende kaart met eigen titel en tekst en een betere standaardtekst. De keuze "prijs tonen of niet" is een duidelijke keuzelijst.
= 1.6.0 =
Instelbaar bericht voor de klant als de prijs niet getoond wordt (eindscherm en ontvangstbevestiging).
= 1.5.1 =
Bouwplan zonder uitleesbare maten: een prijsindicatie komt alleen als alle te stucen maten bekend zijn (geen te lage prijs bij één ingevulde maat). Duidelijker uitleg in de controlestap.
= 1.5.0 =
Nieuwe stap "Wat moet er gestukt worden?": alleen wanden, alleen plafonds of beide. De flow slaat onnodige vragen over (m² en daktype) en prijs, overzicht en mails rekenen alleen met de gekozen oppervlakken.
= 1.4.1 =
Nieuwe versies verschijnen binnen een uur bij Plugins (voorheen tot 12 uur).
= 1.4.0 =
Nieuwe stap vóór de kalender: "Weet je al in welke periode?" (Ik weet het, op korte termijn, binnen 3 maanden, 3-6 maanden). Duidelijkere, persoonlijkere teksten op de welkomstpagina.
= 1.3.0 =
Standaard wordt de prijsindicatie niet meer aan de klant getoond (aanpasbaar onder "Na het versturen"). De update-URL staat standaard op de GitHub-releases, dus updaten werkt vanzelf.
= 1.2.0 =
Automatische updates via een eigen update-URL (Instellingen > Offerte Wizard > Updates), met controle op een checksum.
= 1.1.0 =
Goedkeuringsflow: prijs tonen aan/uit, mail naar klant instelbaar, knoppen in de mail aan de eigenaar, eigen prijs per aanvraag.
= 1.0.0 =
Eerste versie.
