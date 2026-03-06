import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import App from './App.tsx';
import './index.css';

const rootElement = document.getElementById('ops-view');
if (!rootElement) throw new Error("Could not find #ops-view element to mount to");

createRoot(rootElement).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
