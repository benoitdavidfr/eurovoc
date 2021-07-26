<?php
/*PhpDoc
name: showyaml.inc.php
title: showyaml.inc.php - affiche un document Yaml sous forme de tables Html
doc: |
journal: |
  2021-07-26:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// test si un array est un tableau associatif ou une liste,  [] n'est pas un assoc_array
if (!function_exists('is_assoc_array')) {
  function is_assoc_array(array $array): bool { return count(array_diff_key($array, array_keys(array_keys($array)))); }
}
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de is_assoc_array 
  if (isset($_GET['test']) && ($_GET['test']=='is_assoc_array')) {
    echo "Test is_assoc_array<br>\n";
    foreach ([[], [1, 2, 3], ['a'=>'a','b'=>'b']] as $array) {
      echo json_encode($array), (is_assoc_array($array) ? ' is_assoc_array' : ' is NOT assoc_array') , "<br>\n";
    }
    echo "FIN test is_assoc_array<br><br>\n";
  }
  $unitaryTests[] = 'is_assoc_array';
}

// le par. est-il une liste ? cad un array dont les clés sont la liste des n-1 premiers entiers positifs, [] est une liste
function is_list($list): bool { return is_array($list) && !is_assoc_array($list); }

// Affiche le Yaml avec des tables Html imbriquées
function showYaml($yaml): void {
  if (!is_array($yaml)) { // représentation par une table Html
    echo $yaml; return;
  }
  echo "<table border=1>";
  if (is_list($yaml)) {
    foreach ($yaml as $val) {
      echo "<tr><td>"; showYaml($val); echo "</td></tr>";
    }
  }
  else {
    foreach ($yaml as $key => $val) {
      echo "<tr><td>$key</td><td>"; showYaml($val); echo "</td></tr>";
    }
  }
  echo "</table>";
}


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return;


$yaml = file_get_contents('eurovoc.yaml');
$yaml = Yaml::parse($yaml);
showYaml($yaml);
