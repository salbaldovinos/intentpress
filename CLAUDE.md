# CLAUDE.md - IntentPress

> AI-powered semantic search plugin for WordPress

## Project Overview

IntentPress replaces WordPress's keyword-based search with intent-aware semantic search using OpenAI embeddings and vector similarity. Built with PHP 8.0+ backend and React 18+ admin UI using `@wordpress/scripts`.

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.0+, WordPress 6.4+ |
| Frontend | React 18, TypeScript, @wordpress/components |
| Build | @wordpress/scripts (webpack) |
| State | useState + React Query |
| Embeddings | OpenAI text-embedding-3-small |
| Vector Storage | WordPress MySQL (MVP), Supabase pgvector (Pro) |
| Testing | PHPUnit, Jest, Playwright |

## Directory Structure

```
intentpress/
├── intentpress.php           # Main plugin file
├── includes/                 # PHP classes
│   ├── class-intentpress-activator.php
│   ├── class-intentpress-search-handler.php
│   ├── class-intentpress-embedding-service.php
│   ├── class-intentpress-vector-store.php
│   └── class-intentpress-rest-api.php
├── src/                      # React source (TypeScript)
│   ├── admin/
│   │   ├── App.tsx
│   │   ├── components/
│   │   └── hooks/
│   └── index.tsx
├── build/                    # Compiled assets (git-ignored)
├── tests/
│   ├── phpunit/
│   └── jest/
└── docs/                     # Additional documentation
```

## Commands

```bash
# Development
npm run start              # Watch mode for React
npm run build              # Production build

# Linting
npm run lint:js            # ESLint for JS/TS
composer phpcs             # PHP CodeSniffer
composer phpcbf            # Auto-fix PHP issues

# Testing
npm run test:unit          # Jest tests
composer test              # PHPUnit tests
npm run test:e2e           # Playwright E2E

# WordPress Environment
npx wp-env start           # Start Docker environment
npx wp-env stop            # Stop environment
```

## Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| PHP Classes | `IntentPress_Class_Name` | `IntentPress_Search_Handler` |
| PHP Functions | `intentpress_function_name` | `intentpress_get_settings` |
| Hooks (actions) | `intentpress_hook_name` | `intentpress_before_index` |
| Hooks (filters) | `intentpress_filter_name` | `intentpress_search_results` |
| Options | `intentpress_option_name` | `intentpress_api_key` |
| REST Namespace | `intentpress/v1` | `/wp-json/intentpress/v1/search` |
| React Components | PascalCase | `SettingsPage`, `SearchPreview` |
| React Functions | camelCase | `handleSearch`, `fetchResults` |
| TypeScript Files | kebab-case | `settings-page.tsx` |

## WordPress Security (MANDATORY)

### Input Sanitization
```php
sanitize_text_field( $input );      // General text
absint( $input );                   // Positive integers
esc_url_raw( $input );              // URLs for database
wp_kses_post( $input );             // HTML content
```

### Output Escaping
```php
esc_html( $output );                // Plain text
esc_attr( $output );                // HTML attributes
esc_url( $output );                 // URLs in HTML
wp_kses_post( $output );            // Trusted HTML
```

### Security Verification
```php
// Always verify nonces
wp_verify_nonce( $_POST['nonce'], 'intentpress_action' );
check_ajax_referer( 'intentpress_nonce', 'security' );

// Always check capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized' );
}
```

### Database Queries
```php
// Always use prepared statements
$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}intentpress_embeddings WHERE post_id = %d", $post_id );
```

## REST API Endpoints

| Endpoint | Method | Permission | Purpose |
|----------|--------|------------|---------|
| `/search` | POST | `read` | Execute semantic search |
| `/search/test` | POST | `manage_options` | Test search (admin) |
| `/settings` | GET, POST | `manage_options` | Read/update settings |
| `/index` | POST | `manage_options` | Trigger indexing |
| `/index/status` | GET | `manage_options` | Get index progress |
| `/health` | GET | `manage_options` | System health check |

## Key PHP Classes

- `IntentPress_Search_Handler` - Orchestrates search flow, manages fallback
- `IntentPress_Embedding_Service` - Generates embeddings via OpenAI
- `IntentPress_Vector_Store` - Stores and queries embeddings
- `IntentPress_Cache` - Manages query and result caching
- `IntentPress_REST_API` - Registers and handles REST endpoints

## Key React Components

- `App.tsx` - Main settings container with tabs
- `DashboardTab.tsx` - Status overview, health checks
- `SettingsTab.tsx` - API key, post types, behavior config
- `IndexingTab.tsx` - Index management, progress UI
- `AnalyticsTab.tsx` - Search statistics, top queries

## Database Schema

### Embeddings Table
```sql
{prefix}intentpress_embeddings (
    id BIGINT PRIMARY KEY,
    post_id BIGINT UNIQUE,
    embedding LONGTEXT,
    model_version VARCHAR(50),
    content_hash VARCHAR(32),
    created_at DATETIME,
    updated_at DATETIME
)
```

### Options (wp_options)
- `intentpress_api_key` - Encrypted OpenAI API key
- `intentpress_indexed_post_types` - Array of post types
- `intentpress_per_page` - Results per page (default: 10)
- `intentpress_similarity_threshold` - Min score (default: 0.5)
- `intentpress_monthly_searches` - Free tier counter

## Error Handling Rules

1. **Never break the site** - Always fallback to WordPress search
2. **Fail silently for visitors** - No error messages shown to users
3. **Log for debugging** - Technical details go to logs only
4. **Notify admins** - Show admin notices for issues requiring attention

## Performance Targets

- Search response: < 500ms (P95)
- Indexing: 100 posts/minute
- Cache query embeddings: 1-hour TTL
- Maximum results: 100 per query

## Free Tier Limits

- 500 posts indexed
- 1,000 searches/month
- Counter resets on 1st of month
- Silent fallback when limits reached

## Testing Requirements

- All PHP classes need PHPUnit tests
- All React components need Jest tests
- REST endpoints need integration tests
- Security-sensitive code needs manual review

## Context Files

Read these for detailed specifications:
- `docs/PRD-Overview.md` - Product requirements
- `docs/PRD-User-Stories.md` - User stories with acceptance criteria
- `docs/00-FRD-Core-Search.md` - Search functionality specs
- `docs/00-FRD-Admin-Settings.md` - Admin UI specs
- `docs/00-FRD-Indexing.md` - Indexing system specs
- `docs/PRD-Error-Handling.md` - Error handling specs

## Code Style Enforcement

- **PHP**: WordPress Coding Standards (WPCS 3.0+)
- **JavaScript/TypeScript**: @wordpress/eslint-plugin
- **CSS**: Follow WordPress admin styles
- Run linters before committing - they are authoritative

## Do NOT

- Store API keys in plain text
- Make direct database queries without `$wpdb->prepare()`
- Output unescaped user input
- Skip nonce verification on form submissions
- Hardcode text strings (use `__()` for i18n)
- Use `eval()` or `create_function()`
- Include external resources without integrity checks
