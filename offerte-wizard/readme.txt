=== Offerte Wizard ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.0
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
= 1.3.0 =
Standaard wordt de prijsindicatie niet meer aan de klant getoond (aanpasbaar onder "Na het versturen"). De update-URL staat standaard op de GitHub-releases, dus updaten werkt vanzelf.
= 1.2.0 =
Automatische updates via een eigen update-URL (Instellingen > Offerte Wizard > Updates), met controle op een checksum.
= 1.1.0 =
Goedkeuringsflow: prijs tonen aan/uit, mail naar klant instelbaar, knoppen in de mail aan de eigenaar, eigen prijs per aanvraag.
= 1.0.0 =
Eerste versie.
