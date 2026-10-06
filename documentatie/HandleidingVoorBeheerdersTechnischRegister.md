# Handleiding voor beheerders van het technisch register

Deze handleiding is bedoeld voor wie <https://register.geostandaarden.nl> beheert: standaarden aanmelden, artefacttypen toevoegen, de server bijwerken en problemen oplossen.

Voor beheerders van een standaard (hoe publiceer ik mijn bestanden?) zie de [handleiding voor beheerders van informatiemodellen](HandleidingVoorBeheerdersInformatiemodellen.md).

## Overzicht

```
GitHub-repository van een standaard           register.geostandaarden.nl (Apache + PHP)
┌───────────────────────────┐   release   ┌──────────────────────────────────────────┐
│ shacl/1.0.0/...           │ ──webhook──▶│ autodeploy/releasecreated.php            │
│ xmlschema/2.1/...         │             │  1. zoekt repo op in repos.json          │
└───────────────────────────┘             │  2. downloadt <repo>/archive/<tag>.zip   │
                                          │  3. kopieert bekende mappen naar         │
technisch-register-2019 (deze repo)       │     production/<type>/<id>/              │
┌───────────────────────────┐   leest     │                                          │
│ src/autodeploy/config/    │ ◀─────────  │ index.php: pagina's per standaard        │
│   repos.json              │  (live van  │ Apache: directory listings van bestanden │
│   cluster.json            │   GitHub)   └──────────────────────────────────────────┘
│   descriptions.json       │
└───────────────────────────┘
```

Belangrijke eigenschappen:

- **De configuratie wordt live van GitHub gelezen.** `githubConfig.php` verwijst naar de `raw.githubusercontent.com`-URL's van `repos.json`, `cluster.json` en `descriptions.json` op de branch `master`. Een gemergede wijziging is dus direct actief, na hooguit enkele minuten cache van GitHub. Daarvoor hoeft er niets op de server te gebeuren.
- **De PHP-code en de Apache-configuratie worden niet automatisch uitgerold.** Wijzigingen in `src/*.php`, `src/resources/` of `conf/apache2/rewrite-rules.txt` moet je handmatig op de server zetten (zie [Server](#server)).
- **Alleen GitHub-releases starten een publicatie.** Een push doet niets.

## Configuratiebestanden

Alle bestanden staan in `src/autodeploy/config/`.

| Bestand             | Inhoud                                                            |
|---------------------|-------------------------------------------------------------------|
| `repos.json`        | Eén entry per GitHub-repository die mag publiceren.               |
| `cluster.json`      | Eén entry per item op de hoofdpagina: een cluster van standaarden, of een losse standaard. |
| `descriptions.json` | De artefacttypen: welke mapnamen gepubliceerd worden, met titel en beschrijving. |

### Velden in repos.json

| Veld                | Gebruik                                                                                   |
|---------------------|-------------------------------------------------------------------------------------------|
| `id`                | Identifier van de standaard. Wordt de mapnaam op de server (`/<type>/<id>/`) en onderdeel van de URL van de pagina (`/<id>/` of `/<cluster>/<id>/`). Minimaal 2 tekens. Zie [Eisen aan het id](#eisen-aan-het-id). |
| `cluster`           | `""` voor een losse standaard, of het `id` van een cluster uit `cluster.json` (bijvoorbeeld `brt`, `ro`). |
| `titel`             | Titel van de standaard.                                                                   |
| `titel_kort`        | Korte naam, gebruikt als linktekst en in het kruimelpad.                                  |
| `beschrijving`      | Lange beschrijving.                                                                       |
| `beschrijving_kort` | Korte beschrijving, maximaal 58 tekens.                                                   |
| `url`               | URL van de GitHub-repository. Moet overeenkomen met de `html_url` die GitHub in de webhook meestuurt; hoofdletters maken niet uit. Wordt de repository hernoemd of verplaatst, pas dit dan aan. |

### Velden in cluster.json

Zelfde velden als hierboven, zonder `cluster` en `url`. De titel en beschrijving uit `cluster.json` zijn wat er op de pagina van de standaard getoond wordt.

### Losse standaard of cluster

**Losse standaard** (bijvoorbeeld IMEV, NL-SBB):

- `repos.json`: entry met `"cluster": ""`.
- `cluster.json`: entry met **hetzelfde `id`**. Zonder deze entry verschijnt de standaard niet op de hoofdpagina en heeft de pagina geen titel en beschrijving.
- Pagina: `/<id>/index.html`.

**Cluster met meerdere standaarden** (bijvoorbeeld BRT met TOP10NL, TOP50NL, …):

- `cluster.json`: één entry voor de cluster (bijvoorbeeld `brt`).
- `repos.json`: per repository een entry met `"cluster": "brt"`.
- Pagina's: `/brt/index.html` (overzicht) en `/brt/<id>` per standaard.

### Eisen aan het id

- Gebruik **alleen kleine letters, cijfers en koppeltekens** (`imgeo`, `top10nl`, `nl-sbb`). De rewrite-regels sturen alleen paden met `[a-zA-Z0-9_-]` door naar `index.php`; met andere tekens (zoals een punt of spatie) werkt de pagina van de standaard niet.
- **Wijzig een id niet achteraf.** Het id zit in alle gepubliceerde URL's en mogelijk in de bestanden zelf (zoals `owl:versionIRI`).

## Taken

### Een nieuwe standaard aanmelden

1. Ontvang de gegevens via de helpdesk of als pull request op deze repository.
2. Controleer:
   - Is het `id` uniek, minimaal 2 tekens, en alleen kleine letters, cijfers en koppeltekens?
   - Is `url` de juiste GitHub-URL?
   - Is `beschrijving_kort` maximaal 58 tekens?
   - Is de JSON geldig? Let op komma's: `python3 -m json.tool repos.json` of de validatie van je editor.
3. Voeg de entry toe aan `repos.json` en, bij een losse standaard, ook aan `cluster.json`.
4. Merge naar `master`. De configuratie is daarna direct actief.
5. Laat de beheerder van de standaard weten dat de webhook en een release kunnen volgen, en verwijs naar de [handleiding voor beheerders van informatiemodellen](HandleidingVoorBeheerdersInformatiemodellen.md).
6. Controleer na de eerste release de pagina `/<id>/index.html` en de bestanden onder `/<type>/<id>/`.

Een pull request beoordelen gaat via **Pull requests → Files changed** op GitHub. Akkoord: **Merge pull request**. Niet akkoord: geef commentaar en **Close pull request**.

### Een nieuw artefacttype toevoegen

Voorbeeld: in november 2025 is `shacl` toegevoegd. Er zijn vier plekken die je moet bijwerken:

1. **`src/autodeploy/config/descriptions.json`**: voeg een sleutel toe met `titel` en `beschrijving`. De sleutel is de mapnaam die beheerders in hun repository gebruiken. Actief na merge.
2. **`conf/apache2/rewrite-rules.txt` én de configuratie op de server**: voeg een uitzondering toe, zodat de directory listing niet naar `index.php` wordt omgeleid:

   ```apache
   RewriteRule ^/<type>(/|$) - [L]
   ```

   Herlaad daarna Apache (zie [Server](#server)). Zonder deze regel stuurt `/<type>/` door naar de hoofdpagina.
3. **`src/listDescriptions.php`**: de lijst artefacttypen op de hoofdpagina staat hier vast in de HTML. Voeg een blok toe en zet het bestand op de server. De lijst op de pagina van een standaard wordt wel automatisch uit `descriptions.json` gemaakt.
4. **De handleiding voor beheerders van informatiemodellen**: werk de tabel met artefacttypen bij.

### De server bijwerken

Zie [Server](#server). Nodig na elke wijziging in `src/` (behalve de JSON-configuratie) of in `conf/apache2/rewrite-rules.txt`.

## Server

| Onderdeel              | Waarde                                                        |
|------------------------|---------------------------------------------------------------|
| Basismap (`$baseDir`)  | `/var/www/geostandaarden/v2`                                  |
| DocumentRoot           | `/var/www/geostandaarden/v2/production`                       |
| Staging                | `/var/www/geostandaarden/v2/staging` (krijgt een kopie van pre-releases, maar wordt niet geserveerd; zie [Wat de webhook precies doet](#wat-de-webhook-precies-doet)) |
| Tijdelijke map         | `/var/www/geostandaarden/v2/tmp` (moet schrijfbaar zijn voor PHP) |
| Backups                | `/var/www/geostandaarden/v2/backup/<type>/<id>`               |
| Webhook                | `https://register.geostandaarden.nl/autodeploy/releasecreated.php` |
| Software               | Apache 2 met `mod_rewrite`, PHP 7.2+ met `ZipArchive` en `allow_url_fopen` |

Instellingen staan in `src/autodeploy/registerConfig.php` (paden, basis-URL) en `src/autodeploy/githubConfig.php` (URL's van de configuratiebestanden).

### Code uitrollen

1. Kopieer de gewijzigde bestanden uit `src/` naar de DocumentRoot.
2. Zorg dat de bestanden leesbaar zijn voor de webserver en dat `tmp/`, `production/` en `backup/` schrijfbaar zijn (groep `www-data`). Gebruikers die via FTP of SSH bestanden aanpassen moeten in die groep zitten: `sudo usermod -aG www-data <gebruiker>`.

### Rewrite-regels aanpassen

`conf/apache2/rewrite-rules.txt` is een **fragment**. De volledige Apache-configuratie staat niet op GitHub.

1. Pas de regels aan in de Apache-configuratie op de server (vhost) of in `.htaccess`.
   - In een vhost begint het pad met `/`: `^/shacl(/|$)`.
   - In `.htaccess` begint het pad **zonder** `/`: `^shacl(/|$)`.
2. Controleer de syntax: `sudo apachectl configtest`.
3. Herlaad: `sudo systemctl reload apache2` (niet nodig bij `.htaccess`).
4. Neem dezelfde wijziging over in `conf/apache2/rewrite-rules.txt` in deze repository, zodat die de serverconfiguratie blijft weerspiegelen.

> Aan te vullen: hoe je toegang krijgt tot de server en wie die toegang beheert.

## Soorten pagina's

| URL                        | Wat                                                     | Gemaakt door              |
|----------------------------|---------------------------------------------------------|---------------------------|
| `/`                        | Hoofdpagina met alle items uit `cluster.json`           | `index.php`               |
| `/<cluster>/`              | Overzicht van de standaarden in een cluster             | `index.php` via rewrite   |
| `/<id>/` of `/<cluster>/<id>/` | Pagina van één standaard met de beschikbare artefacttypen | `index.php` via rewrite |
| `/<type>/`                 | Alle standaarden met dit artefacttype                   | Apache directory listing  |
| `/<type>/<id>/`            | Versies van dit artefacttype voor een standaard         | Apache directory listing  |

De rewrite-regels sturen `/<x>/`, `/<x>/<y>/` en `/<x>/index.html` door naar `/?url=…`, behalve voor de uitgezonderde mappen (`autodeploy`, `resources` en de artefacttypen).

## Wat de webhook precies doet

`src/autodeploy/releasecreated.php`, bij een POST van GitHub:

1. Leest de body als JSON. Daarom moet de webhook op content type `application/json` staan. Er is geen controle op een secret (bewust uitgezet in 2019; de controle is dat de repository in `repos.json` moet staan).
2. Reageert alleen op `action` = `published`, `created` (→ `production/`) of `prereleased` (→ `staging/`). **Let op:** GitHub stuurt bij het publiceren van een pre-release óók een levering met `published` (en meestal `created`), niet alleen `prereleased`. Het script kijkt alleen naar `action` en niet naar `release.prerelease`, dus **een pre-release komt gewoon op productie**, met daarnaast een kopie in `staging/`.
3. Zoekt `repository.html_url` op in `repos.json` (hoofdletterongevoelig). Niet gevonden: `NOT SYNCED TO REGISTER…`.
4. Downloadt `<repo-url>/archive/<tag>.zip` naar `tmp/` en pakt die uit.
5. Verplaatst voor **elk** artefacttype de bestaande map `<type>/<id>/` naar `backup/<type>/<id>`.
6. Kopieert elke map in de root van de release waarvan de naam een sleutel in `descriptions.json` is naar `<type>/<id>/`.
7. Ruimt de tijdelijke bestanden op.

De response (zichtbaar in GitHub onder *Settings → Webhooks → Recent Deliveries*) vermeldt de gedownloade zip en elke gesynchroniseerde map. Een overzicht van responses en hun betekenis staat in de [handleiding voor beheerders van informatiemodellen](HandleidingVoorBeheerdersInformatiemodellen.md#controleren-of-het-gelukt-is).

## Bekende beperkingen

| Beperking | Gevolg | Mogelijke oplossing |
|-----------|--------|---------------------|
| `zipfile` staat in `descriptions.json`, maar heeft geen uitzondering in de rewrite-regels. | `/zipfile/` en `/zipfile/<id>/` sturen door naar `index.php` in plaats van een directory listing te tonen. Bestanden dieper in de map zijn wel bereikbaar. | Voeg `RewriteRule ^/zipfile(/\|$) - [L]` toe. |
| Backup via `rename()` mislukt als `backup/<type>/<id>` al bestaat (vanaf de tweede vervanging). | De oude map blijft staan en wordt overschreven. Bestanden die uit de repository zijn verwijderd, blijven online. | Backupmap met tijdstempel gebruiken (de variabele `$backupTimeStamp` bestaat al maar wordt niet gebruikt), of de oude backup eerst verwijderen. Tot die tijd: verwijderde bestanden handmatig van de server halen. |
| Pre-releases worden niet als test behandeld. GitHub stuurt bij een pre-release ook `published`, en het script kijkt alleen naar `action`. | Een pre-release komt direct op productie. Staging krijgt een kopie maar is nergens zichtbaar, dus een testrelease bestaat niet. | In `releasecreated.php` op `release.prerelease` controleren in plaats van alleen op `action` (zie de TODO in de code), en staging als aparte vhost inrichten. Tot die tijd: beheerders laten weten dat een pre-release gewoon publiceert. |
| De `RewriteCond`-regels gelden alleen voor de eerstvolgende `RewriteRule` (`autodeploy`). | Bestaande mappen worden niet automatisch uitgezonderd; elk artefacttype heeft een eigen uitzondering nodig. | Bekend gedrag van `mod_rewrite`; bij nieuwe artefacttypen altijd een uitzondering toevoegen. |
| De lijst artefacttypen op de hoofdpagina staat vast in `listDescriptions.php`. | Een nieuw type verschijnt daar niet vanzelf. | Lijst genereren uit `descriptions.json`, zoals al gebeurt op de pagina's per standaard. |

## Problemen oplossen

| Symptoom | Waar kijken |
|----------|-------------|
| Pagina `/<id>/index.html` geeft een fout | Bevat het id een ander teken dan letters, cijfers, `_` of `-`? → rewrite-regels. Staat het id in `cluster.json`? |
| Standaard staat niet op de hoofdpagina | Ontbreekt de entry in `cluster.json`? |
| Release publiceert niets | *Recent Deliveries* van de webhook bekijken; zie de tabel in de [handleiding voor beheerders van informatiemodellen](HandleidingVoorBeheerdersInformatiemodellen.md#controleren-of-het-gelukt-is). |
| `/<type>/` toont de hoofdpagina in plaats van een lijst | Ontbrekende uitzondering in de rewrite-regels. |
| Wijziging in `repos.json` lijkt niet actief | Gemerged naar `master`? Enkele minuten wachten (cache van GitHub). |
| Download van de zip mislukt | `allow_url_fopen` aan? Is `tmp/` schrijfbaar? Is de repository publiek? Het register haalt de zip zonder authenticatie op, dus private repositories werken niet. |

## Nog te documenteren

- Toegang tot de server en wie die beheert.
- Aanpassen van de vaste teksten op de website (`src/resources/html/`).
- Testen van wijzigingen voordat ze live gaan.
