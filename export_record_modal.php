<!-- MODAL EXPORT RECORD EXCEL (20 BATCH / 20.000 NOMOR) -->
<div id="excelExportModal" class="modal" style="display: none; z-index: 10000;">
  <div class="modal-content" style="max-width: 580px; width: 92%; margin: 6% auto; text-align: left; box-sizing: border-box; max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--content-line-strong); padding-bottom: 12px; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 17px; color: var(--content-text); display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 20px;">&#128196;</span> Download Record Range Excel (.xls)
      </h3>
      <button type="button" onclick="closeExcelExportModal()" style="background: none; border: none; color: var(--content-muted); font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
    </div>

    <p style="font-size: 13px; color: var(--content-muted); margin-top: 0; margin-bottom: 16px; line-height: 1.5;">
      Unduh laporan rentang barcode per 20 Batch (~20.000 nomor) atau rentang kustom format Excel sesuai lembar cek fisik QC produksi (Format Halaman: <strong>Landscape</strong>).
    </p>

    <!-- Kategori -->
    <div style="margin-bottom: 14px;">
      <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--content-text);">Kategori Produk</label>
      <select id="exportModalCategory" style="width: 100%; height: 40px; box-sizing: border-box; border-radius: 10px; padding: 0 12px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); color: var(--content-text);">
        <option value="pcb">PCB (Smart Meter)</option>
        <option value="ev_charger">EV Charger</option>
        <option value="nameplate">Nameplate</option>
      </select>
    </div>

    <!-- Pilih Work Order -->
    <div style="margin-bottom: 14px;">
      <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--content-text);">Work Order (WO)</label>
      <select id="exportModalWo" style="width: 100%; height: 40px; box-sizing: border-box; border-radius: 10px; padding: 0 12px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); color: var(--content-text);">
        <option value="">-- Memuat daftar WO... --</option>
      </select>
      <div id="exportModalWoHint" style="font-size: 12px; color: var(--content-muted); margin-top: 4px;"></div>
    </div>

    <!-- Judul Header Excel -->
    <div style="margin-bottom: 14px;">
      <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--content-text);">Judul Header Laporan</label>
      <input type="text" id="exportModalTitle" placeholder="Contoh: REPORT PRINT METER NUMBER/PCB NUMBER HXE128KP" style="width: 100%; height: 40px; box-sizing: border-box; border-radius: 10px; padding: 0 12px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); color: var(--content-text); font-size: 13px;">
      <div style="font-size: 11px; color: var(--content-muted); margin-top: 4px;">
        * Tulisan di pojok kiri atas Excel. Sisi kanan otomatis berisi tulisan <strong>Batch [Awal] - [Akhir]</strong> (tanpa Part dan tanpa Tanggal).
      </div>
    </div>

    <!-- Pilihan Batch Range -->
    <div style="margin-bottom: 14px;">
      <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--content-text);">Pilih Rentang Batch</label>
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="flex: 1;">
          <small style="display: block; color: var(--content-muted); margin-bottom: 4px; font-size: 11px;">Dari Batch</small>
          <input type="number" id="exportModalStartBatch" min="1" value="1" style="width: 100%; height: 40px; box-sizing: border-box; border-radius: 10px; padding: 0 12px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); color: var(--content-text); font-weight: 600; font-size: 14px;">
        </div>
        <div style="padding-top: 18px; font-weight: bold; color: var(--content-muted);">&mdash;</div>
        <div style="flex: 1;">
          <small style="display: block; color: var(--content-muted); margin-bottom: 4px; font-size: 11px;">Sampai Batch</small>
          <input type="number" id="exportModalEndBatch" min="1" value="20" style="width: 100%; height: 40px; box-sizing: border-box; border-radius: 10px; padding: 0 12px; background: var(--content-panel-soft); border: 1px solid var(--content-line-strong); color: var(--content-text); font-weight: 600; font-size: 14px;">
        </div>
      </div>

      <!-- Quick Preset 20 Batch -->
      <div style="margin-top: 10px;">
        <small style="display: block; color: var(--content-muted); margin-bottom: 6px; font-size: 11px; font-weight: 600;">Tombol Cepat (Pilihan per 20 Batch):</small>
        <div id="exportModalPresets" style="display: flex; gap: 6px; flex-wrap: wrap;">
          <!-- Dynamically populated -->
        </div>
      </div>
    </div>

    <!-- Opsi Sertakan Tanggal Print -->
    <div style="margin-bottom: 16px;">
      <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: var(--content-text); cursor: pointer;">
        <input type="checkbox" id="exportModalIncludePrintInfo" style="margin-top: 2px;">
        <span>Sertakan Tanggal & Status print di baris data jika sudah pernah di-print di sistem (Biarkan tidak dicentang jika form untuk pengisian manual QC).</span>
      </label>
    </div>

    <!-- Live Preview Info -->
    <div id="exportModalPreview" style="padding: 10px 14px; border-radius: 12px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); color: #10b981; font-size: 13px; margin-bottom: 18px;">
      Pilih WO untuk melihat estimasi nomor barcode.
    </div>

    <!-- Actions -->
    <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--content-line-strong); padding-top: 14px; margin-top: 0;">
      <button type="button" onclick="closeExcelExportModal()" style="height: 42px; padding: 0 18px; border-radius: 999px; background: var(--content-panel-soft); color: var(--content-text); border: 1px solid var(--content-line-strong); font-weight: 600; cursor: pointer;">
        Batal
      </button>
      <button type="button" id="exportModalBtnSubmit" class="primary" style="height: 42px; padding: 0 24px; border-radius: 999px; background: #10b981; border: 1px solid #10b981; color: #ffffff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
        &#128196; Download Excel (.xls)
      </button>
    </div>
  </div>
</div>

<script>
(function() {
  var modal = document.getElementById("excelExportModal");
  var catSelect = document.getElementById("exportModalCategory");
  var woSelect = document.getElementById("exportModalWo");
  var woHint = document.getElementById("exportModalWoHint");
  var titleInput = document.getElementById("exportModalTitle");
  var startInput = document.getElementById("exportModalStartBatch");
  var endInput = document.getElementById("exportModalEndBatch");
  var presetsBox = document.getElementById("exportModalPresets");
  var includeCheck = document.getElementById("exportModalIncludePrintInfo");
  var previewBox = document.getElementById("exportModalPreview");
  var btnSubmit = document.getElementById("exportModalBtnSubmit");

  var currentWoData = null;

  window.openExcelExportModal = function(category, targetWo, targetStart, targetEnd) {
    if (!modal) return;
    modal.style.display = "block";
    if (category && catSelect) {
      catSelect.value = category;
    }
    loadWoList(targetWo, targetStart, targetEnd);
  };

  window.closeExcelExportModal = function() {
    if (modal) modal.style.display = "none";
  };

  // Tutup jika klik di luar modal content
  if (modal) {
    modal.addEventListener("click", function(e) {
      if (e.target === modal) {
        closeExcelExportModal();
      }
    });
  }

  function loadWoList(targetWo, targetStart, targetEnd) {
    if (!catSelect || !woSelect) return;
    var cat = catSelect.value || "pcb";
    woSelect.innerHTML = '<option value="">-- Memuat daftar WO... --</option>';
    woSelect.disabled = true;
    if (woHint) woHint.textContent = "";

    fetch("export_record_excel.php?action=get_wo_list&category=" + encodeURIComponent(cat))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        woSelect.disabled = false;
        if (!data.success || !data.wo_list || data.wo_list.length === 0) {
          woSelect.innerHTML = '<option value="">-- Belum ada WO pada kategori ini --</option>';
          updatePreviewText();
          return;
        }

        var html = '<option value="">-- Pilih Work Order --</option>';
        data.wo_list.forEach(function(item) {
          var sel = (targetWo && item.wo === targetWo) ? " selected" : "";
          var batchLabel = item.total_batches + " Batch";
          var bcLabel = item.total_barcodes.toLocaleString("id-ID") + " nomor";
          html += '<option value="' + escapeHtml(item.wo) + '"' + sel + '>' + escapeHtml(item.wo) + ' (' + batchLabel + ', ' + bcLabel + ')</option>';
        });
        woSelect.innerHTML = html;

        var selectedWo = targetWo || (data.wo_list.length > 0 ? data.wo_list[0].wo : "");
        if (selectedWo) {
          woSelect.value = selectedWo;
          loadWoBatches(selectedWo, targetStart, targetEnd);
        } else {
          updatePreviewText();
        }
      })
      .catch(function(err) {
        woSelect.disabled = false;
        woSelect.innerHTML = '<option value="">Gagal memuat WO</option>';
      });
  }

  function loadWoBatches(wo, targetStart, targetEnd) {
    if (!wo) {
      currentWoData = null;
      if (presetsBox) presetsBox.innerHTML = "";
      updatePreviewText();
      return;
    }

    var cat = catSelect.value || "pcb";
    fetch("export_record_excel.php?action=get_wo_batches&category=" + encodeURIComponent(cat) + "&wo=" + encodeURIComponent(wo))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data.success) {
          alert(data.error || "Gagal memuat batch untuk WO ini.");
          return;
        }
        currentWoData = data;

        if (titleInput && (!titleInput.value || titleInput.dataset.autoFilled === "1")) {
          titleInput.value = data.default_title;
          titleInput.dataset.autoFilled = "1";
        }

        var minB = data.min_batch;
        var maxB = data.max_batch;

        if (startInput) {
          startInput.min = minB;
          startInput.max = maxB;
          startInput.value = targetStart ? Math.max(minB, Math.min(targetStart, maxB)) : minB;
        }

        if (endInput) {
          endInput.min = minB;
          endInput.max = maxB;
          var defaultEnd = targetEnd ? Math.max(minB, Math.min(targetEnd, maxB)) : Math.min(minB + 19, maxB);
          endInput.value = defaultEnd;
        }

        if (woHint) {
          woHint.textContent = "Total: " + data.total_batches + " Batch (Batch " + minB + " s/d Batch " + maxB + ")";
        }

        renderPresets(data.batches, minB, maxB);
        updatePreviewText();
      })
      .catch(function(err) {
        console.error("loadWoBatches error:", err);
      });
  }

  function renderPresets(batches, minB, maxB) {
    if (!presetsBox) return;
    presetsBox.innerHTML = "";

    var step = 20;
    for (var b = minB; b <= maxB; b += step) {
      var s = b;
      var e = Math.min(b + step - 1, maxB);
      var btn = document.createElement("button");
      btn.type = "button";
      btn.style.cssText = "height: 28px; padding: 0 10px; border-radius: 999px; background: var(--content-panel-soft); color: var(--content-text); border: 1px solid var(--content-line-strong); font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s;";
      btn.textContent = (s === e) ? ("Batch " + s) : ("Batch " + s + " - " + e);
      (function(startVal, endVal) {
        btn.addEventListener("click", function() {
          if (startInput) startInput.value = startVal;
          if (endInput) endInput.value = endVal;
          updatePreviewText();
        });
      })(s, e);
      presetsBox.appendChild(btn);
    }

    // Tombol "Semua Batch" jika batch > 20
    if (maxB - minB + 1 > 20) {
      var allBtn = document.createElement("button");
      allBtn.type = "button";
      allBtn.style.cssText = "height: 28px; padding: 0 12px; border-radius: 999px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid #10b981; font-size: 11px; font-weight: bold; cursor: pointer;";
      allBtn.textContent = "Semua Batch (" + minB + " - " + maxB + ")";
      allBtn.addEventListener("click", function() {
        if (startInput) startInput.value = minB;
        if (endInput) endInput.value = maxB;
        updatePreviewText();
      });
      presetsBox.appendChild(allBtn);
    }
  }

  function updatePreviewText() {
    if (!previewBox) return;
    if (!currentWoData || !woSelect || !woSelect.value) {
      previewBox.innerHTML = "Pilih Work Order untuk melihat estimasi.";
      previewBox.style.color = "var(--content-muted)";
      previewBox.style.borderColor = "var(--content-line-strong)";
      return;
    }

    var s = parseInt(startInput.value, 10) || 1;
    var e = parseInt(endInput.value, 10) || 1;
    if (e < s) e = s;

    var selectedCount = 0;
    if (currentWoData.batches) {
      currentWoData.batches.forEach(function(item) {
        if (item.batch_num >= s && item.batch_num <= e) {
          selectedCount++;
        }
      });
    }

    var estQty = selectedCount * 999;
    if (catSelect && catSelect.value === "ev_charger") {
      estQty = selectedCount * 200; // estimasi EV charger ~200 pcs
    }

    previewBox.style.color = "#10b981";
    previewBox.style.borderColor = "rgba(16, 185, 129, 0.25)";
    previewBox.innerHTML = "Terpilih: <strong>" + selectedCount + " Batch</strong> (Batch " + s + " s/d Batch " + e + ") &bull; Estimasi: ~<strong>" + estQty.toLocaleString("id-ID") + " barcode</strong>";
  }

  if (catSelect) {
    catSelect.addEventListener("change", function() {
      loadWoList();
    });
  }

  if (woSelect) {
    woSelect.addEventListener("change", function() {
      loadWoBatches(this.value);
    });
  }

  if (startInput) {
    startInput.addEventListener("input", updatePreviewText);
  }

  if (endInput) {
    endInput.addEventListener("input", updatePreviewText);
  }

  if (titleInput) {
    titleInput.addEventListener("input", function() {
      titleInput.dataset.autoFilled = "0";
    });
  }

  if (btnSubmit) {
    btnSubmit.addEventListener("click", function() {
      var cat = catSelect ? catSelect.value : "pcb";
      var wo = woSelect ? woSelect.value.trim() : "";
      if (!wo) {
        alert("Silakan pilih Work Order (WO) terlebih dahulu.");
        if (woSelect) woSelect.focus();
        return;
      }

      var s = parseInt(startInput ? startInput.value : 1, 10) || 1;
      var e = parseInt(endInput ? endInput.value : 1, 10) || 1;
      if (e < s) {
        alert("Batch akhir harus lebih besar atau sama dengan batch awal.");
        if (endInput) endInput.focus();
        return;
      }

      var title = titleInput ? titleInput.value.trim() : "";
      var inc = (includeCheck && includeCheck.checked) ? "1" : "0";

      var url = "export_record_excel.php?action=download_excel"
        + "&category=" + encodeURIComponent(cat)
        + "&wo=" + encodeURIComponent(wo)
        + "&start_batch=" + encodeURIComponent(s)
        + "&end_batch=" + encodeURIComponent(e)
        + "&title=" + encodeURIComponent(title)
        + "&include_print_info=" + encodeURIComponent(inc);

      // Trigger download di browser
      window.location.href = url;
    });
  }

  function escapeHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
})();
</script>
