# FP Iscrizione Giocatori - Refactored Structure

## Overview
Player registration plugin with token-based email confirmation, admin approval, and Supabase synchronization. Refactored for better organization and maintainability.

## Directory Structure

```
fp-iscrizione-giocatori/
├── fp-iscrizione-giocatori-refactored.php  # New main file (refactored)
├── fp-iscrizione-giocatori.php             # Original file (reference)
├── controllers/                            # Business logic (future)
├── models/                                 # Data models
│   ├── class-database.php
│   └── class-player-post-type.php
├── helpers/                                # Utility functions
│   └── config.php
├── includes/                               # Feature modules
│   ├── ajax-check-giocatore.php
│   ├── cron-eventi.php
│   ├── eventi-token-mail.php
│   ├── functions-eventi.php
│   ├── rating.php
│   ├── template-loader.php
│   ├── votazioni.php
│   ├── legacy-player-features.php        # Remaining code to refactor
│   ├── metaboxes/
│   │   ├── fp-evento-chiusura-metabox.php
│   │   ├── fp-giocatore-privacy-metabox.php
│   │   └── fp-giocatore-sport-metabox.php
│   └── sports/
│       ├── sports-cpt.php
│       ├── sports-helpers.php
│       └── sports-migrate.php
├── templates/                              # View templates
│   └── single-fp_giocatore.php
└── assets/                                 # CSS/JS
    └── fp-search.css
```

## Refactored Components

### Models
- **Database** (`class-database.php`)
  - Manages database table creation
  - Handles temporary players table (token confirmation)
  - Manages player votes table
  - Schedules/unschedules cleanup cron jobs

- **Player Post Type** (`class-player-post-type.php`)
  - Registers `fp_giocatore` custom post type
  - Centralized CPT configuration

### Helpers
- **Config** (`config.php`)
  - Centralized configuration (Supabase, mail settings, tokens)
  - Mail filter configuration
  - Mail headers generator

### Existing Modular Includes
- **AJAX** - Player duplicate checking
- **Cron** - Event cleanup tasks
- **Token Mail** - Email confirmation system
- **Functions** - Event-related utilities
- **Rating** - Player rating system
- **Votazioni** - Player voting system
- **Metaboxes** - Admin interface components
- **Sports** - Sport taxonomy and helpers

## Features

### Player Registration Flow
1. User submits frontend registration form
2. Temporary entry created in database with token
3. Confirmation email sent with unique token link
4. User clicks link to confirm email
5. Player card created with 'pending' status
6. Admin reviews and approves
7. On approval: Player data synced to Supabase
8. WordPress user account created and linked

### Database Tables
- `wp_fp_giocatori_temp` - Temporary registrations pending email confirmation
- `wp_fp_player_votes` - Player rating/voting system

### Integration
- **Supabase**: Player data synchronization
- **WordPress Users**: Automatic account creation on approval
- **Email System**: Token-based confirmation
- **Cron Jobs**: Daily cleanup of expired tokens

## Improvements Made

### Before Refactoring
- 1490-line main file
- Mixed configuration and logic
- Database setup scattered
- No clear separation of concerns

### After Refactoring
- Modular structure with clear responsibilities
- Centralized configuration
- Separated database management
- Clean activation/deactivation hooks
- Better code organization
- Easier to maintain and extend

## Migration Notes

### Using Refactored Version
1. Test in staging environment first
2. Both versions maintain same database structure
3. All hooks and filters preserved
4. No data migration needed

## Future Refactoring Tasks

### Phase 2 (Pending)
- [ ] Extract player confirmation handler to controller
- [ ] Create Supabase service class
- [ ] Move shortcode handlers to controllers
- [ ] Refactor form rendering to views
- [ ] Add unit tests
- [ ] Extract remaining code from legacy file

### Code to Extract
- Player confirmation token handler (~150 lines)
- Supabase sync functions (~200 lines)
- Admin column customizations
- Gallery metabox
- Player card rendering
- Search results shortcode

## Configuration

### Required Constants
```php
FP_SUPABASE_URL          // Supabase project URL
FP_SUPABASE_API_KEY      // Supabase anon key
```

### Optional Constants
```php
FP_MAIL_FROM_NAME        // Default: 'Find Player'
FP_MAIL_FROM_EMAIL       // Default: 'no-reply@findplayer.it'
FP_MAIL_REPLYTO          // Default: 'findplayeritaly@gmail.com'
FP_ADMIN_EMAIL           // Default: 'findplayeritaly@gmail.com'
FP_TOKEN_TTL_HOURS       // Default: 48
```

## Development

### Adding Features
1. Determine appropriate location (controller/model/helper)
2. Create new class file with descriptive name
3. Initialize in main plugin file
4. Document in this README

### Coding Standards
- Follow WordPress coding standards
- Use meaningful class and function names
- Add PHPDoc comments for all public methods
- Keep functions focused and single-purpose

## Version History
- **1.1.0** - Refactored into MVC structure, improved organization
- **1.0.0** - Initial monolithic version
