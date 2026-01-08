# FindPlayer Project

WordPress plugin ecosystem for managing sports events, player profiles, and community features.

## Plugins

### Core Plugins
- **find-player** - Main event calendar and management system
- **fp-iscrizione-giocatori** - Player registration with email confirmation and admin approval
- **find-player-sport** - Sport taxonomy and templates
- **fp-private-mail** - Private messaging system between users

### Additional Modules
- **calendario-eventi** - Universal calendar events integration
- **chat-facile** - Real-time chat with Supabase integration
- **form-preiscrizione-asd-supabase-full** - ASD pre-registration forms

## Recent Optimizations

### Version 2.5.0 (2026-01-08)
- ✅ Removed redundant files (`offff` files, ZIP backups)
- ✅ Refactored to MVC architecture
- ✅ Fixed user duplication bug
- ✅ Fixed event attribution issues
- ✅ Removed hardcoded credentials (security fix)
- ✅ Added comprehensive documentation

See individual plugin README files for detailed information.

## Security Configuration

**IMPORTANT**: Add the following to your `wp-config.php`:

```php
// SMTP Configuration for FindPlayer
define('FINDPLAYER_SMTP_PASSWORD', 'your-secure-password-here');
```

This is required for the email system to function. Never commit passwords to version control.

## Installation

1. Upload plugin directories to `/wp-content/plugins/`
2. Configure `wp-config.php` with required constants (see Security Configuration)
3. Activate plugins through WordPress admin
4. Configure Supabase credentials in plugin settings

## Documentation

Each plugin contains its own README.md with:
- Directory structure
- Configuration options
- API documentation
- Development guidelines

## Development

Follow WordPress coding standards and the MVC patterns established in the refactored plugins.