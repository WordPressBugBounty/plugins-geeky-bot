(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }
    callback();
  }

  function text(value, fallback) {
    value = String(value || '').trim();
    return value || fallback;
  }

  function setImagePreview(field, attachment) {
    var wrap = field.closest('.gb-media-field');
    if (!wrap) {
      return;
    }
    var preview = wrap.querySelector('[data-gb-media-preview]');
    var remove = wrap.querySelector('[data-gb-media-remove]');
    var url = attachment && attachment.url ? attachment.url : '';
    if (url) {
      preview.innerHTML = '<img src="' + String(url).replace(/"/g, '&quot;') + '" alt="" />';
      preview.classList.add('has-image');
      wrap.classList.add('has-image');
      wrap.classList.remove('is-empty');
      field.value = attachment.id || '';
      if (remove) {
        remove.disabled = false;
      }
      return;
    }
    preview.innerHTML = '<span class="gb-media-field__empty">No image selected</span>';
    preview.classList.remove('has-image');
    wrap.classList.remove('has-image');
    wrap.classList.add('is-empty');
    field.value = '';
    if (remove) {
      remove.disabled = true;
    }
  }

  ready(function () {
    document.querySelectorAll('[data-gb-media-select]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        var wrap = button.closest('.gb-media-field');
        var field = wrap ? wrap.querySelector('[data-gb-media-id]') : null;
        if (!field || !window.wp || !wp.media) {
          return;
        }
        var labels = window.GeekyBotAdmin || {};
        var frame = wp.media({
          title: labels.mediaTitle || 'Choose widget image',
          button: { text: labels.mediaButton || 'Use this image' },
          library: { type: 'image' },
          multiple: false
        });
        frame.on('select', function () {
          var attachment = frame.state().get('selection').first();
          if (!attachment) {
            return;
          }
          attachment = attachment.toJSON();
          setImagePreview(field, attachment);
          field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        frame.open();
      });
    });

    document.querySelectorAll('[data-gb-media-remove]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        var wrap = button.closest('.gb-media-field');
        var field = wrap ? wrap.querySelector('[data-gb-media-id]') : null;
        if (!field) {
          return;
        }
        setImagePreview(field, null);
        field.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  ready(function () {
    var wrap = document.querySelector('.geekybot-admin-widget');
    if (!wrap) {
      return;
    }

    var nameInput = wrap.querySelector('[name="assistant_name"]');
    var subtitleInput = wrap.querySelector('[name="assistant_subtitle"]');
    var welcomeInput = wrap.querySelector('[name="welcome_message"]');
    var colorInput = wrap.querySelector('[name="accent_color"]');
    var launcherStyleInput = wrap.querySelector('[name="launcher_style"]');
    var launcherTextInput = wrap.querySelector('[name="launcher_text"]');
    var headerStyleInput = wrap.querySelector('[name="header_style"]');
    var preview = wrap.querySelector('.gb-widget-preview-stack');
    var previewWindow = wrap.querySelector('.gb-widget-preview');
    var previewName = wrap.querySelector('.gb-widget-preview__header strong');
    var previewSubtitle = wrap.querySelector('.gb-widget-preview__header span:not(.gb-widget-preview__logo)');
    var previewWelcome = wrap.querySelector('.gb-widget-preview__body > p');
    var previewLauncher = wrap.querySelector('[data-gb-preview-launcher]');
    var previewLauncherText = wrap.querySelector('[data-gb-preview-launcher-text]');

    function updatePreview() {
      if (previewName && nameInput) {
        previewName.textContent = text(nameInput.value, 'Geeky Bot');
      }
      if (previewSubtitle && subtitleInput) {
        previewSubtitle.textContent = text(subtitleInput.value, 'WooCommerce shopping assistant');
      }
      if (previewWelcome && welcomeInput) {
        previewWelcome.textContent = text(welcomeInput.value, 'Hi! Ask me what you are looking for and I will help you find the right product.');
      }
      if (preview && colorInput) {
        preview.style.setProperty('--gb-preview-accent', colorInput.value || '#2563eb');
      }
      if (previewLauncher && launcherStyleInput) {
        previewLauncher.classList.toggle('gb-launcher-preview--pill', launcherStyleInput.value === 'pill');
        previewLauncher.classList.toggle('gb-launcher-preview--icon', launcherStyleInput.value !== 'pill');
      }
      if (previewLauncherText && launcherTextInput) {
        previewLauncherText.textContent = text(launcherTextInput.value, 'Ask about products');
      }
      if (previewWindow && headerStyleInput) {
        previewWindow.classList.toggle('gb-widget-preview--solid', headerStyleInput.value === 'solid');
        previewWindow.classList.toggle('gb-widget-preview--gradient', headerStyleInput.value !== 'solid');
      }
    }

    [nameInput, subtitleInput, welcomeInput, colorInput, launcherStyleInput, launcherTextInput, headerStyleInput].forEach(function (field) {
      if (!field) {
        return;
      }
      field.addEventListener('input', updatePreview);
      field.addEventListener('change', updatePreview);
    });

    updatePreview();
  });


  ready(function () {
    document.querySelectorAll('[data-gb-confirm]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        var message = button.getAttribute('data-gb-confirm') || '';
        if (message && !window.confirm(message)) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    });
  });

  ready(function () {
    document.querySelectorAll('[data-gb-copy-demo]').forEach(function (button) {
      button.addEventListener('click', function () {
        var phrase = button.getAttribute('data-gb-copy-demo') || '';
        var labels = window.GeekyBotAdmin || {};
        if (!phrase) {
          return;
        }

        function markCopied() {
          var original = button.getAttribute('data-gb-copy-label') || button.textContent;
          button.setAttribute('data-gb-copy-label', original);
          button.textContent = labels.copiedDemo || 'Copied';
          window.setTimeout(function () {
            button.textContent = original || labels.copyDemo || 'Copy';
          }, 1600);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(phrase).then(markCopied).catch(function () {});
          return;
        }

        var textarea = document.createElement('textarea');
        textarea.value = phrase;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
          document.execCommand('copy');
          markCopied();
        } catch (error) {}
        document.body.removeChild(textarea);
      });
    });
  });

})();
