# AGENTS.md - AI Coding Agent Instructions

> Centralized instruction manual for AI coding agents working on IntentPress

This document provides AI coding agents with everything needed to understand, operate, and contribute to the IntentPress project. It complements `README.md` (for humans) and `CLAUDE.md` (quick reference) by focusing on agent-specific workflows, patterns, and guardrails.

---

## Table of Contents

1. [Project Context](#1-project-context)
2. [Environment Setup](#2-environment-setup)
3. [Build Commands Reference](#3-build-commands-reference)
4. [Code Style & Standards](#4-code-style--standards)
5. [Testing Workflows](#5-testing-workflows)
6. [Security Checklist](#6-security-checklist)
7. [File Operation Guidelines](#7-file-operation-guidelines)
8. [Common Patterns](#8-common-patterns)
9. [Error Handling Patterns](#9-error-handling-patterns)
10. [Task Execution Protocol](#10-task-execution-protocol)
11. [Context Management](#11-context-management)
12. [Do NOT List](#12-do-not-list)

---

## 1. Project Context

### What is IntentPress?

IntentPress is a WordPress plugin that replaces the default keyword-based search with AI-powered semantic search using vector embeddings. It consists of:

- **PHP Backend**: Handles WordPress integration, REST API, database operations, shortcodes
- **React Frontend**: Powers the admin settings dashboard
- **External API**: Communicates with OpenAI for embedding generation
- **Search Integration**: Hooks into WordPress search system, provides shortcodes and widgets

### Key PHP Classes

| Class | Purpose |
|-------|---------|
| `IntentPress_Search_Handler` | Orchestrates search flow, manages fallback |
| `IntentPress_Search_Integration` | WordPress search hooks, shortcodes, widget, template tags |
| `IntentPress_Embedding_Service` | Generates embeddings via OpenAI |
| `IntentPress_Vector_Store` | Stores and queries embeddings |
| `IntentPress_REST_API` | Registers and handles REST endpoints |
| `IntentPress_Admin` | Admin dashboard and settings UI |
| `IntentPress_Activator` | Plugin activation/deactivation hooks |

### Key Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Embedding Provider | OpenAI text-embedding-3-small | Cost-effective, fast, sufficient quality |
| Vector Storage (MVP) | WordPress MySQL with LONGTEXT | Simple, no external dependencies |
| Vector Storage (Pro) | Supabase pgvector | Proper vector similarity search at scale |
| Build Tool | @wordpress/scripts | Official WP tooling, zero-config webpack |
| State Management | useState + React Query | Simple until complexity demands more |
| CSS Strategy | @wordpress/components + scoped CSS | Consistent with WP admin UI |

### Documentation Hierarchy

Read these files in order for full context:

1. `CLAUDE.md` - Quick reference (always read first)
2. `docs/00-PRD-Overview.md` - Product vision, goals, personas
3. `docs/01-PRD-User-Stories.md` - Detailed user stories with acceptance criteria
4. `docs/02-PRD-Onboarding.md` - Onboarding flow
5. `docs/03-PRD-Error-Handling.md` - Error handling specifications
6. `docs/00-FRD-Core-Search.md` - Technical search specifications
7. `docs/01-FRD-Admin-Settings.md` - Admin UI specifications
8. `docs/02-FRD-Indexing.md` - Indexing system specifications
9. `docs/03-FRD-Onboarding.md` - User onboarding specification
10. `docs/04-FRD-Error-Handling.md` - Error handling technical specifications

---

## 2. Environment Setup

### Prerequisites Verification

Before starting work, verify these are available:

```bash
# Check Node.js (18+ required)
node --version

# Check PHP (8.0+ required)
php --version

# Check Composer
composer --version

# Check Docker (for wp-env)
docker --version
```

### Project Initialization

```bash
# Clone and enter directory
git clone <repository-url>
cd intentpress

# Install JavaScript dependencies
npm install

# Install PHP dependencies
composer install

# Start WordPress environment
npx wp-env start
# → WordPress available at http://localhost:8888
# → Login: admin / password

# Start development watch mode
npm run start
```

### Environment Configuration

The `.wp-env.json` file configures the development environment:

```json
{
    "plugins": ["."],
    "phpVersion": "8.1",
    "config": {
        "WP_DEBUG": true,
        "WP_DEBUG_LOG": true,
        "SCRIPT_DEBUG": true
    }
}
```

---

## 3. Build Commands Reference

### Essential Commands

| Command | Purpose | When to Use |
|---------|---------|-------------|
| `npm run start` | Watch mode for React development | During frontend development |
| `npm run build` | Production build of assets | Before committing, testing production |
| `npm run lint:js` | Run ESLint on JS/TS files | Before committing |
| `composer phpcs` | Run PHP CodeSniffer | Before committing PHP changes |
| `composer phpcbf` | Auto-fix PHP style issues | After phpcs shows fixable errors |
| `composer test` | Run PHPUnit tests | After PHP changes |
| `npm run test:unit` | Run Jest tests | After React changes |
| `npm run test:e2e` | Run Playwright E2E tests | Before releases |

### Full Command Reference

```bash
# === Development ===
npm run start              # Watch mode with hot reload
npm run build              # Production build
npm run build:dev          # Development build (with source maps)

# === Linting ===
npm run lint:js            # ESLint check
npm run lint:js:fix        # ESLint auto-fix
npm run lint:css           # Stylelint check
composer phpcs             # PHP CodeSniffer check
composer phpcbf            # PHP auto-fix
composer phpstan           # PHPStan static analysis

# === Testing ===
npm run test:unit          # Jest unit tests
npm run test:unit:watch    # Jest watch mode
npm run test:e2e           # Playwright E2E tests
composer test              # PHPUnit tests
composer test:coverage     # PHPUnit with coverage

# === WordPress Environment ===
npx wp-env start           # Start Docker environment
npx wp-env stop            # Stop environment
npx wp-env clean           # Reset environment
npx wp-env logs            # View logs
npx wp-env run cli wp      # Run WP-CLI commands
```

### Build Output

After `npm run build`, assets are output to:

```
build/
├── index.js           # Main React bundle
├── index.asset.php    # Dependencies and version hash
├── index.css          # Compiled styles
└── *.map              # Source maps (dev only)
```

---

## 4. Code Style & Standards

### PHP Standards

**Standard**: WordPress Coding Standards (WPCS 3.0+)

```php
<?php
/**
 * Class file docblock.
 *
 * @package IntentPress
 */

namespace IntentPress;

/**
 * Example class demonstrating coding standards.
 */
class IntentPress_Example_Class {

    /**
     * Property with type hint.
     *
     * @var string
     */
    private string $example_property;

    /**
     * Constructor.
     *
     * @param string $value Initial value.
     */
    public function __construct( string $value ) {
        $this->example_property = $value;
    }

    /**
     * Method demonstrating WordPress style.
     *
     * @param int    $post_id The post ID.
     * @param string $content The content to process.
     * @return array<string, mixed> Processed data.
     */
    public function process_content( int $post_id, string $content ): array {
        // Early return for invalid input.
        if ( empty( $content ) ) {
            return array();
        }

        // Use WordPress functions with proper escaping.
        $title = get_the_title( $post_id );
        
        return array(
            'title'   => esc_html( $title ),
            'content' => wp_kses_post( $content ),
        );
    }
}
```

**Key PHP Rules:**
- Use tabs for indentation (not spaces)
- Opening braces on same line for functions/classes
- Space inside parentheses: `function_name( $param )`
- Yoda conditions: `if ( 'value' === $variable )`
- Array syntax: `array( 'key' => 'value' )` (not `[]` for WP compat)
- Always use strict type comparisons (`===`, `!==`)

### JavaScript/TypeScript Standards

**Standard**: @wordpress/eslint-plugin

```typescript
/**
 * Example React component demonstrating coding standards.
 */
import { useState, useCallback } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

interface SearchFormProps {
    onSearch: ( query: string ) => void;
    isLoading?: boolean;
}

const SearchForm: React.FC< SearchFormProps > = ( { onSearch, isLoading = false } ) => {
    const [ query, setQuery ] = useState< string >( '' );

    const handleSubmit = useCallback( async () => {
        if ( ! query.trim() ) {
            return;
        }
        
        try {
            const response = await apiFetch< SearchResponse >( {
                path: '/intentpress/v1/search',
                method: 'POST',
                data: { query },
            } );
            
            onSearch( response );
        } catch ( error ) {
            console.error( 'Search failed:', error );
        }
    }, [ query, onSearch ] );

    return (
        <div className="intentpress-search-form">
            <TextControl
                label={ __( 'Search Query', 'intentpress' ) }
                value={ query }
                onChange={ setQuery }
                placeholder={ __( 'Enter search terms...', 'intentpress' ) }
            />
            <Button
                variant="primary"
                onClick={ handleSubmit }
                isBusy={ isLoading }
                disabled={ ! query.trim() || isLoading }
            >
                { __( 'Search', 'intentpress' ) }
            </Button>
        </div>
    );
};

export default SearchForm;
```

**Key JS/TS Rules:**
- Use tabs for indentation (WordPress standard)
- Space inside JSX braces: `{ value }`
- Always use `const` or `let`, never `var`
- Prefer `async/await` over `.then()` chains
- Use `@wordpress/i18n` for all user-facing strings
- Use `@wordpress/api-fetch` for REST API calls
- Destructure imports from `@wordpress/*` packages

### CSS Standards

```css
/**
 * IntentPress admin styles
 *
 * Scoped to .intentpress-* classes to avoid conflicts
 */

/* Use BEM-like naming */
.intentpress-settings {
    padding: 20px;
}

.intentpress-settings__header {
    margin-bottom: 20px;
}

.intentpress-settings__title {
    font-size: 1.5em;
}

/* State modifiers */
.intentpress-settings--loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Always scope to prevent conflicts */
.intentpress-admin .components-button {
    /* Override only within our container */
}
```

---

## 5. Testing Workflows

### PHP Testing (PHPUnit)

**Test file location:** `tests/phpunit/`

```php
<?php
/**
 * Test case for Search Handler.
 */

class Test_Search_Handler extends WP_UnitTestCase {

    /**
     * Instance being tested.
     */
    private IntentPress_Search_Handler $handler;

    /**
     * Set up before each test.
     */
    public function set_up(): void {
        parent::set_up();
        $this->handler = new IntentPress_Search_Handler();
    }

    /**
     * Test that search returns results for valid query.
     */
    public function test_search_returns_results(): void {
        // Arrange
        $post_id = $this->factory->post->create( array(
            'post_title'   => 'Healthy Recipes',
            'post_content' => 'Nutritious meal ideas for busy families.',
        ) );

        // Act
        $results = $this->handler->search( 'healthy meals' );

        // Assert
        $this->assertNotEmpty( $results );
        $this->assertContains( $post_id, wp_list_pluck( $results, 'post_id' ) );
    }

    /**
     * Test fallback when API unavailable.
     */
    public function test_fallback_on_api_error(): void {
        // Mock API failure
        add_filter( 'pre_http_request', function() {
            return new WP_Error( 'api_error', 'Connection failed' );
        } );

        $results = $this->handler->search( 'test query' );

        // Should fall back to WordPress search
        $this->assertArrayHasKey( 'fallback_used', $results['meta'] );
        $this->assertTrue( $results['meta']['fallback_used'] );
    }
}
```

**Running PHP tests:**

```bash
# Run all tests
composer test

# Run specific test file
composer test -- --filter Test_Search_Handler

# Run with coverage
composer test:coverage
```

### JavaScript Testing (Jest)

**Test file location:** `src/**/__tests__/` or `src/**/*.test.ts`

```typescript
/**
 * Test suite for SearchForm component.
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import SearchForm from '../SearchForm';

// Mock WordPress packages
jest.mock( '@wordpress/api-fetch' );
jest.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
    sprintf: jest.fn( ( format, ...args ) => format ),
} ) );

describe( 'SearchForm', () => {
    const mockOnSearch = jest.fn();

    beforeEach( () => {
        mockOnSearch.mockClear();
    } );

    it( 'renders search input and button', () => {
        render( <SearchForm onSearch={ mockOnSearch } /> );

        expect( screen.getByLabelText( 'Search Query' ) ).toBeInTheDocument();
        expect( screen.getByRole( 'button', { name: 'Search' } ) ).toBeInTheDocument();
    } );

    it( 'disables button when query is empty', () => {
        render( <SearchForm onSearch={ mockOnSearch } /> );

        const button = screen.getByRole( 'button', { name: 'Search' } );
        expect( button ).toBeDisabled();
    } );

    it( 'calls onSearch with query when submitted', async () => {
        render( <SearchForm onSearch={ mockOnSearch } /> );

        const input = screen.getByLabelText( 'Search Query' );
        fireEvent.change( input, { target: { value: 'test query' } } );

        const button = screen.getByRole( 'button', { name: 'Search' } );
        fireEvent.click( button );

        await waitFor( () => {
            expect( mockOnSearch ).toHaveBeenCalled();
        } );
    } );
} );
```

**Running JS tests:**

```bash
# Run all tests
npm run test:unit

# Watch mode
npm run test:unit:watch

# With coverage
npm run test:unit -- --coverage
```

### E2E Testing (Playwright)

**Test file location:** `tests/e2e/`

```typescript
/**
 * E2E tests for IntentPress settings page.
 */
import { test, expect } from '@playwright/test';
import { Admin } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'IntentPress Settings', () => {
    let admin: Admin;

    test.beforeEach( async ( { page } ) => {
        admin = new Admin( { page } );
        await admin.visitAdminPage( 'options-general.php', 'page=intentpress' );
    } );

    test( 'should display API key input', async ( { page } ) => {
        await expect( page.locator( '[data-testid="api-key-input"]' ) ).toBeVisible();
    } );

    test( 'should validate API key format', async ( { page } ) => {
        const input = page.locator( '[data-testid="api-key-input"]' );
        await input.fill( 'invalid-key' );
        await page.click( '[data-testid="validate-button"]' );

        await expect( page.locator( '.notice-error' ) ).toContainText( 'Invalid format' );
    } );

    test( 'should save settings successfully', async ( { page } ) => {
        const input = page.locator( '[data-testid="api-key-input"]' );
        await input.fill( 'sk-test-key-value' );
        await page.click( '[data-testid="save-button"]' );

        await expect( page.locator( '.notice-success' ) ).toBeVisible();
    } );
} );
```

**Running E2E tests:**

```bash
# Ensure wp-env is running
npx wp-env start

# Run E2E tests
npm run test:e2e

# With UI mode
npm run test:e2e -- --ui
```

---

## 6. Security Checklist

### Before Submitting Any Code

**Input Handling:**
- [ ] All `$_GET`, `$_POST`, `$_REQUEST` data is sanitized
- [ ] User input never used directly in SQL queries
- [ ] File uploads validated for type and size
- [ ] JSON input decoded with error handling

**Output Handling:**
- [ ] All dynamic content escaped before output
- [ ] Correct escaping function used for context (HTML, attribute, URL, JS)
- [ ] No raw user content in JavaScript variables

**Authentication & Authorization:**
- [ ] REST endpoints have `permission_callback`
- [ ] Admin actions check `current_user_can()`
- [ ] Nonces verified for all form submissions
- [ ] AJAX handlers use `check_ajax_referer()`

**Database:**
- [ ] All queries use `$wpdb->prepare()`
- [ ] Table names use `$wpdb->prefix`
- [ ] No direct `$_GET`/`$_POST` in queries

### Security Function Quick Reference

```php
// === INPUT SANITIZATION ===
// Use AS EARLY as possible

$text = sanitize_text_field( $_POST['text'] );
$email = sanitize_email( $_POST['email'] );
$int = absint( $_POST['number'] );
$url = esc_url_raw( $_POST['url'] );  // For database
$html = wp_kses_post( $_POST['content'] );
$key = sanitize_key( $_POST['setting_name'] );
$filename = sanitize_file_name( $_FILES['file']['name'] );

// === OUTPUT ESCAPING ===
// Use AS LATE as possible

echo esc_html( $text );              // In text context
echo esc_attr( $value );             // In attributes
echo esc_url( $url );                // In href/src
echo wp_kses_post( $html );          // Allow safe HTML
echo esc_js( $value );               // In JavaScript
echo esc_textarea( $content );       // In textarea

// === NONCE VERIFICATION ===
// Forms
wp_nonce_field( 'intentpress_save_settings', 'intentpress_nonce' );

// Verification
if ( ! wp_verify_nonce( $_POST['intentpress_nonce'], 'intentpress_save_settings' ) ) {
    wp_die( 'Security check failed' );
}

// AJAX
check_ajax_referer( 'intentpress_ajax_nonce', 'security' );

// === CAPABILITY CHECKS ===
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access' );
}

// === DATABASE QUERIES ===
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}intentpress_embeddings WHERE post_id = %d",
        $post_id
    )
);
```

---

## 7. File Operation Guidelines

### Creating New PHP Files

**Location:** `includes/class-intentpress-*.php`

**Template:**
```php
<?php
/**
 * [Class Description]
 *
 * @package IntentPress
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * [Class Name] class.
 *
 * @since 1.0.0
 */
class IntentPress_Class_Name {
    // Implementation
}
```

### Creating New React Components

**Location:** `src/admin/components/ComponentName/`

**Structure:**
```
ComponentName/
├── index.tsx          # Main component
├── ComponentName.tsx  # Component implementation
├── styles.css         # Component styles
└── __tests__/
    └── ComponentName.test.tsx
```

**Template:**
```typescript
/**
 * ComponentName component.
 *
 * @package IntentPress
 */
import { __ } from '@wordpress/i18n';
import './styles.css';

interface ComponentNameProps {
    // Props definition
}

const ComponentName: React.FC< ComponentNameProps > = ( props ) => {
    return (
        <div className="intentpress-component-name">
            {/* Implementation */}
        </div>
    );
};

export default ComponentName;
```

### Creating New REST Endpoints

**Location:** `includes/class-intentpress-rest-api.php`

**Pattern:**
```php
/**
 * Register new endpoint.
 */
register_rest_route(
    'intentpress/v1',
    '/endpoint-name',
    array(
        'methods'             => WP_REST_Server::READABLE, // or CREATABLE, EDITABLE, etc.
        'callback'            => array( $this, 'handle_endpoint_name' ),
        'permission_callback' => array( $this, 'check_admin_permissions' ),
        'args'                => array(
            'param_name' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $param ) {
                    return ! empty( $param );
                },
            ),
        ),
    )
);
```

---

## 8. Common Patterns

### REST API Call from React

```typescript
import apiFetch from '@wordpress/api-fetch';

interface SearchResponse {
    success: boolean;
    data: {
        results: SearchResult[];
        meta: SearchMeta;
    };
}

const performSearch = async ( query: string ): Promise< SearchResponse > => {
    try {
        const response = await apiFetch< SearchResponse >( {
            path: '/intentpress/v1/search',
            method: 'POST',
            data: { query },
        } );
        return response;
    } catch ( error ) {
        console.error( 'Search error:', error );
        throw error;
    }
};
```

### WordPress Hook Registration

```php
// Action hook
add_action( 'init', array( $this, 'register_post_types' ) );

// Filter hook
add_filter( 'the_content', array( $this, 'filter_content' ), 10, 1 );

// Priority and accepted args
add_filter( 'posts_search', array( $this, 'modify_search' ), 10, 2 );
```

### Option Storage and Retrieval

```php
// Save option (auto-serializes arrays)
update_option( 'intentpress_settings', array(
    'api_key'    => $encrypted_key,
    'post_types' => array( 'post', 'page' ),
), false ); // false = don't autoload

// Get option with default
$settings = get_option( 'intentpress_settings', array(
    'api_key'    => '',
    'post_types' => array( 'post' ),
) );

// Delete option
delete_option( 'intentpress_settings' );
```

### Transient Caching

```php
// Cache for 1 hour
$cache_key = 'intentpress_query_' . md5( $query );
$cached = get_transient( $cache_key );

if ( false === $cached ) {
    $result = $this->expensive_operation( $query );
    set_transient( $cache_key, $result, HOUR_IN_SECONDS );
    return $result;
}

return $cached;
```

### React State with React Query

```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiFetch from '@wordpress/api-fetch';

// Fetch settings
const useSettings = () => {
    return useQuery( {
        queryKey: [ 'intentpress-settings' ],
        queryFn: () => apiFetch( { path: '/intentpress/v1/settings' } ),
    } );
};

// Save settings
const useSaveSettings = () => {
    const queryClient = useQueryClient();
    
    return useMutation( {
        mutationFn: ( settings: Settings ) => apiFetch( {
            path: '/intentpress/v1/settings',
            method: 'POST',
            data: settings,
        } ),
        onSuccess: () => {
            queryClient.invalidateQueries( { queryKey: [ 'intentpress-settings' ] } );
        },
    } );
};
```

### Shortcode Registration

```php
// Register shortcode
add_shortcode( 'intentpress_search', array( $this, 'search_form_shortcode' ) );

// Shortcode handler with attributes
public function search_form_shortcode( array $atts = array() ): string {
    $atts = shortcode_atts(
        array(
            'placeholder' => __( 'Search...', 'intentpress' ),
            'button_text' => __( 'Search', 'intentpress' ),
            'class'       => '',
        ),
        $atts,
        'intentpress_search'
    );

    ob_start();
    ?>
    <form class="intentpress-search-form <?php echo esc_attr( $atts['class'] ); ?>">
        <input type="search" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" />
        <button type="submit"><?php echo esc_html( $atts['button_text'] ); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
```

### Template Tags

```php
// Define template tag function (global scope)
function intentpress_is_semantic_search(): bool {
    global $intentpress_search_integration;
    return $intentpress_search_integration instanceof IntentPress_Search_Integration
        && $intentpress_search_integration->is_semantic_search();
}

// Usage in theme template
if ( intentpress_is_semantic_search() ) {
    echo '<span class="badge">AI-Powered</span>';
}
```

---

## 9. Error Handling Patterns

### PHP Error Handling

```php
/**
 * Handle API request with error handling.
 */
public function make_api_request( string $endpoint, array $data ): array|WP_Error {
    $response = wp_remote_post(
        'https://api.openai.com/v1/' . $endpoint,
        array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $data ),
        )
    );

    // Check for WP error
    if ( is_wp_error( $response ) ) {
        $this->log_error( 'API request failed: ' . $response->get_error_message() );
        return $response;
    }

    // Check HTTP status
    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        $error_code = $this->get_error_code_for_status( $status_code );
        return new WP_Error(
            $error_code,
            $this->get_error_message( $error_code ),
            array( 'status' => $status_code )
        );
    }

    // Parse response
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_Error(
            'intentpress_json_error',
            __( 'Invalid response from API', 'intentpress' )
        );
    }

    return $data;
}
```

### React Error Handling

```typescript
import { useState, useCallback } from '@wordpress/element';
import { Notice } from '@wordpress/components';

interface ErrorState {
    message: string;
    code?: string;
}

const SettingsForm: React.FC = () => {
    const [ error, setError ] = useState< ErrorState | null >( null );
    const [ isLoading, setIsLoading ] = useState( false );

    const handleSave = useCallback( async () => {
        setError( null );
        setIsLoading( true );

        try {
            await apiFetch( {
                path: '/intentpress/v1/settings',
                method: 'POST',
                data: settings,
            } );
        } catch ( err ) {
            const errorMessage = err instanceof Error 
                ? err.message 
                : __( 'An unexpected error occurred', 'intentpress' );
            
            setError( { message: errorMessage } );
        } finally {
            setIsLoading( false );
        }
    }, [ settings ] );

    return (
        <>
            { error && (
                <Notice status="error" onRemove={ () => setError( null ) }>
                    { error.message }
                </Notice>
            ) }
            {/* Form content */}
        </>
    );
};
```

---

## 10. Task Execution Protocol

### Before Starting Any Task

1. **Read CLAUDE.md** - Always review quick reference first
2. **Check task acceptance criteria** - Understand what "done" means
3. **Identify affected files** - Know what you'll be modifying
4. **Run existing tests** - Ensure baseline is passing
5. **Check for dependencies** - Are other tasks required first?

### During Task Execution

1. **Work incrementally** - Small, testable changes
2. **Run linters frequently** - Catch issues early
3. **Test as you go** - Don't wait until the end
4. **Document decisions** - Add comments for non-obvious code
5. **Ask for clarification** - If requirements are ambiguous

### After Completing Task

1. **Run all linters**
   ```bash
   npm run lint:js
   composer phpcs
   ```

2. **Run relevant tests**
   ```bash
   npm run test:unit
   composer test
   ```

3. **Verify security checklist** - Review section 6

4. **Update documentation** - If behavior changed

5. **Report completion** - Include:
   - Files modified
   - Tests added/updated
   - Any remaining concerns

### Task Status Format

When reporting task completion:

```markdown
## Task: [Task ID] - [Task Name]

### Status: Complete ✓

### Changes Made:
- `includes/class-intentpress-search-handler.php` - Added fallback logic
- `src/admin/components/SearchTest/SearchTest.tsx` - New component
- `tests/phpunit/test-search-handler.php` - Added fallback tests

### Tests:
- ✓ All existing tests passing
- ✓ 3 new tests added for fallback behavior

### Notes:
- Fallback triggers after 3 retries (configurable via filter)
- Consider adding admin notice when fallback is active (future task)

### Next Steps:
- Task 2.3 can now proceed (depends on this)
```

---

## 11. Context Management

### Session Continuity

At the end of each coding session, create a handoff note:

```markdown
## Session Handoff - [Date]

### Completed This Session:
- [List of completed tasks]

### In Progress:
- [Task ID]: [Current state, next steps]

### Files Modified (uncommitted):
- `path/to/file.php` - [brief description]

### Known Issues:
- [Any bugs or concerns discovered]

### Next Session Should:
1. [Priority action 1]
2. [Priority action 2]
```

### Context Window Management

- **Keep CLAUDE.md under 200 lines** - Core instructions only
- **Use external files for details** - Reference FRDs, PRDs
- **Clear context between phases** - Research → Planning → Implementation
- **Don't paste large files** - Read them with tools, summarize

### When Context Gets Long

1. Summarize completed work
2. Clear conversation if possible
3. Reload only essential context:
   - CLAUDE.md
   - Current task requirements
   - Relevant code sections

---

## 12. Do NOT List

### Code Quality

- ❌ Skip linting before commits
- ❌ Ignore TypeScript errors
- ❌ Use `any` type without justification
- ❌ Leave `console.log` in production code
- ❌ Write code without tests

### Security

- ❌ Store secrets in plain text
- ❌ Use `eval()` or `create_function()`
- ❌ Trust user input without validation
- ❌ Output unescaped content
- ❌ Skip nonce verification
- ❌ Use direct database queries without prepare()

### WordPress Conventions

- ❌ Hardcode text strings (use `__()`)
- ❌ Use short array syntax `[]` (use `array()`)
- ❌ Skip docblocks on functions/classes
- ❌ Use global variables without prefix
- ❌ Hook into `init` when `plugins_loaded` is appropriate

### React/JavaScript

- ❌ Use `var` (use `const`/`let`)
- ❌ Import React directly (use `@wordpress/element`)
- ❌ Inline styles for anything substantial
- ❌ Ignore accessibility (use proper labels, ARIA)
- ❌ Store sensitive data in localStorage

### Project Structure

- ❌ Put PHP in src/ directory
- ❌ Put React source in includes/
- ❌ Edit build/ directory manually
- ❌ Commit node_modules or vendor/
- ❌ Create files without proper headers

### Communication

- ❌ Make assumptions about unclear requirements
- ❌ Skip reporting task completion status
- ❌ Hide errors or failed tests
- ❌ Claim completion without testing
- ❌ Proceed without understanding acceptance criteria

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | January 2026 | Initial release |
