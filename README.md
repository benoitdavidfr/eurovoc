## Utilisation d'EuroVoc, thésaurus multilingue de l'Union européenne

L'objectif de ce projet est de faciliter la visualisation, la compréhension et l'utilisation 
d'[EuroVoc, le thésaurus multilingue de l'Union 
européenne](https://op.europa.eu/fr/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/eurovoc),
notamment pour indexer les jeux de données du guichet d'accès à la donnée de la transition écologique et des territoires.

Pour cela le thésaurus a été téléchargé depuis la 
[page EuroVoc
](https://op.europa.eu/fr/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/eurovoc).
Dans les différents fichiers téléchargeables, le fichier
[eurovoc_in_skos_core_concepts.zip](https://op.europa.eu/o/opportal-service/euvoc-download-handler?cellarURI=http%3A%2F%2Fpublications.europa.eu%2Fresource%2Fcellar%2Fb868cf85-c47b-11eb-a925-01aa75ed71a1.0001.04%2FDOC_1&fileName=eurovoc_in_skos_core_concepts.zip)
semble le plus utilisable principalement en raison de sa taille limitée (53 Mo)
pour pouvoir être analysé par EasyRdf.
Cela permet de:
  - le convertir en Turtle pour visualiser plus facilement son contenu,
  - le sérialiser en Php pour l'utiliser en Php,
  - supprimer les langues autres que 'fr' et 'en' pour l'alléger

Une fois téléchargé, le thésaurus a été  transformé, en utilisant le script [skosrdf.php](skosrdf.php),
dans une structuration, appelée YamlSkosV2, définie par [un schéma JSON](yamlskosv2.schema.yaml).
Le résultat est stocké dans le fichier [eurovoc.yaml](eurovoc.yaml).
 
EuroVoc peut ainsi être visualisé sur https://georef.eu/eurovoc/ avec, notamment, un affichage assez rapide,
en une page Html, de tous les labels préférentiels, notamment pour en rechercher un.

Le module utilise:

  - [EasyRdf](https://www.easyrdf.org/) pour exploiter le format RDF/XML et
  - [le composant Yaml de Symfony](https://symfony.com/doc/current/components/yaml.html) 
    pour lire et écrire les fichiers Yaml.

