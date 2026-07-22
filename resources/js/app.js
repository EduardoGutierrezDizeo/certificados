import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
