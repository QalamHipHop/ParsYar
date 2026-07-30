import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import './styles.css';

declare global {
	interface Window {
		parsYarBoot?: {
			rest: string;
			nonce: string;
			userId: number;
		};
	}
}

const rootEl = document.getElementById('pars-yar-root');
if (rootEl) {
	createRoot(rootEl).render(
		<React.StrictMode>
			<BrowserRouter>
				<App />
			</BrowserRouter>
		</React.StrictMode>
	);
}
