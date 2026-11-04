import './bootstrap';
// Cally calendar web components (<calendar-range>, <calendar-date>), styled by daisyUI's .cally.
import 'cally';
import { initTabScrollControllers } from './components/tab-scroll.js';
import { initTableSelectionGuard } from './components/table-selection.js';
import { initLivewireAccessHandling } from './components/livewire-access.js';
import { keepTableScrollStateThroughRenders, tableScrollRail } from './components/table-scroll-rail.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('tableScrollRail', tableScrollRail);
});

document.addEventListener('livewire:init', keepTableScrollStateThroughRenders);

const initializeTabScrollControllers = () => {
    initTabScrollControllers();
};

document.addEventListener('DOMContentLoaded', initializeTabScrollControllers);
document.addEventListener('livewire:navigated', initializeTabScrollControllers);

document.addEventListener('DOMContentLoaded', initTableSelectionGuard);
document.addEventListener('livewire:navigated', initTableSelectionGuard);

// A Livewire update refused for lost access (401/403) reloads the page instead of showing the error overlay.
initLivewireAccessHandling();
