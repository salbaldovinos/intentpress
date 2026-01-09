# Functional Requirements Document: Content Indexing
## IntentPress MVP

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related PRDs:** PRD-Overview.md, PRD-User-Stories.md  
**Related User Stories:** US-301, US-302, US-303, US-304, US-305

---

## Table of Contents

1. [Overview](#1-overview)
2. [Indexing Architecture](#2-indexing-architecture)
3. [Content Extraction](#3-content-extraction)
4. [Embedding Generation](#4-embedding-generation)
5. [Background Processing](#5-background-processing)
6. [Progress Tracking](#6-progress-tracking)
7. [Error Handling & Recovery](#7-error-handling--recovery)
8. [Index Management](#8-index-management)
9. [Free Tier Limits](#9-free-tier-limits)
10. [REST API Endpoints](#10-rest-api-endpoints)
11. [React Components](#11-react-components)
12. [Data Models](#12-data-models)
13. [Performance Specifications](#13-performance-specifications)

---

## 1. Overview

### 1.1 Purpose

This document specifies the functional requirements for IntentPress's content indexing system, which generates and stores vector embeddings for WordPress content to enable semantic search.

### 1.2 Scope

| In Scope | Out of Scope |
|----------|--------------|
| Manual indexing trigger | Real-time indexing on post save |
| Background processing | Scheduled automatic re-indexing |
| Progress tracking UI | Incremental/delta indexing |
| Error handling & retry | Custom field indexing |
| Free tier limit enforcement | Multisite batch indexing |
| Single post re-indexing | External content sources |

### 1.3 Indexing Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        INDEXING LIFECYCLE                                │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  IDLE    │────▶│ QUEUED   │────▶│ RUNNING  │────▶│ COMPLETE │
│          │     │          │     │          │     │          │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
     ▲                                  │               │
     │                                  │               │
     │                                  ▼               │
     │                           ┌──────────┐          │
     │                           │  ERROR   │          │
     │                           │          │          │
     │                           └──────────┘          │
     │                                  │               │
     └──────────────────────────────────┴───────────────┘
                    (Reset / New Index)
```

---

## 2. Indexing Architecture

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      INDEXING SYSTEM ARCHITECTURE                        │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                           Admin Interface                                 │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────────────────┐  │
│  │ Start Indexing │  │ Cancel Button  │  │ Progress Display           │  │
│  │    Button      │  │                │  │                            │  │
│  └───────┬────────┘  └───────┬────────┘  └─────────────▲──────────────┘  │
│          │                   │                         │                  │
└──────────┼───────────────────┼─────────────────────────┼──────────────────┘
           │                   │                         │
           │ REST API          │ REST API                │ Polling (5s)
           ▼                   ▼                         │
┌──────────────────────────────────────────────────────────────────────────┐
│                         REST API Layer                                    │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌──────────────────┐  │
│  │ POST /index/start   │  │ POST /index/cancel  │  │ GET /index/status│  │
│  └──────────┬──────────┘  └──────────┬──────────┘  └────────┬─────────┘  │
└─────────────┼────────────────────────┼──────────────────────┼────────────┘
              │                        │                      │
              ▼                        ▼                      ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                       Indexing Controller                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐ │
│  │                     IntentPress_Indexer                              │ │
│  │  • validate_can_index()                                              │ │
│  │  • start_indexing()                                                  │ │
│  │  • process_batch()                                                   │ │
│  │  • cancel_indexing()                                                 │ │
│  │  • get_status()                                                      │ │
│  └──────────────────────────────────┬──────────────────────────────────┘ │
└─────────────────────────────────────┼────────────────────────────────────┘
                                      │
              ┌───────────────────────┼───────────────────────┐
              │                       │                       │
              ▼                       ▼                       ▼
┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────────┐
│   Content Extractor  │  │   Embedding Service  │  │    Vector Store      │
│                      │  │                      │  │                      │
│ • get_post_content() │  │ • generate_batch()   │  │ • store_embedding()  │
│ • normalize_text()   │  │ • handle_errors()    │  │ • delete_embedding() │
│ • calculate_hash()   │  │ • track_tokens()     │  │ • get_embedding()    │
└──────────────────────┘  └──────────────────────┘  └──────────────────────┘
              │                       │                       │
              └───────────────────────┼───────────────────────┘
                                      │
                                      ▼
                          ┌──────────────────────┐
                          │     wp_options       │
                          │ (status tracking)    │
                          └──────────────────────┘
```

### 2.2 Component Responsibilities

| Component | Class | Responsibilities |
|-----------|-------|------------------|
| Indexer | `IntentPress_Indexer` | Orchestration, batch processing, status management |
| Content Extractor | `IntentPress_Content_Extractor` | Post content extraction, normalization |
| Embedding Service | `IntentPress_Embedding_Service` | OpenAI API communication |
| Vector Store | `IntentPress_Vector_Store` | Database operations for embeddings |
| Progress Tracker | `IntentPress_Progress_Tracker` | Status persistence, UI data |

---

## 3. Content Extraction

### 3.1 Content Sources

| Source | Priority | Extraction Method |
|--------|----------|-------------------|
| Post title | 1 (always included) | `get_the_title()` |
| Post content | 2 (primary) | `get_the_content()` |
| Post excerpt | 3 (if exists) | `get_the_excerpt()` |

### 3.2 Content Processing Pipeline

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    CONTENT EXTRACTION PIPELINE                           │
└─────────────────────────────────────────────────────────────────────────┘

POST OBJECT
     │
     ▼
┌─────────────────────┐
│ 1. Get Raw Content  │
│    - Title          │
│    - Content        │
│    - Excerpt        │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 2. Expand Shortcodes│
│    do_shortcode()   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 3. Strip HTML Tags  │
│    wp_strip_all_tags│
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 4. Decode Entities  │
│    html_entity_     │
│    decode()         │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 5. Normalize        │
│    Whitespace       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 6. Truncate         │
│    (50,000 chars)   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 7. Calculate Hash   │
│    md5(content)     │
└──────────┬──────────┘
           │
           ▼
PROCESSED CONTENT
```

### 3.3 Content Extraction Function

```php
/**
 * Extract indexable content from a post
 * 
 * @param WP_Post $post Post object
 * @return array{content: string, hash: string, tokens_estimate: int}
 */
public function extract_content( WP_Post $post ): array {
    // 1. Build content string
    $parts = [];
    
    // Title (always include, weight by repeating)
    $parts[] = $post->post_title;
    $parts[] = $post->post_title; // Repeat for emphasis
    
    // Excerpt if exists
    if ( ! empty( $post->post_excerpt ) ) {
        $parts[] = $post->post_excerpt;
    }
    
    // Main content
    $content = $post->post_content;
    
    // 2. Expand shortcodes
    $content = do_shortcode( $content );
    
    // 3. Strip HTML
    $content = wp_strip_all_tags( $content );
    
    // 4. Decode HTML entities
    $content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
    
    $parts[] = $content;
    
    // 5. Combine and normalize
    $combined = implode( "\n\n", $parts );
    $combined = $this->normalize_whitespace( $combined );
    
    // 6. Truncate if necessary
    $max_chars = 50000;
    if ( strlen( $combined ) > $max_chars ) {
        $combined = substr( $combined, 0, $max_chars );
    }
    
    // 7. Calculate hash for change detection
    $hash = md5( $combined );
    
    // 8. Estimate tokens (rough: 1 token ≈ 4 chars)
    $tokens_estimate = (int) ceil( strlen( $combined ) / 4 );
    
    return [
        'content' => $combined,
        'hash' => $hash,
        'tokens_estimate' => $tokens_estimate,
    ];
}
```

### 3.4 Content Filtering Rules

| Rule | Action | Reason |
|------|--------|--------|
| Empty content | Skip post | Nothing to embed |
| Only whitespace | Skip post | No meaningful content |
| > 50,000 chars | Truncate | Token limits |
| Non-UTF8 encoding | Attempt fix, skip if fails | API requirement |
| Script/style tags | Strip completely | Noise removal |

---

## 4. Embedding Generation

### 4.1 Batch Processing Strategy

| Parameter | Value | Rationale |
|-----------|-------|-----------|
| Batch size | 10 posts | Balance between efficiency and progress visibility |
| API calls per batch | 1 (batched embeddings) | OpenAI supports batch input |
| Delay between batches | 100ms | Rate limit buffer |

### 4.2 Embedding Request Format

```php
/**
 * Generate embeddings for a batch of posts
 * 
 * @param array $posts Array of extracted content
 * @return array{embeddings: array, tokens_used: int}|WP_Error
 */
public function generate_batch_embeddings( array $posts ): array|WP_Error {
    // Prepare batch input
    $inputs = array_map( function( $post ) {
        return $post['content'];
    }, $posts );
    
    // Call OpenAI API with batch
    $response = $this->api_client->post( 'embeddings', [
        'model' => 'text-embedding-3-small',
        'input' => $inputs,
        'encoding_format' => 'float',
    ]);
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    // Map responses back to posts
    $embeddings = [];
    $total_tokens = 0;
    
    foreach ( $response['data'] as $index => $data ) {
        $embeddings[ $index ] = $data['embedding'];
    }
    
    $total_tokens = $response['usage']['total_tokens'];
    
    return [
        'embeddings' => $embeddings,
        'tokens_used' => $total_tokens,
    ];
}
```

### 4.3 Token Tracking

```php
/**
 * Track token usage for cost monitoring
 */
public function track_token_usage( int $tokens ): void {
    $current_month = date( 'Y-m' );
    $key = 'intentpress_tokens_' . $current_month;
    
    $total = get_option( $key, 0 );
    update_option( $key, $total + $tokens );
}
```

### 4.4 Embedding Storage Format

| Field | Type | Content |
|-------|------|---------|
| `embedding` | LONGTEXT | JSON-encoded float array (1536 dimensions) |
| `model_version` | VARCHAR(50) | 'text-embedding-3-small' |
| `content_hash` | VARCHAR(32) | MD5 of source content |
| `token_count` | INT | Actual tokens used |

---

## 5. Background Processing

### 5.1 Processing Model

IntentPress uses **synchronous batch processing with progress tracking** rather than WordPress cron-based background jobs for MVP simplicity.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     BATCH PROCESSING MODEL                               │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐
│ Admin clicks     │
│ "Start Indexing" │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐     ┌──────────────────┐
│ AJAX Request     │────▶│ Process 1 Batch  │
│ /index/start     │     │ (10 posts)       │
└──────────────────┘     └────────┬─────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │ Update Progress  │
                         │ in wp_options    │
                         └────────┬─────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │ Return Response  │
                         │ with status      │
                         └────────┬─────────┘
                                  │
┌──────────────────┐              │
│ Frontend polls   │◀─────────────┘
│ /index/status    │
│ every 5 seconds  │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐     ┌──────────────────┐
│ If incomplete:   │────▶│ Continue via     │
│ more batches     │     │ subsequent AJAX  │
└──────────────────┘     └──────────────────┘
```

### 5.2 Batch Processing Loop

```php
/**
 * Process indexing in batches
 * Called via AJAX, returns after each batch for progress update
 * 
 * @return array Status response
 */
public function process_batch(): array {
    // Get current state
    $state = $this->get_indexing_state();
    
    if ( $state['status'] !== 'running' ) {
        return ['status' => 'idle'];
    }
    
    // Get next batch of posts
    $posts = $this->get_next_batch( $state['current_offset'], self::BATCH_SIZE );
    
    if ( empty( $posts ) ) {
        // Indexing complete
        $this->complete_indexing();
        return $this->get_status();
    }
    
    // Process batch
    $results = $this->process_posts( $posts );
    
    // Update state
    $this->update_indexing_state([
        'current_offset' => $state['current_offset'] + count( $posts ),
        'indexed_count' => $state['indexed_count'] + $results['success_count'],
        'error_count' => $state['error_count'] + $results['error_count'],
        'errors' => array_merge( $state['errors'], $results['errors'] ),
    ]);
    
    return $this->get_status();
}
```

### 5.3 State Persistence

```php
/**
 * Indexing state stored in wp_options
 */
[
    'intentpress_indexing_status' => [
        'status' => 'running',        // idle, running, complete, error
        'started_at' => '2026-01-08T15:30:00Z',
        'total_posts' => 500,
        'current_offset' => 150,
        'indexed_count' => 145,       // Successfully indexed
        'error_count' => 5,           // Failed to index
        'errors' => [                 // Last N errors
            ['post_id' => 123, 'error' => 'Token limit exceeded'],
        ],
        'estimated_completion' => '2026-01-08T15:35:00Z',
    ]
]
```

---

## 6. Progress Tracking

### 6.1 Progress Data Model

```typescript
interface IndexingProgress {
    status: 'idle' | 'running' | 'complete' | 'error' | 'cancelled';
    startedAt: string | null;
    completedAt: string | null;
    totalPosts: number;
    indexedCount: number;
    errorCount: number;
    currentOffset: number;
    percentComplete: number;
    estimatedTimeRemaining: number | null; // seconds
    errors: IndexingError[];
    canCancel: boolean;
}

interface IndexingError {
    postId: number;
    postTitle: string;
    error: string;
    timestamp: string;
}
```

### 6.2 Progress Calculation

```php
/**
 * Calculate progress percentage and time estimate
 */
public function calculate_progress(): array {
    $state = $this->get_indexing_state();
    
    $processed = $state['indexed_count'] + $state['error_count'];
    $total = $state['total_posts'];
    
    $percent = $total > 0 ? ( $processed / $total ) * 100 : 0;
    
    // Calculate estimated time remaining
    $elapsed = time() - strtotime( $state['started_at'] );
    $rate = $elapsed > 0 ? $processed / $elapsed : 0;
    $remaining_posts = $total - $processed;
    $estimated_seconds = $rate > 0 ? $remaining_posts / $rate : null;
    
    return [
        'percent_complete' => round( $percent, 1 ),
        'estimated_time_remaining' => $estimated_seconds,
        'posts_per_second' => round( $rate, 2 ),
    ];
}
```

### 6.3 Progress UI Update Frequency

| Phase | Update Interval | Rationale |
|-------|-----------------|-----------|
| Active indexing | 5 seconds | Balance responsiveness and server load |
| Idle/complete | No polling | No need to poll |
| Error state | No polling | User action required |

### 6.4 Progress Display Format

```
┌────────────────────────────────────────────────────────────────────────┐
│ Indexing Progress                                                       │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌────────────────────────────────────────────────────────────────┐   │
│  │ ████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  45%        │   │
│  └────────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  225 of 500 posts indexed                                              │
│                                                                        │
│  ⏱️ Estimated time remaining: 2 minutes 30 seconds                      │
│  📊 Processing rate: 1.5 posts/second                                   │
│                                                                        │
│  ┌────────────────┐                                                    │
│  │     Cancel     │                                                    │
│  └────────────────┘                                                    │
│                                                                        │
│  ℹ️ You can navigate away from this page. Indexing will continue.      │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 7. Error Handling & Recovery

### 7.1 Error Categories

| Category | Examples | Recovery Strategy |
|----------|----------|-------------------|
| Transient API | Rate limit, timeout, 5xx | Retry with backoff |
| Permanent API | Invalid key, quota exceeded | Stop, notify admin |
| Content | Invalid encoding, empty | Skip post, log error |
| System | Database write fail | Stop, notify admin |

### 7.2 Retry Logic

```php
/**
 * Process a single post with retry logic
 */
public function process_post_with_retry( WP_Post $post, int $max_retries = 3 ): array {
    $attempts = 0;
    $last_error = null;
    
    while ( $attempts < $max_retries ) {
        $attempts++;
        
        try {
            $result = $this->process_single_post( $post );
            return ['success' => true, 'result' => $result];
        } catch ( IntentPress_Retryable_Exception $e ) {
            $last_error = $e;
            // Exponential backoff
            $wait = pow( 2, $attempts ) * 1000; // ms
            usleep( $wait * 1000 );
        } catch ( IntentPress_Permanent_Exception $e ) {
            // Don't retry permanent errors
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => false,
            ];
        }
    }
    
    return [
        'success' => false,
        'error' => $last_error->getMessage(),
        'retryable' => true,
        'attempts' => $attempts,
    ];
}
```

### 7.3 Error Logging

```php
/**
 * Log indexing error
 */
public function log_indexing_error( int $post_id, string $error, array $context = [] ): void {
    $entry = [
        'timestamp' => current_time( 'mysql' ),
        'post_id' => $post_id,
        'post_title' => get_the_title( $post_id ),
        'error' => $error,
        'context' => $context,
    ];
    
    // Store in state (keep last 100 errors)
    $state = $this->get_indexing_state();
    $errors = $state['errors'] ?? [];
    array_unshift( $errors, $entry );
    $errors = array_slice( $errors, 0, 100 );
    
    $this->update_indexing_state( ['errors' => $errors] );
    
    // Also log to WordPress debug log if enabled
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            '[IntentPress] Indexing error for post %d: %s',
            $post_id,
            $error
        ));
    }
}
```

### 7.4 Consecutive Failure Handling

```php
/**
 * Track consecutive failures and pause if threshold exceeded
 */
private int $consecutive_failures = 0;
private const MAX_CONSECUTIVE_FAILURES = 5;

public function handle_post_result( bool $success ): bool {
    if ( $success ) {
        $this->consecutive_failures = 0;
        return true;
    }
    
    $this->consecutive_failures++;
    
    if ( $this->consecutive_failures >= self::MAX_CONSECUTIVE_FAILURES ) {
        $this->pause_indexing( 'Too many consecutive failures' );
        return false;
    }
    
    return true;
}
```

### 7.5 Recovery Actions

| State | Available Actions | UI Button |
|-------|-------------------|-----------|
| Paused (errors) | Resume, Retry failed, View errors | "Resume" / "Retry Failed" |
| Complete with errors | Retry failed, Clear errors | "Retry Failed Posts" |
| Cancelled | Restart | "Start Indexing" |
| Error | View details, Restart | "View Errors" / "Restart" |

---

## 8. Index Management

### 8.1 Index Operations

| Operation | Trigger | Effect |
|-----------|---------|--------|
| Full re-index | Admin button | Clear all, re-index all posts |
| Single post index | Post meta box | Index/re-index one post |
| Clear index | Admin button (destructive) | Delete all embeddings |
| Index status | Auto/manual | Check index health |

### 8.2 Full Re-index Flow

```php
/**
 * Start full re-indexing
 */
public function start_full_reindex(): array {
    // 1. Validate prerequisites
    if ( ! $this->can_start_indexing() ) {
        return ['error' => 'Cannot start indexing'];
    }
    
    // 2. Get total posts to index
    $post_types = get_option( 'intentpress_indexed_post_types', ['post', 'page'] );
    $total = $this->count_indexable_posts( $post_types );
    
    // 3. Apply free tier limit
    $limit = $this->get_index_limit();
    $total = min( $total, $limit );
    
    // 4. Initialize state
    $this->set_indexing_state([
        'status' => 'running',
        'started_at' => current_time( 'mysql' ),
        'total_posts' => $total,
        'current_offset' => 0,
        'indexed_count' => 0,
        'error_count' => 0,
        'errors' => [],
    ]);
    
    // 5. Clear existing embeddings
    $this->vector_store->clear_all();
    
    // 6. Return initial status
    return $this->get_status();
}
```

### 8.3 Single Post Re-index

```php
/**
 * Re-index a single post
 * 
 * @param int $post_id Post ID to re-index
 * @return array Result status
 */
public function reindex_single_post( int $post_id ): array {
    $post = get_post( $post_id );
    
    if ( ! $post || $post->post_status !== 'publish' ) {
        return ['error' => 'Post not found or not published'];
    }
    
    // Check if post type is indexed
    $indexed_types = get_option( 'intentpress_indexed_post_types', ['post', 'page'] );
    if ( ! in_array( $post->post_type, $indexed_types, true ) ) {
        return ['error' => 'Post type not configured for indexing'];
    }
    
    // Process the post
    $result = $this->process_single_post( $post );
    
    if ( $result['success'] ) {
        return [
            'success' => true,
            'message' => 'Post re-indexed successfully',
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['error'],
    ];
}
```

### 8.4 Index Staleness Detection

```php
/**
 * Check if index is stale (new/updated posts since last index)
 * 
 * @return array{stale: bool, new_posts: int, updated_posts: int}
 */
public function check_index_staleness(): array {
    $last_index = get_option( 'intentpress_last_index_at' );
    
    if ( ! $last_index ) {
        return ['stale' => true, 'reason' => 'never_indexed'];
    }
    
    $post_types = get_option( 'intentpress_indexed_post_types', ['post', 'page'] );
    
    // Count posts created after last index
    $new_posts = $this->count_posts_since( $last_index, 'created', $post_types );
    
    // Count posts modified after last index
    $updated_posts = $this->count_posts_since( $last_index, 'modified', $post_types );
    
    return [
        'stale' => ( $new_posts > 0 || $updated_posts > 0 ),
        'new_posts' => $new_posts,
        'updated_posts' => $updated_posts,
        'last_index_at' => $last_index,
    ];
}
```

---

## 9. Free Tier Limits

### 9.1 Limit Specifications

| Limit | Value | Enforcement Point |
|-------|-------|-------------------|
| Max posts indexed | 500 | Before processing |
| Index priority | Newest first | Query ordering |
| No time limit | N/A | Indexing completes fully |

### 9.2 Limit Enforcement

```php
/**
 * Get posts to index respecting free tier limit
 */
public function get_posts_to_index(): array {
    $post_types = get_option( 'intentpress_indexed_post_types', ['post', 'page'] );
    $limit = $this->get_index_limit(); // 500 for free tier
    
    $args = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC', // Newest first
        'fields' => 'ids',
    ];
    
    return get_posts( $args );
}

/**
 * Get index limit based on plan
 */
public function get_index_limit(): int {
    $is_pro = get_option( 'intentpress_pro_active', false );
    
    if ( $is_pro ) {
        return PHP_INT_MAX; // Unlimited for pro
    }
    
    return 500; // Free tier limit
}
```

### 9.3 Limit UI Display

```
┌────────────────────────────────────────────────────────────────────────┐
│ Index Status                                                            │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌────────────────────────────────────────────────────────────────┐   │
│  │ ████████████████████████████████████████████████████  100%     │   │
│  └────────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  500 of 650 posts indexed (free tier limit)                            │
│                                                                        │
│  ⚠️ 150 posts are not included in semantic search.                      │
│     These posts will use standard WordPress search.                     │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │ 🚀 Upgrade to Pro to index all 650 posts                         │ │
│  │                                                                  │ │
│  │ IntentPress Pro includes:                                        │ │
│  │ • Unlimited post indexing                                        │ │
│  │ • Unlimited searches                                             │ │
│  │ • Priority support                                               │ │
│  │                                                                  │ │
│  │ [$X/month - Upgrade Now]                                         │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 9.4 Over-Limit Notification

```php
/**
 * Check and notify about limit
 */
public function check_limit_notification(): ?array {
    $total_posts = $this->count_total_indexable_posts();
    $limit = $this->get_index_limit();
    
    if ( $total_posts <= $limit ) {
        return null; // No notification needed
    }
    
    return [
        'type' => 'limit_exceeded',
        'total_posts' => $total_posts,
        'indexed_posts' => $limit,
        'excluded_posts' => $total_posts - $limit,
        'message' => sprintf(
            __( '%d of your %d posts exceed the free tier limit.', 'intentpress' ),
            $total_posts - $limit,
            $total_posts
        ),
    ];
}
```

---

## 10. REST API Endpoints

### 10.1 Start Indexing

**Endpoint:** `POST /wp-json/intentpress/v1/index/start`

**Request:**
```json
{
    "full_reindex": true
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "status": "running",
        "total_posts": 500,
        "indexed_count": 0,
        "message": "Indexing started"
    }
}
```

**Response (Already Running):**
```json
{
    "success": false,
    "error": {
        "code": "indexing_in_progress",
        "message": "Indexing is already in progress"
    }
}
```

### 10.2 Get Status

**Endpoint:** `GET /wp-json/intentpress/v1/index/status`

**Response:**
```json
{
    "status": "running",
    "started_at": "2026-01-08T15:30:00Z",
    "total_posts": 500,
    "current_offset": 225,
    "indexed_count": 220,
    "error_count": 5,
    "percent_complete": 45.0,
    "estimated_time_remaining": 150,
    "posts_per_second": 1.5,
    "errors": [
        {
            "post_id": 123,
            "post_title": "Example Post",
            "error": "Content exceeds token limit",
            "timestamp": "2026-01-08T15:32:00Z"
        }
    ],
    "can_cancel": true
}
```

### 10.3 Process Batch

**Endpoint:** `POST /wp-json/intentpress/v1/index/batch`

**Purpose:** Process next batch (called automatically by frontend)

**Response:**
```json
{
    "success": true,
    "batch_processed": 10,
    "status": "running",
    "indexed_count": 230,
    "continue": true
}
```

### 10.4 Cancel Indexing

**Endpoint:** `POST /wp-json/intentpress/v1/index/cancel`

**Response:**
```json
{
    "success": true,
    "message": "Indexing cancelled",
    "indexed_count": 225,
    "status": "cancelled"
}
```

### 10.5 Re-index Single Post

**Endpoint:** `POST /wp-json/intentpress/v1/index/post/{id}`

**Response:**
```json
{
    "success": true,
    "post_id": 123,
    "message": "Post re-indexed successfully"
}
```

### 10.6 Get Errors

**Endpoint:** `GET /wp-json/intentpress/v1/index/errors`

**Response:**
```json
{
    "errors": [
        {
            "post_id": 123,
            "post_title": "Example Post",
            "error": "Content exceeds token limit",
            "timestamp": "2026-01-08T15:32:00Z",
            "retryable": false
        }
    ],
    "total": 5,
    "retryable_count": 2
}
```

### 10.7 Retry Failed

**Endpoint:** `POST /wp-json/intentpress/v1/index/retry-failed`

**Response:**
```json
{
    "success": true,
    "retrying": 2,
    "message": "Retrying 2 failed posts"
}
```

---

## 11. React Components

### 11.1 Component Hierarchy

```
IndexingTab
├── IndexStatus
│   ├── StatusBadge
│   └── LastIndexInfo
├── IndexProgress (when running)
│   ├── ProgressBar
│   ├── ProgressStats
│   └── TimeEstimate
├── IndexActions
│   ├── StartButton
│   ├── CancelButton
│   └── RetryButton
├── LimitWarning (when over limit)
│   └── UpgradePrompt
└── ErrorList
    └── ErrorItem[]
```

### 11.2 IndexProgress Component

```typescript
interface IndexProgressProps {
    status: IndexingStatus;
    totalPosts: number;
    indexedCount: number;
    errorCount: number;
    percentComplete: number;
    estimatedTimeRemaining: number | null;
    postsPerSecond: number;
    onCancel: () => void;
}

function IndexProgress({
    status,
    totalPosts,
    indexedCount,
    errorCount,
    percentComplete,
    estimatedTimeRemaining,
    postsPerSecond,
    onCancel,
}: IndexProgressProps) {
    return (
        <Card>
            <CardHeader>
                <h3>{__('Indexing Progress', 'intentpress')}</h3>
            </CardHeader>
            <CardBody>
                <ProgressBar value={percentComplete} />
                
                <div className="progress-stats">
                    <span>
                        {sprintf(
                            __('%d of %d posts indexed', 'intentpress'),
                            indexedCount,
                            totalPosts
                        )}
                    </span>
                    {errorCount > 0 && (
                        <span className="error-count">
                            {sprintf(__('%d errors', 'intentpress'), errorCount)}
                        </span>
                    )}
                </div>
                
                {estimatedTimeRemaining && (
                    <div className="time-estimate">
                        <Icon icon="clock" />
                        {sprintf(
                            __('Estimated time remaining: %s', 'intentpress'),
                            formatDuration(estimatedTimeRemaining)
                        )}
                    </div>
                )}
                
                <div className="processing-rate">
                    {sprintf(
                        __('Processing rate: %.1f posts/second', 'intentpress'),
                        postsPerSecond
                    )}
                </div>
            </CardBody>
            <CardFooter>
                <Button
                    variant="secondary"
                    onClick={onCancel}
                    disabled={status !== 'running'}
                >
                    {__('Cancel', 'intentpress')}
                </Button>
            </CardFooter>
        </Card>
    );
}
```

### 11.3 Progress Polling Hook

```typescript
function useIndexingProgress() {
    const [progress, setProgress] = useState<IndexingProgress | null>(null);
    const [isPolling, setIsPolling] = useState(false);
    
    const fetchStatus = useCallback(async () => {
        const response = await apiFetch<IndexingProgress>({
            path: '/intentpress/v1/index/status',
        });
        setProgress(response);
        return response;
    }, []);
    
    useEffect(() => {
        let intervalId: number;
        
        if (isPolling) {
            // Initial fetch
            fetchStatus();
            
            // Poll every 5 seconds
            intervalId = window.setInterval(async () => {
                const status = await fetchStatus();
                
                // Stop polling if not running
                if (status.status !== 'running') {
                    setIsPolling(false);
                }
            }, 5000);
        }
        
        return () => {
            if (intervalId) {
                clearInterval(intervalId);
            }
        };
    }, [isPolling, fetchStatus]);
    
    const startPolling = () => setIsPolling(true);
    const stopPolling = () => setIsPolling(false);
    
    return {
        progress,
        isPolling,
        startPolling,
        stopPolling,
        refetch: fetchStatus,
    };
}
```

---

## 12. Data Models

### 12.1 Embeddings Table

```sql
CREATE TABLE {prefix}intentpress_embeddings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    embedding LONGTEXT NOT NULL,
    model_version VARCHAR(50) NOT NULL DEFAULT 'text-embedding-3-small',
    content_hash VARCHAR(32) NOT NULL,
    token_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY post_id (post_id),
    KEY model_version (model_version),
    KEY content_hash (content_hash),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12.2 Indexing State Options

| Option Key | Type | Purpose |
|------------|------|---------|
| `intentpress_indexing_status` | array | Current indexing state |
| `intentpress_last_index_at` | string | Timestamp of last complete index |
| `intentpress_indexed_count` | int | Total posts currently indexed |
| `intentpress_index_errors` | array | Persistent error log |

### 12.3 TypeScript Interfaces

```typescript
interface EmbeddingRecord {
    id: number;
    postId: number;
    embedding: number[];
    modelVersion: string;
    contentHash: string;
    tokenCount: number;
    createdAt: string;
    updatedAt: string;
}

interface IndexingState {
    status: 'idle' | 'running' | 'complete' | 'error' | 'cancelled';
    startedAt: string | null;
    completedAt: string | null;
    totalPosts: number;
    currentOffset: number;
    indexedCount: number;
    errorCount: number;
    errors: IndexingError[];
}

interface IndexingError {
    postId: number;
    postTitle: string;
    error: string;
    timestamp: string;
    retryable: boolean;
}
```

---

## 13. Performance Specifications

### 13.1 Performance Targets

| Metric | Target | Measurement |
|--------|--------|-------------|
| Posts per minute | 100+ | Batch processing rate |
| Memory per batch | < 64MB | Peak memory usage |
| API latency | < 2s per batch | OpenAI response time |
| Database write | < 50ms per post | Insert/update time |
| Progress update | < 100ms | UI responsiveness |

### 13.2 Optimization Strategies

| Strategy | Implementation | Impact |
|----------|----------------|--------|
| Batch API calls | 10 posts per API request | -90% API calls |
| Chunked DB inserts | Multi-row INSERT | -80% DB queries |
| Memory streaming | Process posts one at a time | Constant memory |
| Hash comparison | Skip unchanged posts | -50% API calls on re-index |

### 13.3 Memory Management

```php
/**
 * Process posts with memory-efficient iteration
 */
public function process_posts_efficiently(): Generator {
    $post_ids = $this->get_posts_to_index();
    
    foreach ( array_chunk( $post_ids, self::BATCH_SIZE ) as $batch_ids ) {
        // Load only current batch
        $posts = get_posts([
            'post__in' => $batch_ids,
            'post_type' => 'any',
            'orderby' => 'post__in',
        ]);
        
        yield $posts;
        
        // Clear memory
        unset( $posts );
        wp_cache_flush();
    }
}
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial functional requirements |
