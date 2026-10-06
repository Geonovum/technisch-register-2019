# Handleiding voor beheerders van informatiemodellen

Deze handleiding beschrijft hoe je de technische bestanden van een standaard of informatiemodel publiceert in het technisch register van Geonovum: <https://register.geostandaarden.nl>.

Technische bestanden zijn bijvoorbeeld UML-modellen (XMI/EAP), GML-applicatieschema's, XML- en JSON-schema's, SHACL-shapes, Schematron-regels, waardelijsten, WSDL's, visualisaties (SLD) en symbolen.

## Hoe het werkt

Het register haalt bestanden automatisch op uit GitHub. Je hoeft niets te uploaden:

1. Je zet de bestanden in een GitHub-repository, in een vaste mappenstructuur.
2. De repository is aangemeld bij het register (in `repos.json` van [technisch-register-2019](https://github.com/Geonovum/technisch-register-2019)).
3. De repository heeft een webhook naar het register.
4. Je maakt een **release** op GitHub. GitHub stuurt dan een bericht naar het register. Het register downloadt de zip van de release-tag en kopieert de herkende mappen naar de website.

Er wordt dus alleen gepubliceerd bij een release. Een push of een merge naar `main` doet niets.

## Checklist

Voordat je een release maakt:

- [ ] De bestanden staan in de root van de repository in een map met de naam van een artefacttype (zie [Mappenstructuur](#mappenstructuur)).
- [ ] Die mappen staan op de branch of commit waarvan je de release maakt (meestal `main`).
- [ ] De repository staat in `repos.json`, met precies de juiste GitHub-URL, en de wijziging is gemerged.
- [ ] De webhook is ingesteld met content type `application/json` en het event *Releases*.
- [ ] Je maakt een gewone release, geen pre-release.

## Mappenstructuur

Het register verwacht in de root van de repository:

```
/<artefacttype>/<versie>/<bestanden>
```

Een voorbeeld uit NL-SBB:

```
shacl/
  1.0.0/skos-ap-nl.ttl
  1.0.1-cv/skos-ap-nl.ttl
```

Dit wordt gepubliceerd als:

```
https://register.geostandaarden.nl/shacl/nl-sbb/1.0.0/skos-ap-nl.ttl
https://register.geostandaarden.nl/shacl/nl-sbb/1.0.1-cv/skos-ap-nl.ttl
```

Het deel `nl-sbb` is het `id` van de repository in `repos.json`, niet de naam van de repository op GitHub.

### Artefacttypen

Alleen mappen waarvan de naam precies overeenkomt met een sleutel in [`descriptions.json`](../src/autodeploy/config/descriptions.json) worden gepubliceerd. Andere mappen (zoals `docs/`, `profiles/` of `respec/`) worden zonder melding genegeerd.

| Map (artefacttype)    | Inhoud                                                   | Gebruikelijke extensies        |
|-----------------------|----------------------------------------------------------|--------------------------------|
| `informatiemodel`     | UML-informatiemodellen                                   | `.xmi`, `.eap`, `.qea`         |
| `gmlapplicatieschema` | GML-applicatieschema's                                   | `.xsd`                         |
| `xmlschema`           | StUF- en andere XML-schema's (submappen toegestaan)      | `.xsd`, `.wsdl`                |
| `jsonschema`          | JSON-schema's                                            | `.json`                        |
| `shacl`               | SHACL-shapes voor validatie van linked data              | `.ttl`, `.rdf`, `.jsonld`      |
| `regels`              | Schematron-regels                                        | `.sch`                         |
| `waardelijst`         | Waardelijsten als downloadbaar bestand                   | `.xlsx`, `.csv`, `.rdf`, `.ttl`, `.xml`, `.pdf` |
| `wsdl`                | Servicebeschrijvingen                                    | `.wsdl`                        |
| `visualisatie`        | Visualisatieregels (submappen toegestaan)                | `.xml`, `.sld`                 |
| `symbool`             | Symbolen en iconen                                       | `.svg`, `.png`, `.eps`         |
| `zipfile`             | Zip met het complete informatiemodel                     | `.zip`                         |

De mapnaam is hoofdlettergevoelig: `SHACL/` of `Shacl/` wordt niet herkend.

Heb je een artefacttype nodig dat er niet bij staat? Vraag dan de beheerder van het technisch register om het toe te voegen. Dat vergt een aanpassing in het register en op de server.

### Versies

De versiemap is vrij te kiezen. Wij raden semantische versienummers aan (major.minor.patch, conform [BOMOS](https://www.forumstandaardisatie.nl/bomos)), bijvoorbeeld `1.0.1`. Een achtervoegsel voor een tussenversie mag, bijvoorbeeld `1.0.1-cv` voor een consultatieversie. Gebruik in mapnamen alleen letters, cijfers, punten en koppeltekens.

> **Belangrijk: een release vervangt alles.**
> Bij elke release probeert het register eerst alle gepubliceerde mappen van jouw standaard (voor alle artefacttypen) weg te zetten als backup, en zet dan de inhoud van de nieuwe release neer. Ga er dus van uit dat alleen online blijft wat **in de nieuwe release** staat. Laat oude versiemappen daarom in de repository staan.
>
> Andersom werkt het niet betrouwbaar: een bestand of versie uit de repository verwijderen haalt het niet altijd offline (zie de [beheerdershandleiding](HandleidingVoorBeheerdersTechnischRegister.md#bekende-beperkingen)). Moet iets echt offline, vraag dat dan aan de beheerder van het register.

Neem je in een bestand de eigen publicatie-URL op (zoals `owl:versionIRI` in een ontologie)? Laat die dan overeenkomen met de map: `https://register.geostandaarden.nl/<artefacttype>/<id>/<versie>/<bestand>`.

## Aanmelden bij het register

Het register kent alleen repositories die in [`src/autodeploy/config/repos.json`](../src/autodeploy/config/repos.json) staan. Aanmelden kan op twee manieren.

### Via de Geonovum-helpdesk

Mail naar <geostandaarden@geonovum.nl> met:

- de naam van de standaard (bijvoorbeeld IMGolf);
- een korte omschrijving van maximaal 58 tekens (bijvoorbeeld "Informatiemodel Golf");
- een lange omschrijving van één of enkele alinea's;
- de URL van de GitHub-repository (bijvoorbeeld `https://github.com/Geonovum/IMGolf`);
- of het model hoort bij een bestaande cluster van standaarden (zoals BRT of RO).

Je krijgt een mail zodra de aanmelding is verwerkt.

### Via een pull request

1. Open [technisch-register-2019](https://github.com/Geonovum/technisch-register-2019) op GitHub.
2. Open `src/autodeploy/config/repos.json` en klik op het potloodje (*Edit this file*). GitHub maakt automatisch een fork voor je aan.
3. Voeg onderaan een entry toe. Let op de komma na de vorige entry:

   ```json
   {
     "id": "imgolf",
     "cluster": "",
     "titel": "Informatiemodel Golf",
     "titel_kort": "IMGolf",
     "beschrijving": "Het informatiemodel Golf beschrijft golfbanen en wordt door Geonovum als voorbeeld gebruikt.",
     "beschrijving_kort": "Informatiemodel Golf",
     "url": "https://github.com/Geonovum/IMGolf"
   }
   ```

4. Doe hetzelfde in `src/autodeploy/config/cluster.json` voor een standaard die niet bij een bestaande cluster hoort (zie de [beheerdershandleiding](HandleidingVoorBeheerdersTechnischRegister.md#velden-in-reposjson)).
5. Klik op *Propose changes* en daarna op *Create pull request*.

Uitleg van de velden staat in de [handleiding voor beheerders van het technisch register](HandleidingVoorBeheerdersTechnischRegister.md#velden-in-reposjson). De belangrijkste regels:

- `id` wordt onderdeel van alle URL's en kun je achteraf niet zonder gevolgen wijzigen. Gebruik alleen kleine letters, cijfers en koppeltekens, minimaal 2 tekens (bijvoorbeeld `imgolf` of `nl-sbb`).
- `url` moet de GitHub-URL van de repository zijn. Hoofdletters maken niet uit.

## Webhook instellen

Hiervoor heb je admin-rechten op de repository nodig.

1. Ga in de repository naar **Settings → Webhooks → Add webhook**.
2. Vul in:

   | Veld                   | Waarde                                                       |
   |------------------------|--------------------------------------------------------------|
   | Payload URL            | `https://register.geostandaarden.nl/autodeploy/releasecreated.php` |
   | Content type           | **`application/json`** (niet de standaardwaarde!)            |
   | Secret                 | leeg laten                                                   |
   | SSL verification       | Enable                                                       |
   | Which events…          | *Let me select individual events* → alleen **Releases** aanvinken (en *Pushes* uitvinken) |
   | Active                 | aangevinkt                                                   |

3. Klik op **Add webhook**.

GitHub stuurt direct een *ping*. Die verschijnt onder *Recent Deliveries* en doet verder niets.

> Laat je het content type op `application/x-www-form-urlencoded` staan, dan kan het register het bericht niet lezen. Er wordt dan niets gepubliceerd en je krijgt geen foutmelding.

## Release maken

1. Zorg dat alles wat je wilt publiceren op `main` staat (of op de branch waarvan je de release maakt).
2. Ga in de repository naar **Releases → Draft a new release**.
3. Bij *Choose a tag*: vul een nieuwe tag in, bijvoorbeeld `v1.0.1`, en kies als *Target* de juiste branch.
4. Geef een titel en beschrijving.
5. Laat **Set as a pre-release uit**.
6. Klik op **Publish release**.

> **Een pre-release is géén testrelease: die komt gewoon online.** GitHub meldt een pre-release aan het register ook als *published*, en het register zet de inhoud dan direct op <https://register.geostandaarden.nl>, net als bij een gewone release. Er is geen testomgeving om eerst te kijken. Publiceer daarom alleen wat online mag. Wil je een concept- of consultatieversie publiceren? Maak dan een gewone release en geef de versiemap een herkenbare naam, zoals `1.0.1-cv`.

De releasetag hoeft niet overeen te komen met de versiemappen. Het register gebruikt de tag alleen om de juiste zip op te halen.

## Controleren of het gelukt is

1. Ga naar **Settings → Webhooks →** jouw webhook **→ Recent Deliveries**.
2. Open de bovenste levering (event `release`) en kijk bij **Response**.

| Response                                                                 | Betekenis                                                        |
|--------------------------------------------------------------------------|------------------------------------------------------------------|
| `ZIP downloaded from GitHub: …` gevolgd door `Resource directory: …` en `Sync <map> directory to: <map>/<id>` | Gelukt. Per gepubliceerde map staan er twee regels.          |
| Alleen `ZIP downloaded from GitHub: …`, geen `Sync`-regels                | De release bevat geen herkende artefactmappen. Controleer de mapnamen en of de mappen op de getagde commit staan. |
| `NOT SYNCED TO REGISTER. Repo information not found in repos.json…`       | De repository staat niet (of met een andere URL) in `repos.json`, of de wijziging is nog niet gemerged. |
| `NOT SYNCED TO REGISTER. Repo id is too short`                           | Het `id` in `repos.json` is korter dan 2 tekens.                 |
| Lege response (of alleen een PHP-melding) met status 200, ook bij de levering met *published* | Het content type staat niet op `application/json`.               |
| `ZIP downloaded from GitHub: …` gevolgd door `Something went wrong in processing the ZIP file` | De zip kon niet worden opgehaald of geopend. De eerste regel verschijnt ook als de download mislukt. Is de repository publiek en bestaat de tag? Zo ja, neem contact op met de beheerder van het register. |
| Een levering met `action` *prereleased* toont `Sync`-regels             | Normaal bij een pre-release: deze levering zet een kopie in de niet-zichtbare staging-map. De levering met *published* zet dezelfde inhoud op productie. |
| Status 4xx/5xx of een timeout                                            | Probleem op de server. Neem contact op met de beheerder van het register. |

Eén release levert meerdere leveringen op, bijvoorbeeld *created*, *published* en *released* (bij een pre-release ook *prereleased*). De leveringen met *created* en *published* publiceren allebei dezelfde inhoud. Dat is onschuldig. Leveringen met andere acties krijgen een lege response.

Mislukte leveringen kun je na een correctie opnieuw versturen met **Redeliver**. Dat werkt alleen voor fouten aan de kant van het register (bijvoorbeeld `repos.json`). Ontbreekt er iets in je repository, maak dan een nieuwe release: het register publiceert de inhoud van de tag, en een bestaande tag verandert niet.

Bekijk tot slot de pagina van je standaard: `https://register.geostandaarden.nl/<id>/index.html`, en de bestanden onder `https://register.geostandaarden.nl/<artefacttype>/<id>/`.

## Veelgemaakte fouten

| Symptoom                                              | Oorzaak                                                                          |
|-------------------------------------------------------|----------------------------------------------------------------------------------|
| Er gebeurt niets na een merge naar `main`             | Alleen een release start de publicatie.                                         |
| Release gemaakt, maar niets online                    | Verkeerd content type, de repository staat niet (goed) in `repos.json`, of de artefactmap staat niet op de getagde branch. |
| Pre-release bedoeld als test, maar staat al online    | Een pre-release publiceert gewoon; er is geen testomgeving. Zie [Release maken](#release-maken). |
| Oude versies zijn verdwenen                           | Ze stonden niet meer in de nieuwe release. Een release vervangt alles.          |
| Verwijderd bestand staat nog online                   | Bekende beperking van het register; vraag de beheerder het weg te halen.        |
| Map wordt genegeerd                                   | De mapnaam staat niet in `descriptions.json` of heeft afwijkende hoofdletters. |
| Bestanden staan online, maar de pagina `/<id>/` werkt niet | Het `id` ontbreekt in `cluster.json`, of bevat tekens die de webserver niet doorstuurt (alles behalve letters, cijfers, `_` en `-`). Neem contact op met de beheerder van het register. |
