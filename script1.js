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
  const hiddenTempat = document.getElementById("hidden_id_tempat");
  if (hiddenTempat) hiddenTempat.value = selectedTempatId || "";
  const hiddenStartDate = document.getElementById("hidden_start_date");
  if (hiddenStartDate) hiddenStartDate.value = startDate || "";
  const hiddenMahasiswaId = document.getElementById("hidden_mahasiswa_id");
  if (hiddenMahasiswaId) hiddenMahasiswaId.value = selectedMahasiswaId || "";
  const hiddenDosen = document.getElementById("hidden_id_dosen_pembimbing");
  if (hiddenDosen)
    hiddenDosen.value =
      document.getElementById("dosen_pembimbing")?.value || "";
  const hiddenPreceptor1 = document.getElementById("hidden_id_preceptor1");
  if (hiddenPreceptor1)
    hiddenPreceptor1.value = document.getElementById("preceptor1")?.value || "";
  const hiddenPreceptor2 = document.getElementById("hidden_id_preceptor2");
  if (hiddenPreceptor2)
    hiddenPreceptor2.value = document.getElementById("preceptor2")?.value || "";
}

function setTempatColor(select) {
  console.log("setTempatColor called:", {
    value: select.value,
    nama: select.selectedOptions[0]?.dataset.nama,
    jenis: select.dataset.jenis,
  });

  // Hanya reset dropdown lain jika diperlukan
  document.querySelectorAll(".dropdown-item select").forEach((sel) => {
    if (
      sel !== select &&
      sel.id !== "dosen_pembimbing" &&
      sel.id !== "preceptor1" &&
      sel.id !== "preceptor2"
    ) {
      if (sel.value && sel.value !== select.value) return; // Jangan reset jika sudah ada nilai
      sel.value = "";
      const jenis = sel.dataset.jenis;
      sel.className = jenis ? `dropdown-${jenis}` : "default-dropdown";
    }
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
    window.history.replaceState({}, "", url);
    // Hapus submit otomatis untuk mencegah reload
    // document.getElementById("filterForm").submit();
    // Muat ulang preceptor berdasarkan id_tempat
    fetchPreceptors(selectedTempatId);
  } else {
    localStorage.removeItem("selectedTempatId");
    localStorage.removeItem("selectedNamaTempat");
    localStorage.removeItem("selectedJenisTempat");
    const url = new URL(window.location);
    url.searchParams.delete("id_tempat");
    url.searchParams.delete("id_preceptor1");
    url.searchParams.delete("id_preceptor2");
    window.history.replaceState({}, "", url);
    document.getElementById("preceptor1").innerHTML =
      '<option value="">Pilih Preceptor 1 (Opsional)</option><option value="" disabled>Pilih tempat terlebih dahulu</option>';
    document.getElementById("preceptor2").innerHTML =
      '<option value="">Pilih Preceptor 2 (Opsional)</option><option value="" disabled>Pilih tempat terlebih dahulu</option>';
    updateHiddenFields();
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
    window.history.replaceState({}, "", url);
    updateHiddenFields();
  } else if (startDate && selectedMahasiswaId) {
    updateRangePreview(startDate, startDate);
  }
}

function fetchPreceptors(idTempat) {
  if (!idTempat) return;
  fetch(`tambah_jadwal.php?id_tempat=${idTempat}&get_preceptors=1`, {
    method: "GET",
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Preceptors fetched:", data);
      const preceptor1Select = document.getElementById("preceptor1");
      const preceptor2Select = document.getElementById("preceptor2");
      preceptor1Select.innerHTML =
        '<option value="">Pilih Preceptor 1 (Opsional)</option>';
      preceptor2Select.innerHTML =
        '<option value="">Pilih Preceptor 2 (Opsional)</option>';
      if (data.length === 0) {
        preceptor1Select.innerHTML +=
          '<option value="" disabled>Tidak ada preceptor untuk tempat ini</option>';
        preceptor2Select.innerHTML +=
          '<option value="" disabled>Tidak ada preceptor untuk tempat ini</option>';
      } else {
        data.forEach((preceptor) => {
          const option1 = document.createElement("option");
          option1.value = preceptor.id;
          option1.textContent = preceptor.nama;
          preceptor1Select.appendChild(option1);
          const option2 = document.createElement("option");
          option2.value = preceptor.id;
          option2.textContent = preceptor.nama;
          preceptor2Select.appendChild(option2);
        });
      }
      // Pulihkan pilihan preceptor jika ada
      const savedPreceptor1Id = localStorage.getItem("selectedPreceptor1Id");
      const savedPreceptor2Id = localStorage.getItem("selectedPreceptor2Id");
      if (savedPreceptor1Id) preceptor1Select.value = savedPreceptor1Id;
      if (savedPreceptor2Id) preceptor2Select.value = savedPreceptor2Id;
      updateHiddenFields();
    })
    .catch((error) => {
      console.error("Error fetching preceptors:", error);
      alert("Gagal memuat preceptor. Silakan coba lagi.");
    });
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

function restoreTempatSelection() {
  console.log("restoreTempatSelection called");
  const hiddenTempat = document.getElementById("hidden_id_tempat");
  const storedTempatId = localStorage.getItem("selectedTempatId");
  const urlParams = new URLSearchParams(window.location.search);
  const savedTempatId = urlParams.get("id_tempat") || storedTempatId;

  if (savedTempatId) {
    selectedTempatId = parseInt(savedTempatId);
    const select = document.querySelector(
      `select option[value="${savedTempatId}"]`
    )?.parentNode;
    if (select) {
      select.value = savedTempatId;
      selectedNamaTempat =
        select.selectedOptions[0]?.dataset.nama ||
        localStorage.getItem("selectedNamaTempat") ||
        "";
      const jenis =
        select.dataset.jenis ||
        localStorage.getItem("selectedJenisTempat") ||
        "";
      const index =
        Array.from(select.options)
          .filter((opt) => opt.value !== "")
          .indexOf(select.selectedOptions[0]) %
          placeColors[jenis]?.variations.length || 0;
      selectedColor = placeColors[jenis]?.variations[index] || "#ccc";
      localStorage.setItem("selectedTempatId", selectedTempatId);
      localStorage.setItem("selectedNamaTempat", selectedNamaTempat);
      localStorage.setItem("selectedJenisTempat", jenis);
      const url = new URL(window.location);
      url.searchParams.set("id_tempat", selectedTempatId);
      window.history.replaceState({}, "", url);
      if (hiddenTempat) hiddenTempat.value = selectedTempatId;
      fetchPreceptors(selectedTempatId); // Muat preceptor saat restore
      console.log("Tempat restored:", {
        id: selectedTempatId,
        nama: selectedNamaTempat,
        jenis,
      });
    } else {
      console.warn("No select found for savedTempatId:", savedTempatId);
    }
  }
  updateHiddenFields();
}

function handleCellSelection() {
  const cells = document.querySelectorAll(".jadwalTable td[data-date]");
  cells.forEach((cell) => {
    cell.addEventListener("click", (e) => {
      e.preventDefault();
      const idMahasiswa = parseInt(cell.parentNode.dataset.mahasiswaId);
      const tanggal = cell.dataset.date;
      if (cell.classList.contains("scheduled")) {
        if (confirmDelete()) {
          submitForm("hapus", idMahasiswa, null, tanggal, tanggal);
        }
        return;
      }
      if (!selectedTempatId) {
        restoreTempatSelection();
        if (!selectedTempatId) {
          alert("Pilih tempat dulu");
          return;
        }
      }
      if (!startDate) {
        selectedMahasiswaId = idMahasiswa;
        startDate = tanggal;
        localStorage.setItem("startDate", startDate);
        localStorage.setItem("selectedMahasiswaId", selectedMahasiswaId);
        const url = new URL(window.location);
        url.searchParams.set("start_date", startDate);
        url.searchParams.set("mahasiswa_id", selectedMahasiswaId);
        window.history.replaceState({}, "", url);
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
          window.history.replaceState({}, "", url);
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
          window.history.replaceState({}, "", url);
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
        window.history.replaceState({}, "", url);
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
  const namaPreceptor1 = cell.dataset.preceptor1 || "Tidak ada";
  const namaPreceptor2 = cell.dataset.preceptor2 || "Tidak ada";
  const tooltip = document.createElement("div");
  tooltip.id = "schedule-tooltip";
  tooltip.style.cssText =
    "position:absolute;background:#333;color:#fff;padding:8px;border-radius:4px;z-index:1000;font-size:14px;max-width:300px;box-shadow:0 2px 5px rgba(0,0,0,0.2);";
  tooltip.innerHTML = `<strong>Tempat:</strong> ${namaTempat}<br><strong>Jenis:</strong> ${jenis}<br><strong>Rentang:</strong> ${mulai} hingga ${selesai}<br><strong>Dosen:</strong> ${namaDosen}<br><strong>Preceptor 1:</strong> ${namaPreceptor1}<br><strong>Preceptor 2:</strong> ${namaPreceptor2}`;
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
    const idPreceptor1 = document.getElementById("preceptor1")?.value;
    const idPreceptor2 = document.getElementById("preceptor2")?.value;
    if (idDosen && !isNaN(parseInt(idDosen)))
      formData.append("id_dosen_pembimbing", idDosen);
    if (idPreceptor1 && !isNaN(parseInt(idPreceptor1)))
      formData.append("id_preceptor1", idPreceptor1);
    if (idPreceptor2 && !isNaN(parseInt(idPreceptor2)))
      formData.append("id_preceptor2", idPreceptor2);
    formData.append("tanggal_mulai_0", tanggalMulai);
    formData.append("tanggal_selesai_0", tanggalSelesai);
  } else if (action === "hapus") formData.append("tanggal", tanggalMulai);
  const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
  if (csrfToken) formData.append("csrf_token", csrfToken);
  fetch("tambah_jadwal.php", { method: "POST", body: formData })
    .then((response) =>
      response
        .text()
        .then((text) => ({
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
        if (action === "tambah") {
          clearSelection();
          startDate = null;
          selectedMahasiswaId = null;
          localStorage.removeItem("startDate");
          localStorage.removeItem("selectedMahasiswaId");
          const url = new URL(window.location);
          url.searchParams.delete("start_date");
          url.searchParams.delete("mahasiswa_id");
          window.history.replaceState({}, "", url);
          updateHiddenFields();
        }
      }
    })
    .catch((error) => {
      alert("Terjadi kesalahan: " + error.message);
      if (action === "tambah") {
        clearSelection();
        startDate = null;
        selectedMahasiswaId = null;
        localStorage.removeItem("startDate");
        localStorage.removeItem("selectedMahasiswaId");
        const url = new URL(window.location);
        url.searchParams.delete("start_date");
        url.searchParams.delete("mahasiswa_id");
        window.history.replaceState({}, "", url);
        updateHiddenFields();
      }
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
  console.log("DOMContentLoaded triggered");
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
  const savedDosenId = urlParams.get("id_dosen_pembimbing");
  const savedPreceptor1Id = urlParams.get("id_preceptor1");
  const savedPreceptor2Id = urlParams.get("id_preceptor2");

  if (savedDosenId) {
    const dosenSelect = document.getElementById("dosen_pembimbing");
    if (dosenSelect) {
      dosenSelect.value = savedDosenId;
      localStorage.setItem("selectedDosenId", savedDosenId);
    }
  }

  if (savedPreceptor1Id) {
    const preceptor1Select = document.getElementById("preceptor1");
    if (preceptor1Select) {
      preceptor1Select.value = savedPreceptor1Id;
      localStorage.setItem("selectedPreceptor1Id", savedPreceptor1Id);
    }
  }
  if (savedPreceptor2Id) {
    const preceptor2Select = document.getElementById("preceptor2");
    if (preceptor2Select) {
      preceptor2Select.value = savedPreceptor2Id;
      localStorage.setItem("selectedPreceptor2Id", savedPreceptor2Id);
    }
  }

  restoreTempatSelection();

  if (
    savedStartDate &&
    savedMahasiswaId &&
    /^\d{4}-\d{2}-\d{2}$/.test(savedStartDate)
  ) {
    startDate = savedStartDate;
    selectedMahasiswaId = parseInt(savedMahasiswaId);
    localStorage.setItem("startDate", startDate);
    localStorage.setItem("selectedMahasiswaId", selectedMahasiswaId);
    if (selectedTempatId) {
      updateRangePreview(startDate, startDate);
    }
  }

  const filterForm = document.getElementById("filterForm");
  if (filterForm) {
    filterForm.addEventListener("submit", (e) => {
      if (selectedTempatId) {
        const hiddenTempat = document.getElementById("hidden_id_tempat");
        if (hiddenTempat) hiddenTempat.value = selectedTempatId;
        const url = new URL(window.location);
        url.searchParams.set("id_tempat", selectedTempatId);
        window.history.replaceState({}, "", url);
      }
      const dosenSelect = document.getElementById("dosen_pembimbing");
      if (dosenSelect && dosenSelect.value) {
        const hiddenDosen = document.getElementById(
          "hidden_id_dosen_pembimbing"
        );
        if (hiddenDosen) hiddenDosen.value = dosenSelect.value;
        const url = new URL(window.location);
        url.searchParams.set("id_dosen_pembimbing", dosenSelect.value);
        window.history.replaceState({}, "", url);
      }
      const preceptor1Select = document.getElementById("preceptor1");
      if (preceptor1Select && preceptor1Select.value) {
        const hiddenPreceptor1 = document.getElementById(
          "hidden_id_preceptor1"
        );
        if (hiddenPreceptor1) hiddenPreceptor1.value = preceptor1Select.value;
        const url = new URL(window.location);
        url.searchParams.set("id_preceptor1", preceptor1Select.value);
        window.history.replaceState({}, "", url);
      }
      const preceptor2Select = document.getElementById("preceptor2");
      if (preceptor2Select && preceptor2Select.value) {
        const hiddenPreceptor2 = document.getElementById(
          "hidden_id_preceptor2"
        );
        if (hiddenPreceptor2) hiddenPreceptor2.value = preceptor2Select.value;
        const url = new URL(window.location);
        url.searchParams.set("id_preceptor2", preceptor2Select.value);
        window.history.replaceState({}, "", url);
      }
    });
  }

  updateHiddenFields();
  colorScheduleTable();
  handleCellSelection();
  setupZoom();
  const style = document.createElement("style");
  style.textContent = `#schedule-tooltip {background:#333;color:#fff;padding:8px;border-radius:4px;z-index:1000;font-size:14px;max-width:300px;box-shadow:0 2px 5px rgba(0,0,0,0.2);}`;
  document.head.appendChild(style);
});
