/**
 * IntentPress Admin Entry Point
 *
 * @package
 */

import { createRoot } from '@wordpress/element';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './admin/App';

import './admin/styles/index.css';

// Initialize React Query client.
const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			staleTime: 30000,
			retry: 1,
		},
	},
});

// Mount the app when DOM is ready.
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('intentpress-admin-root');

	if (container) {
		const root = createRoot(container);
		root.render(
			<QueryClientProvider client={queryClient}>
				<App />
			</QueryClientProvider>
		);
	}
});
