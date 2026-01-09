/**
 * Main Admin App Component
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import { TabPanel, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import DashboardTab from './components/DashboardTab';
import SettingsTab from './components/SettingsTab';
import IndexingTab from './components/IndexingTab';
import OnboardingWizard from './components/OnboardingWizard';
import { useOnboardingStatus } from './hooks/useApi';

import './styles/app.css';

/**
 * Admin configuration from localized script.
 */
declare const intentpressAdmin: {
	apiUrl: string;
	nonce: string;
	version: string;
	isConfigured: boolean;
	postTypes: Array<{ value: string; label: string }>;
	limits: {
		freeSearches: number;
		freeIndex: number;
	};
};

/**
 * Tab definition type.
 */
interface Tab {
	name: string;
	title: string;
	className: string;
}

/**
 * Main App component.
 */
const App: React.FC = () => {
	const [activeTab, setActiveTab] = useState<string>('dashboard');
	const { data: onboardingData, isLoading: onboardingLoading } =
		useOnboardingStatus();

	// Check if onboarding is needed.
	const showOnboarding =
		!onboardingLoading && onboardingData && !onboardingData.complete;

	// Tab definitions.
	const tabs: Tab[] = [
		{
			name: 'dashboard',
			title: __('Dashboard', 'intentpress'),
			className: 'intentpress-tab-dashboard',
		},
		{
			name: 'settings',
			title: __('Settings', 'intentpress'),
			className: 'intentpress-tab-settings',
		},
		{
			name: 'indexing',
			title: __('Indexing', 'intentpress'),
			className: 'intentpress-tab-indexing',
		},
	];

	/**
	 * Handle tab selection.
	 * @param tabName
	 */
	const handleTabSelect = (tabName: string) => {
		setActiveTab(tabName);
	};

	/**
	 * Render tab content.
	 * @param tab
	 */
	const renderTabContent = (tab: Tab) => {
		switch (tab.name) {
			case 'dashboard':
				return <DashboardTab />;
			case 'settings':
				return <SettingsTab />;
			case 'indexing':
				return <IndexingTab />;
			default:
				return null;
		}
	};

	// Show onboarding wizard if not completed.
	if (showOnboarding) {
		return (
			<div className="intentpress-admin">
				<OnboardingWizard />
			</div>
		);
	}

	return (
		<div className="intentpress-admin">
			<header className="intentpress-header">
				<h1 className="intentpress-title">
					{__('IntentPress', 'intentpress')}
				</h1>
				<span className="intentpress-version">
					v{intentpressAdmin.version}
				</span>
			</header>

			<TabPanel
				className="intentpress-tabs"
				activeClass="is-active"
				tabs={tabs}
				onSelect={handleTabSelect}
				initialTabName={activeTab}
			>
				{(tab) => (
					<div className="intentpress-tab-content">
						{renderTabContent(tab)}
					</div>
				)}
			</TabPanel>
		</div>
	);
};

export default App;
