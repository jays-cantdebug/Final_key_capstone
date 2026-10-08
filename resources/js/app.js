import './bootstrap';

import Alpine from 'alpinejs';
import './live-search';
import './session-form';
import remoteMonitor from './remote-monitor';

window.Alpine = Alpine;

Alpine.data('remoteMonitor', remoteMonitor);

Alpine.start();
