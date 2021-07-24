<?php
/*PhpDoc:
name:  skosrdf.php
title: skosrdf.php - test d'utilisation des fichiers Skos RDF/XML d'EuroVoc
doc: |
  Dans les différents fichiers téléchargeables sur la page EuroVoc, eurovoc_in_skos_core_concepts.zip semble le plus
  utilisable. Il correspond à du RDF/XML donc pas très lisible.
  Il est cependant suffisament petit (53 Mo) pour pouvoir être importé dans EasyRdf pour
    1) générer du Turtle pour visualiser son contenu,
    2) générer du JSON-LD assez exploitable en Php,
    3) supprimer les langues autres que 'fr' et 'en' pour l'alléger
journal: |
  22/7/2021:
    première version
*/
require_once __DIR__.'/vendor/autoload.php';

ini_set('memory_limit','2G');
//set_time_limit(5*60);

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if ($argc == 1) {
  echo "usage: php $argv[0] {option} ({id})\n";
  echo " où {option} vaut:\n";
  echo "  - ttl : sortie en Turtle en supprimant les langues autres que fr et en\n";
  echo "  - yamlld : sortie en Yaml-LD en supprimant les langues autres que fr et en et en mettant @id en clé\n";
  echo " où {id} est éventuellement l'identifiant d'un élément ; s'il est fourni alors seul cet élément est sélectionné.\n";
  die();
}
$option = $argv[1];
$id = $argv[2] ?? null;
//echo $argc; die();

$data = file_get_contents(__DIR__.'/eurovoc_in_skos_core_concepts.rdf');
$graph = new \EasyRdf\Graph('http://eurovoc.europa.eu/', $data, 'application/rdf+xml');
$data = null; // libération mémoire

// création JSON-LD et suppression langues autres que 'fr' et 'en'
$jsonld = $graph->serialise('jsonld');
$graph = null; // libération mémoire
$langs = [
  'bg','cs','da','de','el','es','et','fi','ga','hr','hu','it','lt',
  'lv','mk','mt','nl','pl','pt','ro','sk','sl','sq','sr','sv',
];
foreach ($langs as $lang) {
  $pattern = sprintf('!{"@value":"[^"]+","@language":"%s"},!', $lang);
  $jsonld = preg_replace($pattern, '', $jsonld);
  $pattern = sprintf('!,{"@value":"[^"]+","@language":"%s"}!', $lang);
  $jsonld = preg_replace($pattern, '', $jsonld);
}
//echo $jsonld;
// $jsonld contient le JSON-LD en text

if (($option == 'ttl') && !$id) { // sortie en Turtle en supprimant les langues autres que fr et en
  $graph = new \EasyRdf\Graph('http://eurovoc.europa.eu/', $jsonld, 'application/ld+json');
  echo $graph->serialise('text/turtle');
}

$jsonld = json_decode($jsonld, true);

if (($option == 'yamlld') && !$id) { // sortie en Yaml-LD en supp. les langues autres que fr et en et en mettant @id en clé
  foreach ($jsonld as $elt) {
    $uri = $elt['@id'];
    unset($elt['@id']);
    echo Yaml::dump([$uri=> $elt], 9, 2);
  }
}

foreach ($jsonld as $elt) {
  $uri = $elt['@id'];
  if ($uri <> "http://eurovoc.europa.eu/$id") continue;
  if ($option == 'yamlld') {
    unset($elt['@id']);
    echo Yaml::dump([$uri => $elt], 9, 2);
  }
  else {
    $graph = new \EasyRdf\Graph('http://eurovoc.europa.eu/', json_encode($elt), 'application/ld+json');
    echo $graph->serialise('text/turtle');
  }
  die();
}
