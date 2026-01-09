# Project Planning Document
## AI-Powered Semantic Search Plugin for WordPress

**Document Version:** 1.0  
**Created:** January 2026  
**Purpose:** Foundation for PRD, TRD, CLAUDE.md, and ROADMAP.md

---

## Table of Contents

1. [Project Vision & Scope](#1-project-vision--scope)
2. [Key Decisions Required](#2-key-decisions-required) ✅
3. [PRD Outline & Guidance](#3-prd-outline--guidance)
4. [TRD Outline & Guidance](#4-trd-outline--guidance)
5. [CLAUDE.md Template](#5-claudemd-template)
6. [ROADMAP.md Structure](#6-roadmapmd-structure)
7. [Development Workflow](#7-development-workflow)
8. [Naming Conventions & Standards](#8-naming-conventions--standards)
9. [Risk Assessment & Mitigations](#9-risk-assessment--mitigations)
10. [Pre-Development Checklist](#10-pre-development-checklist)
11. [Additional Recommended Documents](#11-additional-recommended-documents)
12. [Gaps to Address Before PRD](#12-gaps-to-address-before-prd) ← NEW

---

## 1. Project Vision & Scope

### 1.1 Problem Statement

WordPress's default search functionality uses basic keyword matching that returns irrelevant results, frustrating users and increasing bounce rates on content-heavy sites (blogs, e-commerce, knowledge bases).

### 1.2 Solution

An AI-powered semantic search plugin that understands query intent and returns contextually relevant results using vector embeddings and similarity search.

### 1.3 Target Users

| User Type | Pain Point | Value Proposition |
|-----------|------------|-------------------|
| Site Owners | Poor search UX, high bounce rates | Better engagement, reduced support queries |
| Developers | Complex search customization | Simple API, extensible architecture |
| Agencies | Managing search across client sites | Centralized configuration, white-label ready |

### 1.4 Scope Boundaries

**In Scope (MVP):**
- Replace default WordPress search with semantic search
- React-powered admin settings dashboard
- Integration with embedding API (OpenAI/Anthropic)
- Basic search analytics
- Fallback to keyword search on API failure

**Out of Scope (Future):**
- Voice search
- Multilingual support (beyond what embedding models provide)
- WooCommerce product search integration
- Multisite network-wide search
- Custom training of embedding models

### 1.5 Success Metrics

Define these before development begins:

- [ ] Search relevance improvement (A/B test vs default search)
- [ ] Average search result click-through rate
- [ ] Admin configuration completion rate
- [ ] API response time (target: <500ms)
- [ ] Plugin activation retention (30-day)

---

## 2. Key Decisions Required ✅ FINALIZED

### 2.1 Product Decisions

#### Plugin Name Rationale
**IntentPress** was selected because it:
- Communicates intent-driven search clearly and immediately
- Feels WordPress-native and professional
- Has strong SaaS and agency appeal
- Is broad enough to support future features (analytics, recommendations, content intelligence)

| Decision | Final Choice |
|----------|--------------|
| **Plugin Name** | IntentPress |
| **Plugin Slug** | `intentpress` |
| **Text Domain** | `intentpress` |
| **Function Prefix** | `intentpress_` |
| **REST Namespace** | `intentpress/v1` |
| **Monetization Model** | Freemium + SaaS API subscription |
| **Free Tier Limits** | 1,000 searches/month, 500 posts indexed |

### 2.2 Technical Decisions

| Decision | Final Choice | Rationale |
|----------|--------------|-----------|
| **Embedding Provider** | OpenAI | Mature, cost-effective, well-documented |
| **Embedding Model** | text-embedding-3-small | Lower cost, fast, sufficient quality |
| **Vector Storage** | Supabase pgvector | Free tier, scalable, self-hostable option |
| **Build Tool** | @wordpress/scripts | Official WP tooling, zero-config |
| **State Management** | useState + React Query | Simple until complexity demands more |
| **TypeScript** | Yes | Early error detection, better DX |
| **CSS Strategy** | @wordpress/components + scoped CSS | Consistent WP admin UI |

### 2.3 Distribution Decisions

| Decision | Final Choice | Rationale |
|----------|--------------|-----------|
| **Distribution Channel** | WordPress.org + own site | Max reach, WP.org for free, own site for pro |
| **License** | GPL-2.0-or-later | WordPress standard |
| **Pro Feature Delivery** | Separate add-on plugin | WP.org compliant |
| **Billing Platform** | Stripe | Industry standard, good DX |
| **Account Model** | API key only | Simplest UX for users |

### Positioning Statement (Canonical)
> **IntentPress replaces WordPress's keyword-based search with intent-aware semantic search, delivering more relevant results with minimal configuration.**

---

## 3. PRD Outline & Guidance

### 3.1 Recommended PRD Structure

```markdown
# Product Requirements Document: [Plugin Name]

## 1. Overview
- Problem statement (2-3 sentences)
- Solution summary (2-3 sentences)
- Target release date

## 2. Goals & Success Metrics
- Primary goal
- Secondary goals
- Measurable KPIs with targets

## 3. User Personas
- Persona 1: Site Owner (non-technical)
- Persona 2: Developer (technical)
- Persona 3: Agency Manager

## 4. User Stories & Acceptance Criteria
Format: As a [persona], I want [action] so that [benefit].

### 4.1 Core Search (MVP)
- US-001: As a site visitor, I want to search and get relevant results...
- US-002: As a site owner, I want search to understand synonyms...

### 4.2 Admin Configuration
- US-010: As an admin, I want to enter my API key...
- US-011: As an admin, I want to see indexing progress...

### 4.3 Analytics
- US-020: As an admin, I want to see popular search terms...

## 5. Feature Specifications
Detailed specs for each feature with:
- Description
- User flow
- UI mockups/wireframes (if available)
- Edge cases
- Error states

## 6. Non-Functional Requirements
- Performance (response time targets)
- Security requirements
- Accessibility (WCAG 2.1 AA)
- Browser support
- WordPress version support

## 7. Out of Scope
Explicit list of what's NOT included

## 8. Dependencies & Assumptions
- External API availability
- User has API key
- Site has < X posts (for free tier)

## 9. Risks & Mitigations
- Risk: API rate limiting → Mitigation: Caching, fallback search

## 10. Release Phases
- Phase 1 (MVP): Core features
- Phase 2: Analytics
- Phase 3: Advanced features
```

### 3.2 PRD Writing Tips for AI-Assisted Development

1. **Be explicit about edge cases** — Claude Code handles happy paths well but needs guidance on errors
2. **Include example data** — Show sample API responses, search queries, expected results
3. **Define "done"** — Each user story needs testable acceptance criteria
4. **Specify error messages** — Don't leave copy to interpretation

---

## 4. TRD Outline & Guidance

### 4.1 Recommended TRD Structure

```markdown
# Technical Requirements Document: [Plugin Name]

## 1. System Overview
- Architecture diagram (ASCII or link to image)
- Component summary
- Data flow description

## 2. Technology Stack
| Layer | Technology | Version | Rationale |
|-------|------------|---------|-----------|
| Backend | PHP | 8.0+ | WP minimum |
| Frontend | React | 18.x | Via @wordpress/element |
| Build | @wordpress/scripts | 28.x | Official tooling |
| ... | ... | ... | ... |

## 3. Architecture

### 3.1 Directory Structure
```
intentpress/
├── intentpress.php           # Main plugin file
├── includes/
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-embedding-service.php
│   ├── class-search-handler.php
│   └── rest-api/
│       └── class-search-controller.php
├── admin/
│   ├── class-admin.php
│   └── js/
│       └── src/
│           ├── index.tsx
│           └── components/
├── public/
│   └── class-public.php
├── tests/
│   ├── php/
│   └── js/
└── languages/
```

### 3.2 Class Diagram
[Describe main classes and relationships]

### 3.3 Database Schema
```sql
CREATE TABLE {prefix}intentpress_embeddings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    embedding LONGTEXT NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY post_id (post_id),
    FOREIGN KEY (post_id) REFERENCES {prefix}_posts(ID) ON DELETE CASCADE
);
```

## 4. API Specifications

### 4.1 REST Endpoints
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/wp-json/intentpress/v1/search` | POST | Public | Perform search |
| `/wp-json/intentpress/v1/index` | POST | Admin | Trigger indexing |
| `/wp-json/intentpress/v1/settings` | GET/POST | Admin | Manage settings |

### 4.2 External API Integration
- Provider: OpenAI
- Endpoint: `https://api.openai.com/v1/embeddings`
- Model: text-embedding-3-small
- Rate limits: [document limits]
- Error handling strategy

## 5. Security Requirements

### 5.1 Input Sanitization
| Input | Sanitization Function |
|-------|----------------------|
| Search query | sanitize_text_field() |
| API key | sanitize_text_field() + encryption |
| Post IDs | absint() |

### 5.2 Output Escaping
| Output Context | Escaping Function |
|----------------|-------------------|
| HTML content | esc_html() |
| HTML attributes | esc_attr() |
| URLs | esc_url() |
| JavaScript | wp_json_encode() |

### 5.3 Capability Checks
| Action | Required Capability |
|--------|---------------------|
| View settings | manage_options |
| Trigger indexing | manage_options |
| Perform search | read |

## 6. Performance Requirements

### 6.1 Targets
- Search response: < 500ms (P95)
- Admin page load: < 2s
- Indexing: 100 posts/minute

### 6.2 Caching Strategy
- Search results: Transients (1 hour TTL)
- Embeddings: Database (permanent until content changes)
- API responses: Object cache if available

## 7. Testing Requirements

### 7.1 Unit Tests
- PHP: PHPUnit with wp-phpunit
- JS: Jest via @wordpress/scripts
- Coverage target: 80%

### 7.2 Integration Tests
- REST API endpoint tests
- Database migration tests
- External API mock tests

### 7.3 E2E Tests
- Playwright
- Critical user flows

## 8. DevOps & CI/CD

### 8.1 GitHub Actions Workflow
- Lint (PHP + JS)
- Unit tests (matrix: PHP 8.0-8.3, WP 6.4-latest)
- E2E tests
- Build artifacts

### 8.2 Release Process
- Semantic versioning
- Changelog generation
- WordPress.org SVN deployment

## 9. Compatibility Matrix

| Dependency | Minimum | Maximum Tested |
|------------|---------|----------------|
| WordPress | 6.4 | 6.7 |
| PHP | 8.0 | 8.3 |
| MySQL | 5.7 | 8.0 |
| MariaDB | 10.3 | 11.x |
```

---

## 5. CLAUDE.md Template

Keep this file **under 60 lines** for optimal Claude Code performance.

```markdown
# IntentPress

## Project Overview
WordPress plugin replacing default search with AI-powered semantic search.
Tech: PHP 8.0+, WordPress 6.4+, React admin UI (TypeScript), OpenAI embeddings, Supabase pgvector.

## Quick Reference
- Plugin slug: `intentpress`
- Text domain: `intentpress`
- Function prefix: `intentpress_`
- REST namespace: `intentpress/v1`

## Commands
```bash
npm run build        # Build React/TS assets
npm run start        # Watch mode
npm run lint:js      # ESLint
npm run test:unit    # Jest tests
composer phpcs       # PHP CodeSniffer
composer phpcbf      # Auto-fix PHP
composer test        # PHPUnit
wp-env start         # Start local WordPress
```

## Code Standards (MANDATORY)

### PHP Security
- Sanitize ALL input: sanitize_text_field(), absint(), esc_url_raw()
- Escape ALL output: esc_html(), esc_attr(), wp_kses_post()
- Verify nonces: wp_verify_nonce(), check_ajax_referer()
- Check capabilities: current_user_can('manage_options')
- Prepare queries: $wpdb->prepare()

### TypeScript/React
- Use @wordpress/api-fetch for REST calls (handles nonces)
- Import from @wordpress/* packages (externalized by build)
- All strings must use __() or _x() from @wordpress/i18n
- Strict TypeScript: no `any` types without justification

## File Locations
- Main plugin: `intentpress.php`
- PHP classes: `includes/`
- React/TS source: `admin/js/src/`
- REST controllers: `includes/rest-api/`
- Tests: `tests/php/` and `tests/js/`

## Context Documents
- `docs/PRD.md` - Product requirements
- `docs/TRD.md` - Technical specifications
- `ROADMAP.md` - Task checklist with status
- `CHANGELOG.md` - Version history

## Task Workflow
1. Read task from ROADMAP.md
2. Implement with tests
3. Run linters: `npm run lint:js && composer phpcs`
4. Run tests: `npm run test:unit && composer test`
5. Update ROADMAP.md with completion status
6. Commit with conventional commit message

## Conventional Commits
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Tests
- `refactor:` Code restructuring
- `chore:` Maintenance
```

---

## 6. ROADMAP.md Structure

Organize tasks for Claude Code's **task-by-task execution pattern**.

```markdown
# IntentPress Development Roadmap

## Status Legend
- [ ] Not started
- [~] In progress
- [x] Complete
- [!] Blocked

## Phase 0: Project Setup
**Target: Week 1**

- [ ] 0.1 Initialize plugin with @wordpress/create-block
  - Acceptance: Plugin activates without errors in wp-env
  - Notes: Use `npx @wordpress/create-block intentpress --variant dynamic`

- [ ] 0.2 Configure TypeScript
  - Acceptance: `npm run build` compiles .tsx files without errors
  - Files: tsconfig.json, admin/js/src/index.tsx

- [ ] 0.3 Configure linting (ESLint + PHPCS)
  - Acceptance: `npm run lint:js` and `composer phpcs` run without config errors
  - Files: .eslintrc.json, phpcs.xml.dist, composer.json

- [ ] 0.4 Configure testing (Jest + PHPUnit)
  - Acceptance: `npm run test:unit` and `composer test` execute (even with 0 tests)
  - Files: jest.config.js, phpunit.xml.dist, tests/bootstrap.php

- [ ] 0.5 Set up wp-env for local development
  - Acceptance: `wp-env start` launches WordPress with plugin active
  - Files: .wp-env.json

- [ ] 0.6 Configure GitHub Actions CI
  - Acceptance: Push triggers lint + test workflow
  - Files: .github/workflows/ci.yml

## Phase 1: Core Infrastructure
**Target: Week 2**

- [ ] 1.1 Create database migration for embeddings table
  - Acceptance: Table created on activation, removed on uninstall
  - Files: includes/class-activator.php, uninstall.php
  - Tests: tests/php/test-activator.php

- [ ] 1.2 Build Settings API with React UI
  - Acceptance: Settings page renders, saves API key (encrypted) to options
  - Files: includes/class-admin.php, admin/js/src/components/SettingsPage.tsx
  - Tests: tests/js/SettingsPage.test.tsx

- [ ] 1.3 Implement IntentPress_Embedding_Service class
  - Acceptance: Generates embeddings via OpenAI API, handles errors gracefully
  - Files: includes/class-embedding-service.php
  - Tests: tests/php/test-embedding-service.php

- [ ] 1.4 Build content indexer with background processing
  - Acceptance: Indexes all published posts, shows progress in admin
  - Files: includes/class-indexer.php
  - Tests: tests/php/test-indexer.php

## Phase 2: Search Functionality
**Target: Week 3**

- [ ] 2.1 Create REST endpoint for semantic search
  - Acceptance: POST /wp-json/intentpress/v1/search returns ranked results
  - Files: includes/rest-api/class-search-controller.php
  - Tests: tests/php/test-search-controller.php

- [ ] 2.2 Implement vector similarity search
  - Acceptance: Cosine similarity ranking returns relevant results
  - Files: includes/class-search-handler.php
  - Tests: tests/php/test-search-handler.php

- [ ] 2.3 Hook into WordPress default search
  - Acceptance: Frontend search form uses semantic search transparently
  - Files: includes/class-public.php
  - Tests: tests/php/test-public.php

- [ ] 2.4 Implement fallback to keyword search
  - Acceptance: If API fails, gracefully falls back to WP default search
  - Files: includes/class-search-handler.php
  - Tests: tests/php/test-search-fallback.php

## Phase 3: Admin Dashboard
**Target: Week 4**

- [ ] 3.1 Build indexing status component
  - Acceptance: Shows indexed/total posts, last index time, re-index button
  - Files: admin/js/src/components/IndexingStatus.tsx
  - Tests: tests/js/IndexingStatus.test.tsx

- [ ] 3.2 Build search analytics component
  - Acceptance: Shows top search terms, searches with no results
  - Files: admin/js/src/components/Analytics.tsx
  - Tests: tests/js/Analytics.test.tsx

- [ ] 3.3 Build search preview/testing component
  - Acceptance: Admin can test search queries and see results with scores
  - Files: admin/js/src/components/SearchPreview.tsx
  - Tests: tests/js/SearchPreview.test.tsx

## Phase 4: Polish & Release Prep
**Target: Week 4-5**

- [ ] 4.1 Accessibility audit (WCAG 2.1 AA)
  - Acceptance: No critical a11y issues in admin UI
  - Tools: axe-core, manual keyboard testing

- [ ] 4.2 Performance optimization
  - Acceptance: Admin bundle < 100KB gzipped, search < 500ms P95
  - Tools: webpack-bundle-analyzer, Query Monitor

- [ ] 4.3 Security audit
  - Acceptance: Pass Plugin Check plugin, no PHPCS security warnings
  - Tools: Plugin Check, PHPCS with security rules

- [ ] 4.4 Documentation
  - Acceptance: README.md, inline PHPDoc, user guide
  - Files: README.md, docs/user-guide.md

- [ ] 4.5 WordPress.org submission prep
  - Acceptance: All guidelines met, screenshots, banner, icon ready
  - Files: assets/, readme.txt

---

## Session Notes
<!-- Claude Code updates this section at end of each session -->

### [Date] - Session 1
- Completed: 
- In Progress: 
- Blockers: 
- Next: 
```

---

## 7. Development Workflow

### 7.1 Session Start Protocol (for Claude Code)

```
1. Read CLAUDE.md (automatic)
2. Read ROADMAP.md to identify current task
3. Read relevant PRD/TRD sections for context
4. Confirm task understanding with user
5. Implement task
6. Run linters and tests
7. Update ROADMAP.md
8. Commit with conventional commit message
```

### 7.2 Context Management Strategy

| Context Size | Action |
|--------------|--------|
| < 40% | Continue working |
| 40-60% | Wrap up current task, prepare handoff |
| > 60% | Use `/clear`, summarize state to ROADMAP.md |

### 7.3 Git Workflow

```
main (protected)
  └── develop
        ├── feature/1.1-database-migration
        ├── feature/1.2-settings-api
        └── feature/2.1-search-endpoint
```

**Branch naming:** `feature/[task-id]-[short-description]`

**Commit messages:** Follow Conventional Commits
```
feat(search): implement vector similarity search

- Add cosine similarity function
- Integrate with embedding service
- Add unit tests

Closes #12
```

---

## 8. Naming Conventions & Standards

### 8.1 PHP

| Element | Convention | IntentPress Example |
|---------|------------|---------------------|
| Classes | `Prefix_Class_Name` | `IntentPress_Embedding_Service` |
| Functions | `prefix_function_name` | `intentpress_get_results()` |
| Hooks (actions) | `prefix_hook_name` | `intentpress_before_index` |
| Hooks (filters) | `prefix_filter_name` | `intentpress_search_results` |
| Options | `prefix_option_name` | `intentpress_api_key` |
| Transients | `prefix_transient_name` | `intentpress_cache_abc123` |
| Database tables | `{prefix}intentpress_*` | `wp_intentpress_embeddings` |

### 8.2 JavaScript/TypeScript

| Element | Convention | IntentPress Example |
|---------|------------|---------------------|
| Components | PascalCase | `SettingsPage`, `SearchPreview` |
| Functions | camelCase | `handleSearch`, `fetchResults` |
| Constants | SCREAMING_SNAKE | `API_NAMESPACE`, `DEFAULT_LIMIT` |
| Files | kebab-case | `settings-page.tsx`, `use-search.ts` |
| Types/Interfaces | PascalCase + suffix | `SearchResultType`, `SettingsState` |

### 8.3 REST API

| Element | Convention | IntentPress Example |
|---------|------------|---------------------|
| Namespace | `plugin-slug/v1` | `intentpress/v1` |
| Endpoints | lowercase, hyphens | `/search`, `/index-status` |
| Parameters | snake_case | `post_type`, `per_page` |

---

## 9. Risk Assessment & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| OpenAI API rate limits | Medium | High | Implement caching, exponential backoff, fallback search |
| API costs exceed projections | Medium | Medium | Set usage limits, add cost estimation to admin UI |
| WordPress.org rejection | Low | High | Use Plugin Check early, follow guidelines strictly |
| React conflicts with other plugins | Low | Medium | Use @wordpress/scripts externals, scope CSS |
| Security vulnerability | Low | Critical | PHPCS security rules, manual audit, no user input in queries |
| Performance issues with large sites | Medium | Medium | Background processing, pagination, caching |
| Embedding model changes | Low | Medium | Abstract provider, store model version with embeddings |

---

## 10. Pre-Development Checklist

Complete these before starting Phase 0:

### 10.1 Accounts & Access
- [ ] OpenAI API account with billing enabled (set $10 spending limit)
- [ ] Supabase account created (free tier)
- [ ] GitHub repository created (`intentpress` or `intentpress-wp`)
- [ ] WordPress.org account (for eventual submission)
- [ ] Stripe account (for pro billing — can defer until Phase 4)

### 10.2 Development Environment
- [ ] Node.js 18+ installed
- [ ] PHP 8.0+ installed
- [ ] Composer installed
- [ ] Docker installed (for wp-env)
- [ ] VS Code / IDE configured with PHP and ESLint extensions

### 10.3 Decisions Finalized ✅
- [x] Plugin name decided: **IntentPress**
- [x] Text domain / function prefix decided: `intentpress` / `intentpress_`
- [x] Embedding provider selected: **OpenAI text-embedding-3-small**
- [x] Vector storage solution selected: **Supabase pgvector**
- [x] Monetization model defined: **Freemium + SaaS API**

### 10.4 Documents Prepared
- [x] PROJECT_PLANNING.md created
- [ ] PRD written (at least MVP scope)
- [ ] TRD written (at least architecture section)
- [ ] CLAUDE.md created
- [ ] ROADMAP.md created with Phase 0 tasks

---

## 11. Additional Recommended Documents

Beyond the core four (PRD, TRD, CLAUDE.md, ROADMAP.md), consider:

| Document | Purpose | When to Create |
|----------|---------|----------------|
| `CONTRIBUTING.md` | Guide for contributors | Before public release |
| `SECURITY.md` | Vulnerability reporting process | Before public release |
| `CHANGELOG.md` | Version history | Start at v0.1.0 |
| `docs/ARCHITECTURE.md` | Detailed architecture decisions | During Phase 1 |
| `docs/API.md` | REST API documentation | After Phase 2 |
| `docs/HOOKS.md` | Filter/action reference for developers | After Phase 2 |
| `.github/ISSUE_TEMPLATE/` | Bug/feature request templates | Before public release |
| `.github/PULL_REQUEST_TEMPLATE.md` | PR checklist | Before accepting contributions |

---

## 12. Gaps to Address Before PRD

These items were identified as missing and should be resolved during PRD writing:

### 12.1 Competitor Analysis

| Competitor | Approach | Pricing | Gap IntentPress Fills |
|------------|----------|---------|----------------------|
| **SearchWP** | Enhanced keyword matching | $99-$399/year | No AI/semantic understanding |
| **Relevanssi** | Keyword + fuzzy matching | Free / $109 premium | Basic relevance, no intent |
| **ElasticPress** | Elasticsearch integration | Free (requires Elastic) | Complex setup, enterprise focus |
| **Jetpack Search** | Cloud-based Elasticsearch | $25+/month | Expensive, Automattic lock-in |

**IntentPress Differentiation:**
- True semantic understanding (not just keyword enhancement)
- Simple setup (API key, not infrastructure)
- Affordable for small sites (freemium model)
- Privacy-focused option coming (local embeddings)

### 12.2 API Key Security Architecture

**Decision needed:** How to store the OpenAI API key securely.

| Option | Security | Complexity | Recommendation |
|--------|----------|------------|----------------|
| Plain text in wp_options | ❌ Low | Simple | Not acceptable |
| Encrypted with wp_salt | ✅ Good | Medium | **Recommended for MVP** |
| Environment variable | ✅ Best | Complex | Document as advanced option |

**Implementation:** Use `openssl_encrypt()` with `AUTH_KEY` salt from wp-config.php.

### 12.3 GDPR / Privacy Considerations

Search queries are sent to OpenAI for embedding generation. This has privacy implications:

- [ ] Add privacy policy section explaining data flow
- [ ] Consider opt-in consent for non-essential data processing
- [ ] Document in readme.txt per WP.org guidelines
- [ ] Offer "anonymize queries" setting (strip PII before sending)

### 12.4 Error Message Copy

Define actual error messages (not just "error occurred"):

| Scenario | User-Facing Message |
|----------|---------------------|
| API key missing | "Please enter your IntentPress API key in Settings → IntentPress to enable semantic search." |
| API key invalid | "Your API key appears to be invalid. Please check it in Settings → IntentPress." |
| Rate limit hit | "Search is temporarily limited. Trying again in a moment..." |
| API timeout | "Search is taking longer than expected. Showing standard results instead." |
| No results (semantic) | "No results found for '[query]'. Try different keywords or browse categories." |

### 12.5 Onboarding Flow

What happens immediately after plugin activation?

1. **Activation** → Show admin notice with "Get Started" link
2. **Settings page** → API key input with validation
3. **API key saved** → Prompt to start indexing
4. **Indexing started** → Show progress bar, estimated time
5. **Indexing complete** → Show "Test your search" prompt
6. **First search** → Celebrate with "🎉 Semantic search is live!"

### 12.6 Free Tier Enforcement

How are limits enforced?

| Limit | Enforcement Point | User Experience |
|-------|-------------------|-----------------|
| 500 posts indexed | Before indexing | "Upgrade to index more than 500 posts" |
| 1,000 searches/month | Before API call | Fallback to WP search + notice |

**Counter storage:** `intentpress_monthly_searches` option, reset on 1st of month.

---

1. ~~**Fill in Section 2** — Make all key decisions~~ ✅ Complete
2. **Write PRD** — Use Section 3 outline, focus on MVP user stories
3. **Write TRD** — Use Section 4 outline, finalize architecture
4. **Create CLAUDE.md** — Copy template from Section 5
5. **Create ROADMAP.md** — Copy template from Section 6
6. **Complete Pre-Dev Checklist** — Section 10
7. **Start Phase 0** — Project setup with Claude Code

---

## Questions to Discuss ✅ ANSWERED

| Question | Answer | Implications |
|----------|--------|--------------|
| **Target launch date?** | ~1 month (vibe coding, no hard deadline) | Can prioritize quality over speed; iterate on MVP |
| **WordPress.org submission?** | Yes (free version with limits + pro add-on) | Must follow Plugin Guidelines strictly; no pro features in main plugin |
| **Existing beta testers?** | No | Need to build feedback loop; consider soft launch to WP communities |
| **API cost budget?** | Unknown | **Action needed:** Estimate dev costs below |
| **Solo or team?** | Mostly solo, may expand | Single-branch workflow initially; document for future contributors |

### API Cost Estimation for Development

Since API budget is unknown, here's a realistic estimate:

| Phase | Activity | Estimated API Calls | Cost (text-embedding-3-small) |
|-------|----------|---------------------|-------------------------------|
| Development | Testing with 100 sample posts | ~500 embeddings | ~$0.01 |
| Integration testing | Re-indexing during debugging | ~2,000 embeddings | ~$0.04 |
| Manual QA | Search testing | ~500 searches | ~$0.01 |
| **Total Development** | | | **~$0.10** |

**Note:** text-embedding-3-small costs $0.00002 per 1K tokens. A typical blog post (~500 words) ≈ 700 tokens. Development costs are negligible. Production costs scale with user search volume.

**Recommendation:** Set up OpenAI API with a $10 spending limit during development. This provides ample buffer and prevents surprise charges.
