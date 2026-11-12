<?php
/**
 * Plugin Name: Pawita – przycisk galerii (Staffa)
 * Description: Dodaje przycisk GALERIA pod opisem inwestycji i przewija do galerii na tej samej stronie.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) { exit; }
add_action('wp_footer', function () {
    if (!is_page('domy-na-staffa')) { return; }
    ?>
    <style id="pawita-staffa-gallery-css">
      .pawita-staffa-gallery-link-wrap { text-align:center; margin:30px 0; }
      .pawita-staffa-gallery-link {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:265px; min-height:49px; padding:12px 28px;
        border-radius:999px; background:#ff1955; color:#fff !important;
        font-size:14px; font-weight:700; line-height:1.2;
        text-decoration:none !important; transition:background .2s, transform .2s;
      }
      .pawita-staffa-gallery-link:hover,.pawita-staffa-gallery-link:focus-visible {
        background:#ec0f4b; transform:translateY(-1px);
      }
      #pawita-staffa-gallery { scroll-margin-top:100px; }
      @media (prefers-reduced-motion:no-preference) { html {scroll-behavior:smooth;} }
    </style>
    <script id="pawita-staffa-gallery-js">
    document.addEventListener('DOMContentLoaded', function () {
      if (document.querySelector('.pawita-staffa-gallery-link-wrap')) return;
      var paragraphs=Array.from(document.querySelectorAll('p'));
      var last=paragraphs.find(function (p) {
        return p.textContent.includes('Lokalizacja inwestycji umożliwia wygodny dostęp do infrastruktury miejskiej Wejherowa');
      });
      if (!last) return;

      var selectors=[
        '.elementor-widget-image-gallery', '.elementor-widget-gallery',
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
      // Do not introduce a broken navigation button if the gallery cannot be identified.
      if (!target) return;
      target.id='pawita-staffa-gallery';
      var wrap=document.createElement('div');
      wrap.className='pawita-staffa-gallery-link-wrap';
      var a=document.createElement('a');
      a.className='pawita-staffa-gallery-link';
      a.href='#pawita-staffa-gallery';
      a.textContent='GALERIA';
      wrap.appendChild(a);
      last.insertAdjacentElement('afterend',wrap);
    });
    </script>
    <?php
}, 30);
