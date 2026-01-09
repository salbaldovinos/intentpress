# Product Requirements Document: IntentPress MVP
## Onboarding Flow Specification

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Related Documents:** PRD-Overview.md, PRD-User-Stories.md, PRD-Error-Handling.md

---

## Table of Contents

1. [Onboarding Philosophy](#1-onboarding-philosophy)
2. [Flow Overview](#2-flow-overview)
3. [Step-by-Step Specification](#3-step-by-step-specification)
4. [UI Components & Copy](#4-ui-components--copy)
5. [State Management](#5-state-management)
6. [Edge Cases & Recovery](#6-edge-cases--recovery)
7. [Success Metrics](#7-success-metrics)

---

## 1. Onboarding Philosophy

### 1.1 Design Principles

**Time to Value: Under 5 Minutes**
From plugin activation to first successful semantic search, the user should experience value in under 5 minutes. Every step must be necessary; remove anything that isn't.

**Progressive Disclosure**
Show only what's needed at each step. Advanced options exist but are hidden from the critical path. Users who want to customize can find options; users who want to get started can ignore them.

**No Dead Ends**
Every error state includes a clear next action. Every screen has a way forward or back. Users should never feel stuck.

**Celebrate Success**
Acknowledge completion of key milestones. A small celebration at the end increases perceived value and completion satisfaction.

### 1.2 Target User

Primary onboarding target: **Sarah the Site Owner** (non-technical)
- Wants search to "just work"
- May not know what an API key is
- Needs clear guidance without jargon
- Will abandon if process feels complicated

### 1.3 Success Definition

Onboarding is complete when:
1. ✓ API key is configured and validated
2. ✓ Content indexing has been initiated
3. ✓ At least one test search has been performed
4. ✓ User understands search is now active

---

## 2. Flow Overview

### 2.1 Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         INTENTPRESS ONBOARDING FLOW                       │
└──────────────────────────────────────────────────────────────────────────┘

    ┌─────────────┐
    │   Install   │
    │   Plugin    │
    └──────┬──────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────────┐
    │  STEP 1: ACTIVATION NOTICE                                          │
    │  Admin notice appears on all admin pages                             │
    │  "IntentPress is ready! Set up semantic search →"                    │
    └──────────────────────────────────┬──────────────────────────────────┘
                                       │
                     ┌─────────────────┴─────────────────┐
                     │                                   │
                     ▼                                   ▼
              [Click "Get Started"]              [Dismiss Notice]
                     │                                   │
                     │                           (Can access via
                     │                            Settings menu)
                     ▼
    ┌─────────────────────────────────────────────────────────────────────┐
    │  STEP 2: API KEY CONFIGURATION                                       │
    │  Settings → IntentPress → Welcome wizard                             │
    │  "Enter your OpenAI API key to enable semantic search"               │
    └──────────────────────────────────┬──────────────────────────────────┘
                                       │
                     ┌─────────────────┼─────────────────┐
                     │                 │                 │
                     ▼                 ▼                 ▼
              [Key Valid]        [Key Invalid]    [No Key/Skip]
                     │                 │                 │
                     │                 │                 ▼
                     │                 │         Search uses fallback
                     │                 │         (Default WordPress)
                     │                 ▼
                     │         Show error message
                     │         "API key invalid..."
                     │         [Try again]
                     ▼
    ┌─────────────────────────────────────────────────────────────────────┐
    │  STEP 3: START INDEXING                                              │
    │  "Your API key is connected! Ready to index your content?"           │
    │  [Start Indexing] button                                             │
    └──────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
    ┌─────────────────────────────────────────────────────────────────────┐
    │  STEP 4: INDEXING PROGRESS                                           │
    │  Progress bar: "Indexing... 125 of 500 posts"                        │
    │  Estimated time remaining shown                                       │
    └──────────────────────────────────┬──────────────────────────────────┘
                                       │
                     ┌─────────────────┼─────────────────┐
                     │                 │                 │
                     ▼                 ▼                 ▼
              [Complete]         [Errors]         [User Cancels]
                     │                 │                 │
                     │                 ▼                 ▼
                     │         Show error summary   Partial index OK
                     │         [Retry Failed]       Can retry later
                     ▼
    ┌─────────────────────────────────────────────────────────────────────┐
    │  STEP 5: TEST YOUR SEARCH                                            │
    │  "Success! Try searching your site:"                                 │
    │  [Search input field] [Test]                                         │
    └──────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
    ┌─────────────────────────────────────────────────────────────────────┐
    │  STEP 6: COMPLETION CELEBRATION                                      │
    │  "🎉 Semantic search is live!"                                       │
    │  "Visitors to your site now get smarter search results."             │
    │  [View Dashboard] [Go to Site]                                       │
    └─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Step Summary

| Step | Screen | Required? | Est. Time |
|------|--------|-----------|-----------|
| 1 | Activation Notice | Yes | 5 sec |
| 2 | API Key Configuration | Yes | 2-3 min |
| 3 | Start Indexing | Yes | 10 sec |
| 4 | Indexing Progress | Yes | 1-5 min* |
| 5 | Test Search | Recommended | 30 sec |
| 6 | Completion | Yes | 10 sec |

*Indexing time depends on content volume (100 posts/min estimate)

---

## 3. Step-by-Step Specification

### 3.1 Step 1: Activation Notice

**Trigger:** Plugin activation via Plugins → Installed Plugins → Activate

**Display:** Admin notice on all WordPress admin pages

**Behavior:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ ℹ️  IntentPress is ready to upgrade your search!                    [×] │
│     Enable AI-powered semantic search in just a few minutes.            │
│     [Get Started →]                                                     │
└─────────────────────────────────────────────────────────────────────────┘
```

**Notice Type:** `notice-info` (blue)

**Dismissibility:** 
- Dismissible via "×" button
- Dismissal is remembered for 30 days
- After 30 days, shows again if setup not complete

**Technical Implementation:**
- Use `admin_notices` hook
- Check `intentpress_onboarding_complete` option
- Store dismissal in user meta: `intentpress_notice_dismissed`

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| 1 | Plugin just activated | I view any admin page | I see the activation notice |
| 2 | Notice is displayed | I click "Get Started" | I'm taken to IntentPress settings |
| 3 | Notice is displayed | I click "×" to dismiss | Notice disappears and doesn't show for 30 days |
| 4 | I've completed onboarding | I view admin pages | The activation notice never appears |

---

### 3.2 Step 2: API Key Configuration

**Trigger:** Click "Get Started" from notice OR navigate to Settings → IntentPress

**Display:** Full settings page with wizard-like first section

**Layout:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ IntentPress                                                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Welcome to IntentPress! 👋                                             │
│                                                                         │
│  Let's set up semantic search for your site. This takes about          │
│  5 minutes and will dramatically improve your search results.           │
│                                                                         │
│  ───────────────────────────────────────────────────────────────────── │
│                                                                         │
│  Step 1 of 3: Connect to AI Service                                    │
│                                                                         │
│  IntentPress uses OpenAI's embedding technology to understand search   │
│  queries. You'll need an API key to continue.                          │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  OpenAI API Key                                                   │  │
│  │  ┌────────────────────────────────────────────┐                  │  │
│  │  │ sk-...                                     │                  │  │
│  │  └────────────────────────────────────────────┘                  │  │
│  │                                                                   │  │
│  │  🔗 Don't have an API key? Get one at platform.openai.com/api-keys│  │
│  │                                                                   │  │
│  │  Your API key is stored securely and encrypted in your database. │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│           ┌─────────────────────┐                                      │
│           │  Validate & Continue │                                      │
│           └─────────────────────┘                                      │
│                                                                         │
│  ┌────────────────────────────────────────────────────────────────────┐│
│  │ 💡 Tip: OpenAI's API is pay-per-use. Semantic search typically     ││
│  │    costs less than $1/month for sites with under 1,000 posts.     ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Validation Flow:**

1. User enters API key
2. Click "Validate & Continue"
3. Button shows loading spinner: "Validating..."
4. System makes test API call to OpenAI
5. **Success:** Green checkmark, proceed to Step 3
6. **Failure:** Red error message, stay on Step 2

**Validation States:**

| State | UI Feedback |
|-------|-------------|
| Empty | "Please enter your API key" (on submit attempt) |
| Validating | Spinner + "Validating..." + button disabled |
| Valid | ✓ "API key is valid!" (green) + auto-advance in 1.5s |
| Invalid format | "This doesn't look like a valid OpenAI API key. Keys start with 'sk-'" |
| Invalid key | "This API key is invalid. Please check it and try again." |
| Network error | "Couldn't connect to OpenAI. Please check your connection." |
| Rate limited | "OpenAI rate limit reached. Please wait a moment and try again." |

**Copy for "Don't have an API key?" expandable section:**
```
How to get an OpenAI API Key:

1. Go to platform.openai.com and create an account (or sign in)
2. Navigate to API Keys in your account settings
3. Click "Create new secret key"
4. Copy the key and paste it above

Note: OpenAI requires a payment method on file, but semantic search
typically costs less than $1/month for most sites.
```

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| 1 | Settings page loads | I view the page | I see clear instructions and API key input |
| 2 | I haven't entered a key | I click "Validate" | I see "Please enter your API key" |
| 3 | I enter a valid key | I click "Validate" | I see success and advance to Step 3 |
| 4 | I enter an invalid key | I click "Validate" | I see error message and stay on this step |
| 5 | I click the help link | Link opens | OpenAI platform page opens in new tab |

---

### 3.3 Step 3: Start Indexing

**Trigger:** Successful API key validation

**Display:** Same page, scrolled/transitioned to indexing section

**Layout:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ✓ Step 1: API Connected                                               │
│                                                                         │
│  ───────────────────────────────────────────────────────────────────── │
│                                                                         │
│  Step 2 of 3: Index Your Content                                       │
│                                                                         │
│  IntentPress needs to analyze your content to enable semantic search.  │
│  This is a one-time process that takes about 1 minute per 100 posts.   │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                                                                   │  │
│  │  📊 Content to index:                                            │  │
│  │                                                                   │  │
│  │     Posts:  423                                                   │  │
│  │     Pages:   12                                                   │  │
│  │     ─────────                                                     │  │
│  │     Total:  435 items                                             │  │
│  │                                                                   │  │
│  │  ⏱️  Estimated time: ~4 minutes                                   │  │
│  │                                                                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│           ┌─────────────────────┐                                      │
│           │   Start Indexing    │                                      │
│           └─────────────────────┘                                      │
│                                                                         │
│  You can continue using WordPress while indexing runs in the           │
│  background. We'll let you know when it's done.                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Free Tier Limit Display (if applicable):**
```
┌──────────────────────────────────────────────────────────────────────────┐
│  ⚠️ Free tier includes 500 posts. You have 623 posts.                    │
│                                                                          │
│  The first 500 posts (by date, newest first) will be indexed.           │
│  Upgrade to IntentPress Pro to index all content.                        │
│                                                                          │
│  [Start Indexing (500 posts)]  [Learn about Pro →]                       │
└──────────────────────────────────────────────────────────────────────────┘
```

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| 1 | API key is valid | I reach Step 3 | I see content count and time estimate |
| 2 | I have 435 posts/pages | I view Step 3 | Estimate shows ~4-5 minutes |
| 3 | I click "Start Indexing" | Button is clicked | Indexing begins, proceed to Step 4 |
| 4 | I exceed free tier limit | I view Step 3 | I see warning about limit with upgrade option |
| 5 | I navigate away | Indexing hasn't started | No indexing occurs (expected) |

---

### 3.4 Step 4: Indexing Progress

**Trigger:** Click "Start Indexing" in Step 3

**Display:** Progress UI replaces Step 3 content

**Layout:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ✓ Step 1: API Connected                                               │
│  ○ Step 2: Indexing Content                                            │
│                                                                         │
│  ───────────────────────────────────────────────────────────────────── │
│                                                                         │
│  Indexing Your Content...                                               │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                                                                   │  │
│  │  ████████████████████░░░░░░░░░░░░░░░░░░░░░  45%                  │  │
│  │                                                                   │  │
│  │  196 of 435 items indexed                                        │  │
│  │                                                                   │  │
│  │  ⏱️ Estimated time remaining: 2 minutes                          │  │
│  │                                                                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│           ┌─────────────────────┐                                      │
│           │       Cancel        │                                      │
│           └─────────────────────┘                                      │
│                                                                         │
│  💡 You can navigate away from this page. Indexing will continue       │
│     in the background and we'll notify you when complete.              │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Progress Updates:**
- Poll every 5 seconds via REST API
- Update progress bar, count, and time estimate
- Show current item being processed (optional, reduces visual noise)

**Completion Transition:**
When indexing completes (100%), auto-advance to Step 5 after 1 second delay.

**Error During Indexing:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│  ⚠️ Some items couldn't be indexed                                       │
│                                                                          │
│  420 of 435 items were indexed successfully.                            │
│  15 items failed due to API errors.                                     │
│                                                                          │
│  [View Failed Items]  [Retry Failed]  [Continue Anyway →]               │
└──────────────────────────────────────────────────────────────────────────┘
```

**Cancel Behavior:**
- User clicks "Cancel"
- Confirm: "Stop indexing? You can resume later from the dashboard."
- If confirmed: Stop processing, keep what's indexed, show partial completion message
- Search will work with partial index

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| 1 | Indexing starts | I watch the screen | Progress bar updates every 5 seconds |
| 2 | 196 of 435 indexed | I view progress | Shows "45%" and "196 of 435 items indexed" |
| 3 | Indexing completes | 100% reached | Auto-advance to Step 5 after 1 second |
| 4 | I navigate away | Return to settings | Progress is shown correctly |
| 5 | I click "Cancel" | Confirmation shown | Can stop indexing and continue with partial index |
| 6 | Some items fail | Indexing finishes | I see summary of successes and failures |

---

### 3.5 Step 5: Test Your Search

**Trigger:** Indexing completion (or "Continue Anyway" after partial completion)

**Display:** Interactive search test section

**Layout:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ✓ Step 1: API Connected                                               │
│  ✓ Step 2: Content Indexed (435 items)                                 │
│                                                                         │
│  ───────────────────────────────────────────────────────────────────── │
│                                                                         │
│  Step 3 of 3: Test Your Search                                         │
│                                                                         │
│  Let's make sure everything is working! Try a search:                  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                                                                   │  │
│  │  ┌──────────────────────────────────────┐  ┌────────────────┐    │  │
│  │  │ Try: "getting started"               │  │    Search      │    │  │
│  │  └──────────────────────────────────────┘  └────────────────┘    │  │
│  │                                                                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                                                                         │
│           ┌─────────────────────┐                                      │
│           │   Skip for Now →    │                                      │
│           └─────────────────────┘                                      │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**After Search Submitted:**
```
┌──────────────────────────────────────────────────────────────────────────┐
│                                                                          │
│  Search Results for "getting started":                          0.23s   │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  1. Getting Started with WordPress                                 │ │
│  │     Relevance: ●●●●●○ (92%)                                       │ │
│  │     A complete guide to setting up your first WordPress site...    │ │
│  │                                                                    │ │
│  │  2. Beginner's Guide to Site Configuration                         │ │
│  │     Relevance: ●●●●○○ (78%)                                       │ │
│  │     Learn the basics of configuring your new website...           │ │
│  │                                                                    │ │
│  │  3. Your First Blog Post: A Starter's Handbook                    │ │
│  │     Relevance: ●●●○○○ (65%)                                       │ │
│  │     Tips for writing your first content as a new blogger...       │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                          │
│  ✨ Notice how results match the intent, not just keywords!             │
│                                                                          │
│           ┌─────────────────────┐                                       │
│           │   Complete Setup →  │                                       │
│           └─────────────────────┘                                       │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

**Suggested Queries:**
Provide 2-3 suggested queries based on actual content:
- Analyze indexed content for common topics
- Suggest queries that demonstrate semantic matching
- Fallback suggestions: "getting started", "how to", "guide"

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| 1 | Indexing complete | I reach Step 5 | I see a search input with placeholder suggestion |
| 2 | I enter a query | I click "Search" | Results appear with relevance scores |
| 3 | Results are shown | I view them | I see how semantic matching improved results |
| 4 | I want to skip | I click "Skip for Now" | I proceed to completion screen |
| 5 | Search fails | API error occurs | I see error message with "Try Again" option |

---

### 3.6 Step 6: Completion Celebration

**Trigger:** Click "Complete Setup" from Step 5 OR "Skip for Now"

**Display:** Full-screen (or modal) completion celebration

**Layout:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                                                                         │
│                              🎉                                         │
│                                                                         │
│                    Semantic Search is Live!                             │
│                                                                         │
│         Visitors to your site now get smarter search results.          │
│                                                                         │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                                                                   │  │
│  │  ✓  435 posts and pages indexed                                  │  │
│  │  ✓  AI-powered search active                                     │  │
│  │  ✓  Automatic fallback enabled                                   │  │
│  │                                                                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│         ┌──────────────────┐     ┌──────────────────┐                  │
│         │  View Dashboard  │     │   Visit Site     │                  │
│         └──────────────────┘     └──────────────────┘                  │
│                                                                         │
│                                                                         │
│  ───────────────────────────────────────────────────────────────────── │
│                                                                         │
│  What's Next?                                                           │
│                                                                         │
│  • View search analytics in your dashboard                             │
│  • Re-index after adding new content (Dashboard → Re-index)            │
│  • Customize search settings anytime in Settings → IntentPress         │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Actions:**
- "View Dashboard" → Navigate to IntentPress dashboard
- "Visit Site" → Open site frontend in new tab

**Side Effects:**
- Set `intentpress_onboarding_complete` to `true`
- Set `intentpress_onboarding_completed_at` to current timestamp
- Dismiss any remaining admin notices
- Track onboarding completion (if analytics enabled)

**Acceptance Criteria:**
| # | Given | When | Then |
|---|-------|------|------|
| 1 | I complete onboarding | Step 6 displays | I see celebration message and summary |
| 2 | Completion screen shown | I view the summary | I see indexed count, status indicators |
| 3 | I click "View Dashboard" | Button clicked | I'm taken to IntentPress main dashboard |
| 4 | I click "Visit Site" | Button clicked | Site opens in new tab |
| 5 | Onboarding completes | Any future admin visit | Activation notice no longer appears |

---

## 4. UI Components & Copy

### 4.1 Component Library

All onboarding UI uses WordPress standard components via `@wordpress/components`:

| Component | WordPress Component | Usage |
|-----------|---------------------|-------|
| Buttons | `Button` | Primary/secondary actions |
| Text inputs | `TextControl` | API key input |
| Progress bar | `ProgressBar` (custom) | Indexing progress |
| Notices | `Notice` | Success/error messages |
| Cards | `Card`, `CardBody` | Content sections |
| Spinner | `Spinner` | Loading states |

### 4.2 Complete Copy Reference

**Step 1: Activation Notice**
```
Title: IntentPress is ready to upgrade your search!
Body: Enable AI-powered semantic search in just a few minutes.
CTA: Get Started →
```

**Step 2: API Key**
```
Title: Welcome to IntentPress! 👋
Subtitle: Let's set up semantic search for your site. This takes about 5 minutes 
          and will dramatically improve your search results.

Section Title: Step 1 of 3: Connect to AI Service
Section Body: IntentPress uses OpenAI's embedding technology to understand search 
              queries. You'll need an API key to continue.

Field Label: OpenAI API Key
Field Help: Don't have an API key? Get one at platform.openai.com/api-keys
Security Note: Your API key is stored securely and encrypted in your database.

Button: Validate & Continue
Button (loading): Validating...

Tip: OpenAI's API is pay-per-use. Semantic search typically costs less than 
     $1/month for sites with under 1,000 posts.
```

**Step 3: Start Indexing**
```
Section Title: Step 2 of 3: Index Your Content
Section Body: IntentPress needs to analyze your content to enable semantic search. 
              This is a one-time process that takes about 1 minute per 100 posts.

Content Label: Content to index:
Time Label: Estimated time: ~X minutes

Button: Start Indexing
Note: You can continue using WordPress while indexing runs in the background. 
      We'll let you know when it's done.

Free Tier Warning: Free tier includes 500 posts. You have X posts. The first 500 
                   posts (by date, newest first) will be indexed. Upgrade to 
                   IntentPress Pro to index all content.
```

**Step 4: Indexing Progress**
```
Title: Indexing Your Content...
Progress: X of Y items indexed
Time: Estimated time remaining: X minutes

Button: Cancel

Note: You can navigate away from this page. Indexing will continue in the 
      background and we'll notify you when complete.

Error Title: Some items couldn't be indexed
Error Body: X of Y items were indexed successfully. Z items failed due to API errors.
Error Actions: View Failed Items | Retry Failed | Continue Anyway →
```

**Step 5: Test Search**
```
Section Title: Step 3 of 3: Test Your Search
Section Body: Let's make sure everything is working! Try a search:

Placeholder: Try: "getting started"
Button: Search
Skip: Skip for Now →

Results Header: Search Results for "[query]":
Results Highlight: Notice how results match the intent, not just keywords!

Button: Complete Setup →
```

**Step 6: Completion**
```
Emoji: 🎉
Title: Semantic Search is Live!
Subtitle: Visitors to your site now get smarter search results.

Checklist:
✓ X posts and pages indexed
✓ AI-powered search active
✓ Automatic fallback enabled

Buttons: View Dashboard | Visit Site

What's Next Title: What's Next?
What's Next Items:
• View search analytics in your dashboard
• Re-index after adding new content (Dashboard → Re-index)
• Customize search settings anytime in Settings → IntentPress
```

### 4.3 Internationalization

All strings must use `__()` or `_x()` with text domain `intentpress`:

```php
__( 'Welcome to IntentPress!', 'intentpress' )
_x( 'Start Indexing', 'button label', 'intentpress' )
sprintf( __( '%d posts indexed', 'intentpress' ), $count )
```

---

## 5. State Management

### 5.1 Onboarding State

Stored in `wp_options`:

| Option Key | Type | Description |
|------------|------|-------------|
| `intentpress_onboarding_step` | int | Current step (1-6) |
| `intentpress_onboarding_complete` | bool | Whether onboarding finished |
| `intentpress_onboarding_completed_at` | datetime | When onboarding completed |
| `intentpress_onboarding_started_at` | datetime | When onboarding began |

### 5.2 State Transitions

```
┌─────────────────────────────────────────────────────────────┐
│                   ONBOARDING STATE MACHINE                   │
└─────────────────────────────────────────────────────────────┘

    not_started ──[activate]──► step_1_notice
         │
         │ (direct URL access)
         ▼
    step_2_api_key ◄──[click "Get Started"]── step_1_notice
         │
         │ [validate success]
         ▼
    step_3_pre_index
         │
         │ [click "Start Indexing"]
         ▼
    step_4_indexing
         │
         ├──[complete]──► step_5_test
         │
         └──[cancel]──► step_5_test (partial)
                              │
                              │ [click "Complete" or "Skip"]
                              ▼
                         completed
```

### 5.3 Resume Behavior

If user abandons onboarding mid-flow:

| Left At | Returns To | Behavior |
|---------|------------|----------|
| Step 1 (dismissed notice) | Settings page | See Step 2 (API key) |
| Step 2 (entered key, didn't validate) | Settings page | See Step 2 with key pre-filled |
| Step 3 (didn't start indexing) | Settings page | See Step 3 if key valid, else Step 2 |
| Step 4 (indexing in progress) | Settings page | See Step 4 with current progress |
| Step 4 (indexing complete, left before Step 5) | Settings page | See Step 5 |
| Step 5 (didn't complete) | Settings page | See Step 5 |

---

## 6. Edge Cases & Recovery

### 6.1 Edge Case Matrix

| Scenario | Handling |
|----------|----------|
| User has 0 posts | Show message: "No content to index. Create some posts first!" |
| User has only draft posts | Show message: "Only published posts are indexed. Publish some content first." |
| API key expires mid-indexing | Pause indexing, show error, prompt to update key |
| User's browser crashes during indexing | Indexing continues server-side; progress shown on return |
| User upgrades plan mid-onboarding | Update limits, show option to index additional content |
| User downgrades mid-use | Don't remove indexed content, but cap future indexing |
| Multiple admins doing onboarding | First to complete wins; others see completed state |
| Plugin deactivated during onboarding | Onboarding state preserved; resume on reactivation |
| Network timeout during validation | Show retry option with error message |

### 6.2 Recovery Actions

For each error state, provide clear recovery:

| Error | Recovery Message | Action |
|-------|------------------|--------|
| Invalid API key | "This API key is invalid. Please check it and try again." | Re-enter key |
| Network timeout | "Couldn't connect. Please check your connection and try again." | Retry button |
| Rate limit | "Rate limit reached. Waiting 60 seconds before retry..." | Auto-retry |
| Unknown error | "Something went wrong. Please try again or contact support." | Retry + Support link |

---

## 7. Success Metrics

### 7.1 Funnel Metrics

Track these at each step:

| Metric | Target | Measurement |
|--------|--------|-------------|
| Notice → Settings (Step 1→2) | >50% | Click tracking |
| API key validation (Step 2→3) | >80% | Validation success rate |
| Start indexing (Step 3→4) | >90% | Button click rate |
| Complete indexing (Step 4→5) | >95% | Completion rate |
| Test search (Step 5) | >60% | Search submitted rate |
| Full completion (Step 6) | >85% | Onboarding complete flag |

### 7.2 Time Metrics

| Metric | Target |
|--------|--------|
| Time to complete Step 2 (API key) | <3 minutes |
| Total onboarding time (excluding indexing) | <5 minutes |
| 7-day onboarding completion rate | >70% |

### 7.3 Quality Metrics

| Metric | Target |
|--------|--------|
| Support tickets during onboarding | <5% of activations |
| Onboarding abandonment rate | <30% |
| Re-start rate (abandoned then returned) | Track only |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial onboarding specification |
