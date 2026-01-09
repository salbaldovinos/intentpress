# Product Requirements Document: IntentPress MVP
## Overview & Foundation

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Status:** Draft  
**Target Release:** MVP v1.0.0

---

## 1. Executive Summary

### 1.1 Problem Statement

WordPress powers over 40% of the web, yet its default search functionality remains one of its weakest features. The built-in search uses basic keyword matching that frequently returns irrelevant results, frustrating users and increasing bounce rates—particularly on content-heavy sites like blogs, documentation portals, e-commerce stores, and knowledge bases.

Site owners lose engagement and conversions when visitors can't find what they're looking for. The current alternatives either require complex infrastructure (Elasticsearch), expensive subscriptions (Jetpack Search at $25+/month), or still rely on enhanced keyword matching without true semantic understanding (SearchWP, Relevanssi).

### 1.2 Solution Summary

**IntentPress** replaces WordPress's keyword-based search with intent-aware semantic search, delivering more relevant results with minimal configuration. By leveraging AI-powered vector embeddings, IntentPress understands what users mean—not just what they type—returning contextually relevant results even when exact keywords don't match.

### 1.3 Positioning Statement

> IntentPress replaces WordPress's keyword-based search with intent-aware semantic search, delivering more relevant results with minimal configuration.

---

## 2. Goals & Success Metrics

### 2.1 Primary Goal

Deliver a production-ready WordPress plugin that replaces default search with AI-powered semantic search, achieving measurably better search relevance while maintaining sub-500ms response times.

### 2.2 Secondary Goals

1. **Simplicity:** Enable non-technical site owners to set up semantic search in under 5 minutes
2. **Reliability:** Provide graceful fallback to keyword search when AI services are unavailable
3. **Transparency:** Give admins visibility into search performance and indexing status
4. **Extensibility:** Create a foundation for future features (analytics, recommendations, WooCommerce)

### 2.3 Success Metrics (KPIs)

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Search relevance improvement | 40%+ improvement vs default WP search | A/B testing with click-through rate |
| Search result CTR | >25% of searches result in a click | Analytics tracking |
| Setup completion rate | >80% of activations complete onboarding | Funnel tracking |
| API response time (P95) | <500ms | Server-side logging |
| Fallback rate | <5% of searches use fallback | Error tracking |
| 30-day retention | >60% of activated plugins remain active | WordPress.org stats |
| User satisfaction | >4.0 star rating | WordPress.org reviews |

### 2.4 Non-Goals for MVP

These are explicitly **out of scope** for the initial release:

- Voice search functionality
- Multilingual support beyond what embedding models provide natively
- WooCommerce product search integration
- Multisite network-wide search
- Custom training or fine-tuning of embedding models
- Real-time indexing (webhooks on post save)
- Search-as-you-type / autocomplete
- Faceted search / filtering

---

## 3. User Personas

### 3.1 Primary Persona: Sarah the Site Owner

**Demographics:**
- Age: 35-50
- Role: Small business owner, blogger, or content creator
- Technical skill: Low to moderate (can install plugins, uncomfortable with code)
- WordPress experience: 2-5 years

**Context:**
Sarah runs a recipe blog with 500+ posts. Visitors frequently complain they can't find recipes using the search. She's tried Relevanssi but found results still miss the mark when users search for concepts like "quick weeknight meals" instead of exact recipe titles.

**Pain Points:**
- Search returns irrelevant results, frustrating visitors
- Bounce rate on search results pages is 70%+
- Receives support emails asking for content that exists but wasn't found
- Previous search plugins required too much configuration

**Goals:**
- Improve search without touching code
- Reduce "I can't find X" support requests
- Keep visitors engaged longer on the site

**Success Criteria:**
- Setup takes less than 5 minutes
- Immediately sees better search results
- Dashboard shows search is working

**Quote:**
> "I just want search to work. When someone searches 'healthy dinner ideas,' I want them to find my healthy dinner recipes—not a random post that mentions 'healthy' once."

---

### 3.2 Secondary Persona: Dev the Developer

**Demographics:**
- Age: 25-40
- Role: Freelance developer or agency developer
- Technical skill: High (writes PHP, JavaScript, uses CLI tools)
- WordPress experience: 5+ years, builds custom themes/plugins

**Context:**
Dev builds and maintains WordPress sites for clients. They've implemented Elasticsearch for enterprise clients but need a simpler solution for smaller sites. They want something that "just works" but can be customized when needed.

**Pain Points:**
- Elasticsearch is overkill for most client sites
- Existing search plugins lack good APIs for customization
- Clients complain about search but won't pay for enterprise solutions
- Time spent configuring search plugins isn't billable

**Goals:**
- Recommend a search solution that works out of the box
- Have API access for custom integrations when needed
- Minimize ongoing maintenance

**Success Criteria:**
- Clean, well-documented REST API
- Hooks and filters for customization
- Can white-label for client sites

**Quote:**
> "I need something I can set up in 10 minutes and forget about, but with escape hatches for the 10% of sites that need custom behavior."

---

### 3.3 Tertiary Persona: Alex the Agency Manager

**Demographics:**
- Age: 30-45
- Role: Digital agency owner or project manager
- Technical skill: Moderate (understands concepts, doesn't write code daily)
- Manages: 20-100 client WordPress sites

**Context:**
Alex's agency manages WordPress sites for various clients. They need a standardized search solution they can deploy across sites without per-site configuration overhead. Cost predictability and centralized management are priorities.

**Pain Points:**
- Different search solutions across client sites
- No visibility into search performance across portfolio
- Support tickets about search eat into margins
- Enterprise solutions are cost-prohibitive per-site

**Goals:**
- Standardize on one search solution across all client sites
- Predictable per-site pricing they can pass to clients
- Reduce search-related support tickets

**Success Criteria:**
- Bulk configuration capabilities
- White-label options for client-facing UI
- Agency pricing tier with volume discounts

**Quote:**
> "If I could just add this to our standard WordPress stack and know search is handled, that's worth paying for."

---

## 4. Competitive Analysis

### 4.1 Direct Competitors

| Competitor | Approach | Pricing | Strengths | Weaknesses |
|------------|----------|---------|-----------|------------|
| **SearchWP** | Enhanced keyword matching with custom fields | $99-$399/year | Deep WP integration, WooCommerce support | No AI/semantic understanding |
| **Relevanssi** | Keyword + fuzzy matching + partial matches | Free / $109 premium | Free tier, extensive options | Complex configuration, still keyword-based |
| **ElasticPress** | Elasticsearch integration | Free (requires Elastic hosting) | Powerful, scalable | Complex infrastructure, enterprise-focused |
| **Jetpack Search** | Cloud-based Elasticsearch | $25+/month | Zero infrastructure, Automattic backing | Expensive for small sites, vendor lock-in |

### 4.2 IntentPress Differentiation

| Differentiator | Why It Matters |
|----------------|----------------|
| **True semantic understanding** | Returns relevant results even without keyword matches |
| **Simple setup** | API key only, not infrastructure management |
| **Affordable** | Freemium model accessible to small sites |
| **Modern architecture** | React admin, REST API, TypeScript |
| **Transparent pricing** | Clear limits, no surprise overages |

### 4.3 Competitive Positioning Map

```
                    HIGH RELEVANCE (AI/Semantic)
                           ▲
                           │
                           │    ★ IntentPress
                           │
        LOW COST ──────────┼────────── HIGH COST
                           │
                           │  SearchWP    Jetpack Search
         Relevanssi        │       ElasticPress
                           │
                           ▼
                    LOW RELEVANCE (Keyword-based)
```

---

## 5. Product Principles

These principles guide decision-making throughout development:

### 5.1 Simplicity Over Features

The default experience should require zero configuration beyond entering an API key. Advanced options exist but are hidden from the critical path. When in doubt, remove options rather than adding them.

### 5.2 Graceful Degradation

The plugin must never break a site. If the AI service is unavailable, fall back to WordPress default search silently. Users should never see a broken search—just a less intelligent one.

### 5.3 Transparency Over Magic

Users should understand what's happening: how many posts are indexed, when indexing last ran, why a search returned certain results. Avoid "it just works" black boxes that frustrate debugging.

### 5.4 Performance as a Feature

Search must feel instant. A 2-second search response time negates the relevance benefits. Target sub-500ms response times, even if it means simpler algorithms.

### 5.5 Privacy by Design

Minimize data sent to external services. Never send user identifiers. Provide clear documentation about what data flows where. Plan for a future local-only mode.

---

## 6. Scope Definition

### 6.1 MVP Scope (v1.0)

| Category | Included | Not Included |
|----------|----------|--------------|
| **Search** | Semantic search for posts/pages | WooCommerce products, custom post types (configurable) |
| **Indexing** | Manual trigger, progress UI | Real-time on post save, scheduled |
| **Admin UI** | Settings, indexing status, basic analytics | Advanced analytics, A/B testing |
| **API** | REST endpoints for search | GraphQL, webhooks |
| **Customization** | Filters for results, basic hooks | Visual customizer, templates |

### 6.2 Technical Constraints

- **WordPress:** 6.4+ (block editor era)
- **PHP:** 8.0+ (modern PHP features)
- **MySQL:** 5.7+ / MariaDB 10.3+
- **Browser:** Last 2 versions of Chrome, Firefox, Safari, Edge

### 6.3 Business Constraints

- **Free tier:** 500 posts indexed, 1,000 searches/month
- **API dependency:** Requires OpenAI API key (user provides)
- **No trialware:** Core functionality must work in free tier per WordPress.org guidelines

---

## 7. Dependencies & Assumptions

### 7.1 External Dependencies

| Dependency | Type | Risk Level | Mitigation |
|------------|------|------------|------------|
| OpenAI Embeddings API | Critical | Medium | Implement fallback search, consider multi-provider support |
| Supabase pgvector | Optional (Pro) | Low | WordPress database fallback for free tier |
| WordPress REST API | Critical | Very Low | Core WordPress feature, stable |
| @wordpress/scripts | Development | Low | Well-maintained by WordPress core team |

### 7.2 Assumptions

1. **Users have OpenAI API access:** They can create an account and generate an API key
2. **Sites have < 10,000 posts:** MVP performance optimized for typical blog/small business scale
3. **Search volume is moderate:** < 10,000 searches/day for free tier sites
4. **English content primarily:** Embedding model performance may vary for other languages
5. **Standard WordPress installation:** No significant core modifications or unusual hosting restrictions

---

## 8. Release Phases (Post-MVP Roadmap Preview)

### Phase 1: MVP (This Document)
Core semantic search, admin settings, basic analytics, fallback search

### Phase 2: Enhanced Analytics (v1.1)
- Search term analytics dashboard
- "No results" tracking and suggestions
- Click-through rate tracking
- Export capabilities

### Phase 3: Advanced Features (v1.2)
- Real-time indexing on post save
- WooCommerce product search
- Custom post type configuration
- Search-as-you-type preview

### Phase 4: Enterprise Features (v2.0)
- Multisite network support
- White-label options
- Advanced caching
- Multiple AI provider support

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| **Semantic search** | Search that understands the meaning/intent behind queries, not just keywords |
| **Vector embedding** | Numerical representation of text that captures semantic meaning |
| **Cosine similarity** | Mathematical measure of similarity between two vectors (embeddings) |
| **Fallback search** | Using WordPress default search when AI search is unavailable |
| **Indexing** | Process of generating and storing embeddings for all site content |
| **pgvector** | PostgreSQL extension for efficient vector similarity search |

---

## Appendix B: Document References

| Document | Purpose | Location |
|----------|---------|----------|
| PRD-User-Stories.md | Detailed user stories with acceptance criteria | docs/ |
| PRD-Onboarding.md | Onboarding flow specification | docs/ |
| PRD-Error-Handling.md | Error states and edge cases | docs/ |
| TRD.md | Technical requirements and architecture | docs/ |
| CLAUDE.md | AI coding assistant context | root |
| ROADMAP.md | Development task tracking | root |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | [Author] | Initial MVP PRD |
