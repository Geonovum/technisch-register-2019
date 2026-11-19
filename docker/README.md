# Technisch register in een container (ZAD)

Het technisch register kan draaien als container op [ZAD](https://zad.rijksapp.nl), het platform voor Zelfservice Applicatie Deployment. Het image bevat Apache met PHP 8.4, de code uit `src/` en de Apache-instellingen uit `conf/apache2/`. De container draait rootless, als `www-data` (uid 33), en luistert op poort 8080.

## Hoe het werkt

Alles wat het register schrijft, staat in `/var/www/geostandaarden/v2`, net als op de huidige server:

| Map | Inhoud |
|---|---|
| `production/` | De website: de code uit het image en de gepubliceerde artefacten (`xmlschema/`, `regels/`, enzovoort) |
| `staging/` | Artefacten van pre-releases |
| `backup/` | De vorige versie van elke standaard |
| `log/` | `webhook.log` en de verwerkte deliveries |
| `tmp/` | Uitgepakte releases tijdens de verwerking |

Koppel daar blijvende opslag aan. Bij elke start zet de container de code uit het image in `production/`. Code die een vorige versie van het image daar zette en die nu niet meer bestaat, wordt weggehaald. Gepubliceerde artefacten blijven staan. Een nieuwe versie van het register uitrollen is dus: een nieuw image starten.

## Instellingen

| Omgevingsvariabele | Betekenis | Standaard |
|---|---|---|
| `REGISTER_BASE_URL` | Publiek adres, met `/` aan het eind. Gebruikt voor links en voor de omleidingen van Apache. | `https://register.geostandaarden.nl/` |
| `REGISTER_WEBHOOK_SECRET` | Het geheim van de GitHub-webhooks. Zet het als versleutelde variabele in ZAD. | geen: de webhook weigert dan elke release |
| `REGISTER_WEBHOOK_SECRET_FILE` | In plaats daarvan: een bestand met het geheim, bijvoorbeeld een bijlage in ZAD | `/var/www/geostandaarden/v2/webhook-secret` |
| `REGISTER_BACKUPS_TO_KEEP` | Aantal vorige versies per standaard in `backup/` | `1` (ZAD maakt zelf 's nachts back-ups) |

Dezelfde variabelen werken op de huidige server; zonder variabelen gelden de waarden van die server.

## ZAD-project aanmaken

In de wizard:

1. **Projectgegevens:** bijvoorbeeld `technisch-register`.
2. **Services:**
   - *Publiceren op het web*
   - *Permanente opslag*, gekoppeld op `/var/www/geostandaarden/v2`. Het maximum is 1Gi en een volume kan niet krimpen: kies een maat die past bij de huidige data plus groei (zie Migratie).
   - *Health check*: HTTP op poort 8080, pad `/resources/css/style.css`. De hoofdpagina is daar niet geschikt voor: die raadpleegt GitHub bij elke aanroep.
   - Geen *Slaapstand*: GitHub wacht 10 seconden op het antwoord van de webhook, een koude start haalt dat niet.
   - Geen *Authorization Wall*: het register is openbaar en GitHub moet de webhook kunnen bereiken.
3. **Team:** minstens twee beheerders.
4. **Component** `register`: image `ghcr.io/geonovum/technisch-register-2019:<tag>`, poort 8080, één replica, omgevingsvariabelen zoals hierboven. Eén replica, omdat releases naar de opslag worden geschreven en per standaard worden vergrendeld.
5. **Domein:** eerst het standaarddomein van ZAD, met `REGISTER_BASE_URL` op dat adres. Daarna `register.geostandaarden.nl`.

De container moet uitgaand verkeer naar `github.com`, `codeload.github.com` en `raw.githubusercontent.com` kunnen maken: de pagina's lezen `repos.json` en `cluster.json` van GitHub, en de webhook downloadt de releases.

## Uitrollen met GitHub Actions

[`.github/workflows/container.yml`](../.github/workflows/container.yml) bouwt en test het image bij elke pull request. Bij een gepubliceerde release pusht de workflow het image naar `ghcr.io/geonovum/technisch-register-2019` met de release-tag, ondertekent het met een build-attestatie, en rolt het uit met [ZAD Actions](https://github.com/RijksICTGilde/zad-actions). Instellen in de repository:

| Soort | Naam | Waarde |
|---|---|---|
| Variabele | `ZAD_PROJECT_ID` | Het project in ZAD. Zonder deze variabele wordt niets uitgerold. |
| Variabele | `ZAD_DEPLOYMENT` | De deployment voor releases, standaard `production` |
| Variabele | `ZAD_COMPONENT` | Het component, standaard `register` |
| Secret | `ZAD_API_KEY` | API-token van ZAD |

De deployment-naam is ook de naam van een GitHub-environment. Zet op `production` een verplichte goedkeuring als een release niet vanzelf live mag gaan. Met de hand (Actions, "Run workflow", met een deployment-naam) kun je een branch uitrollen naar een test-deployment.

Maak het package `technisch-register-2019` in GitHub Container Registry na de eerste push openbaar. Anders heeft ZAD een token nodig om het image op te halen (service *Eigen container registries*).

## Migratie van de huidige server

1. Meet de huidige data: `du -sh /var/www/geostandaarden/v2/{production,staging,backup}`. De artefacten in `production/` moeten mee; `backup/` hoeft niet.
2. Zet een test-deployment op met het standaarddomein van ZAD en kopieer de artefactmappen van `production/` (alles behalve de code: `index.php`, `listDescriptions.php`, `resources/`, `autodeploy/`) naar de opslag. Vraag het ZAD-team hoe je data in een volume zet.
3. Vergelijk de test-deployment met register.geostandaarden.nl.
4. Zet het webhookgeheim in ZAD, verwijs `register.geostandaarden.nl` naar ZAD en zet `REGISTER_BASE_URL` terug op de standaard. De webhooks van de standaarden hoeven niet te veranderen.

## Lokaal draaien en testen

```bash
docker build -t register .
docker run --rm -p 8080:8080 -v register-data:/var/www/geostandaarden/v2 \
  -e REGISTER_BASE_URL=http://localhost:8080/ register
sh docker/test-image.sh register
```

`test-image.sh` start het image met een lege opslag en controleert onder meer de rootless start, de omleidingen achter een proxy, de artefactmappen, de webhook en het verversen van de code bij een herstart.
