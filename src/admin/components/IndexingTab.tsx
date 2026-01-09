/**
 * Indexing Tab Component
 *
 * @package
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	Notice,
	Spinner,
	RangeControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useIndexStatus,
	useTriggerIndexing,
	useClearIndex,
} from '../hooks/useApi';

/**
 * Admin configuration.
 */
declare const intentpressAdmin: {
	limits: {
		freeSearches: number;
		freeIndex: number;
	};
};

/**
 * Indexing Tab component.
 */
const IndexingTab: React.FC = () => {
	const { data: indexStatus, isLoading, refetch } = useIndexStatus();
	const triggerIndexing = useTriggerIndexing();
	const clearIndex = useClearIndex();

	const [isIndexing, setIsIndexing] = useState<boolean>(false);
	const [indexProgress, setIndexProgress] = useState<{
		total: number;
		completed: number;
		errors: Array<{ post_id: number; error: string }>;
	}>({ total: 0, completed: 0, errors: [] });
	const [batchSize, setBatchSize] = useState<number>(10);
	const [showClearConfirm, setShowClearConfirm] = useState<boolean>(false);

	const indexingRef = useRef<boolean>(false);

	/**
	 * Start indexing process.
	 */
	const handleStartIndexing = async () => {
		if (isIndexing || !indexStatus) {
			return;
		}

		setIsIndexing(true);
		indexingRef.current = true;
		setIndexProgress({
			total: indexStatus.needs_indexing,
			completed: 0,
			errors: [],
		});

		let totalIndexed = 0;
		const allErrors: Array<{ post_id: number; error: string }> = [];

		// Index in batches.
		while (indexingRef.current) {
			try {
				const result = await triggerIndexing.mutateAsync({
					batch_size: batchSize,
				});

				if (result.indexed === 0) {
					// No more posts to index.
					break;
				}

				totalIndexed += result.indexed;
				allErrors.push(...result.errors);

				setIndexProgress((prev) => ({
					...prev,
					completed: totalIndexed,
					errors: allErrors,
				}));

				// Refetch status to update progress.
				await refetch();

				// Check if we've hit the limit.
				const currentStatus = await refetch();
				if (currentStatus.data?.limit_reached) {
					break;
				}
			} catch (error) {
				// Stop on error.
				break;
			}
		}

		setIsIndexing(false);
		indexingRef.current = false;
	};

	/**
	 * Stop indexing process.
	 */
	const handleStopIndexing = () => {
		indexingRef.current = false;
		setIsIndexing(false);
	};

	/**
	 * Clear all indexed data.
	 */
	const handleClearIndex = async () => {
		try {
			await clearIndex.mutateAsync();
			setShowClearConfirm(false);
			await refetch();
		} catch (error) {
			// Error handled by mutation state.
		}
	};

	/**
	 * Index single batch.
	 */
	const handleIndexBatch = async () => {
		try {
			await triggerIndexing.mutateAsync({ batch_size: batchSize });
			await refetch();
		} catch (error) {
			// Error handled by mutation state.
		}
	};

	if (isLoading) {
		return (
			<div className="intentpress-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="intentpress-indexing">
			{/* Index Status Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Index Status', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					{indexStatus ? (
						<div className="intentpress-index-overview">
							<div className="intentpress-index-stats">
								<div className="intentpress-stat intentpress-stat--large">
									<span className="intentpress-stat-value">
										{indexStatus.indexed}
									</span>
									<span className="intentpress-stat-label">
										{__('Posts Indexed', 'intentpress')}
									</span>
								</div>
								<div className="intentpress-stat intentpress-stat--large">
									<span className="intentpress-stat-value">
										{indexStatus.total}
									</span>
									<span className="intentpress-stat-label">
										{__('Total Indexable', 'intentpress')}
									</span>
								</div>
								<div className="intentpress-stat intentpress-stat--large">
									<span className="intentpress-stat-value">
										{indexStatus.needs_indexing}
									</span>
									<span className="intentpress-stat-label">
										{__('Needs Indexing', 'intentpress')}
									</span>
								</div>
							</div>

							<div className="intentpress-progress-section">
								<div className="intentpress-progress-info">
									<span>
										{__('Progress:', 'intentpress')}
									</span>
									<span>{indexStatus.percentage}%</span>
								</div>
								<div className="intentpress-progress-bar intentpress-progress-bar--large">
									<div
										className="intentpress-progress-fill"
										style={{
											width: `${indexStatus.percentage}%`,
										}}
									/>
								</div>
							</div>

							<div className="intentpress-limit-info">
								<span>
									{__('Free tier limit:', 'intentpress')}{' '}
									{indexStatus.indexed} /{' '}
									{intentpressAdmin.limits.freeIndex}{' '}
									{__('posts', 'intentpress')}
								</span>
								{indexStatus.limit_reached && (
									<Notice
										status="warning"
										isDismissible={false}
									>
										{__(
											'You have reached the free tier limit. Upgrade to index more posts.',
											'intentpress'
										)}
									</Notice>
								)}
							</div>
						</div>
					) : (
						<Notice status="error" isDismissible={false}>
							{__('Unable to load index status.', 'intentpress')}
						</Notice>
					)}
				</CardBody>
			</Card>

			{/* Index Actions Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Index Actions', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<div className="intentpress-index-actions">
						<RangeControl
							label={__('Batch Size', 'intentpress')}
							value={batchSize}
							onChange={(value) => setBatchSize(value || 10)}
							min={1}
							max={50}
							help={__(
								'Number of posts to index per batch. Lower values are more stable.',
								'intentpress'
							)}
							disabled={isIndexing}
						/>

						<div className="intentpress-action-buttons">
							{!isIndexing ? (
								<>
									<Button
										variant="primary"
										onClick={handleStartIndexing}
										disabled={
											!indexStatus ||
											indexStatus.needs_indexing === 0 ||
											indexStatus.limit_reached
										}
									>
										{__(
											'Start Full Indexing',
											'intentpress'
										)}
									</Button>
									<Button
										variant="secondary"
										onClick={handleIndexBatch}
										disabled={
											!indexStatus ||
											indexStatus.needs_indexing === 0 ||
											indexStatus.limit_reached ||
											triggerIndexing.isPending
										}
										isBusy={triggerIndexing.isPending}
									>
										{__('Index One Batch', 'intentpress')}
									</Button>
								</>
							) : (
								<Button
									variant="secondary"
									onClick={handleStopIndexing}
									isDestructive
								>
									{__('Stop Indexing', 'intentpress')}
								</Button>
							)}
						</div>

						{/* Indexing Progress */}
						{isIndexing && (
							<div className="intentpress-indexing-progress">
								<Spinner />
								<p>
									{__('Indexing in progress…', 'intentpress')}{' '}
									{indexProgress.completed} /{' '}
									{indexProgress.total}
								</p>
								<div className="intentpress-progress-bar">
									<div
										className="intentpress-progress-fill"
										style={{
											width:
												indexProgress.total > 0
													? `${(indexProgress.completed / indexProgress.total) * 100}%`
													: '0%',
										}}
									/>
								</div>
								{indexProgress.errors.length > 0 && (
									<Notice
										status="warning"
										isDismissible={false}
									>
										{indexProgress.errors.length}{' '}
										{__(
											'posts failed to index.',
											'intentpress'
										)}
									</Notice>
								)}
							</div>
						)}

						{triggerIndexing.isSuccess && !isIndexing && (
							<Notice status="success" isDismissible={false}>
								{triggerIndexing.data?.message}
							</Notice>
						)}

						{triggerIndexing.isError && (
							<Notice status="error" isDismissible={false}>
								{__(
									'Indexing failed. Please try again.',
									'intentpress'
								)}
							</Notice>
						)}
					</div>
				</CardBody>
			</Card>

			{/* Danger Zone Card */}
			<Card className="intentpress-card intentpress-card--danger">
				<CardHeader>
					<h2>{__('Danger Zone', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<div className="intentpress-danger-zone">
						<p className="intentpress-danger-description">
							{__(
								'Clear all indexed data. This will remove all stored embeddings and require re-indexing all content.',
								'intentpress'
							)}
						</p>

						{!showClearConfirm ? (
							<Button
								variant="secondary"
								isDestructive
								onClick={() => setShowClearConfirm(true)}
								disabled={isIndexing}
							>
								{__('Clear Index', 'intentpress')}
							</Button>
						) : (
							<div className="intentpress-confirm-clear">
								<p className="intentpress-confirm-message">
									{__(
										'Are you sure? This cannot be undone.',
										'intentpress'
									)}
								</p>
								<div className="intentpress-confirm-buttons">
									<Button
										variant="secondary"
										isDestructive
										onClick={handleClearIndex}
										isBusy={clearIndex.isPending}
									>
										{__('Yes, Clear Index', 'intentpress')}
									</Button>
									<Button
										variant="tertiary"
										onClick={() =>
											setShowClearConfirm(false)
										}
									>
										{__('Cancel', 'intentpress')}
									</Button>
								</div>
							</div>
						)}

						{clearIndex.isSuccess && (
							<Notice status="success" isDismissible={false}>
								{__(
									'Index cleared successfully.',
									'intentpress'
								)}
							</Notice>
						)}

						{clearIndex.isError && (
							<Notice status="error" isDismissible={false}>
								{__('Failed to clear index.', 'intentpress')}
							</Notice>
						)}
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default IndexingTab;
