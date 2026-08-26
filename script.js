// Global variables
let currentFormId = null;
let isViewMode = false;
let vehiclesData = []; // Store vehicles data for autocomplete

// Auto-resize textarea function - improved version
function autoResizeTextarea(textarea) {
  // Reset height to auto to get proper scrollHeight
  textarea.style.height = "auto";

  // Set new height based on scroll height with small padding
  const newHeight = Math.max(textarea.scrollHeight, 80);
  textarea.style.height = newHeight + "px";

  // Ensure scrollbar is visible if content exceeds reasonable height
  if (newHeight > 300) {
    textarea.style.overflowY = "auto";
  } else {
    textarea.style.overflowY = "hidden";
  }
}

// Initialize all textarea auto-resize
function initAutoResize() {
  // Keterangan fields
  document.querySelectorAll(".input-keterangan").forEach((textarea) => {
    // Add input event listener for live resize
    textarea.addEventListener("input", function () {
      autoResizeTextarea(this);
    });

    // Add focus event to ensure proper sizing
    textarea.addEventListener("focus", function () {
      autoResizeTextarea(this);
    });

    // Add blur event to maintain size
    textarea.addEventListener("blur", function () {
      autoResizeTextarea(this);
    });

    // Initial resize
    autoResizeTextarea(textarea);
  });

  // Catatan tambahan field
  const catatanField = document.getElementById("catatan");
  if (catatanField) {
    catatanField.addEventListener("input", function () {
      autoResizeTextarea(this);
    });
    catatanField.addEventListener("focus", function () {
      autoResizeTextarea(this);
    });
    catatanField.addEventListener("blur", function () {
      autoResizeTextarea(this);
    });
    autoResizeTextarea(catatanField);
  }

  // Re-run auto-resize after a short delay to ensure proper rendering
  setTimeout(() => {
    document.querySelectorAll(".input-keterangan").forEach((textarea) => {
      if (textarea.value.trim() !== "") {
        autoResizeTextarea(textarea);
      }
    });
    if (catatanField && catatanField.value.trim() !== "") {
      autoResizeTextarea(catatanField);
    }
  }, 100);

  // Additional resize after page is fully loaded
  setTimeout(() => {
    document
      .querySelectorAll(".input-keterangan, textarea")
      .forEach((textarea) => {
        autoResizeTextarea(textarea);
      });
  }, 500);
}

// Run auto-resize on page load
window.addEventListener("load", initAutoResize);

// Load vehicles data for autocomplete
window.addEventListener("load", loadVehiclesData);

// Setup nomor polisi autocomplete and auto-fill
window.addEventListener("load", setupNomorPolisiAutocomplete);

// Auto-uncheck opposite checkbox
document.querySelectorAll(".chk-baik").forEach((checkbox, index) => {
  checkbox.addEventListener("change", function () {
    if (this.checked) {
      document.querySelectorAll(".chk-tidak")[index].checked = false;
    }
  });
});

document.querySelectorAll(".chk-tidak").forEach((checkbox, index) => {
  checkbox.addEventListener("change", function () {
    if (this.checked) {
      document.querySelectorAll(".chk-baik")[index].checked = false;
    }
  });
});

/**
 * Beri warna peringatan pada input tanggal masa berlaku surat (STNK, Pajak,
 * SIMFIT, Surat Tera, Surat Keur) — merah jika sudah lewat tanggal atau H-3
 * (akan habis dalam 3 hari), kuning jika akan habis dalam 14 hari ke depan.
 */
function updateExpireInputStyle(input) {
  input.classList.remove("expire-warning", "expire-danger");
  if (!input.value) return;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const expireDate = new Date(input.value + "T00:00:00");
  if (isNaN(expireDate.getTime())) return;
  const diffDays = Math.round((expireDate - today) / 86400000);
  if (diffDays <= 3) {
    input.classList.add("expire-danger");
  } else if (diffDays <= 14) {
    input.classList.add("expire-warning");
  }
}

document.querySelectorAll(".input-expire").forEach((input) => {
  updateExpireInputStyle(input);
  input.addEventListener("change", function () {
    updateExpireInputStyle(this);
  });
});

// Check URL parameters on page load
window.addEventListener("load", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const id = urlParams.get("id");
  const mode = urlParams.get("mode");

  if (id) {
    currentFormId = id;
    loadFormFromDatabase(id);

    if (mode === "view") {
      isViewMode = true;
      disableAllInputs();
      document.querySelector(".btn-save").style.display = "none";
      document.querySelector(".btn-reset").style.display = "none";
    }
  } else {
    // SECURITY: formulir BARU belum ada di database sehingga get.php belum
    // pernah dipanggil (tidak ada formData.viewer_* flags). Tanpa ini, kartu
    // TTD HSSE/Manajer pada formulir baru tidak pernah di-role-gate.
    applyRoleGatingForNewForm();
  }

  // Initialize auto-resize after potential data load
  setTimeout(initAutoResize, 500);
});

// Disable all inputs for view mode
function disableAllInputs() {
  document.querySelectorAll("input, select, textarea").forEach((input) => {
    input.disabled = true;
  });
}

/**
 * SECURITY: Terapkan pembatasan kartu TTD HSSE/Manajer berdasarkan role
 * SAAT MEMBUAT FORMULIR BARU (belum ada formulir_id, sehingga get.php belum
 * pernah dipanggil). Tanpa ini, Petugas HSSE bisa melihat & memakai kartu
 * TTD Manajer pada formulir yang baru dibuat.
 */
async function applyRoleGatingForNewForm() {
  try {
    const response = await fetch("api-my-permissions.php");
    const result = await response.json();
    if (result.success) {
      applyRoleBasedSignatureUI(result.data);
    }
  } catch (err) {
    console.error("applyRoleGatingForNewForm error:", err);
  }
}
// =====================================================
// AUTOCOMPLETE NOMOR POLISI & AUTO-FILL
// =====================================================

// Load vehicles data from server
async function loadVehiclesData() {
  try {
    // Get jenis kendaraan from hidden field
    const jenisKendaraan =
      document.getElementById("jenisKendaraan")?.value || "SPBU";

    const response = await fetch(`get-vehicles.php?jenis=${jenisKendaraan}`);
    const result = await response.json();

    if (result.success) {
      vehiclesData = result.data;
      populateNomorPolisiDatalist();
    } else {
      console.error("Gagal load data kendaraan:", result.message);
    }
  } catch (error) {
    console.error("Error loading vehicles:", error);
  }
}

// Populate datalist with nomor polisi options
function populateNomorPolisiDatalist() {
  const datalist = document.getElementById("nomorPolisiList");
  if (!datalist) return;

  // Clear existing options
  datalist.innerHTML = "";

  // Add options from vehicles data
  vehiclesData.forEach((vehicle) => {
    const option = document.createElement("option");
    option.value = vehicle.nomor_polisi;
    option.textContent = `${vehicle.nomor_polisi} - ${vehicle.nama_transport || "N/A"}`;
    datalist.appendChild(option);
  });
}

// Setup autocomplete and auto-fill functionality
function setupNomorPolisiAutocomplete() {
  const nomorPolisiInput = document.getElementById("nomorPolisi");
  if (!nomorPolisiInput) return;

  // Auto-fill when user selects or types a nomor polisi
  nomorPolisiInput.addEventListener("input", function () {
    const selectedNomorPolisi = this.value.trim().toUpperCase();

    if (selectedNomorPolisi) {
      // Find matching vehicle
      const vehicle = vehiclesData.find(
        (v) => v.nomor_polisi.toUpperCase() === selectedNomorPolisi,
      );

      if (vehicle) {
        // Auto-fill form fields
        autoFillVehicleData(vehicle);
      }
    }
  });

  // Also handle blur event for better UX
  nomorPolisiInput.addEventListener("blur", function () {
    const selectedNomorPolisi = this.value.trim().toUpperCase();

    if (selectedNomorPolisi) {
      const vehicle = vehiclesData.find(
        (v) => v.nomor_polisi.toUpperCase() === selectedNomorPolisi,
      );

      if (vehicle) {
        autoFillVehicleData(vehicle);
      }
    }
  });

  // Convert to uppercase when typing
  nomorPolisiInput.addEventListener("input", function () {
    this.value = this.value.toUpperCase();
  });
}

// Auto-fill vehicle data into form fields
function autoFillVehicleData(vehicle) {
  // Merk Mobil
  const merkMobilInput = document.getElementById("merkMobil");
  if (merkMobilInput && vehicle.merk_mobil && !merkMobilInput.value) {
    merkMobilInput.value = vehicle.merk_mobil;
  }

  // Nama Transport
  const namaTransportInput = document.getElementById("namaTransport");
  if (
    namaTransportInput &&
    vehicle.nama_transport &&
    !namaTransportInput.value
  ) {
    namaTransportInput.value = vehicle.nama_transport;
  }

  // Produk Kapasitas
  const produkKapasitasInput = document.getElementById("produkKapasitas");
  if (
    produkKapasitasInput &&
    vehicle.produk_kapasitas &&
    !produkKapasitasInput.value
  ) {
    produkKapasitasInput.value = vehicle.produk_kapasitas;
  }

  // Tanggal Terakhir (optional)
  const tanggalTerakhirInput = document.getElementById("tanggalTerakhir");
  if (
    tanggalTerakhirInput &&
    vehicle.tanggal_terakhir &&
    !tanggalTerakhirInput.value
  ) {
    tanggalTerakhirInput.value = vehicle.tanggal_terakhir;
  }

  // Visual feedback
  showAutoFillNotification();
}

// Show notification when auto-fill happens
function showAutoFillNotification() {
  const nomorPolisiInput = document.getElementById("nomorPolisi");
  if (!nomorPolisiInput) return;

  // Create notification element
  const notification = document.createElement("div");
  notification.textContent = "✓ Data kendaraan terisi otomatis";
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 20px;
    border-radius: 5px;
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
  `;

  document.body.appendChild(notification);

  // Remove after 3 seconds
  setTimeout(() => {
    notification.style.animation = "slideOut 0.3s ease-out";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add CSS animations
if (!document.getElementById("autocomplete-styles")) {
  const style = document.createElement("style");
  style.id = "autocomplete-styles";
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }
  `;
  document.head.appendChild(style);
}

// =====================================================
// END AUTOCOMPLETE
// =====================================================

/**
 * Ambil nama/label item pemeriksaan (mis. "STNK", "PAJAK", dst) dari sebuah
 * baris <tr> pada tabel checklist. Kolom nama item TIDAK selalu berada di
 * posisi index yang sama, karena beberapa baris memakai <td rowspan> untuk
 * kolom NO. Namun urutan 5 kolom TERAKHIR selalu tetap:
 * [PELAKSANA, PRIORITAS, BAIK, TIDAK, KETERANGAN] — jadi kolom nama item
 * selalu tepat SEBELUM 5 kolom tersebut (dihitung dari belakang).
 */
function getChecklistItemName(row) {
  const cells = row.cells;
  if (!cells || cells.length < 6) return "";
  const nameCell = cells[cells.length - 6];
  return nameCell ? nameCell.textContent.trim() : "";
}

// Save form data to database
async function saveForm() {
  // Validate required fields
  const nomorPolisi = document.getElementById("nomorPolisi").value.trim();
  const tanggalPemeriksaan =
    document.getElementById("tanggalPemeriksaan").value;

  if (!nomorPolisi) {
    alert("Nomor Polisi harus diisi!");
    document.getElementById("nomorPolisi").focus();
    return;
  }

  if (!tanggalPemeriksaan) {
    alert("Tanggal Pemeriksaan harus diisi!");
    document.getElementById("tanggalPemeriksaan").focus();
    return;
  }

  const formData = {
    id: currentFormId,
    jenisKendaraan: document.getElementById("jenisKendaraan")
      ? document.getElementById("jenisKendaraan").value
      : "SPBU",
    nomorUrut: document.getElementById("nomorUrut").value,
    namaTransport: document.getElementById("namaTransport").value,
    tanggalTerakhir: document.getElementById("tanggalTerakhir")
      ? document.getElementById("tanggalTerakhir").value
      : "",
    tanggalPemeriksaan: tanggalPemeriksaan,
    merkMobil: document.getElementById("merkMobil").value,
    nomorPolisi: nomorPolisi,
    produkKapasitas: document.getElementById("produkKapasitas").value,
    ekimValidUntil: document.getElementById("ekimValidUntil").value,
    statusGate: document.querySelector('input[name="statusGate"]:checked')
      ? document.querySelector('input[name="statusGate"]:checked').value
      : "",
    statusUpload: document.querySelector('input[name="statusUpload"]:checked')
      ? document.querySelector('input[name="statusUpload"]:checked').value
      : "",
    namaPemeriksaBagian: document.getElementById("namaPemeriksaBagian").value,
    tanggalPemeriksaBagian: document.getElementById("tanggalPemeriksaBagian")
      .value,
    catatan: document.getElementById("catatan").value,
    checklist: [],
  };

  // Save checklist data
  const rows = document.querySelectorAll("#checklistTable tbody tr");
  rows.forEach((row, index) => {
    const baik = row.querySelector(".chk-baik")
      ? row.querySelector(".chk-baik").checked
      : false;
    const tidak = row.querySelector(".chk-tidak")
      ? row.querySelector(".chk-tidak").checked
      : false;
    const keterangan = row.querySelector(".input-keterangan")
      ? row.querySelector(".input-keterangan").value
      : "";
    const tanggalExpire = row.querySelector(".input-expire")
      ? row.querySelector(".input-expire").value
      : "";

    formData.checklist.push({
      nama: getChecklistItemName(row),
      baik: baik,
      tidak: tidak,
      keterangan: keterangan,
      tanggal_expire: tanggalExpire,
    });
  });

  try {
    // Show loading
    const btnSave = document.querySelector(".btn-save");
    const originalText = btnSave.textContent;
    btnSave.textContent = "Menyimpan...";
    btnSave.disabled = true;

    // Save to database
    const response = await fetch("save.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const result = await response.json();

    btnSave.textContent = originalText;
    btnSave.disabled = false;

    if (result.success) {
      currentFormId = result.data.id;

      alert(result.message + "\n\nData telah tersimpan di database.");

      // Ask if user wants to go to list or create new
      if (confirm("Lihat daftar semua data?")) {
        window.location.href = "list.php";
      } else {
        // Reset form for new entry
        if (confirm("Buat data baru?")) {
          window.location.href = "index.html";
        }
      }
    } else {
      alert("Gagal menyimpan data: " + result.message);
    }
  } catch (error) {
    console.error("Error saving data:", error);
    alert(
      "Terjadi kesalahan saat menyimpan data. Pastikan:\n1. Database sudah dibuat\n2. Konfigurasi database di config.php sudah benar\n3. Apache dan MySQL sudah berjalan",
    );
  }
}

// Load form data from database
async function loadFormFromDatabase(id) {
  try {
    const response = await fetch(`get.php?id=${id}`);
    const result = await response.json();

    if (result.success) {
      const formData = result.data;

      document.getElementById("nomorUrut").value = formData.nomor_urut || "";
      document.getElementById("namaTransport").value =
        formData.nama_transport || "";
      if (document.getElementById("tanggalTerakhir")) {
        document.getElementById("tanggalTerakhir").value =
          formData.tanggal_terakhir || "";
      }
      document.getElementById("tanggalPemeriksaan").value =
        formData.tanggal_pemeriksaan || "";
      document.getElementById("merkMobil").value = formData.merk_mobil || "";
      document.getElementById("nomorPolisi").value =
        formData.nomor_polisi || "";
      document.getElementById("produkKapasitas").value =
        formData.produk_kapasitas || "";
      document.getElementById("ekimValidUntil").value =
        formData.ekim_valid_until || "";
      document.getElementById("namaPemeriksaBagian").value =
        formData.nama_pemeriksa || "";
      document.getElementById("tanggalPemeriksaBagian").value =
        formData.tanggal_pemeriksa || "";

      const catatanTextarea = document.getElementById("catatan");
      catatanTextarea.value = formData.catatan || "";
      // Auto-resize after loading data
      autoResizeTextarea(catatanTextarea);

      if (formData.status_gate) {
        const radioGate = document.querySelector(
          `input[name="statusGate"][value="${formData.status_gate}"]`,
        );
        if (radioGate) radioGate.checked = true;
      }

      if (formData.status_upload) {
        const radioUpload = document.querySelector(
          `input[name="statusUpload"][value="${formData.status_upload}"]`,
        );
        if (radioUpload) radioUpload.checked = true;
      }

      // Load checklist items
      if (formData.checklist_items) {
        const rows = document.querySelectorAll("#checklistTable tbody tr");
        formData.checklist_items.forEach((item, index) => {
          if (rows[index]) {
            const baik = rows[index].querySelector(".chk-baik");
            const tidak = rows[index].querySelector(".chk-tidak");
            const keterangan = rows[index].querySelector(".input-keterangan");
            const tanggalExpire = rows[index].querySelector(".input-expire");

            if (baik) baik.checked = item.is_baik == 1;
            if (tidak) tidak.checked = item.is_tidak == 1;
            if (keterangan) {
              keterangan.value = item.keterangan || "";
              // Auto-resize after loading data
              autoResizeTextarea(keterangan);
            }
            if (tanggalExpire) {
              tanggalExpire.value =
                item.tanggal_expire && item.tanggal_expire !== "0000-00-00"
                  ? item.tanggal_expire
                  : "";
              updateExpireInputStyle(tanggalExpire);
            }
          }
        });
      }

      // Load TTD digital data (signatures)
      loadTTDFromFormData(formData);
      // QR HSSE muncul begitu HSSE sudah TTD (baik masih pending maupun sudah
      // final), ditampilkan di dalam kotak TTD HSSE sendiri.
      renderVerificationQr(
        "hsse",
        formData.verification_url || "",
        formData.verification_qrcode_url || "",
        formData.status_approval === "approved",
      );
      // QR Manajer hanya muncul di kotak TTD Manajer setelah Manajer approve
      // (final) — sebelum itu kotaknya tetap kosong/tersembunyi.
      renderVerificationQr(
        "manajer",
        formData.status_approval === "approved"
          ? formData.verification_url || ""
          : "",
        formData.verification_qrcode_url || "",
        true,
      );
      updateManagerApprovalCard(formData);
      // SECURITY: terapkan pembatasan UI berbasis role SETELAH data TTD
      // dimuat, agar hanya role yang berwenang yang melihat kontrol tanda
      // tangan HSSE/Manajer (lihat BUG audit Digital Signature Manager).
      applyRoleBasedSignatureUI(formData);

      if (isViewMode) {
        document.title = `Lihat Data - ${formData.nomor_polisi} - Checklist E-KIM`;
        // In view mode: hide canvas/sign buttons
        hideSignatureControls(formData);
      } else {
        document.title = `Edit Data - ${formData.nomor_polisi} - Checklist E-KIM`;
      }
    } else {
      alert("Gagal memuat data: " + result.message);
      window.location.href = "list.php";
    }
  } catch (error) {
    console.error("Error loading data:", error);
    alert("Gagal memuat data dari database");
  }
}

// Reset form
function resetForm() {
  if (confirm("Apakah Anda yakin ingin mereset semua data?")) {
    document
      .querySelectorAll('input[type="text"], input[type="date"]')
      .forEach((input) => {
        input.value = "";
      });
    document
      .querySelectorAll('input[type="checkbox"], input[type="radio"]')
      .forEach((input) => {
        input.checked = false;
      });

    // Reset all textareas and auto-resize
    document.querySelectorAll("textarea").forEach((textarea) => {
      textarea.value = "";
      autoResizeTextarea(textarea);
    });

    localStorage.removeItem("checklistData");
    currentFormId = null;

    // Reset status TTD
    ["hsse", "manajer"].forEach((role) => {
      const badge = document.getElementById("badge-" + role);
      if (badge) badge.style.display = "none";
      const btn = document.getElementById("btn-sign-" + role);
      if (btn) {
        btn.style.display = "block";
        btn.disabled = false;
      }
    });

    alert("Form berhasil direset!");
  }
}

// =====================================================
// TTD DIGITAL - One-click RSA Signing (tanpa gambar)
// =====================================================

/**
 * Simpan data checklist ke database secara otomatis dan transparan saat
 * user menekan tombol "Tandatangani Secara Digital" pada formulir yang
 * belum pernah disimpan (currentFormId masih kosong). Mengembalikan id
 * formulir yang baru dibuat, atau null jika validasi/simpan gagal.
 */
async function autoSaveBeforeSign() {
  const nomorPolisi = document.getElementById("nomorPolisi").value.trim();
  const tanggalPemeriksaan =
    document.getElementById("tanggalPemeriksaan").value;

  if (!nomorPolisi) {
    alert("Nomor Polisi harus diisi sebelum tanda tangan!");
    document.getElementById("nomorPolisi").focus();
    return null;
  }
  if (!tanggalPemeriksaan) {
    alert("Tanggal Pemeriksaan harus diisi sebelum tanda tangan!");
    document.getElementById("tanggalPemeriksaan").focus();
    return null;
  }

  const formData = {
    id: currentFormId,
    jenisKendaraan: document.getElementById("jenisKendaraan")
      ? document.getElementById("jenisKendaraan").value
      : "SPBU",
    nomorUrut: document.getElementById("nomorUrut").value,
    namaTransport: document.getElementById("namaTransport").value,
    tanggalTerakhir: document.getElementById("tanggalTerakhir")
      ? document.getElementById("tanggalTerakhir").value
      : "",
    tanggalPemeriksaan: tanggalPemeriksaan,
    merkMobil: document.getElementById("merkMobil").value,
    nomorPolisi: nomorPolisi,
    produkKapasitas: document.getElementById("produkKapasitas").value,
    ekimValidUntil: document.getElementById("ekimValidUntil").value,
    statusGate: document.querySelector('input[name="statusGate"]:checked')
      ? document.querySelector('input[name="statusGate"]:checked').value
      : "",
    statusUpload: document.querySelector('input[name="statusUpload"]:checked')
      ? document.querySelector('input[name="statusUpload"]:checked').value
      : "",
    namaPemeriksaBagian: document.getElementById("namaPemeriksaBagian").value,
    tanggalPemeriksaBagian: document.getElementById("tanggalPemeriksaBagian")
      .value,
    catatan: document.getElementById("catatan").value,
    checklist: [],
  };

  const rows = document.querySelectorAll("#checklistTable tbody tr");
  rows.forEach((row) => {
    const baik = row.querySelector(".chk-baik")
      ? row.querySelector(".chk-baik").checked
      : false;
    const tidak = row.querySelector(".chk-tidak")
      ? row.querySelector(".chk-tidak").checked
      : false;
    const keterangan = row.querySelector(".input-keterangan")
      ? row.querySelector(".input-keterangan").value
      : "";
    const tanggalExpire = row.querySelector(".input-expire")
      ? row.querySelector(".input-expire").value
      : "";
    formData.checklist.push({
      nama: getChecklistItemName(row),
      baik,
      tidak,
      keterangan,
      tanggal_expire: tanggalExpire,
    });
  });

  try {
    const response = await fetch("save.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(formData),
    });
    const result = await response.json();
    if (!result.success) {
      alert("Gagal menyimpan data sebelum tanda tangan: " + result.message);
      return null;
    }
    currentFormId = result.data.id;
    return currentFormId;
  } catch (err) {
    console.error("autoSaveBeforeSign error:", err);
    alert("Terjadi kesalahan saat menyimpan data sebelum tanda tangan.");
    return null;
  }
}

async function signDigital(role) {
  if (!currentFormId) {
    // Auto-save formulir terlebih dahulu secara transparan, agar HSSE bisa
    // langsung menandatangani tanpa harus klik "Simpan Data" secara manual.
    const savedId = await autoSaveBeforeSign();
    if (!savedId) return;
  }

  const nameId = role === "hsse" ? "namaPemeriksaBagian" : "namaManajer";
  const nameEl = document.getElementById(nameId);
  const signerName = nameEl ? nameEl.value.trim() : "";

  if (!signerName) {
    alert(
      "Isi nama " +
        (role === "hsse" ? "Pemeriksa HSSE" : "Manajer") +
        " terlebih dahulu.",
    );
    if (nameEl) nameEl.focus();
    return;
  }

  const btn = document.getElementById("btn-sign-" + role);
  const originalText = btn.textContent;
  btn.textContent = "Memproses...";
  btn.disabled = true;

  try {
    const response = await fetch("sign-checklist.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        formulir_id: parseInt(currentFormId),
        action: role === "manajer" ? "sign_manajer" : "sign_hsse",
      }),
    });

    const result = await response.json();
    btn.disabled = false;

    if (result.success) {
      showTTDBadge(role, result.data);
      btn.style.display = "none";
      // QR verifikasi ditampilkan langsung di dalam kotak TTD role yang baru
      // saja menandatangani (HSSE atau Manajer) — tidak dipisah ke kotak lain.
      if (result.data.verification_url) {
        renderVerificationQr(
          role,
          result.data.verification_url,
          result.data.verification_qrcode_url || "",
          result.data.new_status === "approved",
        );
      }
    } else {
      btn.textContent = originalText;
      alert("Gagal menyimpan TTD: " + result.message);
    }
  } catch (err) {
    btn.textContent = originalText;
    btn.disabled = false;
    console.error("signDigital error:", err);
    alert("Terjadi kesalahan saat menyimpan TTD. Periksa koneksi server.");
  }
}

/**
 * Tampilkan badge "TERVERIFIKASI" setelah TTD berhasil disimpan.
 */
function showTTDBadge(role, data) {
  const badge = document.getElementById("badge-" + role);
  const namaEl = document.getElementById("badge-" + role + "-nama");
  const waktuEl = document.getElementById("badge-" + role + "-waktu");
  const hashEl = document.getElementById("badge-" + role + "-hash");

  if (!badge) return;

  if (namaEl) namaEl.textContent = data.nama || "-";
  if (waktuEl) waktuEl.textContent = data.waktu_display || data.waktu || "-";
  if (hashEl) hashEl.textContent = (data.hash_preview || "-") + "...";

  badge.style.display = "block"; // .ttd-badge uses display:block
}

/** Tampilkan QR verifikasi DI DALAM kotak TTD role terkait ("hsse" atau
 *  "manajer") — bukan di kotak terpisah. QR sudah muncul sejak TTD HSSE
 *  (status "pending" saat discan sampai Manajer approve), lalu jadi "valid"
 *  penuh setelah TTD Manajer selesai. */
function renderVerificationQr(
  role,
  verificationUrl,
  qrcodeImageUrl = "",
  isFinal = true,
) {
  const box = document.getElementById("qr-box-" + role);
  const container = document.getElementById("verification-qrcode-" + role);
  const message = document.getElementById("verification-qr-message-" + role);
  const urlElement = document.getElementById("verification-qr-url-" + role);
  if (!container || !message || !urlElement) return;

  container.innerHTML = "";
  urlElement.textContent = "";
  if (!verificationUrl) {
    if (box) box.style.display = "none";
    return;
  }
  if (box) box.style.display = "block";

  message.textContent = isFinal
    ? "Dokumen ini telah ditandatangani secara digital menggunakan algoritma RSA dan SHA-512. Scan QR Code untuk memverifikasi keaslian dokumen melalui website."
    : 'TTD HSSE berhasil dibubuhkan. Scan QR Code untuk memverifikasi — status masih "Menunggu persetujuan Manajer" sampai TTD Manajer selesai.';
  urlElement.textContent = verificationUrl;
  if (qrcodeImageUrl) {
    const image = new Image();
    image.src = qrcodeImageUrl;
    image.width = 180;
    image.height = 180;
    image.alt = "QR Code verifikasi dokumen";
    image.onerror = () =>
      renderVerificationQr(role, verificationUrl, "", isFinal);
    container.appendChild(image);
    return;
  }
  if (typeof QRCode === "undefined") {
    message.textContent =
      "QR Code tidak dapat dimuat. Periksa koneksi internet lalu muat ulang halaman.";
    return;
  }

  QRCode.toCanvas(
    verificationUrl,
    {
      width: 180,
      margin: 2,
      color: { dark: "#0d2137", light: "#ffffff" },
    },
    (error, canvas) => {
      if (error) {
        message.textContent = "QR Code tidak dapat dibuat.";
        return;
      }
      container.appendChild(canvas);
    },
  );
}

/**
 * Tampilkan data TTD yang sudah ada (saat form di-load dari database).
 */
function loadTTDFromFormData(formData) {
  ["hsse", "manajer"].forEach((role) => {
    const prefix = "ttd_" + role;
    const nama = formData[prefix + "_nama"] || null;
    const waktu = formData[prefix + "_timestamp"] || null;
    const hash = formData[prefix + "_hash"] || null;

    if (nama && waktu && hash) {
      showTTDBadge(role, {
        nama: nama,
        waktu_display: formatTTDWaktu(waktu),
        hash_preview: hash.substring(0, 16),
      });

      const btn = document.getElementById("btn-sign-" + role);
      if (btn) btn.style.display = "none";

      const nameId = role === "hsse" ? "namaPemeriksaBagian" : "namaManajer";
      const nameEl = document.getElementById(nameId);
      if (nameEl && !nameEl.value) nameEl.value = nama;
    }
  });
}

/**
 * Tampilkan catatan status di bawah kotak TTD yang disembunyikan, supaya
 * viewer selalu tahu KENAPA kotaknya kosong (bukan terlihat seperti rusak).
 * Maksimal satu catatan per kartu (dicek lewat class .ttd-pending-note).
 */
function addSignatureNote(role, text) {
  const anchor = document.getElementById("btn-sign-" + role);
  const body = anchor ? anchor.closest(".ttd-body") : null;
  if (body && !body.querySelector(".ttd-pending-note")) {
    const note = document.createElement("div");
    note.className = "ttd-pending-note";
    note.style.cssText =
      "margin-top:10px;padding:10px 12px;background:#fef3c7;border:1.5px solid #fcd34d;" +
      "border-radius:8px;font-size:12px;color:#92400e;font-weight:700;text-align:center;";
    note.textContent = text;
    body.appendChild(note);
  }
}

/**
 * Sembunyikan canvas dan tombol sign saat view mode, lalu jelaskan status
 * dokumen (menunggu HSSE / menunggu Manajer / dst) agar kotak TTD yang
 * kosong tidak membingungkan viewer (lihat keluhan "kenapa tidak muncul
 * tempat ttdnya").
 */
function hideSignatureControls(formData) {
  formData = formData || {};
  const hsseSigned = Boolean(
    formData.ttd_hsse_nama && formData.ttd_hsse_timestamp,
  );
  const manajerSigned = Boolean(
    formData.ttd_manajer_nama && formData.ttd_manajer_timestamp,
  );

  ["hsse", "manajer"].forEach((role) => {
    const btn = document.getElementById("btn-sign-" + role);
    if (btn) btn.style.display = "none";
  });

  if (!hsseSigned) {
    addSignatureNote("hsse", "Menunggu Tanda Tangan HSSE");
    addSignatureNote(
      "manajer",
      "Menunggu Tanda Tangan HSSE terlebih dahulu sebelum Manajer dapat menyetujui.",
    );
  } else if (!manajerSigned) {
    addSignatureNote(
      "manajer",
      'HSSE sudah menandatangani. Gunakan tombol "Approve & TTD" pada halaman Checklist Menunggu Persetujuan untuk menyetujui dokumen ini.',
    );
  }
}

/** Tampilkan status review dan tombol approval untuk dokumen yang menunggu/selesai persetujuan.
 *  Kartu status ditampilkan untuk SEMUA role (agar HSSE/Admin tahu progres approval),
 *  tapi tombol Approve & Digital Signature Manager HANYA muncul untuk viewer_can_approve
 *  (yaitu role Manager, lihat get.php / auth.php::canSignManager()). */
function updateManagerApprovalCard(formData) {
  const card = document.getElementById("manager-approval-card");
  if (!card) return;

  const isAwaitingApproval = formData.status_approval === "signed_hsse";
  const isApproved = formData.status_approval === "approved";
  if (!isAwaitingApproval && !isApproved) return;

  const status = document.getElementById("manager-approval-status");
  const date = document.getElementById("manager-approval-date");
  const name = document.getElementById("manager-approval-name");
  const button = document.getElementById("btn-manager-approve");
  if (status)
    status.textContent = isApproved
      ? "APPROVED"
      : "Menunggu Persetujuan Manager";
  if (date)
    date.textContent = formData.ttd_manajer_timestamp
      ? formatTTDWaktu(formData.ttd_manajer_timestamp)
      : "-";
  if (name) name.textContent = formData.ttd_manajer_nama || "-";
  // SECURITY: hanya viewer_can_approve (Manager) yang boleh melihat tombol.
  if (button)
    button.style.display = formData.viewer_can_approve ? "block" : "none";
  card.style.display = "block";
}

/**
 * SECURITY: Sembunyikan kontrol tanda tangan (canvas + tombol) untuk role
 * yang tidak berwenang. HSSE card hanya untuk viewer_can_sign_hsse (Admin/HSSE),
 * Manajer card hanya untuk viewer_is_manager (Manager). Role lain hanya melihat
 * status read-only (badge jika sudah ditandatangani, atau catatan menunggu).
 */
function applyRoleBasedSignatureUI(formData) {
  const permissions = {
    hsse: Boolean(formData.viewer_can_sign_hsse),
    manajer: Boolean(formData.viewer_is_manager),
  };

  ["hsse", "manajer"].forEach((role) => {
    if (permissions[role]) return; // authorized: leave default controls visible

    const btn = document.getElementById("btn-sign-" + role);
    if (btn) btn.style.display = "none";

    const nameId = role === "hsse" ? "namaPemeriksaBagian" : "namaManajer";
    const dateId =
      role === "hsse" ? "tanggalPemeriksaBagian" : "tanggalManajer";
    const nameEl = document.getElementById(nameId);
    if (nameEl) nameEl.disabled = true;
    const dateEl = document.getElementById(dateId);
    if (dateEl) dateEl.disabled = true;

    const alreadySigned = Boolean(
      formData["ttd_" + role + "_nama"] &&
      formData["ttd_" + role + "_timestamp"],
    );
    if (!alreadySigned) {
      addSignatureNote(
        role,
        role === "hsse"
          ? "Menunggu Tanda Tangan HSSE"
          : "Menunggu Persetujuan Manager",
      );
    }
  });
}

/** Konfirmasi dan jalankan approval Manager dari halaman detail yang read-only. */
async function approveChecklistFromDetail() {
  if (!currentFormId) return;
  if (
    !confirm(
      "Apakah Anda yakin akan menyetujui hasil checklist kendaraan ini?\n\nSetelah disetujui, Digital Signature Manager akan dibuat secara permanen.",
    )
  ) {
    return;
  }

  const button = document.getElementById("btn-manager-approve");
  if (button) {
    button.disabled = true;
    button.textContent = "Memproses Approval...";
  }

  try {
    const response = await fetch("sign-checklist.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        formulir_id: Number(currentFormId),
        action: "sign_manajer",
      }),
    });
    const result = await response.json();
    if (!result.success)
      throw new Error(result.message || "Approval gagal diproses.");
    window.location.reload();
  } catch (error) {
    if (button) {
      button.disabled = false;
      button.textContent = "Approve & Digital Signature";
    }
    alert(error.message || "Gagal memproses approval Manager.");
  }
}

/**
 * Format waktu datetime dari database ke format Indonesia.
 */
function formatTTDWaktu(datetimeStr) {
  if (!datetimeStr) return "-";
  try {
    const d = new Date(datetimeStr);
    const months = [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "Mei",
      "Jun",
      "Jul",
      "Ags",
      "Sep",
      "Okt",
      "Nov",
      "Des",
    ];
    return (
      d.getDate() +
      " " +
      months[d.getMonth()] +
      " " +
      d.getFullYear() +
      " " +
      String(d.getHours()).padStart(2, "0") +
      ":" +
      String(d.getMinutes()).padStart(2, "0") +
      " WIB"
    );
  } catch {
    return datetimeStr;
  }
}

// =====================================================
// END TTD DIGITAL
// =====================================================

// Prepare textareas for printing - ensure all content is visible
window.addEventListener("beforeprint", function () {
  document
    .querySelectorAll(".input-keterangan, textarea")
    .forEach((textarea) => {
      // Set height to auto to show all content when printing
      textarea.style.height = "auto";
      textarea.style.height = textarea.scrollHeight + "px";
      textarea.style.overflow = "visible";
    });
});

// Restore textarea state after printing
window.addEventListener("afterprint", function () {
  document
    .querySelectorAll(".input-keterangan, textarea")
    .forEach((textarea) => {
      autoResizeTextarea(textarea);
    });
});
