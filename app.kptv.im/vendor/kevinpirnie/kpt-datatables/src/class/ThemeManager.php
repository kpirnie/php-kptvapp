<?php

declare(strict_types=1);

namespace KPT;

// Check if class already exists before declaring it
if (! class_exists('KPT\ThemeManager', false)) {

    /**
     * ThemeManager - Theme Configuration and Asset Management
     *
     * Handles theme selection, CSS class mapping, and asset URL generation
     * for different UI frameworks (Plain, UIKit, Bootstrap, Tailwind).
     *
     * @since   1.1.0
     * @author  Kevin Pirnie <me@kpirnie.com>
     * @package
     */
    class ThemeManager
    {
        /**
         * Available theme identifiers
         */
        public const THEME_PLAIN = 'plain';
        public const THEME_UIKIT = 'uikit';
        public const THEME_BOOTSTRAP = 'bootstrap';
        public const THEME_TAILWIND = 'tailwind';

        /**
         * CDN URLs for framework assets
         */
        // UIKIT
        private const CDN_UIKIT_CSS = 'https://cdn.jsdelivr.net/npm/uikit@latest/dist/css/uikit.css';
        private const CDN_UIKIT_JS = 'https://cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.js';
        private const CDN_UIKIT_ICONS = 'https://cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.js';
        private const CDN_UIKIT_MINCSS = 'https://cdn.jsdelivr.net/npm/uikit@latest/dist/css/uikit.min.css';
        private const CDN_UIKIT_MINJS = 'https://cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.min.js';
        private const CDN_UIKIT_MINICONS = 'https://cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.min.js';

        // BOOSTRAP
        private const CDN_BOOTSTRAP_CSS = 'https://cdn.jsdelivr.net/npm/bootstrap@latest/dist/css/bootstrap.css';
        private const CDN_BOOTSTRAP_JS = 'https://cdn.jsdelivr.net/npm/bootstrap@latest/dist/js/bootstrap.bundle.js';
        private const CDN_BOOTSTRAP_ICONS = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/font/bootstrap-icons.css';
        private const CDN_BOOTSTRAP_MINCSS = 'https://cdn.jsdelivr.net/npm/bootstrap@latest/dist/css/bootstrap.min.css';
        private const CDN_BOOTSTRAP_MINJS = 'https://cdn.jsdelivr.net/npm/bootstrap@latest/dist/js/bootstrap.bundle.min.js';
        private const CDN_BOOTSTRAP_MINICONS = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/font/bootstrap-icons.min.css';

        /**
         * Current theme
         *
         * @var string
         */
        private string $theme = self::THEME_UIKIT;

        /**
         * Class mappings for each theme
         *
         * @var array
         */
        private array $classMappings = [];

        /**
         * Constructor - Initialize theme manager with optional theme
         *
         * @param string $theme Initial theme to use
         */
        public function __construct(string $theme = self::THEME_UIKIT)
        {
            $this->setTheme($theme);
            $this->initializeClassMappings();
        }

        /**
         * Set the current theme
         *
         * @param  string $theme Theme identifier (plain, uikit, bootstrap, tailwind)
         * @return self Returns self for method chaining
         */
        public function setTheme(string $theme): self
        {
            $validThemes = [
                self::THEME_PLAIN,
                self::THEME_UIKIT,
                self::THEME_BOOTSTRAP,
                self::THEME_TAILWIND
            ];

            if (in_array($theme, $validThemes)) {
                $this->theme = $theme;
            }

            return $this;
        }

        /**
         * Get the current theme
         *
         * @return string Current theme identifier
         */
        public function getTheme(): string
        {
            return $this->theme;
        }

        /**
         * Get CSS class for a component
         *
         * @param  string $component Component name (e.g., 'table', 'button', 'input')
         * @param  string $variant   Optional variant (e.g., 'primary', 'striped')
         * @return string CSS class string
         */
        public function getClass(string $component, string $variant = ''): string
        {
            $key = $variant ? "{$component}.{$variant}" : $component;
            return $this->classMappings[$this->theme][$key] ?? '';
        }

        /**
         * Get all classes for a component with framework prefix
         *
         * @param  string $component Component name
         * @param  string $variant   Optional variant
         * @return string Combined CSS classes (framework + kp-dt prefix)
         */
        public function getClasses(string $component, string $variant = ''): string
        {
            $frameworkClass = $this->getClass($component, $variant);
            $kpDtClass = $this->getKpDtClass($component, $variant);

            return trim("{$frameworkClass} {$kpDtClass}");
        }

        /**
         * Get kp-dt prefixed class
         *
         * @param  string $component Component name
         * @param  string $variant   Optional variant
         * @return string kp-dt prefixed class
         */
        public function getKpDtClass(string $component, string $variant = ''): string
        {
            $suffix = $this->theme !== self::THEME_PLAIN ? "-{$this->theme}" : '';
            $base = "kp-dt-{$component}";

            if ($variant) {
                return "{$base}-{$variant}{$suffix}";
            }

            return "{$base}{$suffix}";
        }

        /**
         * Get CSS includes for the current theme
         *
         * @param  bool $includeCdn Whether to include CDN links for framework
         * @param  bool $useMinified Whether to include the minified versions for framework
         * @return string HTML link tags
         */
        public function getCssIncludes(bool $includeCdn = true, bool $useMinified = false): string
        {
            $html = '';

            // Add framework CDN CSS if enabled
            if ($includeCdn) {
                switch ($this->theme) {
                    case self::THEME_UIKIT:
                        $css = ($useMinified) ? self::CDN_UIKIT_MINCSS : self::CDN_UIKIT_CSS;
                        $html .= "<link rel=\"stylesheet\" href=\"" . $css . "\">\n";
                        break;
                    case self::THEME_BOOTSTRAP:
                        $css = ($useMinified) ? self::CDN_BOOTSTRAP_MINCSS : self::CDN_BOOTSTRAP_CSS;
                        $cssIcons = ($useMinified) ? self::CDN_BOOTSTRAP_MINICONS : self::CDN_BOOTSTRAP_ICONS;
                        $html .= "<link rel=\"stylesheet\" href=\"" . $css . "\">\n";
                        $html .= "<link rel=\"stylesheet\" href=\"" . $cssIcons . "\">\n";
                        break;
                }
            }

            // Add theme-specific CSS
            $themeCss = $this->getThemeCssPath($useMinified);
            if ($themeCss) {
                $html .= "<link rel=\"stylesheet\" href=\"{$themeCss}\">\n";
            }

            return $html;
        }

        /**
         * Get JavaScript includes for the current theme
         *
         * @param  bool $includeCdn Whether to include CDN links for framework
         * @param  bool $useMinified Whether to include the minified versions for framework
         * @return string HTML script tags
         */
        public function getJsIncludes(bool $includeCdn = true, bool $useMinified = false): string
        {
            $html = '';

            // Add framework CDN JS if enabled
            if ($includeCdn) {
                switch ($this->theme) {
                    case self::THEME_UIKIT:
                        $js = ($useMinified) ? self::CDN_UIKIT_MINJS : self::CDN_UIKIT_JS;
                        $jsIcons = ($useMinified) ? self::CDN_UIKIT_MINICONS : self::CDN_UIKIT_ICONS;
                        $html .= "<script src=\"" . $js . "\" defer></script>\n";
                        $html .= "<script src=\"" . $jsIcons . "\" defer></script>\n";
                        break;
                    case self::THEME_BOOTSTRAP:
                        $js = ($useMinified) ? self::CDN_BOOTSTRAP_MINJS : self::CDN_BOOTSTRAP_JS;
                        $html .= "<script src=\"" . $js . "\" defer></script>\n";
                        break;
                }
            }

            return $html;
        }

        /**
         * Get the path to the theme CSS file
         *
         * @return string Path to theme CSS file
         */
        public function getThemeCssPath(bool $useMinified = true): string
        {
            // seutp the css file to use
            $cssFile = ($useMinified) ? sprintf('dist/%s.min', $this->theme) : sprintf('themes/%s.min', $this->theme);
            return "/vendor/kevinpirnie/kpt-datatables/src/assets/css/{$cssFile}.css";
        }

        /**
         * Get icon HTML for the current theme
         *
         * @param  string $icon  Icon name
         * @param  string $class Additional CSS classes
         * @return string HTML for the icon
         */
        public function getIcon(string $icon, string $class = ''): string
        {
            switch ($this->theme) {
                case self::THEME_UIKIT:
                    return "<span uk-icon=\"{$icon}\"" . ($class ? " class=\"{$class}\"" : "") . "></span>";

                case self::THEME_BOOTSTRAP:
                    return "<i class=\"bi bi-{$icon}" . ($class ? " {$class}" : "") . "\"></i>";

                case self::THEME_TAILWIND:
                case self::THEME_PLAIN:
                default:
                    // SVG icons for plain/tailwind themes
                    return $this->getSvgIcon($icon, $class);
            }
        }

        /**
         * Get SVG icon HTML
         *
         * @param  string $icon  Icon name
         * @param  string $class CSS classes
         * @return string SVG HTML
         */
        private function getSvgIcon(string $icon, string $class = ''): string
        {
            $icons = [
                'search' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><circle fill="none" stroke="currentColor" stroke-width="1.1" cx="9" cy="9" r="7"/><path fill="none" stroke="currentColor" stroke-width="1.1" d="M14,14 L18,18 L14,14 Z"/></svg>',
                'plus' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><rect x="9" y="1" width="1" height="17"/><rect x="1" y="9" width="17" height="1"/></svg>',
                'pencil' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="none" stroke="currentColor" d="M17.25,6.01 L7.12,16.14 L3.82,17.68 L5.36,14.38 L15.49,4.25 L17.25,6.01 L17.25,6.01 Z"/><path fill="none" stroke="currentColor" d="M15.98,7.58 L13.92,5.52"/><path fill="none" stroke="currentColor" d="M2,18 L18,18"/></svg>',
                'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><polyline fill="none" stroke="currentColor" points="6.5 3 6.5 1.5 13.5 1.5 13.5 3"/><polyline fill="none" stroke="currentColor" points="3.5 4 3.5 18.5 16.5 18.5 16.5 4"/><rect x="2" y="3" width="16" height="1"/><rect x="8" y="7" width="1" height="9"/><rect x="11" y="7" width="1" height="9"/></svg>',
                'check' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><polyline fill="none" stroke="currentColor" stroke-width="1.1" points="4,10 8,15 17,4"/></svg>',
                'close' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="none" stroke="currentColor" stroke-width="1.06" d="M16,16 L4,4"/><path fill="none" stroke="currentColor" stroke-width="1.06" d="M16,4 L4,16"/></svg>',
                'refresh' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="none" stroke="currentColor" stroke-width="1.1" d="M17.08,11.15 C17.09,11.31 17.1,11.47 17.1,11.64 C17.1,15.53 13.94,18.69 10.05,18.69 C6.16,18.69 3,15.53 3,11.64 C3,7.75 6.16,4.59 10.05,4.59 C10.9,4.59 11.71,4.73 12.46,5"/><polyline fill="none" stroke="currentColor" points="9.9 2 12.79 4.89 9.79 7.9"/></svg>',
                'triangle-up' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><polygon points="5 13 10 8 15 13"/></svg>',
                'triangle-down' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><polygon points="5 7 10 12 15 7"/></svg>',
                'chevron-double-left' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><polyline fill="none" stroke="currentColor" stroke-width="1.03" points="10 14 6 10 10 6"/><polyline fill="none" stroke="currentColor" stroke-width="1.03" points="14 14 10 10 14 6"/></svg>',
                'chevron-double-right' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><polyline fill="none" stroke="currentColor" stroke-width="1.03" points="10 6 14 10 10 14"/><polyline fill="none" stroke="currentColor" stroke-width="1.03" points="6 6 10 10 6 14"/></svg>',
            ];

            $svg = $icons[$icon] ?? $icons['close'];
            $classAttr = $class ? " class=\"{$class}\"" : '';

            return str_replace('<svg', "<svg{$classAttr}", $svg);
        }

        /**
         * Initialize CSS class mappings for all themes
         *
         * @return void
         */
        private function initializeClassMappings(): void
        {
            // Plain theme - uses kp-dt-* classes only
            $this->classMappings[self::THEME_PLAIN] = [
                'container' => 'kp-dt-container',
                'table' => 'kp-dt-table',
                'table.striped' => 'kp-dt-table kp-dt-table-striped',
                'table.hover' => 'kp-dt-table kp-dt-table-hover',
                'table.full' => 'kp-dt-table kp-dt-table-striped kp-dt-table-hover',
                'thead' => '',
                'tbody' => '',
                'tfoot' => '',
                'th' => '',
                'td' => '',
                'th.shrink' => 'kp-dt-table-shrink',
                'input' => 'kp-dt-input',
                'select' => 'kp-dt-select',
                'textarea' => 'kp-dt-textarea',
                'checkbox' => 'kp-dt-checkbox',
                'radio' => 'kp-dt-radio',
                'button' => 'kp-dt-button',
                'button.primary' => 'kp-dt-button kp-dt-button-primary',
                'button.danger' => 'kp-dt-button kp-dt-button-danger',
                'button.small' => 'kp-dt-button kp-dt-button-small',
                'button.default' => 'kp-dt-button',
                'form.stacked' => 'kp-dt-form-stacked',
                'form.label' => 'kp-dt-form-label',
                'form.controls' => 'kp-dt-form-controls',
                'icon.link' => 'kp-dt-icon-link',
                'pagination' => 'kp-dt-pagination',
                'pagination.active' => 'kp-dt-active',
                'pagination.disabled' => 'kp-dt-disabled',
                'modal' => 'kp-dt-modal',
                'modal.dialog' => 'kp-dt-modal-dialog',
                'modal.body' => 'kp-dt-modal-body',
                'modal.title' => 'kp-dt-modal-title',
                'modal.close' => 'kp-dt-modal-close',
                'grid' => 'kp-dt-grid',
                'grid.small' => 'kp-dt-grid kp-dt-grid-small',
                'overflow.auto' => 'kp-dt-overflow-auto',
                'text.center' => 'kp-dt-text-center',
                'text.right' => 'kp-dt-text-right',
                'text.muted' => 'kp-dt-text-muted',
                'text.success' => 'kp-dt-text-success',
                'text.danger' => 'kp-dt-text-danger',
                'margin.top' => 'kp-dt-margin-top',
                'margin.bottom' => 'kp-dt-margin-bottom',
                'margin.small.right' => 'kp-dt-margin-small-right',
                'margin.small.left' => 'kp-dt-margin-small-left',
                'width.auto' => 'kp-dt-width-auto',
                'width.medium' => 'kp-dt-width-medium',
                'width.1-1' => 'kp-dt-width-1-1',
                'inline' => 'kp-dt-inline',
                'form.icon' => 'kp-dt-form-icon',
            ];

            // UIKit theme
            $this->classMappings[self::THEME_UIKIT] = [
                'container' => '',
                'table' => 'uk-table',
                'table.striped' => 'uk-table uk-table-striped',
                'table.hover' => 'uk-table uk-table-hover',
                'table.full' => 'uk-table uk-table-striped uk-table-hover uk-margin-bottom',
                'thead' => '',
                'tbody' => '',
                'tfoot' => '',
                'th' => '',
                'td' => '',
                'th.shrink' => 'uk-table-shrink',
                'input' => 'uk-input',
                'select' => 'uk-select',
                'textarea' => 'uk-textarea',
                'checkbox' => 'uk-checkbox',
                'radio' => 'uk-radio',
                'button' => 'uk-button',
                'button.primary' => 'uk-button uk-button-primary',
                'button.danger' => 'uk-button uk-button-danger',
                'button.small' => 'uk-button uk-button-small',
                'button.default' => 'uk-button uk-button-default',
                'form.stacked' => 'uk-form-stacked',
                'form.label' => 'uk-form-label',
                'form.controls' => 'uk-form-controls',
                'icon.link' => 'uk-icon-link',
                'pagination' => 'uk-pagination',
                'pagination.active' => 'uk-active',
                'pagination.disabled' => 'uk-disabled',
                'modal' => '',
                'modal.dialog' => 'uk-modal-dialog',
                'modal.body' => 'uk-modal-body',
                'modal.title' => 'uk-modal-title',
                'modal.close' => 'uk-modal-close',
                'grid' => '',
                'grid.small' => 'uk-grid-small uk-child-width-auto',
                'overflow.auto' => 'uk-overflow-auto',
                'text.center' => 'uk-text-center',
                'text.right' => 'uk-text-right',
                'text.muted' => 'uk-text-muted',
                'text.success' => 'uk-text-success',
                'text.danger' => 'uk-text-danger',
                'margin.top' => 'uk-margin-top',
                'margin.bottom' => 'uk-margin-bottom',
                'margin.small.right' => 'uk-margin-small-right',
                'margin.small.left' => 'uk-margin-small-left',
                'width.auto' => 'uk-width-auto',
                'width.medium' => 'uk-width-medium',
                'width.1-1' => 'uk-width-1-1',
                'inline' => 'uk-inline',
                'form.icon' => 'uk-form-icon',
            ];

            // Bootstrap theme
            $this->classMappings[self::THEME_BOOTSTRAP] = [
                'container' => 'container',
                'table' => 'table',
                'table.striped' => 'table table-striped',
                'table.hover' => 'table table-hover',
                'table.full' => 'table table-striped table-hover mb-4',
                'thead' => '',
                'tbody' => '',
                'tfoot' => '',
                'th' => '',
                'td' => '',
                'th.shrink' => '',
                'input' => 'form-control',
                'select' => 'form-select',
                'textarea' => 'form-control',
                'checkbox' => 'form-check-input',
                'radio' => 'form-check-input',
                'button' => 'btn',
                'button.primary' => 'btn btn-primary',
                'button.danger' => 'btn btn-danger',
                'button.small' => 'btn btn-sm',
                'button.default' => 'btn btn-secondary',
                'form.stacked' => '',
                'form.label' => 'form-label',
                'form.controls' => 'mb-3',
                'icon.link' => '',
                'pagination' => 'pagination',
                'pagination.active' => 'active',
                'pagination.disabled' => 'disabled',
                'modal' => 'modal fade',
                'modal.dialog' => 'modal-dialog',
                'modal.body' => 'modal-body',
                'modal.title' => 'modal-title',
                'modal.close' => 'btn-close',
                'grid' => 'row',
                'grid.small' => 'row g-2',
                'overflow.auto' => 'overflow-auto',
                'text.center' => 'text-center',
                'text.right' => 'text-end',
                'text.muted' => 'text-muted',
                'text.success' => 'text-success',
                'text.danger' => 'text-danger',
                'margin.top' => 'mt-4',
                'margin.bottom' => 'mb-4',
                'margin.small.right' => 'me-2',
                'margin.small.left' => 'ms-2',
                'width.auto' => 'w-auto',
                'width.medium' => '',
                'width.1-1' => 'w-100',
                'inline' => 'position-relative d-inline-block',
                'form.icon' => 'position-absolute',
            ];

            // Tailwind theme
            $this->classMappings[self::THEME_TAILWIND] = [
                'container' => 'kp-dt-container-tailwind',
                'table' => 'kp-dt-table-tailwind',
                'table.striped' => 'kp-dt-table-tailwind kp-dt-table-striped-tailwind',
                'table.hover' => 'kp-dt-table-tailwind kp-dt-table-hover-tailwind',
                'table.full' => 'kp-dt-table-tailwind kp-dt-table-striped-tailwind kp-dt-table-hover-tailwind mb-5',
                'thead' => '',
                'tbody' => '',
                'tfoot' => '',
                'th' => '',
                'td' => '',
                'th.shrink' => 'w-px whitespace-nowrap',
                'input' => 'kp-dt-input-tailwind',
                'select' => 'kp-dt-select-tailwind',
                'textarea' => 'kp-dt-textarea-tailwind',
                'checkbox' => 'kp-dt-checkbox-tailwind',
                'radio' => 'kp-dt-radio-tailwind',
                'button' => 'kp-dt-button-tailwind',
                'button.primary' => 'kp-dt-button-tailwind kp-dt-button-primary-tailwind',
                'button.danger' => 'kp-dt-button-tailwind kp-dt-button-danger-tailwind',
                'button.small' => 'kp-dt-button-tailwind kp-dt-button-small-tailwind',
                'button.default' => 'kp-dt-button-tailwind',
                'form.stacked' => '',
                'form.label' => 'kp-dt-form-label-tailwind',
                'form.controls' => 'kp-dt-form-controls-tailwind',
                'icon.link' => 'kp-dt-icon-link-tailwind',
                'pagination' => 'kp-dt-pagination-tailwind',
                'pagination.active' => 'kp-dt-active-tailwind',
                'pagination.disabled' => 'kp-dt-disabled-tailwind',
                'modal' => 'kp-dt-modal-tailwind',
                'modal.dialog' => 'kp-dt-modal-dialog-tailwind',
                'modal.body' => 'kp-dt-modal-body-tailwind',
                'modal.title' => 'kp-dt-modal-title-tailwind',
                'modal.close' => 'kp-dt-modal-close-tailwind',
                'grid' => 'flex flex-wrap',
                'grid.small' => 'flex flex-wrap gap-4',
                'overflow.auto' => 'overflow-auto',
                'text.center' => 'text-center',
                'text.right' => 'text-right',
                'text.muted' => 'text-gray-400',
                'text.success' => 'text-green-500',
                'text.danger' => 'text-red-500',
                'margin.top' => 'mt-5',
                'margin.bottom' => 'mb-5',
                'margin.small.right' => 'mr-2',
                'margin.small.left' => 'ml-2',
                'width.auto' => 'w-auto',
                'width.medium' => 'w-72',
                'width.1-1' => 'w-full',
                'inline' => 'inline-block relative',
                'form.icon' => 'kp-dt-search-icon-tailwind',
            ];
        }

        /**
         * Get notification function for the current theme
         *
         * @param  string $message Message to display
         * @param  string $status  Status type (success, danger, warning)
         * @return string JavaScript notification call
         */
        public function getNotificationJs(string $message, string $status = 'success'): string
        {
            switch ($this->theme) {
                case self::THEME_UIKIT:
                    return "UIkit.notification('{$message}', { status: '{$status}' });";

                case self::THEME_BOOTSTRAP:
                    return "KPDataTablesBootstrap.notification('{$message}', '{$status}');";

                case self::THEME_TAILWIND:
                case self::THEME_PLAIN:
                default:
                    return "KPDataTablesPlain.notification('{$message}', '{$status}');";
            }
        }

        /**
         * Get modal show function for the current theme
         *
         * @param  string $modalId Modal element ID
         * @return string JavaScript modal show call
         */
        public function getModalShowJs(string $modalId): string
        {
            switch ($this->theme) {
                case self::THEME_UIKIT:
                    return "UIkit.modal('#{$modalId}').show();";

                case self::THEME_BOOTSTRAP:
                    return "new bootstrap.Modal(document.getElementById('{$modalId}')).show();";

                case self::THEME_TAILWIND:
                case self::THEME_PLAIN:
                default:
                    return "KPDataTablesPlain.showModal('{$modalId}');";
            }
        }

        /**
         * Get modal hide function for the current theme
         *
         * @param  string $modalId Modal element ID
         * @return string JavaScript modal hide call
         */
        public function getModalHideJs(string $modalId): string
        {
            switch ($this->theme) {
                case self::THEME_UIKIT:
                    return "UIkit.modal('#{$modalId}').hide();";

                case self::THEME_BOOTSTRAP:
                    return "bootstrap.Modal.getInstance(document.getElementById('{$modalId}')).hide();";

                case self::THEME_TAILWIND:
                case self::THEME_PLAIN:
                default:
                    return "KPDataTablesPlain.hideModal('{$modalId}');";
            }
        }

        /**
         * Get modal confirm function for the current theme
         *
         * @param  string $message Confirmation message
         * @return string JavaScript confirm modal call pattern
         */
        public function getModalConfirmJs(string $message): string
        {
            switch ($this->theme) {
                case self::THEME_UIKIT:
                    return "UIkit.modal.confirm('{$message}')";

                case self::THEME_BOOTSTRAP:
                case self::THEME_TAILWIND:
                case self::THEME_PLAIN:
                default:
                    return "KPDataTablesPlain.confirm('{$message}')";
            }
        }
    }
}
