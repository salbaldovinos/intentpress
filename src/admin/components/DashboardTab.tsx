/**
 * Dashboard Tab Component
 *
 * @package
 */

import { useState } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	Spinner,
	TextControl,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useHealthStatus,
	useIndexStatus,
	useAnalytics,
	useTestSearch,
	SearchResponse,
} from '../hooks/useApi';

/**
 * Status badge component.
 * @param root0
 * @param root0.status
 */
const StatusBadge: React.FC<{ status: 'ok' | 'warning' | 'error' }> = ({
	status,
}) => {
	const statusClasses: Record<string, string> = {
		ok: 'intentpress-status--ok',
		warning: 'intentpress-status--warning',
		error: 'intentpress-status--error',
	};

	const statusLabels: Record<string, string> = {
		ok: __('OK', 'intentpress'),
		warning: __('Warning', 'intentpress'),
		error: __('Error', 'intentpress'),
	};

	return (
		<span className={`intentpress-status ${statusClasses[status]}`}>
			{statusLabels[status]}
		</span>
	);
};

/**
 * Dashboard Tab component.
 */
const DashboardTab: React.FC = () => {
	const { data: health, isLoading: healthLoading } = useHealthStatus();
	const { data: indexStatus, isLoading: indexLoading } = useIndexStatus();
	const { data: analytics, isLoading: analyticsLoading } = useAnalytics('7d');

	const [testQuery, setTestQuery] = useState<string>('');
	const [testResults, setTestResults] = useState<SearchResponse | null>(null);
	const testSearch = useTestSearch();

	/**
	 * Handle test search.
	 */
	const handleTestSearch = async () => {
		if (!testQuery.trim()) {
			return;
		}

		try {
			const results = await testSearch.mutateAsync(testQuery);
			setTestResults(results);
		} catch (error) {
			// Error is handled by mutation state.
		}
	};

	return (
		<div className="intentpress-dashboard">
			{/* Health Status Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('System Health', 'intentpress')}</h2>
					{health && <StatusBadge status={health.status} />}
				</CardHeader>
				<CardBody>
					{healthLoading && <Spinner />}
					{!healthLoading && health && (
						<div className="intentpress-health-checks">
							{Object.entries(health.checks).map(
								([key, check]) => (
									<div
										key={key}
										className="intentpress-health-check"
									>
										<StatusBadge status={check.status} />
										<span className="intentpress-health-message">
											{check.message}
										</span>
									</div>
								)
							)}
						</div>
					)}
					{!healthLoading && !health && (
						<Notice status="error" isDismissible={false}>
							{__('Unable to load health status.', 'intentpress')}
						</Notice>
					)}
				</CardBody>
			</Card>

			{/* Index Status Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Index Status', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					{indexLoading && <Spinner />}
					{!indexLoading && indexStatus && (
						<div className="intentpress-index-stats">
							<div className="intentpress-stat">
								<span className="intentpress-stat-value">
									{indexStatus.indexed}
								</span>
								<span className="intentpress-stat-label">
									{__('Posts Indexed', 'intentpress')}
								</span>
							</div>
							<div className="intentpress-stat">
								<span className="intentpress-stat-value">
									{indexStatus.total}
								</span>
								<span className="intentpress-stat-label">
									{__('Total Posts', 'intentpress')}
								</span>
							</div>
							<div className="intentpress-stat">
								<span className="intentpress-stat-value">
									{indexStatus.percentage}%
								</span>
								<span className="intentpress-stat-label">
									{__('Indexed', 'intentpress')}
								</span>
							</div>
							<div className="intentpress-progress-bar">
								<div
									className="intentpress-progress-fill"
									style={{
										width: `${indexStatus.percentage}%`,
									}}
								/>
							</div>
							{indexStatus.limit_reached && (
								<Notice status="warning" isDismissible={false}>
									{__(
										'Index limit reached. Upgrade to index more posts.',
										'intentpress'
									)}
								</Notice>
							)}
						</div>
					)}
				</CardBody>
			</Card>

			{/* Analytics Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>
						{__('Search Analytics (Last 7 Days)', 'intentpress')}
					</h2>
				</CardHeader>
				<CardBody>
					{analyticsLoading && <Spinner />}
					{!analyticsLoading && analytics && (
						<div className="intentpress-analytics">
							<div className="intentpress-analytics-stats">
								<div className="intentpress-stat">
									<span className="intentpress-stat-value">
										{analytics.summary.total_searches}
									</span>
									<span className="intentpress-stat-label">
										{__('Total Searches', 'intentpress')}
									</span>
								</div>
								<div className="intentpress-stat">
									<span className="intentpress-stat-value">
										{analytics.summary.avg_results.toFixed(
											1
										)}
									</span>
									<span className="intentpress-stat-label">
										{__('Avg. Results', 'intentpress')}
									</span>
								</div>
								<div className="intentpress-stat">
									<span className="intentpress-stat-value">
										{(
											analytics.summary
												.avg_execution_time * 1000
										).toFixed(0)}
										ms
									</span>
									<span className="intentpress-stat-label">
										{__(
											'Avg. Response Time',
											'intentpress'
										)}
									</span>
								</div>
								<div className="intentpress-stat">
									<span className="intentpress-stat-value">
										{analytics.summary.fallback_rate}%
									</span>
									<span className="intentpress-stat-label">
										{__('Fallback Rate', 'intentpress')}
									</span>
								</div>
							</div>
							{analytics.top_queries.length > 0 && (
								<div className="intentpress-top-queries">
									<h3>{__('Top Queries', 'intentpress')}</h3>
									<ul>
										{analytics.top_queries
											.slice(0, 5)
											.map((query, index) => (
												<li key={index}>
													<span className="intentpress-query-text">
														{query.query_text}
													</span>
													<span className="intentpress-query-count">
														{query.count}
													</span>
												</li>
											))}
									</ul>
								</div>
							)}
						</div>
					)}
				</CardBody>
			</Card>

			{/* Test Search Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Test Search', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<div className="intentpress-test-search">
						<div className="intentpress-test-search-form">
							<TextControl
								label={__('Search Query', 'intentpress')}
								value={testQuery}
								onChange={setTestQuery}
								placeholder={__(
									'Enter a test search query…',
									'intentpress'
								)}
							/>
							<Button
								variant="primary"
								onClick={handleTestSearch}
								isBusy={testSearch.isPending}
								disabled={
									!testQuery.trim() || testSearch.isPending
								}
							>
								{__('Search', 'intentpress')}
							</Button>
						</div>

						{testSearch.isError && (
							<Notice status="error" isDismissible={false}>
								{__(
									'Search failed. Please try again.',
									'intentpress'
								)}
							</Notice>
						)}

						{testResults && (
							<div className="intentpress-test-results">
								<div className="intentpress-test-meta">
									<span>
										{__('Type:', 'intentpress')}{' '}
										{testResults.meta.search_type}
									</span>
									<span>
										{__('Time:', 'intentpress')}{' '}
										{(
											testResults.meta.execution_time *
											1000
										).toFixed(0)}
										ms
									</span>
									<span>
										{__('Results:', 'intentpress')}{' '}
										{testResults.data.total}
									</span>
									{testResults.meta.fallback_used && (
										<span className="intentpress-fallback-notice">
											{__(
												'(Fallback used)',
												'intentpress'
											)}
										</span>
									)}
								</div>

								{testResults.data.results.length > 0 ? (
									<ul className="intentpress-results-list">
										{testResults.data.results.map(
											(result) => (
												<li
													key={result.id}
													className="intentpress-result-item"
												>
													<a
														href={result.url}
														target="_blank"
														rel="noopener noreferrer"
													>
														{result.title}
													</a>
													{result.similarity && (
														<span className="intentpress-similarity">
															{(
																result.similarity *
																100
															).toFixed(1)}
															%
														</span>
													)}
													<p className="intentpress-result-excerpt">
														{result.excerpt}
													</p>
												</li>
											)
										)}
									</ul>
								) : (
									<p className="intentpress-no-results">
										{__('No results found.', 'intentpress')}
									</p>
								)}
							</div>
						)}
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default DashboardTab;
