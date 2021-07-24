<?php
/*PhpDoc:
name:  skosrdf.php
title: skosrdf.php - test d'utilisation des fichiers Skos RDF/XML d'EuroVoc v2
doc: |
  Dans les différents fichiers téléchargeables sur la page EuroVoc, eurovoc_in_skos_core_concepts.zip semble le plus
  utilisable. Il correspond à du RDF/XML donc pas très lisible.
  Il est cependant suffisament petit (53 Mo) pour pouvoir être importé dans EasyRdf.
  Dans cette version je gère en pser la sérialisation Php de ce fichier avec suppression des langues autres que fr et en.
  Cette structure Php assez simple peut être ré-injectée dans EasyRdf par exemple pour obtenir une sérialisation Turtle.
journal: |
  24/7/2021:
    première version
*/
require_once __DIR__.'/vendor/autoload.php';

define ('LANGS', ['en','fr']);

ini_set('memory_limit','2G');
//set_time_limit(5*60);

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if ($argc == 1) {
  echo "usage: php $argv[0] {option} ({id})\n";
  echo " où {option} vaut:\n";
  echo "  - ttl : sortie en Turtle\n";
  echo "  - yamlld : sortie en Yaml-LD\n";
  echo "  - yamlskos : sortie du yamlSkos\n";
  echo "  - easyRdf : affichage de caractéristique d'EasyRdf\n";
  die();
}
$option = $argv[1];
$id = $argv[2] ?? null;

if ($option == 'easyRdf') { // affichage de caractéristique d'EasyRdf
  echo Yaml::dump(['\EasyRdf\Format::getNames()' => \EasyRdf\Format::getNames()], 9, 2);
  echo '\EasyRdf\Format::getFormats(): '; print_r(\EasyRdf\Format::getFormats());
  die();
}

if (is_file(__DIR__.'/eurovoc_in_skos_core_concepts.pser')) {
  $graph = unserialize(file_get_contents(__DIR__.'/eurovoc_in_skos_core_concepts.pser'));
}
else {
  $graph = file_get_contents(__DIR__.'/eurovoc_in_skos_core_concepts.rdf');
  $graph = new \EasyRdf\Graph('http://eurovoc.europa.eu/', $graph, 'rdfxml');
  $graph = $graph->serialise('php');
  foreach ($graph as $s => $po) {
    //echo Yaml::dump(['source'=> [$s => $po]], 4, 2);
    $r = [];
    foreach ($po as $p => $o) {
      foreach ($o as $i => $v) {
        if (($v['type']<>'literal') || !isset($v['lang']) || in_array($v['lang'], LANGS)) {
          $r[$p][] = $v;
        }
      }
    }
    //echo Yaml::dump(['dest'=> [$s => $r]], 4, 2);
    $graph[$s] = $r;
  }
  file_put_contents(__DIR__.'/eurovoc_in_skos_core_concepts.pser', serialize($graph));
}

if ($id)
  $graph = ["http://eurovoc.europa.eu/$id" => $graph["http://eurovoc.europa.eu/$id"]];

if ($option == 'ttl') {
  $graph = new \EasyRdf\Graph('http://eurovoc.europa.eu/', $graph, 'php');
  echo $graph->serialise('turtle');
  die();
}

// permet de simplifier une ressource en utilisant des raccourcis à la Turtle pour faciliter la lecture
class Context {
  public array $prefixes=[];
  
  function __construct(array $prefixes) {
    $this->prefixes = $prefixes;
  }
  
  function simplify(array $resource): array {
    foreach ($resource as $p => $o) {
      $r[$this->prefixes[$p] ?? $p] = $o;
    }
    foreach ($r as $p => $o) {
      foreach ($o as $i => $v) {
        if (($v['type']=='uri') && isset($this->prefixes[$v['value']]))
          $r[$p][$i]['value'] = $this->prefixes[$v['value']];
      }
    }
    return $r;
  }
};

$context = new Context([
  'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => 'rdf:type',
  'http://www.w3.org/2004/02/skos/core#Concept'=> 'skos:Concept',
  'http://www.w3.org/2004/02/skos/core#inScheme'=> 'skos:inScheme',
  'http://www.w3.org/2004/02/skos/core#ConceptScheme'=> 'skos:ConceptScheme',
  'http://www.w3.org/2004/02/skos/core#topConceptOf'=> 'skos:topConceptOf',
  'http://www.w3.org/2004/02/skos/core#prefLabel'=> 'skos:prefLabel',
  'http://www.w3.org/2004/02/skos/core#altLabel'=> 'skos:altLabel',
  'http://www.w3.org/2004/02/skos/core#definition'=> 'skos:definition',
  'http://www.w3.org/2004/02/skos/core#editorialNote'=> 'skos:editorialNote',
  'http://www.w3.org/2004/02/skos/core#historyNote'=> 'skos:historyNote',
  'http://www.w3.org/2004/02/skos/core#scopeNote'=> 'skos:scopeNote',
  'http://www.w3.org/2004/02/skos/core#narrower' => 'skos:narrower',
  'http://www.w3.org/2004/02/skos/core#broader' => 'skos:broader',
  'http://www.w3.org/2004/02/skos/core#related' => 'skos:related',
  'http://www.w3.org/2004/02/skos/core#notation'=> 'skos:notation'
]
);

if ($option == 'yamlld') { // Affichage Yaml à la JSON-LD en mettant les URI entre <> et la langue comme en Turtle
  foreach ($graph as $s => $resource) {
    $r = [];
    foreach ($context->simplify($resource) as $p => $o) {
      foreach ($o as $i => $v) {
        if ($v['type']=='uri')
          $r[$p][] = "<$v[value]>";
        elseif (($v['type']=='literal') && isset($v['lang']))
          $r[$p][] = "$v[value]@$v[lang]";
        elseif ($v['type']=='literal')
          $r[$p][] = $v['value'];
        else
          $r[$p][] = $v;
      }
    }
    echo Yaml::dump([$s => $r], 3, 2);
  }
  die();
}

/*function notNull(array $array): array {
  $a = [];
  foreach ($array as $k => $v)
    if ($v !== null)
      $a[$k] = $v;
  return $a;
}*/

if ($option == 'yamlskos') {
  require_once __DIR__.'/yamlskos.inc.php';
  $prf = [
    'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'skos' => 'http://www.w3.org/2004/02/skos/core#',
  ];
  YamlSkos::$titles = [
    'fr'=> "EuroVoc, thésaurus multilingue de l'Union européenne",
    'en'=> "EuroVoc, the EU's multilingual thesaurus",
  ];
  foreach ($graph as $uri => $resource) {
    $id = substr($uri, strlen('http://eurovoc.europa.eu/'));
    if ($resource["$prf[rdf]type"][0]['value'] == "$prf[skos]ConceptScheme") { // construction des ConceptScheme
      unset($resource["$prf[rdf]type"]);
      echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      YamlSkos::$schemes[$id]['domain'] = [];
      foreach ($resource["$prf[skos]prefLabel"] as $v)
        YamlSkos::$schemes[$id]['prefLabel'][$v['lang']] = $v['value'];
      unset($resource["$prf[skos]prefLabel"]);
      YamlSkos::$schemes[$id]['hasTopConcept'] = [];
      if (isset($resource["$prf[skos]notation"])) {
        foreach ($resource["$prf[skos]notation"] as $v)
          YamlSkos::$schemes[$id]['notation'][] = $v['value'];
        unset($resource["$prf[skos]notation"]);
      }
      if ($resource) {
        //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
        echo Yaml::dump(['error'=> [$id => $resource]], 4, 2);
      }
      echo Yaml::dump(['schemes'=> [$id => YamlSkos::$schemes[$id]]], 4, 2);

      /*
        100166:
          domain:
            - 100142
          prefLabel:
            fr: 0421 Parlement
            en: 0421 parliament
          hasTopConcept:
            - 2246
            - 41
            - 2242
            - 3232
            - 53
          notation:
            - 0421
      */
      //die();
    }
    elseif ($resource["$prf[rdf]type"][0]['value'] == "$prf[skos]Concept") { // construction des concepts
      //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      unset($resource["$prf[rdf]type"]);
      YamlSkos::$concepts[$id] = [];
      if (isset($resource["$prf[skos]inScheme"])) {
        foreach ($resource["$prf[skos]inScheme"] as $v)
          YamlSkos::$concepts[$id]['inScheme'][] = substr($v['value'], strlen('http://eurovoc.europa.eu/'));
        unset($resource["$prf[skos]inScheme"]);
      }
      if (isset($resource["$prf[skos]topConceptOf"])) {
        foreach ($resource["$prf[skos]topConceptOf"] as $v)
          YamlSkos::$concepts[$id]['topConceptOf'][] = substr($v['value'], strlen('http://eurovoc.europa.eu/'));
        unset($resource["$prf[skos]topConceptOf"]);
      }
      // Le champ pour lequel il ne peut y avoir qu'un littéral par langue
      if (isset($resource["$prf[skos]prefLabel"])) {
        foreach ($resource["$prf[skos]prefLabel"] as $v)
          YamlSkos::$concepts[$id]['prefLabel'][$v['lang']] = $v['value'];
        unset($resource["$prf[skos]prefLabel"]);
      }
      // Les champs pour lesquels il peut y avoir plusieurs littéraux par langue
      foreach (['altLabel','definition','scopeNote','editorialNote','changeNote','historyNote'] as $labelNote) {
        if (isset($resource["$prf[skos]$labelNote"])) {
          foreach ($resource["$prf[skos]$labelNote"] as $v)
            YamlSkos::$concepts[$id][$labelNote][$v['lang']][] = $v['value'];
          unset($resource["$prf[skos]$labelNote"]);
        }
      }
      // Les relations
      foreach (['broader','narrower','related'] as $labelLink) {
        if (isset($resource["$prf[skos]$labelLink"])) {
          foreach ($resource["$prf[skos]$labelLink"] as $v)
            YamlSkos::$concepts[$id][$labelLink][] = substr($v['value'], strlen('http://eurovoc.europa.eu/'));
          unset($resource["$prf[skos]$labelLink"]);
        }
      }
      if (isset($resource["$prf[skos]notation"])) {
        foreach ($resource["$prf[skos]notation"] as $v)
          YamlSkos::$concepts[$id]['notation'][] = $v['value'];
        unset($resource["$prf[skos]notation"]);
      }
      if ($resource) {
        echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
        echo Yaml::dump(['error'=> [$id => $resource]], 4, 2);
      }
      //echo Yaml::dump(['concepts'=> [$id => YamlSkos::$concepts[$id]]], 4, 2);
    }
    else {
      echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      die();
    }
    //echo Yaml::dump([$id => $resource], 3, 2);
    //die();
  }
}
