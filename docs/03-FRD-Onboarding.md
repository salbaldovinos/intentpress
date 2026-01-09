# Functional Requirements Document: Onboarding Flow
## IntentPress MVP

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related PRDs:** PRD-Onboarding.md  
**Related User Stories:** Onboarding flow (implicit)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Onboarding State Machine](#2-onboarding-state-machine)
3. [Step 1: Activation Notice](#3-step-1-activation-notice)
4. [Step 2: API Key Configuration](#4-step-2-api-key-configuration)
5. [Step 3: Start Indexing](#5-step-3-start-indexing)
6. [Step 4: Indexing Progress](#6-step-4-indexing-progress)
7. [Step 5: Test Search](#7-step-5-test-search)
8. [Step 6: Completion](#8-step-6-completion)
9. [State Persistence](#9-state-persistence)
10. [Resume & Recovery](#10-resume--recovery)
11. [Component Specifications](#11-component-specifications)
12. [Analytics & Tracking](#12-analytics--tracking)

---

## 1. Overview

### 1.1 Purpose

This document specifies the functional requirements for IntentPress's onboarding flow, which guides new users from plugin activation through first successful search.

### 1.2 Design Goals

| Goal | Target | Measurement |
|------|--------|-------------|
| Time to value | < 5 minutes | Clock time from activation to working search |
| Completion rate | > 80% | Users who finish all steps |
| Drop-off rate | < 20% per step | Abandonment at each step |
| Support requests | < 5% | Users needing help during onboarding |

### 1.3 Target User

Primary: **Sarah the Site Owner** (non-technical)
- May not know what an API key is
- Expects "it just works" experience
- Will abandon if confused

---

## 2. Onboarding State Machine

### 2.1 State Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     ONBOARDING STATE MACHINE                             │
└─────────────────────────────────────────────────────────────────────────┘

                              ┌──────────────────┐
                              │   NOT_STARTED    │
                              │  (Fresh install) │
                              └────────┬─────────┘
                                       │
                                       │ Plugin activated
                                       ▼
                              ┌──────────────────┐
                              │  STEP_1_NOTICE   │
                              │ (Admin notice)   │
                              └────────┬─────────┘
                                       │
                         ┌─────────────┴─────────────┐
                         │                           │
                         ▼                           ▼
                   [Get Started]              [Dismiss/Direct URL]
                         │                           │
                         └───────────┬───────────────┘
                                     │
                                     ▼
                              ┌──────────────────┐
                              │  STEP_2_API_KEY  │
                              │ (Settings page)  │
                              └────────┬─────────┘
                                       │
                                       │ API key validated
                                       ▼
                              ┌──────────────────┐
                              │ STEP_3_PRE_INDEX │
                              │ (Start indexing) │
                              └────────┬─────────┘
                                       │
                                       │ Start clicked
                                       ▼
                              ┌──────────────────┐
                              │  STEP_4_INDEXING │
                              │   (Progress)     │◀────┐
                              └────────┬─────────┘     │
                                       │               │
                         ┌─────────────┼───────────────┤
                         │             │               │
                         ▼             ▼               │
                    [Complete]    [Cancelled]     [Errors]
                         │             │               │
                         │             └───────────────┤
                         │                             │
                         ▼                             │
                              ┌──────────────────┐     │
                              │  STEP_5_TEST     │     │
                              │ (Test search)    │◀────┘
                              └────────┬─────────┘
                                       │
                                       │ Test or Skip
                                       ▼
                              ┌──────────────────┐
                              │  STEP_6_COMPLETE │
                              │  (Celebration)   │
                              └────────┬─────────┘
                                       │
                                       │ View Dashboard/Visit Site
                                       ▼
                              ┌──────────────────┐
                              │    COMPLETED     │
                              │  (Normal usage)  │
                              └──────────────────┘
```

### 2.2 State Definitions

| State | Description | Next States |
|-------|-------------|-------------|
| `not_started` | Plugin activated, no action taken | `step_1_notice` |
| `step_1_notice` | Admin notice displayed | `step_2_api_key` |
| `step_2_api_key` | API key configuration screen | `step_3_pre_index` |
| `step_3_pre_index` | Ready to start indexing | `step_4_indexing` |
| `step_4_indexing` | Indexing in progress | `step_5_test`, `step_4_indexing` |
| `step_5_test` | Test search interface | `step_6_complete` |
| `step_6_complete` | Celebration screen | `completed` |
| `completed` | Onboarding finished | N/A |

### 2.3 State Transitions

```php
/**
 * Valid state transitions
 */
const VALID_TRANSITIONS = [
    'not_started'     => ['step_1_notice'],
    'step_1_notice'   => ['step_2_api_key'],
    'step_2_api_key'  => ['step_3_pre_index'],
    'step_3_pre_index'=> ['step_4_indexing'],
    'step_4_indexing' => ['step_5_test', 'step_4_indexing'],
    'step_5_test'     => ['step_6_complete'],
    'step_6_complete' => ['completed'],
    'completed'       => [], // Terminal state
];

/**
 * Transition to new state with validation
 */
public function transition_to( string $new_state ): bool {
    $current = $this->get_current_state();
    
    if ( ! in_array( $new_state, self::VALID_TRANSITIONS[ $current ], true ) ) {
        return false;
    }
    
    $this->set_state( $new_state );
    $this->record_transition( $current, $new_state );
    
    return true;
}
```

---

## 3. Step 1: Activation Notice

### 3.1 Trigger Conditions

| Condition | Notice Displayed |
|-----------|------------------|
| Plugin just activated | Yes |
| Onboarding incomplete, not dismissed | Yes |
| Onboarding incomplete, dismissed < 30 days ago | No |
| Onboarding incomplete, dismissed ≥ 30 days ago | Yes |
| Onboarding complete | No |

### 3.2 Notice Specification

```php
/**
 * Admin notice configuration
 */
[
    'type' => 'info',
    'dismissible' => true,
    'message' => 'IntentPress is ready to upgrade your search!',
    'description' => 'Enable AI-powered semantic search in just a few minutes.',
    'action' => [
        'label' => 'Get Started →',
        'url' => admin_url( 'admin.php?page=intentpress' ),
    ],
]
```

### 3.3 Notice Display Logic

```php
/**
 * Determine if notice should display
 */
public function should_show_notice(): bool {
    // Already completed
    if ( get_option( 'intentpress_onboarding_complete', false ) ) {
        return false;
    }
    
    // Check dismissal
    $user_id = get_current_user_id();
    $dismissed_at = get_user_meta( $user_id, 'intentpress_notice_dismissed', true );
    
    if ( $dismissed_at ) {
        $days_since = ( time() - strtotime( $dismissed_at ) ) / DAY_IN_SECONDS;
        if ( $days_since < 30 ) {
            return false;
        }
    }
    
    // Check capability
    return current_user_can( 'manage_options' );
}
```

### 3.4 Dismissal Handling

```php
/**
 * Handle notice dismissal via AJAX
 */
public function handle_dismiss(): void {
    check_ajax_referer( 'intentpress_dismiss_notice' );
    
    $user_id = get_current_user_id();
    update_user_meta( $user_id, 'intentpress_notice_dismissed', current_time( 'mysql' ) );
    
    wp_send_json_success();
}
```

---

## 4. Step 2: API Key Configuration

### 4.1 UI Layout

```
┌────────────────────────────────────────────────────────────────────────┐
│ IntentPress                                                             │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Welcome to IntentPress! 👋                                            │
│                                                                        │
│  Let's set up semantic search for your site. This takes about          │
│  5 minutes and will dramatically improve your search results.          │
│                                                                        │
│  ─────────────────────────────────────────────────────────────────     │
│                                                                        │
│  Step 1 of 3: Connect to AI Service                                    │
│                                                                        │
│  IntentPress uses OpenAI's embedding technology to understand          │
│  search queries. You'll need an API key to continue.                   │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                                                                  │  │
│  │  OpenAI API Key                                                  │  │
│  │  ┌──────────────────────────────────────────┐                   │  │
│  │  │ sk-...                                   │                   │  │
│  │  └──────────────────────────────────────────┘                   │  │
│  │                                                                  │  │
│  │  🔗 Need an API key? Get one at platform.openai.com →           │  │
│  │                                                                  │  │
│  │  🔒 Your API key is encrypted and stored securely.              │  │
│  │                                                                  │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│          ┌──────────────────────┐                                     │
│          │  Validate & Continue │                                     │
│          └──────────────────────┘                                     │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │ 💡 Tip: OpenAI's API is pay-per-use. Semantic search typically  │  │
│  │    costs less than $1/month for sites with under 1,000 posts.   │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Validation States

| State | UI Display | User Action |
|-------|------------|-------------|
| `empty` | Empty input, neutral styling | Enter key |
| `validating` | Spinner, disabled button | Wait |
| `valid` | Green checkmark, auto-advance | None (auto) |
| `invalid_format` | Red border, format error | Correct key |
| `invalid_key` | Red border, API error | Re-enter key |
| `network_error` | Warning icon, connection error | Retry |

### 4.3 Validation Flow

```typescript
interface ValidationResult {
    valid: boolean;
    error?: {
        code: 'invalid_format' | 'invalid_key' | 'rate_limited' | 'network_error';
        message: string;
    };
}

async function validateApiKey(key: string): Promise<ValidationResult> {
    // 1. Format validation (client-side)
    if (!key.startsWith('sk-') || key.length < 20) {
        return {
            valid: false,
            error: {
                code: 'invalid_format',
                message: "This doesn't look like a valid OpenAI API key. Keys start with 'sk-'."
            }
        };
    }
    
    // 2. API validation (server-side)
    try {
        const response = await apiFetch({
            path: '/intentpress/v1/settings/validate-key',
            method: 'POST',
            data: { api_key: key }
        });
        
        if (response.valid) {
            return { valid: true };
        }
        
        return {
            valid: false,
            error: {
                code: response.error_code,
                message: response.message
            }
        };
    } catch (e) {
        return {
            valid: false,
            error: {
                code: 'network_error',
                message: "Couldn't connect to validate your key. Please check your connection."
            }
        };
    }
}
```

### 4.4 Success Transition

```typescript
async function handleValidationSuccess(): Promise<void> {
    // 1. Show success state
    setValidationState('valid');
    
    // 2. Wait for user to see success
    await sleep(1500);
    
    // 3. Transition to Step 3
    setCurrentStep('step_3_pre_index');
    
    // 4. Update server state
    await updateOnboardingState('step_3_pre_index');
}
```

---

## 5. Step 3: Start Indexing

### 5.1 UI Layout

```
┌────────────────────────────────────────────────────────────────────────┐
│                                                                        │
│  ✓ Step 1: API Connected                                               │
│                                                                        │
│  ─────────────────────────────────────────────────────────────────     │
│                                                                        │
│  Step 2 of 3: Index Your Content                                       │
│                                                                        │
│  IntentPress needs to analyze your content to enable semantic          │
│  search. This is a one-time process that takes about 1 minute          │
│  per 100 posts.                                                        │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                                                                  │  │
│  │  📊 Content to index:                                            │  │
│  │                                                                  │  │
│  │     Posts:   423                                                 │  │
│  │     Pages:    12                                                 │  │
│  │     ─────────                                                    │  │
│  │     Total:   435 items                                           │  │
│  │                                                                  │  │
│  │  ⏱️ Estimated time: ~4 minutes                                   │  │
│  │                                                                  │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│          ┌──────────────────────┐                                     │
│          │    Start Indexing    │                                     │
│          └──────────────────────┘                                     │
│                                                                        │
│  You can continue using WordPress while indexing runs in the           │
│  background. We'll notify you when it's complete.                      │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Content Summary Calculation

```php
/**
 * Get indexable content summary for onboarding
 */
public function get_content_summary(): array {
    $post_types = get_option( 'intentpress_indexed_post_types', ['post', 'page'] );
    $limit = $this->get_index_limit();
    
    $summary = [];
    $total = 0;
    
    foreach ( $post_types as $type ) {
        $count = wp_count_posts( $type );
        $published = (int) $count->publish;
        
        $type_obj = get_post_type_object( $type );
        $summary[] = [
            'type' => $type,
            'label' => $type_obj->labels->name,
            'count' => $published,
        ];
        
        $total += $published;
    }
    
    // Apply limit
    $will_index = min( $total, $limit );
    $over_limit = $total > $limit;
    
    // Estimate time (100 posts per minute)
    $estimated_minutes = ceil( $will_index / 100 );
    
    return [
        'by_type' => $summary,
        'total' => $total,
        'will_index' => $will_index,
        'over_limit' => $over_limit,
        'excluded' => max( 0, $total - $limit ),
        'estimated_minutes' => $estimated_minutes,
    ];
}
```

### 5.3 Free Tier Warning

If content exceeds free tier limit:

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ⚠️ Free tier includes 500 posts. You have 623 posts.                     │
│                                                                         │
│ The first 500 posts (by date, newest first) will be indexed.            │
│ Upgrade to IntentPress Pro to index all content.                        │
│                                                                         │
│ ┌───────────────────────────┐  ┌───────────────────────────────────┐   │
│ │ Start Indexing (500 posts)│  │      Learn about Pro →           │   │
│ └───────────────────────────┘  └───────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

### 5.4 Start Indexing Handler

```typescript
async function handleStartIndexing(): Promise<void> {
    try {
        // 1. Start indexing via API
        await apiFetch({
            path: '/intentpress/v1/index/start',
            method: 'POST',
            data: { full_reindex: true }
        });
        
        // 2. Transition to progress step
        setCurrentStep('step_4_indexing');
        
        // 3. Start progress polling
        startProgressPolling();
        
    } catch (error) {
        showError('Failed to start indexing. Please try again.');
    }
}
```

---

## 6. Step 4: Indexing Progress

### 6.1 UI Layout

```
┌────────────────────────────────────────────────────────────────────────┐
│                                                                        │
│  ✓ Step 1: API Connected                                               │
│  ○ Step 2: Indexing Content                                            │
│                                                                        │
│  ─────────────────────────────────────────────────────────────────     │
│                                                                        │
│  Indexing Your Content...                                              │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                                                                  │  │
│  │  ██████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░  45%         │  │
│  │                                                                  │  │
│  │  196 of 435 items indexed                                        │  │
│  │                                                                  │  │
│  │  ⏱️ Estimated time remaining: 2 minutes                          │  │
│  │                                                                  │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│          ┌──────────────────────┐                                     │
│          │        Cancel        │                                     │
│          └──────────────────────┘                                     │
│                                                                        │
│  💡 You can navigate away from this page. Indexing will continue       │
│     in the background and we'll notify you when complete.              │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 6.2 Progress Polling

```typescript
function useOnboardingProgress() {
    const [progress, setProgress] = useState<IndexingProgress | null>(null);
    const pollIntervalRef = useRef<number>();
    
    const startPolling = useCallback(() => {
        // Clear any existing interval
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
        }
        
        // Initial fetch
        fetchProgress();
        
        // Poll every 5 seconds
        pollIntervalRef.current = window.setInterval(async () => {
            const status = await fetchProgress();
            
            // Stop polling and advance if complete
            if (status.status === 'complete') {
                stopPolling();
                advanceToTestStep();
            }
            
            // Stop polling if cancelled or error
            if (['cancelled', 'error'].includes(status.status)) {
                stopPolling();
            }
        }, 5000);
    }, []);
    
    const stopPolling = useCallback(() => {
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = undefined;
        }
    }, []);
    
    return { progress, startPolling, stopPolling };
}
```

### 6.3 Completion Detection

```typescript
async function handleIndexingComplete(): Promise<void> {
    // 1. Show completion message briefly
    setCompletionMessage('Indexing complete!');
    
    // 2. Wait 1 second for user to see
    await sleep(1000);
    
    // 3. Auto-advance to test step
    setCurrentStep('step_5_test');
    
    // 4. Update server state
    await updateOnboardingState('step_5_test');
}
```

### 6.4 Cancel Handling

```typescript
async function handleCancelIndexing(): Promise<void> {
    // 1. Confirm with user
    const confirmed = await showConfirmDialog({
        title: 'Cancel indexing?',
        message: 'You can resume later from the dashboard. Posts already indexed will be kept.',
        confirmLabel: 'Yes, cancel',
        cancelLabel: 'Continue indexing',
    });
    
    if (!confirmed) return;
    
    // 2. Cancel via API
    await apiFetch({
        path: '/intentpress/v1/index/cancel',
        method: 'POST'
    });
    
    // 3. Still advance to test (partial index works)
    setCurrentStep('step_5_test');
}
```

---

## 7. Step 5: Test Search

### 7.1 UI Layout

```
┌────────────────────────────────────────────────────────────────────────┐
│                                                                        │
│  ✓ Step 1: API Connected                                               │
│  ✓ Step 2: Content Indexed (435 items)                                 │
│                                                                        │
│  ─────────────────────────────────────────────────────────────────     │
│                                                                        │
│  Step 3 of 3: Test Your Search                                         │
│                                                                        │
│  Let's make sure everything is working! Try a search:                  │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                                                                  │  │
│  │  ┌────────────────────────────────────┐  ┌────────────────────┐ │  │
│  │  │ Try: "getting started"             │  │      Search        │ │  │
│  │  └────────────────────────────────────┘  └────────────────────┘ │  │
│  │                                                                  │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│          ┌──────────────────────┐                                     │
│          │    Skip for Now →    │                                     │
│          └──────────────────────┘                                     │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 7.2 Search Results Display

```
┌────────────────────────────────────────────────────────────────────────┐
│                                                                        │
│  Search Results for "getting started":                        0.23s   │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                                                                  │  │
│  │  1. Getting Started with WordPress                               │  │
│  │     Relevance: ●●●●●○ (92%)                                      │  │
│  │     A complete guide to setting up your first WordPress site...  │  │
│  │                                                                  │  │
│  │  2. Beginner's Guide to Site Configuration                       │  │
│  │     Relevance: ●●●●○○ (78%)                                      │  │
│  │     Learn the basics of configuring your new website...          │  │
│  │                                                                  │  │
│  │  3. Your First Blog Post: A Starter's Handbook                   │  │
│  │     Relevance: ●●●○○○ (65%)                                      │  │
│  │     Tips for writing your first content as a new blogger...      │  │
│  │                                                                  │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│  ✨ Notice how results match the intent, not just keywords!            │
│                                                                        │
│          ┌──────────────────────┐                                     │
│          │   Complete Setup →   │                                     │
│          └──────────────────────┘                                     │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 7.3 Test Search Handler

```typescript
interface TestSearchResult {
    results: Array<{
        postId: number;
        title: string;
        excerpt: string;
        relevanceScore: number;
        relevanceDisplay: number; // 0-100 percentage
    }>;
    searchTime: number; // milliseconds
}

async function handleTestSearch(query: string): Promise<void> {
    setIsSearching(true);
    
    try {
        const response = await apiFetch<TestSearchResult>({
            path: '/intentpress/v1/search/test',
            method: 'POST',
            data: { query }
        });
        
        setSearchResults(response.results);
        setSearchTime(response.searchTime);
        
    } catch (error) {
        showError('Search failed. You can skip for now and test later.');
    } finally {
        setIsSearching(false);
    }
}
```

### 7.4 Suggested Queries

```php
/**
 * Generate suggested test queries based on content
 */
public function get_suggested_queries(): array {
    $suggestions = [];
    
    // Default suggestions
    $defaults = [
        'getting started',
        'how to',
        'guide',
    ];
    
    // Try to extract from actual content
    $recent_posts = get_posts([
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    
    foreach ( $recent_posts as $post ) {
        // Extract potential query from title
        $words = explode( ' ', strtolower( $post->post_title ) );
        if ( count( $words ) >= 2 ) {
            $suggestions[] = implode( ' ', array_slice( $words, 0, 3 ) );
        }
    }
    
    // Combine and dedupe
    $all = array_unique( array_merge( $defaults, $suggestions ) );
    
    return array_slice( $all, 0, 5 );
}
```

---

## 8. Step 6: Completion

### 8.1 UI Layout

```
┌────────────────────────────────────────────────────────────────────────┐
│                                                                        │
│                                                                        │
│                              🎉                                        │
│                                                                        │
│                    Semantic Search is Live!                            │
│                                                                        │
│         Visitors to your site now get smarter search results.          │
│                                                                        │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                                                                  │  │
│  │  ✓  435 posts and pages indexed                                  │  │
│  │  ✓  AI-powered search active                                     │  │
│  │  ✓  Automatic fallback enabled                                   │  │
│  │                                                                  │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│         ┌──────────────────┐     ┌──────────────────┐                 │
│         │  View Dashboard  │     │   Visit Site     │                 │
│         └──────────────────┘     └──────────────────┘                 │
│                                                                        │
│                                                                        │
│  ─────────────────────────────────────────────────────────────────     │
│                                                                        │
│  What's Next?                                                          │
│                                                                        │
│  • View search analytics in your dashboard                             │
│  • Re-index after adding new content (Dashboard → Re-index)            │
│  • Customize search settings anytime in Settings → IntentPress         │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### 8.2 Completion Handler

```typescript
async function handleOnboardingComplete(): Promise<void> {
    // 1. Mark onboarding complete
    await apiFetch({
        path: '/intentpress/v1/onboarding/complete',
        method: 'POST'
    });
    
    // 2. Track completion (if analytics enabled)
    trackEvent('onboarding_complete', {
        posts_indexed: indexedCount,
        time_elapsed: Date.now() - startTime,
    });
    
    // 3. Show celebration UI
    setCurrentStep('step_6_complete');
}
```

### 8.3 Post-Completion Actions

| Action | Destination | Effect |
|--------|-------------|--------|
| View Dashboard | `/wp-admin/admin.php?page=intentpress` | Dashboard tab |
| Visit Site | Site frontend (new tab) | Test search live |

### 8.4 Completion Side Effects

```php
/**
 * Execute completion side effects
 */
public function complete_onboarding(): void {
    // 1. Set completion flag
    update_option( 'intentpress_onboarding_complete', true );
    
    // 2. Record completion time
    update_option( 'intentpress_onboarding_completed_at', current_time( 'mysql' ) );
    
    // 3. Clear any dismissal user meta
    $user_id = get_current_user_id();
    delete_user_meta( $user_id, 'intentpress_notice_dismissed' );
    
    // 4. Fire completion action
    do_action( 'intentpress_onboarding_complete', [
        'indexed_count' => get_option( 'intentpress_indexed_count', 0 ),
        'started_at' => get_option( 'intentpress_onboarding_started_at' ),
        'completed_at' => current_time( 'mysql' ),
    ]);
}
```

---

## 9. State Persistence

### 9.1 Onboarding State Schema

```php
/**
 * Onboarding state stored in wp_options
 */
[
    'intentpress_onboarding_state' => [
        'current_step' => 'step_2_api_key',
        'started_at' => '2026-01-08T15:30:00Z',
        'step_times' => [
            'step_1_notice' => '2026-01-08T15:30:00Z',
            'step_2_api_key' => '2026-01-08T15:31:00Z',
        ],
        'api_key_validated' => true,
        'indexing_started' => false,
    ],
    'intentpress_onboarding_complete' => false,
    'intentpress_onboarding_completed_at' => null,
]
```

### 9.2 State Update Functions

```php
/**
 * Update onboarding state
 */
public function update_state( array $updates ): void {
    $state = get_option( 'intentpress_onboarding_state', [] );
    $state = array_merge( $state, $updates );
    update_option( 'intentpress_onboarding_state', $state );
}

/**
 * Record step transition
 */
public function record_step( string $step ): void {
    $state = get_option( 'intentpress_onboarding_state', [] );
    
    $state['current_step'] = $step;
    $state['step_times'][ $step ] = current_time( 'mysql' );
    
    update_option( 'intentpress_onboarding_state', $state );
}
```

### 9.3 TypeScript Interface

```typescript
interface OnboardingState {
    currentStep: OnboardingStep;
    startedAt: string | null;
    stepTimes: Record<OnboardingStep, string>;
    apiKeyValidated: boolean;
    indexingStarted: boolean;
    indexingComplete: boolean;
    testSearchDone: boolean;
}

type OnboardingStep = 
    | 'not_started'
    | 'step_1_notice'
    | 'step_2_api_key'
    | 'step_3_pre_index'
    | 'step_4_indexing'
    | 'step_5_test'
    | 'step_6_complete'
    | 'completed';
```

---

## 10. Resume & Recovery

### 10.1 Resume Logic

```typescript
/**
 * Determine which step to show when user returns
 */
function determineCurrentStep(state: OnboardingState): OnboardingStep {
    // Already complete
    if (state.currentStep === 'completed') {
        return 'completed';
    }
    
    // Check API key first
    if (!state.apiKeyValidated) {
        return 'step_2_api_key';
    }
    
    // Check if indexing was in progress
    if (state.indexingStarted && !state.indexingComplete) {
        return 'step_4_indexing';
    }
    
    // Check if ready for indexing
    if (!state.indexingStarted) {
        return 'step_3_pre_index';
    }
    
    // Check if test was done
    if (state.indexingComplete && !state.testSearchDone) {
        return 'step_5_test';
    }
    
    // Show completion
    return 'step_6_complete';
}
```

### 10.2 Recovery Scenarios

| Scenario | Detection | Action |
|----------|-----------|--------|
| Browser closed during indexing | Check indexing status on load | Resume Step 4 |
| API key became invalid | Validation fails | Return to Step 2 |
| Indexing failed | Error status detected | Show error, allow retry |
| User dismissed notice | Dismissed flag set | Hide notice, allow direct URL |
| Plugin deactivated mid-flow | N/A | Preserve state for reactivation |

### 10.3 Error Recovery UI

```
┌────────────────────────────────────────────────────────────────────────┐
│                                                                        │
│  ⚠️ Indexing encountered an issue                                       │
│                                                                        │
│  Some posts couldn't be indexed due to API errors.                     │
│                                                                        │
│  Successfully indexed: 420 posts                                        │
│  Failed: 15 posts                                                       │
│                                                                        │
│  You can continue with partial indexing (recommended) or retry          │
│  the failed posts.                                                      │
│                                                                        │
│  ┌──────────────────────┐  ┌──────────────────────┐                   │
│  │   Continue Anyway    │  │    Retry Failed      │                   │
│  └──────────────────────┘  └──────────────────────┘                   │
│                                                                        │
│  [View Error Details]                                                   │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 11. Component Specifications

### 11.1 Component Hierarchy

```
OnboardingWizard
├── OnboardingHeader
│   ├── StepIndicator
│   └── ProgressDots
├── OnboardingContent
│   ├── Step1Notice (external - admin notice)
│   ├── Step2ApiKey
│   │   ├── ApiKeyInput
│   │   ├── ValidationStatus
│   │   └── HelpAccordion
│   ├── Step3PreIndex
│   │   ├── ContentSummary
│   │   ├── TimeEstimate
│   │   └── LimitWarning
│   ├── Step4Indexing
│   │   ├── ProgressBar
│   │   ├── ProgressStats
│   │   └── TimeRemaining
│   ├── Step5Test
│   │   ├── SearchInput
│   │   └── SearchResults
│   └── Step6Complete
│       ├── Celebration
│       ├── Summary
│       └── NextActions
└── OnboardingFooter
    └── ActionButtons
```

### 11.2 StepIndicator Component

```typescript
interface StepIndicatorProps {
    steps: Array<{
        id: string;
        label: string;
    }>;
    currentStep: string;
    completedSteps: string[];
}

function StepIndicator({ steps, currentStep, completedSteps }: StepIndicatorProps) {
    return (
        <div className="step-indicator">
            {steps.map((step, index) => {
                const isComplete = completedSteps.includes(step.id);
                const isCurrent = step.id === currentStep;
                
                return (
                    <div 
                        key={step.id}
                        className={cn(
                            'step',
                            isComplete && 'step--complete',
                            isCurrent && 'step--current'
                        )}
                    >
                        <span className="step-icon">
                            {isComplete ? '✓' : index + 1}
                        </span>
                        <span className="step-label">{step.label}</span>
                    </div>
                );
            })}
        </div>
    );
}
```

### 11.3 Main Wizard Component

```typescript
function OnboardingWizard() {
    const [state, setState] = useState<OnboardingState | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    
    // Load initial state
    useEffect(() => {
        loadOnboardingState().then(data => {
            setState(data);
            setIsLoading(false);
        });
    }, []);
    
    if (isLoading) {
        return <Spinner />;
    }
    
    const currentStep = determineCurrentStep(state);
    
    return (
        <div className="intentpress-onboarding">
            <OnboardingHeader 
                currentStep={currentStep}
                completedSteps={getCompletedSteps(state)}
            />
            
            <OnboardingContent>
                {currentStep === 'step_2_api_key' && (
                    <Step2ApiKey 
                        onComplete={() => advanceTo('step_3_pre_index')}
                    />
                )}
                {currentStep === 'step_3_pre_index' && (
                    <Step3PreIndex
                        onStart={() => advanceTo('step_4_indexing')}
                    />
                )}
                {currentStep === 'step_4_indexing' && (
                    <Step4Indexing
                        onComplete={() => advanceTo('step_5_test')}
                        onCancel={() => advanceTo('step_5_test')}
                    />
                )}
                {currentStep === 'step_5_test' && (
                    <Step5Test
                        onComplete={() => advanceTo('step_6_complete')}
                        onSkip={() => advanceTo('step_6_complete')}
                    />
                )}
                {currentStep === 'step_6_complete' && (
                    <Step6Complete
                        onViewDashboard={() => navigateToDashboard()}
                        onVisitSite={() => openSiteInNewTab()}
                    />
                )}
            </OnboardingContent>
        </div>
    );
}
```

---

## 12. Analytics & Tracking

### 12.1 Funnel Events

| Event | Step | Data |
|-------|------|------|
| `onboarding_started` | Step 1 | `timestamp` |
| `onboarding_notice_clicked` | Step 1→2 | `timestamp` |
| `onboarding_notice_dismissed` | Step 1 | `timestamp` |
| `api_key_entered` | Step 2 | `valid: bool` |
| `api_key_validated` | Step 2→3 | `timestamp` |
| `indexing_started` | Step 3→4 | `post_count` |
| `indexing_progress` | Step 4 | `percent`, `rate` |
| `indexing_complete` | Step 4→5 | `duration`, `count`, `errors` |
| `indexing_cancelled` | Step 4 | `percent_complete` |
| `test_search_performed` | Step 5 | `query`, `results_count` |
| `test_search_skipped` | Step 5 | `timestamp` |
| `onboarding_complete` | Step 6 | `total_duration`, `posts_indexed` |

### 12.2 Tracking Implementation

```php
/**
 * Track onboarding event (respects user consent)
 */
public function track_event( string $event, array $data = [] ): void {
    // Check if tracking is enabled
    if ( ! $this->is_tracking_enabled() ) {
        return;
    }
    
    $event_data = array_merge( $data, [
        'event' => 'onboarding_' . $event,
        'timestamp' => current_time( 'mysql' ),
        'site_hash' => wp_hash( home_url() ), // Anonymized
    ]);
    
    // Store locally (for admin display)
    $this->store_local_event( $event_data );
    
    // Fire action for extensions
    do_action( 'intentpress_onboarding_event', $event, $data );
}
```

### 12.3 Conversion Metrics

| Metric | Calculation | Target |
|--------|-------------|--------|
| Step 1 → Step 2 | Notice clicks / Total notices shown | > 50% |
| Step 2 → Step 3 | API validated / Step 2 views | > 80% |
| Step 3 → Step 4 | Indexing started / Step 3 views | > 90% |
| Step 4 → Step 5 | Indexing complete / Started | > 95% |
| Step 5 → Step 6 | Complete clicked / Step 5 views | > 85% |
| Overall | Completed / Started | > 70% |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial functional requirements |
