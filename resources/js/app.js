import Alpine from 'alpinejs';

window.Alpine = Alpine;

const certicheckSwalDefaults = {
    confirmButtonColor: '#16324F',
    cancelButtonColor: '#6B7280',
    fontFamily: 'Inter, sans-serif',
    customClass: {
        confirmButton: 'swal2-confirm-brass',
        cancelButton: 'swal2-cancel-ink',
    },
};

window.swalConfirm = (options) => {
    return Swal.fire({
        ...certicheckSwalDefaults,
        reverseButtons: true,
        showCancelButton: true,
        ...options,
    });
};

window.swalSuccess = (options) => {
    return Swal.fire({
        ...certicheckSwalDefaults,
        icon: 'success',
        confirmButtonColor: '#16324F',
        ...options,
    });
};

const certicheckSiteLabels = {
    rnmc: 'Medidas Correctivas (RNMC)',
    judicial_police: 'Antecedentes Judiciales',
    comptroller: 'Antecedentes Fiscales',
    attorney_general: 'Antecedentes Disciplinarios',
};

window.certicheckSiteLabel = (site) => certicheckSiteLabels[site] ?? site;

function certicheckEscapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function certicheckFormatDate(value) {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? 'fecha desconocida' : parsed.toLocaleDateString('es-CO');
}

/**
 * Intercepta una respuesta 409 del backend (sin espacio de almacenamiento) y
 * muestra un SweetAlert explicando exactamente qué PDFs se eliminarán.
 * Devolverá true si el usuario confirmó, false si eligió gestionar él mismo
 * (en cuyo caso ya navega a storage.index).
 */
window.certicheckStorageConflict = async (response) => {
    const data = await response.json();

    if (!data.needs_confirmation || !Array.isArray(data.to_delete)) {
        throw new Error('Respuesta inesperada del servidor');
    }

    const items = data.to_delete;
    const noun = items.length === 1
        ? 'el siguiente PDF'
        : `los siguientes ${items.length} PDFs`;

    const listHtml = items
        .map((item) => {
            const name = certicheckEscapeHtml(item.subject_name) || '—';
            const doc = certicheckEscapeHtml(item.document_number);
            const site = certicheckSiteLabel(item.site);
            const date = certicheckFormatDate(item.pdf_generated_at);

            return `<li>${name} · ${doc} · ${site} · ${date}</li>`;
        })
        .join('');

    return swalConfirm({
        title: 'No tienes espacio disponible',
        html: `Para continuar, se eliminarán ${noun} (los más antiguos de tu almacenamiento) y no se podrá deshacer la acción:<ul class="text-left mt-2 space-y-1">${listHtml}</ul>`,
        icon: 'warning',
        confirmButtonText: 'Eliminar y continuar',
        cancelButtonText: 'Cancelar, quiero gestionar yo mismo',
        confirmButtonColor: '#B54B3F',
        allowOutsideClick: false,
        allowEscapeKey: false,
    });
};

const certicheckManageModal = (config = {}) => ({
    open: false,
    needed: Number(config.needed ?? 1),
    view: 'grouped',
    dir: 'desc',
    currentPageUrl: null,
    items: [],
    used: 0,
    limit: 0,
    selectedIds: [],
    loading: false,
    nextPageUrl: null,
    prevPageUrl: null,
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    dataUrl: config.dataset?.url ?? config.dataUrl ?? '/storage/data',
    deleting: false,

    init() {
        this.fetchData(this.buildUrl());
    },

    buildUrl() {
        const params = new URLSearchParams({ view: this.view, sort: 'date', dir: this.dir });
        return `${this.dataUrl}?${params.toString()}`;
    },

    show(detail = {}) {
        this.needed = Number(detail.needed ?? 1);
        this.selectedIds = [];
        this.deleting = false;
        this.view = 'grouped';
        this.dir = 'desc';
        this.open = true;
        this.fetchData(this.buildUrl());
    },

    close() {
        this.open = false;
    },

    async fetchData(url = null) {
        this.loading = true;
        try {
            const res = await fetch(url ?? this.buildUrl(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) return;
            const payload = await res.json();
            this.items = payload.data;
            this.used = payload.used;
            this.limit = payload.limit;
            this.currentPageUrl = url ?? this.buildUrl();
            this.nextPageUrl = payload.next_page_url;
            this.prevPageUrl = payload.prev_page_url;
        } catch (e) {
            console.error('Error cargando almacenamiento:', e);
        } finally {
            this.loading = false;
        }
    },

    setView(view) {
        if (this.view === view) return;
        this.view = view;
        this.selectedIds = [];
        this.fetchData(this.buildUrl());
    },

    toggleDir() {
        this.dir = this.dir === 'asc' ? 'desc' : 'asc';
        this.fetchData(this.buildUrl());
    },

    async deleteConsultation(id) {
        const ok = await certicheckConfirmFree('Se borrarán todos los PDFs de esta consulta del almacenamiento. El registro se conservará y podrá regenerarse.');
        if (!ok) return;
        await this.runDelete(`/storage/consultations/${id}`);
    },

    async deleteCertificate(id) {
        const ok = await certicheckConfirmFree('Se borrará este PDF del almacenamiento. El registro se conservará y podrá regenerarse.');
        if (!ok) return;
        await this.runDelete(`/storage/certificates/${id}`);
    },

    async deleteBulk() {
        if (this.selectedIds.length === 0 || this.deleting) return;
        const ok = await certicheckConfirmFree(`Se liberarán ${this.selectedIds.length} PDF(s) seleccionado(s). Los registros se conservarán y podrán regenerarse.`);
        if (!ok) return;

        const params = new URLSearchParams();
        this.selectedIds.forEach((id) => params.append('ids[]', id));

        await this.runDelete('/storage/certificates', params);
    },

    async runDelete(path, body = null) {
        if (this.deleting) return;
        this.deleting = true;

        const params = new URLSearchParams({ needed: this.needed });
        const init = {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken,
            },
        };

        if (body) {
            init.body = body.toString();
            init.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        }

        try {
            const res = await fetch(`${path}?${params.toString()}`, init);
            if (!res.ok) {
                console.error('Error al liberar almacenamiento:', res.status);
                return;
            }

            const payload = await res.json();
            this.used = payload.used;
            this.limit = payload.limit;

            if (payload.has_space === true) {
                this.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Espacio disponible',
                    text: 'Ya puedes continuar con la generación.',
                    timer: 1800,
                    timerProgressBar: true,
                    showConfirmButton: false,
                });
                return;
            }

            this.selectedIds = [];
            await this.fetchData(this.currentPageUrl);
        } catch (e) {
            console.error('Error al liberar almacenamiento:', e);
        } finally {
            this.deleting = false;
        }
    },

    toggleSelected(id) {
        const index = this.selectedIds.indexOf(id);
        if (index === -1) {
            this.selectedIds.push(id);
        } else {
            this.selectedIds.splice(index, 1);
        }
    },

    isSelected(id) {
        return this.selectedIds.includes(id);
    },

    toggleAll(checked) {
        this.selectedIds = checked ? this.items.map((item) => item.id) : [];
    },

    isAllSelected() {
        return this.items.length > 0 && this.selectedIds.length === this.items.length;
    },

    siteLabel(site) {
        return certicheckSiteLabel(site);
    },

    formatDate(value) {
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) return '—';
        return parsed.toLocaleString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    },
});

document.addEventListener('alpine:init', () => {
    Alpine.data('manageStorageModal', (el) => certicheckManageModal(el));
});

Alpine.start();

function certicheckConfirmFree(text) {
    return swalConfirm({
        title: 'Liberar almacenamiento',
        text,
        icon: 'warning',
        confirmButtonText: 'Sí, liberar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#B54B3F',
    }).then((result) => result.isConfirmed);
}
