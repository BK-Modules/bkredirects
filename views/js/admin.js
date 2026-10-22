/**
 * Comportamiento de las pantallas del módulo en el back office (1.7.5 → 9.x, sin dependencias
 * más allá de jQuery y Chosen, que ya trae el BO).
 *
 * - Chosen en los desplegables `.bkr-chosen`.
 * - Formulario de regla: el destino cambia de forma con el tipo y con el código (410/451 no
 *   llevan destino), y el buscador de entidades rellena `target_id`.
 * - Panel: detalle del gráfico al pasar el ratón y aplicar en lote las recomendaciones marcadas.
 */
(function ($) {
    'use strict';

    function initChosen() {
        if (!$.fn.chosen) {
            return;
        }
        $('.bkr-chosen').chosen({
            disable_search_threshold: 10,
            width: '100%'
        });
    }

    /* ---------------- Formulario de regla ---------------- */

    function initRuleForm() {
        var form = document.querySelector('.bkr-rule-form');
        if (!form || typeof bkrRules === 'undefined') {
            return;
        }

        var code = form.querySelector('[name="http_code"]');
        var type = form.querySelector('[name="target_type"]');
        var match = form.querySelector('[name="match_type"]');
        var block = form.querySelector('.bkr-target-block');
        var gone = form.querySelector('.bkr-gone-note');
        var entityRow = form.querySelector('.bkr-target-entity');
        var urlRow = form.querySelector('.bkr-target-url');
        var finder = initFinder(form);

        function sync() {
            var isGone = bkrRules.gone.indexOf(parseInt(code.value, 10)) !== -1;
            var t = type.value;
            block.hidden = isGone;
            gone.hidden = !isGone;
            urlRow.hidden = t !== 'url';
            entityRow.hidden = t === 'url' || t === 'home';
            form.querySelectorAll('.bkr-when').forEach(function (help) {
                help.hidden = !help.classList.contains('bkr-when--' + match.value);
            });
        }

        // Chosen dispara el change de jQuery, no el nativo.
        $(code).on('change', sync);
        $(match).on('change', sync);
        $(type).on('change', function () {
            finder.reset();
            sync();
        });
        sync();
    }

    /**
     * Buscador de entidades: lista de opciones bajo el campo, navegable con el teclado.
     */
    function initFinder(form) {
        var root = form.querySelector('.bkr-finder');
        var hidden = form.querySelector('[name="target_id"]');
        var type = form.querySelector('[name="target_type"]');
        var input = root.querySelector('.bkr-finder-input');
        var list = root.querySelector('.bkr-finder-list');
        var label = root.querySelector('.bkr-finder-label');
        var timer = null;
        var request = null;
        var active = -1;
        var current = [];

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function choose(item) {
            hidden.value = item.id;
            label.textContent = item.name + (item.id ? ' #' + item.id : '');
            root.classList.add('has-value');
            input.value = '';
            close();
        }

        function render(results) {
            current = results;
            list.innerHTML = '';
            if (!results.length) {
                var empty = document.createElement('li');
                empty.className = 'bkr-finder-empty';
                empty.textContent = bkrRules.i18n.noResults;
                list.appendChild(empty);
            }
            results.forEach(function (item, index) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.id = 'bkr-finder-opt-' + index;
                li.className = 'bkr-finder-option';
                var name = document.createElement('span');
                name.textContent = item.name;
                li.appendChild(name);
                var meta = document.createElement('small');
                meta.textContent = (item.hint ? item.hint + ' · ' : '') + (item.id ? '#' + item.id : '');
                li.appendChild(meta);
                li.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(item);
                });
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            active = -1;
        }

        function search() {
            var q = input.value.trim();
            if (request) {
                request.abort();
            }
            request = $.getJSON(bkrRules.searchUrl, { type: type.value, q: q }).done(function (data) {
                var results = data.results || [];
                // Sin nada escrito no hay «no encontrado» que decir: solo se abre si hay opciones.
                if (!results.length && q.length < 2) {
                    close();
                    return;
                }
                render(results);
            });
        }

        function highlight(step) {
            var options = list.querySelectorAll('.bkr-finder-option');
            if (!options.length) {
                return;
            }
            active = (active + step + options.length) % options.length;
            options.forEach(function (option, index) {
                option.classList.toggle('is-active', index === active);
                option.setAttribute('aria-selected', index === active ? 'true' : 'false');
            });
            input.setAttribute('aria-activedescendant', options[active].id);
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 200);
        });
        input.addEventListener('focus', search);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Enter') {
                // Enter elige la opción marcada; nunca envía el formulario desde el buscador.
                event.preventDefault();
                if (!list.hidden && current[active]) {
                    choose(current[active]);
                }
            } else if (event.key === 'Escape') {
                close();
            }
        });
        root.querySelector('.bkr-finder-change').addEventListener('click', function () {
            root.classList.remove('has-value');
            input.focus();
        });

        return {
            reset: function () {
                hidden.value = '0';
                label.textContent = '';
                root.classList.remove('has-value');
                close();
            }
        };
    }

    /* ---------------- Panel ---------------- */

    function initChart() {
        var figure = document.querySelector('.bkr-chart');
        if (!figure) {
            return;
        }
        var svg = figure.querySelector('svg');
        var tip = figure.querySelector('.bkr-chart-tip');
        var cursor = figure.querySelector('.bkr-chart-cursor');
        var width = svg.viewBox.baseVal.width;

        function show(rect) {
            var cx = parseFloat(rect.getAttribute('data-cx'));
            cursor.setAttribute('x1', cx);
            cursor.setAttribute('x2', cx);
            figure.classList.add('is-hovering');
            tip.querySelector('.bkr-chart-tip-date').textContent = rect.getAttribute('data-date');
            tip.querySelector('[data-key="redirects"]').textContent = rect.getAttribute('data-redirects');
            tip.querySelector('[data-key="notfound"]').textContent = rect.getAttribute('data-notfound');
            tip.querySelector('[data-key="bots"]').textContent = rect.getAttribute('data-bots');
            tip.hidden = false;
            var left = cx / width * svg.clientWidth;
            var flip = left > svg.clientWidth - tip.offsetWidth - 16;
            tip.style.left = (flip ? left - tip.offsetWidth - 12 : left + 12) + 'px';
        }

        figure.querySelectorAll('.bkr-chart-hits rect').forEach(function (rect) {
            rect.addEventListener('mouseenter', function () {
                show(rect);
            });
        });
        svg.addEventListener('mouseleave', function () {
            tip.hidden = true;
            figure.classList.remove('is-hovering');
        });
    }

    function initBulk() {
        var form = document.querySelector('.bkr-recs-form');
        if (!form) {
            return;
        }
        var button = form.querySelector('[name="submitBkrApplyBulk"]');
        var all = form.querySelector('.bkr-check-all');
        var boxes = form.querySelectorAll('input[name="bkr_keys[]"]');

        function sync() {
            var checked = form.querySelectorAll('input[name="bkr_keys[]"]:checked').length;
            if (button) {
                button.disabled = checked === 0;
            }
            if (all) {
                all.checked = checked > 0 && checked === boxes.length;
                all.indeterminate = checked > 0 && checked < boxes.length;
            }
        }

        if (all) {
            all.addEventListener('change', function () {
                boxes.forEach(function (box) {
                    box.checked = all.checked;
                });
                sync();
            });
        }
        boxes.forEach(function (box) {
            box.addEventListener('change', sync);
        });
        sync();
    }

    $(function () {
        initChosen();
        initRuleForm();
        initChart();
        initBulk();
    });
})(jQuery);
