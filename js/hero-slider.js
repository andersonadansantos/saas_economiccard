/* ==========================================================================
   Economic Card - Hero Slideshow
   As imagens e o intervalo ficam em assets/images/hero-slider/slides.json.
   ========================================================================== */

(function () {
    'use strict';

    const DIR = 'assets/images/hero-slider/';
    const container = document.getElementById('hero-slideshow');
    if (!container) return;

    function build(images, intervalMs, transitionMs) {
        if (!images.length) return;

        // Remove apenas as imagens do slideshow, preservando elementos decorativos do container.
        container.querySelectorAll('.hero-slide').forEach(function (el) {
            el.remove();
        });
        container.style.setProperty('--hero-fade', transitionMs + 'ms');

        const slides = images.map((item, index) => {
            const img = document.createElement('img');
            img.src = DIR + item.file;
            img.alt = item.alt || '';
            img.className = 'hero-slide' + (index === 0 ? ' is-active' : '');
            img.decoding = 'async';
            // Carrega todas antecipadamente para não haver espaço vazio na troca.
            img.loading = 'eager';
            img.fetchPriority = index === 0 ? 'high' : 'low';
            img.draggable = false;
            container.appendChild(img);
            return img;
        });

        let dotsWrap = null;
        if (slides.length > 1) {
            dotsWrap = document.createElement('div');
            dotsWrap.className = 'hero-slideshow-dots';
            dotsWrap.setAttribute('aria-hidden', 'true');
            slides.forEach((_, index) => {
                const dot = document.createElement('span');
                dot.className = 'hero-slideshow-dot' + (index === 0 ? ' is-active' : '');
                dotsWrap.appendChild(dot);
            });
            container.insertAdjacentElement('afterend', dotsWrap);
        }

        let current = 0;
        let timer = null;

        function show(next) {
            slides[current].classList.remove('is-active');
            if (dotsWrap) dotsWrap.children[current].classList.remove('is-active');

            current = next;

            slides[current].classList.add('is-active');
            if (dotsWrap) dotsWrap.children[current].classList.add('is-active');
        }

        function stop() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function start() {
            stop();
            if (slides.length < 2 || document.hidden) return;
            timer = window.setInterval(function () {
                show((current + 1) % slides.length);
            }, intervalMs);
        }

        start();

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });
    }

    fetch(DIR + 'slides.json', { cache: 'no-cache' })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            const images = Array.isArray(data.images) ? data.images : [];
            build(
                images,
                Math.max(2000, Number(data.intervalMs) || 5000),
                Math.max(200, Number(data.transitionMs) || 900)
            );
        })
        .catch(function (error) {
            console.error('Slideshow do hero: falha ao carregar slides.json', error);
            // A primeira imagem já está no HTML como fallback; garante que fique visível.
            const fallback = container.querySelector('.hero-slide');
            if (fallback) fallback.classList.add('is-active');
        });
})();
