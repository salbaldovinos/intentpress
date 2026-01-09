# Product Requirements Document: IntentPress MVP
## Error Handling & Edge Cases

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related Documents:** PRD-Overview.md, PRD-User-Stories.md, PRD-Onboarding.md

---

## Table of Contents

1. [Error Handling Philosophy](#1-error-handling-philosophy)
2. [Error Categories](#2-error-categories)
3. [API & Network Errors](#3-api--network-errors)
4. [Configuration Errors](#4-configuration-errors)
5. [Search Errors](#5-search-errors)
6. [Indexing Errors](#6-indexing-errors)
7. [Limit & Quota Errors](#7-limit--quota-errors)
8. [WordPress Compatibility Errors](#8-wordpress-compatibility-errors)
9. [Edge Cases & Boundary Conditions](#9-edge-cases--boundary-conditions)
10. [Error Logging & Debugging](#10-error-logging--debugging)
11. [Error Message Reference](#11-error-message-reference)

---

## 1. Error Handling Philosophy

### 1.1 Core Principles

**Never Break the Site**
IntentPress must never cause a site to become non-functional. All errors must fail gracefully, falling back to WordPress default behavior when possible.

**Be Specific and Actionable**
Error messages must tell users what went wrong AND what to do about it. Avoid vague messages like "An error occurred."

**Log for Debugging, Display for Users**
Technical details go to logs for developers. User-facing messages are clear, non-technical, and helpful.

**Assume the User is Non-Technical**
Error messages should be understandable by someone who doesn't know what an API is. Offer simple next steps.

**Fail Silently for Visitors, Loudly for Admins**
Site visitors should never see IntentPress errors. Admins should be clearly informed of issues that need attention.

### 1.2 Error Response Structure

All errors internally follow this structure:

```php
[
    'code'           => 'intentpress_api_key_invalid',     // Machine-readable code
    'message'        => 'Your API key appears to be invalid.',  // User-facing message
    'action'         => 'Please check your API key in Settings → IntentPress.',  // What to do
    'technical_info' => 'HTTP 401 from api.openai.com',    // For logs only
    'recoverable'    => true,                               // Can user fix this?
    'retry_after'    => null,                               // Seconds until retry (if applicable)
]
```

---

## 2. Error Categories

### 2.1 Category Overview

| Category | User Impact | Visibility | Recovery |
|----------|-------------|------------|----------|
| **API Errors** | Search degraded | Admin dashboard | Often automatic |
| **Configuration Errors** | Features disabled | Admin notices | Manual fix required |
| **Search Errors** | Fallback used | Silent to visitors | Automatic fallback |
| **Indexing Errors** | Partial index | Admin dashboard | Retry available |
| **Limit Errors** | Features restricted | Admin + possibly visitor | Upgrade or wait |
| **Compatibility Errors** | Plugin may not work | Admin notices | Environment change |

### 2.2 Error Severity Levels

| Level | Icon | Color | Meaning | Example |
|-------|------|-------|---------|---------|
| **Critical** | ❌ | Red | Plugin non-functional | PHP version incompatible |
| **Error** | ⚠️ | Orange | Feature broken | API key invalid |
| **Warning** | ⚡ | Yellow | Degraded experience | Rate limit approaching |
| **Info** | ℹ️ | Blue | Informational | Index slightly stale |

---

## 3. API & Network Errors

### 3.1 OpenAI API Errors

#### 3.1.1 Authentication Errors (HTTP 401)

**Cause:** Invalid, revoked, or missing API key

**User Message:**
```
Error: API Authentication Failed

Your OpenAI API key appears to be invalid or has been revoked. 
Please check your API key in Settings → IntentPress.

If you recently regenerated your key, update it here.
```

**Behavior:**
- Semantic search disabled
- Fallback to WordPress default search
- Admin notice displayed
- Dashboard shows error state

**Recovery:**
- User re-enters valid API key
- System re-validates automatically

---

#### 3.1.2 Rate Limit Errors (HTTP 429)

**Cause:** Too many requests to OpenAI API

**User Message:**
```
Notice: Search Temporarily Limited

We've hit the API rate limit. Search will automatically resume in 
[X seconds/minutes]. In the meantime, standard search is being used.

If this happens frequently, consider upgrading your OpenAI API tier.
```

**Behavior:**
- For search: Use fallback search, retry after cooldown
- For indexing: Pause, wait, auto-resume

**Technical Handling:**
```php
// Exponential backoff with jitter
$retry_after = min(
    pow(2, $attempt) + random_int(0, 1000) / 1000,
    60  // Max 60 seconds
);
```

**Recovery:**
- Automatic after cooldown period
- Track rate limit events for analytics

---

#### 3.1.3 Quota Exceeded (HTTP 429 with quota message)

**Cause:** OpenAI billing limit reached

**User Message:**
```
Error: OpenAI Quota Exceeded

Your OpenAI account has reached its spending limit. 
Semantic search is temporarily disabled.

To resolve this:
1. Log in to platform.openai.com
2. Go to Billing → Usage limits
3. Increase your spending limit or add payment method

Standard WordPress search will be used until this is resolved.
```

**Behavior:**
- Disable semantic features until resolved
- Log event with timestamp
- Display persistent admin notice

---

#### 3.1.4 Server Errors (HTTP 500, 502, 503)

**Cause:** OpenAI service issues

**User Message:**
```
Notice: AI Service Temporarily Unavailable

OpenAI's servers are experiencing issues. We're automatically 
retrying. Standard search is being used in the meantime.

Status: Retry attempt 2 of 5
```

**Behavior:**
- Retry with exponential backoff (up to 5 attempts)
- Use fallback search during retry period
- Log for debugging

**Recovery:**
- Usually automatic when OpenAI recovers
- Manual retry available in dashboard

---

#### 3.1.5 Network Timeout

**Cause:** Network connectivity issues, slow response

**User Message (Admin):**
```
Warning: Connection Timeout

Unable to reach OpenAI within 10 seconds. This may be due to:
• Network connectivity issues
• Firewall blocking api.openai.com
• High server load

Standard search is being used as a fallback.

[Test Connection]  [View Debug Info]
```

**User Message (Visitor):**
Nothing shown - silent fallback to WordPress search

**Timeout Configuration:**
- Embedding generation: 30 seconds
- Search query embedding: 10 seconds
- API validation: 5 seconds

---

### 3.2 Network Errors Matrix

| Error | HTTP Code | Retryable | Fallback | Admin Notice | Visitor Impact |
|-------|-----------|-----------|----------|--------------|----------------|
| Invalid API key | 401 | No | Yes | Persistent | None (fallback) |
| Rate limit | 429 | Yes (after delay) | Yes | Temporary | None (fallback) |
| Quota exceeded | 429 | No | Yes | Persistent | None (fallback) |
| Server error | 5xx | Yes (3x) | Yes | If persistent | None (fallback) |
| Timeout | - | Yes (2x) | Yes | If frequent | None (fallback) |
| DNS failure | - | Yes (1x) | Yes | Persistent | None (fallback) |
| SSL error | - | No | Yes | Persistent | None (fallback) |

---

## 4. Configuration Errors

### 4.1 Missing API Key

**Cause:** Plugin activated but API key not configured

**User Message:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│ ⚙️  IntentPress needs configuration                                       │
│                                                                          │
│ Enter your OpenAI API key to enable semantic search.                     │
│                                                                          │
│ [Configure Now →]                                                         │
└──────────────────────────────────────────────────────────────────────────┘
```

**Where Displayed:**
- Admin notice on all admin pages
- IntentPress dashboard (prominent)
- Settings page (Step 2)

**Search Behavior:** Falls back to WordPress default search

---

### 4.2 Invalid Post Type Configuration

**Cause:** Selected post type no longer exists (e.g., plugin deactivated)

**User Message:**
```
Warning: Invalid Post Type Selected

The post type "product" is configured for indexing but no longer 
exists. This may be because a plugin was deactivated.

Affected: WooCommerce Products (product)

[Remove Invalid Type]  [Ignore]
```

**Behavior:**
- Skip invalid type during indexing
- Log warning
- Continue with valid types

---

### 4.3 Encryption Key Issues

**Cause:** WordPress AUTH_KEY changed, can't decrypt stored API key

**User Message:**
```
Error: API Key Decryption Failed

Your API key couldn't be decrypted. This usually happens when 
WordPress security keys have been changed.

Please re-enter your API key to restore semantic search.

[Re-enter API Key →]
```

**Behavior:**
- Prompt for new API key
- Clear old encrypted value
- Log security event

---

## 5. Search Errors

### 5.1 Empty Search Query

**Cause:** User submits empty search form

**User Message (on search page):**
```
Please enter a search term.

Try searching for topics, questions, or keywords related to 
the content you're looking for.
```

**Behavior:**
- Don't call API
- Show prompt message
- Display recent/popular content (optional)

---

### 5.2 Search Query Too Short

**Cause:** Query is 1-2 characters

**User Message:**
```
Please enter at least 3 characters to search.

Short searches may not find relevant results.
```

**Behavior:**
- Don't call API for single characters
- Fall back to WordPress search for 2 characters
- Use semantic search for 3+ characters

**Technical Note:** Configurable via filter `intentpress_min_query_length`

---

### 5.3 Search Query Too Long

**Cause:** Query exceeds reasonable length (>500 characters)

**User Message:**
```
Your search query is too long. Please try a shorter search.
```

**Behavior:**
- Truncate to 500 characters before API call
- Log truncation event
- Proceed with search

---

### 5.4 No Results Found

**Cause:** No posts match the semantic query

**User Message:**
```
No results found for "[query]"

Suggestions:
• Try different keywords
• Use broader terms
• Check your spelling
• Browse our categories: [Category links]
```

**Behavior:**
- Show helpful suggestions
- Optionally show popular/recent posts
- Log "no results" query for analytics

---

### 5.5 Partial Results (Some Posts Not Indexed)

**Cause:** New posts added since last index

**Admin Notice:**
```
ℹ️  Some content may not appear in search results.

15 posts have been added since the last index. 
Re-index to include new content.

[Re-index Now]  [Dismiss]
```

**Visitor Impact:** None shown - best available results displayed

---

## 6. Indexing Errors

### 6.1 Individual Post Indexing Failure

**Cause:** Specific post couldn't be embedded (content issues, API error)

**User Message (in indexing log):**
```
⚠️ Failed to index: "Post Title Here" (ID: 1234)
   Reason: Content exceeds maximum token limit
   Action: This post will use keyword search only
```

**Behavior:**
- Skip failed post
- Continue with remaining posts
- Track in failed items list
- Offer retry option after completion

---

### 6.2 Batch Indexing Failure

**Cause:** Multiple consecutive failures

**User Message:**
```
Indexing paused due to repeated errors.

5 posts failed to index consecutively. This may indicate:
• API connectivity issues
• Content format problems
• Rate limiting

Successfully indexed: 245 of 300 posts

[View Errors]  [Retry Failed]  [Continue Anyway]
```

**Behavior:**
- Pause after 5 consecutive failures
- Preserve successful indexes
- Allow user to decide next action

---

### 6.3 Indexing Timeout

**Cause:** Overall indexing process taking too long

**User Message:**
```
Indexing is taking longer than expected.

Progress: 150 of 500 posts (30%)
Elapsed: 15 minutes

Indexing continues in the background. You can safely close 
this page and check back later.

[Continue Waiting]  [Check Status Later]
```

**Behavior:**
- Continue processing in background
- Don't timeout prematurely
- Update status asynchronously

---

### 6.4 Database Write Failure

**Cause:** Can't store embeddings (database full, permissions)

**User Message:**
```
Error: Database Storage Failed

Unable to save search index to database. This may be due to:
• Database disk space full
• Database permission issues
• Table corruption

Please contact your hosting provider or check your database status.

Technical: MySQL Error 1142 - INSERT command denied
```

**Behavior:**
- Stop indexing immediately
- Preserve existing index
- Display critical error notice
- Log detailed error

---

### 6.5 Content Parsing Errors

**Cause:** Post content can't be processed (encoding issues, malformed HTML)

**User Message (in log):**
```
⚠️ Content parsing failed: "Post Title" (ID: 567)
   Reason: Invalid UTF-8 encoding detected
   Action: Post will use title-only matching
```

**Behavior:**
- Try to index with just title
- Skip if title also fails
- Log for debugging
- Continue with other posts

---

## 7. Limit & Quota Errors

### 7.1 Free Tier Post Limit Reached

**Cause:** Site has more posts than free tier allows (500)

**User Message (During Indexing):**
```
Free tier limit reached (500 posts)

You have 750 posts, but the free tier includes indexing for 
500 posts. The newest 500 posts have been indexed.

These 250 posts are not included in semantic search:
• [Post titles or "View list"]

[Upgrade to Pro]  [Continue with 500]
```

**User Message (Dashboard):**
```
┌────────────────────────────────────────────────────────────────┐
│ Index Status: 500 / 750 posts                                  │
│ ████████████████████████████░░░░░░░░░░ 67%                    │
│                                                                │
│ ⚡ Upgrade to index all 750 posts                              │
│                                                                │
│ [Upgrade to Pro - $X/month]                                    │
└────────────────────────────────────────────────────────────────┘
```

**Behavior:**
- Index newest posts first (by publish date)
- Non-indexed posts use keyword search
- Persistent upgrade prompt in dashboard

---

### 7.2 Free Tier Search Limit Reached

**Cause:** 1,000 semantic searches used in current month

**User Message (Admin Dashboard):**
```
Monthly search limit reached

You've used 1,000 of 1,000 semantic searches this month.
Standard WordPress search is now active until [date].

Your limit resets on [first of next month].

[Upgrade for Unlimited]
```

**Behavior:**
- Switch all searches to fallback
- Track continued search volume
- Reset counter on 1st of month

**Visitor Impact:** None visible - search still works (fallback)

---

### 7.3 Approaching Limits Warning

**User Message (Admin Dashboard at 80%):**
```
⚡ Approaching search limit

You've used 800 of 1,000 searches this month (80%).
At current rate, you'll reach the limit in ~5 days.

[View Usage Details]  [Upgrade]
```

---

## 8. WordPress Compatibility Errors

### 8.1 PHP Version Incompatible

**Cause:** PHP < 8.0

**User Message (prevents activation):**
```
IntentPress requires PHP 8.0 or higher.

Your server is running PHP 7.4.

Please contact your hosting provider to upgrade PHP, or use a 
different hosting environment.

[Learn More About PHP Upgrades]
```

**Behavior:**
- Prevent plugin activation
- Display admin notice if somehow activated
- Don't break site

---

### 8.2 WordPress Version Incompatible

**Cause:** WordPress < 6.4

**User Message:**
```
IntentPress requires WordPress 6.4 or higher.

Your site is running WordPress 6.2.

Please update WordPress to use IntentPress, or contact your 
administrator if updates are managed elsewhere.

[Update WordPress]
```

---

### 8.3 REST API Disabled

**Cause:** REST API blocked by security plugin or configuration

**User Message:**
```
Error: WordPress REST API Unavailable

IntentPress requires the WordPress REST API to function.
The REST API appears to be disabled or blocked.

Common causes:
• Security plugin blocking REST API
• .htaccess rules blocking /wp-json/
• Server firewall configuration

[Test REST API]  [Troubleshooting Guide]
```

**Behavior:**
- Detect during activation or settings page load
- Provide specific troubleshooting steps
- Test endpoint: `/wp-json/intentpress/v1/status`

---

### 8.4 Database Table Creation Failed

**Cause:** Can't create custom tables on activation

**User Message:**
```
Error: Database Setup Failed

IntentPress couldn't create required database tables.

This may be due to:
• Database user lacking CREATE TABLE permission
• Database prefix conflicts
• MySQL version incompatibility

Technical: [specific MySQL error]

Please contact your hosting provider with this error message.
```

---

### 8.5 Plugin Conflicts

**Known Potential Conflicts:**

| Plugin | Conflict Type | Resolution |
|--------|---------------|------------|
| Other search plugins (SearchWP, Relevanssi) | Hook conflicts | Detect and warn; let user choose |
| Security plugins (Wordfence, Sucuri) | REST API blocking | Whitelist instructions |
| Caching plugins | Cached nonces | Cache exclusion rules |
| Multisite plugins | Scope confusion | Detect and handle |

**Generic Conflict Message:**
```
Potential plugin conflict detected

IntentPress detected [Plugin Name] which may affect search 
functionality. If you experience issues:

1. Try deactivating [Plugin Name] temporarily
2. If search works, configure [Plugin Name] to exclude 
   IntentPress endpoints
3. Contact support if issues persist

[Continue Anyway]  [Get Help]
```

---

## 9. Edge Cases & Boundary Conditions

### 9.1 Content Edge Cases

| Edge Case | Handling | User Message |
|-----------|----------|--------------|
| Post with no content (title only) | Index title only | None (works) |
| Post with only shortcodes | Expand shortcodes before indexing | None |
| Post with >100,000 characters | Truncate to first 50,000 chars | Log warning |
| Post with only images | Index alt text, skip if none | Log info |
| Post in non-English language | Index anyway (model handles) | None |
| Password-protected post | Skip indexing | None |
| Private post | Skip indexing | None |
| Scheduled (future) post | Skip indexing | None |
| Post with broken encoding | Attempt cleanup, skip if fails | Log error |

### 9.2 Search Edge Cases

| Edge Case | Handling | User Message |
|-----------|----------|--------------|
| Query is only stop words ("the", "a") | Fall back to WordPress search | None |
| Query is URL | Search normally | None |
| Query contains SQL injection attempt | Sanitize and search | None (sanitized) |
| Query contains XSS attempt | Escape and search | None (escaped) |
| Query is emoji only | Search normally (embedding handles) | None |
| Query is numeric only | Search normally | None |
| Query has excessive whitespace | Normalize and search | None |
| Simultaneous searches (race condition) | Handle concurrently | None |

### 9.3 Environment Edge Cases

| Edge Case | Handling | User Message |
|-----------|----------|--------------|
| Site behind CDN | Works normally | None |
| Site in subdirectory | Adjust REST URLs | None |
| Site with custom REST prefix | Detect and adapt | None |
| Local development (localhost) | Works normally | None |
| Staging site | Works normally | Consider separate API key |
| Site migration (domain change) | Preserve index | May need re-validation |
| Database restored from backup | Preserve index | May show stale notice |
| WP_DEBUG enabled | Show additional info | Debug output in admin |

### 9.4 User Behavior Edge Cases

| Edge Case | Handling | User Message |
|-----------|----------|--------------|
| Rapid repeated searches | Rate limit client-side | None (throttled silently) |
| Very long session | Token refresh if needed | None |
| Multiple browser tabs | Handle concurrently | None |
| Admin and visitor searching simultaneously | Concurrent OK | None |
| User changes settings during indexing | Queue changes | "Applied after indexing" |
| User deactivates during indexing | Stop gracefully | Index preserved |
| User deletes post during indexing | Skip deleted post | None |

---

## 10. Error Logging & Debugging

### 10.1 Logging Levels

```php
// Logging severity levels
define('INTENTPRESS_LOG_ERROR', 1);    // Errors requiring attention
define('INTENTPRESS_LOG_WARNING', 2);  // Potential issues
define('INTENTPRESS_LOG_INFO', 3);     // General information
define('INTENTPRESS_LOG_DEBUG', 4);    // Detailed debugging (WP_DEBUG only)
```

### 10.2 Log Entry Format

```
[2026-01-08 15:23:45] [ERROR] [API] Invalid API key response
  Context: {"endpoint": "embeddings", "http_code": 401}
  User: admin (ID: 1)
  URL: /wp-admin/admin.php?page=intentpress
```

### 10.3 Log Storage

| Environment | Storage Location | Retention |
|-------------|------------------|-----------|
| Standard | `wp_options` (intentpress_error_log) | Last 100 entries |
| WP_DEBUG | WordPress debug.log | Per WP settings |
| WP_DEBUG_LOG | Custom file if specified | Per WP settings |

### 10.4 Debug Mode

When `WP_DEBUG` is enabled:

```php
// Additional debug output
add_filter('intentpress_debug_output', function($output, $context) {
    return array_merge($output, [
        'embedding_time_ms' => $context['embedding_time'],
        'similarity_scores' => $context['scores'],
        'api_request_id' => $context['request_id'],
    ]);
}, 10, 2);
```

### 10.5 Health Check Data

Available via REST API `/wp-json/intentpress/v1/health`:

```json
{
    "status": "healthy",
    "checks": {
        "api_connection": {"status": "ok", "latency_ms": 245},
        "database": {"status": "ok", "table_exists": true},
        "index": {"status": "warning", "posts_indexed": 500, "posts_total": 520},
        "last_error": null,
        "php_version": "8.2.0",
        "wp_version": "6.7.0"
    }
}
```

---

## 11. Error Message Reference

### 11.1 Complete Message Catalog

#### API Errors

| Code | Short | Full Message | Action |
|------|-------|--------------|--------|
| `api_key_missing` | API key required | Please enter your IntentPress API key in Settings → IntentPress to enable semantic search. | Configure API key |
| `api_key_invalid` | Invalid API key | Your API key appears to be invalid. Please check it in Settings → IntentPress. | Re-enter key |
| `api_rate_limit` | Rate limited | Search is temporarily limited. Trying again in a moment... | Wait |
| `api_timeout` | Connection timeout | Search is taking longer than expected. Showing standard results instead. | Auto-fallback |
| `api_quota_exceeded` | Quota exceeded | Your OpenAI account has reached its limit. Please check your billing settings. | Upgrade OpenAI |
| `api_server_error` | Service unavailable | AI service temporarily unavailable. Using standard search. | Wait |

#### Search Errors

| Code | Short | Full Message | Action |
|------|-------|--------------|--------|
| `search_empty` | Empty search | Please enter a search term to continue. | Enter query |
| `search_too_short` | Query too short | Please enter at least 3 characters to search. | Longer query |
| `search_no_results` | No results | No results found for '[query]'. Try different keywords or browse categories. | Refine search |
| `search_fallback_used` | (Internal only) | - | - |

#### Indexing Errors

| Code | Short | Full Message | Action |
|------|-------|--------------|--------|
| `index_in_progress` | Indexing busy | Indexing is already in progress. Please wait for it to complete. | Wait |
| `index_post_failed` | Post failed | Failed to index "[title]". This post will use standard search. | Retry later |
| `index_batch_failed` | Multiple failures | Indexing paused due to repeated errors. [X] posts indexed successfully. | View errors |
| `index_database_error` | Storage failed | Unable to save search index. Please check database permissions. | Contact hosting |

#### Limit Errors

| Code | Short | Full Message | Action |
|------|-------|--------------|--------|
| `limit_posts_reached` | Post limit | Free tier limit reached (500 posts). Upgrade to index all content. | Upgrade |
| `limit_searches_reached` | Search limit | Monthly search limit reached. Standard search active until [date]. | Upgrade or wait |
| `limit_approaching` | Limit warning | Approaching monthly limit ([X]% used). | Consider upgrading |

#### System Errors

| Code | Short | Full Message | Action |
|------|-------|--------------|--------|
| `system_php_version` | PHP outdated | IntentPress requires PHP 8.0+. Current version: [X]. | Upgrade PHP |
| `system_wp_version` | WP outdated | IntentPress requires WordPress 6.4+. Current version: [X]. | Update WP |
| `system_rest_disabled` | REST API blocked | WordPress REST API is unavailable. Check security plugin settings. | Enable REST API |
| `system_db_error` | Database error | Database operation failed. Please contact your hosting provider. | Contact hosting |

### 11.2 Internationalization Notes

All error messages must be translatable:

```php
// ✅ Correct
__( 'Your API key appears to be invalid.', 'intentpress' )

// ✅ Correct with placeholder
sprintf( 
    __( 'No results found for "%s".', 'intentpress' ), 
    esc_html( $query ) 
)

// ❌ Incorrect - don't concatenate translatable strings
__( 'No results for ', 'intentpress' ) . $query

// ❌ Incorrect - don't include variables in translation string
__( "No results found for $query", 'intentpress' )
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial error handling specification |
