<?php
// TODO: document config
include './autodeploy/registerConfig.php';
// repo
// if repoData: then display specific descriptions, loop over them
?>
 <h3>
  Ingang: soort bestand
 </h3>
<?php
$filter = False;
if ($repoData) {
  // check for all description types if a dir is available
  $descriptionsJson = file_get_contents($descriptionsURL);
  $descriptionsArr = json_decode($descriptionsJson, true);
  $descriptions = [];
  foreach ($descriptionsArr as $key => $name) {
    array_push($descriptions, $key);
    $descModelDir = $key."/".$repoData["id"];
    if (is_dir($descModelDir)) {
      ?>
      <p>
       <i class="fa fa-file-o">
       </i>
       <span style="margin-left: 25px">
        <a href="<?=$baseURL;?><?=$descModelDir?>">
         <?=$descriptionsArr[$key]["titel"]?>
        </a>
       </span>
      </p>
      <p>
       <span style="margin-left:37px; width: 100%">
         <?=$descriptionsArr[$key]["beschrijving"]?>
       </span>
      </p>
    <?php }
  }
} else {
  // list all descriptions (we are in a cluster page or the index), inluding a nicer icon
?>
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
<?php } ?>
