# Functional Requirements Document: Core Search
## IntentPress MVP

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related PRDs:** PRD-Overview.md, PRD-User-Stories.md  
**Related User Stories:** US-101, US-102, US-103, US-104, US-105, US-501

---

## Table of Contents

1. [Overview](#1-overview)
2. [Functional Components](#2-functional-components)
3. [Search Request Flow](#3-search-request-flow)
4. [Embedding Generation](#4-embedding-generation)
5. [Vector Similarity Search](#5-vector-similarity-search)
6. [Result Ranking & Scoring](#6-result-ranking--scoring)
7. [Fallback Search Logic](#7-fallback-search-logic)
8. [Search Result Formatting](#8-search-result-formatting)
9. [Caching Strategy](#9-caching-strategy)
10. [WordPress Integration Hooks](#10-wordpress-integration-hooks)
11. [API Contracts](#11-api-contracts)
12. [Business Rules](#12-business-rules)
13. [Data Models](#13-data-models)

---

## 1. Overview

### 1.1 Purpose

This document specifies the functional requirements for IntentPress's core semantic search functionality. It details how search queries are processed, how vector similarity is calculated, and how results are returned to users.

### 1.2 Scope

| In Scope | Out of Scope |
|----------|--------------|
| Frontend search form integration | WooCommerce product search |
| Query embedding generation | Voice search |
| Vector similarity calculation | Autocomplete/search-as-you-type |
| Result ranking and pagination | Faceted filtering |
| Fallback to WordPress search | Real-time indexing |
| Result caching | Multi-language detection |

### 1.3 Actors

| Actor | Description | Permissions |
|-------|-------------|-------------|
| Site Visitor | Anonymous or logged-in user performing searches | `read` |
| Site Administrator | Configures search settings | `manage_options` |
| Background Process | Handles async operations | System-level |
| External API (OpenAI) | Generates embeddings | API key authenticated |

---

## 2. Functional Components

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         SEARCH REQUEST FLOW                              │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────┐
│   Frontend   │────▶│   Search     │────▶│  Embedding   │────▶│  OpenAI  │
│  Search Form │     │  Handler     │     │   Service    │     │   API    │
└──────────────┘     └──────────────┘     └──────────────┘     └──────────┘
                            │                    │
                            │                    ▼
                            │            ┌──────────────┐
                            │            │   Query      │
                            │            │   Cache      │
                            │            └──────────────┘
                            │
                            ▼
                     ┌──────────────┐     ┌──────────────┐
                     │   Vector     │────▶│  Embeddings  │
                     │  Similarity  │     │   Database   │
                     └──────────────┘     └──────────────┘
                            │
                            ▼
                     ┌──────────────┐     ┌──────────────┐
                     │   Result     │────▶│  WordPress   │
                     │   Formatter  │     │   Posts      │
                     └──────────────┘     └──────────────┘
                            │
                            ▼
                     ┌──────────────┐
                     │   Search     │
                     │   Results    │
                     └──────────────┘
```

### 2.2 Component Responsibilities

| Component | Responsibility | Class/Function |
|-----------|----------------|----------------|
| Search Handler | Orchestrates search flow, manages fallback | `IntentPress_Search_Handler` |
| Embedding Service | Generates embeddings via OpenAI | `IntentPress_Embedding_Service` |
| Vector Store | Stores and queries embeddings | `IntentPress_Vector_Store` |
| Similarity Calculator | Computes cosine similarity | `IntentPress_Similarity` |
| Result Formatter | Formats results for display | `IntentPress_Result_Formatter` |
| Cache Manager | Manages query and result caching | `IntentPress_Cache` |

---

## 3. Search Request Flow

### 3.1 Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      SEARCH REQUEST PROCESSING                           │
└─────────────────────────────────────────────────────────────────────────┘

START
  │
  ▼
┌─────────────────────┐
│ Receive Search Query │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐     ┌─────────────────────┐
│ Validate Query      │────▶│ Return Error        │
│ (length, content)   │ NO  │ (empty/too short)   │
└──────────┬──────────┘     └─────────────────────┘
           │ YES
           ▼
┌─────────────────────┐     ┌─────────────────────┐
│ Check API Key       │────▶│ Use Fallback Search │
│ Configured?         │ NO  │                     │
└──────────┬──────────┘     └─────────────────────┘
           │ YES
           ▼
┌─────────────────────┐     ┌─────────────────────┐
│ Check Query Cache   │────▶│ Return Cached       │
│                     │ HIT │ Embedding           │
└──────────┬──────────┘     └──────────┬──────────┘
           │ MISS                      │
           ▼                           │
┌─────────────────────┐                │
│ Generate Query      │                │
│ Embedding (OpenAI)  │                │
└──────────┬──────────┘                │
           │                           │
           ├───────────────────────────┘
           │
           ▼
┌─────────────────────┐     ┌─────────────────────┐
│ API Call Successful?│────▶│ Use Fallback Search │
│                     │ NO  │ (log error)         │
└──────────┬──────────┘     └─────────────────────┘
           │ YES
           ▼
┌─────────────────────┐
│ Calculate Similarity│
│ Against All Posts   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Filter by Threshold │
│ (min: 0.5 default)  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Sort by Similarity  │
│ (descending)        │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Apply Pagination    │
│ (per_page setting)  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Hydrate Post Data   │
│ (title, excerpt)    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Cache Results       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Return Results      │
└─────────────────────┘
           │
           ▼
          END
```

### 3.2 Request Processing Steps

| Step | Action | Timeout | Failure Mode |
|------|--------|---------|--------------|
| 1 | Sanitize query input | N/A | Reject invalid |
| 2 | Validate query length | N/A | Return error message |
| 3 | Check configuration | N/A | Use fallback |
| 4 | Check query cache | 10ms | Skip to API |
| 5 | Generate embedding | 10s | Use fallback |
| 6 | Calculate similarities | 500ms | Use fallback |
| 7 | Filter and sort | 50ms | Return empty |
| 8 | Hydrate post data | 100ms | Partial results |
| 9 | Cache results | 50ms | Skip caching |
| 10 | Return response | N/A | Always succeeds |

---

## 4. Embedding Generation

### 4.1 Query Embedding Process

**Input:** Sanitized search query string  
**Output:** 1536-dimensional float vector (for text-embedding-3-small)

```php
/**
 * Generate embedding for search query
 * 
 * @param string $query Sanitized search query
 * @return array{embedding: float[], tokens_used: int}|WP_Error
 */
public function generate_query_embedding( string $query ): array|WP_Error {
    // 1. Normalize query
    $normalized = $this->normalize_query( $query );
    
    // 2. Check cache
    $cache_key = 'intentpress_query_' . md5( $normalized );
    $cached = wp_cache_get( $cache_key, 'intentpress' );
    if ( $cached !== false ) {
        return $cached;
    }
    
    // 3. Call OpenAI API
    $response = $this->api_client->create_embedding( $normalized );
    
    // 4. Cache successful response
    if ( ! is_wp_error( $response ) ) {
        wp_cache_set( $cache_key, $response, 'intentpress', HOUR_IN_SECONDS );
    }
    
    return $response;
}
```

### 4.2 Query Normalization Rules

| Rule | Before | After |
|------|--------|-------|
| Trim whitespace | `"  hello world  "` | `"hello world"` |
| Collapse multiple spaces | `"hello    world"` | `"hello world"` |
| Lowercase | `"Hello World"` | `"hello world"` |
| Remove excess punctuation | `"hello!!! world???"` | `"hello! world?"` |
| Preserve meaningful symbols | `"C++ programming"` | `"C++ programming"` |
| Truncate to max length | (>500 chars) | (first 500 chars) |

### 4.3 OpenAI API Request Format

```json
{
    "model": "text-embedding-3-small",
    "input": "normalized search query here",
    "encoding_format": "float"
}
```

### 4.4 OpenAI API Response Format

```json
{
    "object": "list",
    "data": [
        {
            "object": "embedding",
            "index": 0,
            "embedding": [0.0023064255, -0.009327292, ...]
        }
    ],
    "model": "text-embedding-3-small",
    "usage": {
        "prompt_tokens": 8,
        "total_tokens": 8
    }
}
```

### 4.5 Embedding Error Handling

| Error Type | HTTP Code | Action | Retry |
|------------|-----------|--------|-------|
| Invalid API key | 401 | Fallback + notify admin | No |
| Rate limited | 429 | Fallback + schedule retry | Yes (after delay) |
| Server error | 500-503 | Fallback + retry | Yes (3x) |
| Timeout | N/A | Fallback + log | Yes (1x) |
| Invalid response | N/A | Fallback + log | No |

---

## 5. Vector Similarity Search

### 5.1 Cosine Similarity Calculation

**Formula:**
```
similarity(A, B) = (A · B) / (||A|| × ||B||)

Where:
- A · B = Σ(Aᵢ × Bᵢ) for i = 1 to n
- ||A|| = √(Σ(Aᵢ²)) for i = 1 to n
```

**Implementation:**

```php
/**
 * Calculate cosine similarity between two vectors
 * 
 * @param array $vector_a Query embedding
 * @param array $vector_b Post embedding
 * @return float Similarity score between -1 and 1
 */
public function cosine_similarity( array $vector_a, array $vector_b ): float {
    $dot_product = 0.0;
    $norm_a = 0.0;
    $norm_b = 0.0;
    
    $length = count( $vector_a );
    
    for ( $i = 0; $i < $length; $i++ ) {
        $dot_product += $vector_a[$i] * $vector_b[$i];
        $norm_a += $vector_a[$i] * $vector_a[$i];
        $norm_b += $vector_b[$i] * $vector_b[$i];
    }
    
    $denominator = sqrt( $norm_a ) * sqrt( $norm_b );
    
    if ( $denominator == 0 ) {
        return 0.0;
    }
    
    return $dot_product / $denominator;
}
```

### 5.2 Batch Similarity Calculation

For performance, calculate similarities in batches:

```php
/**
 * Calculate similarities for all indexed posts
 * 
 * @param array $query_embedding The query vector
 * @return array Array of [post_id => similarity_score]
 */
public function calculate_all_similarities( array $query_embedding ): array {
    global $wpdb;
    
    $table = $wpdb->prefix . 'intentpress_embeddings';
    
    // Fetch all embeddings
    $rows = $wpdb->get_results(
        "SELECT post_id, embedding FROM {$table}",
        ARRAY_A
    );
    
    $similarities = [];
    
    foreach ( $rows as $row ) {
        $post_embedding = json_decode( $row['embedding'], true );
        $score = $this->cosine_similarity( $query_embedding, $post_embedding );
        $similarities[ (int) $row['post_id'] ] = $score;
    }
    
    return $similarities;
}
```

### 5.3 Similarity Thresholds

| Threshold | Value | Meaning |
|-----------|-------|---------|
| Minimum display | 0.50 | Results below this are not shown |
| Good match | 0.70 | Highlighted as relevant |
| Excellent match | 0.85 | Top result quality |
| Configurable via filter | `intentpress_similarity_threshold` | Developer override |

### 5.4 Performance Optimization

| Optimization | Description | Impact |
|--------------|-------------|--------|
| Memory-mapped embeddings | Load embeddings efficiently | -50% memory |
| Early termination | Stop if enough high-quality results found | -30% time |
| Vectorized operations | Use array operations vs loops | -40% time |
| Result limit | Only fully process top N candidates | -60% time |

---

## 6. Result Ranking & Scoring

### 6.1 Primary Ranking

Results are primarily ranked by cosine similarity score (descending).

### 6.2 Secondary Ranking Factors

When similarity scores are equal (within 0.01), apply secondary factors:

| Factor | Weight | Rationale |
|--------|--------|-----------|
| Post date (newer first) | Tiebreaker | Fresh content preference |
| Post type priority | Configurable | Pages vs Posts preference |

### 6.3 Score Normalization for Display

```php
/**
 * Normalize similarity score for user display
 * 
 * @param float $raw_score Raw cosine similarity (-1 to 1)
 * @return int Percentage (0-100)
 */
public function normalize_score_for_display( float $raw_score ): int {
    // Map typical range (0.5 to 1.0) to (0% to 100%)
    $min_threshold = 0.5;
    $normalized = ( $raw_score - $min_threshold ) / ( 1 - $min_threshold );
    $percentage = (int) round( $normalized * 100 );
    
    return max( 0, min( 100, $percentage ) );
}
```

### 6.4 Pagination

| Setting | Default | Range | Option Key |
|---------|---------|-------|------------|
| Results per page | 10 | 1-50 | `intentpress_per_page` |
| Max results | 100 | Fixed | N/A |

**Pagination Response Format:**

```php
[
    'results' => [...],      // Post objects for current page
    'total' => 47,           // Total matching results
    'page' => 1,             // Current page
    'per_page' => 10,        // Results per page
    'total_pages' => 5,      // Total pages
    'has_more' => true,      // More results available
]
```

---

## 7. Fallback Search Logic

### 7.1 Fallback Trigger Conditions

| Condition | Fallback Type |
|-----------|---------------|
| API key not configured | Silent fallback |
| API key invalid (401) | Silent fallback + admin notice |
| API rate limited (429) | Silent fallback + retry later |
| API server error (5xx) | Silent fallback after 3 retries |
| Network timeout | Silent fallback |
| No indexed content | Silent fallback |
| Monthly search limit reached | Silent fallback + admin notice |
| Query too short (<3 chars) | WordPress search |

### 7.2 Fallback Implementation

```php
/**
 * Execute fallback to WordPress default search
 * 
 * @param string $query Search query
 * @param string $reason Reason for fallback (for logging)
 * @return WP_Query WordPress query results
 */
public function execute_fallback_search( string $query, string $reason ): WP_Query {
    // Log fallback event
    $this->log_fallback( $query, $reason );
    
    // Increment fallback counter
    $this->increment_fallback_count();
    
    // Execute standard WordPress search
    $args = [
        's' => $query,
        'post_type' => $this->get_indexed_post_types(),
        'post_status' => 'publish',
        'posts_per_page' => $this->get_per_page_setting(),
        'paged' => get_query_var( 'paged', 1 ),
    ];
    
    return new WP_Query( $args );
}
```

### 7.3 Fallback Logging

Each fallback event is logged with:

```php
[
    'timestamp' => '2026-01-08 15:30:00',
    'reason' => 'api_rate_limit',
    'query' => 'search term',
    'recovery_action' => 'retry_scheduled',
    'retry_at' => '2026-01-08 15:31:00',
]
```

### 7.4 Admin Fallback Notification

When fallback rate exceeds threshold (>10% in 24 hours):

```
⚠️ Search Fallback Alert

Semantic search fell back to standard search 45 times in the 
last 24 hours (15% of searches).

Most common reason: API rate limiting

[View Details]  [Adjust Settings]
```

---

## 8. Search Result Formatting

### 8.1 Result Object Structure

```php
/**
 * Search result object structure
 */
[
    'post_id' => 1234,
    'title' => 'Post Title Here',
    'excerpt' => 'The post excerpt with highlighted terms...',
    'url' => 'https://example.com/post-slug/',
    'date' => '2026-01-05',
    'author' => 'Author Name',
    'thumbnail_url' => 'https://example.com/wp-content/...',
    'post_type' => 'post',
    'categories' => ['Category 1', 'Category 2'],
    'similarity_score' => 0.87,
    'display_score' => 74,  // Percentage for display
    'is_semantic' => true,  // vs fallback result
]
```

### 8.2 Excerpt Generation

**Priority order for excerpt source:**

1. Custom excerpt (if exists)
2. Post content (first 200 words)
3. Post title (as fallback)

**Excerpt processing:**

```php
/**
 * Generate search excerpt with highlighting
 * 
 * @param WP_Post $post Post object
 * @param string $query Search query
 * @return string Processed excerpt
 */
public function generate_excerpt( WP_Post $post, string $query ): string {
    // 1. Get raw content
    $content = $post->post_excerpt ?: wp_strip_all_tags( $post->post_content );
    
    // 2. Truncate to ~55 words (WordPress default)
    $excerpt = wp_trim_words( $content, 55 );
    
    // 3. Apply highlighting
    $excerpt = $this->apply_highlighting( $excerpt, $query );
    
    return $excerpt;
}
```

### 8.3 Term Highlighting

```php
/**
 * Highlight matching terms in excerpt
 * 
 * @param string $text Text to highlight
 * @param string $query Search query
 * @return string Text with <mark> tags
 */
public function apply_highlighting( string $text, string $query ): string {
    $terms = explode( ' ', $query );
    
    foreach ( $terms as $term ) {
        if ( strlen( $term ) < 3 ) {
            continue; // Skip short terms
        }
        
        $pattern = '/\b(' . preg_quote( $term, '/' ) . ')\b/i';
        $text = preg_replace( 
            $pattern, 
            '<mark class="intentpress-highlight">$1</mark>', 
            $text 
        );
    }
    
    return $text;
}
```

### 8.4 Highlighting CSS

```css
.intentpress-highlight {
    background-color: #fff3cd;
    padding: 0.1em 0.2em;
    border-radius: 2px;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .intentpress-highlight {
        background-color: #5a4a00;
        color: #fff;
    }
}
```

---

## 9. Caching Strategy

### 9.1 Cache Layers

| Layer | What's Cached | TTL | Storage |
|-------|--------------|-----|---------|
| Query embedding | Embedding vector for query string | 1 hour | Object cache |
| Search results | Full result set for query | 15 min | Transient |
| Post embeddings | Stored in database | Permanent | Custom table |

### 9.2 Cache Key Generation

```php
/**
 * Generate cache key for search query
 */
public function get_query_cache_key( string $query, int $page = 1 ): string {
    $normalized = strtolower( trim( $query ) );
    $hash = md5( $normalized );
    
    return "intentpress_search_{$hash}_page_{$page}";
}

/**
 * Generate cache key for query embedding
 */
public function get_embedding_cache_key( string $query ): string {
    $normalized = strtolower( trim( $query ) );
    return 'intentpress_embed_' . md5( $normalized );
}
```

### 9.3 Cache Invalidation

| Event | Invalidation Action |
|-------|---------------------|
| Post published | Invalidate all search result caches |
| Post updated | Invalidate all search result caches |
| Post deleted | Invalidate all search result caches |
| Settings changed | Invalidate all caches |
| Re-index triggered | Invalidate all caches |

```php
/**
 * Invalidate all search caches
 */
public function invalidate_all_caches(): void {
    global $wpdb;
    
    // Clear transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_intentpress_search_%'
         OR option_name LIKE '_transient_timeout_intentpress_search_%'"
    );
    
    // Clear object cache group
    wp_cache_flush_group( 'intentpress' );
}
```

---

## 10. WordPress Integration Hooks

### 10.1 Search Override Hook

```php
/**
 * Override default WordPress search
 */
add_action( 'pre_get_posts', function( WP_Query $query ) {
    // Only modify main search query on frontend
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
        $handler = IntentPress_Search_Handler::get_instance();
        $handler->intercept_search( $query );
    }
});
```

### 10.2 Filter Hooks Provided

| Filter | Purpose | Parameters |
|--------|---------|------------|
| `intentpress_pre_search` | Modify query before search | `$query` |
| `intentpress_similarity_threshold` | Adjust minimum score | `$threshold` |
| `intentpress_search_results` | Modify results before display | `$results, $query` |
| `intentpress_result_excerpt` | Customize excerpt | `$excerpt, $post, $query` |
| `intentpress_highlight_terms` | Customize highlighting | `$terms, $query` |
| `intentpress_per_page` | Override results per page | `$per_page` |
| `intentpress_use_fallback` | Force fallback search | `$use_fallback, $reason` |

### 10.3 Action Hooks Provided

| Action | Trigger | Parameters |
|--------|---------|------------|
| `intentpress_before_search` | Before search execution | `$query` |
| `intentpress_after_search` | After search completes | `$results, $query, $time_ms` |
| `intentpress_fallback_used` | When fallback is triggered | `$query, $reason` |
| `intentpress_api_error` | When API call fails | `$error, $context` |

### 10.4 Integration Example

```php
// Modify similarity threshold for specific post types
add_filter( 'intentpress_similarity_threshold', function( $threshold ) {
    if ( is_post_type_archive( 'documentation' ) ) {
        return 0.6; // Lower threshold for docs
    }
    return $threshold;
});

// Log all searches to external analytics
add_action( 'intentpress_after_search', function( $results, $query, $time_ms ) {
    my_analytics_track( 'search', [
        'query' => $query,
        'results_count' => count( $results ),
        'duration_ms' => $time_ms,
    ]);
}, 10, 3 );
```

---

## 11. API Contracts

### 11.1 REST Endpoint: Search

**Endpoint:** `POST /wp-json/intentpress/v1/search`

**Request:**

```json
{
    "query": "search terms here",
    "page": 1,
    "per_page": 10
}
```

**Response (Success):**

```json
{
    "success": true,
    "data": {
        "results": [
            {
                "post_id": 1234,
                "title": "Post Title",
                "excerpt": "Excerpt with <mark>highlighted</mark> terms...",
                "url": "https://example.com/post/",
                "date": "2026-01-05T10:30:00",
                "similarity_score": 0.87,
                "display_score": 74
            }
        ],
        "meta": {
            "total": 47,
            "page": 1,
            "per_page": 10,
            "total_pages": 5,
            "search_time_ms": 245,
            "is_semantic": true
        }
    }
}
```

**Response (Error):**

```json
{
    "success": false,
    "error": {
        "code": "intentpress_api_error",
        "message": "Search temporarily unavailable",
        "fallback_used": true
    },
    "data": {
        "results": [...],  // Fallback results if available
        "meta": {
            "is_semantic": false
        }
    }
}
```

### 11.2 REST Endpoint: Test Search (Admin)

**Endpoint:** `POST /wp-json/intentpress/v1/search/test`

**Authentication:** Requires `manage_options` capability

**Request:**

```json
{
    "query": "test search query"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "results": [...],
        "debug": {
            "embedding_time_ms": 180,
            "similarity_time_ms": 45,
            "total_time_ms": 245,
            "posts_compared": 500,
            "cache_hit": false
        }
    }
}
```

---

## 12. Business Rules

### 12.1 Query Rules

| Rule ID | Rule | Implementation |
|---------|------|----------------|
| QR-001 | Query must be at least 3 characters | Validate in handler |
| QR-002 | Query must not exceed 500 characters | Truncate silently |
| QR-003 | Empty queries return error, not results | Return error response |
| QR-004 | Queries are case-insensitive | Normalize before embedding |
| QR-005 | Special characters are preserved | Don't strip meaningful chars |

### 12.2 Result Rules

| Rule ID | Rule | Implementation |
|---------|------|----------------|
| RR-001 | Only published posts are searchable | Filter by `post_status` |
| RR-002 | Password-protected posts are excluded | Filter in query |
| RR-003 | Private posts excluded for non-owners | Check `current_user_can` |
| RR-004 | Minimum similarity 0.5 for display | Filter results |
| RR-005 | Maximum 100 results regardless of pagination | Hard limit |

### 12.3 Fallback Rules

| Rule ID | Rule | Implementation |
|---------|------|----------------|
| FR-001 | Always have working search | Fallback to WordPress |
| FR-002 | Fallback is silent to visitors | No error messages shown |
| FR-003 | Track fallback events | Log to database |
| FR-004 | Notify admin if fallback rate >10% | Admin notice |

### 12.4 Rate Limiting Rules

| Rule ID | Rule | Implementation |
|---------|------|----------------|
| RL-001 | Free tier: 1,000 searches/month | Counter in options |
| RL-002 | Counter resets on 1st of month | Scheduled cron job |
| RL-003 | After limit: fallback without error | Silent switch |
| RL-004 | Admin sees usage in dashboard | Display counter |

---

## 13. Data Models

### 13.1 Embeddings Table Schema

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
    KEY content_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 13.2 Search Log Table Schema

```sql
CREATE TABLE {prefix}intentpress_search_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    query VARCHAR(500) NOT NULL,
    query_hash VARCHAR(32) NOT NULL,
    results_count INT UNSIGNED NOT NULL DEFAULT 0,
    top_score DECIMAL(5,4) DEFAULT NULL,
    response_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
    is_semantic TINYINT(1) NOT NULL DEFAULT 1,
    fallback_reason VARCHAR(50) DEFAULT NULL,
    searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY query_hash (query_hash),
    KEY searched_at (searched_at),
    KEY is_semantic (is_semantic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 13.3 Options Data Model

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `intentpress_api_key` | string (encrypted) | `''` | OpenAI API key |
| `intentpress_indexed_post_types` | array | `['post', 'page']` | Post types to index |
| `intentpress_per_page` | int | `10` | Results per page |
| `intentpress_similarity_threshold` | float | `0.5` | Minimum score |
| `intentpress_fallback_enabled` | bool | `true` | Enable fallback |
| `intentpress_monthly_searches` | int | `0` | Current month counter |
| `intentpress_search_limit` | int | `1000` | Monthly limit (free) |
| `intentpress_last_index_at` | datetime | `null` | Last index timestamp |
| `intentpress_indexed_count` | int | `0` | Number indexed |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial functional requirements |
