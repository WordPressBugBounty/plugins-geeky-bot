/**
 * Geeky Bot → AI & Privacy: create or connect a Zywrap account without
 * leaving wp-admin. Step 1 asks Zywrap to email a 6-digit code; step 2 trades
 * the code for this site's key (see ZywrapConnectService.php).
 *
 * The box sits inside the settings form, so Enter in its fields runs the step
 * instead of submitting the settings.
 */
(function () {
  'use strict';

  var box = document.getElementById('gb-zywrap-connect');
  var config = window.GeekyBotZywrap;
  if (!box || !config || !box.querySelector('[data-step="email"]')) {
    return;
  }

  // A store answering through OpenAI sees the box only once it picks Zywrap.
  if (box.getAttribute('data-only-when-chosen') === '1') {
    document.querySelectorAll('input[name="provider_mode"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        box.hidden = !(radio.checked && radio.value === 'zywrap');
      });
    });
  }

  var i18n = config.i18n || {};
  var stepEmail = box.querySelector('[data-step="email"]');
  var stepCode = box.querySelector('[data-step="code"]');
  var message = box.querySelector('.gb2-zywrap__message');
  var sent = box.querySelector('.gb2-zywrap__sent');
  var email = document.getElementById('gb-zywrap-email');
  var name = document.getElementById('gb-zywrap-name');
  var accept = document.getElementById('gb-zywrap-accept');
  var code = document.getElementById('gb-zywrap-code');
  var busy = false;

  function say(text, isError) {
    message.textContent = text || '';
    message.className = 'gb2-zywrap__message' + (isError ? ' is-error' : '');
  }

  function post(action, fields) {
    var data = new FormData();
    data.append('action', action);
    data.append('nonce', config.nonce);
    Object.keys(fields).forEach(function (key) {
      data.append(key, fields[key]);
    });
    return fetch(config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (response) { return response.json(); });
  }

  function run(button, action, fields, onSuccess) {
    if (busy) {
      return;
    }
    busy = true;
    button.disabled = true;
    say(i18n.working || '');
    post(action, fields)
      .then(function (result) {
        if (result && result.success) {
          onSuccess(result.data || {});
        } else {
          say((result && result.data && result.data.message) || i18n.failed, true);
        }
      })
      .catch(function () {
        say(i18n.failed, true);
      })
      .then(function () {
        busy = false;
        button.disabled = false;
      });
  }

  function sendCode(button, isResend) {
    if (!accept.checked) {
      say(i18n.needAccept, true);
      accept.focus();
      return;
    }
    run(button, 'geekybot_zywrap_signup_start', {
      email: email.value.trim(),
      name: name.value.trim(),
      accept: '1',
      from: config.from || ''
    }, function (data) {
      var template = data.existingAccount ? i18n.sentExisting : i18n.sentNew;
      sent.textContent = (template || '').replace('%s', data.email || email.value.trim());
      stepEmail.hidden = true;
      stepCode.hidden = false;
      say(isResend ? i18n.resent : '');
      code.value = '';
      code.focus();
    });
  }

  function verify(button) {
    run(button, 'geekybot_zywrap_signup_verify', { code: code.value }, function (data) {
      window.location.href = data.redirect || window.location.href;
    });
  }

  var sendButton = box.querySelector('.gb2-zywrap__send');
  var verifyButton = box.querySelector('.gb2-zywrap__verify');

  sendButton.addEventListener('click', function () { sendCode(sendButton, false); });
  verifyButton.addEventListener('click', function () { verify(verifyButton); });
  box.querySelector('.gb2-zywrap__resend').addEventListener('click', function (event) {
    sendCode(event.currentTarget, true);
  });
  box.querySelector('.gb2-zywrap__back').addEventListener('click', function () {
    stepCode.hidden = true;
    stepEmail.hidden = false;
    say('');
    email.focus();
  });

  [email, name].forEach(function (field) {
    field.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        sendCode(sendButton, false);
      }
    });
  });
  code.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      verify(verifyButton);
    }
  });
}());
