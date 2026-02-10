/**
 * Select2-like Component for DataTables
 * 
 * AJAX-powered searchable dropdown without jQuery dependency.
 * Compatible with UIKit, Bootstrap, Tailwind, and Plain themes.
 * 
 * @since   1.2.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

class KPTSelect2 {
    constructor(element, config = {}) {
        this.element = element;
        this.config = {
            placeholder: config.placeholder || 'Select...',
            query: config.query || '',
            minSearchChars: parseInt(config.minSearchChars) || 0,
            maxResults: parseInt(config.maxResults) || 50,
            theme: config.theme || 'uikit',
            recordData: config.recordData || {},
            ...config
        };

        this.isOpen = false;
        this.selectedValue = element.value || '';
        this.selectedLabel = '';
        this.searchTimeout = null;
        this.debounceDelay = 300;

        this.init();
    }

    init() {
        // Hide original select
        this.element.style.display = 'none';

        // Create custom dropdown structure
        this.createDropdown();
        this.bindEvents();

        // Store instance on element for later access
        this.element.kptSelect2Instance = this;

        // Load initial value if set
        if (this.selectedValue) {
            this.loadInitialValue();
        }
    }

    createDropdown() {
        // Main container
        this.container = document.createElement('div');
        this.container.className = this.getContainerClass();

        // Selected display
        this.display = document.createElement('div');
        this.display.className = this.getDisplayClass();
        this.display.textContent = this.config.placeholder;
        this.display.setAttribute('tabindex', '0');

        // Dropdown arrow
        this.arrow = document.createElement('span');
        this.arrow.className = this.getArrowClass();
        this.arrow.innerHTML = this.getArrowIcon();

        // Dropdown panel
        this.dropdown = document.createElement('div');
        this.dropdown.className = this.getDropdownClass();
        this.dropdown.style.display = 'none';

        // Stop click propagation to prevent triggering parent events
        this.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Search input
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = this.getSearchInputClass();
        this.searchInput.placeholder = 'Search...';
        this.searchInput.setAttribute('autocomplete', 'off');
        this.searchInput.setAttribute('autocorrect', 'off');
        this.searchInput.setAttribute('autocapitalize', 'off');
        this.searchInput.setAttribute('spellcheck', 'false');

        // Results container
        this.resultsContainer = document.createElement('div');
        this.resultsContainer.className = this.getResultsClass();

        // Assemble dropdown
        this.dropdown.appendChild(this.searchInput);
        this.dropdown.appendChild(this.resultsContainer);

        this.container.appendChild(this.display);
        this.container.appendChild(this.arrow);
        this.container.appendChild(this.dropdown);

        // Insert container after original element
        this.element.parentNode.insertBefore(this.container, this.element.nextSibling);

        // Insert container after original element
        this.element.parentNode.insertBefore(this.container, this.element.nextSibling);

        // Append to closest modal body for all themes to allow keyboard input
        const modalBody = this.element.closest('.uk-modal-body, .uk-modal-dialog, .modal-body, .kp-dt-modal-body');
        if (modalBody) {
            modalBody.appendChild(this.dropdown);
        } else {
            document.body.appendChild(this.dropdown);
        }
    }

    bindEvents() {
        // Toggle dropdown on display click
        this.display.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.toggle();
        });

        // Toggle on arrow click
        this.arrow.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.toggle();
        });

        // Keyboard navigation on display
        this.display.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.toggle();
            }
        });

        // Search input events
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        });

        // Close on outside click - add delay to avoid immediate close
        setTimeout(() => {
            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target) && !this.dropdown.contains(e.target)) {
                    this.close();
                }
            });
        }, 100);
    }

    toggle() {

        // Close all other select2 dropdowns first
        document.querySelectorAll('select[data-select2]').forEach(el => {
            if (el !== this.element && el.kptSelect2Instance) {
                el.kptSelect2Instance.close();
            }
        });

        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {

        // Close all other select2 dropdowns first
        document.querySelectorAll('select[data-select2]').forEach(el => {
            if (el !== this.element && el.kptSelect2Instance) {
                el.kptSelect2Instance.close();
            }
        });

        this.isOpen = true;
        this.dropdown.style.display = 'block';
        this.display.classList.add('kp-select2-open');

        const rect = this.container.getBoundingClientRect();

        // Check if dropdown is in modal (relative positioning) or body (fixed positioning)
        const inModal = this.dropdown.parentElement.closest('.uk-modal-body, .uk-modal-dialog, .modal-body, .kp-dt-modal-body, .kp-dt-modal-body-tailwind') !== null;

        if (inModal) {
            // Relative positioning within modal
            const parentRect = this.dropdown.parentElement.getBoundingClientRect();
            this.dropdown.style.position = 'absolute';
            this.dropdown.style.top = `${rect.bottom - parentRect.top}px`;
            this.dropdown.style.left = `${rect.left - parentRect.left}px`;
        } else {
            // Fixed positioning to body
            this.dropdown.style.position = 'fixed';
            this.dropdown.style.top = `${rect.bottom}px`;
            this.dropdown.style.left = `${rect.left}px`;
        }

        this.dropdown.style.width = `${rect.width}px`;

        // Force focus on search input with delay to override modal focus
        setTimeout(() => {
            this.searchInput.focus();
            this.searchInput.click();
        }, 50);

        // Always load initial results when opening
        this.loadResults('');
    }

    close() {
        if (!this.isOpen) {
            return;
        }

        // Check if value changed before closing
        const valueChanged = this.element.value !== this.selectedValue;

        this.isOpen = false;
        this.dropdown.style.display = 'none';
        this.display.classList.remove('kp-select2-open');
        this.searchInput.value = '';
        this.resultsContainer.innerHTML = '';

        // Trigger change event if value changed
        if (valueChanged && this.selectedValue) {
            this.element.value = this.selectedValue;
            const event = new Event('change', { bubbles: true });
            this.element.dispatchEvent(event);
        }
    }

    handleSearch(searchTerm) {
        clearTimeout(this.searchTimeout);

        // Check minimum characters only if search term is not empty
        if (searchTerm.length > 0 && searchTerm.length < this.config.minSearchChars) {
            this.resultsContainer.innerHTML = `<div class="${this.getNoResultsClass()}">Type ${this.config.minSearchChars} or more characters...</div>`;
            return;
        }

        // Debounce search
        this.searchTimeout = setTimeout(() => {
            this.loadResults(searchTerm, false);
        }, this.debounceDelay);
    }

    loadResults(searchTerm) {
        const formData = new FormData();
        formData.append('action', 'fetch_select2_options');
        formData.append('query', this.config.query);
        formData.append('search', searchTerm);
        formData.append('max_results', this.config.maxResults);

        // Add record data for query parameter substitution
        formData.append('record_data', JSON.stringify(this.config.recordData));

        // Show loading state
        this.resultsContainer.innerHTML = `<div class="${this.getLoadingClass()}">Loading...</div>`;

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderResults(data.results);
                } else {
                    this.resultsContainer.innerHTML = `<div class="${this.getErrorClass()}">${data.message || 'Error loading results'}</div>`;
                }
            })
            .catch(error => {
                console.error('Select2 error:', error);
                this.resultsContainer.innerHTML = `<div class="${this.getErrorClass()}">Error loading results</div>`;
            });
    }

    renderResults(results) {
        if (!results || results.length === 0) {
            this.resultsContainer.innerHTML = `<div class="${this.getNoResultsClass()}">No results found</div>`;
            return;
        }

        this.resultsContainer.innerHTML = '';

        results.forEach(result => {
            const item = document.createElement('div');
            item.className = this.getResultItemClass();
            item.textContent = result.Label;
            item.setAttribute('data-value', result.ID);

            // Highlight if currently selected
            if (result.ID == this.selectedValue) {
                item.classList.add('kp-select2-selected');
            }

            item.addEventListener('click', () => {
                this.selectItem(result.ID, result.Label);
            });

            this.resultsContainer.appendChild(item);
        });
    }

    selectItem(value, label) {
        this.selectedValue = value;
        this.selectedLabel = label;

        // Clear existing options and add the selected one
        this.element.innerHTML = `<option value="${value}" selected>${label}</option>`;
        this.element.value = value;

        // Remove required validation error
        this.element.setCustomValidity('');

        // Trigger change event
        const event = new Event('change', { bubbles: true });
        this.element.dispatchEvent(event);

        // Update display
        this.display.textContent = label;
        this.display.classList.add('kp-select2-has-value');

        // Close dropdown
        this.close();
    }

    loadInitialValue() {

        const formData = new FormData();
        formData.append('action', 'fetch_select2_options');
        formData.append('query', this.config.query);
        formData.append('search', '');
        formData.append('max_results', 1);
        formData.append('value_filter', this.selectedValue);
        formData.append('record_data', JSON.stringify(this.config.recordData));

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results.length > 0) {
                    this.selectedLabel = data.results[0].Label;
                    this.display.textContent = this.selectedLabel;
                    this.display.classList.add('kp-select2-has-value');
                }
            })
            .catch(error => {
                console.error('Select2 initial value error:', error);
            });
    }

    // Theme-specific class getters
    getContainerClass() {
        const base = 'kp-select2-container';
        return `${base} ${base}-${this.config.theme}`;
    }

    getDisplayClass() {
        const base = 'kp-select2-display';
        return `${base} ${base}-${this.config.theme}`;
    }

    getArrowClass() {
        const base = 'kp-select2-arrow';
        return `${base} ${base}-${this.config.theme}`;
    }

    getDropdownClass() {
        const base = 'kp-select2-dropdown';
        return `${base} ${base}-${this.config.theme}`;
    }

    getSearchInputClass() {
        const base = 'kp-select2-search';
        return `${base} ${base}-${this.config.theme}`;
    }

    getResultsClass() {
        const base = 'kp-select2-results';
        return `${base} ${base}-${this.config.theme}`;
    }

    getResultItemClass() {
        const base = 'kp-select2-result-item';
        return `${base} ${base}-${this.config.theme}`;
    }

    getLoadingClass() {
        return `kp-select2-loading kp-select2-loading-${this.config.theme}`;
    }

    getErrorClass() {
        return `kp-select2-error kp-select2-error-${this.config.theme}`;
    }

    getNoResultsClass() {
        return `kp-select2-no-results kp-select2-no-results-${this.config.theme}`;
    }

    getArrowIcon() {
        if (this.config.theme === 'bootstrap') {
            return '<i class="bi bi-chevron-down"></i>';
        } else {
            // SVG chevron for other themes
            return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><polyline points="5 7 10 12 15 7" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>';
        }
    }

    destroy() {
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        if (this.dropdown && this.dropdown.parentNode) {
            this.dropdown.parentNode.removeChild(this.dropdown);
        }
        this.element.style.display = '';
        delete this.element.kptSelect2Instance;
    }
}

// Auto-initialize all select2 elements
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-select2]').forEach(element => {
        // Skip if already initialized
        if (element.kptSelect2Instance) {
            return;
        }

        const config = {
            placeholder: element.getAttribute('data-placeholder') || 'Select...',
            query: element.getAttribute('data-query') || '',
            minSearchChars: element.getAttribute('data-min-search-chars') || 0,
            maxResults: element.getAttribute('data-max-results') || 50,
            theme: element.getAttribute('data-theme') || 'uikit',
            recordData: {}
        };

        // Parse record data if available
        const recordDataAttr = element.getAttribute('data-record-data');
        if (recordDataAttr) {
            try {
                config.recordData = JSON.parse(recordDataAttr);
            } catch (e) {
                console.error('Failed to parse record data:', e);
            }
        }

        new KPTSelect2(element, config);
    });
});

// Make class globally available
window.KPTSelect2 = KPTSelect2;