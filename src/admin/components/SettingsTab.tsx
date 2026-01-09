/**
 * Settings Tab Component
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	TextControl,
	CheckboxControl,
	RangeControl,
	SelectControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useSettings,
	useUpdateSettings,
	useValidateApiKey,
} from '../hooks/useApi';

/**
 * Admin configuration.
 */
declare const intentpressAdmin: {
	postTypes: Array<{ value: string; label: string }>;
};

/**
 * Settings Tab component.
 */
const SettingsTab: React.FC = () => {
	const { data: settings, isLoading, isError } = useSettings();
	const updateSettings = useUpdateSettings();
	const validateApiKey = useValidateApiKey();

	// Local state for form values.
	const [apiKey, setApiKey] = useState<string>('');
	const [showApiKey, setShowApiKey] = useState<boolean>(false);
	const [indexedPostTypes, setIndexedPostTypes] = useState<string[]>([]);
	const [perPage, setPerPage] = useState<number>(10);
	const [similarityThreshold, setSimilarityThreshold] = useState<number>(0.5);
	const [fallbackEnabled, setFallbackEnabled] = useState<boolean>(true);
	const [replaceSearch, setReplaceSearch] = useState<boolean>(true);
	const [cacheTtl, setCacheTtl] = useState<number>(3600);
	const [maxResults, setMaxResults] = useState<number>(100);
	const [apiKeyValidation, setApiKeyValidation] = useState<{
		valid: boolean | null;
		message: string;
	}>({ valid: null, message: '' });

	const [saveSuccess, setSaveSuccess] = useState<boolean>(false);

	// Sync settings to local state.
	useEffect(() => {
		if (settings) {
			setIndexedPostTypes(settings.indexed_post_types || []);
			setPerPage(settings.per_page || 10);
			setSimilarityThreshold(settings.similarity_threshold || 0.5);
			setFallbackEnabled(settings.fallback_enabled ?? true);
			setReplaceSearch(settings.replace_search ?? true);
			setCacheTtl(settings.cache_ttl || 3600);
			setMaxResults(settings.max_results || 100);
		}
	}, [settings]);

	/**
	 * Handle API key validation.
	 */
	const handleValidateApiKey = async () => {
		if (!apiKey.trim()) {
			setApiKeyValidation({
				valid: false,
				message: __('Please enter an API key.', 'intentpress'),
			});
			return;
		}

		try {
			const result = await validateApiKey.mutateAsync(apiKey);
			setApiKeyValidation({
				valid: result.valid,
				message: result.valid
					? __('API key is valid!', 'intentpress')
					: result.error || __('Invalid API key.', 'intentpress'),
			});
		} catch (error) {
			setApiKeyValidation({
				valid: false,
				message: __('Failed to validate API key.', 'intentpress'),
			});
		}
	};

	/**
	 * Handle post type toggle.
	 * @param postType
	 * @param checked
	 */
	const handlePostTypeToggle = (postType: string, checked: boolean) => {
		if (checked) {
			setIndexedPostTypes([...indexedPostTypes, postType]);
		} else {
			setIndexedPostTypes(
				indexedPostTypes.filter((pt) => pt !== postType)
			);
		}
	};

	/**
	 * Save settings.
	 */
	const handleSave = async () => {
		setSaveSuccess(false);

		const data: Record<string, unknown> = {
			indexed_post_types: indexedPostTypes,
			per_page: perPage,
			similarity_threshold: similarityThreshold,
			fallback_enabled: fallbackEnabled,
			replace_search: replaceSearch,
			cache_ttl: cacheTtl,
			max_results: maxResults,
		};

		// Only include API key if it was entered.
		if (apiKey.trim()) {
			data.api_key = apiKey;
		}

		try {
			await updateSettings.mutateAsync(data as any);
			setSaveSuccess(true);
			setApiKey('');

			// Clear success message after 3 seconds.
			setTimeout(() => setSaveSuccess(false), 3000);
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

	if (isError) {
		return (
			<Notice status="error" isDismissible={false}>
				{__('Failed to load settings.', 'intentpress')}
			</Notice>
		);
	}

	return (
		<div className="intentpress-settings">
			{saveSuccess && (
				<Notice status="success" isDismissible={false}>
					{__('Settings saved successfully!', 'intentpress')}
				</Notice>
			)}

			{updateSettings.isError && (
				<Notice status="error" isDismissible={false}>
					{__(
						'Failed to save settings. Please try again.',
						'intentpress'
					)}
				</Notice>
			)}

			{/* API Key Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('API Configuration', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<div className="intentpress-api-key-section">
						{settings?.api_key_configured && (
							<Notice status="success" isDismissible={false}>
								{__('API key configured:', 'intentpress')}{' '}
								<code>{settings.api_key_masked}</code>
							</Notice>
						)}

						<TextControl
							label={__('OpenAI API Key', 'intentpress')}
							value={apiKey}
							onChange={setApiKey}
							type={showApiKey ? 'text' : 'password'}
							placeholder={
								settings?.api_key_configured
									? __(
											'Enter new key to replace…',
											'intentpress'
										)
									: __('sk-…', 'intentpress')
							}
							help={__(
								'Your API key is encrypted before storage.',
								'intentpress'
							)}
						/>

						<div className="intentpress-api-key-actions">
							<CheckboxControl
								label={__('Show API key', 'intentpress')}
								checked={showApiKey}
								onChange={setShowApiKey}
							/>
							<Button
								variant="secondary"
								onClick={handleValidateApiKey}
								isBusy={validateApiKey.isPending}
								disabled={
									!apiKey.trim() || validateApiKey.isPending
								}
							>
								{__('Validate Key', 'intentpress')}
							</Button>
						</div>

						{apiKeyValidation.message && (
							<Notice
								status={
									apiKeyValidation.valid ? 'success' : 'error'
								}
								isDismissible={false}
							>
								{apiKeyValidation.message}
							</Notice>
						)}
					</div>
				</CardBody>
			</Card>

			{/* Content Settings Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Content Settings', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<div className="intentpress-post-types">
						<p className="intentpress-field-label">
							{__('Post Types to Index', 'intentpress')}
						</p>
						{intentpressAdmin.postTypes.map((postType) => (
							<CheckboxControl
								key={postType.value}
								label={postType.label}
								checked={indexedPostTypes.includes(
									postType.value
								)}
								onChange={(checked) =>
									handlePostTypeToggle(
										postType.value,
										checked
									)
								}
							/>
						))}
					</div>
				</CardBody>
			</Card>

			{/* Search Settings Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Search Settings', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<RangeControl
						label={__('Results Per Page', 'intentpress')}
						value={perPage}
						onChange={(value) => setPerPage(value || 10)}
						min={1}
						max={50}
						help={__(
							'Number of search results to show per page.',
							'intentpress'
						)}
					/>

					<RangeControl
						label={__('Similarity Threshold', 'intentpress')}
						value={similarityThreshold}
						onChange={(value) =>
							setSimilarityThreshold(value || 0.5)
						}
						min={0}
						max={1}
						step={0.05}
						help={__(
							'Minimum similarity score (0-1) for results. Lower values return more results.',
							'intentpress'
						)}
					/>

					<RangeControl
						label={__('Maximum Results', 'intentpress')}
						value={maxResults}
						onChange={(value) => setMaxResults(value || 100)}
						min={10}
						max={500}
						step={10}
						help={__(
							'Maximum number of results to return per query.',
							'intentpress'
						)}
					/>

					<CheckboxControl
						label={__('Replace WordPress Search', 'intentpress')}
						checked={replaceSearch}
						onChange={setReplaceSearch}
						help={__(
							"Replace the default WordPress search with IntentPress semantic search. Uses your theme's search template.",
							'intentpress'
						)}
					/>

					<CheckboxControl
						label={__(
							'Enable WordPress Search Fallback',
							'intentpress'
						)}
						checked={fallbackEnabled}
						onChange={setFallbackEnabled}
						help={__(
							'Fall back to WordPress default search if semantic search fails or returns no results.',
							'intentpress'
						)}
					/>
				</CardBody>
			</Card>

			{/* Shortcodes Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Shortcodes & Integration', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<p>
						{__(
							'Use these shortcodes to add semantic search to any page or widget:',
							'intentpress'
						)}
					</p>
					<div className="intentpress-shortcodes">
						<div className="intentpress-shortcode">
							<code>[intentpress_search]</code>
							<span>
								{__('Displays a search form', 'intentpress')}
							</span>
						</div>
						<div className="intentpress-shortcode">
							<code>[intentpress_results]</code>
							<span>
								{__(
									'Displays search results with relevance scores',
									'intentpress'
								)}
							</span>
						</div>
					</div>
					<p className="intentpress-shortcode-note">
						{__(
							'A search widget is also available under Appearance → Widgets.',
							'intentpress'
						)}
					</p>
				</CardBody>
			</Card>

			{/* Advanced Settings Card */}
			<Card className="intentpress-card">
				<CardHeader>
					<h2>{__('Advanced Settings', 'intentpress')}</h2>
				</CardHeader>
				<CardBody>
					<SelectControl
						label={__('Cache Duration', 'intentpress')}
						value={String(cacheTtl)}
						onChange={(value) => setCacheTtl(parseInt(value, 10))}
						options={[
							{
								value: '0',
								label: __('No caching', 'intentpress'),
							},
							{
								value: '900',
								label: __('15 minutes', 'intentpress'),
							},
							{
								value: '1800',
								label: __('30 minutes', 'intentpress'),
							},
							{
								value: '3600',
								label: __('1 hour', 'intentpress'),
							},
							{
								value: '7200',
								label: __('2 hours', 'intentpress'),
							},
							{
								value: '86400',
								label: __('24 hours', 'intentpress'),
							},
						]}
						help={__(
							'How long to cache embedding results.',
							'intentpress'
						)}
					/>
				</CardBody>
			</Card>

			{/* Save Button */}
			<div className="intentpress-settings-actions">
				<Button
					variant="primary"
					onClick={handleSave}
					isBusy={updateSettings.isPending}
					disabled={updateSettings.isPending}
				>
					{__('Save Settings', 'intentpress')}
				</Button>
			</div>
		</div>
	);
};

export default SettingsTab;
