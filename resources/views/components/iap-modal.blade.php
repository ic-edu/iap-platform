<!-- IAP Design System Global Modal Component (GLOBAL-MODAL-001) -->
<div id="iap-global-modal" 
     class="hidden fixed inset-0 z-[999999] flex items-center justify-center p-4 sm:p-6 overflow-y-auto" 
     style="position: fixed; top: 0; right: 0; bottom: 0; left: 0; width: 100vw; height: 100vh; height: 100dvh; z-index: 999999; display: none; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box;"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="iap-modal-title" 
     aria-describedby="iap-modal-desc">
    
    <!-- Full-Viewport Backdrop Overlay -->
    <div id="iap-modal-backdrop" 
         class="fixed inset-0 bg-slate-950/85 backdrop-blur-md transition-opacity duration-200"
         style="position: fixed; top: 0; right: 0; bottom: 0; left: 0; width: 100vw; height: 100vh; height: 100dvh; background-color: rgba(2, 6, 23, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999998;"></div>

    <!-- Centered Floating Modal Panel Card (Vertical Flex Container) -->
    <div id="iap-modal-panel" 
         class="relative w-full max-w-lg text-left bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl z-[1000000] my-auto flex flex-col overflow-hidden transform transition-all"
         style="position: relative; width: 100%; max-width: 32rem; max-height: calc(100vh - 3rem); margin: auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 1rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75); z-index: 1000000; display: flex; flex-direction: column; overflow: hidden; box-sizing: border-box;">
        
        <!-- 1. Scrollable Modal Body Region -->
        <div id="iap-modal-body" class="p-6 sm:p-7 overflow-y-auto flex-1" style="padding: 1.5rem; overflow-y: auto; flex: 1 1 auto; min-height: 0;">
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
        </div>

        <!-- 2. Non-Shrinking Sticky Action Footer (Always Visible at Card Base) -->
        <div id="iap-modal-actions" 
             class="px-6 py-4 bg-slate-900/95 border-t border-slate-800/80 flex items-center justify-end gap-3 flex-shrink-0"
             style="padding: 1rem 1.5rem; background-color: rgba(15, 23, 42, 0.95); border-top: 1px solid rgba(30, 41, 59, 0.8); display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; flex-shrink: 0;">
            <button id="iap-modal-cancel-btn" 
                    type="button" 
                    class="px-4 py-2 text-xs font-bold text-slate-300 bg-slate-800/80 hover:bg-slate-700 active:bg-slate-800 border border-slate-700 rounded-xl transition-all cursor-pointer"
                    style="display: inline-flex; align-items: center; justify-content: center; min-height: 2.25rem; min-width: 5rem; padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 700; color: #cbd5e1; background-color: rgba(30, 41, 59, 0.8); border: 1px solid #334155; border-radius: 0.75rem; cursor: pointer; opacity: 1; visibility: visible;">
                Cancel
            </button>
            <button id="iap-modal-confirm-btn" 
                    type="button" 
                    class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 border border-indigo-500/40 rounded-xl shadow-lg shadow-indigo-950/50 transition-all cursor-pointer inline-flex items-center gap-2"
                    style="display: inline-flex; align-items: center; justify-content: center; min-height: 2.25rem; min-width: 5rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; font-weight: 700; color: #ffffff; background-color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 0.75rem; cursor: pointer; gap: 0.5rem; opacity: 1; visibility: visible;">
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
    let originalBodyOverflow = '';

    function ensureDocumentBodyPlacement(modal) {
        if (modal && document.body && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
    }

    window.iapConfirm = function(options) {
        const modal = document.getElementById('iap-global-modal');
        if (!modal) return false;

        ensureDocumentBodyPlacement(modal);

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
            cancelBtn.style.visibility = 'visible';
        }
        if (confirmBtn) {
            confirmBtn.style.display = 'inline-flex';
            confirmBtn.style.visibility = 'visible';
            confirmBtn.style.color = '#ffffff';
        }

        // Variant styling
        if (confirmBtn && iconBadge && icon) {
            if (variant === 'danger' || variant === 'destructive') {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 active:bg-rose-700 border border-rose-500/40 rounded-xl shadow-lg shadow-rose-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#e11d48';
                confirmBtn.style.borderColor = 'rgba(244, 63, 94, 0.4)';
                confirmBtn.style.display = 'inline-flex';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-rose-500/10 border border-rose-500/30 text-rose-400';
                iconBadge.style.backgroundColor = 'rgba(244, 63, 94, 0.1)';
                iconBadge.style.borderColor = 'rgba(244, 63, 94, 0.3)';
                iconBadge.style.color = '#fb7185';
                icon.textContent = '⚠️';
            } else if (variant === 'warning') {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 border border-amber-500/40 rounded-xl shadow-lg shadow-amber-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#d97706';
                confirmBtn.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                confirmBtn.style.display = 'inline-flex';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400';
                iconBadge.style.backgroundColor = 'rgba(245, 158, 11, 0.1)';
                iconBadge.style.borderColor = 'rgba(245, 158, 11, 0.3)';
                iconBadge.style.color = '#fbbf24';
                icon.textContent = '⚡';
            } else if (variant === 'success') {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 border border-emerald-500/40 rounded-xl shadow-lg shadow-emerald-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#059669';
                confirmBtn.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                confirmBtn.style.display = 'inline-flex';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-400';
                iconBadge.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                iconBadge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                iconBadge.style.color = '#34d399';
                icon.textContent = '✅';
            } else {
                confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 border border-indigo-500/40 rounded-xl shadow-lg shadow-indigo-950/50 transition-all cursor-pointer inline-flex items-center gap-2';
                confirmBtn.style.backgroundColor = '#4f46e5';
                confirmBtn.style.borderColor = 'rgba(99, 102, 241, 0.4)';
                confirmBtn.style.display = 'inline-flex';
                iconBadge.className = 'flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-xl font-bold bg-indigo-500/10 border border-indigo-500/30 text-indigo-400';
                iconBadge.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
                iconBadge.style.borderColor = 'rgba(99, 102, 241, 0.3)';
                iconBadge.style.color = '#818cf8';
                icon.textContent = '📚';
            }
        }

        pendingForm = options.form || null;
        pendingCallback = options.onConfirm || null;

        originalBodyOverflow = document.body.style.overflow;
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
            document.body.style.overflow = originalBodyOverflow || '';
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

        ensureDocumentBodyPlacement(modal);

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
