/**
 * API hooks for IntentPress admin.
 *
 * @package
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import apiFetch from '@wordpress/api-fetch';

/**
 * Admin configuration.
 */
declare const intentpressAdmin: {
	apiUrl: string;
	nonce: string;
};

/**
 * Settings data type.
 */
export interface Settings {
	api_key_configured: boolean;
	api_key_masked: string;
	indexed_post_types: string[];
	per_page: number;
	similarity_threshold: number;
	fallback_enabled: boolean;
	cache_ttl: number;
	max_results: number;
}

/**
 * Health check data.
 */
export interface HealthCheck {
	status: 'ok' | 'warning' | 'error';
	checks: {
		[key: string]: {
			status: 'ok' | 'warning' | 'error';
			message: string;
		};
	};
}

/**
 * Index status data.
 */
export interface IndexStatus {
	indexed: number;
	total: number;
	needs_indexing: number;
	percentage: number;
	limit: number;
	limit_reached: boolean;
}

/**
 * Analytics data.
 */
export interface Analytics {
	period: string;
	summary: {
		total_searches: number;
		avg_execution_time: number;
		avg_results: number;
		fallback_rate: number;
	};
	top_queries: Array<{ query_text: string; count: number }>;
	daily: Array<{ date: string; searches: number }>;
}

/**
 * Onboarding status.
 */
export interface OnboardingStatus {
	complete: boolean;
	steps: {
		api_key_configured: boolean;
		posts_indexed: boolean;
	};
	indexed_count: number;
}

/**
 * Search result item.
 */
export interface SearchResult {
	id: number;
	title: string;
	excerpt: string;
	url: string;
	post_type: string;
	similarity?: number;
}

/**
 * Search response.
 */
export interface SearchResponse {
	success: boolean;
	data: {
		results: SearchResult[];
		total: number;
		page: number;
		per_page: number;
		query: string;
	};
	meta: {
		fallback_used: boolean;
		execution_time: number;
		search_type: string;
	};
	debug?: {
		embedding_model: string;
		indexed_count: number;
		usage_stats: {
			monthly_searches: number;
			monthly_search_limit: number;
			indexed_posts: number;
			index_limit: number;
		};
	};
}

/**
 * API response wrapper.
 */
interface ApiResponse<T> {
	success: boolean;
	data: T;
	message?: string;
	error?: string;
}

/**
 * Fetch settings.
 */
export const useSettings = () => {
	return useQuery<Settings>({
		queryKey: ['intentpress-settings'],
		queryFn: async () => {
			const response = await apiFetch<ApiResponse<Settings>>({
				path: '/intentpress/v1/settings',
			});
			return response.data;
		},
	});
};

/**
 * Update settings mutation.
 */
export const useUpdateSettings = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: async (
			settings: Partial<Settings> & { api_key?: string }
		) => {
			const response = await apiFetch<ApiResponse<null>>({
				path: '/intentpress/v1/settings',
				method: 'POST',
				data: settings,
			});
			return response;
		},
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ['intentpress-settings'],
			});
			queryClient.invalidateQueries({ queryKey: ['intentpress-health'] });
			queryClient.invalidateQueries({
				queryKey: ['intentpress-onboarding'],
			});
		},
	});
};

/**
 * Fetch health status.
 */
export const useHealthStatus = () => {
	return useQuery<HealthCheck>({
		queryKey: ['intentpress-health'],
		queryFn: async () => {
			const response = await apiFetch<ApiResponse<HealthCheck>>({
				path: '/intentpress/v1/health',
			});
			return response.data;
		},
		refetchInterval: 60000, // Refetch every minute.
	});
};

/**
 * Fetch index status.
 */
export const useIndexStatus = () => {
	return useQuery<IndexStatus>({
		queryKey: ['intentpress-index-status'],
		queryFn: async () => {
			const response = await apiFetch<ApiResponse<IndexStatus>>({
				path: '/intentpress/v1/index/status',
			});
			return response.data;
		},
	});
};

/**
 * Trigger indexing mutation.
 */
export const useTriggerIndexing = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: async (
			params: { post_ids?: number[]; batch_size?: number } = {}
		) => {
			const response = await apiFetch<{
				success: boolean;
				indexed: number;
				errors: Array<{ post_id: number; error: string }>;
				message: string;
			}>({
				path: '/intentpress/v1/index',
				method: 'POST',
				data: params,
			});
			return response;
		},
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ['intentpress-index-status'],
			});
			queryClient.invalidateQueries({ queryKey: ['intentpress-health'] });
			queryClient.invalidateQueries({
				queryKey: ['intentpress-onboarding'],
			});
		},
	});
};

/**
 * Clear index mutation.
 */
export const useClearIndex = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: async () => {
			const response = await apiFetch<ApiResponse<null>>({
				path: '/intentpress/v1/index/clear',
				method: 'DELETE',
			});
			return response;
		},
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ['intentpress-index-status'],
			});
			queryClient.invalidateQueries({ queryKey: ['intentpress-health'] });
		},
	});
};

/**
 * Fetch analytics.
 * @param period
 */
export const useAnalytics = (period: string = '7d') => {
	return useQuery<Analytics>({
		queryKey: ['intentpress-analytics', period],
		queryFn: async () => {
			const response = await apiFetch<ApiResponse<Analytics>>({
				path: `/intentpress/v1/analytics?period=${period}`,
			});
			return response.data;
		},
	});
};

/**
 * Validate API key mutation.
 */
export const useValidateApiKey = () => {
	return useMutation({
		mutationFn: async (apiKey: string) => {
			const response = await apiFetch<{
				success: boolean;
				valid: boolean;
				error?: string;
				message?: string;
			}>({
				path: '/intentpress/v1/validate-key',
				method: 'POST',
				data: { api_key: apiKey },
			});
			return response;
		},
	});
};

/**
 * Fetch onboarding status.
 */
export const useOnboardingStatus = () => {
	return useQuery<OnboardingStatus>({
		queryKey: ['intentpress-onboarding'],
		queryFn: async () => {
			const response = await apiFetch<ApiResponse<OnboardingStatus>>({
				path: '/intentpress/v1/onboarding',
			});
			return response.data;
		},
	});
};

/**
 * Update onboarding status mutation.
 */
export const useUpdateOnboarding = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: async (complete: boolean) => {
			const response = await apiFetch<ApiResponse<null>>({
				path: '/intentpress/v1/onboarding',
				method: 'POST',
				data: { complete },
			});
			return response;
		},
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ['intentpress-onboarding'],
			});
		},
	});
};

/**
 * Test search mutation.
 */
export const useTestSearch = () => {
	return useMutation({
		mutationFn: async (query: string) => {
			const response = await apiFetch<SearchResponse>({
				path: '/intentpress/v1/search/test',
				method: 'POST',
				data: { query },
			});
			return response;
		},
	});
};
