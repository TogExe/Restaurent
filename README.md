# Le Restaurant - Expérience Gastronomique & UI Moderne

Ce projet est une refonte conceptuelle d'un site web de restauration. Nous avons
voulu nous éloigner des codes classiques (fonds blancs, designs neutres) pour
proposer une interface premium, nocturne et hautement personnalisée.

## Accès Direct

Le site est déployé via GitHub Pages et peut être visité directement à cette
adresse :\
**[https://togexe.github.io/Restaurent/](https://togexe.github.io/Restaurent/)**

---

## Concept Visuel : Gastronomie Nocturne, Liquid Glass et Ricing

Le design de ce site repose sur deux éléments qui nous passionnent :

- **L'esthétique "Liquid Glass" d'Apple :** Nous nous sommes inspirés du design
  transparent de macOS. L'idée est d'utiliser des surfaces semi-transparentes et
  des fonds floutés (`backdrop-filter`) pour donner de la profondeur au site.
  Cela donne l'impression que les éléments flottent sur des plaques de verre.
- **Le "Ricing" sur Arch Linux :** Étant habitués à personnaliser et _tweak_
  notre environnement de bureau (Hyprland, Waybar...), nous avons voulu
  appliquer la même rigueur ici. Le design est pensé comme un environnement de
  bureau minimaliste où chaque marge, bordure et couleur est calculée au pixel
  près.

## Choix de Couleurs (Catppuccin Mocha)

Nous avons choisi le thème Catppuccin Mocha, très connu dans la communauté Linux
pour ses tons sombres et doux pour les yeux.

- ![#1e1e2e](https://placehold.co/15x15/1e1e2e/1e1e2e.png) **Les Fonds
  (`#1e1e2e`) :** Un dégradé bleu nuit très profond. Cela donne une ambiance
  feutrée et fait ressortir les photos des plats.
- ![#cdd6f4](https://placehold.co/15x15/cdd6f4/cdd6f4.png) **Les Textes
  (`#cdd6f4`) :** Pour éviter la fatigue visuelle, nous avons opté pour un blanc
  légèrement bleuté.
- ![#cba6f7](https://placehold.co/15x15/cba6f7/cba6f7.png) **Le Mauve
  (`#cba6f7`) :** Nous avons pris cette couleur pour les titres, car cela
  rappelle le vin et le luxe.
- ![#5fccff](https://placehold.co/15x15/5fccff/5fccff.png) **Le Saphir
  (`#5fccff`) :** Utilisé pour dynamiser les éléments cliquables et les noms mis
  en avant.
- ![#89b4fa](https://placehold.co/15x15/89b4fa/89b4fa.png) **Les Boutons
  (`#89b4fa`) :** Un bleu pervenche qui ressort parfaitement sur le fond sombre
  pour guider l'utilisateur.

## Structure du Projet

- `index.html` : Page de présentation (Héros, Horaires, Histoire).
- `Accueil.html` & `Menu.html` : Pages de consultation des plats avec filtres.
- `SeConnecter.html` & `CreerCompte.html` : Interfaces de connexion en verre
  dépoli.
- `style.css` : Feuille de style unique gérant le design responsive et le
  glassmorphism.

## Technologies Utilisées

- HTML5 (Sémantique)
- CSS3 (Flexbox, CSS Grid, Variables CSS, Backdrop-filter)
- Polices : Playfair Display (Titres) & Lato (Corps de texte)

