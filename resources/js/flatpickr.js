/**
 * Flatpickr date picker initialization module.
 *
 * Include via @vite('resources/js/flatpickr.js') on pages that need date picking.
 * Exposes flatpickr globally for use in inline scripts and Alpine components.
 */
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

window.flatpickr = flatpickr;
