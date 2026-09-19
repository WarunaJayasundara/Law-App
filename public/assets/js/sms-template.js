// Live preview and SMS-part counter for the Settings page. Mirrors
// SmsTemplate::estimate() on the server (GSM-7 = 160/153 per part,
// Unicode = 70/67 per part) so the number shown while typing matches what
// text.lk bills.
(function () {
  var textarea = document.getElementById('smsTemplate');
  if (!textarea) return;

  var GSM_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
  var GSM_EXTENDED = "^{}\\[~]|€";
  var SAMPLE_DEED_NUMBER = '14367';

  function parts(length, single, multi) {
    if (length === 0) return 0;
    return length <= single ? 1 : Math.ceil(length / multi);
  }

  function estimate(text) {
    text = text.replace(/\r\n?/g, '\n');
    var gsmLength = 0;
    var isGsm = true;
    Array.from(text).forEach(function (ch) {
      if (!isGsm) return;
      if (GSM_BASIC.indexOf(ch) !== -1) gsmLength += 1;
      else if (GSM_EXTENDED.indexOf(ch) !== -1) gsmLength += 2;
      else isGsm = false;
    });
    if (isGsm) return { encoding: 'GSM-7', length: gsmLength, parts: parts(gsmLength, 160, 153) };
    return { encoding: 'Unicode', length: text.length, parts: parts(text.length, 70, 67) };
  }

  function update() {
    var rendered = textarea.value.replace(/\r\n?/g, '\n').trim().split('{deed_number}').join(SAMPLE_DEED_NUMBER);
    var est = estimate(rendered);
    document.getElementById('smsPreview').textContent = rendered;
    document.getElementById('smsChars').textContent = est.length;
    document.getElementById('smsEncoding').textContent = est.encoding;
    document.getElementById('smsParts').textContent = est.parts;
  }

  textarea.addEventListener('input', update);
  update();
})();
