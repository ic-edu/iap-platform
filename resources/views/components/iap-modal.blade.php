<!-- IAP Design System Global Modal Component (GLOBAL-MODAL-001) -->
<div id="iap-global-modal" 
     class="hidden fixed inset-0 z-50 overflow-y-auto" 
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="iap-modal-title" 
     aria-describedby="iap-modal-desc">
    <!-- Backdrop -->
    <div id="iap-modal-backdrop" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity duration-200"></div>

    <!-- Modal Dialog Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4 sm:p-0">
        <div id="iap-modal-panel" 
             class="relative inline-block w-full max-w-md p-6 text-left align-middle transition-all transform bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl z-10 my-8">
            
            <!-- Header Icon & Title -->
            <div class="flex items-start gap-4">
                <div id="iap-modal-icon-badge" class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-indigo-500/10 border border-indigo-500/30 text-indigo-400">
                    <span id="iap-modal-icon">⚡</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="iap-modal-title" class="text-base font-bold text-slate-100 leading-snug">
                        Confirm Action
                    </h3>
                    <p id="iap-modal-desc" class="mt-2 text-xs text-slate-400 leading-relaxed break-words whitespace-pre-line">
                        Are you sure you want to proceed?
                    </p>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-800/80">
                <button id="iap-modal-cancel-btn" 
                        type="button" 
                        class="px-4 py-2 text-xs font-bold text-slate-300 bg-slate-800/80 hover:bg-slate-700 active:bg-slate-800 border border-slate-700 rounded-xl transition-all cursor-pointer">
                    Cancel
                </button>
                <button id="iap-modal-confirm-btn" 
                        type="button" 
                        class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 border border-indigo-500/40 rounded-xl shadow-lg shadow-indigo-950/50 transition-all cursor-pointer inline-flex items-center gap-2">
                    <span id="iap-modal-confirm-text">Confirm</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let pendingForm = null;
    let pendingCallback = null;
    let lastActiveElement = null;

    window.iapConfirm = function(options) {
        const modal = document.getElementById('iap-global-modal');
        if (!modal) return false;

        lastActiveElement = document.activeElement;
        options = options || {};

        const title = options.title || 'Confirm Action';
        const message = options.message || 'Are you sure you want to proceed?';
        const confirmText = options.confirmText || 'Confirm';
        const cancelText = options.cancelText || 'Cancel';
        const variant = options.variant || 'primary';

        document.getElementById('iap-modal-title').textContent = title;
        document.getElementById('iap-modal-desc').textContent = message;

        const confirmBtn = document.getElementById('iap-modal-confirm-btn');
        const cancelBtn = document.getElementById('iap-modal-cancel-btn');
        const confirmTextEl = document.getElementById('iap-modal-confirm-text');
        const iconBadge = document.getElementById('iap-modal-icon-badge');
        const icon = document.getElementById('iap-modal-icon');

        if (confirmTextEl) confirmTextEl.textContent = confirmText;
        if (cancelBtn) {
            cancelBtn.textContent = cancelText;
            cancelBtn.style.display = options.alertOnly ? 'none' : 'inline-flex';
        }

        // Variant styling
        if (confirmBtn && iconBadge && icon) {
            confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white border rounded-xl shadow-lg transition-all cursor-pointer inline-flex items-center gap-2 ';
            iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold border ';

            if (variant === 'danger' || variant === 'destructive') {
                confirmBtn.className += 'bg-rose-600 hover:bg-rose-500 active:bg-rose-700 border-rose-500/40 shadow-rose-950/50';
                iconBadge.className += 'bg-rose-500/10 border-rose-500/30 text-rose-400';
                icon.textContent = '⚠️';
            } else if (variant === 'warning') {
                confirmBtn.className += 'bg-amber-600 hover:bg-amber-500 active:bg-amber-700 border-amber-500/40 shadow-amber-950/50';
                iconBadge.className += 'bg-amber-500/10 border-amber-500/30 text-amber-400';
                icon.textContent = '⚡';
            } else if (variant === 'success') {
                confirmBtn.className += 'bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 border-emerald-500/40 shadow-emerald-950/50';
                iconBadge.className += 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
                icon.textContent = '✅';
            } else {
                confirmBtn.className += 'bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 border-indigo-500/40 shadow-indigo-950/50';
                iconBadge.className += 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400';
                icon.textContent = '📚';
            }
        }

        pendingForm = options.form || null;
        pendingCallback = options.onConfirm || null;

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        setTimeout(() => {
            if (confirmBtn) confirmBtn.focus();
        }, 50);
    };

    window.iapAlert = function(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        options = options || {};
        options.alertOnly = true;
        options.confirmText = options.okText || 'OK';
        window.iapConfirm(options);
    };

    window.closeIapModal = function() {
        const modal = document.getElementById('iap-global-modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        pendingForm = null;
        pendingCallback = null;
        if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
            lastActiveElement.focus();
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('iap-global-modal');
        if (!modal) return;

        const confirmBtn = document.getElementById('iap-modal-confirm-btn');
        const cancelBtn = document.getElementById('iap-modal-cancel-btn');
        const backdrop = document.getElementById('iap-modal-backdrop');

        confirmBtn?.addEventListener('click', function() {
            if (pendingForm) {
                const targetForm = pendingForm;
                closeIapModal();
                targetForm.submit();
            } else if (pendingCallback) {
                const cb = pendingCallback;
                closeIapModal();
                cb();
            } else {
                closeIapModal();
            }
        });

        cancelBtn?.addEventListener('click', closeIapModal);
        backdrop?.addEventListener('click', closeIapModal);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeIapModal();
            }
        });
    });
})();
</script>
