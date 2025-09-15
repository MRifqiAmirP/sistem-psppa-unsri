const placeColors = {
  industri: {
    variations: [
      "#1e3a8a",
      "#2563eb",
      "#3b82f6",
      "#60a5fa",
      "#93c5fd",
      "#bfdbfe",
      "#dbeafe",
    ],
  },
  "rumah-sakit": {
    variations: [
      "#166534",
      "#22c55e",
      "#4ade80",
      "#86efac",
      "#bbf7d0",
      "#dcfce7",
      "#f0fdf4",
    ],
  },
  apotek: {
    variations: [
      "#581c87",
      "#7e22ce",
      "#a855f7",
      "#c084fc",
      "#d8b4fe",
      "#f3e8ff",
      "#faf5ff",
    ],
  },
  bpom: {
    variations: [
      "#9a3412",
      "#ea580c",
      "#fb923c",
      "#fdba74",
      "#fed7aa",
      "#ffedd5",
      "#fff7ed",
    ],
  },
  pbf: {
    variations: [
      "#854d0e",
      "#ca8a04",
      "#eab308",
      "#facc15",
      "#fde047",
      "#fef08a",
      "#fefce8",
    ],
  },
  dinkes: {
    variations: [
      "#991b1b",
      "#dc2626",
      "#ef4444",
      "#f87171",
      "#fca5a5",
      "#fecaca",
      "#fee2e2",
    ],
  },
  puskesmas: {
    variations: [
      "#f59e0b",
      "#facc15",
      "#fde047",
      "#fef08a",
      "#fefce8",
      "#fefce8",
      "#fefce8",
    ],
  },
};

let selectedTempatId = null;
let selectedColor = null;
let selectedNamaTempat = null;
let selectedMahasiswaId = null;
let startDate = null;
let hoverTimeout = null;
const HOVER_DELAY = 2000;

function updateHiddenFields() {
  document.getElementById("hidden_id_tempat").value = selectedTempatId || "";
  document.getElementById("hidden_start_date").value = startDate || "";
  document.getElementById("hidden_mahasiswa_id").value =
    selectedMahasiswaId || "";
  document.getElementById("hidden_id_dosen_pembimbing").value =
    document.getElementById("dosen_pembimbing")?.value || "";
}

function setTempatColor(select) {
  document.querySelectorAll(".dropdown-item select").forEach((sel) => {
    if (sel !== select && sel.id !== "dosen_pembimbing") sel.value = "";
    const jenis = sel.dataset.jenis;
    sel.className = jenis ? `dropdown-${jenis}` : "default-dropdown";
  });

  selectedTempatId = select.value ? parseInt(select.value) : null;
  selectedNamaTempat = select.selectedOptions[0]?.dataset.nama || "";
  const jenis = select.dataset.jenis;
  const index =
    Array.from(select.options)
      .filter((opt) => opt.value !== "")
      .indexOf(select.selectedOptions[0]) %
      placeColors[jenis]?.variations.length || 0;
  selectedColor = selectedTempatId
    ? placeColors[jenis]?.variations[index] || "#ccc"
    : null;

  if (selectedTempatId) {
    localStorage.setItem("selectedTempatId", selectedTempatId);
    localStorage.setItem("selectedNamaTempat", selectedNamaTempat);
    localStorage.setItem("selectedJenisTempat", jenis);
    const url = new URL(window.location);
    url.searchParams.set("id_tempat", selectedTempatId);
    window.history.pushState({}, "", url);
  } else {
    localStorage.removeItem("selectedTempatId");
    localStorage.removeItem("selectedNamaTempat");
    localStorage.removeItem("selectedJenisTempat");
    const url = new URL(window.location);
    url.searchParams.delete("id_tempat");
    window.history.pushState({}, "", url);
  }
  updateHiddenFields();

  if (!selectedTempatId) {
    clearSelection();
    startDate = null;
    selectedMahasiswaId = null;
    localStorage.removeItem("startDate");
    localStorage.removeItem("selectedMahasiswaId");
    const url = new URL(window.location);
    url.searchParams.delete("start_date");
    url.searchParams.delete("mahasiswa_id");
    window.history.pushState({}, "", url);
    updateHiddenFields();
  } else if (startDate && selectedMahasiswaId) {
    updateRangePreview(startDate, startDate);
  }
}

function colorScheduleTable() {
  document.querySelectorAll(".jadwalTable td.scheduled").forEach((cell) => {
    const jenis = cell.dataset.jenis;
    const namaTempat = cell.textContent;
    const index =
      Array.from(
        document.querySelectorAll(`select[data-jenis="${jenis}"] option`)
      )
        .filter((opt) => opt.dataset.nama === namaTempat)
        .map((opt) => Array.from(opt.parentNode.options).indexOf(opt) - 1)[0] ||
      0;
    cell.style.backgroundColor =
      placeColors[jenis]?.variations[index] || "#ccc";
  });

  if (startDate && selectedMahasiswaId && selectedTempatId) {
    updateRangePreview(startDate, startDate);
  }
}

function handleCellSelection() {
  const cells = document.querySelectorAll(".jadwalTable td[data-date]");
  cells.forEach((cell) => {
    cell.addEventListener("click", (e) => {
      e.preventDefault();
      if (!selectedTempatId) {
        alert("Pilih tempat dulu");
        return;
      }
      const idMahasiswa = parseInt(cell.parentNode.dataset.mahasiswaId);
      const tanggal = cell.dataset.date;
      if (cell.classList.contains("scheduled")) {
        submitForm("hapus", idMahasiswa, null, tanggal, tanggal);
        return;
      }
      if (!startDate) {
        selectedMahasiswaId = idMahasiswa;
        startDate = tanggal;
        localStorage.setItem("startDate", startDate);
        localStorage.setItem("selectedMahasiswaId", selectedMahasiswaId);
        const url = new URL(window.location);
        url.searchParams.set("start_date", startDate);
        url.searchParams.set("mahasiswa_id", selectedMahasiswaId);
        window.history.pushState({}, "", url);
        updateHiddenFields();
        updateRangePreview(tanggal, tanggal);
      } else {
        if (idMahasiswa !== selectedMahasiswaId) {
          alert("Tanggal mulai dan selesai harus untuk mahasiswa yang sama");
          clearSelection();
          startDate = null;
          selectedMahasiswaId = null;
          localStorage.removeItem("startDate");
          localStorage.removeItem("selectedMahasiswaId");
          const url = new URL(window.location);
          url.searchParams.delete("start_date");
          url.searchParams.delete("mahasiswa_id");
          window.history.pushState({}, "", url);
          updateHiddenFields();
          return;
        }
        const endDate = tanggal;
        if (startDate > endDate) {
          alert("Tanggal mulai tidak boleh setelah tanggal selesai");
          clearSelection();
          startDate = null;
          selectedMahasiswaId = null;
          localStorage.removeItem("startDate");
          localStorage.removeItem("selectedMahasiswaId");
          const url = new URL(window.location);
          url.searchParams.delete("start_date");
          url.searchParams.delete("mahasiswa_id");
          window.history.pushState({}, "", url);
          updateHiddenFields();
          return;
        }
        updateRangePreview(startDate, endDate);
        submitForm("tambah", idMahasiswa, selectedTempatId, startDate, endDate);
        localStorage.removeItem("startDate");
        localStorage.removeItem("selectedMahasiswaId");
        const url = new URL(window.location);
        url.searchParams.delete("start_date");
        url.searchParams.delete("mahasiswa_id");
        window.history.pushState({}, "", url);
        updateHiddenFields();
        startDate = null;
        selectedMahasiswaId = null;
      }
    });

    cell.addEventListener("mouseenter", (e) => {
      if (cell.classList.contains("scheduled")) {
        hoverTimeout = setTimeout(() => showScheduleInfo(cell, e), HOVER_DELAY);
      }
    });

    cell.addEventListener("mouseleave", () => {
      clearTimeout(hoverTimeout);
      hideScheduleInfo();
    });
  });
}

function updateRangePreview(startDate, endDate) {
  clearSelection();
  const minDate = new Date(Math.min(new Date(startDate), new Date(endDate)));
  const maxDate = new Date(Math.max(new Date(startDate), new Date(endDate)));
  const cells = document.querySelectorAll(
    `tr[data-mahasiswa-id="${selectedMahasiswaId}"] td[data-date]`
  );
  cells.forEach((cell) => {
    if (cell.dataset.date) {
      const cellDate = new Date(cell.dataset.date);
      if (
        cellDate >= minDate &&
        cellDate <= maxDate &&
        !cell.classList.contains("scheduled")
      ) {
        cell.classList.add("selected");
        cell.style.backgroundColor = selectedColor;
        cell.textContent = selectedNamaTempat;
      }
    }
  });
}

function showScheduleInfo(cell, event) {
  const jenis = cell.dataset.jenis.replace(/-/g, " ");
  const namaTempat = cell.textContent;
  const mulai = cell.dataset.mulai;
  const selesai = cell.dataset.selesai;
  const namaDosen = cell.dataset.dosen || "Tidak ada";
  const tooltip = document.createElement("div");
  tooltip.id = "schedule-tooltip";
  tooltip.style.cssText =
    "position:absolute;background:#333;color:#fff;padding:8px;border-radius:4px;z-index:1000;font-size:14px;max-width:300px;box-shadow:0 2px 5px rgba(0,0,0,0.2);";
  tooltip.innerHTML = `<strong>Tempat:</strong> ${namaTempat}<br><strong>Jenis:</strong> ${jenis}<br><strong>Rentang:</strong> ${mulai} hingga ${selesai}<br><strong>Dosen:</strong> ${namaDosen}`;
  document.body.appendChild(tooltip);
  tooltip.style.left = `${event.clientX + 10}px`;
  tooltip.style.top = `${event.clientY + 10}px`;
}

function hideScheduleInfo() {
  const tooltip = document.getElementById("schedule-tooltip");
  if (tooltip) tooltip.remove();
}

function confirmDelete() {
  return confirm("Apakah Anda yakin ingin menghapus jadwal ini?");
}

function submitForm(
  action,
  idMahasiswa,
  idTempat,
  tanggalMulai = null,
  tanggalSelesai = null
) {
  const dateFormatRegex = /^\d{4}-\d{2}-\d{2}$/;
  if (action === "tambah") {
    if (!idMahasiswa || isNaN(idMahasiswa))
      return alert("Error: ID mahasiswa tidak valid");
    if (!idTempat || isNaN(idTempat))
      return alert("Error: Tidak ada tempat yang dipilih");
    if (
      !dateFormatRegex.test(tanggalMulai) ||
      !dateFormatRegex.test(tanggalSelesai)
    )
      return alert("Error: Format tanggal tidak valid");
    if (tanggalMulai > tanggalSelesai)
      return alert("Error: Tanggal mulai tidak boleh setelah tanggal selesai");
  } else if (action === "hapus") {
    if (!idMahasiswa || isNaN(idMahasiswa))
      return alert("Error: ID mahasiswa tidak valid");
    if (!dateFormatRegex.test(tanggalMulai))
      return alert("Error: Format tanggal tidak valid");
  }
  const formData = new FormData();
  formData.append("action", action);
  formData.append("id_mahasiswa", idMahasiswa);
  if (idTempat) formData.append("id_tempat", idTempat);
  if (action === "tambah") {
    const idDosen = document.getElementById("dosen_pembimbing")?.value;
    if (idDosen && !isNaN(parseInt(idDosen)))
      formData.append("id_dosen_pembimbing", idDosen);
    formData.append("tanggal_mulai_0", tanggalMulai);
    formData.append("tanggal_selesai_0", tanggalSelesai);
  } else if (action === "hapus") formData.append("tanggal", tanggalMulai);
  fetch("tambah_jadwal.php", { method: "POST", body: formData })
    .then((response) =>
      response.text().then((text) => ({
        status: response.status,
        data: text ? JSON.parse(text) : null,
        rawText: text,
      }))
    )
    .then(({ status, data }) => {
      if (status >= 200 && status < 300) {
        if (data?.success) alert("Jadwal berhasil disimpan!");
        else if (action === "hapus") alert("Jadwal berhasil dihapus!");
        window.location.reload();
      } else {
        let errorMsg =
          data?.error || `HTTP ${status}: Kesalahan tidak diketahui`;
        if (data?.conflicts?.length > 0)
          errorMsg += `\nKonflik: ${data.conflicts
            .map((c) => `${c.tanggal_mulai} hingga ${c.tanggal_selesai}`)
            .join(", ")}`;
        alert("Error: " + errorMsg);
        clearSelection();
        startDate = null;
        selectedMahasiswaId = null;
        localStorage.removeItem("startDate");
        localStorage.removeItem("selectedMahasiswaId");
        updateHiddenFields();
      }
    })
    .catch((error) => {
      alert("Terjadi kesalahan: " + error.message);
      clearSelection();
      startDate = null;
      selectedMahasiswaId = null;
      localStorage.removeItem("startDate");
      localStorage.removeItem("selectedMahasiswaId");
      updateHiddenFields();
    });
}

function clearSelection() {
  document.querySelectorAll(".jadwalTable td[data-date]").forEach((cell) => {
    if (!cell.classList.contains("scheduled")) {
      cell.classList.remove("selected");
      cell.style.backgroundColor = "";
      cell.textContent = "";
    }
  });
}

function setupZoom() {
  const zoomWrapper = document.getElementById("zoomWrapper");
  const zoomInBtn = document.getElementById("zoom-in");
  const zoomOutBtn = document.getElementById("zoom-out");
  if (!zoomWrapper || !zoomInBtn || !zoomOutBtn) return;
  let zoomLevel = parseFloat(zoomWrapper.dataset.zoom) || 1;
  function updateZoom() {
    zoomWrapper.style.transform = `scale(${zoomLevel})`;
    zoomWrapper.style.width = `${1500 * zoomLevel}px`;
    zoomWrapper.dataset.zoom = zoomLevel;
  }
  zoomInBtn.addEventListener("click", () => {
    zoomLevel = Math.min(zoomLevel + 0.1, 2);
    updateZoom();
  });
  zoomOutBtn.addEventListener("click", () => {
    zoomLevel = Math.max(zoomLevel - 0.1, 0.5);
    updateZoom();
  });
  zoomWrapper.addEventListener(
    "wheel",
    (e) => {
      e.preventDefault();
      const delta = e.deltaY > 0 ? -0.05 : 0.05;
      zoomLevel = Math.max(0.5, Math.min(2, zoomLevel + delta));
      updateZoom();
    },
    { passive: false }
  );
}

document.addEventListener("DOMContentLoaded", () => {
  // Reset selectedTempatId untuk jadwal.php agar dropdown tempat default ke "Semua Tempat"
  if (window.location.pathname.includes("jadwal.php")) {
    localStorage.removeItem("selectedTempatId");
    localStorage.removeItem("selectedNamaTempat");
    localStorage.removeItem("selectedJenisTempat");
    selectedTempatId = null;
    selectedNamaTempat = null;
    selectedColor = null;
  }

  const urlParams = new URLSearchParams(window.location.search);
  const savedStartDate = urlParams.get("start_date");
  const savedMahasiswaId = urlParams.get("mahasiswa_id");
  const savedTempatId =
    urlParams.get("id_tempat") || localStorage.getItem("selectedTempatId");
  const savedDosenId = urlParams.get("id_dosen_pembimbing");

  // Restore dosen
  if (savedDosenId) {
    document.getElementById("dosen_pembimbing").value = savedDosenId;
    localStorage.setItem("selectedDosenId", savedDosenId);
  }

  // Restore tempat hanya jika di halaman selain jadwal.php
  if (!window.location.pathname.includes("jadwal.php") && savedTempatId) {
    const select = document.querySelector(
      `select option[value="${savedTempatId}"]`
    )?.parentNode;
    if (select) {
      select.value = savedTempatId;
      selectedTempatId = parseInt(savedTempatId);
      selectedNamaTempat =
        select.selectedOptions[0]?.dataset.nama ||
        localStorage.getItem("selectedNamaTempat") ||
        "";
      const jenis =
        select.dataset.jenis || localStorage.getItem("selectedJenisTempat");
      const index =
        Array.from(select.options)
          .filter((opt) => opt.value !== "")
          .indexOf(select.selectedOptions[0]) %
          placeColors[jenis]?.variations.length || 0;
      selectedColor = placeColors[jenis]?.variations[index] || "#ccc";
      document.querySelectorAll(".dropdown-item select").forEach((sel) => {
        if (sel !== select && sel.id !== "dosen_pembimbing") sel.value = "";
        const jenisSel = sel.dataset.jenis;
        sel.className = jenisSel ? `dropdown-${jenisSel}` : "default-dropdown";
      });
      localStorage.setItem("selectedTempatId", selectedTempatId);
      localStorage.setItem("selectedNamaTempat", selectedNamaTempat);
      localStorage.setItem("selectedJenisTempat", jenis);
    }
  }

  // Restore startDate dan mahasiswaId
  if (
    savedStartDate &&
    savedMahasiswaId &&
    /^\d{4}-\d{2}-\d{2}$/.test(savedStartDate)
  ) {
    startDate = savedStartDate;
    selectedMahasiswaId = parseInt(savedMahasiswaId);
    localStorage.setItem("startDate", startDate);
    localStorage.setItem("selectedMahasiswaId", selectedMahasiswaId);
  }

  updateHiddenFields();
  colorScheduleTable();
  handleCellSelection();
  setupZoom();
  const style = document.createElement("style");
  style.textContent = `#schedule-tooltip {background:#333;color:#fff;padding:8px;border-radius:4px;z-index:1000;font-size:14px;max-width:300px;box-shadow:0 2px 5px rgba(0,0,0,0.2);}`;
  document.head.appendChild(style);
});
