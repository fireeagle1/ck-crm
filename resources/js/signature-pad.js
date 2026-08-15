/**
 * Signature Pad initialization module.
 *
 * Include via @vite('resources/js/signature-pad.js') on pages that need signature capture.
 * Exposes SignaturePad globally for use in inline scripts and Alpine components.
 */
import SignaturePad from 'signature_pad';

window.SignaturePad = SignaturePad;
