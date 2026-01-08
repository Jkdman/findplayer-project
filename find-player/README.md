# FindPlayer Plugin - Refactored Structure

## Overview
This plugin has been reorganized into an MVC (Model-View-Controller) architecture for better maintainability, scalability, and code organization.

## Directory Structure

```
find-player/
├── find-player-refactored.php    # New main plugin file (refactored)
├── find-player.php                # Original plugin file (kept for reference)
├── controllers/                   # Business logic controllers
│   └── class-event-token-controller.php
├── models/                        # Data models
│   ├── class-event-post-type.php
│   └── class-user-sync.php
├── views/                         # Templates (future)
├── helpers/                       # Utility functions and helpers
│   ├── config.php
│   ├── class-assets-helper.php
│   └── class-calendar-integration.php
├── includes/                      # Legacy includes
│   ├── functions-nickname.php
│   ├── functions-player.php
│   └── legacy-features.php       # Remaining features to be refactored
├── templates/                     # View templates
│   └── single-fp_giocatore.php
└── assets/                        # CSS/JS assets (future)
```

## Refactored Components

### Controllers
- **Event Token Controller** (`class-event-token-controller.php`)
  - Handles token-based event confirmation for guest users
  - Manages event publishing workflow
  - Integrates with Supabase for event confirmation

### Models
- **Event Post Type** (`class-event-post-type.php`)
  - Registers the `findplayer_event` custom post type
  - Handles automatic cleanup of old events (>7 days)

- **User Sync** (`class-user-sync.php`)
  - **Fixes duplicate user bug**: Synchronizes WordPress users with FindPlayer custom user types
  - **Fixes event attribution**: Links author profiles to player cards
  - Updates display names, nicknames, and slugs consistently
  - Redirects author links to player profile pages

### Helpers
- **Config** (`config.php`)
  - Centralized Supabase configuration
  - Environment-specific constants

- **Assets Helper** (`class-assets-helper.php`)
  - Enqueues Leaflet map library
  - Manages plugin assets

- **Calendar Integration** (`class-calendar-integration.php`)
  - Exports FindPlayer events to "Calendario Eventi Universale" plugin
  - Prevents duplicate events
  - Filters by date range and published status

## Bug Fixes Implemented

### 1. User Duplication Fix
**Problem**: Users were being created as both WordPress users and FindPlayer custom user types, causing confusion and data inconsistency.

**Solution**: The `FP_User_Sync` model now:
- Automatically synchronizes WP user profiles with FindPlayer player cards
- Maintains a single source of truth (player card)
- Updates WP user data to match player information on profile updates

### 2. Event Attribution Fix
**Problem**: Events were not properly associated with user profiles.

**Solution**: 
- Author links now redirect to player cards instead of WP author archives
- `fp_wp_user_id` meta field links WP users to player cards
- Event creators are properly attributed and visible on player profiles

## Migration Notes

### Using the Refactored Version
To activate the refactored version:
1. Deactivate the old plugin if active
2. Rename `find-player-refactored.php` to `find-player-main.php` (or update plugin activation)
3. The old `find-player.php` is kept for reference during transition

### Backwards Compatibility
- All existing hooks and filters are preserved
- Database structure remains unchanged
- Shortcodes continue to work (currently in `legacy-features.php`)

## Future Refactoring Tasks

### Phase 2 (Pending)
- [ ] Extract shortcode handlers into separate controller classes
- [ ] Move template rendering logic to views/
- [ ] Create dedicated Supabase service class
- [ ] Implement proper dependency injection
- [ ] Add unit tests for each component
- [ ] Refactor fp-iscrizione-giocatori plugin (1490 lines)

### Shortcodes to Extract
- `[fp_form_create_event]` - Event creation form
- `[fp_event_calendar]` - Event calendar/list view
- `[fp_event_map]` - Leaflet map of events
- `[fp_manage_event]` - Event management interface
- `[fp_event_detail]` - Event detail page with booking

## Code Quality Improvements

### Before Refactoring
- Single 3064-line file with mixed concerns
- No clear separation of responsibilities
- Difficult to test and maintain
- Tightly coupled code

### After Refactoring
- Modular architecture with clear responsibilities
- Organized into logical components
- Easy to locate and modify specific features
- Follows WordPress coding standards
- Better code reusability
- Prepared for unit testing

## Development Guidelines

### Adding New Features
1. Determine if it's a controller, model, helper, or view concern
2. Create new class file in appropriate directory
3. Follow naming convention: `class-{feature-name}.php`
4. Initialize in `find-player-refactored.php`
5. Document in this README

### Coding Standards
- Use PSR-4 autoloading conventions (where applicable)
- Follow WordPress coding standards
- Use class-based architecture for new features
- Add inline documentation for complex logic
- Use meaningful variable and function names

## Support
For issues or questions about the refactored code structure, contact the development team.

## Version History
- **2.5.0** - Major refactoring into MVC architecture, bug fixes for user duplication and event attribution
- **2.4.0** - Previous version (monolithic structure)
