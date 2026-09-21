# Photos produit depuis Open Beauty Facts

Le catalogue importé depuis le logiciel de caisse arrive sans photos. Cette
fonctionnalité en cherche sur [Open Beauty Facts](https://world.openbeautyfacts.org),
base de données publique et collaborative de produits cosmétiques.

**Rien n'est publié automatiquement.** La commande ne produit que des
propositions ; une photo n'arrive sur une fiche produit qu'après validation
humaine dans le back-office.

## Licence et attribution

| Élément | Licence |
| --- | --- |
| Données (noms, marques, contenances) | [Open Database License](https://opendatacommons.org/licenses/odbl/1.0/) + [Database Contents License](https://opendatacommons.org/licenses/dbcl/1.0/) |
| **Photos** | [Creative Commons Attribution – Partage dans les mêmes conditions 3.0](https://creativecommons.org/licenses/by-sa/3.0/deed.fr) |

La licence des photos impose de citer l'auteur et la licence. Le texte
d'attribution est donc :

1. construit au moment de la découverte (`Photo : <contributeur> — Open Beauty
   Facts (CC-BY-SA-3.0)`) et figé en base, pour rester valable même si la
   fiche distante change ensuite ;
2. montré au validateur avant qu'il ne publie ;
3. recopié sur le `ProductImage` à la validation ;
4. affiché sous la galerie de la fiche produit, avec un lien vers la fiche
   source et vers le texte de la licence.

Open Beauty Facts prévient par ailleurs que les photos « peuvent contenir des
éléments graphiques soumis au droit d'auteur ». C'est l'une des raisons pour
lesquelles la validation humaine est obligatoire : le validateur voit la photo
avant qu'elle ne soit publiée.

## Lancer la recherche

```bash
# Essai : 20 produits, sans rien télécharger ni écrire
php artisan products:source-images --limit=20 --dry-run

# Un lot réel de 20 produits
php artisan products:source-images --limit=20

# Le lot suivant
php artisan products:source-images --limit=20 --offset=20

# Une seule marque
php artisan products:source-images --brand=Nivea --limit=0

# Tout le catalogue (compter environ une heure : voir « Débit » plus bas)
php artisan products:source-images --limit=0
```

| Option | Rôle |
| --- | --- |
| `--limit=N` | Nombre de produits traités. `0` = tous. Défaut : 20. |
| `--offset=N` | Produits à passer. Un produit sans correspondance ne laisse aucune trace en base : sans `--offset`, relancer avec `--limit` retomberait sur les mêmes. |
| `--dry-run` | Ne télécharge rien, n'écrit rien. Sert à vérifier ce que la commande trouverait. |
| `--brand=` | Restreint à une marque (nom ou identifiant). |
| `--min-confidence=` | Score minimal retenu. Défaut : `config('image_sourcing.matching.min_confidence')`, soit 55. |
| `--csv=` | Chemin du rapport. Défaut : `storage/app/rapports/produits-sans-photo-<date>.csv`. |

La commande ne traite **que les produits sans aucune photo**, et laisse
intactes les propositions déjà en attente : elle peut être relancée sans
risque de doublon ni d'écrasement.

## Comment une photo est trouvée

1. **Par code-barres**, si le produit en a un *vrai*. Attention : le champ
   `barcode` du catalogue contient surtout des références internes du logiciel
   de caisse (neuf chiffres, préfixées de zéros). La commande valide donc la
   longueur et la clé de contrôle GS1 avant de chercher — sinon elle gaspille
   du quota d'API et risque d'apparier un produit au hasard.
2. **Par marque + nom**, sinon. Les libellés de caisse sont illisibles pour un
   moteur de recherche (`URIAGE HYSEAC 3-REGUL CRM/40ML (S)`), donc ils sont
   d'abord nettoyés : contenance extraite, conditionnement retiré,
   abréviations développées (`CRM` → crème, `DEMAQ` → démaquillant), fautes de
   frappe connues du catalogue corrigées. Les requêtes sont ensuite essayées
   de la plus précise à la plus large, car Open Beauty Facts exige que *tous*
   les mots correspondent et ne renvoie rien si la requête est trop longue.

## Le score de confiance

Une note de 0 à 100, qui **ne décide de rien** : elle sert à trier ce qui est
présenté au validateur et à écarter le bruit évident.

| Signal | Poids | Rôle |
| --- | --- | --- |
| Nom | 0,45 | Signal principal, mais bruité (libellés tronqués, fautes). |
| Marque | 0,35 | Très discriminant. |
| Contenance | 0,20 | Départage deux formats d'un même produit. Neutre si absente d'un côté. |

Deux garde-fous, tous deux issus de ce que le premier lot réel a produit :

- **Aucun mot en commun ⇒ score 0.** Les fiches d'Open Beauty Facts sont
  souvent nommées d'après leur seule marque (`Signal`, `Pierre Fabre`,
  `betadine`). La bonne marque et la bonne contenance suffisaient alors à
  franchir le seuil sans qu'un seul mot ne relie la fiche au produit — c'est
  ainsi qu'un dentifrice Signal adulte a été proposé pour un *Signal Kids*.
  Marque et contenance ne disent que « un produit de cette marque dans ce
  format », ce qui décrit souvent une dizaine d'articles.
- **Deux marques connues et différentes plafonnent le score à 45**, donc sous
  le seuil. Le catalogue vend des parfums génériques nommés d'après ceux
  qu'ils imitent (`IAP EDP Nø32 OLYMPEA`) ; sans ce plafond, ils
  décrocheraient la photo du parfum original.

## Traitement des images

800 px de large, WebP, moins de 80 Ko. La qualité est dégradée par paliers
(82 → 50) jusqu'à tenir dans le budget ; une image qui n'y tient pas est
abandonnée plutôt que publiée. Le budget prime sur la qualité : la boutique
est consultée en 3G.

Sont également écartées les images de moins de 200 px de côté et celles dont
les proportions dépassent 3:1 : les contributeurs photographient parfois la
tranche d'une boîte ou un bandeau d'étiquette, inutilisables en vignette.

## Valider

Back-office → **Photos à valider**. Chaque ligne met côte à côte le produit du
catalogue (nom, marque, référence) et la photo trouvée (aperçu, fiche source,
contenance), avec le score et l'attribution.

- **Valider** publie la photo, avec son attribution. C'est la seule action qui
  crée une image. Elle est transactionnelle et idempotente, et clôt les autres
  propositions en attente pour le même produit.
- **Rejeter** enregistre la décision et supprime le fichier téléchargé. L'URL
  d'origine reste en base : **la commande ne reproposera plus cette photo pour
  ce produit**.

Une proposition issue d'un `--dry-run` n'a pas de fichier et ne peut pas être
validée.

## Produits sans correspondance

Le CSV produit en fin de course liste les produits restés sans photo, groupés
par marque, la marque la plus coûteuse en premier — c'est la liste des séances
photo à organiser en boutique. La colonne `raison` distingue notamment :

| Raison | Signification |
| --- | --- |
| `aucun_resultat` | La source ne connaît pas ce produit. Photo à prendre en boutique. |
| `score_insuffisant` | Des résultats, mais aucun assez convaincant. |
| `resultat_sans_photo` | La fiche existe, sans photo. |
| `image_inutilisable` | Photo illisible, minuscule, ou trop allongée pour être une photo de produit. |
| `source_indisponible` | **La source n'a pas répondu.** Rien à conclure : relancer la commande. |
| `nom_inexploitable` | Le libellé ne laisse aucun mot cherchable. |

La distinction entre `aucun_resultat` et `source_indisponible` est importante :
seul le second se rejoue.

## Débit

Open Beauty Facts publie ses limites : 15 lectures/minute et 10
recherches/minute par adresse IP. La commande se cale en dessous (12 et 8) en
espaçant ses appels. Compter **environ 8 secondes par produit**, davantage
quand plusieurs requêtes sont nécessaires. Le serveur public est de surcroît
régulièrement lent ; les délais d'attente sont généreux et les échecs sont
comptés à part plutôt que confondus avec des absences de correspondance.

## Configuration

`config/image_sourcing.php`. Variables d'environnement utiles :

```dotenv
OBF_CONTACT_EMAIL=contact@thiaala.sn   # identifie l'application auprès de la source
OBF_MIN_CONFIDENCE=55
OBF_RATE_SEARCH=8
OBF_RATE_PRODUCT=12
```

Le `User-Agent` doit identifier l'application et donner un contact : une
requête anonyme est traitée comme un robot et finit bloquée.
