document.addEventListener('DOMContentLoaded', function () {
    var reportsChart = document.querySelector('.reports-chart-svg');
    if (reportsChart) {
        var reportsChartMobile = window.matchMedia('(max-width: 720px)');
        var updateReportsChartAspect = function () {
            var isMobile = reportsChartMobile.matches;
            reportsChart.setAttribute('preserveAspectRatio', isMobile ? 'none' : 'xMidYMid meet');
            reportsChart.querySelectorAll('.chart-tooltip-position').forEach(function (position) {
                var desktopX = Number(position.getAttribute('data-desktop-x') || 0);
                var centerX = Number(position.getAttribute('data-center-x') || 0);
                var y = Number(position.getAttribute('data-tooltip-y') || 0);
                var x = isMobile ? centerX - 92 : desktopX;
                position.setAttribute('transform', 'translate(' + x + ' ' + y + ')');
            });
        };

        updateReportsChartAspect();
        if (typeof reportsChartMobile.addEventListener === 'function') {
            reportsChartMobile.addEventListener('change', updateReportsChartAspect);
        } else {
            reportsChartMobile.addListener(updateReportsChartAspect);
        }

        var reportMonths = reportsChart.querySelectorAll('.chart-hover-month');
        var closeReportMonthTooltips = function (except) {
            reportMonths.forEach(function (month) {
                if (month !== except) {
                    month.classList.remove('is-active');
                }
            });
        };

        reportMonths.forEach(function (month) {
            month.addEventListener('click', function (event) {
                event.stopPropagation();
                var willOpen = !month.classList.contains('is-active');
                closeReportMonthTooltips(month);
                month.classList.toggle('is-active', willOpen);
            });
            month.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    month.dispatchEvent(new MouseEvent('click', { bubbles: true }));
                }
            });
        });

        document.addEventListener('click', function () {
            closeReportMonthTooltips(null);
        });
    }

    var sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    var sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    document.querySelectorAll('.nav-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    function bindDropdownToggles(root) {
        (root || document).querySelectorAll('[data-dropdown-toggle]:not([data-dropdown-bound])').forEach(function (toggle) {
            toggle.setAttribute('data-dropdown-bound', 'true');
            toggle.addEventListener('click', function () {
                var dropdown = toggle.closest('.dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('is-open');
                }
            });
        });
    }

    bindDropdownToggles(document);

    document.querySelectorAll('[data-dropdown-toggle]').forEach(function (toggle) {
        if (toggle.hasAttribute('data-dropdown-bound') || toggle.hasAttribute('data-dropdown-bound-legacy')) {
            return;
        }

        toggle.setAttribute('data-dropdown-bound-legacy', 'true');
        toggle.addEventListener('click', function () {
            var dropdown = toggle.closest('.dropdown');
            if (dropdown) {
                dropdown.classList.toggle('is-open');
            }
        });
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.dropdown.is-open').forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('is-open');
            }
        });
    });

    document.querySelectorAll('.notification-menu').forEach(function (menu) {
        var button = menu.querySelector('.notification-button');
        var closeTimer = null;

        if (!button) {
            return;
        }

        function cancelClose() {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        }

        function closeNotifications() {
            cancelClose();
            menu.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
        }

        function openNotifications() {
            cancelClose();
            menu.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
        }

        function scheduleClose() {
            cancelClose();
            closeTimer = window.setTimeout(closeNotifications, 1000);
        }

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (menu.classList.contains('is-open')) {
                closeNotifications();
            } else {
                openNotifications();
            }
        });

        menu.addEventListener('mouseenter', cancelClose);
        menu.addEventListener('mouseleave', scheduleClose);
        menu.addEventListener('focusin', cancelClose);
        menu.addEventListener('focusout', function (event) {
            if (!menu.contains(event.relatedTarget)) {
                scheduleClose();
            }
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                closeNotifications();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNotifications();
            }
        });
    });

    function bindRowLinks(root) {
        (root || document).querySelectorAll('[data-row-link]:not([data-row-link-bound])').forEach(function (row) {
            row.setAttribute('data-row-link-bound', 'true');
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, .dropdown')) {
                    return;
                }

                window.location.href = row.getAttribute('data-row-link');
            });

            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    window.location.href = row.getAttribute('data-row-link');
                }
            });
        });
    }

    bindRowLinks(document);

    document.querySelectorAll('[data-subscription-sortable]').forEach(function (list) {
        var draggedRow = null;
        var orderForm = document.querySelector('[data-subscription-order-form]');
        var orderFields = orderForm ? orderForm.querySelector('[data-subscription-order-fields]') : null;

        function rows() {
            return Array.prototype.slice.call(list.querySelectorAll('[data-subscription-sort-row]'));
        }

        function updateSortInputs() {
            rows().forEach(function (row, index) {
                var input = row.querySelector('[data-subscription-sort-input]');
                if (input) {
                    input.value = String((index + 1) * 10);
                }
            });
        }

        function submitOrder() {
            if (!orderForm || !orderFields) {
                return;
            }

            orderFields.innerHTML = '';
            rows().forEach(function (row) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'subscription_order[]';
                input.value = row.getAttribute('data-subscription-id') || '';
                orderFields.appendChild(input);
            });

            orderForm.submit();
        }

        function rowAfterPointer(y) {
            return rows().filter(function (row) {
                return row !== draggedRow;
            }).reduce(function (closest, row) {
                var box = row.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;

                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, row: row };
                }

                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, row: null }).row;
        }

        rows().forEach(function (row) {
            row.addEventListener('dragstart', function (event) {
                if (!event.target.closest('.settings-sort-handle')) {
                    event.preventDefault();
                    return;
                }

                draggedRow = row;
                row.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
            });

            row.addEventListener('dragend', function () {
                row.classList.remove('is-dragging');
                draggedRow = null;
                updateSortInputs();
            });
        });

        list.addEventListener('dragover', function (event) {
            if (!draggedRow) {
                return;
            }

            event.preventDefault();
            var afterRow = rowAfterPointer(event.clientY);

            if (afterRow) {
                list.insertBefore(draggedRow, afterRow);
            } else {
                list.appendChild(draggedRow);
            }

            updateSortInputs();
        });

        list.addEventListener('drop', function (event) {
            if (!draggedRow) {
                return;
            }

            event.preventDefault();
            updateSortInputs();
            submitOrder();
        });

        updateSortInputs();
    });

    document.querySelectorAll('[data-row-link]').forEach(function (row) {
        if (row.hasAttribute('data-row-link-bound') || row.hasAttribute('data-row-link-bound-legacy')) {
            return;
        }

        row.setAttribute('data-row-link-bound-legacy', 'true');
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, textarea, .dropdown')) {
                return;
            }

            window.location.href = row.getAttribute('data-row-link');
        });

        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                window.location.href = row.getAttribute('data-row-link');
            }
        });
    });

    document.querySelectorAll('[data-tabs]').forEach(function (tabsRoot) {
        var tabs = tabsRoot.querySelectorAll('[data-tab-target]');
        var panels = tabsRoot.querySelectorAll('[data-tab-panel]');
        var action = tabsRoot.querySelector('[data-tab-action]');

        function updateTabAction(target) {
            if (!action) {
                return;
            }

            var href = action.getAttribute('data-action-' + target + '-href');
            var label = action.getAttribute('data-action-' + target + '-label');
            var labelNode = action.querySelector('span');

            if (href) {
                action.setAttribute('href', href);
            }

            if (labelNode && label) {
                labelNode.textContent = label;
            }
        }

        function activateTab(target, updateHash) {
            var activated = false;

            tabs.forEach(function (item) {
                var isActive = item.getAttribute('data-tab-target') === target;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                activated = activated || isActive;
            });

            if (!activated) {
                return;
            }

            panels.forEach(function (panel) {
                var isActive = panel.getAttribute('data-tab-panel') === target;
                panel.classList.toggle('is-active', isActive);
                panel.hidden = !isActive;
            });

            updateTabAction(target);

            if (updateHash) {
                history.replaceState(null, '', '#' + target);
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-tab-target');
                activateTab(target, true);
            });
        });

        tabsRoot.querySelectorAll('[data-open-tab]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                activateTab(link.getAttribute('data-open-tab'), true);
            });
        });

        if (action) {
            action.addEventListener('click', function (event) {
                var href = action.getAttribute('href') || '';
                if (href.charAt(0) !== '#') {
                    return;
                }

                event.preventDefault();
                activateTab(href.slice(1), true);
            });
        }

        var initialHash = window.location.hash.replace('#', '');
        if (initialHash) {
            activateTab(initialHash, false);
        } else {
            var activeTab = tabsRoot.querySelector('[data-tab-target].is-active');
            if (activeTab) {
                updateTabAction(activeTab.getAttribute('data-tab-target'));
            }
        }
    });

    var filterStoragePrefix = 'limodelia.filters.';
    var filterFocusKey = 'limodelia.filters.focus';
    var ignoredFilterKeys = [
        'modal',
        'id',
        'action',
        'saved',
        'error',
        'attendance_modal',
        'attendance_date',
        'attendance_saved',
        'highlight_expense',
        'gallery_trip_id',
        'period_month'
    ];

    function isIgnoredFilterKey(key) {
        return ignoredFilterKeys.indexOf(key) !== -1;
    }

    function isPersistentFilterForm(form) {
        if (!form || String(form.method || '').toLowerCase() !== 'get') {
            return false;
        }

        if (form.hasAttribute('data-expenses-direct-filter')) {
            return false;
        }

        return form.hasAttribute('data-auto-filter')
            || form.classList.contains('filter-bar')
            || form.classList.contains('payments-filter-bar')
            || form.classList.contains('trips-filter-bar')
            || form.querySelector('input[type="search"], select[name="status"], input[name="month"], input[name="from"], input[name="to"]');
    }

    function storageKeyForFilterForm(form, index) {
        var customKey = form.getAttribute('data-filter-key');
        var formKey = customKey || form.getAttribute('aria-label') || form.className || String(index);
        return filterStoragePrefix + window.location.pathname + '.' + String(formKey).replace(/\s+/g, '-').toLowerCase();
    }

    function filterParamsFromForm(form) {
        var params = new URLSearchParams();
        var data = new FormData(form);

        data.forEach(function (value, key) {
            var stringValue = String(value || '').trim();

            if (stringValue === '' || isIgnoredFilterKey(key) || key === 'page') {
                return;
            }

            params.set(key, stringValue);
        });

        return params;
    }

    function filterParamsFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var clean = new URLSearchParams();

        params.forEach(function (value, key) {
            if (value !== '' && !isIgnoredFilterKey(key) && key !== 'page') {
                clean.set(key, value);
            }
        });

        return clean;
    }

    function hasParams(params) {
        return Array.from(params.keys()).length > 0;
    }

    function clearFilterStorageForCurrentPage() {
        var prefix = filterStoragePrefix + window.location.pathname + '.';

        Object.keys(window.localStorage).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                window.localStorage.removeItem(key);
            }
        });
    }

    function updateFilterActiveState(field) {
        var type = String(field.getAttribute('type') || '').toLowerCase();
        var active = false;

        if (type === 'checkbox' || type === 'radio') {
            active = field.checked;
        } else {
            active = String(field.value || '').trim() !== '';
        }

        var wrappers = [];
        var formField = field.closest('.form-field');
        var searchField = field.closest('.search-field');

        if (formField) {
            wrappers.push(formField);
        }

        if (searchField) {
            wrappers.push(searchField);
        }

        wrappers.forEach(function (wrapper) {
            wrapper.classList.toggle('is-filter-active', active);
        });
    }

    function cssNameSelectorValue(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function rememberFilterFocus(field, storageKey) {
        if (!field || !field.name) {
            return;
        }

        var focusData = {
            path: window.location.pathname,
            storageKey: storageKey,
            name: field.name,
            selectionStart: typeof field.selectionStart === 'number' ? field.selectionStart : null,
            selectionEnd: typeof field.selectionEnd === 'number' ? field.selectionEnd : null
        };

        window.sessionStorage.setItem(filterFocusKey, JSON.stringify(focusData));
    }

    function restoreFilterFocus(form, storageKey) {
        var savedFocus = window.sessionStorage.getItem(filterFocusKey);

        if (!savedFocus) {
            return;
        }

        var focusData = null;

        try {
            focusData = JSON.parse(savedFocus);
        } catch (error) {
            window.sessionStorage.removeItem(filterFocusKey);
            return;
        }

        if (!focusData
            || focusData.path !== window.location.pathname
            || focusData.storageKey !== storageKey
            || !focusData.name) {
            return;
        }

        var field = form.querySelector('[name="' + cssNameSelectorValue(focusData.name) + '"]');

        if (!field || field.disabled) {
            window.sessionStorage.removeItem(filterFocusKey);
            return;
        }

        window.setTimeout(function () {
            try {
                field.focus({ preventScroll: true });
            } catch (error) {
                field.focus();
            }

            if (typeof field.setSelectionRange === 'function'
                && typeof focusData.selectionStart === 'number'
                && typeof focusData.selectionEnd === 'number') {
                field.setSelectionRange(focusData.selectionStart, focusData.selectionEnd);
            }

            updateFilterActiveState(field);
            window.sessionStorage.removeItem(filterFocusKey);
        }, 0);
    }

    function canUseLiveFilter(form) {
        return form.hasAttribute('data-live-filter') && window.fetch && window.DOMParser && window.history;
    }

    function liveFilterTarget(form) {
        var selector = form.getAttribute('data-filter-target');
        return selector ? document.querySelector(selector) : null;
    }

    function updateLiveFilterResults(form, url, storageKey, triggerField) {
        var target = liveFilterTarget(form);

        if (!target) {
            rememberFilterFocus(triggerField, storageKey);
            window.location.href = url.pathname + (url.search ? url.search : '');
            return;
        }

        target.classList.add('is-loading');

        fetch(url.pathname + (url.search ? url.search : ''), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextTarget = doc.querySelector(form.getAttribute('data-filter-target'));

                if (!nextTarget) {
                    throw new Error('Live target missing');
                }

                target.innerHTML = nextTarget.innerHTML;
                bindDropdownToggles(target);
                bindRowLinks(target);
                window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
            })
            .catch(function () {
                rememberFilterFocus(triggerField, storageKey);
                window.location.href = url.pathname + (url.search ? url.search : '');
            })
            .finally(function () {
                target.classList.remove('is-loading');
            });
    }

    document.querySelectorAll('[data-expenses-direct-filter]').forEach(function (form) {
        var storageKey = filterStoragePrefix + window.location.pathname + '.expenses';
        var searchTimer = null;

        function buildExpensesFilterParams() {
            var params = filterParamsFromForm(form);
            var currentParams = new URLSearchParams(window.location.search);

            currentParams.forEach(function (value, key) {
                if (isIgnoredFilterKey(key) && value !== '') {
                    params.set(key, value);
                }
            });

            return params;
        }

        function goToExpensesFilters(triggerField, useLiveResults) {
            var params = buildExpensesFilterParams();
            window.localStorage.setItem(storageKey, params.toString());
            rememberFilterFocus(triggerField, storageKey);

            var targetUrl = form.getAttribute('action') || window.location.pathname;
            var url = new URL(targetUrl, window.location.origin);

            params.forEach(function (value, key) {
                url.searchParams.set(key, value);
            });

            if (useLiveResults && window.fetch && window.DOMParser && window.history && liveFilterTarget(form)) {
                updateLiveFilterResults(form, url, storageKey, triggerField);
                return;
            }

            window.location.href = url.pathname + (url.search ? url.search : '');
        }

        var currentParams = filterParamsFromUrl();
        var savedParams = window.localStorage.getItem(storageKey);

        if (hasParams(currentParams)) {
            window.localStorage.setItem(storageKey, currentParams.toString());
        } else if (savedParams) {
            window.location.replace(window.location.pathname + '?' + savedParams);
            return;
        }

        form.querySelectorAll('input, select, textarea').forEach(updateFilterActiveState);

        form.addEventListener('change', function (event) {
            if (!event.target.matches('input, select, textarea')) {
                return;
            }

            updateFilterActiveState(event.target);
            goToExpensesFilters(event.target, false);
        });

        form.querySelectorAll('input[type="search"]').forEach(function (field) {
            field.addEventListener('input', function () {
                updateFilterActiveState(field);
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    goToExpensesFilters(field, true);
                }, 450);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            goToExpensesFilters(document.activeElement && form.contains(document.activeElement) ? document.activeElement : null, false);
        });

        form.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                var resetUrl = new URL(link.href, window.location.origin);

                if (resetUrl.pathname === window.location.pathname && resetUrl.search === '') {
                    window.localStorage.removeItem(storageKey);
                    window.sessionStorage.removeItem(filterFocusKey);
                }
            });
        });

        restoreFilterFocus(form, storageKey);
    });

    document.querySelectorAll('form').forEach(function (form, index) {
        if (!isPersistentFilterForm(form)) {
            return;
        }

        var storageKey = storageKeyForFilterForm(form, index);
        var currentFilterParams = filterParamsFromUrl();
        var savedFilterParams = window.localStorage.getItem(storageKey);
        var searchTimer = null;

        if (hasParams(currentFilterParams)) {
            window.localStorage.setItem(storageKey, currentFilterParams.toString());
        } else if (savedFilterParams) {
            var savedParams = new URLSearchParams(savedFilterParams);
            var restoredParams = new URLSearchParams();
            var currentParams = new URLSearchParams(window.location.search);

            savedParams.forEach(function (value, key) {
                if (value !== '' && !isIgnoredFilterKey(key) && key !== 'page') {
                    restoredParams.set(key, value);
                }
            });

            window.localStorage.setItem(storageKey, restoredParams.toString());

            currentParams.forEach(function (value, key) {
                if (isIgnoredFilterKey(key) && value !== '') {
                    restoredParams.set(key, value);
                }
            });

            var restoredUrl = window.location.pathname + '?' + restoredParams.toString() + window.location.hash;
            window.location.replace(restoredUrl);
            return;
        }

        form.querySelectorAll('input, select, textarea').forEach(updateFilterActiveState);

        function submitFilters(triggerField) {
            var params = filterParamsFromForm(form);
            window.localStorage.setItem(storageKey, params.toString());

            rememberFilterFocus(triggerField, storageKey);

            var targetUrl = form.getAttribute('action') || window.location.pathname;
            var url = new URL(targetUrl, window.location.origin);

            params.forEach(function (value, key) {
                url.searchParams.set(key, value);
            });

            if (canUseLiveFilter(form)) {
                updateLiveFilterResults(form, url, storageKey, triggerField);
                return;
            }

            window.location.href = url.pathname + (url.search ? url.search : '');
        }

        form.addEventListener('change', function (event) {
            if (!event.target.matches('input, select, textarea')) {
                return;
            }

            updateFilterActiveState(event.target);
            submitFilters(event.target);
        });

        form.querySelectorAll('input[type="search"], input[data-auto-filter-search]').forEach(function (field) {
            field.addEventListener('input', function () {
                updateFilterActiveState(field);
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    submitFilters(field);
                }, 450);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitFilters(document.activeElement && form.contains(document.activeElement) ? document.activeElement : null);
        });

        restoreFilterFocus(form, storageKey);
    });

    document.addEventListener('click', function (event) {
        var resetLink = event.target.closest('a');

        if (!resetLink) {
            return;
        }

        var filterForm = resetLink.closest('form');

        if (!isPersistentFilterForm(filterForm)) {
            return;
        }

        var resetUrl = new URL(resetLink.href, window.location.origin);

        if (resetUrl.pathname === window.location.pathname && resetUrl.search === '') {
            clearFilterStorageForCurrentPage();
        }
    });

    document.querySelectorAll('[data-photo-editor]').forEach(function (editor) {
        var form = editor.closest('[data-photo-editor-form]');
        var preview = editor.querySelector('.photo-editor-preview');
        var input = editor.querySelector('[data-photo-editor-input]');
        var image = editor.querySelector('[data-photo-editor-image]');
        var placeholder = editor.querySelector('[data-photo-editor-placeholder]');
        var zoom = editor.querySelector('[data-photo-editor-zoom]');
        var output = form ? form.querySelector('input[name="photo_data"]') : null;
        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d');
        var sourceImage = null;
        var hasNewImage = false;
        var isDragging = false;
        var dragStartX = 0;
        var dragStartY = 0;
        var startOffsetX = 0;
        var startOffsetY = 0;
        var offsetX = 0;
        var offsetY = 0;
        var isPositionInitialized = false;

        canvas.width = 600;
        canvas.height = 600;
        canvas.hidden = true;
        canvas.setAttribute('aria-hidden', 'true');

        if (preview) {
            preview.appendChild(canvas);
        }

        function clamp(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function drawPhoto() {
            if (!sourceImage || !context) {
                return;
            }

            var size = canvas.width;
            var coverScale = Math.max(size / sourceImage.width, size / sourceImage.height);
            var scale = coverScale * (zoom ? Number(zoom.value || 1) : 1);
            var width = sourceImage.width * scale;
            var height = sourceImage.height * scale;
            var minX = size - width;
            var minY = size - height;

            if (!isPositionInitialized) {
                offsetX = (size - width) / 2;
                offsetY = (size - height) / 2;
                isPositionInitialized = true;
            }

            offsetX = clamp(offsetX, minX, 0);
            offsetY = clamp(offsetY, minY, 0);

            context.clearRect(0, 0, size, size);
            context.drawImage(sourceImage, offsetX, offsetY, width, height);
        }

        if (zoom) {
            zoom.addEventListener('input', drawPhoto);
        }

        if (input) {
            input.addEventListener('click', function () {
                if (input.hasAttribute('data-reset-on-click')) {
                    input.value = '';
                }
            });

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];

                if (!file || !file.type.match(/^image\/(jpeg|png|webp)$/)) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function () {
                    var loadedImage = new Image();

                    loadedImage.onload = function () {
                        sourceImage = loadedImage;
                        hasNewImage = true;
                        isPositionInitialized = false;
                        canvas.hidden = false;

                        if (image) {
                            image.hidden = true;
                        }

                        if (placeholder) {
                            placeholder.hidden = true;
                        }

                        if (zoom) {
                            zoom.value = '1';
                        }

                        offsetX = 0;
                        offsetY = 0;
                        drawPhoto();
                    };
                    loadedImage.src = String(reader.result);
                };
                reader.readAsDataURL(file);
            });
        }

        if (preview) {
            preview.addEventListener('pointerdown', function (event) {
                if (!sourceImage || canvas.hidden) {
                    return;
                }

                isDragging = true;
                dragStartX = event.clientX;
                dragStartY = event.clientY;
                startOffsetX = offsetX;
                startOffsetY = offsetY;
                preview.setPointerCapture(event.pointerId);
                preview.classList.add('is-dragging');
            });

            preview.addEventListener('pointermove', function (event) {
                if (!isDragging) {
                    return;
                }

                var previewWidth = preview.getBoundingClientRect().width || canvas.width;
                var pointerScale = canvas.width / previewWidth;
                offsetX = startOffsetX + (event.clientX - dragStartX) * pointerScale;
                offsetY = startOffsetY + (event.clientY - dragStartY) * pointerScale;
                drawPhoto();
            });

            preview.addEventListener('pointerup', function (event) {
                isDragging = false;
                preview.releasePointerCapture(event.pointerId);
                preview.classList.remove('is-dragging');
            });

            preview.addEventListener('pointercancel', function () {
                isDragging = false;
                preview.classList.remove('is-dragging');
            });
        }

        if (form && output) {
            form.addEventListener('submit', function () {
                if (!hasNewImage || !sourceImage || canvas.hidden) {
                    return;
                }

                output.value = canvas.toDataURL('image/jpeg', 0.88);
            });
        }
    });

    document.querySelectorAll('[data-payment-form]').forEach(function (form) {
        var studentSelect = form.querySelector('[data-payment-student]');
        var subscriptionSelect = form.querySelector('[data-payment-subscription]');
        var subscriptionPlans = Array.prototype.slice.call(form.querySelectorAll('[data-payment-plan]'));
        var subscriptionHidden = form.querySelector('[data-payment-subscription-hidden]');
        var amountInput = form.querySelector('[data-payment-amount]');
        var periodMonthInput = form.querySelector('[data-payment-period-month]');
        var paymentDateInput = form.querySelector('[data-payment-date]');
        var previewGroup = form.querySelector('[data-payment-preview-group]');
        var previewSubscription = form.querySelector('[data-payment-preview-subscription]');
        var previewAmount = form.querySelector('[data-payment-preview-amount]');

        function selectedSubscriptionControl() {
            if (subscriptionSelect) {
                return subscriptionSelect.selectedOptions[0] || null;
            }

            return subscriptionPlans.find(function (plan) {
                return plan.checked;
            }) || null;
        }

        function selectSubscription(subscriptionId) {
            if (!subscriptionId) {
                return;
            }

            if (subscriptionSelect) {
                subscriptionSelect.value = subscriptionId;
                return;
            }

            subscriptionPlans.forEach(function (plan) {
                plan.checked = plan.value === subscriptionId;
            });
        }

        function subscriptionLabel(control) {
            if (!control) {
                return '-';
            }

            var card = control.closest('.payment-plan-option');
            var strong = card ? card.querySelector('strong') : null;
            return strong ? strong.textContent.trim() : (control.textContent || '-').trim();
        }

        function setPaymentPeriodFromDate() {
            if (!periodMonthInput || !paymentDateInput || !paymentDateInput.value) {
                return;
            }

            periodMonthInput.value = paymentDateInput.value.slice(0, 7);
        }

        function setAmountFromSubscription(force) {
            if (!amountInput) {
                return;
            }

            var option = selectedSubscriptionControl();
            var price = option ? option.getAttribute('data-price') : '';

            if (price && (force || amountInput.value === '')) {
                amountInput.value = Number(price).toFixed(2);
            }
        }

        if (studentSelect && (subscriptionSelect || subscriptionPlans.length)) {
            studentSelect.addEventListener('change', function () {
                var option = studentSelect.selectedOptions[0];
                var subscriptionId = option ? option.getAttribute('data-subscription-id') : '';
                var price = option ? option.getAttribute('data-price') : '';

                if (subscriptionId) {
                    selectSubscription(subscriptionId);
                }

                if (price && amountInput) {
                    amountInput.value = Number(price).toFixed(2);
                }
            });
        }

        if (studentSelect && (subscriptionHidden || subscriptionSelect || subscriptionPlans.length)) {
            function updateQuickPaymentPreview() {
                var option = studentSelect.selectedOptions[0];
                var subscriptionId = option ? option.getAttribute('data-subscription-id') || '' : '';
                var groupName = option ? option.getAttribute('data-group-name') || '-' : '-';
                var selectedSubscription = selectedSubscriptionControl();
                var price = selectedSubscription ? selectedSubscription.getAttribute('data-price') || '' : (option ? option.getAttribute('data-price') || '' : '');

                if (subscriptionHidden) {
                    subscriptionHidden.value = subscriptionId;
                }

                if (subscriptionId && !selectedSubscription) {
                    selectSubscription(subscriptionId);
                    selectedSubscription = selectedSubscriptionControl();
                    price = selectedSubscription ? selectedSubscription.getAttribute('data-price') || '' : price;
                }

                if (amountInput) {
                    amountInput.value = price ? Number(price).toFixed(2) : '';
                }

                if (previewGroup) {
                    previewGroup.textContent = groupName;
                }

                if (previewSubscription) {
                    previewSubscription.textContent = subscriptionLabel(selectedSubscription);
                }

                if (previewAmount) {
                    previewAmount.textContent = price ? Number(price).toLocaleString('pl-PL', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' zł' : '- zł';
                }
            }

            studentSelect.addEventListener('change', updateQuickPaymentPreview);
            if (subscriptionSelect) {
                subscriptionSelect.addEventListener('change', updateQuickPaymentPreview);
            }
            subscriptionPlans.forEach(function (plan) {
                plan.addEventListener('change', updateQuickPaymentPreview);
            });
            updateQuickPaymentPreview();
        }

        if (subscriptionSelect || subscriptionPlans.length) {
            var subscriptionControls = subscriptionSelect ? [subscriptionSelect] : subscriptionPlans;
            subscriptionControls.forEach(function (control) {
                control.addEventListener('change', function () {
                    if (amountInput) {
                        amountInput.value = '';
                    }

                    setAmountFromSubscription(true);
                });
            });
            setAmountFromSubscription(false);
        }

        if (paymentDateInput && periodMonthInput) {
            paymentDateInput.addEventListener('change', setPaymentPeriodFromDate);
            setPaymentPeriodFromDate();
        }

        if (periodMonthInput && periodMonthInput.hasAttribute('data-payment-period-redirect')) {
            periodMonthInput.addEventListener('change', function () {
                if (!periodMonthInput.value) {
                    return;
                }

                var url = new URL(window.location.href);
                url.searchParams.set('modal', 'add');
                url.searchParams.set('period_month', periodMonthInput.value);
                window.location.href = url.toString();
            });
        }
    });

    document.querySelectorAll('[data-expense-form]').forEach(function (form) {
        var categorySelect = form.querySelector('[data-expense-category-select]');
        var amountField = form.querySelector('[data-expense-amount-field]');
        var amountInput = form.querySelector('[data-expense-amount]');
        var rentalHours = form.querySelector('[data-rental-hours]');
        var rentalRate = form.querySelector('[data-rental-rate]');
        var rentalDateSelect = form.querySelector('[data-rental-date-select]');
        var rentalTimePreview = form.querySelector('[data-rental-time-preview]');
        var individualRentalStart = form.querySelector('[data-individual-rental-start]');
        var individualRentalDuration = form.querySelector('[data-individual-rental-duration]');
        var individualRentalDateList = form.querySelector('[data-individual-rental-date-list]');
        var panels = form.querySelectorAll('[data-expense-category-panel]');
        var manualAmount = !!form.querySelector('input[name="expense_id"]');
        if (amountInput) { amountInput.addEventListener('input', function () { manualAmount = true; }); }

        function selectedSlug() {
            var option = categorySelect ? categorySelect.selectedOptions[0] : null;
            return option ? String(option.getAttribute('data-slug') || '') : '';
        }

        function isAutoPricedRental(slug) {
            return slug === 'wynajem' || slug === 'wynajem-indywidualny';
        }

        function selectedRentalOption() {
            if (!rentalDateSelect) {
                return null;
            }

            if (rentalDateSelect.matches && rentalDateSelect.matches('select')) {
                return rentalDateSelect.selectedOptions[0] || null;
            }

            return rentalDateSelect.querySelector('input[name="rental_date"]:checked');
        }

        function updatePanels(shouldUpdateAmount) {
            var slug = selectedSlug();
            var hideAmount = isAutoPricedRental(slug);

            if (amountField && amountInput) {
                amountField.hidden = false;
                amountInput.required = true;
            }

            panels.forEach(function (panel) {
                var isActive = panel.getAttribute('data-expense-category-panel') === slug;
                panel.hidden = !isActive;

                panel.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = !isActive;
                });
            });

            if (shouldUpdateAmount && slug === 'wynajem') {
                updateRentalAmount();
            }

            if (shouldUpdateAmount && slug === 'wynajem-indywidualny') {
                updateIndividualRentalAmount();
            }
        }

        function timeToMinutes(value) {
            var parts = String(value || '').split(':');
            if (parts.length !== 2) {
                return null;
            }

            var hours = Number(parts[0]);
            var minutes = Number(parts[1]);
            if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
                return null;
            }

            return (hours * 60) + minutes;
        }

        function minutesToTime(minutes) {
            var normalized = ((minutes % 1440) + 1440) % 1440;
            var hours = Math.floor(normalized / 60);
            var remainder = normalized % 60;

            return String(hours).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
        }

        function updateRentalTime() {
            if (!rentalDateSelect || !rentalTimePreview) {
                return;
            }

            var option = selectedRentalOption();
            var start = option ? String(option.getAttribute('data-start') || '') : '';
            var end = option ? String(option.getAttribute('data-end') || '') : '';

            rentalTimePreview.value = start && end ? start + ' - ' + end : '';

            if (rentalHours && start && end) {
                var startMinutes = timeToMinutes(start);
                var endMinutes = timeToMinutes(end);
                if (startMinutes !== null && endMinutes !== null && endMinutes > startMinutes) {
                    rentalHours.value = String(Math.max(1, (endMinutes - startMinutes) / 60));
                }
            }
        }

        function updateRentalAmount() {
            if (!amountInput || !rentalHours || !rentalRate || selectedSlug() !== 'wynajem') {
                return;
            }

            updateRentalTime();

            var hours = Number(rentalHours.value || 0);
            var rate = Number(rentalRate.value || 0);

            if (!manualAmount && hours > 0 && rate > 0) {
                amountInput.value = (hours * rate).toFixed(2);
            }
        }

        function updateIndividualRentalAmount() {
            if (!amountInput || !rentalRate || selectedSlug() !== 'wynajem-indywidualny') {
                return;
            }

            var rate = Number(rentalRate.value || 32);
            var duration = Number(individualRentalDuration ? individualRentalDuration.value || 60 : 60);
            var startMinutes = timeToMinutes(individualRentalStart ? individualRentalStart.value : '');

            if (!manualAmount && startMinutes !== null && rate > 0) {
                amountInput.value = ((rate * Math.max(1, duration)) / 60).toFixed(2);
            }
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', function () {
                updatePanels(true);
            });
        }

        if (rentalHours) {
            rentalHours.addEventListener('input', updateRentalAmount);
        }

        if (rentalRate) {
            rentalRate.addEventListener('input', updateRentalAmount);
        }

        if (individualRentalStart) {
            individualRentalStart.addEventListener('input', updateIndividualRentalAmount);
            individualRentalStart.addEventListener('change', updateIndividualRentalAmount);
        }

        if (individualRentalDateList) {
            individualRentalDateList.addEventListener('change', updateIndividualRentalAmount);
        }

        if (rentalDateSelect) {
            rentalDateSelect.addEventListener('change', updateRentalAmount);
            updateRentalTime();
        }

        updatePanels(!amountInput || !amountInput.value);
    });

    document.querySelectorAll('[data-salary-form]').forEach(function (form) {
        var employeeSelect = form.querySelector('[data-salary-employee]');
        var amountPreview = form.querySelector('[data-salary-amount-preview]');

        function updateAmountPreview() {
            if (!employeeSelect || !amountPreview) {
                return;
            }

            var option = employeeSelect.selectedOptions[0];
            var amount = Number(option ? option.getAttribute('data-amount') || 0 : 0);
            amountPreview.value = amount.toLocaleString('pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' z?';
        }

        if (employeeSelect) {
            employeeSelect.addEventListener('change', updateAmountPreview);
        }

        updateAmountPreview();
    });

    document.querySelectorAll('[data-phone-input]').forEach(function (input) {
        function formatPhone(value) {
            var digits = String(value).replace(/\D/g, '');

            if (digits.slice(0, 2) === '48' && digits.length > 9) {
                digits = digits.slice(2);
            }

            digits = digits.slice(0, 9);
            var groups = [];

            for (var i = 0; i < digits.length; i += 3) {
                groups.push(digits.slice(i, i + 3));
            }

            return digits.length ? '+48 ' + groups.join(' ') : '';
        }

        function applyPhoneFormat() {
            input.value = formatPhone(input.value);
        }

        input.addEventListener('input', applyPhoneFormat);
        input.addEventListener('blur', applyPhoneFormat);
        applyPhoneFormat();
    });

    document.querySelectorAll('[data-attendance-mark-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('#attendance-modal select[name$="[status]"]').forEach(function (select) {
                select.value = 'present';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });

    document.querySelectorAll('[data-attendance-close]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            var modal = document.getElementById('attendance-modal');
            if (!modal) {
                return;
            }

            event.preventDefault();
            modal.classList.remove('is-open');

            var url = new URL(window.location.href);
            url.searchParams.delete('attendance_date');
            url.searchParams.delete('attendance_modal');
            window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
        });
    });

    document.querySelectorAll('.trip-dropzone').forEach(function (dropzone) {
        var input = dropzone.querySelector('input[type="file"]');
        var label = dropzone.querySelector('span');
        var defaultLabel = label ? label.innerHTML : '';

        if (!input || !label) {
            return;
        }

        function setFileName() {
            if (input.files && input.files.length > 1) {
                label.textContent = 'Wybrano ' + input.files.length + ' zdjęć';
                return;
            }

            if (input.files && input.files[0]) {
                label.textContent = input.files[0].name;
                return;
            }

            label.innerHTML = defaultLabel || 'Przeciągnij zdjęcia tutaj lub kliknij, aby wybrać pliki';
        }

        dropzone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');

            if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                input.files = event.dataTransfer.files;
                setFileName();
            }
        });

        input.addEventListener('change', setFileName);
    });

    document.querySelectorAll('.trips-create-form, .payment-form-grid').forEach(function (form) {
        var picker = form.querySelector('[data-trip-participant-picker]');
        var budgetInput = form.querySelector('[data-trip-budget]');
        var participantCount = form.querySelector('[data-trip-participant-count]');
        var selectedCount = form.querySelector('[data-trip-selected-count]');
        var costPerson = form.querySelector('[data-trip-cost-person]');
        var searchInput = form.querySelector('[data-trip-participant-search]');

        if (!picker) {
            return;
        }

        function checkedParticipants() {
            return picker.querySelectorAll('input[name="participant_ids[]"]:checked').length;
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' z?';
        }

        function updateTripCost() {
            var count = checkedParticipants();
            var budget = Number(String(budgetInput ? budgetInput.value : '0').replace(',', '.')) || 0;
            var perPerson = count > 0 ? budget / count : 0;

            if (participantCount) {
                participantCount.textContent = String(count);
            }
            if (selectedCount) {
                selectedCount.textContent = String(count);
            }
            if (costPerson) {
                costPerson.textContent = formatMoney(perPerson);
            }
        }

        function filterParticipants() {
            var query = String(searchInput ? searchInput.value : '').trim().toLowerCase();
            picker.querySelectorAll('[data-trip-participant-option]').forEach(function (option) {
                option.hidden = query !== '' && String(option.getAttribute('data-search') || '').indexOf(query) === -1;
            });
        }

        picker.addEventListener('change', updateTripCost);
        if (budgetInput) {
            budgetInput.addEventListener('input', updateTripCost);
        }
        if (searchInput) {
            searchInput.addEventListener('input', filterParticipants);
        }

        updateTripCost();
        filterParticipants();
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('invalid', function () {
            form.classList.add('was-validated');
        }, true);

        form.addEventListener('submit', function () {
            form.classList.add('was-validated');
        });
    });

    document.querySelectorAll('[data-trip-sortable-gallery]').forEach(function (gallery) {
        var draggedCard = null;

        gallery.querySelectorAll('.trip-photo-edit-card').forEach(function (card) {
            card.addEventListener('dragstart', function () {
                draggedCard = card;
                card.classList.add('is-dragging');
            });

            card.addEventListener('dragend', function () {
                card.classList.remove('is-dragging');
                draggedCard = null;
            });
        });

        gallery.addEventListener('dragover', function (event) {
            var target = event.target.closest('.trip-photo-edit-card');
            if (!draggedCard || !target || target === draggedCard || !gallery.contains(target)) {
                return;
            }

            event.preventDefault();
            var rect = target.getBoundingClientRect();
            var insertAfter = event.clientX > rect.left + rect.width / 2 || event.clientY > rect.top + rect.height / 2;
            gallery.insertBefore(draggedCard, insertAfter ? target.nextSibling : target);
        });
    });

    function layoutTripGallery(gallery) {
        var tiles = Array.prototype.slice.call(gallery.querySelectorAll('.trips-photo-tile'));

        if (!tiles.length) {
            return;
        }

        var width = gallery.clientWidth;
        if (width <= 0) {
            return;
        }

        var styles = window.getComputedStyle(gallery);
        var gap = parseFloat(styles.columnGap || styles.gap) || 4;
        var targetHeight = width < 700 ? 112 : 138;
        var minHeight = width < 700 ? 98 : 120;
        var maxHeight = width < 700 ? 132 : 158;
        var availableHeight = gallery.clientHeight || (targetHeight * 2 + gap);
        var maxRows = Math.max(1, Math.floor((availableHeight + gap) / (minHeight + gap)));
        var rows = [];
        var pool = tiles.slice();
        var detailsUrl = gallery.getAttribute('data-details-url') || '#';

        function aspect(tile) {
            var value = parseFloat(tile.getAttribute('data-aspect') || '1.7778');
            return Number.isFinite(value) && value > 0 ? value : 1.7778;
        }

        function resetTile(tile) {
            tile.classList.remove('is-hidden', 'has-more');
            tile.style.removeProperty('--trip-photo-height');
            tile.style.removeProperty('--trip-photo-width');
            tile.href = tile.getAttribute('data-photo-url') || tile.href;
            tile.target = '_blank';
            tile.querySelectorAll('.trips-photo-more').forEach(function (badge) {
                badge.remove();
            });
        }

        function appendMoreBadge(tile, hiddenCount) {
            var badge = document.createElement('span');
            badge.className = 'trips-photo-more';
            badge.textContent = '+' + hiddenCount;
            tile.classList.add('has-more');
            tile.href = detailsUrl;
            tile.target = '_self';
            tile.appendChild(badge);
        }

        function pickNextTile(rowAspect, targetAspect) {
            var bestIndex = 0;
            var bestScore = Infinity;

            pool.forEach(function (tile, index) {
                var nextAspect = rowAspect + aspect(tile);
                var score = Math.abs(targetAspect - nextAspect);

                if (nextAspect < targetAspect) {
                    score -= 0.08;
                }

                if (score < bestScore) {
                    bestScore = score;
                    bestIndex = index;
                }
            });

            return pool.splice(bestIndex, 1)[0];
        }

        function applyRow(row) {
            if (!row.length) {
                return;
            }

            var rowAspect = row.reduce(function (sum, tile) {
                return sum + aspect(tile);
            }, 0);
            var rowGaps = gap * Math.max(0, row.length - 1);
            var rowHeight = (width - rowGaps) / rowAspect;

            if (row.length < 3) {
                rowHeight = targetHeight;
            }

            rowHeight = Math.max(minHeight, Math.min(maxHeight, rowHeight));

            row.forEach(function (tile) {
                tile.style.setProperty('--trip-photo-height', rowHeight.toFixed(2) + 'px');
                tile.style.setProperty('--trip-photo-width', Math.max(96, rowHeight * aspect(tile)).toFixed(2) + 'px');
            });
        }

        tiles.forEach(resetTile);
        pool.sort(function (a, b) {
            return aspect(b) - aspect(a);
        });

        while (pool.length && rows.length < maxRows) {
            var row = [];
            var rowAspect = 0;
            var targetAspect = width / targetHeight;

            while (pool.length) {
                var tile = pickNextTile(rowAspect, targetAspect);
                row.push(tile);
                rowAspect += aspect(tile);

                var projectedWidth = rowAspect * targetHeight + gap * Math.max(0, row.length - 1);
                if (projectedWidth >= width || (!pool.length && rows.length === 0)) {
                    break;
                }
            }

            rows.push(row);
        }

        var visibleTiles = rows.reduce(function (list, row) {
            return list.concat(row);
        }, []);
        var hiddenTiles = tiles.filter(function (tile) {
            return visibleTiles.indexOf(tile) === -1;
        });

        rows.forEach(function (row) {
            row.forEach(function (tile) {
                gallery.appendChild(tile);
            });
            applyRow(row);
        });

        hiddenTiles.forEach(function (tile) {
            tile.classList.add('is-hidden');
            gallery.appendChild(tile);
        });

        if (hiddenTiles.length && visibleTiles.length) {
            appendMoreBadge(visibleTiles[visibleTiles.length - 1], hiddenTiles.length);
        }
    }

    function layoutTripGalleries() {
        document.querySelectorAll('[data-trip-justified-gallery]').forEach(layoutTripGallery);
    }

    var tripGalleryResizeTimer = null;
    window.addEventListener('resize', function () {
        window.clearTimeout(tripGalleryResizeTimer);
        tripGalleryResizeTimer = window.setTimeout(layoutTripGalleries, 120);
    });

    document.querySelectorAll('[data-profile-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = document.getElementById(button.getAttribute('data-profile-modal-open'));
            if (!modal) {
                return;
            }
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.classList.add('modal-open');
            var firstField = modal.querySelector('input:not([type="hidden"]), select, textarea');
            if (firstField) {
                window.setTimeout(function () { firstField.focus(); }, 0);
            }
        });
    });

    document.querySelectorAll('[data-profile-attendance-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = document.getElementById('attendance-edit-modal');
            if (!modal) {
                return;
            }
            var date = button.getAttribute('data-lesson-date') || '';
            var dateInput = modal.querySelector('[data-profile-attendance-date-input]');
            var dateLabel = modal.querySelector('[data-profile-attendance-date-label]');
            var statusInput = modal.querySelector('[data-profile-attendance-status-input]');
            var notesInput = modal.querySelector('[data-profile-attendance-notes-input]');
            if (dateInput) dateInput.value = date;
            if (dateLabel) dateLabel.textContent = date;
            if (statusInput) statusInput.value = button.getAttribute('data-attendance-status') || 'present';
            if (notesInput) notesInput.value = button.getAttribute('data-attendance-notes') || '';
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.classList.add('modal-open');
            if (statusInput) statusInput.focus();
        });
    });

    function closeProfileModal(modal) {
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.hidden = true;
        if (!document.querySelector('.profile-admin-modal.is-open')) {
            document.body.classList.remove('modal-open');
        }
    }

    document.querySelectorAll('[data-profile-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeProfileModal(button.closest('.profile-admin-modal'));
        });
    });

    document.querySelectorAll('.profile-admin-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeProfileModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeProfileModal(document.querySelector('.profile-admin-modal.is-open'));
        }
    });

    layoutTripGalleries();
});
