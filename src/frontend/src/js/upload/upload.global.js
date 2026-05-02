import Upload from './Upload.js';

/**
 * Global entry for standalone `<script>` usage.
 *
 * When bundled into a browser script, this exposes the class as `window.Upload`.
 * The Upload class still lazy-loads Dropzone assets when needed.
 */
if (typeof window !== 'undefined') {
    window.Upload = Upload;
}

