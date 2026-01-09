# Building an AI-powered WordPress search plugin with React, PHP, and Claude Code

**Yes, this stack is highly feasible and follows modern WordPress development best practices.** The combination of PHP for backend functionality and React for admin UI is now the standard approach used by major plugins like WooCommerce, Jetpack, and Yoast SEO. WordPress provides official tooling (`@wordpress/scripts`) that eliminates complex configuration, and Claude Code's CLAUDE.md pattern enables effective AI-assisted iterative development. The primary challenges you'll face involve API cost management for semantic search, WordPress plugin directory compliance rules around external services, and maintaining context across AI coding sessions.

This report covers everything you need to start: architecture patterns, tooling configurations, testing frameworks, AI workflow best practices, and the specific caveats for AI-powered search plugins.

---

## The React + PHP architecture is production-ready and well-supported

WordPress officially supports React through its **`@wordpress/scripts`** package, which provides pre-configured webpack, Babel, and PostCSS with zero manual setup. This is the same tooling powering the Gutenberg block editor and is maintained by WordPress core developers.

The key architectural pattern involves PHP handling all server-side logic (hooks, filters, REST API endpoints, database operations) while React powers the admin dashboard interface. Communication happens through WordPress's REST API with automatic nonce-based authentication. Here's the foundational setup:

```json
{
  "devDependencies": {
    "@wordpress/scripts": "^28.4.0"
  },
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start"
  }
}
```

The `@wordpress/scripts` package automatically generates an **`.asset.php` file** containing version hashes and dependency arrays, eliminating manual dependency management. When you `import` from `@wordpress/*` packages, the build process automatically converts these to references to WordPress's bundled libraries, preventing React version conflicts with other plugins.

For the admin dashboard, mount React to a container div created by your PHP menu page. The initialization pattern follows Gutenberg conventions:

```javascript
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { Panel, PanelBody, TextControl } from '@wordpress/components';

domReady(() => {
  const root = createRoot(document.getElementById('my-plugin-settings'));
  root.render(<SettingsPage />);
});
```

REST API communication uses `@wordpress/api-fetch`, which handles nonces automatically when WordPress's `wp-api-fetch` script is enqueued as a dependency. For your semantic search plugin, register custom endpoints with proper permission callbacks:

```php
register_rest_route('semantic-search/v1', '/search', [
    'methods' => 'POST',
    'callback' => 'handle_semantic_search',
    'permission_callback' => function() {
        return current_user_can('read');
    },
    'args' => [
        'query' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    ],
]);
```

---

## Linting and code quality requires dual JavaScript and PHP tooling

A WordPress plugin with React needs **two parallel linting systems**: ESLint with `@wordpress/eslint-plugin` for JavaScript and PHP_CodeSniffer with WordPress Coding Standards (WPCS) for PHP.

For JavaScript, the WordPress ESLint plugin provides recommended presets including React rules, JSX accessibility checking, and internationalization validation. Install and configure with a minimal `.eslintrc.json`:

```json
{
  "extends": ["plugin:@wordpress/eslint-plugin/recommended"],
  "globals": { "wp": true },
  "rules": {
    "@wordpress/i18n-text-domain": ["error", { "allowedTextDomain": "my-semantic-search" }]
  }
}
```

For PHP, install WPCS version 3.0+ via Composer. The critical configuration in `phpcs.xml.dist` includes setting your **text domain** and **global prefix** to satisfy plugin directory requirements:

```xml
<rule ref="WordPress.WP.I18n">
    <properties>
        <property name="text_domain" type="array" value="my-semantic-search"/>
    </properties>
</rule>
<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
    <properties>
        <property name="prefixes" type="array">
            <element value="semantic_search"/>
        </property>
    </properties>
</rule>
```

**Prettier integrates automatically** when installed alongside `@wordpress/eslint-plugin`. Use `@wordpress/prettier-config` for WordPress-standard formatting (tabs, not spaces). For pre-commit hooks, Husky with lint-staged ensures all staged files pass linting before commits:

```json
{
  "lint-staged": {
    "*.{js,jsx}": ["eslint --fix", "prettier --write"],
    "*.php": ["vendor/bin/phpcs --standard=phpcs.xml.dist"]
  }
}
```

Consider adding **PHPStan** with the `szepeviktor/phpstan-wordpress` extension for static analysis that catches type errors and undefined function calls that PHPCS misses.

---

## Testing requires Jest for React and PHPUnit with WordPress test framework

The `@wordpress/scripts` package includes Jest with WordPress-specific presets. Running `npm run test:unit` executes tests found in `__tests__/` directories or files with `.test.js` suffix. For components using `@wordpress/data` or `@wordpress/components`, mock the dependencies:

```javascript
jest.mock('@wordpress/data', () => ({
  useSelect: jest.fn(),
  useDispatch: jest.fn(),
}));

test('settings form renders', () => {
  useSelect.mockImplementation(() => ({ apiKey: 'test-key' }));
  render(<SettingsPage />);
  expect(screen.getByDisplayValue('test-key')).toBeInTheDocument();
});
```

For PHP, use **`wp-phpunit/wp-phpunit`** from Composer alongside PHPUnit. The WordPress test framework provides `WP_UnitTestCase` with factory methods for creating test posts, users, and terms. REST API endpoint testing uses `WP_REST_Server`:

```php
class REST_Search_Test extends WP_UnitTestCase {
    public function test_search_endpoint_returns_results() {
        $request = new WP_REST_Request('POST', '/semantic-search/v1/search');
        $request->set_body_params(['query' => 'test search']);
        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(200, $response->get_status());
    }
}
```

For end-to-end testing, WordPress core has standardized on **Playwright** (migrated from Puppeteer in 2023). The `@wordpress/e2e-test-utils-playwright` package provides utilities for admin authentication, post creation, and block editor interaction. Use `wp-env` to spin up a Docker-based WordPress environment for E2E tests.

A complete **GitHub Actions workflow** should run JS tests, PHP tests across a version matrix (PHP 8.0-8.3, WordPress 6.3-latest), and E2E tests in parallel:

```yaml
php-tests:
  strategy:
    matrix:
      php: ['8.0', '8.1', '8.2']
      wordpress: ['6.4', 'latest']
  steps:
    - uses: shivammathur/setup-php@v2
      with: { php-version: '${{ matrix.php }}' }
    - run: composer test
```

---

## Claude Code workflow centers on CLAUDE.md and structured task lists

**CLAUDE.md is the highest-leverage configuration point** for AI-assisted development. Claude Code automatically reads this file at the start of every conversation, treating it as persistent project context. Keep it under **300 lines** (ideally 60 lines) with universally applicable instructions—Claude can reliably follow around 150-200 total instructions including its system prompt.

Structure CLAUDE.md using the **WHAT-WHY-HOW framework**:

```markdown
# Semantic Search Plugin

## Project Overview
WordPress plugin replacing default search with AI-powered semantic search.
PHP 8.0+, WordPress 6.4+, React admin UI.

## Commands
- `npm run build` - Build React assets
- `composer phpcs` - Check PHP coding standards
- `composer test` - Run PHPUnit tests

## WordPress Security (MANDATORY)
- Sanitize: sanitize_text_field(), absint(), esc_url_raw()
- Escape: esc_html(), esc_attr(), wp_kses_post()
- Verify nonces: wp_verify_nonce(), check_ajax_referer()
- Check capabilities: current_user_can()

## Context Files
- docs/architecture.md - System design
- docs/api-reference.md - REST endpoints
```

For PRD-driven development, break features into **junior-engineer-level tasks** completable in one session (10-20 minutes). Each task needs clear acceptance criteria:

```markdown
## Phase 1: Embedding Infrastructure
- [ ] 1.1 Create wp_semantic_embeddings table migration
  - Acceptance: Migration creates table with id, post_id, embedding (LONGTEXT), created_at
- [ ] 1.2 Build EmbeddingService class with generate() method
  - Acceptance: PHPUnit tests pass, handles OpenAI API errors gracefully
```

Use a **task-by-task execution pattern**: Claude completes one task, reports status, waits for approval, then proceeds. At session end, prompt Claude to update task documents with progress notes and next steps.

**Context management is critical.** Never exceed 60% context window utilization. Use `/clear` between major phases (research → planning → implementation). For parallel work, use Git worktrees to run separate Claude Code sessions on different features.

---

## Semantic search introduces API costs, rate limits, and storage challenges

Building AI-powered search requires careful attention to **external API dependencies**. OpenAI's embedding API (text-embedding-3-small) has rate limits measured in requests per minute (RPM) and tokens per minute (TPM) that vary by account tier.

Embedding costs are manageable: approximately **$0.05-$0.10 per 1,000 posts** for generation. Since embeddings only need regeneration when content changes, this is largely a one-time cost. Implement **exponential backoff** for rate limit handling and always provide a **fallback to traditional keyword search** when the API fails.

For vector storage, WordPress-native options include:

- **Custom database table** with LONGTEXT column for serialized embeddings (simplest)
- **Supabase pgvector** for proper vector similarity search (recommended for scale)
- **Pinecone** or **Weaviate** as managed vector database services

The **AI Vector Search plugin** on WordPress.org demonstrates this architecture: OpenAI embeddings stored in Supabase with cosine similarity search. WP Engine also offers a managed vector database starting at ~$140/month on premium plans.

For the WordPress Plugin Directory, external AI services are permitted under **Guideline #6** (Software as a Service) if they "provide functionality of substance." You must document the service in your readme with Terms of Use links, and **user tracking requires explicit opt-in consent**. Never lock core functionality behind payment (trialware prohibition), but you can offer premium tiers via external add-ons.

---

## Security, performance, and compatibility require proactive mitigation

WordPress plugin security follows the principle: **never trust any data**. Sanitize all input as early as possible using `sanitize_text_field()`, `absint()`, or `esc_url_raw()`. Escape all output as late as possible using `esc_html()`, `esc_attr()`, or `wp_kses_post()`. Every form submission needs nonce verification; every admin action needs capability checks.

**React bundle size** impacts admin load times. Using WordPress's bundled React via webpack externals eliminates ~100KB. Implement code splitting with `React.lazy()` for settings pages that users may never visit. Use the webpack bundle analyzer to identify heavy dependencies—one team achieved 50% reduction by optimizing imports.

For **database queries**, always use `$wpdb->prepare()` with placeholders. Cache embedding results using WordPress transients to avoid repeated API calls. For search result caching, set TTL based on content freshness (e.g., 1 hour for frequently updated sites).

**Compatibility testing** should cover WordPress 6.3+ (current minus one major version), PHP 8.0-8.3, and both MySQL and MariaDB. Test with popular caching plugins (which may cache nonces causing 403 errors) and security plugins (which may block REST API requests). For multisite compatibility, handle `$network_wide` activation and use `switch_to_blog()` when iterating sites.

Common React + WordPress pitfalls include JavaScript conflicts from multiple plugins loading different library versions (use WordPress externals to avoid this), CSS conflicts with admin styles (scope all styles to your container), and nonce expiration during long admin sessions (refresh via Heartbeat API).

---

## Starter templates accelerate initial setup significantly

For production projects, **WP React Starter** by devowl.io provides the most complete boilerplate: React + TypeScript frontend, modern PHP with namespaces, Docker development environment, and CI/CD integration. Install via `create-wp-react-app` CLI.

For learning the patterns, **@wordpress/create-block** is the official starting point:

```bash
npx @wordpress/create-block@latest semantic-search-plugin
cd semantic-search-plugin
npm run start
```

This generates a complete plugin structure with PHP bootstrap, React entry point, build scripts, and proper asset enqueuing. While designed for blocks, the patterns transfer directly to admin settings pages.

**WPGens WordPress React Admin Panel** offers a modern alternative using Vite instead of webpack, providing significantly faster build times and hot module replacement. It includes TypeScript, Tailwind CSS, and React Query out of the box.

For local development, **`wp-env`** (via `@wordpress/env`) provides a Docker-based WordPress environment with single-command setup. Configure via `.wp-env.json`:

```json
{
  "plugins": ["."],
  "phpVersion": "8.1",
  "config": { "WP_DEBUG": true, "SCRIPT_DEBUG": true }
}
```

Run `wp-env start` and access WordPress at http://localhost:8888 with username "admin" and password "password".

---

## Conclusion: A proven path with specific tooling choices

This architecture represents the current WordPress development standard, validated by plugins serving millions of users. Your **recommended stack** for starting immediately:

| Layer | Tool | Rationale |
|-------|------|-----------|
| Build | @wordpress/scripts | Zero-config, official support, handles externals |
| Components | @wordpress/components | Consistent WordPress admin UI |
| State | useState + React Query | Simple until complexity demands @wordpress/data |
| PHP Linting | PHPCS + WPCS 3.0 | Required for plugin directory |
| JS Linting | @wordpress/eslint-plugin | Includes React, i18n, a11y rules |
| PHP Testing | PHPUnit + wp-phpunit | WordPress integration testing |
| JS Testing | Jest via wp-scripts | Built-in, WordPress-configured |
| E2E | Playwright | Official WordPress choice since 2023 |
| Local Dev | wp-env | Docker-based, minimal configuration |
| Vector Storage | Supabase pgvector | Open-source, self-hostable, free tier available |
| Embeddings | text-embedding-3-small | Cost-effective, sufficient quality for search |

The AI-assisted workflow with Claude Code requires disciplined context management: keep CLAUDE.md concise, structure PRDs into atomic tasks, use external markdown files for session continuity, and always verify security-sensitive code manually. Run actual linters rather than relying on Claude for style enforcement—it's expensive and slow for that purpose.

Start by scaffolding with `@wordpress/create-block`, examine the AI Vector Search plugin source code as a reference implementation, and maintain a `ROADMAP.md` tracking completed work and architectural decisions. The combination of modern tooling and AI assistance makes this project achievable for a solo developer with proper planning.