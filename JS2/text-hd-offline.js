(function (global) {
  "use strict";

  function parsePx(value, fallback) {
    var numeric = parseFloat(value);
    return isFinite(numeric) ? numeric : fallback;
  }

  function escapeXml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&apos;");
  }

  function getMeasureContext(doc) {
    var targetDoc = doc || document;
    if (!targetDoc.__offlineTextMeasureCanvas) {
      targetDoc.__offlineTextMeasureCanvas = targetDoc.createElement("canvas");
    }

    return targetDoc.__offlineTextMeasureCanvas.getContext("2d");
  }

  function buildFontShorthand(styles, fontSize) {
    var fontStyle = styles.fontStyle || "normal";
    var fontVariant = styles.fontVariant || "normal";
    var fontWeight = styles.fontWeight || "700";
    var fontFamily = styles.fontFamily || "Arial, sans-serif";

    return [fontStyle, fontVariant, fontWeight, fontSize + "px", fontFamily].join(" ");
  }

  function measureText(text, styles, fontSize, doc) {
    var ctx = getMeasureContext(doc);
    if (!ctx) {
      return String(text).length * fontSize * 0.62;
    }

    ctx.font = buildFontShorthand(styles, fontSize);
    return ctx.measureText(String(text)).width;
  }

  function resolveAlign(target, styles) {
    var align = (target.getAttribute("data-align") || styles.textAlign || "left").toLowerCase();
    if (align !== "center" && align !== "right") {
      return "left";
    }

    return align;
  }

  function buildSvg(target, text) {
    var doc = target.ownerDocument || document;
    var styles = (doc.defaultView || window).getComputedStyle(target);
    var fontSize = parsePx(styles.fontSize, 12);
    var lineHeight = parsePx(styles.lineHeight, Math.round(fontSize * 1.18));
    var color = styles.color || "#000";
    var letterSpacing = styles.letterSpacing && styles.letterSpacing !== "normal" ? styles.letterSpacing : "0";
    var paddingX = Math.max(fontSize * 0.1, 1);
    var containerWidth = Math.ceil(target.getBoundingClientRect().width || target.clientWidth || 0);
    var measuredWidth = Math.ceil(measureText(text, styles, fontSize, doc) + (paddingX * 2));
    var viewWidth = Math.max(containerWidth, measuredWidth, 1);
    var viewHeight = Math.max(Math.ceil(lineHeight), Math.ceil(fontSize * 1.15), 1);
    var align = resolveAlign(target, styles);
    var anchor = align === "right" ? "end" : (align === "center" ? "middle" : "start");
    var preserve = align === "right" ? "xMaxYMid meet" : (align === "center" ? "xMidYMid meet" : "xMinYMid meet");
    var x = align === "right" ? viewWidth - paddingX : (align === "center" ? viewWidth / 2 : paddingX);
    var y = Math.max(fontSize * 0.88, fontSize);
    var strokeWidth = Math.max(fontSize * 0.045, 0.55).toFixed(2);
    var fontFamily = escapeXml(styles.fontFamily || "Arial, sans-serif");
    var fontWeight = escapeXml(styles.fontWeight || "700");

    return '' +
      '<svg xmlns="http://www.w3.org/2000/svg" class="offline-text-svg" width="100%" height="100%" viewBox="0 0 ' + viewWidth + ' ' + viewHeight + '" preserveAspectRatio="' + preserve + '" aria-hidden="true" focusable="false">' +
        '<text x="' + x + '" y="' + y.toFixed(2) + '" fill="' + escapeXml(color) + '" font-family="' + fontFamily + '" font-size="' + fontSize.toFixed(2) + '" font-weight="' + fontWeight + '" letter-spacing="' + escapeXml(letterSpacing) + '" text-anchor="' + anchor + '" text-rendering="geometricPrecision" paint-order="stroke fill" stroke="' + escapeXml(color) + '" stroke-width="' + strokeWidth + '" stroke-linejoin="round" vector-effect="non-scaling-stroke">' + escapeXml(text) + '</text>' +
      '</svg>';
  }

  function render(target, value) {
    if (!target) {
      return;
    }

    var text = typeof value === "string" ? value : (target.getAttribute("data-text") || "");
    if (!text) {
      return;
    }

    target.innerHTML = buildSvg(target, text);
  }

  function renderAll(root, selector) {
    var targetRoot = root && typeof root.querySelectorAll === "function" ? root : document;
    var query = selector || ".hd-text[data-text]";
    var targets = targetRoot.querySelectorAll(query);

    Array.prototype.forEach.call(targets, function (target) {
      render(target, target.getAttribute("data-text") || "");
    });
  }

  global.OfflineTextRenderer = global.OfflineTextRenderer || {
    render: render,
    renderAll: renderAll
  };
})(window);
