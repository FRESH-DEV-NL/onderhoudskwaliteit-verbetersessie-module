# Onderhoudskwaliteit Verbetersessie Module

Een WordPress plugin voor het beheren van comments/reviews in een gestructureerd workflow systeem met drie statussen voor verbetersessies.

## 📋 Functionaliteiten

### Workflow Management
- **3 statussen**: Te verwerken → Klaar voor export → Afgerond
- Automatische tracking van nieuwe WordPress comments
- Behoud comments zelfs als originele WordPress comment wordt verwijderd
- Admin reacties met auto-save functionaliteit

### Admin Interface
- Overzichtelijke tabellen met filtering en bulk acties
- Inline editable admin reacties (auto-save na 1 seconde)
- Filter op specifieke pagina's
- Visuele feedback bij acties
- Responsive design

### Data Tracking
Per comment wordt opgeslagen:
- Artikel informatie (titel, ID)
- Reviewer gegevens (naam, email, IP)
- Review content (automatisch gestript)
- Admin reactie
- Status en status historie
- Rating (indien beschikbaar)
- Metadata voor toekomstige uitbreidingen

## 🚀 Installatie

1. Upload de plugin folder naar `/wp-content/plugins/`
2. Activeer de plugin via WordPress Admin → Plugins
3. De database tabel wordt automatisch aangemaakt
4. Navigeer naar **Settings → Verbetersessie Module**

## 💻 Gebruik

### Comments Verwerken
1. Nieuwe comments worden automatisch toegevoegd aan "Te verwerken"
2. Voeg een admin reactie toe (wordt automatisch opgeslagen)
3. Verplaats naar "Klaar voor export" wanneer verwerkt
4. Na export, verplaats naar "Afgerond"

### Bulk Acties
- Selecteer meerdere comments met checkboxes
- Kies een bulk actie uit het dropdown menu
- Klik op "Toepassen"

### Filtering
- Gebruik de pagina filter om comments van specifieke pagina's te bekijken
- Filter werkt direct zonder pagina refresh

## 🔧 Technische Details

### Bestandsstructuur
```
onderhoudskwaliteit-verbetersessie-module/
├── onderhoudskwaliteit-verbetersessie-module.php  # Hoofdplugin bestand
├── includes/
│   ├── class-ovm-data-manager.php      # Database operaties
│   ├── class-ovm-comment-tracker.php   # Comment tracking
│   ├── class-ovm-admin-page.php        # Admin interface
│   └── class-ovm-ajax-handler.php      # AJAX handlers
├── assets/
│   ├── css/
│   │   └── admin.css                   # Admin styling
│   └── js/
│       └── admin.js                    # JavaScript functionaliteit
└── README.md

```

### Database
- Custom tabel: `{prefix}_ovm_comments`
- Automatische indexering voor performance
- Support voor toekomstige data cleanup

### Security
- Nonce verificatie voor alle AJAX requests
- Capability checks (alleen admins)
- Data sanitization en escaping
- SQL injection preventie

### Hooks & Filters
- `comment_post` - Track nieuwe comments
- `wp_insert_comment` - Track programmatisch toegevoegde comments
- `ovm_extract_comment_rating` - Custom rating extractie

## 📝 Vereisten

- WordPress 5.0 of hoger
- PHP 7.4 of hoger
- MySQL 5.6 of hoger

## 🔄 Updates

### Versie 1.0
- Initiële release
- Core workflow functionaliteit
- Auto-save admin reacties
- Bulk acties support

## 📄 Licentie

GPL v2 or later - Deze plugin is ontwikkeld door Fresh-Dev voor Onderhoudskwaliteit.

## 🤝 Support

Voor vragen of problemen, neem contact op via [fresh-dev.nl](https://fresh-dev.nl)