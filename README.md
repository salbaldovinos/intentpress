# IntentPress

> AI-powered semantic search plugin for WordPress

[![WordPress Version](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-0.1.2-orange.svg)](https://github.com/salbaldovinos/intentpress/releases)

IntentPress replaces WordPress's keyword-based search with intent-aware semantic search, delivering more relevant results with minimal configuration. Using AI-powered vector embeddings, IntentPress understands what users mean—not just what they type.

## Features

- **Semantic Search**: Find content by meaning, not just keywords
- **Guided Setup**: Onboarding wizard walks you through configuration
- **Graceful Fallback**: Automatically falls back to WordPress search when needed
- **Modern Admin UI**: React-powered dashboard with real-time status
- **Search Analytics**: Track search performance and popular queries
- **Encrypted Storage**: API keys are encrypted with AES-256-CBC
- **Developer Friendly**: REST API for custom integrations

## Requirements

- WordPress 6.4 or higher
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- OpenAI API key ([Get one here](https://platform.openai.com/api-keys))

## Installation

### From ZIP File

1. Download `intentpress-0.1.2.zip` from the [dist folder](https://github.com/salbaldovinos/intentpress) or build it yourself
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

### For Development

```bash
# Clone the repository
git clone https://github.com/salbaldovinos/intentpress.git
cd intentpress

# Install dependencies
npm install

# Build assets
npm run build

# Package for distribution
npm run package
```

## Quick Start

1. **Activate the Plugin**: Go to Plugins and activate IntentPress
2. **Complete Onboarding**: The setup wizard guides you through:
   - Entering your OpenAI API key (supports `sk-` and `sk-proj-` formats)
   - Selecting which post types to index
   - Initial content indexing
3. **Test Your Search**: Use the Dashboard's test search to verify it's working

## Admin Dashboard

### Dashboard Tab
- **System Health**: Real-time checks for API key, database, and indexing status
- **Index Status**: See how many posts are indexed vs total
- **Analytics**: Search counts, response times, and fallback rates (last 7 days)
- **Test Search**: Try semantic search directly from the admin

### Indexing Tab
- View indexing progress with percentage complete
- Trigger batch indexing for new or updated content
- Re-index outdated content when posts change
- Clear and rebuild the entire index

### Settings Tab
- **API Configuration**: Manage your OpenAI API key (encrypted storage)
- **Content Settings**: Choose which post types to index
- **Search Settings**: Results per page, similarity threshold, max results
- **Advanced**: Cache duration, fallback behavior

## Configuration Options

| Setting | Description | Default |
|---------|-------------|---------|
| OpenAI API Key | Your API key for embedding generation | Required |
| Post Types | Which content types to index | Posts, Pages |
| Results Per Page | Number of search results | 10 |
| Similarity Threshold | Minimum relevance score (0-1) | 0.5 |
| Fallback Enabled | Use WordPress search as backup | Yes |
| Cache Duration | How long to cache query embeddings | 1 hour |
| Max Results | Maximum results per query | 100 |

### Free Tier Limits

- **500 posts** indexed
- **1,000 searches** per month
- Automatic fallback to WordPress search when limits reached
- Counter resets on the 1st of each month

## How It Works

```
User Query → Query Embedding → Cosine Similarity Search → Ranked Results
```

1. **Indexing**: Content is converted to vector embeddings using OpenAI's `text-embedding-3-small` model (1536 dimensions)
2. **Storage**: Embeddings are stored in your WordPress database with content hashes for change detection
3. **Search**: User queries are converted to embeddings in real-time
4. **Matching**: Cosine similarity finds semantically similar content
5. **Results**: Posts are ranked by similarity score and returned

## REST API

| Endpoint | Method | Permission | Purpose |
|----------|--------|------------|---------|
| `/intentpress/v1/search` | POST | `read` | Execute semantic search |
| `/intentpress/v1/search/test` | POST | `manage_options` | Test search (admin) |
| `/intentpress/v1/settings` | GET | `manage_options` | Get current settings |
| `/intentpress/v1/settings` | POST | `manage_options` | Update settings |
| `/intentpress/v1/index` | POST | `manage_options` | Trigger indexing |
| `/intentpress/v1/index/status` | GET | `manage_options` | Get index progress |
| `/intentpress/v1/index/clear` | DELETE | `manage_options` | Clear all embeddings |
| `/intentpress/v1/health` | GET | `manage_options` | System health check |
| `/intentpress/v1/analytics` | GET | `manage_options` | Search analytics |
| `/intentpress/v1/validate-key` | POST | `manage_options` | Validate API key |
| `/intentpress/v1/onboarding` | GET/POST | `manage_options` | Onboarding status |

### Search Example

```javascript
const response = await fetch('/wp-json/intentpress/v1/search', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        query: 'how to improve website performance',
        page: 1,
        per_page: 10
    })
});

const result = await response.json();
// result.data.results - array of posts with similarity scores
// result.meta.search_type - 'semantic' or 'fallback'
// result.meta.execution_time - response time in seconds
```

## Development

### Commands

```bash
npm run start          # Development mode (watch)
npm run build          # Production build
npm run lint:js        # ESLint
npm run lint:css       # Stylelint
npm run test:unit      # Jest unit tests
npm run package        # Build and create ZIP
```

### Version Bumping

```bash
bash scripts/bump-version.sh 0.2.0
```

Updates version in `package.json`, `composer.json`, and `intentpress.php`.

### Project Structure

```
intentpress/
├── intentpress.php                    # Main plugin file
├── includes/                          # PHP classes
│   ├── class-intentpress-activator.php
│   ├── class-intentpress-admin.php
│   ├── class-intentpress-embedding-service.php
│   ├── class-intentpress-rest-api.php
│   ├── class-intentpress-search-handler.php
│   └── class-intentpress-vector-store.php
├── src/                               # React/TypeScript source
│   ├── index.tsx
│   └── admin/
│       ├── App.tsx
│       ├── components/
│       │   ├── DashboardTab.tsx
│       │   ├── IndexingTab.tsx
│       │   ├── OnboardingWizard.tsx
│       │   └── SettingsTab.tsx
│       ├── hooks/
│       │   └── useApi.ts
│       └── styles/
├── build/                             # Compiled assets (git-ignored)
├── dist/                              # Distribution ZIPs (git-ignored)
├── tests/jest/                        # Unit tests
├── scripts/                           # Build scripts
│   ├── package.sh
│   └── bump-version.sh
└── docs/                              # Documentation
```

## FAQ

### Does IntentPress replace my theme's search?

No. IntentPress hooks into WordPress's search system and returns more relevant results, but your theme's search templates remain unchanged.

### What happens if the API is unavailable?

IntentPress automatically falls back to WordPress's default keyword search. Visitors won't see errors—just standard search results.

### How much does the OpenAI API cost?

Very affordable with the `text-embedding-3-small` model:
- Indexing 1,000 posts: ~$0.02
- 10,000 searches: ~$0.02

### Is my content sent to OpenAI?

Yes, post content (title + body text) is sent to generate embeddings. No user data or metadata is sent. Embeddings are stored locally in your database.

### What API key formats work?

Both standard (`sk-...`) and project (`sk-proj-...`) OpenAI API keys are supported.

## Troubleshooting

### "Invalid API key format" error

- Verify your key at [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
- Ensure billing is enabled on your OpenAI account
- Check for extra spaces when pasting
- Both `sk-` and `sk-proj-` prefixes are supported

### Search returns no results

- Check indexing completed (Dashboard → Index Status)
- Lower the similarity threshold (try 0.3)
- Verify the content type is enabled for indexing

### Indexing stuck or slow

- Check for API rate limit errors in the Indexing tab
- Try smaller batch sizes
- Verify your OpenAI account has available credits

## Security

IntentPress follows WordPress security best practices:

- **SQL Injection**: All queries use `$wpdb->prepare()`
- **XSS**: All output properly escaped
- **CSRF**: Nonce verification on all state-changing operations
- **Authorization**: Capability checks on all admin endpoints
- **API Keys**: Encrypted with AES-256-CBC using WordPress salts

## License

GPL-2.0-or-later - [Full License](https://www.gnu.org/licenses/gpl-2.0.html)

## Credits

- Built with [WordPress](https://wordpress.org/)
- Powered by [OpenAI](https://openai.com/)
- UI: [@wordpress/components](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-components/)
- Build: [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)

## Links

- **Repository**: [github.com/salbaldovinos/intentpress](https://github.com/salbaldovinos/intentpress)
- **Issues**: [GitHub Issues](https://github.com/salbaldovinos/intentpress/issues)

---

Built with [Claude Code](https://claude.com/claude-code)
