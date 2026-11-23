<?php
/**
 * Plugin Name: Pawita – przyciski galerii (Staffa)
 * Description: Dwa przyciski pod opisem inwestycji: WIZUALIZACJA WNĘTRZA (istniejąca galeria Elementor) i WIZUALIZACJA BUDYNKU (galeria zdjęć i film budynku z lightboxem).
 * Version: 2.0.0
 */
if (!defined('ABSPATH')) { exit; }

add_action('wp_footer', function () {
    if (!is_page('domy-na-staffa')) { return; }

    $dir_rel = 'uploads/pawita-wizualizacje/';
    $base    = trailingslashit(content_url($dir_rel));
    $items   = array(
        array('type' => 'image', 'file' => 'budynek_view1_small',        'alt' => 'Wizualizacja budynku – widok od ulicy'),
        array('type' => 'image', 'file' => 'budynek_view2_small',        'alt' => 'Wizualizacja budynku – widok z boku'),
        array('type' => 'image', 'file' => 'budynek_view3_small',        'alt' => 'Wizualizacja budynku – widok od ogrodu'),
        array('type' => 'image', 'file' => 'budynek_view1_small_jesien', 'alt' => 'Wizualizacja budynku – jesień'),
        array('type' => 'image', 'file' => 'budynek_view1_small_zima',   'alt' => 'Wizualizacja budynku – zima'),
        array('type' => 'image', 'file' => 'budynek_view3_small_night',  'alt' => 'Wizualizacja budynku – wieczorem'),
        array('type' => 'video', 'file' => 'budynek_film',               'alt' => 'Film – wizualizacja budynku'),
    );
    $gallery = array();
    foreach ($items as $item) {
        $full = $item['type'] === 'video' ? $item['file'] . '.mp4' : $item['file'] . '.webp';
        $thumb = $item['type'] === 'video' ? $item['file'] . '_poster.webp' : $item['file'] . '_thumb.webp';
        // Show only files that were actually uploaded, so the page never gets broken tiles.
        if (!file_exists(WP_CONTENT_DIR . '/' . $dir_rel . $full) ||
            !file_exists(WP_CONTENT_DIR . '/' . $dir_rel . $thumb)) {
            continue;
        }
        $gallery[] = array('type' => $item['type'], 'src' => $base . $full,
                           'thumb' => $base . $thumb, 'alt' => $item['alt']);
    }
    ?>
    <style id="pawita-staffa-gallery-css">
      .pawita-staffa-gallery-link-wrap {
        display:flex; flex-wrap:wrap; gap:14px; justify-content:center; margin:30px 0;
      }
      .pawita-staffa-gallery-link {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:240px; min-height:49px; padding:12px 24px;
        border-radius:999px; background:#ff1955; color:#fff !important;
        font-size:14px; font-weight:700; line-height:1.2; letter-spacing:.02em;
        text-decoration:none !important; transition:background .2s, transform .2s;
      }
      .pawita-staffa-gallery-link:hover,.pawita-staffa-gallery-link:focus-visible {
        background:#ec0f4b; transform:translateY(-1px);
      }
      #pawita-staffa-gallery, #pawita-staffa-budynek { scroll-margin-top:100px; }
      .pawita-gallery-title {
        margin:40px 0 18px; text-align:center; font-weight:700; font-size:26px;
        text-transform:uppercase; letter-spacing:.04em;
      }
      .pawita-budynek-grid {
        display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin:0 0 30px;
      }
      @media (max-width:1024px) { .pawita-budynek-grid { grid-template-columns:repeat(2,1fr); } }
      @media (max-width:560px)  { .pawita-budynek-grid { grid-template-columns:1fr; } }
      .pawita-budynek-tile {
        position:relative; display:block; padding:0; border:0; cursor:pointer;
        aspect-ratio:16/9; overflow:hidden; border-radius:6px; background:#eee;
      }
      .pawita-budynek-tile img {
        width:100%; height:100%; object-fit:cover; display:block; transition:transform .35s;
      }
      .pawita-budynek-tile:hover img, .pawita-budynek-tile:focus-visible img { transform:scale(1.04); }
      .pawita-budynek-tile.is-video::after {
        content:''; position:absolute; left:50%; top:50%; width:68px; height:68px;
        margin:-34px 0 0 -34px; border-radius:50%; background:rgba(255,25,85,.9);
        box-shadow:0 4px 18px rgba(0,0,0,.3);
      }
      .pawita-budynek-tile.is-video::before {
        content:''; position:absolute; left:50%; top:50%; z-index:1; margin:-13px 0 0 -8px;
        border-style:solid; border-width:13px 0 13px 22px;
        border-color:transparent transparent transparent #fff;
      }
      .pawita-lb {
        position:fixed; inset:0; z-index:999999; display:none; align-items:center;
        justify-content:center; background:rgba(0,0,0,.92);
      }
      .pawita-lb.is-open { display:flex; }
      .pawita-lb-stage { max-width:92vw; max-height:86vh; }
      .pawita-lb-stage img, .pawita-lb-stage video {
        display:block; max-width:92vw; max-height:86vh; width:auto; height:auto;
      }
      .pawita-lb button {
        position:absolute; border:0; background:transparent; color:#fff; cursor:pointer;
        font-size:42px; line-height:1; padding:14px; opacity:.85;
      }
      .pawita-lb button:hover, .pawita-lb button:focus-visible { opacity:1; }
      .pawita-lb-close { top:8px; right:12px; }
      .pawita-lb-prev { left:8px; top:50%; transform:translateY(-50%); }
      .pawita-lb-next { right:8px; top:50%; transform:translateY(-50%); }
      .pawita-lb-count {
        position:absolute; bottom:14px; left:0; right:0; text-align:center;
        color:#fff; font-size:14px; opacity:.8;
      }
      @media (prefers-reduced-motion:no-preference) { html {scroll-behavior:smooth;} }
    </style>
    <script id="pawita-staffa-gallery-js">
    window.pawitaBudynek = <?php echo wp_json_encode($gallery); ?>;
    document.addEventListener('DOMContentLoaded', function () {
      if (document.querySelector('.pawita-staffa-gallery-link-wrap')) return;
      var paragraphs=Array.from(document.querySelectorAll('p'));
      var last=paragraphs.find(function (p) {
        return p.textContent.includes('Lokalizacja inwestycji umożliwia wygodny dostęp do infrastruktury miejskiej Wejherowa');
      });
      if (!last) return;

      var selectors=[
        '.elementor-widget-image-gallery', '.elementor-widget-gallery',
        '.elementor-widget-image-carousel',
        '.wp-block-gallery', '.gallery', '.e-gallery-container',
        '.swiper-container', '.swiper', '[data-gallery]', '[id*="galeria"]'
      ];
      var candidates=Array.from(document.querySelectorAll(selectors.join(',')))
        .filter(function(el) {
          return !el.closest('header, footer, nav, .dialog-widget, .elementor-lightbox') &&
            el.getBoundingClientRect().height>0 &&
            el.getBoundingClientRect().top>last.getBoundingClientRect().top;
        });
      var target=candidates[candidates.length-1];
      if (!target) {
        var headings=Array.from(document.querySelectorAll('h2,h3,h4'))
          .filter(function(h) {return /galeria/i.test(h.textContent) &&
            h.getBoundingClientRect().top>last.getBoundingClientRect().top;});
        target=headings[headings.length-1];
      }
      // Do not introduce broken navigation buttons if the gallery cannot be identified.
      if (!target) return;

      // Anchor the whole Elementor widget that holds the interior carousel.
      var widget = target.closest('.elementor-widget') || target;
      // The button scrolls to this heading, so it stays visible above the carousel.
      var interiorTitle = document.createElement('h3');
      interiorTitle.className = 'pawita-gallery-title';
      interiorTitle.id = 'pawita-staffa-gallery';
      interiorTitle.textContent = 'Wizualizacja wnętrza';
      widget.insertAdjacentElement('beforebegin', interiorTitle);

      var wrap=document.createElement('div');
      wrap.className='pawita-staffa-gallery-link-wrap';
      function addButton(href, label) {
        var a=document.createElement('a');
        a.className='pawita-staffa-gallery-link';
        a.href=href;
        a.textContent=label;
        wrap.appendChild(a);
      }
      addButton('#pawita-staffa-gallery', 'WIZUALIZACJA WNĘTRZA');

      var items = Array.isArray(window.pawitaBudynek) ? window.pawitaBudynek : [];
      if (items.length) {
        addButton('#pawita-staffa-budynek', 'WIZUALIZACJA BUDYNKU');
        buildBuildingGallery(widget, items);
      }
      last.insertAdjacentElement('afterend',wrap);

      // Center across the description and visualization columns.
      function centerAcrossSection() {
        wrap.style.transform = '';
        if (window.innerWidth < 768) return;
        var images = Array.from(document.querySelectorAll('img')).filter(function(img) {
          var src = img.currentSrc || img.src || '';
          return /Wizualizacja_Staffa_Wejherowo_view[12]_small/i.test(src) &&
            img.getBoundingClientRect().width > 0;
        });
        if (!images.length) return;
        var textRect = last.getBoundingClientRect();
        var rightEdge = Math.max.apply(null, images.map(function(img) {
          return img.getBoundingClientRect().right;
        }));
        if (rightEdge <= textRect.right) return;
        var sectionCenter = (textRect.left + rightEdge) / 2;
        var textColumnCenter = (textRect.left + textRect.right) / 2;
        wrap.style.transform = 'translateX(' + Math.round(sectionCenter - textColumnCenter) + 'px)';
      }
      centerAcrossSection();
      window.addEventListener('resize', centerAcrossSection, { passive: true });
      window.addEventListener('load', centerAcrossSection, { once: true });
    });

    function buildBuildingGallery(afterEl, items) {
      var section = document.createElement('div');
      section.className = 'pawita-budynek';
      var title = document.createElement('h3');
      title.className = 'pawita-gallery-title';
      title.id = 'pawita-staffa-budynek';
      title.textContent = 'Wizualizacja budynku';
      section.appendChild(title);
      var grid = document.createElement('div');
      grid.className = 'pawita-budynek-grid';
      items.forEach(function (item, i) {
        var tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'pawita-budynek-tile' + (item.type === 'video' ? ' is-video' : '');
        tile.setAttribute('aria-label', item.alt);
        var img = document.createElement('img');
        img.src = item.thumb; img.alt = item.alt; img.loading = 'lazy';
        tile.appendChild(img);
        tile.addEventListener('click', function () { openLightbox(items, i); });
        grid.appendChild(tile);
      });
      section.appendChild(grid);
      afterEl.insertAdjacentElement('afterend', section);
    }

    function openLightbox(items, start) {
      var lb = document.querySelector('.pawita-lb');
      if (!lb) {
        lb = document.createElement('div');
        lb.className = 'pawita-lb';
        lb.setAttribute('role', 'dialog');
        lb.setAttribute('aria-modal', 'true');
        lb.innerHTML = '<div class="pawita-lb-stage"></div>' +
          '<button type="button" class="pawita-lb-close" aria-label="Zamknij">&times;</button>' +
          '<button type="button" class="pawita-lb-prev" aria-label="Poprzednie">&#8249;</button>' +
          '<button type="button" class="pawita-lb-next" aria-label="Następne">&#8250;</button>' +
          '<div class="pawita-lb-count"></div>';
        document.body.appendChild(lb);
      }
      var stage = lb.querySelector('.pawita-lb-stage');
      var count = lb.querySelector('.pawita-lb-count');
      var index = start;
      function show(i) {
        index = (i + items.length) % items.length;
        var item = items[index];
        stage.innerHTML = '';
        var el;
        if (item.type === 'video') {
          el = document.createElement('video');
          el.src = item.src; el.controls = true; el.autoplay = true;
          el.playsInline = true; el.poster = item.thumb;
        } else {
          el = document.createElement('img');
          el.src = item.src; el.alt = item.alt;
        }
        stage.appendChild(el);
        count.textContent = (index + 1) + ' / ' + items.length;
      }
      function close() {
        lb.classList.remove('is-open');
        stage.innerHTML = '';
        document.removeEventListener('keydown', onKey);
        document.documentElement.style.overflow = '';
      }
      function onKey(e) {
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') show(index - 1);
        else if (e.key === 'ArrowRight') show(index + 1);
      }
      lb.querySelector('.pawita-lb-close').onclick = close;
      lb.querySelector('.pawita-lb-prev').onclick = function () { show(index - 1); };
      lb.querySelector('.pawita-lb-next').onclick = function () { show(index + 1); };
      lb.onclick = function (e) { if (e.target === lb) close(); };
      var touchX = null;
      lb.ontouchstart = function (e) { touchX = e.touches[0].clientX; };
      lb.ontouchend = function (e) {
        if (touchX === null) return;
        var dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
        touchX = null;
      };
      document.addEventListener('keydown', onKey);
      document.documentElement.style.overflow = 'hidden';
      lb.classList.add('is-open');
      show(start);
    }
    </script>
    <?php
}, 30);
