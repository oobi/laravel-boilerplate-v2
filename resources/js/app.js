import './bootstrap';
import { initTabScrollControllers } from './components/tab-scroll.js';

const initializeTabScrollControllers = () => {
    initTabScrollControllers();
};

document.addEventListener('DOMContentLoaded', initializeTabScrollControllers);
document.addEventListener('livewire:navigated', initializeTabScrollControllers);
