/**
 * Tailwind CSS Configuration for KPT DataTables
 * 
 * @since   1.1.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/**/*.php',
    './src/**/*.js',
    './src/assets/css/themes/tailwind.src.css'
  ],
  theme: {
    extend: {
      colors: {
        'kp-dt-primary': '#1e87f0',
        'kp-dt-success': '#32d296',
        'kp-dt-danger': '#f0506e',
        'kp-dt-warning': '#faa05a',
        'kp-dt-muted': '#999',
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
    },
  },
  plugins: [],
  // Safelist classes that might be dynamically generated
  safelist: [
    'kp-dt-container-tailwind',
    'kp-dt-table-tailwind',
    'kp-dt-table-striped-tailwind',
    'kp-dt-table-hover-tailwind',
    'kp-dt-input-tailwind',
    'kp-dt-select-tailwind',
    'kp-dt-textarea-tailwind',
    'kp-dt-button-tailwind',
    'kp-dt-button-primary-tailwind',
    'kp-dt-button-danger-tailwind',
    'kp-dt-button-small-tailwind',
    'kp-dt-modal-tailwind',
    'kp-dt-open-tailwind',
    'kp-dt-notification-tailwind',
    'kp-dt-notification-success-tailwind',
    'kp-dt-notification-danger-tailwind',
    'kp-dt-notification-warning-tailwind',
  ],
}
