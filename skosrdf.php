<?php
/*PhpDoc:
name:  skosrdf.php
title: skosrdf.php - utilisation d'un fichier Skos RDF/XML d'EuroVoc avec EasyRdf (v2)
doc: |
  Ce script a pour objectifs de :
   1) consulter le fichier Skos RDF/XML d'EuroVoc en Turtle ou YamlLd
   2) générer le fichier eurovoc.yam structuré selon le schéma YamlSkosV2

  Le fichier RDF/XML utilisé provient de la page ci-dessous de distribution d'EuroVoc:
  https://op.europa.eu/fr/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/eurovoc
  C'est celui nommé eurovoc_in_skos_core_concepts.zip
  Dézipper il est formaté en RDF/XML donc pas très lisible.
  Il est cependant suffisament petit (53 Mo) pour être importé dans EasyRdf.
  Ce script exploite ce fichier Skos en utilisant EasyRdf pour le lire.
  Un fichier pser est constitué pour accélérer les traitements, il correspond à la sérialisation Php du fichier RDF/XML
  en supprimant les langues autres que fr et en.
  Cette sérialisation Php assez simple peut être ré-injectée dans EasyRdf par exemple pour obtenir une sérialisation Turtle.

  La sérialisation Php d'EasyRdf génère un graphe Php sous la forme [{subject} => [{predicate} => [{valeur}]]]
  où:
    - {subject} et {predicate} sont chacun un URI
    - {valeur} correspond à une des ressources associée à {subject} et {predicate},
      c'est soit un URI, soit un litéral, éventuellement associé à une lange
      structuré comme un dict. ['type'=> ('uri'|'literal'), 'value'=>{value}, ('lang'=>{lang})?]

  Ce fichier Skos a les particularités suivantes :
    - Chaque domaine est défini comme un skos:Concept appartenant au scheme <http://eurovoc.europa.eu/domains>
      exemple
        <http://eurovoc.europa.eu/100142>
          a skos:Concept ;
          skos:inScheme <http://eurovoc.europa.eu/domains> ;
          skos:notation "04" ;
          skos:prefLabel "04 POLITICS"@en, "04 VIE POLITIQUE"@fr ;
          skos:topConceptOf <http://eurovoc.europa.eu/domains> .
    - L'ensemble des domaines est défini comme le skos:ConceptScheme
      <http://eurovoc.europa.eu/domains>
        a skos:ConceptScheme ;
        skos:prefLabel "Eurovoc domains"@en .
    - Il existe un scheme particulier ayant pour prefLabel "EuroVoc" qui comprend tous les topConcepts
      <http://eurovoc.europa.eu/100141>
        a skos:ConceptScheme ;
        skos:prefLabel "EuroVoc"@en, "EuroVoc"@fr .
      Je le conserve comme Scheme hors domaine (domaine 0)

  Le fichier ne contient pas le lien d'appartenance d'un ConceptScheme à son domaine.
  Ce lien peut être construit en utilisant les chiffres initiaux du prefLabel.

  Dans la génération du fichier eurovoc.yaml conforme au schéma YamlSkosV2 :
    - les concepts appartenant au scheme <http://eurovoc.europa.eu/domains> sont définis comme des domaines ;
      leur id est défini par l'entier constitué des 2 premiers chiffres du prefLabel.
    - le scheme <http://eurovoc.europa.eu/domains> est éliminé
    - le scheme EuroVoc est conservé comme Scheme hors domaine (domaine=0)

  Suppression:
    - du concept BAT qui n'existe pas dans la version interactive
journal: |
  24-25/7/2021:
    première version
*/
require_once __DIR__.'/vendor/autoload.php';

define ('PRESERVED_LANGS', ['en','fr']); // langues conservées, les autres sont supprimées, si faux alors pas de suppression

ini_set('memory_limit','2G');
//set_time_limit(5*60);

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

if ($argc == 1) {
  echo "usage: php $argv[0] {option} ({id})\n";
  echo " où {option} vaut:\n";
  echo "  - ttl : sortie en Turtle\n";
  echo "  - yamlld : sortie en Yaml-LD\n";
  echo "  - yamlskos : génère un YamlSkosV2\n";
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

if (is_file(__DIR__.'/eurovoc_in_skos_core_concepts.pser')) { // si pser existe alors il contient le graphe sérialisé Php
  $graph = unserialize(file_get_contents(__DIR__.'/eurovoc_in_skos_core_concepts.pser'));
}
else { // Sinon je le construit à partir du RDF/XML en utilisant EasyRdf puis en supprimant les langues hors PRESERVED_LANGS
  $graph = file_get_contents(__DIR__.'/eurovoc_in_skos_core_concepts.rdf');
  $graph = new \EasyRdf\Graph('http://eurovoc.europa.eu/', $graph, 'rdfxml');
  $graph = $graph->serialise('php');
  if (PRESERVED_LANGS) { // suppression des langues autres que celles dans LANGS
    foreach ($graph as $s => $po) {
      //echo Yaml::dump(['source'=> [$s => $po]], 4, 2);
      $r = [];
      foreach ($po as $p => $o) {
        foreach ($o as $i => $v) {
          if (($v['type']<>'literal') || !isset($v['lang']) || in_array($v['lang'], PRESERVED_LANGS)) {
            $r[$p][] = $v;
          }
        }
      }
      //echo Yaml::dump(['dest'=> [$s => $r]], 4, 2);
      $graph[$s] = $r;
    }
  }
  file_put_contents(__DIR__.'/eurovoc_in_skos_core_concepts.pser', serialize($graph));
}

if ($id) { // si le paramètre id est présent alors je restreint le graphe à la ressource identifiée par cet id
  $graph = ["http://eurovoc.europa.eu/$id" => $graph["http://eurovoc.europa.eu/$id"]];
}

if ($option == 'ttl') { // génération du graphe en Turtle 
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

if ($option == 'yamlskos') { // génération d'un fichier YamlSkos
  // les préfixes
  $prf = [
    'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'skos' => 'http://www.w3.org/2004/02/skos/core#',
  ];
  $yamlSkos = [
    'title' => [
      'fr'=> "EuroVoc, thésaurus multilingue de l'Union européenne",
      'en'=> "EuroVoc, the EU's multilingual thesaurus",
    ],
    'language'=> PRESERVED_LANGS,
    'issued'=> '2021-06-04', // identification de la version téléchargée
    '$schema'=> 'yamlskosv2', // le nom du fichier contenant le schéma, sans .schema.yaml
    'domains'=> [],
    'schemes'=> [],
    'concepts'=> [],
  ];
  foreach ($graph as $uri => $resource) {
    $id = substr($uri, strlen('http://eurovoc.europa.eu/'));
    if ($id == 'domains') // le scheme <http://eurovoc.europa.eu/domains> des domaines n'est pas considéré comme un scheme
      continue;
    if (is_numeric($id))
      $id = (int)$id;
    if ($resource["$prf[rdf]type"][0]['value'] == "$prf[skos]ConceptScheme") { // construction des ConceptScheme
      //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      unset($resource["$prf[rdf]type"]);
      $scheme = ['domain'=> 0];
      foreach ($resource["$prf[skos]prefLabel"] as $v)
        $scheme['prefLabel'][$v['lang']] = $v['value'];
      unset($resource["$prf[skos]prefLabel"]);
      if (isset($scheme['prefLabel']['fr'])) {
        if ($scheme['prefLabel']['fr'] <> 'EuroVoc') {
          $domain = substr($scheme['prefLabel']['fr'], 0, 2);
          $scheme['domain'] = is_numeric($domain)  ? (int)$domain : $domain;
        }
      }
      // le champ hasTopConcept est renseigné à partir des de la lecture des concepts
      // Certains topConcepts ont pu être lus avant le scheme, il faut donc dans ce cas les récupérer
      $scheme['hasTopConcept'] = $yamlSkos['schemes'][$id]['hasTopConcept'] ?? [];
      if (isset($resource["$prf[skos]notation"])) {
        foreach ($resource["$prf[skos]notation"] as $v)
          $scheme['notation'][] = is_numeric($v['value']) ? (int)$v['value'] : $v['value'];
        unset($resource["$prf[skos]notation"]);
      }
      if ($resource) {
        //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
        echo Yaml::dump(['error'=> [$id => $resource]], 4, 2);
      }
      //echo Yaml::dump(['schemes'=> [$id => $scheme]], 4, 2);
      $yamlSkos['schemes'][$id] = $scheme;
    }
    elseif ($resource["$prf[rdf]type"][0]['value'] == "$prf[skos]Concept") { // construction des concepts
      //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      unset($resource["$prf[rdf]type"]);
      $concept = []; // structuration à la YamlSkosV2
      if (isset($resource["$prf[skos]inScheme"])) {
        foreach ($resource["$prf[skos]inScheme"] as $v) {
          $inScheme = substr($v['value'], strlen('http://eurovoc.europa.eu/'));
          $concept['inScheme'][] = is_numeric($inScheme) ? (int)$inScheme : $inScheme;
        }
        unset($resource["$prf[skos]inScheme"]);
      }
      if (isset($resource["$prf[skos]topConceptOf"])) {
        foreach ($resource["$prf[skos]topConceptOf"] as $v) {
          $topConceptOf = substr($v['value'], strlen('http://eurovoc.europa.eu/'));
          if ($topConceptOf <> 'domains') {
            if (is_numeric($topConceptOf))
              $topConceptOf = (int)$topConceptOf;
            $concept['topConceptOf'][] = $topConceptOf;
          }
        }
        unset($resource["$prf[skos]topConceptOf"]);
      }
      // Le champ pour lequel il ne peut y avoir qu'un littéral par langue
      if (isset($resource["$prf[skos]prefLabel"])) {
        foreach ($resource["$prf[skos]prefLabel"] as $v)
          $concept['prefLabel'][$v['lang']] = $v['value'];
        unset($resource["$prf[skos]prefLabel"]);
      }
      // Suppression de certains artéfacts
      if ($concept['prefLabel']['fr'] == "BAT") {
        continue;
      }
      // report des topConceptOf, après la suppression des artéfacts
      foreach ($concept['topConceptOf'] ?? [] as $topConceptOf) {
        $yamlSkos['schemes'][$topConceptOf]['hasTopConcept'][] = $id;
      }
      // Les champs pour lesquels il peut y avoir plusieurs littéraux par langue
      foreach (['altLabel','definition','scopeNote','editorialNote','changeNote','historyNote'] as $labelNote) {
        if (isset($resource["$prf[skos]$labelNote"])) {
          foreach ($resource["$prf[skos]$labelNote"] as $v)
            $concept[$labelNote][$v['lang']][] = $v['value'];
          unset($resource["$prf[skos]$labelNote"]);
        }
      }
      // Les relations
      foreach (['broader','narrower','related'] as $labelLink) {
        if (isset($resource["$prf[skos]$labelLink"])) {
          foreach ($resource["$prf[skos]$labelLink"] as $v) {
            $val = substr($v['value'], strlen('http://eurovoc.europa.eu/'));
            if (is_numeric($val))
              $val = (int)$val;
            $concept[$labelLink][] = $val;
          }
          unset($resource["$prf[skos]$labelLink"]);
        }
      }
      if (isset($resource["$prf[skos]notation"])) {
        foreach ($resource["$prf[skos]notation"] as $v)
          $concept['notation'][] = is_numeric($v['value']) ? (int)$v['value'] : $v['value'];
        unset($resource["$prf[skos]notation"]);
      }
      if ($resource) { // des champs de la ressource n'ont pas été transférés
        echo "Erreur: des champs de la ressource Concept n'ont pas été pris en compte\n";
        echo Yaml::dump(['error'=> [$id => $resource]], 4, 2);
        die();
      }
      if ($concept['inScheme'][0] == 'domains') { // Les concepts du scheme domains sont des domaines et pas des concepts
        //echo Yaml::dump(['concepts'=> [$id => $concept]], 4, 2);
        $did = (int)substr($concept['prefLabel']['fr'], 0, 2); // les 2 premiers caractères forment le numéro du doamine
        $yamlSkos['domains'][$did] = [
          'prefLabel'=> $concept['prefLabel'],
          'notation'=> $concept['notation'],
          'eurovocId'=> $id,
        ];
      }
      else // autre concept
        $yamlSkos['concepts'][$id] = $concept;
    }
    else { // cas d'erreur où la ressource n'est ni un concept ni un conceptScheme
      echo "Erreur: la ressource suivante n'est ni un concept ni un scheme\n";
      echo Yaml::dump(['error'=> [$id => $resource]], 4, 2);
      die();
    }
  }
  echo Yaml::dump($yamlSkos, 5, 2);
}
