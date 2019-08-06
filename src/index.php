<?php
// TODO: document config
// how to deal with staging and production environment? Have a separate config for this?
include './autodeploy/registerConfig.php';
?><html lang="nl">
 <head>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
  <title>
   Artefact Repository for Geo-Standards in the Netherlands
  </title>
  <!-- TODO: resources directories: ../resources/.. -->
  <link href="./resources/css/style.css" rel="stylesheet" type="text/css"/>
  <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet"/>
  <!-- <script src="./resources/js/jquery-1.9.1.js" type="text/javascript"> -->
  </script>
 </head>
 <body>
  <header>
   <div id="topmenu_container">
     <?php
      include "./resources/html/header.html";
     ?>
   </div>
   <div id="breadctxt_container">
    <div id="breadctxt">
     <a href="<?=$baseURL;?>">
      Technisch register voor geo-standaarden in Nederland
     </a>
    </div>
   </div>
  </header>
  <article class="exp">
   <h2>
    Technisch register voor geo-standaarden in Nederland
   </h2>
   <p>
    Het technisch register is de centrale vindplaats voor de informatiemodellen uit het
    <a href="http://www.geonovum.nl/onderwerpen/basismodel-geo-informatie-nen3610/algemeen-basismodel-geo-informatie-nen3610">
     NEN3610
    </a>
    stelsel, plus de technische standaarden die bij die informatiemodellen horen. Deze technische standaarden implementeren het informatiemodel en haar regels in bijvoorbeeld XML Schema en Schematron, maar het kan ook gaan om implementatiebestanden voor visualisatieregels en iconen. Al deze ‘technische’ bestanden zijn te vinden in dit register.
   </p>
   <p>
    Dit technisch register voor geo-standaarden wordt beheerd door
    <a href="http://www.geonovum.nl">
     Geonovum
    </a>
    . Ook Nederlandse geo-standaarden die niet bij Geonovum in beheer zijn, maar wél onderdeel van het NEN3610 stelsel zijn, zijn hier te vinden. Dit kan ofwel fysiek, ofwel via een referentie zijn naar een eigen register van de beheerder van de desbetreffende standaard.
   </p>
   <div id="container">
    <div id="leftcolumn">
     <h3>
      Ingang: informatiemodel
     </h3>
    <?php
    $clustersJson = file_get_contents($clustersURL);
    $clustersArr = json_decode($clustersJson, true);
    foreach ($clustersArr as $cluster) {
      ?><p>
       <i class="fa fa-file">
       </i>
       <span style="margin-left: 25px">
      <?php
        echo '<a href="./'.$cluster["id"].'/index.html">';
        echo $cluster["titel_kort"].'</a>' ; //BRT
        ?>
       </span>
      </p>
      <p>
       <span style="margin-left:37px; width: 100%">
         <?= $cluster["beschrijving_kort"];?>
       </span>
      </p>
      <?php
    }
    ?>
    </div>
    <div id="rightcolumn">
     <h3>
      Ingang: soort bestand
     </h3>
     <p>
      <i class="fa fa-file-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>informatiemodel/">
        Informatiemodel
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       UML informatiemodellen (*.EAP of *.XMI). UML modellen worden hier aangeboden voor technische doeleinden zoals het genereren van schema's/code of het hergebruiken van entiteiten uit elkaars informatiemodel.
      </span>
     </p>
     <p>
      <i class="fa fa-file-code-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>gmlapplicatieschema/">
        GML applicatieschema
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       GML application schemas: XML schema's die een valide extensie van
       <a href="http://www.opengeospatial.org/standards/gml">
        GML
       </a>
       zijn.
      </span>
     </p>
     <p>
      <i class="fa fa-file-code-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>xmlschema/">
        XML Schema
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       <a href="https://www.gemmaonline.nl/index.php/StUF_Berichtenstandaard">
        StUF
       </a>
       schema's en andere XML schema's
      </span>
     </p>
     <p>
      <i class="fa fa-file-code-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>regels/">
        Regels
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       <a href="http://www.schematron.com">
        Schematron
       </a>
       schema's, waarin validatieregels aanvullend op GML of XML schema's zijn opgenomen.
      </span>
     </p>
     <p>
      <i class="fa fa-file-text-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>waardelijst/">
        Waardelijst
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       Waardelijsten worden in het technisch register als downloadbaar bestand opgenomen. De inhoud van waardelijsten is in de
       <a href="http://definities.geostandaarden.nl">
        conceptenbibliotheek
       </a>
       te raadplegen.
      </span>
     </p>
     <p>
      <i class="fa fa-file-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>wsdl/">
        WSDL
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       Web Services Description Language (WSDL) bestanden, die beschrijven welke services en operaties een webapplicatie aanbiedt voor het zenden/ontvangen van berichten.
      </span>
     </p>
     <p>
      <i class="fa fa-file-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>visualisatie/">
        Visualisatie
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       Regels die specificeren hoe geografische objecten op de kaart weergegeven moeten worden, doorgaans geïmplementeerd in SLD/SE
      </span>
     </p>
     <p>
      <i class="fa fa-file-image-o">
      </i>
      <span style="margin-left: 25px">
       <a href="<?=$baseURL;?>symbool/">
        Symbool
       </a>
      </span>
     </p>
     <p>
      <span style="margin-left:37px; width: 100%">
       Symbolen / iconen voor het weergeven van geografische objecten op kaarten
      </span>
     </p>
    </div>
   </div>
  </article>
  <footer>
   <?php
    include "./resources/html/footer.html";
   ?>
  </footer>
 </body>
</html>
