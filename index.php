<?php
/*PhpDoc:
name:  index.php
title: index.php - affichage d'EuroVoc lu dans un YamlSkos
doc: |
  Affichage par défaut de l'ensemble des domaines, sous-domaines (= Scheme) et concepts.
  Possibilité de cliquer sur un concept pour obtenir un affichage global.
journal: |
  22/7/2021:
    première version
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/yamlskos.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

echo "<html><head><meta charset='UTF-8'><title>eurovoc</title></head><body>\n";
//echo '<pre>';

$lang = $_GET['lang'] ?? 'fr';
$options = isset($_GET['options']) ? explode(',', $_GET['options']) : [];

if (is_file(__DIR__.'/eurovoc.pser')) {
  YamlSkos::init(unserialize(file_get_contents(__DIR__.'/eurovoc.pser')), $options);
}
else {
  $yaml = Yaml::parseFile(__DIR__.'/eurovoc.yaml');
  YamlSkos::init($yaml, $options);
  file_put_contents(__DIR__.'/eurovoc.pser', serialize($yaml));
}

if (isset($_GET['scheme'])) {
  echo "<a href='?lang=$lang'>Revenir à l'ensemble du thésaurus</a><br>\n";
  YamlSkos::$schemes[$_GET['scheme']]->show($lang, $options);
}
elseif (isset($_GET['concept']))
  YamlSkos::$concepts[$_GET['concept']]->showFull($lang);
else
  YamlSkos::show($lang, $options);

