/**
 * Onboarding Wizard Component
 *
 * @package
 */

import { useState } from '@wordpress/element';
import {
	Card,
	CardBody,
	Button,
	TextControl,
	Notice,
	Spinner,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	useValidateApiKey,
	useUpdateSettings,
	useTriggerIndexing,
	useUpdateOnboarding,
} from '../hooks/useApi';

/**
 * Admin configuration.
 */
declare const intentpressAdmin: {
	postTypes: Array<{ value: string; label: string }>;
};

/**
 * Get step class based on index and current step.
 * @param index
 * @param currentStep
 */
const getStepClass = (index: number, currentStep: number): string => {
	if (index === currentStep) {
		return 'intentpress-step--active';
	}
	if (index < currentStep) {
		return 'intentpress-step--completed';
	}
	return '';
};

/**
 * Step indicator component.
 * @param root0
 * @param root0.steps
 * @param root0.currentStep
 */
const StepIndicator: React.FC<{
	steps: string[];
	currentStep: number;
}> = ({ steps, currentStep }) => {
	return (
		<div className="intentpress-step-indicator">
			{steps.map((step, index) => (
				<div
					key={index}
					className={`intentpress-step ${getStepClass(index, currentStep)}`}
				>
					<span className="intentpress-step-number">{index + 1}</span>
					<span className="intentpress-step-label">{step}</span>
				</div>
			))}
		</div>
	);
};

/**
 * Onboarding Wizard component.
 */
const OnboardingWizard: React.FC = () => {
	const [currentStep, setCurrentStep] = useState<number>(0);
	const [apiKey, setApiKey] = useState<string>('');
	const [showApiKey, setShowApiKey] = useState<boolean>(false);
	const [selectedPostTypes, setSelectedPostTypes] = useState<string[]>([
		'post',
		'page',
	]);
	const [indexedCount, setIndexedCount] = useState<number>(0);
	const [isIndexing, setIsIndexing] = useState<boolean>(false);

	const validateApiKey = useValidateApiKey();
	const updateSettings = useUpdateSettings();
	const triggerIndexing = useTriggerIndexing();
	const updateOnboarding = useUpdateOnboarding();

	const steps = [
		__('Welcome', 'intentpress'),
		__('API Key', 'intentpress'),
		__('Content', 'intentpress'),
		__('Index', 'intentpress'),
		__('Complete', 'intentpress'),
	];

	/**
	 * Handle post type toggle.
	 * @param postType
	 * @param checked
	 */
	const handlePostTypeToggle = (postType: string, checked: boolean) => {
		if (checked) {
			setSelectedPostTypes([...selectedPostTypes, postType]);
		} else {
			setSelectedPostTypes(
				selectedPostTypes.filter((pt) => pt !== postType)
			);
		}
	};

	/**
	 * Validate and save API key.
	 */
	const handleSaveApiKey = async () => {
		if (!apiKey.trim()) {
			return;
		}

		try {
			const validation = await validateApiKey.mutateAsync(apiKey);

			if (!validation.valid) {
				return;
			}

			await updateSettings.mutateAsync({ api_key: apiKey });
			setCurrentStep(2);
		} catch (error) {
			// Error handled by mutation state.
		}
	};

	/**
	 * Save content settings.
	 */
	const handleSaveContentSettings = async () => {
		try {
			await updateSettings.mutateAsync({
				indexed_post_types: selectedPostTypes,
			});
			setCurrentStep(3);
		} catch (error) {
			// Error handled by mutation state.
		}
	};

	/**
	 * Start initial indexing.
	 */
	const handleStartIndexing = async () => {
		setIsIndexing(true);
		let totalIndexed = 0;

		// Index a few batches to get started.
		for (let i = 0; i < 5; i++) {
			try {
				const result = await triggerIndexing.mutateAsync({
					batch_size: 10,
				});

				if (result.indexed === 0) {
					break;
				}

				totalIndexed += result.indexed;
				setIndexedCount(totalIndexed);
			} catch (error) {
				break;
			}
		}

		setIsIndexing(false);
		setCurrentStep(4);
	};

	/**
	 * Skip indexing.
	 */
	const handleSkipIndexing = () => {
		setCurrentStep(4);
	};

	/**
	 * Complete onboarding.
	 */
	const handleComplete = async () => {
		try {
			await updateOnboarding.mutateAsync(true);
			// The app will re-render and show the main dashboard.
		} catch (error) {
			// Error handled by mutation state.
		}
	};

	/**
	 * Render step content.
	 */
	const renderStepContent = () => {
		switch (currentStep) {
			case 0:
				return (
					<div className="intentpress-onboarding-step">
						<h2>{__('Welcome to IntentPress!', 'intentpress')}</h2>
						<p>
							{__(
								"IntentPress replaces WordPress's keyword-based search with AI-powered semantic search. Your visitors will find exactly what they're looking for, even if they don't use the exact words.",
								'intentpress'
							)}
						</p>
						<ul className="intentpress-feature-list">
							<li>
								{__(
									'Understand search intent, not just keywords',
									'intentpress'
								)}
							</li>
							<li>
								{__(
									'Find relevant content even with different wording',
									'intentpress'
								)}
							</li>
							<li>
								{__(
									'Automatic fallback to WordPress search if needed',
									'intentpress'
								)}
							</li>
						</ul>
						<p>
							{__(
								"Let's get you set up in just a few minutes.",
								'intentpress'
							)}
						</p>
						<Button
							variant="primary"
							onClick={() => setCurrentStep(1)}
						>
							{__('Get Started', 'intentpress')}
						</Button>
					</div>
				);

			case 1:
				return (
					<div className="intentpress-onboarding-step">
						<h2>{__('Connect Your API Key', 'intentpress')}</h2>
						<p>
							{__(
								"IntentPress uses OpenAI to understand search queries. You'll need an OpenAI API key.",
								'intentpress'
							)}
						</p>
						<p>
							<a
								href="https://platform.openai.com/api-keys"
								target="_blank"
								rel="noopener noreferrer"
							>
								{__(
									'Get your API key from OpenAI',
									'intentpress'
								)}{' '}
								→
							</a>
						</p>

						<TextControl
							label={__('OpenAI API Key', 'intentpress')}
							value={apiKey}
							onChange={setApiKey}
							type={showApiKey ? 'text' : 'password'}
							placeholder="sk-..."
						/>

						<CheckboxControl
							label={__('Show API key', 'intentpress')}
							checked={showApiKey}
							onChange={setShowApiKey}
						/>

						{validateApiKey.isError && (
							<Notice status="error" isDismissible={false}>
								{__(
									'Failed to validate API key.',
									'intentpress'
								)}
							</Notice>
						)}

						{validateApiKey.data && !validateApiKey.data.valid && (
							<Notice status="error" isDismissible={false}>
								{validateApiKey.data.error}
							</Notice>
						)}

						<div className="intentpress-onboarding-actions">
							<Button
								variant="tertiary"
								onClick={() => setCurrentStep(0)}
							>
								{__('Back', 'intentpress')}
							</Button>
							<Button
								variant="primary"
								onClick={handleSaveApiKey}
								isBusy={
									validateApiKey.isPending ||
									updateSettings.isPending
								}
								disabled={
									!apiKey.trim() ||
									validateApiKey.isPending ||
									updateSettings.isPending
								}
							>
								{__('Continue', 'intentpress')}
							</Button>
						</div>
					</div>
				);

			case 2:
				return (
					<div className="intentpress-onboarding-step">
						<h2>{__('Select Content to Index', 'intentpress')}</h2>
						<p>
							{__(
								'Choose which content types should be searchable with semantic search.',
								'intentpress'
							)}
						</p>

						<div className="intentpress-post-types-selection">
							{intentpressAdmin.postTypes.map((postType) => (
								<CheckboxControl
									key={postType.value}
									label={postType.label}
									checked={selectedPostTypes.includes(
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

						{updateSettings.isError && (
							<Notice status="error" isDismissible={false}>
								{__('Failed to save settings.', 'intentpress')}
							</Notice>
						)}

						<div className="intentpress-onboarding-actions">
							<Button
								variant="tertiary"
								onClick={() => setCurrentStep(1)}
							>
								{__('Back', 'intentpress')}
							</Button>
							<Button
								variant="primary"
								onClick={handleSaveContentSettings}
								isBusy={updateSettings.isPending}
								disabled={
									selectedPostTypes.length === 0 ||
									updateSettings.isPending
								}
							>
								{__('Continue', 'intentpress')}
							</Button>
						</div>
					</div>
				);

			case 3:
				return (
					<div className="intentpress-onboarding-step">
						<h2>{__('Index Your Content', 'intentpress')}</h2>
						<p>
							{__(
								"Let's index some of your content so semantic search can start working right away.",
								'intentpress'
							)}
						</p>

						{isIndexing ? (
							<div className="intentpress-indexing-progress">
								<Spinner />
								<p>
									{__(
										'Indexing your content…',
										'intentpress'
									)}{' '}
									{indexedCount}{' '}
									{__('posts indexed', 'intentpress')}
								</p>
							</div>
						) : (
							<>
								<p className="intentpress-index-note">
									{__(
										"We'll index up to 50 posts now. You can index more later from the Indexing tab.",
										'intentpress'
									)}
								</p>

								<div className="intentpress-onboarding-actions">
									<Button
										variant="tertiary"
										onClick={handleSkipIndexing}
									>
										{__('Skip for Now', 'intentpress')}
									</Button>
									<Button
										variant="primary"
										onClick={handleStartIndexing}
									>
										{__('Start Indexing', 'intentpress')}
									</Button>
								</div>
							</>
						)}
					</div>
				);

			case 4:
				return (
					<div className="intentpress-onboarding-step intentpress-onboarding-complete">
						<h2>{__("You're All Set!", 'intentpress')}</h2>
						<p>
							{__(
								'IntentPress is now configured and ready to use.',
								'intentpress'
							)}
						</p>

						{indexedCount > 0 && (
							<p className="intentpress-indexed-count">
								{indexedCount}{' '}
								{__('posts have been indexed.', 'intentpress')}
							</p>
						)}

						<div className="intentpress-next-steps">
							<h3>{__('Next Steps', 'intentpress')}</h3>
							<ul>
								<li>
									{__(
										'Visit the Dashboard to test your semantic search',
										'intentpress'
									)}
								</li>
								<li>
									{__(
										'Go to Indexing to index more content',
										'intentpress'
									)}
								</li>
								<li>
									{__(
										'Fine-tune settings in the Settings tab',
										'intentpress'
									)}
								</li>
							</ul>
						</div>

						<Button
							variant="primary"
							onClick={handleComplete}
							isBusy={updateOnboarding.isPending}
						>
							{__('Go to Dashboard', 'intentpress')}
						</Button>
					</div>
				);

			default:
				return null;
		}
	};

	return (
		<div className="intentpress-onboarding">
			<Card className="intentpress-onboarding-card">
				<CardBody>
					<StepIndicator steps={steps} currentStep={currentStep} />
					{renderStepContent()}
				</CardBody>
			</Card>
		</div>
	);
};

export default OnboardingWizard;
