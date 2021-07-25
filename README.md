## EuroVoc

L'objectif de ce projet est de faciliter la visualisation, la compréhension et l'utilisation d'EuroVoc.  
1er sujet: son téléchargement et sa structuration pour différentes exploitations,  
2ème sujet: visualisation et recherche d'un concept.

Pour stocker EuroVoc, j'utilise une structure simplifiée en Yaml, que j'appelle YamlSkosV2, définie par un schéma JSON.  
eurovoc.yaml respecte ce schéma.

index.php permet de visualiser le contenu de cette structure avec, notamment, un affichage assez rapide, en une page Html,
de tous les labels, notamment pour en rechercher un. Il utilise une structuration Php définie dans yamlskos.inc.php.

EuroVoc évolue et il faut pouvoir prendre en compte ces évolutions.  
Dans les différents fichiers téléchargeables sur la page EuroVoc, eurovoc_in_skos_core_concepts.zip semble le plus
utilisable principalement car il est suffisament petit (53 Mo) pour pouvoir être analysé par EasyRdf, ce qui permet de:

  - le convertir en Turtle pour visualiser plus facilement son contenu,
  - le sérialiser en Php pour l'utiliser en Php,
  - supprimer les langues autres que 'fr' et 'en' pour l'alléger

skosrdf.php permet de consulter ce fichier RDF et de le convertir en un fichier YamlSkosV2.

Le module utilise:

  - EasyRdf pour exploiter le format RDF/XML et
  - symfony/yaml pour lire et écrire les fichiers Yaml.

