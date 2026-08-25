<div x-data="sessionWatcher()" x-init="init()" x-cloak></div>

<script>
    function sessionWatcher() {
        return {
            pollTimer: null,

            init() {
                this.pollTimer = setInterval(async () => {
                    try {
                        const res = await fetch('{{ route("session.heartbeat") }}', {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (res.status === 401) {
                            clearInterval(this.pollTimer);
                            Swal.fire({
                                title: 'Sesión cerrada',
                                html: '<p style="color:#1F2429;font-size:0.875rem;line-height:1.6;">Tu sesión fue cerrada desde otro dispositivo.</p>',
                                icon: 'info',
                                iconColor: '#B08D57',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#16324F',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                customClass: {
                                    confirmButton: 'swal2-session-cc',
                                    popup: 'swal2-session-popup-cc',
                                },
                            }).then(() => {
                                window.location.href = '{{ route("login") }}';
                            });
                        }
                    } catch (e) {
                        console.error('Session heartbeat error:', e);
                    }
                }, 5000);
            },
        };
    }
</script>

<style>
    .swal2-session-popup-cc {
        font-family: 'Inter', sans-serif;
        border-radius: 0.5rem;
        border: 1px solid #D5E1EB;
    }
    .swal2-session-popup-cc .swal2-title {
        font-family: 'Source Serif 4', serif;
        color: #16324F;
        font-weight: 600;
    }
    .swal2-session-popup-cc .swal2-html-container {
        margin-top: 0.5rem;
    }
    .swal2-session-cc {
        background-color: #16324F !important;
        color: #fff !important;
        font-weight: 500 !important;
        font-size: 0.875rem !important;
        border-radius: 0.375rem !important;
        padding: 0.5rem 1rem !important;
        box-shadow: none !important;
        transition: background-color 0.15s ease !important;
    }
    .swal2-session-cc:hover {
        background-color: #1D3F63 !important;
    }
</style>
