/**
 * KPTV Stream Manager - Main JavaScript
 * Mobile-First UIKit3 Dashboard
 */
// actual dom ready
var DOMReady = function (callback) {
    if (document.readyState === "interactive" || document.readyState === "complete") {
        callback();
    } else if (document.addEventListener) {
        document.addEventListener("DOMContentLoaded", callback);
    } else if (document.attachEvent) {
        document.attachEvent("onreadystatechange", function () {
            if (document.readyState != "loading") {
                callback();
            }
        });
    }
};

// ============================================
// KPTV Namespace
// ============================================
const KPTV = {
    init: function () {
        this.initScrollToTop();
        this.initMobileMenu();
        this.initTableSort();
        this.initSearch();
        this.initPerPage();
        console.log('KPTV Stream Manager initialized');
    },

    // ============================================
    // Scroll to Top Button
    // ============================================
    initScrollToTop: function () {
        const scrollBtn = document.getElementById('kptv-scroll-top');
        if (!scrollBtn) return;

        const showScrollBtn = function () {
            if (window.scrollY > 150) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        };

        window.addEventListener('scroll', showScrollBtn, { passive: true });

        scrollBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    },

    // ============================================
    // Mobile Menu Handling
    // ============================================
    initMobileMenu: function () {
        // Close offcanvas when clicking a link (for SPAs)
        const offcanvasLinks = document.querySelectorAll('.kptv-offcanvas-nav a:not(.uk-parent > a)');
        offcanvasLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                const offcanvas = document.getElementById('kptv-mobile-nav');
                if (offcanvas && typeof UIkit !== 'undefined') {
                    UIkit.offcanvas(offcanvas).hide();
                }
            });
        });

        // Handle parent items in mobile nav
        const parentItems = document.querySelectorAll('.kptv-offcanvas-nav .uk-parent > a');
        parentItems.forEach(function (item) {
            item.addEventListener('click', function (e) {
                // Let UIkit handle the accordion behavior
            });
        });
    },

    // ============================================
    // Table Sorting
    // ============================================
    initTableSort: function () {
        const sortableHeaders = document.querySelectorAll('.kptv-table th[data-sort]');

        sortableHeaders.forEach(function (header) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function () {
                const table = this.closest('table');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const column = this.dataset.sort;
                const columnIndex = Array.from(this.parentElement.children).indexOf(this);
                const isAsc = this.classList.contains('asc');

                // Reset all headers
                sortableHeaders.forEach(function (h) {
                    h.classList.remove('asc', 'desc');
                });

                // Sort rows
                rows.sort(function (a, b) {
                    const aVal = a.children[columnIndex].textContent.trim();
                    const bVal = b.children[columnIndex].textContent.trim();

                    // Check if numeric
                    const aNum = parseFloat(aVal);
                    const bNum = parseFloat(bVal);

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAsc ? bNum - aNum : aNum - bNum;
                    }

                    return isAsc
                        ? bVal.localeCompare(aVal)
                        : aVal.localeCompare(bVal);
                });

                // Update class
                this.classList.add(isAsc ? 'desc' : 'asc');

                // Re-append rows
                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    },

    // ============================================
    // Live Search
    // ============================================
    initSearch: function () {
        const searchInputs = document.querySelectorAll('.kptv-search input');

        searchInputs.forEach(function (input) {
            let debounceTimer;

            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const searchValue = this.value.toLowerCase();
                const targetTable = document.querySelector(this.dataset.target || '.kptv-table');

                debounceTimer = setTimeout(function () {
                    if (!targetTable) return;

                    const rows = targetTable.querySelectorAll('tbody tr');

                    rows.forEach(function (row) {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchValue) ? '' : 'none';
                    });

                    // Update visible count if exists
                    KPTV.updateVisibleCount(targetTable);
                }, 300);
            });
        });
    },

    // ============================================
    // Per Page Selector
    // ============================================
    initPerPage: function () {
        const perPageBtns = document.querySelectorAll('.kptv-per-page-btn');

        perPageBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                // Update active state
                perPageBtns.forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');

                const perPage = this.dataset.perpage;
                const targetTable = document.querySelector(this.dataset.target || '.kptv-table');

                if (!targetTable) return;

                const rows = targetTable.querySelectorAll('tbody tr');
                const showAll = perPage === 'all';
                const limit = parseInt(perPage, 10);

                rows.forEach(function (row, index) {
                    if (showAll || index < limit) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                KPTV.updateVisibleCount(targetTable);
            });
        });
    },

    // ============================================
    // Update Visible Count Display
    // ============================================
    updateVisibleCount: function (table) {
        const countDisplay = document.querySelector('.kptv-record-count');
        if (!countDisplay || !table) return;

        const totalRows = table.querySelectorAll('tbody tr').length;
        const visibleRows = table.querySelectorAll('tbody tr:not([style*="display: none"])').length;

        countDisplay.textContent = 'Showing ' + visibleRows + ' of ' + totalRows + ' records';
    },

    // ============================================
    // Utility: Format Number
    // ============================================
    formatNumber: function (num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    // ============================================
    // Utility: Debounce Function
    // ============================================
    debounce: function (func, wait) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function () {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

function MyInit() {

    // --- Event Delegation for Move Streams Handler ---
    const staticParent = document.querySelector('.the-datatable');

    // Global active toggle handler - works for all pages
    document.addEventListener('click', function (e) {
        const activeToggle = e.target.closest('.active-toggle');
        if (activeToggle) {
            e.preventDefault();
            e.stopPropagation();

            const id = activeToggle.dataset.id;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'toggle-active';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });

    // Stream channel click-to-edit functionality
    document.addEventListener('click', function (e) {

        // Check for stream channel cell click
        const channelCell = e.target.closest('.stream-channel.channel-cell');
        if (channelCell && !channelCell.querySelector('input')) {
            e.stopPropagation();

            const cell = channelCell;
            const currentValue = cell.textContent.trim();
            const row = cell.closest('tr');
            const streamId = row.querySelector('.record-checkbox').value;

            // Create input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue;
            input.className = 'uk-input uk-form-small';

            // Clear cell and add input
            cell.textContent = '';
            cell.appendChild(input);
            input.focus();

            // Handle Enter key to save
            const handleKeyDown = function (e) {
                if (e.key === 'Enter') {
                    saveChannelChange(streamId, input.value.trim(), cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                } else if (e.key === 'Escape') {
                    revertChannelCell(cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                }
            };

            // Handle blur (click outside) to save
            const handleBlur = function () {
                saveChannelChange(streamId, input.value.trim(), cell, currentValue);
                input.removeEventListener('keydown', handleKeyDown);
                input.removeEventListener('blur', handleBlur);
            };

            input.addEventListener('keydown', handleKeyDown);
            input.addEventListener('blur', handleBlur);
        }
    });

    // Stream name click-to-edit functionality
    document.addEventListener('click', function (e) {

        // Check for stream name cell click
        const nameCell = e.target.closest('.stream-name.name-cell');
        if (nameCell && !nameCell.querySelector('input')) {
            e.stopPropagation();

            const cell = nameCell;
            const currentValue = cell.textContent.trim();
            const row = cell.closest('tr');
            const streamId = row.querySelector('.record-checkbox').value;

            // Create input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue;
            input.className = 'uk-input uk-form-small';

            // Clear cell and add input
            cell.textContent = '';
            cell.appendChild(input);
            input.focus();

            // Handle Enter key to save
            const handleKeyDown = function (e) {
                if (e.key === 'Enter') {
                    saveNameChange(streamId, input.value.trim(), cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                } else if (e.key === 'Escape') {
                    revertCell(cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                }
            };

            // Handle blur (click outside) to save
            const handleBlur = function () {
                saveNameChange(streamId, input.value.trim(), cell, currentValue);
                input.removeEventListener('keydown', handleKeyDown);
                input.removeEventListener('blur', handleBlur);
            };

            input.addEventListener('keydown', handleKeyDown);
            input.addEventListener('blur', handleBlur);
        }
    });

    // Activate stream clicker
    document.querySelectorAll('.activate-streams').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one stream to activate.');
                return;
            }

            if (!confirm(`Activate ${checkedBoxes.length} selected stream(s)?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'activate-streams';
            form.appendChild(actionInput);

            const urlParams = new URLSearchParams(window.location.search);
            const sortInput = document.createElement('input');
            sortInput.type = 'hidden';
            sortInput.name = 'sort';
            sortInput.value = urlParams.get('sort') || 'sp_priority';
            form.appendChild(sortInput);

            const dirInput = document.createElement('input');
            dirInput.type = 'hidden';
            dirInput.name = 'dir';
            dirInput.value = urlParams.get('dir') || 'asc';
            form.appendChild(dirInput);

            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    });

    // Row selection functionality
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        const cells = Array.from(row.cells).slice(0, -1);
        const checkbox = row.querySelector('.record-checkbox');

        cells.forEach(cell => {
            // Skip cells that have their own click handlers
            if (cell.querySelector('.active-toggle') ||
                cell.classList.contains('stream-name') ||
                cell.classList.contains('stream-channel') ||
                cell.querySelector('.copy-link')) {
                return; // Don't add checkbox toggle to these cells
            }

            cell.style.cursor = 'pointer';
            cell.addEventListener('click', (e) => {
                if (e.target.tagName === 'A' ||
                    e.target.tagName === 'BUTTON' ||
                    e.target.tagName === 'INPUT' ||
                    e.target.classList.contains('copy-link')) return;
                if (e.target === checkbox) return;

                checkbox.checked = !checkbox.checked;
                const event = new Event('change');
                checkbox.dispatchEvent(event);
            });
        });
    });

    // Checkbox management - updated to handle multiple select-all checkboxes
    const selectAllCheckboxes = document.querySelectorAll('.select-all');
    const checkboxes = document.querySelectorAll('.record-checkbox');
    const deleteSelectedBtn = document.getElementById('delete-selected');

    // Function to update all select-all checkboxes
    function updateSelectAllCheckboxes() {
        const allChecked = checkboxes.length > 0 && [...checkboxes].every(cb => cb.checked);
        selectAllCheckboxes.forEach(checkbox => {
            checkbox.checked = allChecked;
            checkbox.indeterminate = !allChecked && [...checkboxes].some(cb => cb.checked);
        });
    }

    // Add change event to all select-all checkboxes
    selectAllCheckboxes.forEach(selectAll => {
        selectAll.addEventListener('change', function () {
            const isChecked = this.checked;
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateDeleteButtonState();
        });
    });

    // Add change event to all record checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            updateSelectAllCheckboxes();
            updateDeleteButtonState();
        });
    });

    function updateDeleteButtonState() {
        if (!deleteSelectedBtn) return;
        const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
        deleteSelectedBtn.disabled = checkedBoxes.length === 0;
    }

    // Initialize the state
    updateSelectAllCheckboxes();
    updateDeleteButtonState();

    // Delete selected items
    document.querySelectorAll('.delete-selected').forEach(button => {
        button.addEventListener('click', function () {
            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one item to delete.');
                return;
            }

            if (!confirm(`Delete ${checkedBoxes.length} selected item(s)?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'delete-multiple';
            form.appendChild(actionInput);

            const urlParams = new URLSearchParams(window.location.search);
            const sortInput = document.createElement('input');
            sortInput.type = 'hidden';
            sortInput.name = 'sort';
            sortInput.value = urlParams.get('sort') || 'sp_priority';
            form.appendChild(sortInput);

            const dirInput = document.createElement('input');
            dirInput.type = 'hidden';
            dirInput.name = 'dir';
            dirInput.value = urlParams.get('dir') || 'asc';
            form.appendChild(dirInput);

            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    });

    function revertCell(cell, originalValue) {
        cell.textContent = originalValue;
        cell.classList.add('stream-name');
        cell.classList.add('name-cell');
    }

    function revertChannelCell(cell, originalValue) {
        cell.textContent = originalValue;
        cell.classList.add('stream-channel');
        cell.classList.add('channel-cell');
    }

    function saveNameChange(streamId, newName, cell, originalValue) {
        if (!newName || newName === originalValue) {
            revertCell(cell, originalValue);
            return;
        }

        // Show loading state
        const spinner = document.createElement('span');
        spinner.setAttribute('uk-spinner', 'ratio: 0.5');
        cell.textContent = '';
        cell.appendChild(spinner);

        // Prepare form data
        const formData = new FormData();
        formData.append('form_action', 'update-name');
        formData.append('id', streamId);
        formData.append('s_name', newName);

        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();  // ← Fixed: Return the promise directly
            })
            .then(text => {  // ← Fixed: Handle text in separate .then()
                return text ? JSON.parse(text) : {};
            })
            .then(data => {
                if (data.success) {
                    cell.textContent = newName;
                    cell.classList.add('stream-name');
                    cell.classList.add('name-cell');

                    // Show success notification
                    if (typeof UIkit !== 'undefined' && UIkit.notification) {
                        UIkit.notification({
                            message: 'Name updated successfully',
                            status: 'success',
                            pos: 'top-right',
                            timeout: 2000
                        });
                    }
                } else {
                    throw new Error(data.message || 'Update failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                revertCell(cell, originalValue);

                // Show error notification
                if (typeof UIkit !== 'undefined' && UIkit.notification) {
                    UIkit.notification({
                        message: 'Error saving: ' + error.message,
                        status: 'danger',
                        pos: 'top-right',
                        timeout: 5000
                    });
                }
            });
    }

    function saveChannelChange(streamId, newChannel, cell, originalValue) {
        if (!newChannel || newChannel === originalValue) {
            revertChannelCell(cell, originalValue);
            return;
        }

        // Show loading state
        const spinner = document.createElement('span');
        spinner.setAttribute('uk-spinner', 'ratio: 0.5');
        cell.textContent = '';
        cell.appendChild(spinner);

        // Prepare form data
        const formData = new FormData();
        formData.append('form_action', 'update-channel');
        formData.append('id', streamId);
        formData.append('s_channel', newChannel);

        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();  // ← Fixed
            })
            .then(text => {  // ← Fixed
                return text ? JSON.parse(text) : {};
            })
            .then(data => {
                if (data.success) {
                    cell.textContent = newChannel;
                    cell.classList.add('stream-channel');
                    cell.classList.add('channel-cell');

                    // Show success notification
                    if (typeof UIkit !== 'undefined' && UIkit.notification) {
                        UIkit.notification({
                            message: 'Channel updated successfully',
                            status: 'success',
                            pos: 'top-right',
                            timeout: 2000
                        });
                    }
                } else {
                    throw new Error(data.message || 'Update failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                revertChannelCell(cell, originalValue);

                // Show error notification
                if (typeof UIkit !== 'undefined' && UIkit.notification) {
                    UIkit.notification({
                        message: 'Error saving channel: ' + error.message,
                        status: 'danger',
                        pos: 'top-right',
                        timeout: 5000
                    });
                }
            });
    }

    // Add click event listeners to all elements with class 'copy-link'
    document.addEventListener('click', function (e) {
        const copyLink = e.target.closest('.copy-link');
        if (copyLink) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const href = copyLink.getAttribute('href');
            //console.log("Copied Link:" + href);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(href).then(function () {
                    UIkit.notification({
                        message: 'Your data has been copied to your clipboard!',
                        status: 'success',
                        pos: 'top-center',
                        timeout: 5000
                    });
                }).catch(function (err) {
                    UIkit.notification({
                        message: 'Failed to copy: ' + err,
                        status: 'danger',
                        pos: 'top-center',
                        timeout: 5000
                    });
                });
            } else {
                const tempInput = document.createElement('input');
                document.body.appendChild(tempInput);
                tempInput.value = href;
                tempInput.select();

                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        UIkit.notification({
                            message: 'Your data has been copied to your clipboard!',
                            status: 'success',
                            pos: 'top-center',
                            timeout: 5000
                        });
                    } else {
                        throw new Error('Copy command failed');
                    }
                } catch (err) {
                    UIkit.notification({
                        message: 'Failed to copy: ' + err,
                        status: 'danger',
                        pos: 'top-center',
                        timeout: 5000
                    });
                }
                document.body.removeChild(tempInput);
            }
        }
    });

}

// ============================================
// Initialize on DOM Ready
// ============================================
DOMReady(function () {
    KPTV.init();
    MyInit();
});
