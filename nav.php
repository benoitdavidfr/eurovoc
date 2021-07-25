<?php
/*PhpDoc:
name:  nav.php
title: nav.php - navigation dans EuroVoc lu dans un YamlSkos de manière à détecter les bugs
doc: |
  Je peux vérifier la génération du fichier eurovoc.yaml en comparant les concepts issus de index.php et de nav.php
journal: |
  25/7/2021:
    - première version
*/

require_once __DIR__.'/vendor/autoload.php';
define ('LANG', 'fr'); // la langue de consultation

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<html><head><meta charset='UTF-8'><title>nav</title></head><body>\n";

$fileName = $_GET['yaml'] ?? 'eurovoc';
if (is_file(__DIR__."/$fileName.pser")) {
  $skos = unserialize(file_get_contents(__DIR__."/$fileName.pser"));
}
else {
  $skos = Yaml::parseFile(__DIR__."/$fileName.yaml");
  file_put_contents(__DIR__."/$fileName.pser", serialize($skos));
}

if (isset($_GET['domain'])) {
  echo "<a href='?'>Revenir à l'ensemble du thésaurus</a><br>\n";
  echo "<pre>"; print_r($skos['domains'][$_GET['domain']]); echo "</pre>\n";
}
elseif (isset($_GET['scheme'])) {
  echo "<a href='?'>Revenir à l'ensemble du thésaurus</a><br>\n";
  echo "<pre>"; print_r($skos['schemes'][$_GET['scheme']]); echo "</pre>\n";
}
elseif (isset($_GET['concept'])) {
  echo "<a href='?'>Revenir à l'ensemble du thésaurus</a><br>\n";
  echo "<pre>"; print_r($skos['concepts'][$_GET['concept']]); echo "</pre>\n";
}
else {
  echo "<h2>Domaines</h2>";
  foreach ($skos['domains'] as $id => $domain) {
    echo "<a href='?domain=$id'>",$domain['prefLabel'][LANG],"</a><br>\n";
  }
  echo "<h2>Schemes</h2>";
  foreach ($skos['schemes'] as $id => $scheme) {
    echo "<a href='?scheme=$id'>",$scheme['prefLabel'][LANG] ?? "NO PREFLABEL","</a><br>\n";
  }
  echo "<h2>Concepts (",count($skos['concepts']),")</h2>";
  foreach ($skos['concepts'] as $id => $concept) {
    echo "<a href='?concept=$id'>",$concept['prefLabel'][LANG] ?? "NO PREFLABEL","</a><br>\n";
  }
}
