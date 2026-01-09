# IntentPress

> AI-powered semantic search plugin for WordPress

[![WordPress Version](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

IntentPress replaces WordPress's keyword-based search with intent-aware semantic search, delivering more relevant results with minimal configuration. Using AI-powered vector embeddings, IntentPress understands what users mean—not just what they type.

## Features

- **Semantic Search**: Find content by meaning, not just keywords
- **Simple Setup**: Just add your API key and start indexing
- **Graceful Fallback**: Automatically falls back to WordPress search when needed
- **Modern Admin UI**: React-powered settings dashboard
- **Developer Friendly**: REST API, hooks, and filters for customization
- **Privacy Focused**: Minimal data sent to external services

## Requirements

- WordPress 6.4 or higher
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- OpenAI API key

## Installation

### From WordPress Admin

1. Download the latest release from the [Releases page](https://github.com/your-username/intentpress/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

### Manual Installation

1. Download and extract the plugin to `/wp-content/plugins/intentpress/`
2. Activate through the **Plugins** menu in WordPress

### For Development

```bash
# Clone the repository
git clone https://github.com/your-username/intentpress.git
cd intentpress

# Install dependencies
npm install
composer install

# Build assets
npm run build

# Start development watch mode
npm run start
```

## Quick Start

1. **Get an API Key**: Sign up at [platform.openai.com](https://platform.openai.com) and create an API key
2. **Configure IntentPress**: Go to **Settings → IntentPress** and enter your API key
3. **Index Your Content**: Click "Start Indexing" to process your posts
4. **Test Your Search**: Use the test search tool to verify it's working

## Configuration

### API Configuration

Navigate to **Settings → IntentPress → Settings** tab:

| Setting | Description | Default |
|---------|-------------|---------|
| OpenAI API Key | Your API key for embedding generation | Required |
| Post Types | Which content types to index | Posts, Pages |
| Results Per Page | Number of search results | 10 |
| Similarity Threshold | Minimum relevance score (0-1) | 0.5 |

### Free Tier Limits

- **500 posts** indexed
- **1,000 searches** per month
- Automatic fallback to WordPress search when limits reached

## How It Works

1. **Indexing**: IntentPress generates vector embeddings for your content using OpenAI's embedding model
2. **Search**: When a user searches, their query is converted to an embedding
3. **Matching**: Vector similarity finds content with matching meaning
4. **Results**: Relevant content is returned, ranked by semantic similarity

```
User Query → Query Embedding → Vector Similarity Search → Ranked Results
```

## Developer Documentation

### REST API

IntentPress exposes several REST API endpoints:

```
POST /wp-json/intentpress/v1/search
POST /wp-json/intentpress/v1/search/test
GET  /wp-json/intentpress/v1/settings
POST /wp-json/intentpress/v1/settings
POST /wp-json/intentpress/v1/index
GET  /wp-json/intentpress/v1/index/status
GET  /wp-json/intentpress/v1/health
```

#### Search Example

```javascript
const response = await fetch('/wp-json/intentpress/v1/search', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        query: 'healthy meal ideas',
        page: 1,
        per_page: 10
    })
});

const data = await response.json();
// data.results contains matched posts with similarity scores
```

### Hooks & Filters

#### Filters

```php
// Modify search results
add_filter( 'intentpress_search_results', function( $results, $query ) {
    // Customize results
    return $results;
}, 10, 2 );

// Adjust similarity threshold
add_filter( 'intentpress_similarity_threshold', function( $threshold ) {
    return 0.6; // Require higher relevance
} );

// Customize content before embedding
add_filter( 'intentpress_content_for_embedding', function( $content, $post_id ) {
    // Add custom fields, remove unwanted content, etc.
    return $content;
}, 10, 2 );
```

#### Actions

```php
// After successful indexing
add_action( 'intentpress_post_indexed', function( $post_id, $embedding ) {
    // Log, notify, etc.
}, 10, 2 );

// Before search query
add_action( 'intentpress_before_search', function( $query ) {
    // Analytics, logging, etc.
} );

// After indexing completes
add_action( 'intentpress_indexing_complete', function( $stats ) {
    // $stats contains indexed count, errors, duration
} );
```

### Extending IntentPress

```php
// Register custom post type for indexing
add_filter( 'intentpress_indexable_post_types', function( $post_types ) {
    $post_types[] = 'product';
    $post_types[] = 'documentation';
    return $post_types;
} );

// Add custom data to search results
add_filter( 'intentpress_result_data', function( $data, $post ) {
    $data['custom_field'] = get_post_meta( $post->ID, 'custom_field', true );
    return $data;
}, 10, 2 );
```

## Development

### Prerequisites

- Node.js 18+
- PHP 8.0+
- Composer
- Docker (for wp-env)

### Setup

```bash
# Install dependencies
npm install
composer install

# Start WordPress environment
npx wp-env start

# Development mode (watch)
npm run start

# Production build
npm run build
```

### Testing

```bash
# Run PHP tests
composer test

# Run JavaScript tests
npm run test:unit

# Run E2E tests
npm run test:e2e

# Run linters
npm run lint:js
composer phpcs
```

### Project Structure

```
intentpress/
├── intentpress.php              # Main plugin file
├── includes/                    # PHP classes
│   ├── class-intentpress-activator.php
│   ├── class-intentpress-search-handler.php
│   ├── class-intentpress-embedding-service.php
│   ├── class-intentpress-vector-store.php
│   └── class-intentpress-rest-api.php
├── src/                         # React/TypeScript source
│   ├── admin/
│   │   ├── App.tsx
│   │   ├── components/
│   │   └── hooks/
│   └── index.tsx
├── build/                       # Compiled assets
├── tests/
│   ├── phpunit/
│   └── jest/
├── languages/                   # Translation files
├── .wp-env.json                 # WordPress environment config
├── package.json
├── composer.json
├── phpcs.xml.dist
└── tsconfig.json
```

## FAQ

### Does IntentPress replace my theme's search template?

No, IntentPress integrates with your existing search. It intercepts search queries and returns more relevant results, but uses your theme's templates for display.

### What happens if the API is unavailable?

IntentPress automatically falls back to WordPress's default search. Your visitors won't see any errors—just standard keyword search results.

### How much does the API cost?

OpenAI's embedding API is very affordable. Typical costs:
- Indexing 1,000 posts: ~$0.10
- 10,000 searches: ~$0.20

### Is my content sent to OpenAI?

Yes, post content is sent to OpenAI to generate embeddings. Only the text content is sent—no personal user data. Embeddings are stored locally.

### Can I use a different AI provider?

Currently, IntentPress supports OpenAI. Support for additional providers (Anthropic, local models) is planned for future releases.

## Troubleshooting

### "API key invalid" error

1. Verify your API key at [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Ensure billing is enabled on your OpenAI account
3. Check for extra spaces when copying the key

### Search returns no results

1. Verify indexing completed successfully (check Dashboard tab)
2. Lower the similarity threshold in Settings
3. Check if the searched content type is enabled for indexing

### Indexing is stuck

1. Check the error log in the Indexing tab
2. Verify your API key hasn't hit rate limits
3. Try indexing a smaller batch of posts

### Performance issues

1. Enable caching in Settings
2. Reduce the number of indexed post types
3. Check server resources and increase PHP memory limit if needed

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details on:

- Code style and standards
- Testing requirements
- Pull request process
- Issue reporting

## Security

Found a security vulnerability? Please report it privately via [security@example.com](mailto:security@example.com). Do not open a public issue.

See [SECURITY.md](SECURITY.md) for our security policy.

## License

IntentPress is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a full history of changes.

## Credits

- Built with [WordPress](https://wordpress.org/)
- Powered by [OpenAI](https://openai.com/)
- UI components from [@wordpress/components](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-components/)

## Support

- **Documentation**: [docs.intentpress.io](https://docs.intentpress.io)
- **Issues**: [GitHub Issues](https://github.com/your-username/intentpress/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-username/intentpress/discussions)
- **Email**: [support@example.com](mailto:support@example.com)

---

Made with ❤️ for the WordPress community
