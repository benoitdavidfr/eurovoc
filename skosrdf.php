<?php
/*PhpDoc:
name:  skosrdf.php
title: skosrdf.php - utilisation d'un fichier Skos RDF/XML d'EuroVoc avec EasyRdf (v2)
doc: |
  Dans les différents fichiers téléchargeables sur la page EuroVoc, eurovoc_in_skos_core_concepts.zip semble le plus
  utilisable. Il est formaté en RDF/XML donc pas très lisible.
  Il est cependant suffisament petit (53 Mo) pour être importé dans EasyRdf.
  Ce script exploite ce fichier Skos en utilisant EasyRdf pour le lire.
  Un fichier pser est constitué pour accélérer les traitements, il correspond à la sérialisation Php du fichier RDF/XML
  en supprimant les langues autres que fr et en.
  Cette sérialisation Php assez simple peut être ré-injectée dans EasyRdf par exemple pour obtenir une sérialisation Turtle.

  La sérialisation Php d'EasyRdf génère un graphe Php sous la forme [{subject} => [{predicate} => [{valeur}]]]
  où:
    - {subject} et {predicate} sont chacun un URI
    - {valeur} correspond à un des objets associé à {subject} et {predicate},
      c'est soit un URI soit un litéral éventuellement associé à une lange
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
    - Il existe un scheme ayant pour prefLabel "EuroVoc" qui comprend tous les topConcepts
      <http://eurovoc.europa.eu/100141>
        a skos:ConceptScheme ;
        skos:prefLabel "EuroVoc"@en, "EuroVoc"@fr .

  Le fichier ne contient pas la relation entre un ConceptScheme et son domaine.

  Ce script a pour objectifs de :
   1) consulter le fichier Skos RDF/XML d'EuroVoc en Turtle ou YamlLd
   2) générer le fichier eurovoc.yam structuré selon le schéma yamlskosv2

  Dans la génération du fichier eurovoc.yaml

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

if ($option == 'yamlskos') { // génération d'un fichier YamlSkos
  //require_once __DIR__.'/yamlskos.inc.php';
  $prf = [
    'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'skos' => 'http://www.w3.org/2004/02/skos/core#',
  ];
  $yamlSkos = [
    'title' => [
      'fr'=> "EuroVoc, thésaurus multilingue de l'Union européenne",
      'en'=> "EuroVoc, the EU's multilingual thesaurus",
    ],
    'language'=> ['fr','en'],
    'issued'=> '2021-06-04', // la dernière version le jour du téléchargement du RDF
    '$schema'=> 'yamlskosv2',
    'domains'=> [
      /*4 => [
        "prefLabel" => [
          "en" => "04 POLITICS",
          "fr" => "04 VIE POLITIQUE",
        ],
      ],
      8 => [
        "prefLabel" => [
          "en" => "08 INTERNATIONAL RELATIONS",
          "fr" => "08 RELATIONS INTERNATIONALES",
        ],
      ],
      10 => [
        "prefLabel" => [
          "en" => "10 EUROPEAN UNION",
          "fr" => "10 UNION EUROPÉENNE",
        ],
      ],
      12 => [
        "prefLabel" => [
          "fr" => "12 DROIT",
          "en" => "12 LAW",
        ],
      ],
      16 => [
        "prefLabel" => [
          "en" => "16 ECONOMICS",
          "fr" => "16 ÉCONOMIE",
        ],
      ],
      20 => [
        "prefLabel" => [
          "en" => "20 TRADE",
          "fr" => "20 ÉCHANGES ÉCONOMIQUES ET COMMERCIAUX",
        ],
      ],
      24 => [
        "prefLabel" => [
          "en" => "24 FINANCE",
          "fr" => "24 FINANCES",
        ],
      ],
      28 => [
        "prefLabel" => [
          "fr" => "28 QUESTIONS SOCIALES",
          "en" => "28 SOCIAL QUESTIONS",
        ],
      ],
      32 => [
        "prefLabel" => [
          "en" => "32 EDUCATION AND COMMUNICATIONS",
          "fr" => "32 ÉDUCATION ET COMMUNICATION",
        ],
      ],
      36 => [
        "prefLabel" => [
          "en" => "36 SCIENCE",
          "fr" => "36 SCIENCES",
        ],
      ],
      40 => [
        "prefLabel" => [
          "en" => "40 BUSINESS AND COMPETITION",
          "fr" => "40 ENTREPRISE ET CONCURRENCE",
        ],
      ],
      44 => [
        "prefLabel" => [
          "fr" => "44 EMPLOI ET TRAVAIL",
          "en" => "44 EMPLOYMENT AND WORKING CONDITIONS",
        ],
      ],
      48 => [
        "prefLabel" => [
          "en" => "48 TRANSPORT",
          "fr" => "48 TRANSPORTS",
        ],
      ],
      52 => [
        "prefLabel" => [
          "en" => "52 ENVIRONMENT",
          "fr" => "52 ENVIRONNEMENT",
        ],
      ],
      56 => [
        "prefLabel" => [
          "en" => "56 AGRICULTURE, FORESTRY AND FISHERIES",
          "fr" => "56 AGRICULTURE, SYLVICULTURE ET PÊCHE",
        ],
      ],
      60 => [
        "prefLabel" => [
          "en" => "60 AGRI-FOODSTUFFS",
          "fr" => "60 AGRO-ALIMENTAIRE",
        ],
      ],
      64 => [
        "prefLabel" => [
          "fr" => "64 PRODUCTION, TECHNOLOGIE ET RECHERCHE",
          "en" => "64 PRODUCTION, TECHNOLOGY AND RESEARCH",
        ],
      ],
      66 => [
        "prefLabel" => [
          "en" => "66 ENERGY",
          "fr" => "66 ÉNERGIE",
        ],
      ],
      68 => [
        "prefLabel" => [
          "fr" => "68 INDUSTRIE",
          "en" => "68 INDUSTRY",
        ],
      ],
      72 => [
        "prefLabel" => [
          "en" => "72 GEOGRAPHY",
          "fr" => "72 GÉOGRAPHIE",
        ],
        "notation" => [
          72,
        ],
      ],
      76 => [
        "prefLabel" => [
          "en" => "76 INTERNATIONAL ORGANISATIONS",
          "fr" => "76 ORGANISATIONS INTERNATIONALES",
        ],
      ],*/
    ],
    'schemes'=> [],
    'concepts'=> [],
  ];
  foreach ($graph as $uri => $resource) {
    $id = substr($uri, strlen('http://eurovoc.europa.eu/'));
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
        $domain = substr($scheme['prefLabel']['fr'], 0, 2);
        $scheme['domain'] = is_numeric($domain)  ? (int)$domain : $domain;
      }
      $scheme['hasTopConcept'] = []; // ce champ est renseigné à partir des de la lecture des concepts
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
      if ($id <> 'domains') // le scheme <http://eurovoc.europa.eu/domains> des domaines n'est pas considéré comme un scheme
        $yamlSkos['schemes'][$id] = $scheme;
    }
    elseif ($resource["$prf[rdf]type"][0]['value'] == "$prf[skos]Concept") { // construction des concepts
      //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      unset($resource["$prf[rdf]type"]);
      $concept = [];
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
          if (is_numeric($topConceptOf))
            $topConceptOf = (int)$topConceptOf;
          $concept['topConceptOf'][] = $topConceptOf;
          $yamlSkos['schemes'][$topConceptOf]['hasTopConcept'][] = $id;
        }
        unset($resource["$prf[skos]topConceptOf"]);
      }
      // Le champ pour lequel il ne peut y avoir qu'un littéral par langue
      if (isset($resource["$prf[skos]prefLabel"])) {
        foreach ($resource["$prf[skos]prefLabel"] as $v)
          $concept['prefLabel'][$v['lang']] = $v['value'];
        unset($resource["$prf[skos]prefLabel"]);
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
      if ($resource) {
        //echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
        echo Yaml::dump(['error'=> [$id => $resource]], 4, 2);
        die();
      }
      if ($concept['inScheme'][0] == 'domains') { // Les concepts dans le scheme domains sont des domaines et pas des concepts
        //echo Yaml::dump(['concepts'=> [$id => $concept]], 4, 2);
        $yamlSkos['domains'][$id] = [
          'prefLabel'=> $concept['prefLabel'],
          'notation'=> $concept['notation'],
        ];
      }
      else
        $yamlSkos['concepts'][$id] = $concept;
    }
    else {
      echo Yaml::dump(['source'=> [$id => $resource]], 4, 2);
      die();
    }
  }
  echo Yaml::dump($yamlSkos, 5, 2);
}
