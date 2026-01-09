/**
 * API Hooks Tests
 *
 * @package IntentPress
 */

describe('useApi hooks', () => {
	describe('Settings interface', () => {
		it('should have correct shape', () => {
			const settings = {
				api_key_configured: true,
				api_key_masked: 'sk-...xxx',
				indexed_post_types: ['post', 'page'],
				per_page: 10,
				similarity_threshold: 0.5,
				fallback_enabled: true,
				cache_ttl: 3600,
				max_results: 100,
			};

			expect(settings.api_key_configured).toBe(true);
			expect(settings.indexed_post_types).toContain('post');
			expect(settings.per_page).toBeGreaterThan(0);
			expect(settings.similarity_threshold).toBeGreaterThanOrEqual(0);
			expect(settings.similarity_threshold).toBeLessThanOrEqual(1);
		});
	});

	describe('IndexStatus interface', () => {
		it('should calculate percentage correctly', () => {
			const status = {
				indexed: 50,
				total: 100,
				needs_indexing: 50,
				percentage: 50,
				limit: 500,
				limit_reached: false,
			};

			expect(status.percentage).toBe(
				(status.indexed / status.total) * 100
			);
			expect(status.needs_indexing).toBe(status.total - status.indexed);
		});

		it('should detect limit reached', () => {
			const statusAtLimit = {
				indexed: 500,
				total: 1000,
				needs_indexing: 500,
				percentage: 50,
				limit: 500,
				limit_reached: true,
			};

			expect(statusAtLimit.limit_reached).toBe(
				statusAtLimit.indexed >= statusAtLimit.limit
			);
		});
	});

	describe('SearchResult interface', () => {
		it('should have required fields', () => {
			const result = {
				id: 1,
				title: 'Test Post',
				excerpt: 'This is a test excerpt',
				url: 'https://example.com/test-post',
				post_type: 'post',
				similarity: 0.85,
			};

			expect(result.id).toBeDefined();
			expect(result.title).toBeDefined();
			expect(result.url).toBeDefined();
			expect(result.similarity).toBeGreaterThanOrEqual(0);
			expect(result.similarity).toBeLessThanOrEqual(1);
		});
	});
});
