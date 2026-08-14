/**
 * DVE Work Hub - IndexedDB Driver & Auto-Sync Engine
 * Lightweight, robust offline-first synchronization for daily internship reports.
 */

const DveDB = (() => {
    const DB_NAME = 'DveOfflineDB';
    const DB_VERSION = 1;
    const STORE_NAME = 'reports';

    function openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                }
            };

            request.onsuccess = (event) => {
                resolve(event.target.result);
            };

            request.onerror = (event) => {
                console.error('[IndexedDB] Open error:', event.target.error);
                reject(event.target.error);
            };
        });
    }

    return {
        saveReport: async (report) => {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.add(report);

                request.onsuccess = () => {
                    console.log('[IndexedDB] Report saved offline successfully');
                    resolve(true);
                };
                request.onerror = (e) => reject(e.target.error);
            });
        },

        getReports: async () => {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result || []);
                request.onerror = (e) => reject(e.target.error);
            });
        },

        deleteReport: async (id) => {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.delete(id);

                request.onsuccess = () => resolve(true);
                request.onerror = (e) => reject(e.target.error);
            });
        },

        clearReports: async () => {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.clear();

                request.onsuccess = () => resolve(true);
                request.onerror = (e) => reject(e.target.error);
            });
        },

        syncReports: async (showNotification = true) => {
            if (!navigator.onLine) return;

            try {
                const reports = await DveDB.getReports();
                if (reports.length === 0) return;

                console.log(`[DVE Sync] Found ${reports.length} pending offline reports. Starting silent sync...`);
                
                if (showNotification) {
                    DveDB.showToast('📶 ตรวจพบรายงานออฟไลน์...', 'กำลังอัปโหลดรายงานที่สะสมอยู่ในเครื่องขึ้นระบบหลักในเบื้องหลัง', 'info');
                }

                // Determine sync script endpoint path based on location
                let syncUrl = 'api_sync_offline.php';
                if (window.location.pathname.includes('/roles/')) {
                    syncUrl = '../student/api_sync_offline.php';
                }

                const response = await fetch(syncUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reports })
                });

                const data = await response.json();
                if (data.success && data.synced_count > 0) {
                    await DveDB.clearReports();
                    console.log(`[DVE Sync] Successfully synchronized ${data.synced_count} reports.`);
                    
                    if (showNotification) {
                        DveDB.showToast('✨ ซิงก์ข้อมูลสำเร็จ!', `อัปโหลดรายงานประจำวันออฟไลน์จำนวน ${data.synced_count} รายการเรียบร้อยแล้ว`, 'success');
                    }
                    
                    // Dispatch sync complete event
                    window.dispatchEvent(new CustomEvent('dve-sync-completed', { detail: data }));
                    
                    // If on dashboard, reload page after a brief delay to refresh report counter and weekly statistics
                    if (window.location.pathname.includes('student.php')) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2500);
                    }
                } else if (data.success && data.synced_count === 0) {
                    // No new items synced (could be duplicates)
                    await DveDB.clearReports();
                } else {
                    console.error('[DVE Sync] Synchronization error:', data.errors);
                }
            } catch (err) {
                console.error('[DVE Sync] Network sync connection failed:', err);
            }
        },

        showToast: (title, message, type = 'info') => {
            // Avoid duplicate notifications
            const oldToast = document.getElementById('dve-offline-toast');
            if (oldToast) oldToast.remove();

            let container = document.getElementById('dve-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'dve-toast-container';
                container.style.position = 'fixed';
                container.style.bottom = '2rem';
                container.style.left = '2rem';
                container.style.zIndex = '9999';
                container.style.maxWidth = '380px';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.id = 'dve-offline-toast';
            
            let bg, border, icon;
            if (type === 'success') {
                bg = 'rgba(255, 255, 255, 0.96)';
                border = '2px solid #059669'; // var(--success)
                icon = '<i class="bi bi-check-circle-fill text-success fs-4"></i>';
            } else if (type === 'warning') {
                bg = 'rgba(255, 255, 255, 0.96)';
                border = '2px solid #d97706'; // var(--warning)
                icon = '<i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>';
            } else {
                bg = 'rgba(255, 255, 255, 0.96)';
                border = '2px solid #6366f1'; // var(--primary)
                icon = '<i class="bi bi-wifi-off text-primary fs-4"></i>';
            }

            toast.innerHTML = `
                <div style="background: ${bg}; border: ${border}; border-radius: 1.25rem; box-shadow: 0 15px 30px rgba(0,0,0,0.08); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding: 1.15rem; display: flex; align-items: start; gap: 0.85rem; transform: translateY(50px); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); font-family: 'Sarabun', sans-serif;">
                    <div style="flex-shrink: 0; margin-top: 2px;">
                        ${icon}
                    </div>
                    <div style="flex-grow: 1;">
                        <h6 style="margin: 0 0 0.15rem 0; font-weight: 800; color: #0f172a; font-size: 0.9rem;">${title}</h6>
                        <p style="margin: 0; color: #475569; font-size: 0.78rem; line-height: 1.4;">${message}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="border: none; background: transparent; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 0; line-height: 1; flex-shrink: 0; margin-left: 0.25rem;">&times;</button>
                </div>
            `;

            container.appendChild(toast);

            // Trigger reflow & slide-in animation
            setTimeout(() => {
                const toastInner = toast.firstElementChild;
                if (toastInner) {
                    toastInner.style.transform = 'translateY(0)';
                    toastInner.style.opacity = '1';
                }
            }, 100);

            // Auto dismiss toast
            setTimeout(() => {
                const toastInner = toast.firstElementChild;
                if (toastInner) {
                    toastInner.style.transform = 'translateY(20px)';
                    toastInner.style.opacity = '0';
                    setTimeout(() => toast.remove(), 400);
                }
            }, 6000);
        }
    };
})();

// Listen for connection recovery to trigger automatic background sync
window.addEventListener('online', () => {
    DveDB.syncReports(true);
});
