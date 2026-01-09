# Product Requirements Document: IntentPress MVP
## User Stories & Acceptance Criteria

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related Documents:** PRD-Overview.md, PRD-Onboarding.md, PRD-Error-Handling.md

---

## Table of Contents

1. [User Story Format](#1-user-story-format)
2. [Epic 1: Site Visitor Search](#2-epic-1-site-visitor-search)
3. [Epic 2: Admin Configuration](#3-epic-2-admin-configuration)
4. [Epic 3: Content Indexing](#4-epic-3-content-indexing)
5. [Epic 4: Search Analytics](#5-epic-4-search-analytics)
6. [Epic 5: System Reliability](#6-epic-5-system-reliability)
7. [User Story Summary Matrix](#7-user-story-summary-matrix)

---

## 1. User Story Format

All user stories follow this format:

```
**[ID]** As a [persona], I want [action] so that [benefit].

**Acceptance Criteria:**
- Given [context], when [action], then [expected result]

**Priority:** P0 (Must have) | P1 (Should have) | P2 (Nice to have)
**Estimate:** S (1-2 hrs) | M (half day) | L (1-2 days) | XL (3+ days)
**Dependencies:** [Related stories or technical requirements]
```

---

## 2. Epic 1: Site Visitor Search

### Overview

These stories cover the end-user search experience—what happens when a site visitor uses the search functionality. The search must feel seamless, fast, and deliver notably better results than default WordPress search.

---

### US-101: Basic Semantic Search

**As a** site visitor,  
**I want** to search using natural language queries  
**so that** I find relevant content even when my words don't exactly match the post titles or content.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | A site with indexed posts about "healthy recipes" | I search for "nutritious meal ideas" | I see posts about healthy recipes in the results |
| 2 | A site with a post titled "Getting Started with WordPress" | I search for "how to begin using WP" | That post appears in the top 5 results |
| 3 | The search query contains typos ("recipies" instead of "recipes") | I submit the search | Results still include recipe-related posts |
| 4 | The site has posts in multiple categories | I search for a topic | Results are ranked by semantic relevance, not by date |
| 5 | No posts match the query semantically | I submit the search | I see a helpful "no results" message with suggestions |

**Priority:** P0 (Must have)  
**Estimate:** L (1-2 days)  
**Dependencies:** US-301 (Indexing), US-201 (API Configuration)

**Technical Notes:**
- Use cosine similarity for ranking
- Minimum similarity threshold: 0.5 (configurable via filter)
- Default results limit: 10 per page

---

### US-102: Search Results Display

**As a** site visitor,  
**I want** search results to display consistently with my theme  
**so that** the search experience feels native to the site.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | Theme uses default search results template | Semantic search returns results | Results use the theme's search.php or archive.php template |
| 2 | Results are returned from semantic search | I view the search results page | Each result shows title, excerpt, and date (theme-dependent) |
| 3 | The search query is displayed | I view the results page | My search query appears in the page (properly escaped) |
| 4 | Results are paginated | I click "Next" or page number | I see the next set of results |
| 5 | The URL contains the search query | I share or bookmark the URL | The search can be reproduced from the URL |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-101

**Technical Notes:**
- Use WordPress native pagination
- Override `pre_get_posts` or use `posts_search` filter
- Maintain URL structure: `?s=query`

---

### US-103: Search Response Time

**As a** site visitor,  
**I want** search results to load quickly  
**so that** I don't abandon the search out of frustration.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | A site with < 1,000 indexed posts | I perform a search | Results appear in < 500ms |
| 2 | A site with 1,000-5,000 indexed posts | I perform a search | Results appear in < 750ms |
| 3 | The search requires an API call | I perform a search | A loading indicator is NOT shown (too fast to need one) |
| 4 | Cached results exist for this query | I repeat a search | Results appear in < 100ms |
| 5 | First search of the day (cold cache) | I perform a search | Results still appear in < 1000ms |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-101, US-302 (Caching)

**Technical Notes:**
- Cache embeddings for queries using transients (1 hour TTL)
- Cache full search results using object cache if available
- Use `wp_cache_get/set` with appropriate groups

---

### US-104: Search Highlighting

**As a** site visitor,  
**I want** to understand why results matched my query  
**so that** I can quickly identify the most relevant result.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | Search results are displayed | I view a result excerpt | Keywords from my query are highlighted (bold or styled) |
| 2 | A semantic match has no exact keywords | I view that result | The excerpt shows the most relevant paragraph |
| 3 | Highlighting is applied | The HTML renders | Highlighting uses `<mark>` or `<strong>` tags |
| 4 | The theme has custom excerpt styling | Highlighting is applied | Highlighting CSS doesn't conflict with theme |

**Priority:** P1 (Should have)  
**Estimate:** M (half day)  
**Dependencies:** US-102

**Technical Notes:**
- Use `the_excerpt` filter
- Provide CSS class `.intentpress-highlight` for theme customization
- Fallback: No highlighting if parsing fails

---

### US-105: Empty Search Handling

**As a** site visitor,  
**I want** helpful feedback when my search has no results  
**so that** I can refine my search or find content another way.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | No semantic matches found | I view the results page | I see a friendly message: "No results found for '[query]'" |
| 2 | No results found | I view the results page | I see suggestions: "Try different keywords or browse categories" |
| 3 | The search query is very short (1-2 chars) | I submit the search | I see a message prompting for a longer query |
| 4 | The search query is empty | I submit the form | I'm redirected to the search page with a prompt to enter a query |

**Priority:** P0 (Must have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** US-101

**Technical Notes:**
- All messages must be translatable using `__()` and text domain
- Consider showing popular/recent posts as alternatives

---

## 3. Epic 2: Admin Configuration

### Overview

These stories cover the WordPress admin settings interface where site owners configure IntentPress. The settings must be simple for non-technical users while providing enough options for developers.

---

### US-201: API Key Configuration

**As a** site administrator,  
**I want** to enter and validate my API key  
**so that** the plugin can connect to the AI embedding service.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I navigate to Settings → IntentPress | The page loads | I see a clearly labeled API key input field |
| 2 | I enter an API key and click Save | The key is valid | I see a success message and green checkmark |
| 3 | I enter an API key and click Save | The key is invalid | I see an error: "Your API key appears to be invalid. Please check it." |
| 4 | I enter an API key and click Save | The key is valid | The key is stored encrypted in the database |
| 5 | I view the settings after saving a key | The page loads | The key is masked (shows last 4 characters only: `•••••••••sk-abc1`) |
| 6 | No API key is configured | I view any admin page | I see a dismissible notice prompting me to configure the key |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** None

**Technical Notes:**
- Encrypt using `openssl_encrypt()` with `AUTH_KEY` salt
- Validate by making a test embedding call to OpenAI
- Store in `wp_options` as `intentpress_api_key`

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────┐
│ IntentPress Settings                                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  API Configuration                                      │
│  ─────────────────                                      │
│                                                         │
│  OpenAI API Key                                         │
│  ┌─────────────────────────────────┐  ┌──────────────┐ │
│  │ •••••••••••••••••••sk-abc1      │  │ Save Changes │ │
│  └─────────────────────────────────┘  └──────────────┘ │
│                                                         │
│  ✓ API key is valid and connected                      │
│                                                         │
│  Need an API key? Get one at platform.openai.com       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

### US-202: Post Type Selection

**As a** site administrator,  
**I want** to choose which post types are indexed  
**so that** search only covers relevant content.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view IntentPress settings | The page loads | I see checkboxes for all public post types (Posts, Pages, custom) |
| 2 | Posts and Pages are shown | Default state for new install | Posts and Pages are checked by default |
| 3 | I uncheck "Pages" and save | Re-indexing occurs | Only Posts are searchable |
| 4 | Custom post types exist (e.g., "Products") | I view settings | Custom post types appear in the list |
| 5 | I change post type selection | I save settings | I see a notice: "Re-index required for changes to take effect" |

**Priority:** P1 (Should have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** US-201

**Technical Notes:**
- Use `get_post_types(['public' => true])`
- Store as array in `intentpress_indexed_post_types`
- Trigger re-index prompt on change

---

### US-203: Search Behavior Settings

**As a** site administrator,  
**I want** to configure basic search behavior  
**so that** I can tune the search experience for my site.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view IntentPress settings | The page loads | I see a "Results per page" number input |
| 2 | Default value for results per page | New install | Default is 10 |
| 3 | I set results per page to 20 | A visitor searches | They see 20 results per page |
| 4 | I view settings | The page loads | I see an "Enable fallback search" toggle (default: ON) |
| 5 | Fallback is enabled and API fails | A visitor searches | They see WordPress default search results |
| 6 | Fallback is disabled and API fails | A visitor searches | They see a "Search temporarily unavailable" message |

**Priority:** P1 (Should have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** US-201

**Technical Notes:**
- Results per page: 1-50 range, validated
- Fallback toggle: boolean option `intentpress_fallback_enabled`

---

### US-204: Settings Page Access Control

**As a** site administrator,  
**I want** only authorized users to access IntentPress settings  
**so that** search configuration remains secure.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I'm logged in as Administrator | I navigate to Settings → IntentPress | I can view and modify all settings |
| 2 | I'm logged in as Editor | I look for IntentPress settings | I don't see the menu item |
| 3 | I'm logged in as Editor | I directly access the settings URL | I see "You do not have permission to access this page" |
| 4 | I'm not logged in | I access the REST API settings endpoint | I receive a 401 Unauthorized response |

**Priority:** P0 (Must have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** US-201

**Technical Notes:**
- Capability: `manage_options` (Administrator level)
- Provide filter for custom capability: `intentpress_settings_capability`

---

### US-205: Settings Help & Documentation

**As a** site administrator,  
**I want** contextual help within the settings page  
**so that** I understand what each option does without leaving the page.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view any settings field | I look at the field | I see a brief description below it |
| 2 | I need more help | I look at the page | I see a "Help" tab in the WordPress help dropdown |
| 3 | I click the Help tab | The panel opens | I see documentation for all settings and links to external docs |
| 4 | I want to report an issue | I look at the settings page | I see a "Support" link in the footer |

**Priority:** P2 (Nice to have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** US-201

---

## 4. Epic 3: Content Indexing

### Overview

These stories cover the process of generating and storing vector embeddings for site content. Indexing must be reliable, provide clear feedback, and handle large sites gracefully.

---

### US-301: Manual Index Trigger

**As a** site administrator,  
**I want** to manually trigger content indexing  
**so that** I can control when the embedding generation occurs.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view the IntentPress dashboard | The page loads | I see a "Start Indexing" button |
| 2 | No indexing is in progress | I click "Start Indexing" | Indexing begins and button changes to "Indexing..." |
| 3 | Indexing is in progress | I view the page | I see a progress bar showing X of Y posts indexed |
| 4 | Indexing completes | I view the page | I see "Indexing complete. 523 posts indexed." |
| 5 | API key is not configured | I click "Start Indexing" | Button is disabled with tooltip: "Configure API key first" |

**Priority:** P0 (Must have)  
**Estimate:** L (1-2 days)  
**Dependencies:** US-201

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────┐
│ Content Indexing                                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Indexing Status                                        │
│  ┌───────────────────────────────────────────────────┐ │
│  │ ████████████████░░░░░░░░░░░░░░░░░░░░░  45%       │ │
│  │ 225 of 500 posts indexed                          │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  Estimated time remaining: 2 minutes                   │
│                                                         │
│  ┌───────────────┐                                     │
│  │ Cancel        │                                     │
│  └───────────────┘                                     │
│                                                         │
│  Last indexed: January 8, 2026 at 3:45 PM              │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

### US-302: Indexing Progress Feedback

**As a** site administrator,  
**I want** real-time feedback during indexing  
**so that** I know the process is working and how long it will take.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | Indexing is in progress | I watch the progress bar | It updates at least every 5 seconds |
| 2 | 100 posts have been indexed | I view the progress | Time estimate is reasonably accurate (±30%) |
| 3 | An individual post fails to index | Indexing continues | That post is skipped, logged, and indexing continues |
| 4 | I navigate away from the page | I return to the page | Progress is shown correctly (indexing continues in background) |
| 5 | I close my browser | Indexing is in progress | Indexing continues server-side |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-301

**Technical Notes:**
- Use WordPress background processing (Action Scheduler or custom implementation)
- Store progress in `intentpress_indexing_status` option
- Poll via REST API every 5 seconds during active indexing

---

### US-303: Index Specific Content

**As a** site administrator,  
**I want** to re-index a specific post  
**so that** I can update the search index after editing important content.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I'm editing a post | I look at the publish metabox | I see "Re-index for search" button |
| 2 | The post is published | I click "Re-index for search" | That post's embedding is regenerated |
| 3 | Re-indexing completes | I'm still on the edit screen | I see "✓ Search index updated" notice |
| 4 | The post is a draft | I look for the button | The button is not shown (drafts aren't indexed) |

**Priority:** P2 (Nice to have)  
**Estimate:** M (half day)  
**Dependencies:** US-301

---

### US-304: Indexing Error Handling

**As a** site administrator,  
**I want** clear information when indexing fails  
**so that** I can troubleshoot issues.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | API rate limit is hit | Indexing is running | Indexing pauses, shows "Rate limited. Resuming in 60 seconds..." |
| 2 | API key becomes invalid | Indexing is running | Indexing stops with error: "API key error. Please check settings." |
| 3 | Network timeout occurs | Indexing is running | That batch is retried up to 3 times before failing |
| 4 | Some posts failed to index | Indexing completes | I see "Indexing complete with errors. 5 posts failed." with details link |
| 5 | I click the details link | Error details shown | I see which posts failed and why |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-301, US-302

---

### US-305: Free Tier Indexing Limits

**As a** free tier user,  
**I want** to understand when I've hit indexing limits  
**so that** I can decide whether to upgrade.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I have 600 posts, limit is 500 | I start indexing | First 500 posts are indexed, then I see limit message |
| 2 | Limit is reached | I view the dashboard | I see "500/500 posts indexed. Upgrade to index all 600 posts." |
| 3 | I'm approaching the limit | I have 450/500 indexed | I see a warning: "Approaching index limit (450/500)" |
| 4 | I click "Upgrade" | Modal or page appears | I see pricing and upgrade options |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-301

**Technical Notes:**
- Free tier limit: 500 posts
- Priority: Index by post date (newest first) or let user configure
- Store indexed count in `intentpress_indexed_count`

---

## 5. Epic 4: Search Analytics

### Overview

These stories cover the admin analytics dashboard that helps site owners understand how search is performing and what visitors are looking for.

---

### US-401: Search Analytics Dashboard

**As a** site administrator,  
**I want** to see basic search analytics  
**so that** I understand how visitors use search on my site.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I navigate to IntentPress dashboard | The page loads | I see a summary card: "Searches this month: 1,234" |
| 2 | Search data exists | I view the dashboard | I see "Top search terms" list (top 10) |
| 3 | Search data exists | I view the dashboard | I see "Searches with no results" list (top 10) |
| 4 | I just installed the plugin | I view analytics | I see "Not enough data yet. Analytics will appear after 10+ searches." |
| 5 | Date range selector exists | I change the range | Analytics update to reflect selected period |

**Priority:** P1 (Should have)  
**Estimate:** L (1-2 days)  
**Dependencies:** US-101

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────┐
│ Search Analytics                          [Last 30 days▼]│
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │    1,234    │  │      89%    │  │      11%    │     │
│  │   Searches  │  │   With      │  │   No        │     │
│  │   this      │  │   Results   │  │   Results   │     │
│  │   month     │  │             │  │             │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│                                                         │
│  Top Search Terms          Searches with No Results     │
│  ─────────────────         ────────────────────────     │
│  1. wordpress tutorial 45  1. api integration      8    │
│  2. contact form       38  2. pricing page         5    │
│  3. getting started    32  3. refund policy        4    │
│  4. plugin reviews     28                               │
│  5. hosting            24                               │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

### US-402: Search Logging

**As a** site administrator,  
**I want** search queries to be logged  
**so that** analytics can be generated.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | Analytics are enabled (default) | A visitor searches | Query is logged with timestamp |
| 2 | A search is logged | I view raw logs | I see: timestamp, query, results count, response time |
| 3 | Privacy setting is enabled | A visitor searches | No IP address or user identifier is stored |
| 4 | Data retention period passes | 90 days elapse | Old search logs are automatically deleted |
| 5 | I want to disable logging | I find toggle in settings | I can disable search logging entirely |

**Priority:** P1 (Should have)  
**Estimate:** M (half day)  
**Dependencies:** US-101

**Technical Notes:**
- Store in custom table: `wp_intentpress_search_logs`
- Columns: id, query, results_count, response_time_ms, searched_at
- NO user identifiers by default
- Cron job for data retention cleanup

---

### US-403: Free Tier Search Limits

**As a** free tier user,  
**I want** to understand my search usage and limits  
**so that** I can manage my usage or decide to upgrade.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view the dashboard | The page loads | I see "Searches used: 750 / 1,000 this month" |
| 2 | Usage reaches 80% (800/1,000) | I view dashboard | I see a warning: "Approaching monthly search limit" |
| 3 | Usage reaches 100% | A visitor searches | Search falls back to WordPress default |
| 4 | Limit is reached | I view dashboard | I see "Search limit reached. Upgrade for unlimited searches." |
| 5 | New month begins | Counter resets | Usage shows "0 / 1,000 this month" |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-401

**Technical Notes:**
- Reset counter on 1st of each month
- Store in `intentpress_monthly_searches`
- Use transient for quick access

---

## 6. Epic 5: System Reliability

### Overview

These stories cover fallback behavior, error recovery, and system robustness to ensure the plugin never breaks a site.

---

### US-501: Automatic Fallback Search

**As a** site visitor,  
**I want** search to always work  
**so that** I can find content even when there are technical issues.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | OpenAI API is unreachable | I perform a search | I see results from WordPress default search |
| 2 | API returns an error | I perform a search | I see results from WordPress default search |
| 3 | Fallback is used | I view results | I'm NOT notified (transparent to user) |
| 4 | Fallback is used | Admin views dashboard | Admin sees "Fallback search used 12 times today" warning |
| 5 | API key is not configured | I perform a search | WordPress default search is used |

**Priority:** P0 (Must have)  
**Estimate:** M (half day)  
**Dependencies:** US-101

---

### US-502: Health Check Dashboard

**As a** site administrator,  
**I want** to see the system health status  
**so that** I can quickly identify and resolve issues.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view IntentPress dashboard | The page loads | I see a "System Status" section |
| 2 | Everything is working | I view status | All items show green checkmarks |
| 3 | API connection fails | I view status | API status shows red X with error message |
| 4 | Indexing is stale (>7 days) | I view status | Index status shows yellow warning |
| 5 | I click "Run health check" | Check runs | All components are tested and status updates |

**Status Items to Check:**
- API key configured: Yes/No
- API connection: Connected/Error
- Posts indexed: X of Y
- Last index date: Date or "Never"
- Average response time: Xms
- Fallback usage (24h): X times

**Priority:** P1 (Should have)  
**Estimate:** M (half day)  
**Dependencies:** US-201, US-301

---

### US-503: Graceful Deactivation

**As a** site administrator,  
**I want** search to work normally after deactivating IntentPress  
**so that** my site isn't broken if I need to disable the plugin.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | IntentPress is active | I deactivate the plugin | No PHP errors occur |
| 2 | IntentPress is deactivated | A visitor searches | WordPress default search works normally |
| 3 | IntentPress is deactivated | I view admin | No IntentPress menu items appear |
| 4 | IntentPress is deactivated | Plugin data | Data is preserved (not deleted) |
| 5 | IntentPress is reactivated | I view dashboard | Previous settings and index are restored |

**Priority:** P0 (Must have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** None

---

### US-504: Clean Uninstallation

**As a** site administrator,  
**I want** the option to completely remove all IntentPress data  
**so that** no traces remain after uninstalling.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | Setting "Delete data on uninstall" is ON | I delete the plugin | All options and tables are removed |
| 2 | Setting "Delete data on uninstall" is OFF | I delete the plugin | Data is preserved (can be restored if reinstalled) |
| 3 | Default setting | New install | "Delete data on uninstall" is OFF |
| 4 | I delete the plugin | Deletion completes | No errors, site functions normally |

**Priority:** P1 (Should have)  
**Estimate:** S (1-2 hrs)  
**Dependencies:** None

**Technical Notes:**
- Use `uninstall.php` hook
- Remove: options, transients, custom tables, cron jobs

---

### US-505: Search Test Tool

**As a** site administrator,  
**I want** to test search queries from the admin  
**so that** I can verify search is working correctly without leaving the dashboard.

**Acceptance Criteria:**

| # | Given | When | Then |
|---|-------|------|------|
| 1 | I view IntentPress dashboard | The page loads | I see a "Test Search" input field |
| 2 | I enter a query and click "Test" | Results return | I see top 5 results with relevance scores |
| 3 | Results are shown | I view a result | I see: Title, Score (0.85), Excerpt snippet |
| 4 | No results found | I view the test area | I see "No results found" message |
| 5 | API error occurs | I view the test area | I see the specific error message |

**Priority:** P2 (Nice to have)  
**Estimate:** M (half day)  
**Dependencies:** US-101, US-301

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────┐
│ Test Search                                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────┐  ┌──────────────┐ │
│  │ healthy meal ideas              │  │    Test      │ │
│  └─────────────────────────────────┘  └──────────────┘ │
│                                                         │
│  Results (0.32 seconds):                               │
│                                                         │
│  1. Quick Healthy Dinners for Busy Weeknights          │
│     Score: 0.92 | Post ID: 1234                        │
│     "Looking for healthy meal ideas? These quick..."    │
│                                                         │
│  2. Meal Prep Guide: Healthy Eating Made Easy          │
│     Score: 0.87 | Post ID: 567                         │
│     "Healthy eating doesn't have to be complicated..." │
│                                                         │
│  3. 10 Nutritious Recipes Under 30 Minutes             │
│     Score: 0.81 | Post ID: 890                         │
│     "When you need nutritious recipes fast..."         │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 7. User Story Summary Matrix

| ID | Story Title | Priority | Estimate | Epic |
|----|-------------|----------|----------|------|
| US-101 | Basic Semantic Search | P0 | L | Site Visitor Search |
| US-102 | Search Results Display | P0 | M | Site Visitor Search |
| US-103 | Search Response Time | P0 | M | Site Visitor Search |
| US-104 | Search Highlighting | P1 | M | Site Visitor Search |
| US-105 | Empty Search Handling | P0 | S | Site Visitor Search |
| US-201 | API Key Configuration | P0 | M | Admin Configuration |
| US-202 | Post Type Selection | P1 | S | Admin Configuration |
| US-203 | Search Behavior Settings | P1 | S | Admin Configuration |
| US-204 | Settings Page Access Control | P0 | S | Admin Configuration |
| US-205 | Settings Help & Documentation | P2 | S | Admin Configuration |
| US-301 | Manual Index Trigger | P0 | L | Content Indexing |
| US-302 | Indexing Progress Feedback | P0 | M | Content Indexing |
| US-303 | Index Specific Content | P2 | M | Content Indexing |
| US-304 | Indexing Error Handling | P0 | M | Content Indexing |
| US-305 | Free Tier Indexing Limits | P0 | M | Content Indexing |
| US-401 | Search Analytics Dashboard | P1 | L | Search Analytics |
| US-402 | Search Logging | P1 | M | Search Analytics |
| US-403 | Free Tier Search Limits | P0 | M | Search Analytics |
| US-501 | Automatic Fallback Search | P0 | M | System Reliability |
| US-502 | Health Check Dashboard | P1 | M | System Reliability |
| US-503 | Graceful Deactivation | P0 | S | System Reliability |
| US-504 | Clean Uninstallation | P1 | S | System Reliability |
| US-505 | Search Test Tool | P2 | M | System Reliability |

### MVP P0 Stories (Must Have)

These 12 stories are required for MVP:

1. US-101: Basic Semantic Search
2. US-102: Search Results Display
3. US-103: Search Response Time
4. US-105: Empty Search Handling
5. US-201: API Key Configuration
6. US-204: Settings Page Access Control
7. US-301: Manual Index Trigger
8. US-302: Indexing Progress Feedback
9. US-304: Indexing Error Handling
10. US-305: Free Tier Indexing Limits
11. US-403: Free Tier Search Limits
12. US-501: Automatic Fallback Search
13. US-503: Graceful Deactivation

**Estimated MVP Development Time:** ~8-10 days (excluding testing, polish, and documentation)

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial user stories |
