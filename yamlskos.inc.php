<?php
/*PhpDoc:
name:  yamlskos.inc.php
title: yamlskos.inc.php - chargement et affichage d'un YamlSkos
doc: |
  Structuration Php d'un thésaurus comme EuroVoc disponible en YamlSkos.
  Le thésaurus peut être affiché dans différentes langues ; le paramètre GET définit la langue choisie.
  2 options sont définies et sont passées dans le paramètre GET options comme liste de chaines:
    - 'toc' permet de n'afficher que la table des matières, cad les labels des domaines et des schemes
    - 'select' permet de restreindre l'affichage àa la liste des domaines d'intérêt définie en constante
journal: |
  26/7/2021:
    - ajout champ eurovocId aux domaines
    - lien avec les URI
  24-25/7/2021:
    - première version
*/
require_once __DIR__.'/showyaml.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class YamlSkos { // classe statique permettant de créer la structure à partir du contenu du fichier YamlSkos et de l'afficher
  // liste des langues disponibles
  const LANGS = [
    'fr'=> "français",
    'en'=> "anglais",
  ];
  // liste de certains domaines d'intérêt qui peuvent être sélectionnés avec l'option select
  const DOMAINS_OF_INTEREST = [
    '48 TRANSPORTS',
    '52 ENVIRONNEMENT',
    '56 AGRICULTURE, SYLVICULTURE ET PÊCHE',
    '60 AGRO-ALIMENTAIRE',
    '64 PRODUCTION, TECHNOLOGIE ET RECHERCHE',
    '66 ÉNERGIE',
  ];
  static public array $titles; // Titre du thésaurus multi-langue [lang => title]
  static public string $issued; // date de la version d'EuroVoc ou ''
  static public array $domains; // domaines sous la forme d'un dict. [id => Domain]
  static public array $schemes; // schemes sous la forme d'un dict. [id => Scheme]
  static public array $concepts; // concepts sous la forme d'un dict. [id => Concept]
  
  static function init(array $yaml, array $options): void { // création à partir du contenu du fichier YamlSkos
    self::$titles = $yaml['title'];
    self::$issued = $yaml['issued'] ?? '';
    //$this->hasTopConcept = $yaml['domainScheme']['hasTopConcept'];
    foreach ($yaml['domains'] as $did => $domain) {
      if (isset(self::$domains[$did]))
        throw new Exception("Erreur d'écrasement du domaine $did");
      self::$domains[$did] = new Domain($did, $domain);
    }
    foreach ($yaml['schemes'] as $sid => $scheme) {
      if (isset(self::$schemes[$sid]))
        throw new Exception("Erreur d'écrasement du schemes $sid");
      self::$schemes[$sid] = new Scheme($sid, $scheme);
    }
    foreach ($yaml['concepts'] as $cid => $concept) {
      if (isset(self::$concepts[$cid]))
        throw new Exception("Erreur d'écrasement du concept $cid");
      self::$concepts[$cid] = new Concept($cid, $concept);
    }
  }
  
  static function show(string $lang, array $options): void { // affichage d'un menu langue et options puis de la structure
    // Menu d'affichage des autres langues
    foreach (self::LANGS as $l => $label) {
      if ($l <> $lang)
        echo "<a href='?lang=$l&amp;options=",implode(',',$options),"'>Afficher en $label</a><br>\n";
    }
    $toc = in_array('toc', $options) ? 'toc' : '';
    $select = in_array('select', $options) ? 'select' : '';
    // Menu affichage contenu/toc
    if ($toc)
      echo "<a href='?lang=$lang&amp;options=$select'>Afficher les concepts</a><br>\n";
    else
      echo "<a href='?lang=$lang&amp;options=toc",($select ? ",$select" : ''),"'>Afficher la liste des micro-thésaurus</a><br>\n";
    if ($select)
      echo "<a href='?lang=$lang&amp;options=$toc'>Afficher tous les domaines</a><br>\n";
    else
      echo "<a href='?lang=$lang&amp;options=select",($toc ? ",$toc" : ''),"'>Afficher uniquement les domaines d'intérêt</a><br>\n";
    echo "<a href='?lang=$lang&amp;action=terms'>Afficher les étiquettes préférentielles et synonymes par ordre alphabétique</a><br>\n";

    echo "<h1><a href='http://publications.europa.eu/resource/dataset/eurovoc'>",self::$titles[$lang],"</a></h1>\n";
    echo "version: ",self::$issued,"</p>\n";
    foreach (self::$domains as $did => $domain) {
      $domain->show($lang, $options);
    }
  }

  // renvoie une chaine triable par sort
  static function sorting(string $label): string {
    $mapping = [
      'â'=> 'a',
      'Å'=> 'a',
      'é'=> 'e',
      'É'=> 'e',
      'î'=> 'i',
      'Î'=> 'i',
      'œ'=> 'oe',
      'Ö'=> 'o',
    ];
    $label = strtolower($label);
    $label = str_replace(array_keys($mapping), array_values($mapping), $label);
    return $label;
  }
  
  static function showTerms(string $lang, array $options): void {
    $terms = []; // [cle => ['label'=> label, 'concepts'=> [id => prefLabel]]]
    foreach (self::$concepts as $id => $concept) {
      $prefLabel = $concept->prefLabel($lang);
      $terms[self::sorting($prefLabel)] = ['label'=> $prefLabel, 'concepts'=> [$id => $prefLabel]];
      foreach ($concept->altLabels($lang) as $term) {
        $key = self::sorting($term);
        if (isset($terms[$key])) {
          //print_r($terms[$key]);
          $terms[$key]['concepts'][$id] = $prefLabel;
        }
        else
          $terms[$key] = ['label'=> $term, 'concepts'=> [$id => $prefLabel]];
      }
    }
    ksort($terms);
    foreach ($terms as $term) {
      if (count($term['concepts'])==1)
        echo "<a href='?concept=",array_keys($term['concepts'])[0],"'>$term[label]</a><br>\n";
      else {
        //echo "<pre>"; print_r($term); echo "</pre>\n";
        echo "$term[label] -&gt; ";
        foreach ($term['concepts'] as $id => $prefLabel) {
          echo "<a href='?concept=$id'>$prefLabel</a> ";
        }
        echo "<br>\n";
      }
    }
  }
};

class Domain { // 1er niveau de la structuration, il y a 21 domaines
  protected string $id; // identifiant
  protected array $prefLabels; // dict. de prefLabel indexé par la langue
  protected int $eurovocId;
  public array $schemes=[]; // Liste des schemes appartenant au domaine
  
  function __construct(string $did, array $yaml) {
    $this->id = $did;
    $this->prefLabels = $yaml['prefLabel'];
    $this->eurovocId = $yaml['eurovocId'];
  }
  
  // 3 cas d'utilisation
  // 1) affichage à partir du niveau domain <=> in_array('domain', $options)
  // 2) affichage toc <=> in_array('toc', $options)
  // 3) affichage comme partie d'un tout plus grand
  function show(string $lang, array $options) {
    //print_r($this);
    if (in_array('domain', $options)) {
      echo "<a href='?lang=$lang'>Remonter au thésaurus</a><br>\n";
      echo "<h2><a href='http://eurovoc.europa.eu/$this->eurovocId'>",$this->prefLabels[$lang],"</a></h2>\n";
    }
    elseif (in_array('toc', $options))
      echo "<b>",$this->prefLabels[$lang],"</b><br>\n";
    else {
      echo "<h2><a href='?lang=$lang&amp;domain=$this->id'>",$this->prefLabels[$lang],"</a></h2>\n";
    }
    if (in_array('select', $options) && !in_array($this->prefLabels['fr'], YamlSkos::DOMAINS_OF_INTEREST)) {
      //echo "<h2>NOT ",$this->prefLabels[$lang],"</h2>\n";
      return;
    }
    foreach ($this->schemes as $scheme) {
      $scheme->show($lang, $options);
    }
  }
};

class Scheme { // 2ème niveau de la structuration, contient les concepts
  protected string $id; // identifiant
  protected string $domain; // l'id du domaine d'appartenance ou ''
  protected array $prefLabels; // dict. de prefLabel indexé par la langue
  protected array $hasTopConcept; // tableau d'id des TopConcepts
  
  function __construct(string $id, array $yaml) {
    //echo "<pre>Scheme:"; print_r($yaml); echo "</pre>\n";
    if (0 && isset($yaml['domain']) && (count($yaml['domain']) > 1)) {
      echo "<pre>Scheme:"; print_r($yaml); echo "</pre>\n";
    }
    if (0 && !isset($yaml['domain'])) {
      echo "<pre>Scheme:"; print_r($yaml); echo "</pre>\n";
    }
    $this->id = $id;
    $this->domain = $yaml['domain'] ?? '';
    $this->prefLabels = $yaml['prefLabel'] ?? ['fr'=> "AUCUN PREFLABEL", 'en'=> "NO PREFLABEL"];
    $this->hasTopConcept = $yaml['hasTopConcept'] ?? [];
    if ($this->domain)
      YamlSkos::$domains[$this->domain]->schemes[] = $this;
  }
  
  // retourne l'étiquette dans la langue avec possibilité de naviguer sur le concept
  function label(string $lang): string {
    return "<a href='?lang=$lang&amp;scheme=$this->id'>".$this->prefLabels[$lang]."</a>";
  }

  // 3 cas d'utilisation
  // 1) affichage à partir du niveau scheme <=> in_array('scheme', $options)
  // 2) affichage toc <=> in_array('toc', $options)
  // 3) affichage comme partie d'un tout plus grand
  function show(string $lang, array $options) {
    if (in_array('scheme', $options)) {
      // Affichage du lien vers le domaine ou à défaut vers le thésaurus
      if ($this->domain)
        echo "<a href='?lang=$lang&amp;domain=",$this->domain,"'>Remonter au domaine</a><br>\n";
      else
        echo "<a href='?lang=$lang'>Remonter au thésaurus</a><br>\n";
      echo "<h3><a href='http://eurovoc.europa.eu/$this->id'>",$this->prefLabels[$lang],"</a></h3>\n";
    }
    elseif (in_array('toc', $options)) {
      echo "&nbsp;&nbsp;<a href='?lang=$lang&amp;scheme=$this->id'>",$this->prefLabels[$lang],"</a><br>\n";
      return;
    }
    else {
      echo "<h3><a href='?lang=$lang&amp;scheme=$this->id'>",$this->prefLabels[$lang],"</a></h3>\n";
    }
    echo "<ul>\n";
    foreach ($this->hasTopConcept as $cId) {
      YamlSkos::$concepts[$cId]->showLabels($lang);
    }
    echo "</ul>\n";
    //echo "<pre>\n"; print_r($this);echo "</pre>\n";
  }
};

class Concept {
  protected string $id; // identifiant
  protected array $yaml; // structure SkosYaml
  
  function __construct(string $id, array $yaml) {
    $this->id = $id;
    unset($yaml['notation']);
    $this->yaml = $yaml;
  }
  
  function prefLabel(string $lang): string { return $this->yaml['prefLabel'][$lang]; }
  
  function altLabels(string $lang): array { return $this->yaml['altLabel'][$lang] ?? []; }
  
  // retourne l'étiquette dans la langue avec possibilité de naviguer sur le concept
  function prefLabelWithLink(string $lang): string {
    return "<a href='?lang=$lang&amp;concept=$this->id'>".$this->yaml['prefLabel'][$lang]."</a>";
  }
  
  // affiche une arborescence HTML des labels dans la langue avec possibilité de naviguer sur le concept
  function showLabels(string $lang): void {
    echo "<li>",$this->prefLabelWithLink($lang),"</li>\n";
    //print_r($this);
    if (isset($this->yaml['narrower'])) {
      echo "<ul>\n";
      foreach ($this->yaml['narrower'] as $narrower) {
        if (!isset(YamlSkos::$concepts[$narrower]))
          echo "<li>NARROWER $narrower incorrect</li>\n";
        else
          YamlSkos::$concepts[$narrower]->showLabels($lang);
      }
      echo "</ul>\n";
    }
  }
  
  // afffiche le concept complet
  function showFull(string $lang) {
    echo "<h2><a href='http://eurovoc.europa.eu/$this->id'>",$this->yaml['prefLabel'][$lang],"</a></h2>\n";
    $yaml = $this->yaml;
    foreach ($yaml['inScheme'] as $i => $scheme)
      $yaml['inScheme'][$i] = YamlSkos::$schemes[$scheme]->label($lang)."\n";
    foreach (['broader','narrower','related'] as $link) {
      if (isset($yaml[$link])) {
        foreach ($yaml[$link] as $i => $id)
          $yaml[$link][$i] = YamlSkos::$concepts[$id]->prefLabelWithLink($lang)."\n";
      }
    }
    showYaml($yaml);
    
    //echo "\n"; print_r($this); echo "</pre>\n";
  }
};
