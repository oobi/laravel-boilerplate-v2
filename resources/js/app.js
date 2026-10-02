import './bootstrap';
// Cally calendar web components (<calendar-range>, <calendar-date>), styled by daisyUI's .cally.
import 'cally';
import { initTabScrollControllers } from './components/tab-scroll.js';
import { initTableSelectionGuard } from './components/table-selection.js';

const initializeTabScrollControllers = () => {
    initTabScrollControllers();
};

document.addEventListener('DOMContentLoaded', initializeTabScrollControllers);
document.addEventListener('livewire:navigated', initializeTabScrollControllers);

document.addEventListener('DOMContentLoaded', initTableSelectionGuard);
document.addEventListener('livewire:navigated', initTableSelectionGuard);
