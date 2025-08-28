# Onderhoudskwaliteit Verbetersessie Module

Een WordPress plugin voor het beheren van comments/reviews in een gestructureerd workflow systeem met drie statussen voor verbetersessies.

## 📋 Functionaliteiten

### Workflow Management
- **3 statussen**: Te verwerken → Klaar voor export → Afgerond
- Automatische tracking van nieuwe WordPress comments
- Behoud comments zelfs als originele WordPress comment wordt verwijderd
- Admin reacties met auto-save functionaliteit
- WordPress comment verwijdering voor afgeronde items

### Admin Interface
- Overzichtelijke tabellen met filtering en bulk acties
- Inline editable admin reacties (auto-save na 1 seconde)
- Status-specifieke pagina filtering (toont alleen relevante artikelen per tab)
- Visuele feedback bij acties
- Responsive design
- Afbeelding modal viewer voor ingezonden afbeeldingen

### ChatGPT Integratie
- **Instellingen tab** voor OpenAI API configuratie
- Configureerbare prompt templates met placeholders
- Automatische reactie generatie via ChatGPT API
- Direct invullen van gegenereerde reacties in admin velden

### CSV Export
- Export functionaliteit voor "Klaar voor export" items
- Kolommen: Artikel, Datum ingezonden, Auteur, Opmerking, Antwoord
- Alfabetische sortering op artikel naam
- UTF-8 BOM voor Excel compatibiliteit

### Data Tracking
Per comment wordt opgeslagen:
- Artikel informatie (titel, ID)
- Reviewer gegevens (naam, email, IP)
- Review content (automatisch gestript met behoud van enters)
- Admin reactie
- Status en status historie
- Rating (indien beschikbaar)
- Afbeeldingen (automatische detectie en extractie)
- Metadata voor toekomstige uitbreidingen

## 🚀 Installatie

1. Upload de plugin folder naar `/wp-content/plugins/`
2. Activeer de plugin via WordPress Admin → Plugins
3. De database tabel wordt automatisch aangemaakt
4. Navigeer naar **Settings → Verbetersessie Module**
5. *(Optioneel)* Configureer ChatGPT API in de Instellingen tab

## 💻 Gebruik

### Comments Verwerken
1. Nieuwe comments worden automatisch toegevoegd aan "Te verwerken"
2. Voeg een admin reactie toe (wordt automatisch opgeslagen)
   - *Tip: Gebruik de ChatGPT knop voor automatische reactie generatie*
3. Verplaats naar "Klaar voor export" wanneer verwerkt
4. Exporteer naar CSV vanuit "Klaar voor export" tab
5. Na export, verplaats naar "Afgerond"
6. *(Optioneel)* Verwijder originele WordPress comments bij afgeronde items

### ChatGPT Functionaliteit
1. Configureer OpenAI API key in de Instellingen tab
2. Pas eventueel de prompt template aan (gebruik `[reactie_tekst]` placeholder)
3. Klik op de ChatGPT knop bij items in "Te verwerken" voor automatische reactie generatie

### Bulk Acties
- Selecteer meerdere comments met checkboxes
- Kies een bulk actie uit het dropdown menu:
  - Status wijzigingen
  - WordPress comments verwijderen (alleen bij afgeronde items)
  - Items verwijderen
- Klik op "Toepassen"

### Filtering
- Gebruik de pagina filter om comments van specifieke pagina's te bekijken
- Filter toont per tab alleen artikelen met comments in die status
- Filter werkt direct zonder pagina refresh

### Afbeeldingen
- Ingezonden afbeeldingen worden automatisch gedetecteerd
- Klik op "Bekijk" voor modal popup weergave
- Download functie beschikbaar per afbeelding

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

### Versie 1.1
- **ChatGPT Integratie**: Automatische reactie generatie via OpenAI API
- **Instellingen Tab**: Configuratie voor API keys en prompt templates
- **Afbeelding Support**: Automatische detectie, extractie en modal viewer
- **WordPress Comment Verwijdering**: Veilig verwijderen van originele comments bij afgeronde items
- **Verbeterde Text Processing**: Behoud van enters en betere formatting
- **Status-specifieke Filtering**: Pagina filter toont alleen relevante artikelen per tab
- **CSV Export Verbetering**: Alfabetische sortering en geoptimaliseerde kolommen
- **Read-only Functionaliteit**: Opmerking en reactie alleen bewerkbaar in "Te verwerken" status
- **UI/UX Verbeteringen**: Betere afbeelding layout en modal functionaliteit

### Versie 1.0
- Initiële release
- Core workflow functionaliteit
- Auto-save admin reacties
- Bulk acties support

## 📄 Licentie

GPL v2 or later - Deze plugin is ontwikkeld door Fresh-Dev voor Onderhoudskwaliteit.

## 🤝 Support

Voor vragen of problemen, neem contact op via [fresh-dev.nl](https://fresh-dev.nl)