const API_ENDPOINT = "api/app.php";

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#039;"
    }[char]));
}

function showToast(message, type = "success") {
    const container = document.getElementById("toastContainer");
    if (!container || typeof bootstrap === "undefined") {
        window.alert(message);
        return;
    }

    const icons = {
        success: "bi-check-circle-fill",
        danger: "bi-x-circle-fill",
        warning: "bi-exclamation-triangle-fill",
        info: "bi-info-circle-fill"
    };
    const tone = {
        success: "text-bg-success",
        danger: "text-bg-danger",
        warning: "text-bg-warning",
        info: "text-bg-info"
    }[type] || "text-bg-success";

    const icon = icons[type] || icons.success;

    const toast = document.createElement("div");
    toast.className = `toast align-items-center border-0 ${tone}`;
    toast.setAttribute("role", "status");
    toast.innerHTML = `
        <div class="d-flex align-items-center gap-2 p-3">
            <i class="bi ${icon} fs-5 flex-shrink-0"></i>
            <div class="toast-body fw-semibold p-0 flex-grow-1">${escapeHtml(message)}</div>
            <button type="button" class="btn-close btn-close-white ms-2 flex-shrink-0" data-bs-dismiss="toast" aria-label="Tutup"></button>
        </div>`;
    container.appendChild(toast);
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 3500 }).show();
    toast.addEventListener("hidden.bs.toast", () => toast.remove());
}

function setFormAlert(form, message = "") {
    let alert = form.querySelector("[data-form-alert]");
    if (!alert && message) {
        alert = document.createElement("div");
        alert.className = "alert alert-danger";
        alert.dataset.formAlert = "true";
        form.prepend(alert);
    }
    if (!alert) return;
    alert.textContent = message;
    alert.classList.toggle("d-none", !message);
}

function downloadBlob(content, filename, type) {
    const blob = new Blob(["﻿" + content], { type }); // BOM for Excel UTF-8
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}

function csvEscape(value) {
    const text = String(value ?? "").replace(/\s+/g, " ").trim();
    return /[",\n]/.test(text) ? `"${text.replace(/"/g, "\"\"")}"` : text;
}

function downloadTableCsv(button) {
    const target = document.getElementById(button.dataset.target || "");
    if (!target) return;
    const rows = [...target.querySelectorAll("tr")].map(row =>
        [...row.children].map(cell => csvEscape(cell.innerText)).join(",")
    );
    downloadBlob(rows.join("\n"), `${button.dataset.filename || "export-data"}.csv`, "text/csv;charset=utf-8");
    showToast("File CSV berhasil diunduh.", "success");
}

function setButtonLoading(button, loading) {
    if (!button) return;
    if (loading) {
        button._originalHtml = button.innerHTML;
        button._originalDisabled = button.disabled;
        button.disabled = true;
        button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...`;
    } else {
        button.disabled = button._originalDisabled || false;
        button.innerHTML = button._originalHtml || button.innerHTML;
    }
}

async function postForm(form, submitter = null) {
    const body = new FormData(form);
    if (submitter?.name && submitter?.value) {
        body.set(submitter.name, submitter.value);
    }

    const button = submitter || form.querySelector("[type='submit']");
    setButtonLoading(button, true);

    try {
        const response = await fetch(form.getAttribute("action") || API_ENDPOINT, {
            method: "POST",
            body,
            credentials: "same-origin",
            headers: { "X-Requested-With": "fetch" }
        });
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch {
            throw new Error("Respons server tidak valid. Coba muat ulang halaman.");
        }
        if (!response.ok || !result.success) {
            throw new Error(result.message || "Aksi gagal diproses.");
        }
        setFormAlert(form);
        showToast(result.message || "Aksi berhasil.");
        if (result.data?.redirect) {
            window.setTimeout(() => { window.location.href = result.data.redirect; }, 500);
            return;
        }
        window.setTimeout(() => window.location.reload(), 800);
    } catch (error) {
        setFormAlert(form, error.message);
        showToast(error.message, "danger");
        setButtonLoading(button, false);
    }
}

// Star rating interactivity
function initStarRating() {
    document.querySelectorAll("[data-star-rating]").forEach(group => {
        const inputs = group.querySelectorAll("input[type='radio']");
        inputs.forEach(input => {
            input.addEventListener("change", () => highlightStars(group, parseInt(input.value)));
        });

        group.addEventListener("mouseleave", () => {
            const checked = group.querySelector("input:checked");
            highlightStars(group, checked ? parseInt(checked.value) : 0);
        });

        group.querySelectorAll("label").forEach(label => {
            const input = group.querySelector(`#${label.getAttribute("for")}`);
            if (!input) return;
            label.addEventListener("mouseenter", () => highlightStars(group, parseInt(input.value)));
        });
    });
}

function highlightStars(group, value) {
    group.querySelectorAll("label").forEach(label => {
        const input = group.querySelector(`#${label.getAttribute("for")}`);
        if (!input) return;
        const star = label.querySelector("i");
        if (!star) return;
        const v = parseInt(input.value);
        star.className = v <= value ? "bi bi-star-fill text-warning" : "bi bi-star text-secondary";
    });
}

// Auto-dismiss server flash alerts
function initFlashDismiss() {
    const flash = document.getElementById("serverFlash");
    if (flash && typeof bootstrap !== "undefined") {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(flash).close(), 4000);
    }
}

// Active sidebar link highlight
function initSidebarActive() {
    const current = window.location.search;
    document.querySelectorAll("[data-sidebar-link]").forEach(link => {
        const href = link.getAttribute("href") || "";
        if (href && current && href.includes("page=") && current.includes("page=")) {
            const linkPage = new URLSearchParams(href.split("?")[1] || "").get("page");
            const currentPage = new URLSearchParams(current).get("page");
            if (linkPage && currentPage && linkPage === currentPage) {
                link.classList.add("active");
            }
        }
    });
}

// File input preview
function syncFileInputFiles(input, files) {
    const dataTransfer = new DataTransfer();
    files.forEach(file => dataTransfer.items.add(file));
    input.files = dataTransfer.files;
    input._selectedFiles = files;
}

function renderFilePreview(input) {
    const files = input._selectedFiles || [];
    let container = input.parentElement.querySelector(".file-preview-list");
    if (!container) {
        container = document.createElement("div");
        container.className = "row g-2 mt-1";
        input.parentElement.appendChild(container);
    }
    container.innerHTML = "";

    if (!files.length) return;

    const info = document.createElement("div");
    info.className = "col-12 small text-muted";
    info.textContent = `${files.length} foto dipilih`;
    container.appendChild(info);

    files.forEach((file, index) => {
        const item = document.createElement("div");
        item.className = "col-6 col-md-4 d-flex flex-column gap-1";

        const preview = document.createElement("img");
        preview.className = "img-thumbnail w-100";
        preview.alt = `Pratinjau foto ${index + 1}`;
        item.appendChild(preview);

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "btn btn-sm btn-outline-danger mt-1";
        remove.textContent = "Hapus";
        remove.addEventListener("click", () => {
            const nextFiles = (input._selectedFiles || []).filter((_, fileIndex) => fileIndex !== index);
            syncFileInputFiles(input, nextFiles);
            renderFilePreview(input);
        });
        item.appendChild(remove);
        container.appendChild(item);

        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; };
        reader.readAsDataURL(file);
    });
}

function initFilePreview() {
    document.addEventListener("change", event => {
        const input = event.target.closest("input[type='file']");
        if (!input) return;

        const maxFiles = Number(input.dataset.maxFiles || 0);
        const selectedFiles = Array.from(input.files).filter(file => file.type.startsWith("image/"));
        const existingFiles = input._selectedFiles || [];
        const remainingSlots = maxFiles ? Math.max(maxFiles - existingFiles.length, 0) : selectedFiles.length;

        if (maxFiles && selectedFiles.length > remainingSlots) {
            alert(`Maksimal ${maxFiles} foto.`);
        }

        syncFileInputFiles(input, existingFiles.concat(selectedFiles.slice(0, remainingSlots)));
        renderFilePreview(input);
    });
}

function initEstimateForms() {
    document.querySelectorAll("[data-estimate-form]").forEach(form => {
        const syncRow = toggle => {
            const row = toggle.closest("[data-estimate-row]");
            if (!row) return;
            row.querySelectorAll("input:not([type='checkbox'])").forEach(input => {
                input.disabled = !toggle.checked;
            });
            row.classList.toggle("bg-success-subtle", toggle.checked);
        };
        const calculate = () => {
            let total = 0;
            form.querySelectorAll("[data-estimate-row]").forEach(row => {
                const toggle = row.querySelector("[data-estimate-toggle]");
                if (!toggle?.checked) return;
                total += (Number(row.querySelector("[data-estimate-qty]")?.value) || 0)
                    * (Number(row.querySelector("[data-estimate-price]")?.value) || 0);
            });
            const workers = Number(form.querySelector("[name='worker_count']")?.value) || 0;
            const days = Number(form.querySelector("[name='estimated_days']")?.value) || 0;
            const selectedLabor = form.querySelector("[data-labor-rate] option:checked");
            total += workers * days * (Number(selectedLabor?.dataset.rate) || 0);
            const customName = form.querySelector("[name='custom_additional_name']")?.value.trim();
            if (customName) {
                total += (Number(form.querySelector("[name='custom_additional_qty']")?.value) || 0)
                    * (Number(form.querySelector("[name='custom_additional_price']")?.value) || 0);
            }
            const output = form.querySelector("[data-estimate-total]");
            if (output) output.textContent = `Rp ${Math.round(total).toLocaleString("id-ID")}`;
        };

        form.querySelectorAll("[data-estimate-toggle]").forEach(toggle => {
            syncRow(toggle);
            toggle.addEventListener("change", () => {
                syncRow(toggle);
                calculate();
            });
        });
        form.addEventListener("input", calculate);
        form.addEventListener("change", calculate);
        form.querySelector("[data-estimate-select-all]")?.addEventListener("click", event => {
            const toggles = [...form.querySelectorAll("input[name='material_id[]']")];
            const shouldSelect = toggles.some(toggle => !toggle.checked);
            toggles.forEach(toggle => {
                toggle.checked = shouldSelect;
                syncRow(toggle);
            });
            event.currentTarget.textContent = shouldSelect ? "Batalkan semua" : "Pilih semua";
            calculate();
        });
        calculate();
    });
}

document.addEventListener("submit", event => {
    const form = event.target.closest("[data-api-form]");
    if (!form) return;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        return;
    }
    event.preventDefault();
    postForm(form, event.submitter);
});

document.addEventListener("click", event => {
    const tableDownload = event.target.closest("[data-table-download]");
    if (tableDownload) {
        event.preventDefault();
        downloadTableCsv(tableDownload);
        return;
    }

    const button = event.target.closest("[data-action-button]");
    if (!button) return;
    const action = button.dataset.action;
    const csrf = document.querySelector('input[name="csrf_token"]')?.value
        || document.querySelector("meta[name='csrf-token']")?.content || "";
    const body = new FormData();
    body.set("action", action);
    body.set("csrf_token", csrf);

    setButtonLoading(button, true);
    fetch(API_ENDPOINT, {
        method: "POST",
        body,
        credentials: "same-origin",
        headers: { "X-Requested-With": "fetch" }
    })
        .then(async response => {
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "Aksi gagal.");
            showToast(result.message || "Aksi berhasil.");
            window.setTimeout(() => window.location.reload(), 700);
        })
        .catch(error => {
            showToast(error.message, "danger");
            setButtonLoading(button, false);
        });
});

document.addEventListener("DOMContentLoaded", () => {
    initStarRating();
    initFlashDismiss();
    initSidebarActive();
    initFilePreview();
    initEstimateForms();
});
