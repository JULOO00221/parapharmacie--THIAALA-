# DESIGN.md — Parapharmacie THIAALA

Référence de design pour le frontend Next.js. Maquettes complètes (6 planches) :
https://claude.ai/artifact/CPxmT3u8AKc1xCA73inYeR

Direction : épuré et premium. Beaucoup de blanc, peu de couleurs, pas de dégradés
ni d'ombres lourdes. Palette et typographie tirées du logo.

---

## 1. Tokens

### Couleurs

| Token | Hex | Usage |
|---|---|---|
| `vert` | `#0F5132` | Couleur principale : boutons, bandeaux, pied de page, titres |
| `vert-clair` | `#1D8A4E` | Accents secondaires, états de survol sur fond vert |
| `vert-stock` | `#16693F` | Mention « En stock » uniquement |
| `or` | `#8A5E1C` | Surtitres et accents **sur fond clair** |
| `or-clair` | `#E0B571` | Surtitres et accents **sur fond vert** |
| `ivoire` | `#F7F4EF` | Fond de page |
| `blanc` | `#FFFFFF` | Cartes, en-tête |
| `ivoire-fonce` | `#EDEAE2` | Fond des visuels produits |
| `bordure` | `#E3DCD1` | Bordures de cartes et champs |
| `bordure-forte` | `#CFC6B8` | Bordures de boutons secondaires |
| `encre` | `#14201B` | Texte principal |
| `texte-doux` | `#47544D` | Paragraphes |
| `texte-discret` | `#5F6B64` | Légendes, libellés |

**Ne pas confondre `or` et `or-clair`.** L'or du logo est trop pâle pour du petit
texte sur fond ivoire : il ne passe pas le contraste minimum de 4,5:1. On utilise
`#8A5E1C` sur fond clair et `#E0B571` sur fond vert. C'est la même identité,
adaptée à la lisibilité écran.

### Typographie

- Titres : **Fraunces**, poids 600, `letter-spacing: -0.015em`
- Texte : **Work Sans**, poids 400 / 500 / 600
- Charger via `next/font/google` (pas de `<link>` vers Google Fonts : cela bloque
  le rendu et coûte cher sur les connexions faibles)

Échelle desktop : h1 62px (accueil) / 44px (catalogue) / 42px (produit),
h2 40px, h3 17px, corps 16px, légende 13px.
Échelle mobile : h1 38px / 32px / 30px, h2 28px, corps 15px, légende 12px.

### Formes

- Rayon des cartes : 18px (14px sur mobile) · Grands blocs : 22–26px
- Boutons et champs : `border-radius: 999px`
- Hauteur des boutons : 56px desktop, 52px mobile
- **Toute cible tactile fait au moins 44 × 44px**
- Grille : gouttière 22px desktop, 12px mobile
- Marges de page : 64px desktop, 16–20px mobile

---

## 2. Composants partagés

### En-tête
Logo (SVG détouré, ~236 × 72), navigation, recherche, compte, panier, bouton
WhatsApp vert. Sur mobile : menu burger, logo centré, recherche sur une ligne
dédiée sous l'en-tête.

### Bandeau d'annonce
Fond vert, une ligne : zones de livraison + moyens de paiement.

### Carte produit
Visuel carré sur fond `ivoire-fonce`, puis marque en or majuscules espacées
(11px), nom du produit (16px/600), format, prix en Fraunces, bouton rond « + ».

### Pied de page
Fond vert, quatre colonnes : identité + signature « SANTÉ · BEAUTÉ · BIEN-ÊTRE »
en or clair, Boutique, Aide, Contact.

### Bloc « Conseil du pharmacien »
Fond vert, surtitre en or clair, titre Fraunces blanc, bouton blanc vers
WhatsApp. Présent sur l'accueil et sur chaque fiche produit.

---

## 3. Les trois pages

**Accueil** — bandeau, en-tête, héro (titre + deux boutons + visuel), bandeau de
confiance en 4 points, grille de 8 rayons, sélection de produits, bandeau de
marques, bloc conseil, zones de livraison, pied de page.

**Catalogue** — fil d'Ariane, titre et description de catégorie, colonne de
filtres à gauche (catégorie, marque *avec champ de recherche*, prix, en stock),
barre de tri avec compteur, grille 3 colonnes, pagination.
Sur mobile : filtres derrière un bouton avec compteur, filtres actifs en
pastilles supprimables, grille 2 colonnes.

**Fiche produit** — galerie (visuel principal + 4 miniatures), marque, titre,
prix, disponibilité, quantité, « Ajouter au panier », « Commander sur WhatsApp »,
encadré de réassurance (livraison / retrait / authenticité), onglets
Description · Composition · Conseils, bloc conseil pharmacien, produits
similaires.
Sur mobile : carrousel d'images, **barre d'achat collée en bas** avec le prix et
le bouton.

---

## 4. Règles à ne pas perdre de vue

1. **Tambacounda partout.** Titre, meta-description, héro, section livraison.
   C'est l'avantage concurrentiel du site et son principal levier de
   référencement local.
2. **WhatsApp est un vrai bouton**, au même niveau que le panier. Beaucoup de
   clients commanderont en discutant.
3. **Mobile d'abord.** L'essentiel du trafic viendra de téléphones sur des
   connexions faibles.
4. **Accessibilité** : de vrais `<button>`, `<a href>`, `<input>` + `<label>`,
   un `aria-label` sur les boutons qui n'ont qu'une icône, un contraste de
   4,5:1 sur le texte.
5. **Images** : WebP, 800px de large, moins de 80 Ko, toujours via `next/image`
   avec `width`/`height` pour éviter les sauts de mise en page.
6. **Ne pas toucher à la logique métier** : panier, commandes, paiement,
   appels API. Cette refonte ne concerne que la présentation.

---

## 5. Ce qu'il manque encore

- Logo en **SVG détouré** (fond transparent) et une **version blanche** pour le
  pied de page vert. Le PNG carré actuel a trop de marge blanche.
- Les informations de confiance, aujourd'hui absentes du site : nom de la
  pharmacie, nom du pharmacien titulaire, adresse, téléphone, WhatsApp,
  horaires, mentions légales, CGV.
- Frais et jours de livraison par zone (Tambacounda, Koumpentoum, Goudiry,
  Bakel, Kidira, Kédougou).
- Photos produits et descriptions rédigées (ne pas recopier les textes des
  marques : sans contenu propre, aucun intérêt pour le référencement).
