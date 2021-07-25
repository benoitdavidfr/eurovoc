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
  24/7/2021:
    première version
*/
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
  //protected array $hasTopConcept; // tableau d'id de Domain
  static public array $domains; // domaines sous la forme d'un dict. [id => Domain]
  static public array $schemes; // schemes sous la forme d'un dict. [id => Scheme]
  static public array $concepts; // concepts sous la forme d'un dict. [id => Concept]
  
  static function init(array $yaml, array $options): void { // création à partir du contenu du fichier YamlSkos
    self::$titles = $yaml['title'];
    //$this->hasTopConcept = $yaml['domainScheme']['hasTopConcept'];
    foreach ($yaml['domains'] as $did => $domain) {
      self::$domains[$did] = new Domain($did, $domain);
    }
    foreach ($yaml['schemes'] as $sid => $scheme) {
      self::$schemes[$sid] = new Scheme($sid, $scheme);
    }
    foreach ($yaml['concepts'] as $cid => $concept) {
      self::$concepts[$cid] = new Concept($cid, $concept);
    }
  }
  
  static function show(string $lang, array $options) { // affichage d'un menu langue et options puis de la structure
    echo "<h1>",self::$titles[$lang],"</h1>\n";
    // affichage autre langue
    foreach (self::LANGS as $l => $label) {
      if ($l <> $lang)
        echo "<a href='?lang=$l&amp;options=",implode(',',$options),"'>Affichage en $label</a><br>\n";
    }
    $toc = in_array('toc', $options) ? 'toc' : '';
    $select = in_array('select', $options) ? 'select' : '';
    // affichage contenu/toc
    if ($toc)
      echo "<a href='?lang=$lang&amp;options=$select'>Affichage du contenu</a><br>\n";
    else
      echo "<a href='?lang=$lang&amp;options=toc",($select ? ",$select" : ''),"'>Affichage uniquement des étiquettes des domaines et micro-thésaurus</a><br>\n";
    if ($select)
      echo "<a href='?lang=$lang&amp;options=$toc'>Affichage de tous les domaines</a><br>\n";
    else
      echo "<a href='?lang=$lang&amp;options=select",($toc ? ",$toc" : ''),"'>Affichage uniquement des domaines d'intérêt</a><br>\n";
    foreach (self::$domains as $did => $domain) {
      $domain->show($lang, $options);
    }
  }
};

class Domain { // 1er niveau de la structuration, il y a 21 domaines
  protected array $prefLabels; // dict. de prefLabel indexé par la langue
  public array $schemes=[]; // Liste des schemes appartenant au domaine
  
  function __construct(string $did, array $yaml) {
    $this->prefLabels = $yaml['prefLabel'];
  }
  
  function show(string $lang, array $options) {
    //print_r($this);
    if (in_array('select', $options) && !in_array($this->prefLabels['fr'], YamlSkos::DOMAINS_OF_INTEREST)) {
      //echo "<h2>NOT ",$this->prefLabels[$lang],"</h2>\n";
      return;
    }
    echo "<h2>",$this->prefLabels[$lang],"</h2>\n";
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

  function show(string $lang, array $options) {
    echo "<h3><a href='?lang=$lang&amp;scheme=$this->id'>",$this->prefLabels[$lang],"</a></h3>\n";
    if (in_array('toc', $options)) return;
    echo "<ul>\n";
    foreach ($this->hasTopConcept as $cId) {
      YamlSkos::$concepts[$cId]->showLabels($lang);
    }
    echo "</ul>\n";
  }
};

class Concept {
  protected string $id; // identifiant
  protected array $inSchemes; // liste d'identifiant de schemes
  protected array $prefLabels; // dict. de prefLabel indexé par la langue
  protected array $altLabels; // dict. de listes de altLabels indexé par la langue
  protected array $scopeNotes; // dict. de listes de scopeNotes indexé par la langue
  protected array $historyNotes; // dict. de listes de historyNotes indexé par la langue
  protected array $broaders; // liste des id des concepts plus généraux
  protected array $narrowers; // liste des id des concepts plus étroits
  protected array $related; // liste des id des concepts en relation
  protected array $yaml; // structure SkosYaml initiale pour mise au point
  
  function __construct(string $id, array $yaml) {
    if ($id == 0) {
      echo "<pre>Concept $id:"; print_r($yaml);
    }
    $this->id = $id;
    $this->inSchemes = $yaml['inScheme'];
    $this->prefLabels = $yaml['prefLabel'];
    $this->altLabels = $yaml['altLabel'] ?? [];
    $this->scopeNotes = $yaml['scopeNote'] ?? [];
    $this->historyNotes = $yaml['historyNote'] ?? [];
    $this->narrowers = $yaml['narrower'] ?? [];
    $this->broaders = $yaml['broader'] ?? [];
    $this->related = $yaml['related'] ?? [];
    $this->yaml = $yaml;
  }
  
  // retourne l'étiquette dans la langue avec possibilité de naviguer sur le concept
  function prefLabel(string $lang): string {
    return "<a href='?lang=$lang&amp;concept=$this->id'>".$this->prefLabels[$lang]."</a>";
  }
  
  // affiche une arborescence HTML des labels dans la langue avec possibilité de naviguer sur le concept
  function showLabels(string $lang): void {
    echo "<li>",$this->prefLabel($lang),"</li>\n";
    //print_r($this);
    if ($this->narrowers) {
      echo "<ul>\n";
      foreach ($this->narrowers as $narrower) {
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
    echo "<h2>",$this->prefLabels[$lang],"</h2><pre>\n";
    echo "inScheme:\n";
    foreach ($this->inSchemes as $scheme)
      echo '  - ',YamlSkos::$schemes[$scheme]->label($lang),"\n";
    echo Yaml::dump(['prefLabel'=> $this->prefLabels], 3, 2);
    if ($this->altLabels)
      echo Yaml::dump(['altLabel'=> $this->altLabels], 3, 2);
    if ($this->scopeNotes)
      echo Yaml::dump(['scopeNote'=> $this->scopeNotes], 3, 2);
    if ($this->historyNotes)
      echo Yaml::dump(['historyNote'=> $this->historyNotes], 3, 2);
    if ($this->broaders) {
      echo "boader:\n";
      foreach ($this->broaders as $broader)
        echo '  - ',YamlSkos::$concepts[$broader]->prefLabel($lang),"\n";
    }
    if ($this->narrowers) {
      echo "narrower:\n";
      foreach ($this->narrowers as $narrower)
        echo '  - ',YamlSkos::$concepts[$narrower]->prefLabel($lang),"\n";
    }
    if ($this->related) {
      echo "retated:\n";
      foreach ($this->related as $related)
        echo '  - ',YamlSkos::$concepts[$related]->prefLabel($lang),"\n";
    }
    echo "\n"; print_r($this);
    echo "</pre>\n";
  }
};
