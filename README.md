## EuroVoc

L'objectif de ce projet est de faciliter la visualisation, la compréhension et l'utilisation 
d'[EuroVoc](https://op.europa.eu/fr/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/eurovoc).  
*1er sujet:* son téléchargement et sa structuration pour différentes exploitations,  
*2ème sujet:* visualisation et recherche d'un concept.

Pour stocker EuroVoc, utilisation d'une structure simplifiée en Yaml, appelée YamlSkosV2, définie
par [un schéma JSON](yamlskosv2.schema.yaml).
Le fichier [eurovoc.yaml](eurovoc.yaml) contient EuroVoc et respecte ce schéma.

index.php permet de visualiser le contenu de cette structure avec, notamment, un affichage assez rapide, en une page Html,
de tous les labels, notamment pour en rechercher un. Il utilise une structuration Php définie dans yamlskos.inc.php.

EuroVoc évolue et il faut pouvoir prendre en compte ces évolutions.  
Dans les différents fichiers téléchargeables sur la [page EuroVoc](https://op.europa.eu/fr/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/eurovoc),
[eurovoc_in_skos_core_concepts.zip](https://op.europa.eu/o/opportal-service/euvoc-download-handler?cellarURI=http%3A%2F%2Fpublications.europa.eu%2Fresource%2Fcellar%2Fb868cf85-c47b-11eb-a925-01aa75ed71a1.0001.04%2FDOC_1&fileName=eurovoc_in_skos_core_concepts.zip) semble le plus
utilisable principalement car il est suffisament petit (53 Mo) pour pouvoir être analysé par EasyRdf, ce qui permet de:

  - le convertir en Turtle pour visualiser plus facilement son contenu,
  - le sérialiser en Php pour l'utiliser en Php,
  - supprimer les langues autres que 'fr' et 'en' pour l'alléger

skosrdf.php permet de consulter ce fichier RDF et de le convertir en un fichier YamlSkosV2.

Le module utilise:

  - EasyRdf pour exploiter le format RDF/XML et
  - symfony/yaml pour lire et écrire les fichiers Yaml.

