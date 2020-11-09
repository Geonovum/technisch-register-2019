<?php
// TODO: document config
// how to deal with staging and production environment? Have a separate config for this?
include './autodeploy/registerConfig.php';
$urlparts = explode("/", $_GET['url']);
$cluster = False;
$indexPage = True;
$clusterPage = False;
$repo = False;
$clusterData=[];
if (count($urlparts) > 0 ) {
  $cluster = $urlparts[0];
  // if a cluster has 1 model, directly create an informationmodel page, otherwise create a cluster page
  $repo = $cluster;
  if (count($urlparts) > 1 ) {
    if ($urlparts[1] != "index.html" ){
      $repo = $urlparts[1];
    }
  }

  $clustersJson = file_get_contents($clustersURL);
  $clustersArr = json_decode($clustersJson, true);
  foreach ($clustersArr as $cl) {
      if ($cl["id"]==$cluster) {
        $clusterData = $cl;
        $indexPage = False;
      }
  }

  // Use the length of the cluster array (excluding index.html)
  $reposJson = file_get_contents($reposURL);
  $reposArr = json_decode($reposJson, true);
  $repoData = False;
  $models = [];
  // TODO: make more robust
  foreach ($reposArr as $re) {
      if ($re["id"]==$repo) {
        $repoData = $re;
        $indexPage = False;
      }
      if ($re["cluster"] == $cluster && $cluster!="" ) {
        // get all models for the cluster
        array_push($models, $re);
      }
  }

  if (count($models) > 0 && $repoData == False) {
    // no repos, but models: we are in a clusterPage
    $clusterPage = True;
  }
  // find all directories for a repo?
}

?><html lang="nl">
 <head>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
  <title>
   Technisch register voor geo-standaarden in Nederland
  </title>
  <link href="./resources/css/style.css" rel="stylesheet" type="text/css"/>
  <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet"/>
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
     <?php if ($cluster) { ?>
       &gt;
       <a href="<?=$baseURL;?><?=$cluster?>">
        <?=$clusterData["titel_kort"];?>
       </a>
     <?php } ?>
     <?php if ($repo && $repo!=$cluster) { ?>
       &gt;
       <a href="<?=$baseURL;?><?=$cluster."/".$repoData["id"]?>">
        <?=$repoData["titel_kort"];?>
       </a>
     <?php } ?>
    </div>
   </div>
  </header>
  <article class="exp">
   <?php if ($clusterPage || $indexPage) { ?>
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
    // if a cluster page, find the matching clusterinfo
    // if the general index, list all clusters
      if ($clusterPage) {
        // only display relevant models with the proper URL
        foreach ($models as $model) {
          ?>
          <p>
             <i class="fa fa-file">
             </i>
             <span style="margin-left: 25px">
            <?php
              echo '<a href="./'.$cluster.'/'.$model["id"].'">';
              echo $model["titel_kort"].'</a>' ; //BRT
              ?>
             </span>
            </p>
            <p>
             <span style="margin-left:37px; width: 100%">
               <?= $model["beschrijving_kort"];?>
             </span>
            </p>
            <?php
        }
      } else {
        // display all clusters, with a URL to a index.html page
        $clustersJson = file_get_contents($clustersURL);
        $clustersArr = json_decode($clustersJson, true);
        foreach ($clustersArr as $cluster) {
        ?>
        <p>
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
    }
    ?>
    </div>
    <div id="rightcolumn">
      <?php
        include "listDescriptions.php";
      ?>
    </div>
    <?php } else {
      // If not, we are in a repo page, for a specific model
      ?>
      <div>
      <h2>
       <?=$clusterData["titel"];?>
      </h2>
      <p>
       <?=$clusterData["beschrijving"];?>
      </p>
         </div>
         <div id="container">
           <!-- TODO: find corresponding dirs, and match descriptions. Create URLs based on clusterid  -->
          <div id="leftcolumn">
            <?php
              include "listDescriptions.php";
            ?>
          </div>
         </div>
    <?php } ?>

   </div>
  </article>
  <footer>
   <?php
    include "./resources/html/footer.html";
   ?>
  </footer>
 </body>
</html>
