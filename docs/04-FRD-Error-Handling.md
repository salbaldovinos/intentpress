# Functional Requirements Document: Error Handling & System Reliability
## IntentPress MVP

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related PRDs:** PRD-Error-Handling.md, PRD-Overview.md, PRD-User-Stories.md  
**Related User Stories:** US-501, US-502, US-503, US-504, US-505

---

## Table of Contents

1. [Overview](#1-overview)
2. [Error Handling Architecture](#2-error-handling-architecture)
3. [Error Classification System](#3-error-classification-system)
4. [API Error Handling](#4-api-error-handling)
5. [Configuration Error Handling](#5-configuration-error-handling)
6. [Search Error Handling](#6-search-error-handling)
7. [Indexing Error Handling](#7-indexing-error-handling)
8. [Limit & Quota Error Handling](#8-limit--quota-error-handling)
9. [WordPress Compatibility Errors](#9-wordpress-compatibility-errors)
10. [Edge Case Handling](#10-edge-case-handling)
11. [Error Logging System](#11-error-logging-system)
12. [Recovery Mechanisms](#12-recovery-mechanisms)
13. [API Contracts](#13-api-contracts)
14. [Data Models](#14-data-models)
15. [User Interface Components](#15-user-interface-components)

---

## 1. Overview

### 1.1 Purpose

This document specifies the functional requirements for IntentPress's error handling and system reliability features. It details how errors are detected, categorized, communicated, logged, and recovered from across all plugin components.

### 1.2 Core Principles

| Principle | Description | Implementation |
|-----------|-------------|----------------|
| **Never Break the Site** | Errors must never cause site failure | Graceful degradation to WordPress defaults |
| **Be Specific and Actionable** | Tell users what went wrong AND what to do | Structured error messages with actions |
| **Log for Debugging** | Technical details for developers | Comprehensive logging system |
| **Display for Users** | Clear, non-technical messages | Human-readable error UI |
| **Fail Silently for Visitors** | No error exposure to site visitors | Transparent fallback |
| **Fail Loudly for Admins** | Clear notification of issues | Admin notices and dashboard alerts |

### 1.3 Scope

| In Scope | Out of Scope |
|----------|--------------|
| API error detection and recovery | Third-party plugin error handling |
| Configuration validation | WordPress core error handling |
| Search graceful degradation | Server-level error handling |
| Indexing error recovery | CDN/proxy error handling |
| Limit enforcement and notification | Payment processing errors |
| Error logging and debugging | Email delivery errors |
| WordPress compatibility checks | External service errors (non-OpenAI) |

### 1.4 Actors

| Actor | Error Visibility | Actions Available |
|-------|------------------|-------------------|
| Site Visitor | None (silent fallback) | None |
| Site Administrator | Full (notices, dashboard, logs) | Retry, configure, view logs |
| Background Process | Logs only | Auto-retry, auto-recover |
| External API (OpenAI) | Logs only | N/A |

---

## 2. Error Handling Architecture

### 2.1 System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ERROR HANDLING ARCHITECTURE                           │
└─────────────────────────────────────────────────────────────────────────────┘

                              ┌───────────────┐
                              │   Error       │
                              │   Source      │
                              └───────┬───────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ERROR HANDLER                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  Detection  │──│  Classify   │──│  Process    │──│  Respond    │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
└─────────────────────────────────────────────────────────────────────────────┘
          │                │                │                │
          ▼                ▼                ▼                ▼
   ┌───────────┐    ┌───────────┐    ┌───────────┐    ┌───────────┐
   │  Logger   │    │  Recovery │    │  Fallback │    │  Notifier │
   └───────────┘    └───────────┘    └───────────┘    └───────────┘
          │                │                │                │
          ▼                ▼                ▼                ▼
   ┌───────────┐    ┌───────────┐    ┌───────────┐    ┌───────────┐
   │   Logs    │    │  Retry    │    │ WordPress │    │  Admin    │
   │  Storage  │    │  Queue    │    │  Default  │    │  Notice   │
   └───────────┘    └───────────┘    └───────────┘    └───────────┘
```

### 2.2 Component Responsibilities

| Component | Responsibility | Class |
|-----------|----------------|-------|
| Error Handler | Central error processing | `IntentPress_Error_Handler` |
| Error Logger | Log storage and retrieval | `IntentPress_Error_Logger` |
| Error Notifier | Admin notifications | `IntentPress_Error_Notifier` |
| Recovery Manager | Retry and recovery logic | `IntentPress_Recovery_Manager` |
| Fallback Handler | Graceful degradation | `IntentPress_Fallback_Handler` |
| Health Monitor | System health checks | `IntentPress_Health_Monitor` |

### 2.3 Error Response Structure

All errors follow this standardized structure:

```php
<?php
/**
 * Standard error response structure
 */
class IntentPress_Error {
    /** @var string Machine-readable error code */
    public string $code;
    
    /** @var string User-facing error message */
    public string $message;
    
    /** @var string Actionable instruction for user */
    public string $action;
    
    /** @var string Technical details (logs only) */
    public string $technical_info;
    
    /** @var bool Can user resolve this error? */
    public bool $recoverable;
    
    /** @var int|null Seconds until retry (if applicable) */
    public ?int $retry_after;
    
    /** @var string Error severity level */
    public string $severity; // critical, error, warning, info
    
    /** @var string Error category */
    public string $category; // api, config, search, index, limit, system
    
    /** @var array Additional context data */
    public array $context;
}
```

### 2.4 TypeScript Interface

```typescript
interface IntentPressError {
  code: string;
  message: string;
  action: string;
  technicalInfo?: string;
  recoverable: boolean;
  retryAfter?: number;
  severity: 'critical' | 'error' | 'warning' | 'info';
  category: 'api' | 'config' | 'search' | 'index' | 'limit' | 'system';
  context?: Record<string, unknown>;
}

interface ErrorResponse {
  success: false;
  error: IntentPressError;
}
```

---

## 3. Error Classification System

### 3.1 Error Categories

| Category | Code Prefix | User Impact | Admin Visibility | Recovery |
|----------|-------------|-------------|------------------|----------|
| API Errors | `api_*` | Search degraded | Dashboard + Notice | Often automatic |
| Configuration Errors | `config_*` | Features disabled | Admin notices | Manual fix required |
| Search Errors | `search_*` | Fallback used | Silent to visitors | Automatic fallback |
| Indexing Errors | `index_*` | Partial index | Dashboard | Retry available |
| Limit Errors | `limit_*` | Features restricted | Admin + possibly visitor | Upgrade or wait |
| System Errors | `system_*` | Plugin may not work | Admin notices | Environment change |

### 3.2 Severity Levels

| Level | Code | Icon | Color | CSS Class | Meaning |
|-------|------|------|-------|-----------|---------|
| Critical | 1 | ❌ | `#dc3545` | `intentpress-error-critical` | Plugin non-functional |
| Error | 2 | ⚠️ | `#fd7e14` | `intentpress-error-error` | Feature broken |
| Warning | 3 | ⚡ | `#ffc107` | `intentpress-error-warning` | Degraded experience |
| Info | 4 | ℹ️ | `#0dcaf0` | `intentpress-error-info` | Informational |

### 3.3 Error Classification Logic

```php
<?php
class IntentPress_Error_Classifier {
    
    /**
     * Classify error by HTTP response or exception
     */
    public function classify( $error_source ): IntentPress_Error {
        // API errors from HTTP responses
        if ( $error_source instanceof WP_Error ) {
            return $this->classify_wp_error( $error_source );
        }
        
        // HTTP response errors
        if ( is_array( $error_source ) && isset( $error_source['response'] ) ) {
            return $this->classify_http_response( $error_source );
        }
        
        // Exception errors
        if ( $error_source instanceof Exception ) {
            return $this->classify_exception( $error_source );
        }
        
        // Unknown error type
        return new IntentPress_Error([
            'code'       => 'unknown_error',
            'message'    => __( 'An unexpected error occurred.', 'intentpress' ),
            'action'     => __( 'Please try again or contact support.', 'intentpress' ),
            'severity'   => 'error',
            'category'   => 'system',
            'recoverable' => true,
        ]);
    }
    
    /**
     * Classify HTTP response errors
     */
    private function classify_http_response( array $response ): IntentPress_Error {
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );
        
        $error_map = [
            401 => [
                'code'       => 'api_key_invalid',
                'message'    => __( 'Your API key appears to be invalid.', 'intentpress' ),
                'action'     => __( 'Please check your API key in Settings → IntentPress.', 'intentpress' ),
                'severity'   => 'error',
                'recoverable' => true,
            ],
            429 => $this->classify_rate_limit( $decoded ),
            500 => [
                'code'       => 'api_server_error',
                'message'    => __( 'AI service temporarily unavailable.', 'intentpress' ),
                'action'     => __( 'We\'re automatically retrying. Standard search is being used.', 'intentpress' ),
                'severity'   => 'warning',
                'recoverable' => true,
            ],
            502 => [
                'code'       => 'api_gateway_error',
                'message'    => __( 'AI service experiencing issues.', 'intentpress' ),
                'action'     => __( 'Standard search is being used in the meantime.', 'intentpress' ),
                'severity'   => 'warning',
                'recoverable' => true,
            ],
            503 => [
                'code'       => 'api_unavailable',
                'message'    => __( 'AI service is temporarily unavailable.', 'intentpress' ),
                'action'     => __( 'Standard search is being used. We\'ll retry automatically.', 'intentpress' ),
                'severity'   => 'warning',
                'recoverable' => true,
            ],
        ];
        
        $error_data = $error_map[ $status_code ] ?? [
            'code'       => 'api_unknown_error',
            'message'    => sprintf( __( 'API returned status %d.', 'intentpress' ), $status_code ),
            'action'     => __( 'Please try again later.', 'intentpress' ),
            'severity'   => 'error',
            'recoverable' => true,
        ];
        
        $error_data['category'] = 'api';
        $error_data['technical_info'] = sprintf( 'HTTP %d from api.openai.com', $status_code );
        $error_data['context'] = [
            'http_code' => $status_code,
            'response'  => $decoded,
        ];
        
        return new IntentPress_Error( $error_data );
    }
    
    /**
     * Differentiate rate limit vs quota exceeded
     */
    private function classify_rate_limit( ?array $response_body ): array {
        $message = $response_body['error']['message'] ?? '';
        
        // Check if it's quota exceeded vs rate limit
        if ( stripos( $message, 'quota' ) !== false || stripos( $message, 'billing' ) !== false ) {
            return [
                'code'        => 'api_quota_exceeded',
                'message'     => __( 'Your OpenAI account has reached its spending limit.', 'intentpress' ),
                'action'      => __( 'Please check your billing settings at platform.openai.com.', 'intentpress' ),
                'severity'    => 'error',
                'recoverable' => false,
            ];
        }
        
        return [
            'code'        => 'api_rate_limit',
            'message'     => __( 'Search is temporarily limited.', 'intentpress' ),
            'action'      => __( 'Trying again in a moment...', 'intentpress' ),
            'severity'    => 'warning',
            'recoverable' => true,
            'retry_after' => $this->calculate_retry_delay(),
        ];
    }
}
```

---

## 4. API Error Handling

### 4.1 API Error Types Matrix

| Error Type | HTTP Code | Retryable | Max Retries | Fallback | Admin Notice | Visitor Impact |
|------------|-----------|-----------|-------------|----------|--------------|----------------|
| Invalid API Key | 401 | No | 0 | Yes | Persistent | None (fallback) |
| Rate Limit | 429 | Yes (delay) | 5 | Yes | Temporary | None (fallback) |
| Quota Exceeded | 429 | No | 0 | Yes | Persistent | None (fallback) |
| Server Error | 5xx | Yes | 3 | Yes | If persistent | None (fallback) |
| Timeout | - | Yes | 2 | Yes | If frequent | None (fallback) |
| DNS Failure | - | Yes | 1 | Yes | Persistent | None (fallback) |
| SSL Error | - | No | 0 | Yes | Persistent | None (fallback) |

### 4.2 Authentication Error Handler (HTTP 401)

```php
<?php
class IntentPress_Auth_Error_Handler {
    
    /**
     * Handle authentication failures
     */
    public function handle( IntentPress_Error $error ): void {
        // Disable semantic search
        $this->disable_semantic_search();
        
        // Clear cached API key validation
        delete_transient( 'intentpress_api_key_valid' );
        
        // Display admin notice
        $this->show_admin_notice([
            'type'    => 'error',
            'message' => sprintf(
                '<strong>%s</strong><p>%s</p><p><a href="%s" class="button">%s</a></p>',
                __( 'API Authentication Failed', 'intentpress' ),
                $error->message . ' ' . $error->action,
                admin_url( 'options-general.php?page=intentpress' ),
                __( 'Configure API Key', 'intentpress' )
            ),
            'dismissible' => false,
        ]);
        
        // Log error
        $this->log_error( $error );
        
        // Update dashboard status
        update_option( 'intentpress_api_status', 'invalid' );
    }
    
    /**
     * Disable semantic search and enable fallback
     */
    private function disable_semantic_search(): void {
        update_option( 'intentpress_semantic_enabled', false );
        update_option( 'intentpress_fallback_active', true );
    }
}
```

### 4.3 Rate Limit Handler (HTTP 429)

```php
<?php
class IntentPress_Rate_Limit_Handler {
    
    private const MAX_RETRIES = 5;
    private const MAX_BACKOFF_SECONDS = 60;
    
    /**
     * Handle rate limit with exponential backoff
     */
    public function handle( IntentPress_Error $error, int $attempt = 1 ): ?array {
        if ( $attempt > self::MAX_RETRIES ) {
            // Max retries exceeded, use fallback
            return $this->trigger_fallback( $error );
        }
        
        // Calculate delay with exponential backoff + jitter
        $delay = $this->calculate_backoff_delay( $attempt );
        
        // Store rate limit state
        set_transient( 
            'intentpress_rate_limited', 
            [
                'until'   => time() + $delay,
                'attempt' => $attempt,
            ],
            $delay 
        );
        
        // Log rate limit event
        $this->log_rate_limit( $error, $delay, $attempt );
        
        // Return delay info for caller
        return [
            'retry_after' => $delay,
            'attempt'     => $attempt,
            'max_retries' => self::MAX_RETRIES,
        ];
    }
    
    /**
     * Calculate exponential backoff with jitter
     * Formula: min(2^attempt + random(0-1), MAX_BACKOFF)
     */
    private function calculate_backoff_delay( int $attempt ): int {
        $base_delay = pow( 2, $attempt );
        $jitter = random_int( 0, 1000 ) / 1000; // 0-1 second jitter
        
        return (int) min( $base_delay + $jitter, self::MAX_BACKOFF_SECONDS );
    }
    
    /**
     * Check if currently rate limited
     */
    public function is_rate_limited(): bool {
        $rate_limit = get_transient( 'intentpress_rate_limited' );
        
        if ( ! $rate_limit ) {
            return false;
        }
        
        return time() < $rate_limit['until'];
    }
    
    /**
     * Get remaining cooldown time
     */
    public function get_cooldown_remaining(): int {
        $rate_limit = get_transient( 'intentpress_rate_limited' );
        
        if ( ! $rate_limit ) {
            return 0;
        }
        
        return max( 0, $rate_limit['until'] - time() );
    }
}
```

### 4.4 Timeout Handler

```php
<?php
class IntentPress_Timeout_Handler {
    
    /**
     * Timeout thresholds by operation type (in seconds)
     */
    private const TIMEOUTS = [
        'embedding_generation' => 30,  // Batch indexing
        'search_query'         => 10,  // Single search
        'api_validation'       => 5,   // Key validation
    ];
    
    /**
     * Handle timeout error
     */
    public function handle( string $operation, IntentPress_Error $error ): void {
        // Log timeout
        $this->log_timeout( $operation, $error );
        
        // Track timeout frequency
        $this->track_timeout_frequency();
        
        // Show admin notice if frequent
        if ( $this->is_timeout_frequent() ) {
            $this->show_timeout_warning();
        }
        
        // Trigger fallback for search operations
        if ( $operation === 'search_query' ) {
            do_action( 'intentpress_trigger_fallback', 'timeout' );
        }
    }
    
    /**
     * Track timeout frequency (last hour)
     */
    private function track_timeout_frequency(): void {
        $timeouts = get_transient( 'intentpress_timeout_count' ) ?: [];
        $timeouts[] = time();
        
        // Keep only last hour
        $timeouts = array_filter( $timeouts, function( $t ) {
            return $t > ( time() - HOUR_IN_SECONDS );
        });
        
        set_transient( 'intentpress_timeout_count', $timeouts, HOUR_IN_SECONDS );
    }
    
    /**
     * Check if timeouts are occurring frequently (>5 per hour)
     */
    private function is_timeout_frequent(): bool {
        $timeouts = get_transient( 'intentpress_timeout_count' ) ?: [];
        return count( $timeouts ) > 5;
    }
    
    /**
     * Get timeout for operation type
     */
    public static function get_timeout( string $operation ): int {
        return self::TIMEOUTS[ $operation ] ?? 10;
    }
}
```

---

## 5. Configuration Error Handling

### 5.1 Missing API Key Handler

```php
<?php
class IntentPress_Config_Error_Handler {
    
    /**
     * Handle missing API key
     */
    public function handle_missing_key(): void {
        // Show admin notice
        add_action( 'admin_notices', function() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            
            $onboarding_url = admin_url( 'options-general.php?page=intentpress&tab=onboarding' );
            
            printf(
                '<div class="notice notice-info intentpress-notice">
                    <p><strong>%s</strong></p>
                    <p>%s</p>
                    <p><a href="%s" class="button button-primary">%s</a></p>
                </div>',
                esc_html__( 'IntentPress needs configuration', 'intentpress' ),
                esc_html__( 'Enter your OpenAI API key to enable semantic search.', 'intentpress' ),
                esc_url( $onboarding_url ),
                esc_html__( 'Configure Now →', 'intentpress' )
            );
        });
        
        // Search falls back automatically when no key is configured
        add_filter( 'intentpress_search_enabled', '__return_false' );
    }
    
    /**
     * Handle invalid post type configuration
     */
    public function handle_invalid_post_type( string $post_type ): IntentPress_Error {
        // Remove invalid post type from settings
        $post_types = get_option( 'intentpress_post_types', ['post', 'page'] );
        $post_types = array_diff( $post_types, [ $post_type ] );
        update_option( 'intentpress_post_types', array_values( $post_types ) );
        
        // Log warning
        $error = new IntentPress_Error([
            'code'       => 'config_invalid_post_type',
            'message'    => sprintf( 
                __( 'The post type "%s" is configured for indexing but no longer exists.', 'intentpress' ),
                $post_type 
            ),
            'action'     => __( 'This post type has been automatically removed from indexing.', 'intentpress' ),
            'severity'   => 'warning',
            'category'   => 'config',
            'recoverable' => true,
            'context'    => [ 'post_type' => $post_type ],
        ]);
        
        do_action( 'intentpress_error_logged', $error );
        
        return $error;
    }
    
    /**
     * Handle encryption key issues
     */
    public function handle_decryption_failure(): void {
        // Clear invalid encrypted key
        delete_option( 'intentpress_api_key_encrypted' );
        
        // Show admin notice
        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-error intentpress-notice">
                    <p><strong>%s</strong></p>
                    <p>%s</p>
                    <p><a href="%s" class="button">%s</a></p>
                </div>',
                esc_html__( 'API Key Decryption Failed', 'intentpress' ),
                esc_html__( 'Your API key couldn\'t be decrypted. This usually happens when WordPress security keys have been changed.', 'intentpress' ),
                esc_url( admin_url( 'options-general.php?page=intentpress' ) ),
                esc_html__( 'Re-enter API Key →', 'intentpress' )
            );
        });
        
        // Log security event
        do_action( 'intentpress_security_event', 'decryption_failure' );
    }
}
```

### 5.2 Configuration Validation

```php
<?php
class IntentPress_Config_Validator {
    
    /**
     * Validate all configuration
     * 
     * @return array Array of IntentPress_Error objects
     */
    public function validate_all(): array {
        $errors = [];
        
        // Check API key
        if ( ! $this->has_api_key() ) {
            $errors[] = new IntentPress_Error([
                'code'       => 'config_api_key_missing',
                'message'    => __( 'API key is required.', 'intentpress' ),
                'action'     => __( 'Please enter your API key in Settings → IntentPress.', 'intentpress' ),
                'severity'   => 'error',
                'category'   => 'config',
                'recoverable' => true,
            ]);
        }
        
        // Check post types
        $post_types = get_option( 'intentpress_post_types', ['post', 'page'] );
        foreach ( $post_types as $type ) {
            if ( ! post_type_exists( $type ) ) {
                $errors[] = $this->handle_invalid_post_type( $type );
            }
        }
        
        // Check database tables
        if ( ! $this->tables_exist() ) {
            $errors[] = new IntentPress_Error([
                'code'       => 'config_database_tables',
                'message'    => __( 'Database tables are missing.', 'intentpress' ),
                'action'     => __( 'Please deactivate and reactivate the plugin.', 'intentpress' ),
                'severity'   => 'critical',
                'category'   => 'system',
                'recoverable' => true,
            ]);
        }
        
        return $errors;
    }
}
```

---

## 6. Search Error Handling

### 6.1 Search Error Types

| Error Code | Trigger | User Message | Behavior |
|------------|---------|--------------|----------|
| `search_empty` | Empty query submitted | "Please enter a search term." | Show prompt |
| `search_too_short` | Query < 3 chars | "Please enter at least 3 characters." | WordPress fallback |
| `search_too_long` | Query > 500 chars | Silent | Truncate and proceed |
| `search_no_results` | No matches found | "No results found for '[query]'" | Show suggestions |
| `search_fallback_used` | API error during search | Silent | Use WordPress search |

### 6.2 Search Error Handler

```php
<?php
class IntentPress_Search_Error_Handler {
    
    /**
     * Handle search query validation errors
     */
    public function validate_query( string $query ): ?IntentPress_Error {
        $query = trim( $query );
        
        // Empty query
        if ( empty( $query ) ) {
            return new IntentPress_Error([
                'code'       => 'search_empty',
                'message'    => __( 'Please enter a search term.', 'intentpress' ),
                'action'     => __( 'Try searching for topics, questions, or keywords related to the content you\'re looking for.', 'intentpress' ),
                'severity'   => 'info',
                'category'   => 'search',
                'recoverable' => true,
            ]);
        }
        
        // Too short
        $min_length = apply_filters( 'intentpress_min_query_length', 3 );
        if ( mb_strlen( $query ) < $min_length ) {
            return new IntentPress_Error([
                'code'       => 'search_too_short',
                'message'    => sprintf(
                    __( 'Please enter at least %d characters to search.', 'intentpress' ),
                    $min_length
                ),
                'action'     => __( 'Short searches may not find relevant results.', 'intentpress' ),
                'severity'   => 'info',
                'category'   => 'search',
                'recoverable' => true,
            ]);
        }
        
        return null; // No error
    }
    
    /**
     * Handle no results scenario
     */
    public function handle_no_results( string $query ): array {
        // Log for analytics
        $this->log_no_results_query( $query );
        
        // Get suggestions
        $suggestions = $this->get_search_suggestions( $query );
        $categories = $this->get_category_links();
        
        return [
            'error' => new IntentPress_Error([
                'code'       => 'search_no_results',
                'message'    => sprintf(
                    __( 'No results found for "%s"', 'intentpress' ),
                    esc_html( $query )
                ),
                'action'     => __( 'Try different keywords, use broader terms, or check your spelling.', 'intentpress' ),
                'severity'   => 'info',
                'category'   => 'search',
                'recoverable' => true,
            ]),
            'suggestions'    => $suggestions,
            'categories'     => $categories,
            'popular_posts'  => $this->get_popular_posts( 5 ),
        ];
    }
    
    /**
     * Handle fallback trigger
     */
    public function trigger_fallback( string $reason, string $query ): WP_Query {
        // Log fallback event
        do_action( 'intentpress_fallback_triggered', [
            'reason' => $reason,
            'query'  => $query,
            'time'   => current_time( 'mysql' ),
        ]);
        
        // Execute WordPress default search
        return new WP_Query([
            's'              => $query,
            'posts_per_page' => get_option( 'intentpress_results_per_page', 10 ),
            'post_type'      => get_option( 'intentpress_post_types', ['post', 'page'] ),
            'post_status'    => 'publish',
        ]);
    }
}
```

### 6.3 Search Fallback Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SEARCH FALLBACK FLOW                                 │
└─────────────────────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │  Search Request  │
                    └────────┬─────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │ Validate Query   │
                    └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
        ┌──────────┐  ┌──────────┐  ┌──────────┐
        │  Empty   │  │ Too Short│  │  Valid   │
        └────┬─────┘  └────┬─────┘  └────┬─────┘
             │              │              │
             ▼              ▼              ▼
        ┌──────────┐  ┌──────────┐  ┌──────────┐
        │Show Prompt│  │ WP Search│  │ Check    │
        └──────────┘  └──────────┘  │ API      │
                                    └────┬─────┘
                                         │
                           ┌─────────────┼─────────────┐
                           │             │             │
                           ▼             ▼             ▼
                     ┌──────────┐  ┌──────────┐  ┌──────────┐
                     │ API OK   │  │ API Error│  │ No Key   │
                     └────┬─────┘  └────┬─────┘  └────┬─────┘
                          │             │             │
                          ▼             ▼             ▼
                     ┌──────────┐  ┌──────────┐  ┌──────────┐
                     │ Semantic │  │ Fallback │  │ Fallback │
                     │ Search   │  │ WP Search│  │ WP Search│
                     └──────────┘  └──────────┘  └──────────┘
```

---

## 7. Indexing Error Handling

### 7.1 Indexing Error Types

| Error Code | Trigger | Severity | Action |
|------------|---------|----------|--------|
| `index_in_progress` | Indexing already running | Info | Wait |
| `index_post_failed` | Single post embedding failed | Warning | Skip, continue |
| `index_batch_failed` | 5+ consecutive failures | Error | Pause, offer retry |
| `index_timeout` | Overall process timeout | Warning | Continue in background |
| `index_database_error` | Can't store embeddings | Critical | Stop, show error |
| `index_content_parse` | Content extraction failed | Warning | Use title only |

### 7.2 Indexing Error Handler

```php
<?php
class IntentPress_Index_Error_Handler {
    
    private const MAX_CONSECUTIVE_FAILURES = 5;
    
    private int $consecutive_failures = 0;
    private array $failed_posts = [];
    
    /**
     * Handle individual post indexing failure
     */
    public function handle_post_failure( int $post_id, IntentPress_Error $error ): bool {
        $this->consecutive_failures++;
        $this->failed_posts[] = [
            'post_id' => $post_id,
            'title'   => get_the_title( $post_id ),
            'error'   => $error->code,
            'message' => $error->message,
        ];
        
        // Log failure
        $this->log_post_failure( $post_id, $error );
        
        // Check if we should pause
        if ( $this->consecutive_failures >= self::MAX_CONSECUTIVE_FAILURES ) {
            return $this->pause_indexing( $error );
        }
        
        // Continue with next post
        return true;
    }
    
    /**
     * Reset consecutive failure counter on success
     */
    public function handle_post_success( int $post_id ): void {
        $this->consecutive_failures = 0;
    }
    
    /**
     * Pause indexing due to repeated failures
     */
    private function pause_indexing( IntentPress_Error $last_error ): bool {
        $state = get_option( 'intentpress_index_state', [] );
        
        $state['status'] = 'paused';
        $state['paused_at'] = current_time( 'mysql' );
        $state['pause_reason'] = 'consecutive_failures';
        $state['failed_posts'] = $this->failed_posts;
        $state['last_error'] = [
            'code'    => $last_error->code,
            'message' => $last_error->message,
        ];
        
        update_option( 'intentpress_index_state', $state );
        
        // Notify admin
        do_action( 'intentpress_indexing_paused', $state );
        
        return false; // Stop indexing
    }
    
    /**
     * Handle database write failure
     */
    public function handle_database_failure( \Exception $e ): void {
        global $wpdb;
        
        $error = new IntentPress_Error([
            'code'          => 'index_database_error',
            'message'       => __( 'Unable to save search index to database.', 'intentpress' ),
            'action'        => __( 'Please contact your hosting provider or check your database status.', 'intentpress' ),
            'technical_info' => sprintf( 
                'MySQL Error %s: %s', 
                $wpdb->last_error ? 'unknown' : $wpdb->last_error,
                $e->getMessage()
            ),
            'severity'      => 'critical',
            'category'      => 'index',
            'recoverable'   => false,
        ]);
        
        // Stop indexing immediately
        $this->stop_indexing( $error );
        
        // Show critical notice
        $this->show_critical_notice( $error );
    }
    
    /**
     * Handle content parsing errors
     */
    public function handle_content_parse_error( int $post_id, string $reason ): ?string {
        $title = get_the_title( $post_id );
        
        // Log warning
        $this->log_warning([
            'code'    => 'index_content_parse',
            'post_id' => $post_id,
            'title'   => $title,
            'reason'  => $reason,
        ]);
        
        // Try to index with just title
        if ( ! empty( $title ) ) {
            return $title . ' ' . $title; // Double title for emphasis
        }
        
        // Skip post entirely
        return null;
    }
    
    /**
     * Get indexing error summary
     */
    public function get_error_summary(): array {
        return [
            'total_failed'         => count( $this->failed_posts ),
            'consecutive_failures' => $this->consecutive_failures,
            'failed_posts'         => $this->failed_posts,
            'is_paused'            => $this->consecutive_failures >= self::MAX_CONSECUTIVE_FAILURES,
        ];
    }
}
```

### 7.3 Indexing State Machine with Error States

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    INDEXING STATE MACHINE WITH ERRORS                        │
└─────────────────────────────────────────────────────────────────────────────┘

                           ┌──────────┐
                           │   IDLE   │
                           └────┬─────┘
                                │ start()
                                ▼
                           ┌──────────┐
                           │ RUNNING  │◀───────────────┐
                           └────┬─────┘                │
                                │                      │
            ┌───────────────────┼───────────────────┐  │
            │                   │                   │  │
            ▼                   ▼                   ▼  │
      ┌──────────┐        ┌──────────┐        ┌──────────┐
      │ COMPLETE │        │  ERROR   │        │ PAUSED   │
      └──────────┘        └────┬─────┘        └────┬─────┘
                               │                   │
                               │                   │ retry()
                               ▼                   │
                          ┌──────────┐             │
                          │ STOPPED  │             │
                          └──────────┘             │
                               │                   │
                               │ retry()           │
                               └───────────────────┘
```

---

## 8. Limit & Quota Error Handling

### 8.1 Limit Types

| Limit Type | Free Tier Value | Behavior When Reached | Reset |
|------------|-----------------|----------------------|-------|
| Post Index Limit | 500 posts | Oldest posts excluded | Upgrade |
| Monthly Search Limit | 1,000 searches | Fallback to WordPress | 1st of month |
| API Rate Limit | 60 req/min | Backoff + retry | 1 minute |

### 8.2 Limit Handler

```php
<?php
class IntentPress_Limit_Handler {
    
    private const FREE_POST_LIMIT = 500;
    private const FREE_SEARCH_LIMIT = 1000;
    
    /**
     * Check and handle post limit
     */
    public function check_post_limit(): ?IntentPress_Error {
        $total_posts = $this->count_indexable_posts();
        
        if ( $total_posts <= self::FREE_POST_LIMIT ) {
            return null;
        }
        
        return new IntentPress_Error([
            'code'       => 'limit_posts_reached',
            'message'    => sprintf(
                __( 'Free tier limit reached (%d posts).', 'intentpress' ),
                self::FREE_POST_LIMIT
            ),
            'action'     => __( 'Upgrade to index all content.', 'intentpress' ),
            'severity'   => 'warning',
            'category'   => 'limit',
            'recoverable' => false,
            'context'    => [
                'total_posts'   => $total_posts,
                'indexed_posts' => self::FREE_POST_LIMIT,
                'excluded'      => $total_posts - self::FREE_POST_LIMIT,
            ],
        ]);
    }
    
    /**
     * Check and handle search limit
     */
    public function check_search_limit(): ?IntentPress_Error {
        $usage = $this->get_monthly_usage();
        
        // At limit
        if ( $usage['searches'] >= self::FREE_SEARCH_LIMIT ) {
            // Enable fallback mode
            update_option( 'intentpress_fallback_active', true );
            
            return new IntentPress_Error([
                'code'       => 'limit_searches_reached',
                'message'    => __( 'Monthly search limit reached.', 'intentpress' ),
                'action'     => sprintf(
                    __( 'Standard WordPress search is now active until %s.', 'intentpress' ),
                    $this->get_reset_date()
                ),
                'severity'   => 'warning',
                'category'   => 'limit',
                'recoverable' => false,
                'context'    => [
                    'used'       => $usage['searches'],
                    'limit'      => self::FREE_SEARCH_LIMIT,
                    'reset_date' => $this->get_reset_date(),
                ],
            ]);
        }
        
        // Approaching limit (80%)
        if ( $usage['searches'] >= ( self::FREE_SEARCH_LIMIT * 0.8 ) ) {
            return new IntentPress_Error([
                'code'       => 'limit_approaching',
                'message'    => sprintf(
                    __( 'Approaching search limit (%d%% used).', 'intentpress' ),
                    round( ( $usage['searches'] / self::FREE_SEARCH_LIMIT ) * 100 )
                ),
                'action'     => __( 'Consider upgrading for unlimited searches.', 'intentpress' ),
                'severity'   => 'info',
                'category'   => 'limit',
                'recoverable' => false,
            ]);
        }
        
        return null;
    }
    
    /**
     * Increment search counter
     */
    public function increment_search_count(): void {
        $month_key = 'intentpress_searches_' . gmdate( 'Y_m' );
        $count = get_option( $month_key, 0 );
        update_option( $month_key, $count + 1 );
    }
    
    /**
     * Get monthly usage statistics
     */
    public function get_monthly_usage(): array {
        $month_key = 'intentpress_searches_' . gmdate( 'Y_m' );
        
        return [
            'searches'   => (int) get_option( $month_key, 0 ),
            'limit'      => self::FREE_SEARCH_LIMIT,
            'remaining'  => max( 0, self::FREE_SEARCH_LIMIT - (int) get_option( $month_key, 0 ) ),
            'percentage' => round( ( (int) get_option( $month_key, 0 ) / self::FREE_SEARCH_LIMIT ) * 100 ),
            'reset_date' => $this->get_reset_date(),
        ];
    }
    
    /**
     * Get limit reset date (1st of next month)
     */
    private function get_reset_date(): string {
        return gmdate( 'F 1, Y', strtotime( 'first day of next month' ) );
    }
}
```

### 8.3 Limit Warning UI Component

```typescript
// src/admin/components/LimitWarning.tsx
import React from 'react';
import { Notice, Button, Flex, FlexItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface LimitWarningProps {
  type: 'posts' | 'searches';
  used: number;
  limit: number;
  resetDate?: string;
}

export const LimitWarning: React.FC<LimitWarningProps> = ({
  type,
  used,
  limit,
  resetDate,
}) => {
  const percentage = Math.round((used / limit) * 100);
  const isExceeded = used >= limit;
  const isApproaching = percentage >= 80;

  if (!isApproaching) {
    return null;
  }

  const getMessage = (): string => {
    if (isExceeded) {
      return type === 'posts'
        ? __(`Free tier limit reached (${limit} posts). Upgrade to index all content.`, 'intentpress')
        : __(`Monthly search limit reached. Standard search active until ${resetDate}.`, 'intentpress');
    }
    return __(`Approaching ${type} limit (${percentage}% used).`, 'intentpress');
  };

  return (
    <Notice
      status={isExceeded ? 'warning' : 'info'}
      isDismissible={false}
      className="intentpress-limit-warning"
    >
      <Flex align="center" gap={4}>
        <FlexItem>
          <span className="dashicons dashicons-warning" />
        </FlexItem>
        <FlexItem>
          <p>{getMessage()}</p>
          {/* Progress bar */}
          <div className="intentpress-limit-progress">
            <div 
              className="intentpress-limit-progress-bar"
              style={{ 
                width: `${Math.min(percentage, 100)}%`,
                backgroundColor: isExceeded ? '#dc3545' : isApproaching ? '#ffc107' : '#28a745',
              }}
            />
          </div>
          <p className="intentpress-limit-stats">
            {used.toLocaleString()} / {limit.toLocaleString()} {type}
          </p>
        </FlexItem>
        <FlexItem>
          <Button
            variant="primary"
            href="https://intentpress.com/upgrade"
            target="_blank"
          >
            {__('Upgrade', 'intentpress')}
          </Button>
        </FlexItem>
      </Flex>
    </Notice>
  );
};
```

---

## 9. WordPress Compatibility Errors

### 9.1 Compatibility Requirements

| Requirement | Minimum | Check Method | Error Code |
|-------------|---------|--------------|------------|
| PHP Version | 8.0 | `phpversion()` | `system_php_version` |
| WordPress Version | 6.4 | `get_bloginfo('version')` | `system_wp_version` |
| REST API | Enabled | Test endpoint | `system_rest_disabled` |
| Database | MySQL 5.7+ / MariaDB 10.3+ | Query version | `system_db_version` |
| JSON Extension | Enabled | `extension_loaded('json')` | `system_json_missing` |

### 9.2 Compatibility Checker

```php
<?php
class IntentPress_Compatibility_Checker {
    
    private const MIN_PHP_VERSION = '8.0';
    private const MIN_WP_VERSION = '6.4';
    
    /**
     * Run all compatibility checks
     * 
     * @return array Array of IntentPress_Error objects
     */
    public function check_all(): array {
        $errors = [];
        
        // PHP Version
        if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
            $errors[] = new IntentPress_Error([
                'code'       => 'system_php_version',
                'message'    => sprintf(
                    __( 'IntentPress requires PHP %s or higher.', 'intentpress' ),
                    self::MIN_PHP_VERSION
                ),
                'action'     => sprintf(
                    __( 'Your server is running PHP %s. Please contact your hosting provider to upgrade.', 'intentpress' ),
                    PHP_VERSION
                ),
                'severity'   => 'critical',
                'category'   => 'system',
                'recoverable' => false,
            ]);
        }
        
        // WordPress Version
        global $wp_version;
        if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
            $errors[] = new IntentPress_Error([
                'code'       => 'system_wp_version',
                'message'    => sprintf(
                    __( 'IntentPress requires WordPress %s or higher.', 'intentpress' ),
                    self::MIN_WP_VERSION
                ),
                'action'     => sprintf(
                    __( 'Your site is running WordPress %s. Please update WordPress.', 'intentpress' ),
                    $wp_version
                ),
                'severity'   => 'critical',
                'category'   => 'system',
                'recoverable' => true,
            ]);
        }
        
        // REST API
        if ( ! $this->is_rest_api_available() ) {
            $errors[] = new IntentPress_Error([
                'code'       => 'system_rest_disabled',
                'message'    => __( 'WordPress REST API is unavailable.', 'intentpress' ),
                'action'     => __( 'The REST API appears to be disabled or blocked. Check your security plugin settings.', 'intentpress' ),
                'severity'   => 'critical',
                'category'   => 'system',
                'recoverable' => true,
            ]);
        }
        
        // JSON Extension
        if ( ! extension_loaded( 'json' ) ) {
            $errors[] = new IntentPress_Error([
                'code'       => 'system_json_missing',
                'message'    => __( 'PHP JSON extension is required.', 'intentpress' ),
                'action'     => __( 'Please contact your hosting provider to enable the JSON extension.', 'intentpress' ),
                'severity'   => 'critical',
                'category'   => 'system',
                'recoverable' => false,
            ]);
        }
        
        return $errors;
    }
    
    /**
     * Check if REST API is available
     */
    private function is_rest_api_available(): bool {
        $response = wp_remote_get(
            rest_url( 'intentpress/v1/status' ),
            [
                'timeout'   => 5,
                'sslverify' => false,
            ]
        );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        // 200 = OK, 401 = needs auth (but available), 404 = plugin not active yet
        return in_array( $status_code, [ 200, 401, 404 ], true );
    }
    
    /**
     * Prevent activation if incompatible
     */
    public static function activation_check(): void {
        $checker = new self();
        $errors = $checker->check_all();
        
        $critical_errors = array_filter( $errors, function( $e ) {
            return $e->severity === 'critical';
        });
        
        if ( ! empty( $critical_errors ) ) {
            $message = '<h2>' . __( 'IntentPress cannot be activated', 'intentpress' ) . '</h2><ul>';
            
            foreach ( $critical_errors as $error ) {
                $message .= '<li><strong>' . esc_html( $error->message ) . '</strong><br>';
                $message .= esc_html( $error->action ) . '</li>';
            }
            
            $message .= '</ul>';
            
            wp_die( 
                $message,
                __( 'Plugin Activation Error', 'intentpress' ),
                [ 'back_link' => true ]
            );
        }
    }
}
```

### 9.3 Plugin Conflict Detection

```php
<?php
class IntentPress_Conflict_Detector {
    
    /**
     * Known potential conflicts
     */
    private const KNOWN_CONFLICTS = [
        'searchwp/searchwp.php' => [
            'name'     => 'SearchWP',
            'type'     => 'hook_conflict',
            'severity' => 'warning',
            'message'  => 'SearchWP may conflict with IntentPress search hooks.',
        ],
        'relevanssi/relevanssi.php' => [
            'name'     => 'Relevanssi',
            'type'     => 'hook_conflict',
            'severity' => 'warning',
            'message'  => 'Relevanssi may override IntentPress search functionality.',
        ],
        'wordfence/wordfence.php' => [
            'name'     => 'Wordfence',
            'type'     => 'rest_blocking',
            'severity' => 'info',
            'message'  => 'Wordfence may block REST API requests. Whitelist IntentPress endpoints if needed.',
        ],
        'sucuri-scanner/sucuri.php' => [
            'name'     => 'Sucuri',
            'type'     => 'rest_blocking',
            'severity' => 'info',
            'message'  => 'Sucuri may block external API calls. Check firewall settings.',
        ],
    ];
    
    /**
     * Detect active conflicts
     */
    public function detect(): array {
        $conflicts = [];
        $active_plugins = get_option( 'active_plugins', [] );
        
        foreach ( self::KNOWN_CONFLICTS as $plugin_file => $conflict_info ) {
            if ( in_array( $plugin_file, $active_plugins, true ) ) {
                $conflicts[] = array_merge(
                    $conflict_info,
                    [ 'plugin_file' => $plugin_file ]
                );
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Show conflict warnings
     */
    public function show_warnings(): void {
        $conflicts = $this->detect();
        
        if ( empty( $conflicts ) ) {
            return;
        }
        
        add_action( 'admin_notices', function() use ( $conflicts ) {
            foreach ( $conflicts as $conflict ) {
                printf(
                    '<div class="notice notice-%s is-dismissible">
                        <p><strong>%s:</strong> %s</p>
                        <p>%s</p>
                    </div>',
                    esc_attr( $conflict['severity'] === 'warning' ? 'warning' : 'info' ),
                    esc_html__( 'Potential plugin conflict detected', 'intentpress' ),
                    esc_html( $conflict['name'] ),
                    esc_html( $conflict['message'] )
                );
            }
        });
    }
}
```

---

## 10. Edge Case Handling

### 10.1 Content Edge Cases

| Edge Case | Detection | Handling | Log Level |
|-----------|-----------|----------|-----------|
| Post with no content (title only) | `empty(post_content)` | Index title only | None |
| Post with only shortcodes | Shortcode detection | Expand shortcodes first | None |
| Post > 100,000 chars | `strlen() > 100000` | Truncate to 50,000 | Warning |
| Post with only images | No text after strip | Index alt text | Info |
| Non-English content | N/A | Index normally (model handles) | None |
| Password-protected post | `post_password` | Skip indexing | None |
| Private post | `post_status` | Skip indexing | None |
| Broken encoding | `mb_check_encoding()` | Attempt cleanup, skip if fails | Error |

### 10.2 Edge Case Handler

```php
<?php
class IntentPress_Edge_Case_Handler {
    
    /**
     * Process content with edge case handling
     */
    public function process_content( int $post_id ): ?string {
        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return null;
        }
        
        // Skip protected/private posts
        if ( ! empty( $post->post_password ) || $post->post_status !== 'publish' ) {
            return null;
        }
        
        // Get title
        $title = $post->post_title;
        
        // Get and process content
        $content = $post->post_content;
        
        // Expand shortcodes
        $content = do_shortcode( $content );
        
        // Strip HTML
        $content = wp_strip_all_tags( $content );
        
        // Handle encoding issues
        if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
            $content = $this->fix_encoding( $content );
            
            if ( $content === null ) {
                $this->log_edge_case( $post_id, 'broken_encoding' );
                // Fall back to title only
                return $title ? $title . ' ' . $title : null;
            }
        }
        
        // Handle empty content
        if ( empty( trim( $content ) ) ) {
            // Try to get image alt text
            $alt_text = $this->get_image_alt_text( $post_id );
            
            if ( ! empty( $alt_text ) ) {
                $content = $alt_text;
                $this->log_edge_case( $post_id, 'images_only', 'info' );
            } else {
                $this->log_edge_case( $post_id, 'no_content', 'info' );
            }
        }
        
        // Combine title and content
        $combined = $title . ' ' . $title . ' ' . $content; // Double title for emphasis
        
        // Handle excessive length
        if ( mb_strlen( $combined ) > 100000 ) {
            $combined = mb_substr( $combined, 0, 50000 );
            $this->log_edge_case( $post_id, 'content_truncated', 'warning' );
        }
        
        // Normalize whitespace
        $combined = preg_replace( '/\s+/', ' ', trim( $combined ) );
        
        return $combined;
    }
    
    /**
     * Attempt to fix encoding issues
     */
    private function fix_encoding( string $content ): ?string {
        // Try common encodings
        $encodings = [ 'UTF-8', 'ISO-8859-1', 'Windows-1252' ];
        
        foreach ( $encodings as $encoding ) {
            $converted = @iconv( $encoding, 'UTF-8//IGNORE', $content );
            
            if ( $converted !== false && mb_check_encoding( $converted, 'UTF-8' ) ) {
                return $converted;
            }
        }
        
        // Last resort: remove invalid characters
        $cleaned = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content );
        
        if ( mb_check_encoding( $cleaned, 'UTF-8' ) ) {
            return $cleaned;
        }
        
        return null;
    }
    
    /**
     * Get alt text from post images
     */
    private function get_image_alt_text( int $post_id ): string {
        $alt_texts = [];
        
        // Featured image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            $alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
            if ( $alt ) {
                $alt_texts[] = $alt;
            }
        }
        
        // Images in content
        $post = get_post( $post_id );
        preg_match_all( '/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', $post->post_content, $matches );
        
        if ( ! empty( $matches[1] ) ) {
            $alt_texts = array_merge( $alt_texts, $matches[1] );
        }
        
        return implode( ' ', array_filter( $alt_texts ) );
    }
}
```

### 10.3 Search Query Edge Cases

| Edge Case | Detection | Handling |
|-----------|-----------|----------|
| Only stop words | Stop word analysis | Fall back to WordPress |
| URL as query | URL pattern match | Search normally |
| SQL injection attempt | Pattern detection | Sanitize and search |
| XSS attempt | Script tags | Escape and search |
| Emoji only | Unicode detection | Search normally |
| Numeric only | `is_numeric()` | Search normally |
| Excessive whitespace | Regex | Normalize and search |

```php
<?php
class IntentPress_Query_Sanitizer {
    
    /**
     * Sanitize and normalize search query
     */
    public function sanitize( string $query ): string {
        // Basic sanitization
        $query = sanitize_text_field( $query );
        
        // Remove potential XSS
        $query = wp_kses( $query, [] );
        
        // Normalize whitespace
        $query = preg_replace( '/\s+/', ' ', trim( $query ) );
        
        // Limit length
        $max_length = apply_filters( 'intentpress_max_query_length', 500 );
        if ( mb_strlen( $query ) > $max_length ) {
            $query = mb_substr( $query, 0, $max_length );
        }
        
        return $query;
    }
    
    /**
     * Check if query is only stop words
     */
    public function is_only_stop_words( string $query ): bool {
        $stop_words = [
            'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to',
            'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are',
            'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did',
        ];
        
        $words = preg_split( '/\s+/', strtolower( $query ) );
        $content_words = array_diff( $words, $stop_words );
        
        return empty( $content_words );
    }
}
```

---

## 11. Error Logging System

### 11.1 Logging Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           LOGGING ARCHITECTURE                               │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Error      │────▶│   Logger     │────▶│   Storage    │
│   Source     │     │   Service    │     │   Backend    │
└──────────────┘     └──────────────┘     └──────────────┘
                            │
                            ├──────────────────────────────────┐
                            │                                  │
                            ▼                                  ▼
                     ┌──────────────┐                   ┌──────────────┐
                     │  wp_options  │                   │  debug.log   │
                     │  (last 100)  │                   │  (WP_DEBUG)  │
                     └──────────────┘                   └──────────────┘
```

### 11.2 Logger Implementation

```php
<?php
class IntentPress_Error_Logger {
    
    private const OPTION_KEY = 'intentpress_error_log';
    private const MAX_ENTRIES = 100;
    
    /**
     * Log severity levels
     */
    public const LOG_ERROR   = 1;
    public const LOG_WARNING = 2;
    public const LOG_INFO    = 3;
    public const LOG_DEBUG   = 4;
    
    /**
     * Log an error
     */
    public function log( IntentPress_Error $error, array $extra_context = [] ): void {
        $entry = $this->create_log_entry( $error, $extra_context );
        
        // Store in wp_options
        $this->store_entry( $entry );
        
        // Also write to debug.log if enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $this->write_to_debug_log( $entry );
        }
        
        // Fire action for external logging
        do_action( 'intentpress_error_logged', $error, $entry );
    }
    
    /**
     * Create structured log entry
     */
    private function create_log_entry( IntentPress_Error $error, array $extra_context ): array {
        return [
            'timestamp'      => current_time( 'mysql' ),
            'unix_time'      => time(),
            'severity'       => $error->severity,
            'category'       => $error->category,
            'code'           => $error->code,
            'message'        => $error->message,
            'technical_info' => $error->technical_info ?? '',
            'context'        => array_merge( $error->context ?? [], $extra_context ),
            'user_id'        => get_current_user_id(),
            'url'            => $_SERVER['REQUEST_URI'] ?? '',
            'ip'             => $this->get_client_ip(),
        ];
    }
    
    /**
     * Store entry in wp_options (circular buffer)
     */
    private function store_entry( array $entry ): void {
        $log = get_option( self::OPTION_KEY, [] );
        
        // Add new entry
        array_unshift( $log, $entry );
        
        // Keep only last MAX_ENTRIES
        $log = array_slice( $log, 0, self::MAX_ENTRIES );
        
        update_option( self::OPTION_KEY, $log, false ); // Don't autoload
    }
    
    /**
     * Write to WordPress debug.log
     */
    private function write_to_debug_log( array $entry ): void {
        $formatted = sprintf(
            "[%s] [%s] [%s] %s\n  Context: %s\n  User: %d\n  URL: %s",
            $entry['timestamp'],
            strtoupper( $entry['severity'] ),
            strtoupper( $entry['category'] ),
            $entry['message'],
            wp_json_encode( $entry['context'] ),
            $entry['user_id'],
            $entry['url']
        );
        
        error_log( 'IntentPress: ' . $formatted );
    }
    
    /**
     * Get recent log entries
     */
    public function get_entries( int $limit = 50, ?string $severity = null, ?string $category = null ): array {
        $log = get_option( self::OPTION_KEY, [] );
        
        // Filter by severity
        if ( $severity !== null ) {
            $log = array_filter( $log, fn( $e ) => $e['severity'] === $severity );
        }
        
        // Filter by category
        if ( $category !== null ) {
            $log = array_filter( $log, fn( $e ) => $e['category'] === $category );
        }
        
        return array_slice( $log, 0, $limit );
    }
    
    /**
     * Clear log entries
     */
    public function clear(): void {
        delete_option( self::OPTION_KEY );
    }
    
    /**
     * Get client IP (privacy-safe)
     */
    private function get_client_ip(): string {
        // Only store for admins, and hash for privacy
        if ( ! current_user_can( 'manage_options' ) ) {
            return 'visitor';
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr( md5( $ip . AUTH_KEY ), 0, 8 ); // Partial hash
    }
}
```

### 11.3 Debug Mode Extensions

```php
<?php
class IntentPress_Debug_Mode {
    
    /**
     * Check if debug mode is active
     */
    public static function is_active(): bool {
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
    
    /**
     * Add debug info to search responses
     */
    public function add_debug_info( array $response, array $context ): array {
        if ( ! self::is_active() ) {
            return $response;
        }
        
        $response['debug'] = [
            'embedding_time_ms'    => $context['embedding_time'] ?? null,
            'similarity_scores'    => $context['scores'] ?? [],
            'api_request_id'       => $context['request_id'] ?? null,
            'cache_hit'            => $context['cache_hit'] ?? false,
            'fallback_used'        => $context['fallback'] ?? false,
            'query_length'         => mb_strlen( $context['query'] ?? '' ),
            'results_before_filter' => $context['raw_count'] ?? null,
            'memory_usage_mb'      => round( memory_get_peak_usage() / 1024 / 1024, 2 ),
            'execution_time_ms'    => round( ( microtime( true ) - $context['start_time'] ) * 1000, 2 ),
        ];
        
        return $response;
    }
}
```

---

## 12. Recovery Mechanisms

### 12.1 Automatic Recovery Actions

| Error Type | Recovery Action | Trigger | Max Attempts |
|------------|-----------------|---------|--------------|
| Rate Limit | Exponential backoff | Automatic | 5 |
| Server Error (5xx) | Retry with delay | Automatic | 3 |
| Timeout | Retry once | Automatic | 2 |
| Invalid API Key | Disable + notify | Manual | 0 |
| Quota Exceeded | Disable + notify | Manual | 0 |
| Database Error | Log + continue | Manual | 0 |

### 12.2 Recovery Manager

```php
<?php
class IntentPress_Recovery_Manager {
    
    /**
     * Attempt recovery for an error
     */
    public function attempt_recovery( IntentPress_Error $error, callable $retry_callback ): mixed {
        if ( ! $error->recoverable ) {
            return null;
        }
        
        $strategy = $this->get_recovery_strategy( $error->code );
        
        if ( ! $strategy ) {
            return null;
        }
        
        return $this->execute_strategy( $strategy, $retry_callback );
    }
    
    /**
     * Get recovery strategy for error code
     */
    private function get_recovery_strategy( string $error_code ): ?array {
        $strategies = [
            'api_rate_limit' => [
                'type'         => 'exponential_backoff',
                'max_attempts' => 5,
                'base_delay'   => 1,
                'max_delay'    => 60,
            ],
            'api_server_error' => [
                'type'         => 'linear_retry',
                'max_attempts' => 3,
                'delay'        => 2,
            ],
            'api_timeout' => [
                'type'         => 'immediate_retry',
                'max_attempts' => 2,
            ],
            'api_gateway_error' => [
                'type'         => 'linear_retry',
                'max_attempts' => 3,
                'delay'        => 5,
            ],
        ];
        
        return $strategies[ $error_code ] ?? null;
    }
    
    /**
     * Execute recovery strategy
     */
    private function execute_strategy( array $strategy, callable $retry_callback ): mixed {
        $attempt = 1;
        $last_error = null;
        
        while ( $attempt <= $strategy['max_attempts'] ) {
            // Calculate delay
            $delay = $this->calculate_delay( $strategy, $attempt );
            
            if ( $delay > 0 ) {
                sleep( $delay );
            }
            
            try {
                $result = $retry_callback();
                
                // Success - log recovery
                $this->log_recovery_success( $strategy['type'], $attempt );
                
                return $result;
                
            } catch ( Exception $e ) {
                $last_error = $e;
                $attempt++;
            }
        }
        
        // Max attempts reached
        $this->log_recovery_failure( $strategy['type'], $strategy['max_attempts'], $last_error );
        
        return null;
    }
    
    /**
     * Calculate delay based on strategy
     */
    private function calculate_delay( array $strategy, int $attempt ): int {
        switch ( $strategy['type'] ) {
            case 'exponential_backoff':
                $delay = pow( $strategy['base_delay'], $attempt );
                $jitter = random_int( 0, 1000 ) / 1000;
                return (int) min( $delay + $jitter, $strategy['max_delay'] );
                
            case 'linear_retry':
                return $strategy['delay'];
                
            case 'immediate_retry':
            default:
                return 0;
        }
    }
}
```

### 12.3 Health Check System

```php
<?php
class IntentPress_Health_Monitor {
    
    /**
     * Run all health checks
     */
    public function check_all(): array {
        return [
            'status'     => $this->get_overall_status(),
            'checks'     => [
                'api_connection' => $this->check_api_connection(),
                'database'       => $this->check_database(),
                'index'          => $this->check_index(),
                'configuration'  => $this->check_configuration(),
            ],
            'last_error' => $this->get_last_error(),
            'php_version' => PHP_VERSION,
            'wp_version'  => get_bloginfo( 'version' ),
            'plugin_version' => INTENTPRESS_VERSION,
        ];
    }
    
    /**
     * Check API connection
     */
    private function check_api_connection(): array {
        $start = microtime( true );
        
        $api = new IntentPress_OpenAI_Client();
        $result = $api->test_connection();
        
        $latency = round( ( microtime( true ) - $start ) * 1000 );
        
        return [
            'status'     => $result ? 'ok' : 'error',
            'latency_ms' => $latency,
            'message'    => $result ? null : 'API connection failed',
        ];
    }
    
    /**
     * Check database tables
     */
    private function check_database(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'intentpress_embeddings';
        $exists = $wpdb->get_var( 
            $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) 
        ) === $table_name;
        
        return [
            'status'       => $exists ? 'ok' : 'error',
            'table_exists' => $exists,
            'message'      => $exists ? null : 'Database table missing',
        ];
    }
    
    /**
     * Check index status
     */
    private function check_index(): array {
        $indexed = $this->get_indexed_count();
        $total = $this->get_total_indexable();
        $stale = $this->get_stale_count();
        
        $status = 'ok';
        $message = null;
        
        if ( $indexed === 0 ) {
            $status = 'warning';
            $message = 'No posts indexed';
        } elseif ( $stale > 0 ) {
            $status = 'warning';
            $message = sprintf( '%d posts need re-indexing', $stale );
        } elseif ( $indexed < $total ) {
            $status = 'info';
            $message = sprintf( '%d posts not indexed (limit reached)', $total - $indexed );
        }
        
        return [
            'status'        => $status,
            'posts_indexed' => $indexed,
            'posts_total'   => $total,
            'posts_stale'   => $stale,
            'message'       => $message,
        ];
    }
    
    /**
     * Get overall health status
     */
    private function get_overall_status(): string {
        $checks = [
            $this->check_api_connection(),
            $this->check_database(),
            $this->check_configuration(),
        ];
        
        foreach ( $checks as $check ) {
            if ( $check['status'] === 'error' ) {
                return 'unhealthy';
            }
        }
        
        foreach ( $checks as $check ) {
            if ( $check['status'] === 'warning' ) {
                return 'degraded';
            }
        }
        
        return 'healthy';
    }
}
```

---

## 13. API Contracts

### 13.1 Health Check Endpoint

**Endpoint:** `GET /wp-json/intentpress/v1/health`

**Response:**
```json
{
  "status": "healthy",
  "checks": {
    "api_connection": {
      "status": "ok",
      "latency_ms": 245
    },
    "database": {
      "status": "ok",
      "table_exists": true
    },
    "index": {
      "status": "warning",
      "posts_indexed": 500,
      "posts_total": 520,
      "message": "20 posts not indexed (limit reached)"
    },
    "configuration": {
      "status": "ok"
    }
  },
  "last_error": null,
  "php_version": "8.2.0",
  "wp_version": "6.7.0",
  "plugin_version": "1.0.0"
}
```

### 13.2 Error Log Endpoint

**Endpoint:** `GET /wp-json/intentpress/v1/errors`

**Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 50 | Max entries to return |
| `severity` | string | null | Filter by severity |
| `category` | string | null | Filter by category |

**Response:**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "timestamp": "2026-01-08 15:23:45",
        "severity": "error",
        "category": "api",
        "code": "api_rate_limit",
        "message": "Search is temporarily limited.",
        "context": {
          "retry_after": 5
        }
      }
    ],
    "total": 15,
    "filtered": 10
  }
}
```

### 13.3 Error Response Format

All API errors follow this format:

```json
{
  "success": false,
  "error": {
    "code": "api_key_invalid",
    "message": "Your API key appears to be invalid.",
    "action": "Please check your API key in Settings → IntentPress.",
    "severity": "error",
    "category": "api",
    "recoverable": true,
    "retryAfter": null
  }
}
```

---

## 14. Data Models

### 14.1 Error Log Table Schema

```sql
-- Error logs are stored in wp_options as serialized array
-- Option key: intentpress_error_log

-- Log entry structure (serialized in wp_options):
-- [
--   {
--     "timestamp": "2026-01-08 15:23:45",
--     "unix_time": 1736350625,
--     "severity": "error",
--     "category": "api",
--     "code": "api_key_invalid",
--     "message": "Your API key appears to be invalid.",
--     "technical_info": "HTTP 401 from api.openai.com",
--     "context": {},
--     "user_id": 1,
--     "url": "/wp-admin/admin.php?page=intentpress",
--     "ip": "a1b2c3d4"
--   }
-- ]
```

### 14.2 Rate Limit State

```php
// Stored in transient: intentpress_rate_limited
[
    'until'   => 1736350680,  // Unix timestamp
    'attempt' => 2,           // Current retry attempt
]
```

### 14.3 Index Error State

```php
// Stored in wp_options: intentpress_index_state
[
    'status'       => 'paused',        // idle, running, paused, complete, error
    'paused_at'    => '2026-01-08 15:23:45',
    'pause_reason' => 'consecutive_failures',
    'failed_posts' => [
        ['post_id' => 123, 'title' => 'Example', 'error' => 'api_timeout'],
    ],
    'last_error'   => [
        'code'    => 'api_timeout',
        'message' => 'Connection timeout',
    ],
]
```

---

## 15. User Interface Components

### 15.1 Error Notice Component

```typescript
// src/admin/components/ErrorNotice.tsx
import React from 'react';
import { Notice, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface ErrorNoticeProps {
  error: {
    code: string;
    message: string;
    action: string;
    severity: 'critical' | 'error' | 'warning' | 'info';
    recoverable: boolean;
  };
  onDismiss?: () => void;
  onRetry?: () => void;
}

export const ErrorNotice: React.FC<ErrorNoticeProps> = ({
  error,
  onDismiss,
  onRetry,
}) => {
  const statusMap = {
    critical: 'error',
    error: 'error',
    warning: 'warning',
    info: 'info',
  } as const;

  return (
    <Notice
      status={statusMap[error.severity]}
      isDismissible={error.severity !== 'critical'}
      onDismiss={onDismiss}
      className={`intentpress-error-notice intentpress-error-${error.severity}`}
    >
      <p><strong>{error.message}</strong></p>
      <p>{error.action}</p>
      {error.recoverable && onRetry && (
        <Button variant="secondary" onClick={onRetry}>
          {__('Retry', 'intentpress')}
        </Button>
      )}
    </Notice>
  );
};
```

### 15.2 Error Log Viewer

```typescript
// src/admin/components/ErrorLogViewer.tsx
import React, { useState, useEffect } from 'react';
import {
  Card,
  CardHeader,
  CardBody,
  SelectControl,
  Button,
  Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

interface LogEntry {
  timestamp: string;
  severity: string;
  category: string;
  code: string;
  message: string;
  context: Record<string, unknown>;
}

export const ErrorLogViewer: React.FC = () => {
  const [entries, setEntries] = useState<LogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState({ severity: '', category: '' });

  useEffect(() => {
    loadEntries();
  }, [filter]);

  const loadEntries = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filter.severity) params.set('severity', filter.severity);
      if (filter.category) params.set('category', filter.category);

      const response = await apiFetch<{ data: { entries: LogEntry[] } }>({
        path: `/intentpress/v1/errors?${params}`,
      });
      setEntries(response.data.entries);
    } catch (error) {
      console.error('Failed to load error log:', error);
    }
    setLoading(false);
  };

  const clearLog = async () => {
    if (!confirm(__('Clear all error log entries?', 'intentpress'))) {
      return;
    }

    await apiFetch({
      path: '/intentpress/v1/errors',
      method: 'DELETE',
    });
    setEntries([]);
  };

  const getSeverityIcon = (severity: string): string => {
    const icons = {
      critical: '❌',
      error: '⚠️',
      warning: '⚡',
      info: 'ℹ️',
    };
    return icons[severity as keyof typeof icons] || '•';
  };

  return (
    <Card>
      <CardHeader>
        <h3>{__('Error Log', 'intentpress')}</h3>
        <div className="intentpress-log-filters">
          <SelectControl
            label={__('Severity', 'intentpress')}
            value={filter.severity}
            options={[
              { label: __('All', 'intentpress'), value: '' },
              { label: __('Critical', 'intentpress'), value: 'critical' },
              { label: __('Error', 'intentpress'), value: 'error' },
              { label: __('Warning', 'intentpress'), value: 'warning' },
              { label: __('Info', 'intentpress'), value: 'info' },
            ]}
            onChange={(severity) => setFilter({ ...filter, severity })}
          />
          <SelectControl
            label={__('Category', 'intentpress')}
            value={filter.category}
            options={[
              { label: __('All', 'intentpress'), value: '' },
              { label: __('API', 'intentpress'), value: 'api' },
              { label: __('Config', 'intentpress'), value: 'config' },
              { label: __('Search', 'intentpress'), value: 'search' },
              { label: __('Index', 'intentpress'), value: 'index' },
              { label: __('Limit', 'intentpress'), value: 'limit' },
              { label: __('System', 'intentpress'), value: 'system' },
            ]}
            onChange={(category) => setFilter({ ...filter, category })}
          />
          <Button variant="secondary" onClick={clearLog}>
            {__('Clear Log', 'intentpress')}
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {loading ? (
          <Spinner />
        ) : entries.length === 0 ? (
          <p className="intentpress-log-empty">
            {__('No errors logged.', 'intentpress')}
          </p>
        ) : (
          <table className="intentpress-log-table widefat">
            <thead>
              <tr>
                <th>{__('Time', 'intentpress')}</th>
                <th>{__('Severity', 'intentpress')}</th>
                <th>{__('Category', 'intentpress')}</th>
                <th>{__('Message', 'intentpress')}</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry, index) => (
                <tr key={index} className={`severity-${entry.severity}`}>
                  <td>{entry.timestamp}</td>
                  <td>
                    <span className="severity-badge">
                      {getSeverityIcon(entry.severity)} {entry.severity}
                    </span>
                  </td>
                  <td>{entry.category}</td>
                  <td>
                    <strong>{entry.code}</strong>: {entry.message}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </CardBody>
    </Card>
  );
};
```

### 15.3 Health Status Dashboard Widget

```typescript
// src/admin/components/HealthStatusWidget.tsx
import React, { useState, useEffect } from 'react';
import { Card, CardBody, Spinner, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

interface HealthCheck {
  status: 'ok' | 'warning' | 'error';
  latency_ms?: number;
  message?: string;
}

interface HealthData {
  status: 'healthy' | 'degraded' | 'unhealthy';
  checks: {
    api_connection: HealthCheck;
    database: HealthCheck;
    index: HealthCheck & { posts_indexed: number; posts_total: number };
    configuration: HealthCheck;
  };
  php_version: string;
  wp_version: string;
  plugin_version: string;
}

export const HealthStatusWidget: React.FC = () => {
  const [health, setHealth] = useState<HealthData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkHealth();
  }, []);

  const checkHealth = async () => {
    setLoading(true);
    try {
      const response = await apiFetch<HealthData>({
        path: '/intentpress/v1/health',
      });
      setHealth(response);
    } catch (error) {
      console.error('Health check failed:', error);
    }
    setLoading(false);
  };

  const getStatusColor = (status: string): string => {
    const colors = {
      healthy: '#28a745',
      ok: '#28a745',
      degraded: '#ffc107',
      warning: '#ffc107',
      unhealthy: '#dc3545',
      error: '#dc3545',
    };
    return colors[status as keyof typeof colors] || '#6c757d';
  };

  const getStatusIcon = (status: string): string => {
    const icons = {
      healthy: '✓',
      ok: '✓',
      degraded: '⚠',
      warning: '⚠',
      unhealthy: '✗',
      error: '✗',
    };
    return icons[status as keyof typeof icons] || '?';
  };

  if (loading) {
    return (
      <Card>
        <CardBody>
          <Spinner />
        </CardBody>
      </Card>
    );
  }

  if (!health) {
    return (
      <Card>
        <CardBody>
          <p>{__('Failed to load health status.', 'intentpress')}</p>
          <Button variant="secondary" onClick={checkHealth}>
            {__('Retry', 'intentpress')}
          </Button>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card className="intentpress-health-widget">
      <CardBody>
        <div className="health-overall">
          <span
            className="health-status-badge"
            style={{ backgroundColor: getStatusColor(health.status) }}
          >
            {getStatusIcon(health.status)} {health.status.toUpperCase()}
          </span>
        </div>

        <div className="health-checks">
          {Object.entries(health.checks).map(([name, check]) => (
            <div key={name} className="health-check-item">
              <span
                className="check-icon"
                style={{ color: getStatusColor(check.status) }}
              >
                {getStatusIcon(check.status)}
              </span>
              <span className="check-name">
                {name.replace('_', ' ')}
              </span>
              {check.latency_ms && (
                <span className="check-latency">
                  {check.latency_ms}ms
                </span>
              )}
              {check.message && (
                <span className="check-message">{check.message}</span>
              )}
            </div>
          ))}
        </div>

        <div className="health-versions">
          <small>
            PHP {health.php_version} | WP {health.wp_version} | IntentPress {health.plugin_version}
          </small>
        </div>

        <Button variant="secondary" onClick={checkHealth}>
          {__('Refresh', 'intentpress')}
        </Button>
      </CardBody>
    </Card>
  );
};
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial FRD based on PRD-Error-Handling.md |
