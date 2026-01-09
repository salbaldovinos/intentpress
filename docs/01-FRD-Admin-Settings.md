# Functional Requirements Document: Admin Settings & Configuration
## IntentPress MVP

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related PRDs:** PRD-Overview.md, PRD-User-Stories.md  
**Related User Stories:** US-201, US-202, US-203, US-204, US-205, US-502

---

## Table of Contents

1. [Overview](#1-overview)
2. [Settings Architecture](#2-settings-architecture)
3. [API Key Management](#3-api-key-management)
4. [Post Type Configuration](#4-post-type-configuration)
5. [Search Behavior Settings](#5-search-behavior-settings)
6. [Dashboard Components](#6-dashboard-components)
7. [Health Check System](#7-health-check-system)
8. [REST API Endpoints](#8-rest-api-endpoints)
9. [React Component Specifications](#9-react-component-specifications)
10. [Access Control](#10-access-control)
11. [Data Persistence](#11-data-persistence)
12. [Validation Rules](#12-validation-rules)

---

## 1. Overview

### 1.1 Purpose

This document specifies the functional requirements for IntentPress's admin settings interface, including the React-based dashboard, configuration options, and health monitoring systems.

### 1.2 Scope

| In Scope | Out of Scope |
|----------|--------------|
| Settings page UI (React) | User-facing search UI customization |
| API key management | Billing/subscription management |
| Post type selection | Multi-site network settings |
| Search behavior settings | White-label configuration |
| Health check dashboard | Advanced analytics |
| Test search tool | A/B testing interface |

### 1.3 User Interface Location

**Menu Location:** Settings → IntentPress  
**Menu Capability:** `manage_options`  
**Menu Icon:** Dashicons search icon (`dashicons-search`)

---

## 2. Settings Architecture

### 2.1 Component Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     ADMIN SETTINGS ARCHITECTURE                          │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                           WordPress Admin                                 │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │                    React Application Container                      │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌───────────┐  │  │
│  │  │   Header    │  │   Tabs      │  │   Content   │  │  Notices  │  │  │
│  │  │  Component  │  │  Navigation │  │   Panels    │  │  Area     │  │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └───────────┘  │  │
│  │                                                                     │  │
│  │  ┌─────────────────────────────────────────────────────────────┐   │  │
│  │  │                      Tab Content                              │   │  │
│  │  │  ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌───────────┐ │   │  │
│  │  │  │ Dashboard │  │ Settings  │  │ Indexing  │  │ Analytics │ │   │  │
│  │  │  │   Tab     │  │   Tab     │  │   Tab     │  │   Tab     │ │   │  │
│  │  │  └───────────┘  └───────────┘  └───────────┘  └───────────┘ │   │  │
│  │  └─────────────────────────────────────────────────────────────┘   │  │
│  └────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
         │                                                        ▲
         │ REST API Calls                                         │
         ▼                                                        │
┌──────────────────────────────────────────────────────────────────────────┐
│                         REST API Layer                                    │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐  │
│  │ /settings       │  │ /index          │  │ /health                 │  │
│  │ GET, POST       │  │ POST, GET       │  │ GET                     │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
         │                                                        ▲
         │                                                        │
         ▼                                                        │
┌──────────────────────────────────────────────────────────────────────────┐
│                         Data Layer                                        │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐  │
│  │ wp_options      │  │ Custom Tables   │  │ Transients              │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Tab Structure

| Tab | Purpose | Components |
|-----|---------|------------|
| Dashboard | Overview and quick actions | StatusCards, QuickActions, RecentSearches |
| Settings | Configuration options | APIKeyInput, PostTypeSelector, BehaviorSettings |
| Indexing | Index management | IndexProgress, IndexActions, IndexLog |
| Analytics | Search analytics | SearchStats, TopQueries, NoResultsQueries |

### 2.3 State Management

**Using React Query + useState:**

```typescript
// Global settings state
interface SettingsState {
    apiKey: string;
    apiKeyValid: boolean;
    postTypes: string[];
    perPage: number;
    fallbackEnabled: boolean;
    isLoading: boolean;
    error: string | null;
}

// Index status state
interface IndexState {
    status: 'idle' | 'indexing' | 'complete' | 'error';
    progress: number;
    total: number;
    indexed: number;
    lastIndexAt: string | null;
    errors: IndexError[];
}
```

---

## 3. API Key Management

### 3.1 API Key Input Component

**Component:** `APIKeyInput.tsx`

**States:**

| State | UI Display | Actions Available |
|-------|------------|-------------------|
| `empty` | Empty input field | Enter key |
| `validating` | Spinner, disabled input | Wait |
| `valid` | Masked key, green checkmark | Clear, re-enter |
| `invalid` | Error message, red border | Re-enter |
| `error` | Network error message | Retry |

**Visual Mockup:**

```
┌────────────────────────────────────────────────────────────────────────┐
│ API Configuration                                                       │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  OpenAI API Key                                                        │
│  ┌────────────────────────────────────────────────┐  ┌──────────────┐ │
│  │ sk-...                                         │  │   Validate   │ │
│  └────────────────────────────────────────────────┘  └──────────────┘ │
│                                                                        │
│  ✓ API key is valid and connected                     [Clear Key]     │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │ 🔒 Your API key is encrypted and stored securely in the database │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  Need an API key? Get one at platform.openai.com/api-keys →           │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 3.2 API Key Validation Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        API KEY VALIDATION FLOW                           │
└─────────────────────────────────────────────────────────────────────────┘

START
  │
  ▼
┌─────────────────────┐
│ User enters API key │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐     ┌─────────────────────┐
│ Format validation   │────▶│ Show format error   │
│ (starts with sk-)   │ NO  │ "Invalid format"    │
└──────────┬──────────┘     └─────────────────────┘
           │ YES
           ▼
┌─────────────────────┐
│ Show loading state  │
│ "Validating..."     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Call OpenAI API     │
│ (test embedding)    │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐ ┌─────────────┐
│ Success │ │   Error     │
└────┬────┘ └──────┬──────┘
     │             │
     ▼             ▼
┌─────────────────────┐  ┌─────────────────────┐
│ Encrypt & save key  │  │ Show error message  │
│ Show success state  │  │ Clear input         │
└─────────────────────┘  └─────────────────────┘
```

### 3.3 API Key Encryption

**Encryption Method:** AES-256-CBC using WordPress AUTH_KEY

```php
/**
 * Encrypt API key for storage
 * 
 * @param string $api_key Plain text API key
 * @return string Encrypted and base64 encoded key
 */
public function encrypt_api_key( string $api_key ): string {
    $key = hash( 'sha256', AUTH_KEY, true );
    $iv = openssl_random_pseudo_bytes( 16 );
    
    $encrypted = openssl_encrypt(
        $api_key,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    return base64_encode( $iv . $encrypted );
}

/**
 * Decrypt API key from storage
 * 
 * @param string $encrypted_key Encrypted and base64 encoded key
 * @return string|false Plain text API key or false on failure
 */
public function decrypt_api_key( string $encrypted_key ): string|false {
    $key = hash( 'sha256', AUTH_KEY, true );
    $data = base64_decode( $encrypted_key );
    
    $iv = substr( $data, 0, 16 );
    $encrypted = substr( $data, 16 );
    
    return openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
}
```

### 3.4 API Key Display Masking

```typescript
/**
 * Mask API key for display
 * Shows only last 4 characters
 */
function maskApiKey(key: string): string {
    if (key.length < 8) return '••••••••';
    const lastFour = key.slice(-4);
    return '••••••••••••••••••••' + lastFour;
}

// Example: "sk-abc123xyz789" → "••••••••••••••••••••789"
```

### 3.5 API Key Validation Errors

| Error Code | HTTP | User Message |
|------------|------|--------------|
| `invalid_format` | N/A | This doesn't look like a valid OpenAI API key. Keys start with 'sk-'. |
| `invalid_key` | 401 | This API key is invalid. Please check it and try again. |
| `network_error` | N/A | Couldn't connect to OpenAI. Please check your connection. |
| `rate_limited` | 429 | OpenAI rate limit reached. Please wait a moment and try again. |

---

## 4. Post Type Configuration

### 4.1 Post Type Selector Component

**Component:** `PostTypeSelector.tsx`

**Visual Mockup:**

```
┌────────────────────────────────────────────────────────────────────────┐
│ Content Types                                                           │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Select which content types to include in semantic search:             │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │ ☑ Posts (234 published)                                          │ │
│  │ ☑ Pages (12 published)                                           │ │
│  │ ☐ Products (156 published) — Requires WooCommerce                │ │
│  │ ☐ Documentation (45 published)                                   │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  ℹ️ Changes require re-indexing to take effect.                        │
│                                                                        │
│  Total content to index: 246 items                                     │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Post Type Discovery

```php
/**
 * Get all indexable post types
 * 
 * @return array Array of post type objects with counts
 */
public function get_indexable_post_types(): array {
    $post_types = get_post_types( [
        'public' => true,
    ], 'objects' );
    
    $indexable = [];
    
    foreach ( $post_types as $post_type ) {
        // Skip attachments
        if ( $post_type->name === 'attachment' ) {
            continue;
        }
        
        $count = wp_count_posts( $post_type->name );
        
        $indexable[] = [
            'name' => $post_type->name,
            'label' => $post_type->labels->name,
            'singular_label' => $post_type->labels->singular_name,
            'count' => (int) $count->publish,
            'icon' => $post_type->menu_icon ?? 'dashicons-admin-post',
        ];
    }
    
    return $indexable;
}
```

### 4.3 Post Type Selection Rules

| Rule | Behavior |
|------|----------|
| Default selection | Posts and Pages selected on fresh install |
| Minimum selection | At least one post type must be selected |
| Re-index notice | Show notice when selection changes |
| Count display | Show published post count per type |
| Disabled types | Gray out types with 0 published posts |

### 4.4 Selection Change Handling

```typescript
interface PostTypeChangeResult {
    success: boolean;
    requiresReindex: boolean;
    previousTypes: string[];
    newTypes: string[];
    affectedPosts: number;
}

async function handlePostTypeChange(
    newTypes: string[]
): Promise<PostTypeChangeResult> {
    // 1. Validate at least one type selected
    if (newTypes.length === 0) {
        throw new Error('At least one content type must be selected');
    }
    
    // 2. Compare with current selection
    const currentTypes = await getCurrentPostTypes();
    const added = newTypes.filter(t => !currentTypes.includes(t));
    const removed = currentTypes.filter(t => !newTypes.includes(t));
    
    // 3. Save new selection
    await savePostTypes(newTypes);
    
    // 4. Return result with re-index requirement
    return {
        success: true,
        requiresReindex: added.length > 0 || removed.length > 0,
        previousTypes: currentTypes,
        newTypes: newTypes,
        affectedPosts: await countAffectedPosts(added, removed),
    };
}
```

---

## 5. Search Behavior Settings

### 5.1 Settings Panel Component

**Component:** `BehaviorSettings.tsx`

**Visual Mockup:**

```
┌────────────────────────────────────────────────────────────────────────┐
│ Search Behavior                                                         │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Results per Page                                                      │
│  ┌──────────┐                                                          │
│  │    10    │ ◄───────────────────────────────────────────►            │
│  └──────────┘                                                          │
│  Number of results to show per page (1-50)                             │
│                                                                        │
│  ────────────────────────────────────────────────────────────────────  │
│                                                                        │
│  Similarity Threshold                                                  │
│  ┌──────────┐                                                          │
│  │   0.50   │ ◄───────────────────────────────────────────►            │
│  └──────────┘                                                          │
│  Minimum relevance score to show results (0.3-0.9)                     │
│  Lower = more results, Higher = stricter matching                      │
│                                                                        │
│  ────────────────────────────────────────────────────────────────────  │
│                                                                        │
│  Fallback Search                                                       │
│  [●] Enable fallback to WordPress default search                       │
│                                                                        │
│  When enabled, standard WordPress search will be used if the           │
│  AI service is unavailable.                                            │
│                                                                        │
│  ────────────────────────────────────────────────────────────────────  │
│                                                                        │
│  Search Logging                                                        │
│  [●] Log search queries for analytics                                  │
│                                                                        │
│  When enabled, search queries are logged (without personal data)       │
│  to power the analytics dashboard.                                     │
│                                                                        │
│           ┌────────────────┐                                           │
│           │  Save Changes  │                                           │
│           └────────────────┘                                           │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Settings Schema

```typescript
interface BehaviorSettings {
    perPage: {
        value: number;
        min: 1;
        max: 50;
        default: 10;
        label: string;
        description: string;
    };
    similarityThreshold: {
        value: number;
        min: 0.3;
        max: 0.9;
        step: 0.05;
        default: 0.5;
        label: string;
        description: string;
    };
    fallbackEnabled: {
        value: boolean;
        default: true;
        label: string;
        description: string;
    };
    loggingEnabled: {
        value: boolean;
        default: true;
        label: string;
        description: string;
    };
}
```

### 5.3 Settings Validation

| Setting | Validation | Error Message |
|---------|------------|---------------|
| `perPage` | Integer, 1-50 | Results per page must be between 1 and 50 |
| `similarityThreshold` | Float, 0.3-0.9 | Threshold must be between 0.3 and 0.9 |
| `fallbackEnabled` | Boolean | N/A |
| `loggingEnabled` | Boolean | N/A |

### 5.4 Settings Save Flow

```typescript
async function saveSettings(settings: Partial<BehaviorSettings>): Promise<SaveResult> {
    // 1. Validate all settings
    const validationErrors = validateSettings(settings);
    if (validationErrors.length > 0) {
        return { success: false, errors: validationErrors };
    }
    
    // 2. Call REST API
    const response = await apiFetch({
        path: '/intentpress/v1/settings',
        method: 'POST',
        data: settings,
    });
    
    // 3. Show success notification
    if (response.success) {
        showNotice('Settings saved successfully', 'success');
    }
    
    return response;
}
```

---

## 6. Dashboard Components

### 6.1 Dashboard Overview

**Component:** `Dashboard.tsx`

**Visual Mockup:**

```
┌────────────────────────────────────────────────────────────────────────┐
│ IntentPress Dashboard                                                   │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐     │
│  │ ✓ API Connected  │  │ 📊 500 Indexed   │  │ 🔍 1,234 Searches │     │
│  │                  │  │                  │  │    this month    │     │
│  │ Last verified:   │  │ Last indexed:    │  │                  │     │
│  │ 5 minutes ago    │  │ 2 hours ago      │  │ 234 remaining    │     │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘     │
│                                                                        │
│  ────────────────────────────────────────────────────────────────────  │
│                                                                        │
│  System Status                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │ ✓ API Connection       Connected (245ms latency)                 │ │
│  │ ✓ Database Tables      Healthy                                   │ │
│  │ ✓ Content Index        500 of 500 posts indexed                  │ │
│  │ ⚠ Index Freshness      Re-index recommended (15 new posts)       │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  Quick Actions                                                         │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐          │
│  │ Re-index Now   │  │ Test Search    │  │ View Analytics │          │
│  └────────────────┘  └────────────────┘  └────────────────┘          │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 6.2 Status Card Component

**Component:** `StatusCard.tsx`

```typescript
interface StatusCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    status: 'success' | 'warning' | 'error' | 'info';
    icon: string;
    action?: {
        label: string;
        onClick: () => void;
    };
}

function StatusCard({ title, value, subtitle, status, icon, action }: StatusCardProps) {
    return (
        <Card className={`status-card status-${status}`}>
            <CardHeader>
                <Icon icon={icon} />
                <h3>{title}</h3>
            </CardHeader>
            <CardBody>
                <div className="status-value">{value}</div>
                {subtitle && <div className="status-subtitle">{subtitle}</div>}
            </CardBody>
            {action && (
                <CardFooter>
                    <Button variant="secondary" onClick={action.onClick}>
                        {action.label}
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
}
```

### 6.3 Status Indicators

| Indicator | Condition | Color | Icon |
|-----------|-----------|-------|------|
| API Connected | Valid key, recent successful call | Green | ✓ |
| API Error | Invalid key or connection failed | Red | ✗ |
| Index Current | All published posts indexed | Green | ✓ |
| Index Stale | New posts since last index | Yellow | ⚠ |
| Index Missing | No index exists | Red | ✗ |
| Searches OK | Under 80% of limit | Green | ✓ |
| Searches Warning | 80-99% of limit | Yellow | ⚠ |
| Searches Limited | At 100% of limit | Red | ✗ |

---

## 7. Health Check System

### 7.1 Health Check Components

| Check | What It Tests | Pass Criteria |
|-------|--------------|---------------|
| `api_connection` | OpenAI API reachability | Response within 5s |
| `api_key_valid` | API key authentication | 200 response on test call |
| `database_tables` | Custom tables exist | Tables present and writable |
| `index_exists` | Embeddings table has data | At least 1 embedding |
| `index_current` | Index matches published posts | Within 10% of total |
| `php_version` | PHP 8.0+ | `version_compare` passes |
| `wp_version` | WordPress 6.4+ | `version_compare` passes |
| `rest_api` | REST API accessible | Test endpoint responds |

### 7.2 Health Check Response Format

```php
/**
 * Health check response structure
 */
[
    'status' => 'healthy', // healthy, degraded, unhealthy
    'timestamp' => '2026-01-08T15:30:00Z',
    'checks' => [
        'api_connection' => [
            'status' => 'pass',
            'latency_ms' => 245,
            'message' => 'Connected successfully',
        ],
        'api_key_valid' => [
            'status' => 'pass',
            'message' => 'API key is valid',
        ],
        'database_tables' => [
            'status' => 'pass',
            'message' => 'All tables exist',
        ],
        'index_exists' => [
            'status' => 'pass',
            'posts_indexed' => 500,
            'message' => '500 posts indexed',
        ],
        'index_current' => [
            'status' => 'warn',
            'posts_total' => 515,
            'posts_indexed' => 500,
            'message' => '15 posts need indexing',
        ],
    ],
    'summary' => [
        'passed' => 4,
        'warned' => 1,
        'failed' => 0,
    ],
]
```

### 7.3 Health Check UI Component

**Component:** `HealthCheck.tsx`

```typescript
interface HealthCheckItemProps {
    name: string;
    status: 'pass' | 'warn' | 'fail';
    message: string;
    details?: Record<string, unknown>;
}

function HealthCheckItem({ name, status, message, details }: HealthCheckItemProps) {
    const icons = {
        pass: '✓',
        warn: '⚠',
        fail: '✗',
    };
    
    const colors = {
        pass: 'green',
        warn: 'yellow', 
        fail: 'red',
    };
    
    return (
        <div className={`health-check-item status-${status}`}>
            <span className="health-icon" style={{ color: colors[status] }}>
                {icons[status]}
            </span>
            <span className="health-name">{name}</span>
            <span className="health-message">{message}</span>
            {details && (
                <Button variant="link" onClick={() => showDetails(details)}>
                    Details
                </Button>
            )}
        </div>
    );
}
```

### 7.4 Automatic Health Checks

| Trigger | Checks Run | Notification |
|---------|------------|--------------|
| Settings page load | All checks | In-page display |
| Every 6 hours (cron) | Critical checks only | Admin notice if failed |
| After settings save | Relevant checks | Toast notification |
| Manual "Run Check" | All checks | In-page display |

---

## 8. REST API Endpoints

### 8.1 Settings Endpoints

#### GET /wp-json/intentpress/v1/settings

**Purpose:** Retrieve all settings

**Authentication:** `manage_options` capability required

**Response:**

```json
{
    "api_key_configured": true,
    "api_key_valid": true,
    "api_key_masked": "••••••••••••••••••••abc1",
    "post_types": ["post", "page"],
    "per_page": 10,
    "similarity_threshold": 0.5,
    "fallback_enabled": true,
    "logging_enabled": true,
    "monthly_searches": 750,
    "search_limit": 1000,
    "indexed_count": 500,
    "total_posts": 515
}
```

#### POST /wp-json/intentpress/v1/settings

**Purpose:** Update settings

**Authentication:** `manage_options` capability required

**Request:**

```json
{
    "api_key": "sk-...",
    "post_types": ["post", "page", "documentation"],
    "per_page": 15,
    "similarity_threshold": 0.6,
    "fallback_enabled": true,
    "logging_enabled": true
}
```

**Response:**

```json
{
    "success": true,
    "message": "Settings saved successfully",
    "requires_reindex": true
}
```

### 8.2 Validation Endpoint

#### POST /wp-json/intentpress/v1/settings/validate-key

**Purpose:** Validate API key without saving

**Request:**

```json
{
    "api_key": "sk-..."
}
```

**Response (Success):**

```json
{
    "valid": true,
    "message": "API key is valid"
}
```

**Response (Failure):**

```json
{
    "valid": false,
    "error": "invalid_key",
    "message": "This API key is invalid"
}
```

### 8.3 Health Check Endpoint

#### GET /wp-json/intentpress/v1/health

**Purpose:** Run health checks

**Authentication:** `manage_options` capability required

**Response:** See Section 7.2

### 8.4 Post Types Endpoint

#### GET /wp-json/intentpress/v1/post-types

**Purpose:** Get available post types for configuration

**Response:**

```json
{
    "post_types": [
        {
            "name": "post",
            "label": "Posts",
            "singular_label": "Post",
            "count": 234,
            "selected": true
        },
        {
            "name": "page",
            "label": "Pages",
            "singular_label": "Page",
            "count": 12,
            "selected": true
        },
        {
            "name": "documentation",
            "label": "Documentation",
            "singular_label": "Doc",
            "count": 45,
            "selected": false
        }
    ],
    "total_selected": 246
}
```

---

## 9. React Component Specifications

### 9.1 Component Hierarchy

```
App
├── Header
│   ├── Logo
│   └── Version
├── TabNavigation
│   ├── Tab (Dashboard)
│   ├── Tab (Settings)
│   ├── Tab (Indexing)
│   └── Tab (Analytics)
├── NoticeArea
│   └── Notice[]
└── TabContent
    ├── DashboardTab
    │   ├── StatusCards
    │   ├── HealthCheck
    │   └── QuickActions
    ├── SettingsTab
    │   ├── APIKeyInput
    │   ├── PostTypeSelector
    │   └── BehaviorSettings
    ├── IndexingTab
    │   ├── IndexStatus
    │   ├── IndexProgress
    │   └── IndexActions
    └── AnalyticsTab
        ├── SearchStats
        ├── TopQueries
        └── NoResultsQueries
```

### 9.2 Shared Component Props

```typescript
// Common button props
interface ActionButtonProps {
    label: string;
    onClick: () => void | Promise<void>;
    variant: 'primary' | 'secondary' | 'tertiary';
    disabled?: boolean;
    loading?: boolean;
    icon?: string;
}

// Common input props
interface SettingInputProps<T> {
    label: string;
    value: T;
    onChange: (value: T) => void;
    description?: string;
    error?: string;
    disabled?: boolean;
}

// Common card props
interface CardProps {
    title: string;
    children: React.ReactNode;
    actions?: ActionButtonProps[];
    status?: 'default' | 'success' | 'warning' | 'error';
}
```

### 9.3 WordPress Component Usage

| Component | WordPress Package | Usage |
|-----------|-------------------|-------|
| `Button` | `@wordpress/components` | All buttons |
| `TextControl` | `@wordpress/components` | Text inputs |
| `CheckboxControl` | `@wordpress/components` | Checkboxes |
| `RangeControl` | `@wordpress/components` | Sliders |
| `Notice` | `@wordpress/components` | Notifications |
| `Spinner` | `@wordpress/components` | Loading states |
| `Card`, `CardBody` | `@wordpress/components` | Card layouts |
| `TabPanel` | `@wordpress/components` | Tab navigation |

### 9.4 API Fetch Hook

```typescript
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

interface UseSettingsResult {
    settings: Settings | null;
    isLoading: boolean;
    error: Error | null;
    saveSettings: (updates: Partial<Settings>) => Promise<void>;
    refetch: () => void;
}

function useSettings(): UseSettingsResult {
    const [settings, setSettings] = useState<Settings | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<Error | null>(null);
    
    const fetchSettings = async () => {
        setIsLoading(true);
        try {
            const response = await apiFetch<Settings>({
                path: '/intentpress/v1/settings',
            });
            setSettings(response);
            setError(null);
        } catch (e) {
            setError(e as Error);
        } finally {
            setIsLoading(false);
        }
    };
    
    useEffect(() => {
        fetchSettings();
    }, []);
    
    const saveSettings = async (updates: Partial<Settings>) => {
        setIsLoading(true);
        try {
            await apiFetch({
                path: '/intentpress/v1/settings',
                method: 'POST',
                data: updates,
            });
            await fetchSettings();
        } catch (e) {
            setError(e as Error);
            throw e;
        }
    };
    
    return {
        settings,
        isLoading,
        error,
        saveSettings,
        refetch: fetchSettings,
    };
}
```

---

## 10. Access Control

### 10.1 Capability Requirements

| Action | Required Capability | Fallback |
|--------|---------------------|----------|
| View settings page | `manage_options` | 403 error |
| Save settings | `manage_options` | 403 error |
| Trigger indexing | `manage_options` | 403 error |
| View analytics | `manage_options` | 403 error |
| Test search (admin) | `manage_options` | 403 error |

### 10.2 REST API Permission Callbacks

```php
/**
 * Permission callback for admin-only endpoints
 */
public function check_admin_permission(): bool {
    return current_user_can( 'manage_options' );
}

/**
 * Register REST routes with permission callbacks
 */
public function register_routes(): void {
    register_rest_route( 'intentpress/v1', '/settings', [
        [
            'methods' => 'GET',
            'callback' => [ $this, 'get_settings' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ],
        [
            'methods' => 'POST',
            'callback' => [ $this, 'update_settings' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ],
    ]);
}
```

### 10.3 Custom Capability Filter

```php
/**
 * Filter to customize required capability
 * Allows site owners to delegate settings management
 */
$capability = apply_filters( 'intentpress_settings_capability', 'manage_options' );
```

---

## 11. Data Persistence

### 11.1 Options Storage

| Option Key | Type | Default | Autoload |
|------------|------|---------|----------|
| `intentpress_api_key` | string (encrypted) | `''` | yes |
| `intentpress_api_key_valid` | bool | `false` | yes |
| `intentpress_indexed_post_types` | array | `['post', 'page']` | yes |
| `intentpress_per_page` | int | `10` | yes |
| `intentpress_similarity_threshold` | float | `0.5` | yes |
| `intentpress_fallback_enabled` | bool | `true` | yes |
| `intentpress_logging_enabled` | bool | `true` | yes |
| `intentpress_monthly_searches` | int | `0` | yes |
| `intentpress_search_limit` | int | `1000` | yes |
| `intentpress_last_index_at` | string | `null` | yes |
| `intentpress_indexed_count` | int | `0` | yes |
| `intentpress_onboarding_complete` | bool | `false` | yes |
| `intentpress_delete_on_uninstall` | bool | `false` | no |

### 11.2 Settings Save Function

```php
/**
 * Save settings with validation
 * 
 * @param array $settings Settings to save
 * @return array Result with success status and any errors
 */
public function save_settings( array $settings ): array {
    $errors = [];
    $saved = [];
    
    // Validate and save API key
    if ( isset( $settings['api_key'] ) ) {
        if ( ! $this->validate_api_key_format( $settings['api_key'] ) ) {
            $errors['api_key'] = 'Invalid API key format';
        } else {
            $encrypted = $this->encrypt_api_key( $settings['api_key'] );
            update_option( 'intentpress_api_key', $encrypted );
            $saved['api_key'] = true;
        }
    }
    
    // Validate and save post types
    if ( isset( $settings['post_types'] ) ) {
        if ( empty( $settings['post_types'] ) ) {
            $errors['post_types'] = 'At least one post type required';
        } else {
            $valid_types = array_filter( $settings['post_types'], function( $type ) {
                return post_type_exists( $type );
            });
            update_option( 'intentpress_indexed_post_types', $valid_types );
            $saved['post_types'] = true;
        }
    }
    
    // Validate and save per_page
    if ( isset( $settings['per_page'] ) ) {
        $per_page = absint( $settings['per_page'] );
        if ( $per_page < 1 || $per_page > 50 ) {
            $errors['per_page'] = 'Results per page must be 1-50';
        } else {
            update_option( 'intentpress_per_page', $per_page );
            $saved['per_page'] = true;
        }
    }
    
    // Save boolean settings
    foreach ( ['fallback_enabled', 'logging_enabled'] as $bool_setting ) {
        if ( isset( $settings[ $bool_setting ] ) ) {
            update_option( 
                'intentpress_' . $bool_setting, 
                (bool) $settings[ $bool_setting ] 
            );
            $saved[ $bool_setting ] = true;
        }
    }
    
    return [
        'success' => empty( $errors ),
        'saved' => $saved,
        'errors' => $errors,
    ];
}
```

---

## 12. Validation Rules

### 12.1 Input Validation Matrix

| Field | Type | Constraints | Sanitization |
|-------|------|-------------|--------------|
| `api_key` | string | Starts with 'sk-', 20+ chars | `sanitize_text_field` |
| `post_types` | array | Non-empty, valid post types | `array_map('sanitize_key')` |
| `per_page` | int | 1-50 | `absint` |
| `similarity_threshold` | float | 0.3-0.9 | `floatval` + bounds |
| `fallback_enabled` | bool | true/false | `boolval` |
| `logging_enabled` | bool | true/false | `boolval` |

### 12.2 Validation Functions

```php
/**
 * Validate API key format
 */
public function validate_api_key_format( string $key ): bool {
    // Must start with 'sk-' and be at least 20 characters
    return (
        strlen( $key ) >= 20 &&
        strpos( $key, 'sk-' ) === 0
    );
}

/**
 * Validate post types array
 */
public function validate_post_types( array $types ): bool {
    if ( empty( $types ) ) {
        return false;
    }
    
    foreach ( $types as $type ) {
        if ( ! post_type_exists( $type ) ) {
            return false;
        }
    }
    
    return true;
}

/**
 * Validate numeric range
 */
public function validate_in_range( $value, float $min, float $max ): bool {
    return is_numeric( $value ) && $value >= $min && $value <= $max;
}
```

### 12.3 Error Messages

| Error Code | Field | Message |
|------------|-------|---------|
| `invalid_format` | api_key | API key must start with 'sk-' |
| `too_short` | api_key | API key is too short |
| `empty_selection` | post_types | Select at least one content type |
| `invalid_post_type` | post_types | One or more selected types are invalid |
| `out_of_range` | per_page | Value must be between 1 and 50 |
| `out_of_range` | similarity_threshold | Value must be between 0.3 and 0.9 |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial functional requirements |
