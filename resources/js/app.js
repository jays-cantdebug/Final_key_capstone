import './bootstrap';

import Alpine from 'alpinejs';
import './live-search';
import './session-form';
import remoteMonitor from './remote-monitor';
import copyLink from './copy-link';

window.Alpine = Alpine;

Alpine.data('remoteMonitor', remoteMonitor);
Alpine.data('copyLink', copyLink);

Alpine.start();
