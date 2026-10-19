(function (global) {
  "use strict";

  if (global.OfflineQRCode) {
    return;
  }

  function resolveMode(text) {
    if (/^\d+$/.test(text)) {
      return "Numeric";
    }

    if (/^[0-9A-Z $%*+\-./:]+$/.test(text)) {
      return "Alphanumeric";
    }

    return "Byte";
  }

  function buildSvg(payload) {
    if (typeof global.qrcode !== "function") {
      throw new Error("qrcode-generator library is not loaded.");
    }

    var qr = global.qrcode(0, "L");
    qr.addData(payload, resolveMode(payload));
    qr.make();

    return qr.createSvgTag({
      cellSize: 1,
      margin: 1,
      scalable: true
    });
  }

  function render(target, text) {
    if (!target) {
      return;
    }

    var payload = text || target.getAttribute("data-qr") || "";

    if (!payload) {
      return;
    }

    var holder = document.createElement("div");
    holder.innerHTML = buildSvg(payload);

    var svg = holder.firstElementChild;
    if (!svg) {
      return;
    }

    svg.setAttribute("data-qr", payload);
    svg.setAttribute("shape-rendering", "crispEdges");

    var className = target.getAttribute("class");
    if (className) {
      svg.setAttribute("class", className);
    }

    if (target.getAttribute("aria-label")) {
      svg.setAttribute("aria-label", target.getAttribute("aria-label"));
    }

    if (target.parentNode) {
      target.parentNode.replaceChild(svg, target);
    } else {
      target.innerHTML = "";
      target.appendChild(svg);
    }
  }

  function renderAll(selector) {
    var targets = document.querySelectorAll(selector || "svg[data-qr]");

    Array.prototype.forEach.call(targets, function (target) {
      render(target, target.getAttribute("data-qr"));
    });
  }

  global.OfflineQRCode = {
    render: render,
    renderAll: renderAll
  };
})(window);
