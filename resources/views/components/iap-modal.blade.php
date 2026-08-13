<!-- IAP Design System Global Modal Component (GLOBAL-MODAL-001) -->
<div id="iap-global-modal" 
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto" 
     style="position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 99999; display: none; align-items: center; justify-content: center; padding: 1rem;"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="iap-modal-title" 
     aria-describedby="iap-modal-desc">
    
    <!-- Backdrop Overlay -->
    <div id="iap-modal-backdrop" 
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity duration-200"
         style="position: fixed; top: 0; right: 0; bottom: 0; left: 0; background-color: rgba(2, 6, 23, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99999;"></div>

    <!-- Centered Modal Panel -->
    <div id="iap-modal-panel" 
         class="relative w-full max-w-lg p-6 sm:p-7 text-left bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl z-10 my-auto transform transition-all"
         style="position: relative; width: 100%; max-width: 32rem; margin: auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75); z-index: 100000; padding: 1.5rem;">
        
        <!-- Header Icon & Title -->
        <div class="flex items-start gap-4" style="display: flex; align-items: flex-start; gap: 1rem;">
            <div id="iap-modal-icon-badge" 
                 class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-indigo-500/10 border border-indigo-500/30 text-indigo-400"
                 style="flex-shrink: 0; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700;">
                <span id="iap-modal-icon">⚡</span>
            </div>
            <div class="flex-1 min-w-0" style="flex: 1; min-width: 0;">
                <h3 id="iap-modal-title" class="text-base font-bold text-slate-100 leading-snug" style="margin: 0; font-size: 1rem; font-weight: 700; color: #f8fafc; line-height: 1.375;">
                    Confirm Action
                </h3>
                <p id="iap-modal-desc" class="mt-2 text-xs text-slate-400 leading-relaxed break-words whitespace-pre-line" style="margin-top: 0.5rem; margin-bottom: 0; font-size: 0.75rem; color: #94a3b8; line-height: 1.6; word-break: break-word;">
                    Are you sure you want to proceed?
                </p>
            </div>
        </div>

        <!-- Modal Action Buttons -->
        <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-800/80" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(30, 41, 59, 0.8); display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;">
            <button id="iap-modal-cancel-btn" 
                    type="button" 
                    class="px-4 py-2 text-xs font-bold text-slate-300 bg-slate-800/80 hover:bg-slate-700 active:bg-slate-800 border border-slate-700 rounded-xl transition-all cursor-pointer"
                    style="padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 700; color: #cbd5e1; background-color: rgba(30, 41, 59, 0.8); border: 1px solid #334155; border-radius: 0.75rem; cursor: pointer;">
                Cancel
            </button>
            <button id="iap-modal-confirm-btn" 
                    type="button" 
                    class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 border border-indigo-500/40 rounded-xl shadow-lg shadow-indigo-950/50 transition-all cursor-pointer inline-flex items-center gap-2"
                    style="padding: 0.5rem 1.25rem; font-size: 0.75rem; font-weight: 700; color: #ffffff; background-color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 0.75rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                <span id="iap-modal-confirm-text">Confirm</span>
            </button>
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

        const titleEl = document.getElementById('iap-modal-title');
        const descEl = document.getElementById('iap-modal-desc');
        if (titleEl) titleEl.textContent = title;
        if (descEl) descEl.textContent = message;

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
            if (variant === 'danger' || variant === 'destructive') {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 active:bg-rose-700 border border-rose-500/40 rounded-xl shadow-lg shadow-rose-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#e11d48';
                confirmBtn.style.borderColor = 'rgba(244, 63, 94, 0.4)';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-rose-500/10 border border-rose-500/30 text-rose-400';
                iconBadge.style.backgroundColor = 'rgba(244, 63, 94, 0.1)';
                iconBadge.style.borderColor = 'rgba(244, 63, 94, 0.3)';
                iconBadge.style.color = '#fb7185';
                icon.textContent = '⚠️';
            } else if (variant === 'warning') {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 border border-amber-500/40 rounded-xl shadow-lg shadow-amber-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#d97706';
                confirmBtn.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400';
                iconBadge.style.backgroundColor = 'rgba(245, 158, 11, 0.1)';
                iconBadge.style.borderColor = 'rgba(245, 158, 11, 0.3)';
                iconBadge.style.color = '#fbbf24';
                icon.textContent = '⚡';
            } else if (variant === 'success') {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 border border-emerald-500/40 rounded-xl shadow-lg shadow-emerald-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#059669';
                confirmBtn.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-400';
                iconBadge.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                iconBadge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                iconBadge.style.color = '#34d399';
                icon.textContent = '✅';
            } else {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 border border-indigo-500/40 rounded-xl shadow-lg shadow-indigo-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#4f46e5';
                confirmBtn.style.borderColor = 'rgba(99, 102, 241, 0.4)';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-indigo-500/10 border border-indigo-500/30 text-indigo-400';
                iconBadge.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
                iconBadge.style.borderColor = 'rgba(99, 102, 241, 0.3)';
                iconBadge.style.color = '#818cf8';
                icon.textContent = '📚';
            }
        }

        pendingForm = options.form || null;
        pendingCallback = options.onConfirm || null;

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
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
            modal.style.display = 'none';
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
            if (e.key === 'Escape' && modal.style.display !== 'none' && !modal.classList.contains('hidden')) {
                closeIapModal();
            }
        });
    });
})();
</script>
