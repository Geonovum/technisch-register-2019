Introductie
===========

In dit document worden de stappen beschreven voor het publiceren van de technische bestanden van een informatiemodel in het Technisch register van geonovum(http://register.geostandaarden.nl). Met technische bestanden worden bedoeld: Informatiemodellen (in XMI of EAP), GML applicatieschema’s XML schema’s, schematron gebaseerde regels, waardelijsten, WSDLs, visualisaties(SLDs) en symbolen. Er is vanuit gegaan dat raadplegers van dit document een github account hebben aangemaakt en dat ze de GITHub userinterfase hebben geïnstalleerd.

Op hoofdlijnen werkt dit proces als volgt:

Om technische bestanden van een informatiemodel in het technisch register te kunnen publiceren dient allereerst een repository in Github te worden aangemaakt. Het is ook mogelijk een eerder aangemaakt repository te hergebruiken. Een volgende stap is het vullen van de repository. Hiervoor dient de gewenste repository naar een lokale map te worden gecloned en vervolgens volgens het voorgeschreven formaat te worden gevuld. Vervolgens zal het repository op de juiste manier geconfigureerd moeten worden om automatisch uploaden op het technisch-register mogelijk te maken. Wanneer dit gebeurt is dient het informatie model binnen het technisch register geregistreerd te worden. Dit kan zowel via de GeoNovum helpdesk als via github gedaan worden. Wanneer alle bovenstaande stappen zijn voltooid kan tenslotte de release worden uitgebracht.

In de komende hoofdstukken wordt dit stap voor stap uitgelegd.

Formaat GitHub
==============

Aanmaken nieuwe repository
--------------------------

Creëer een nieuwe repository in GitHub.com:

<img src="./media/image1.png" width="558" height="389" />

Clone de repository naar de gewenste lokale folder:

1.  Open de GitHub desktop User Interface, druk links boven op het plusje en selecteer ‘Clone’

    <img src="./media/image2.PNG" width="604" height="361" />

2.  Selecteer de gewenste repository en druk op *‘Clone &lt;naam van geselecteerde repository&gt;’*

<img src="./media/image3.PNG" width="604" height="405" />

1.  Selecteer de gewenste map waar de repository lokaal moet worden opgeslagen en druk op OK.

<img src="./media/image4.gif" width="366" height="383" />

De gekloonde repository bevind zich nu in de gewenste lokale map. De gewenste bestanden en mappen kunnen nu eenvoudig worden toegevoegd aan de gekloonde repository. Hierbij is het van belang het hieronder beschreven formaat aan te houden.

Voorgeschreven formaat
----------------------

In GitHub wordt het volgende folder formaat voorgeschreven om succesvol te kunnen synchroniseren met het technisch register:

/&lt;artefact-type&gt;/&lt;versie&gt;/&lt;bestandsnaam&gt;

Waarbij **&lt;artefact-type&gt;** één van de volgende kan zijn:

-   <span id="OLE_LINK7" class="anchor"><span id="OLE_LINK8" class="anchor"><span id="OLE_LINK5" class="anchor"><span id="OLE_LINK6" class="anchor"></span></span></span></span>informatiemodel

-   <span id="OLE_LINK9" class="anchor"><span id="OLE_LINK10" class="anchor"></span></span>gmlapplicatieschema

-   <span id="OLE_LINK11" class="anchor"><span id="OLE_LINK12" class="anchor"></span></span>xmlschema

-   <span id="OLE_LINK13" class="anchor"><span id="OLE_LINK14" class="anchor"><span id="OLE_LINK15" class="anchor"></span></span></span>regels

-   <span id="OLE_LINK16" class="anchor"></span>waardelijst

-   <span id="OLE_LINK17" class="anchor"><span id="OLE_LINK18" class="anchor"></span></span>wsdl

-   <span id="OLE_LINK19" class="anchor"><span id="OLE_LINK20" class="anchor"></span></span>visualisatie

-   <span id="OLE_LINK21" class="anchor"></span>symbool

**&lt;Versie&gt;** is volledig vrij maar waar mogelijk raden we aan om volgens BOMOS[1] te werken. Dat wil zeggen:

“Versiebeleid met onderscheid tussen major (functionaliteit aanpassing), minor (kleine verbeteringen) en bug fixes is noodzakelijk.”

Bijvoorbeeld 1.0.1

Voor **&lt;bestandsnaam&gt;** geldt dat hier de per artefacttype de volgende extensie(s) worden verwacht:

| **Artefact-type**   | **Bestands extensie**    |
|---------------------|--------------------------|
| Informatiemodel     | .xmi, .eap               |
| Gmlapplicatieschema | .xsd                     |
| Xmlschema           | .xsd .wsdl [2]           |
| Regels              | .sch                     |
| Waardelijst         | .xls .pdf .doc .rdf .xml |
| Wsdl                | .wsdl                    |
| Visualisatie        | .xml[3]                  |
| Symbool             | .eps .png .svg           |

1.0Voor meer informatie over de inhoud van het technisch register zie het document “20150914BeschrijvingConceptEnTechnischRegister”.

Push de inhoud van de lokale repository naar GitHub.com
-------------------------------------------------------

Wanneer de relevante folders en mappen uit het Technisch register aan het lokale repository zijn toegevoegd kan verder worden gegaan met de volgende stappen.

Om de aan de repository toegevoegde bestanden en mappen te zien klik op de betreffende repository en klik vervolgens op *‘Changes’* .

<img src="./media/image5.PNG" width="604" height="251" />

Zorg vervolgens dat alle *‘changes’* staan aangevinkt, dat in *‘summary’* een korte samenvatting staat van de veranderingen die zijn aangebracht, en druk op *‘commit to master’*

<img src="./media/image6.PNG" width="604" height="406" />

1.  Druk vervolgens op Sync om de lokale repository en de GitHub repository te synchroniseren.

<img src="./media/image7.PNG" width="604" height="253" />

De relevante folders en mappen uit het technisch register staan nu op GitHub.

<span id="OLE_LINK1" class="anchor"><span id="OLE_LINK2" class="anchor"><span id="OLE_LINK3" class="anchor"><span id="OLE_LINK4" class="anchor"></span></span></span></span>Configuratie informatiemodel
========================================================================================================================================================================================================

Voorwaarde is dat je een github repository volgens het juiste formaat reeds hebt aangemaakt en dat je hier admin rechten op hebt. Admin rechten worden verkregen door als owner te zijn ingeschreven voor het repository. Admin rechten op een niet zelf aangemaakt repository kunnen worden verkregen door contact op te nemen met de owner van het repository.

Via GitHub
----------

### Webhook toevoegen

Ga in de repository die je wil koppelen naar settings:

<img src="./media/image8.png" width="604" height="405" />

Vervolgens naar webhooks & services:

<img src="./media/image9.png" width="604" height="338" />

Klik op add webhook:

<img src="./media/image10.PNG" width="604" height="404" />

Vul je password in:

<img src="./media/image11.png" width="336" height="201" />

Vul de url <https://register.geostandaarden.nl/autodeploy/releasecreated.php> in, selecteer “let me select individual elements” haal het vinkje bij “Push” weg en voeg er een toe bij “release” klik tot slot op add webhook zoals hieronder aangegeven:

<img src="./media/image12.png" width="556" height="888" />

### Aanleveren beschrijvende tekst

#### Via Geonovum helpdesk

Stuur een mail naar de geonovum helpdesk(<geostandaarden@geonovum.nl>) en vermeld daarin de volgende informatie:

-   Naam informatiemodel (bijvoorbeeld IMGolf)

-   Korte omschrijving in maximaal 58 karakters (bijvoorbeeld “Informatiemodel Golf”)

-   Lange omschrijving (bijvoorbeeld “In het informatiemodel golf wordt door Geonovum als voorbeeld informatiemodel gebruikt en bevat een beschrijving van golfbanen”

-   URL github repository (bijvoorbeeld http://github.com/Geonovum/IMGolf)

#### Via Github

Ga naar de github pagina van Geonovum en selecteer het ‘*Technisch-register’* repository. Klik vervolgens op Fork rechts boven in het scherm.

<img src="./media/image13.PNG" width="604" height="455" />

Het ‘*technisch register’* repository verschijnt nu op uw eigen GitHub pagina:

<img src="./media/image14.PNG" width="604" height="501" />

Clone de repository via de GitHub desktop UserInterface:

<img src="./media/image15.PNG" width="604" height="392" />

Onderstaand venster verschijnt. Selecteer hierin het gewenste bestand waar het technisch register naartoe gecloned zal worden:

<img src="./media/image16.PNG" width="293" height="310" />

Open in het gekloonde repository het bestand ‘*repos.json’.* Voeg in dit bestand de gewenste informatie over het model toe sla het bestand op.

<img src="./media/image17.PNG" width="604" height="501" />

In de GitHub desktop UserInterface druk op changes, zorg dat alle veranderingen geselecteerd zijn, vul een summary in van de verandering in en druk op ‘Commit to master’

<img src="./media/image18.PNG" width="604" height="422" />

Druk vervolgens op sync in de rechter bovenhoek

<img src="./media/image19.PNG" width="604" height="326" />

Na het uitvoeren van bovenstaande stap: ga naar uw GitHub pagina en druk daar in de repository ‘*Technisch register’* op het pull request logo. Zorg ervoor dat deze actie wordt uitgevoerd op de meest recente versie van het target repository . Dit is automatisch het geval wanneer de in de voorgaande stap beschreven *‘Sync’* correct is uitgevoerd.

<img src="./media/image20.PNG" width="604" height="523" />

Druk vervolgens op ‘view pull request’. Het kan zijn dat het scherm afwijkt van onderstaande voorbeeld doordat de melding “Can’t automatically merge…” verschijnt. Mogelijk wordt dit veroorzaakt doordat de eerder beschreven ‘Sync’ niet goed of te lang geleden is uitgevoerd. Voor het verdere verloop van het proces maakt dit echter niet uit.

<img src="./media/image21.PNG" width="604" height="367" />

Uitbrengen Release
==================

Om een release te kunnen uitbrengen moeten de informatiemodellen bekend zijn bij het ‘Technisch Register’. Het bekend maken van het informatie model bij het ‘Technisch Register begint door het aanleveren van een beschrijvende tekst zoals beschreven in de voorgaande sectie van deze handleiding. Wanneer de aangeleverde beschrijvende tekst is verwerkt ontvangt u hierover een mail. De volgende stappen dienen pas te worden uitgevoerd na ontvangst van deze mail.

Testrelease in staging
----------------------

### Via Github

1.  Ga naar Repositories en klik op de gewenste repository

<img src="./media/image22.PNG" width="604" height="476" />

Klik op releases. Het getal voor releases geeft aan hoeveel releases al zijn uitgebracht en kan afwijken van het getal in onderstaande voorbeeld.

<img src="./media/image23.PNG" width="604" height="404" />

Onderstaande scherm verschijnt in het geval er nog niet eerder een release van het repository is uitgebracht. Klik hierin op ‘Create a new release’

<img src="./media/image24.PNG" width="604" height="362" />

In het geval er al eerder releases zijn uitgebracht zal het onderstaande scherm verschijnen. Klik hierin op “draft a new version”.

<img src="./media/image25.PNG" width="604" height="389" />

Vul bij *‘Tag version’* het versie nummer van de release in. Volg hierbij de aanwijzingen onder *‘Tagging suggestions’* en *‘Semantic versioning’*.

<img src="./media/image26.PNG" width="573" height="478" />

Geef de Testrelease een titel en een beschrijving mee en zorg dat ‘*This is a pre-release’* is aangevinkt.

<img src="./media/image27.PNG" width="604" height="516" />

Druk tenslotte op ‘Publish release’

Productierelease
----------------

### Via Github

Ga naar Repositories en klik op de gewenste repository

<img src="./media/image22.PNG" width="604" height="476" />

Klik op releases. Het getal voor releases geeft aan hoeveel releases al zijn uitgebracht en kan afwijken van het getal in onderstaande voorbeeld.

<img src="./media/image23.PNG" width="604" height="433" />

Onderstaande scherm verschijnt in het geval er nog niet eerder een release van het repository is uitgebracht. Klik hierin op ‘Create a new release’

<img src="./media/image24.PNG" width="604" height="362" />

In het geval er al eerder releases zijn uitgebracht zal het onderstaande scherm verschijnen. Vanaf hier kan er een release worden aangemaakt voor productie. Een tweede optie is het aanmaken van een productie release door een bestaande pre-release te veranderen naar een productie release. In deze handleiding is er vanuit gegaan dat het aanmaken van een nieuwe productie release de standaard procedure is. De alternatieve procedure is te vinden in de bijlage.

In het geval een nieuwe release direct in productie moet worden gebracht: Klik in het scherm op “draft a new version”.

<img src="./media/image28.PNG" width="604" height="539" />

Vul bij *‘Tag version’* het versie nummer van de release in. Volg hierbij de aanwijzingen onder *‘Tagging suggestions’* en *‘Semantic versioning’*.

<img src="./media/image26.PNG" width="604" height="538" />

Geef de Testrelease een titel en een beschrijving mee en zorg dat ‘*This is a pre-release’* *niet* is aangevinkt.

<img src="./media/image29.PNG" width="604" height="524" />

Druk tenslotte op ‘Publish release’

Om een bestaande pre-release in productie te brengen: Klik op de ‘Edit’ button behorend bij het bestaande release.

Bijlage alternatief voor release
================================

Als alternatief voor een nieuwe release is het ook mogelijk een bestaande (pre) release te editen. Klik bij releases op de release welke je wil aanpassen (bijvoorbeeld een pre-release die naar productie gaat) op de edit knop.

<img src="./media/image30.PNG" width="604" height="542" />

Pas de tag en titel en eventueel de begeleidende tekst aan en klik op update release.

<img src="./media/image31.PNG" width="604" height="517" />

[1] https://www.forumstandaardisatie.nl/fileadmin/os/publicaties/HR\_BOMOS\_\_FINAL\_web.pdf

[2] Kan ook nog een verdere folder structuur bevatten, meestal de inhoud van een zip met schema informatie zoals geleverd bij StUF uitwisselingen.

[3] Kan ook nog verdere folder structuur bevatten
