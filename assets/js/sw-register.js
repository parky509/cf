/**
 * Service Worker Registration & PWA Install Prompt
 */
(function() {
    'use strict';
    
    // Store the install prompt event
    let deferredPrompt = null;
    let installBanner = null;
    
    // Check if app is already installed
    function isAppInstalled() {
        // Check if running as standalone app
        if (window.matchMedia('(display-mode: standalone)').matches) {
            return true;
        }
        // Check iOS Safari standalone mode
        if (window.navigator.standalone === true) {
            return true;
        }
        // Check localStorage for dismissed/installed flag
        if (localStorage.getItem('cfi_pwa_installed') === 'true') {
            return true;
        }
        return false;
    }
    
    // Check if user has dismissed the prompt recently (within 7 days)
    function hasRecentlyDismissed() {
        const dismissedTime = localStorage.getItem('cfi_pwa_dismissed');
        if (!dismissedTime) return false;
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        return (Date.now() - parseInt(dismissedTime)) < sevenDays;
    }
    
    // Create install banner
    function createInstallBanner() {
        if (installBanner) return;
        
        installBanner = document.createElement('div');
        installBanner.id = 'cfi-pwa-install-banner';
        installBanner.innerHTML = `
            <style>
                #cfi-pwa-install-banner {
                    position: fixed;
                    bottom: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: linear-gradient(135deg, #001943 0%, #003366 100%);
                    color: white;
                    padding: 12px 20px;
                    border-radius: 50px;
                    box-shadow: 0 8px 32px rgba(0,25,67,0.4);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    z-index: 99999;
                    font-family: 'Inter', sans-serif;
                    animation: slideUp 0.4s ease-out;
                    max-width: 90%;
                    backdrop-filter: blur(10px);
                }
                #cfi-pwa-install-banner.hiding {
                    animation: slideDown 0.3s ease-in forwards;
                }
                @keyframes slideUp {
                    from { transform: translateX(-50%) translateY(100px); opacity: 0; }
                    to { transform: translateX(-50%) translateY(0); opacity: 1; }
                }
                @keyframes slideDown {
                    from { transform: translateX(-50%) translateY(0); opacity: 1; }
                    to { transform: translateX(-50%) translateY(100px); opacity: 0; }
                }
                #cfi-pwa-install-banner .pwa-icon {
                    width: 36px;
                    height: 36px;
                    background: linear-gradient(135deg, #4CAF50, #45a049);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                #cfi-pwa-install-banner .pwa-icon svg {
                    width: 20px;
                    height: 20px;
                    fill: white;
                }
                #cfi-pwa-install-banner .pwa-text {
                    flex: 1;
                    font-size: 13px;
                    line-height: 1.3;
                }
                #cfi-pwa-install-banner .pwa-text strong {
                    display: block;
                    font-size: 14px;
                    margin-bottom: 2px;
                }
                #cfi-pwa-install-banner .pwa-buttons {
                    display: flex;
                    gap: 8px;
                    flex-shrink: 0;
                }
                #cfi-pwa-install-banner button {
                    border: none;
                    cursor: pointer;
                    font-family: inherit;
                    font-size: 12px;
                    font-weight: 600;
                    padding: 8px 16px;
                    border-radius: 20px;
                    transition: all 0.2s;
                }
                #cfi-pwa-install-banner .pwa-install-btn {
                    background: #4CAF50;
                    color: white;
                }
                #cfi-pwa-install-banner .pwa-install-btn:hover {
                    background: #45a049;
                    transform: scale(1.05);
                }
                #cfi-pwa-install-banner .pwa-dismiss-btn {
                    background: rgba(255,255,255,0.15);
                    color: white;
                }
                #cfi-pwa-install-banner .pwa-dismiss-btn:hover {
                    background: rgba(255,255,255,0.25);
                }
                @media (max-width: 480px) {
                    #cfi-pwa-install-banner {
                        flex-wrap: wrap;
                        padding: 12px 16px;
                        gap: 10px;
                        bottom: 15px;
                        border-radius: 20px;
                    }
                    #cfi-pwa-install-banner .pwa-text {
                        flex: 1 1 calc(100% - 50px);
                        font-size: 12px;
                    }
                    #cfi-pwa-install-banner .pwa-buttons {
                        flex: 1 1 100%;
                        justify-content: center;
                    }
                }
            </style>
            <div class="pwa-icon">
                <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            </div>
            <div class="pwa-text">
                <strong>Install App</strong>
                <span>Add to home screen for faster access</span>
            </div>
            <div class="pwa-buttons">
                <button class="pwa-install-btn" id="cfi-pwa-install">Install</button>
                <button class="pwa-dismiss-btn" id="cfi-pwa-dismiss">Later</button>
            </div>
        `;
        
        document.body.appendChild(installBanner);
        
        // Add event listeners
        document.getElementById('cfi-pwa-install').addEventListener('click', handleInstall);
        document.getElementById('cfi-pwa-dismiss').addEventListener('click', handleDismiss);
    }
    
    // Handle install button click
    async function handleInstall() {
        if (!deferredPrompt) return;
        
        // Show the browser's install prompt
        deferredPrompt.prompt();
        
        // Wait for user response
        const { outcome } = await deferredPrompt.userChoice;
        
        if (outcome === 'accepted') {
            localStorage.setItem('cfi_pwa_installed', 'true');
        }
        
        // Clear the deferred prompt
        deferredPrompt = null;
        
        // Hide the banner
        hideBanner();
    }
    
    // Handle dismiss button click
    function handleDismiss() {
        localStorage.setItem('cfi_pwa_dismissed', Date.now().toString());
        hideBanner();
    }
    
    // Hide banner with animation
    function hideBanner() {
        if (installBanner) {
            installBanner.classList.add('hiding');
            setTimeout(() => {
                if (installBanner && installBanner.parentNode) {
                    installBanner.parentNode.removeChild(installBanner);
                }
                installBanner = null;
            }, 300);
        }
    }
    
    // Listen for beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', function(e) {
        // Prevent Chrome's mini-infobar
        e.preventDefault();
        
        // Store the event for later
        deferredPrompt = e;
        
        // Check if we should show the banner
        if (!isAppInstalled() && !hasRecentlyDismissed()) {
            // Delay showing banner to not interrupt initial page load
            setTimeout(createInstallBanner, 3000);
        }
    });
    
    // Listen for app installed event
    window.addEventListener('appinstalled', function() {
        localStorage.setItem('cfi_pwa_installed', 'true');
        hideBanner();
        deferredPrompt = null;
    });
    
    // Check display mode changes (for detecting when app becomes standalone)
    window.matchMedia('(display-mode: standalone)').addEventListener('change', function(e) {
        if (e.matches) {
            localStorage.setItem('cfi_pwa_installed', 'true');
            hideBanner();
        }
    });
    
    // Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register(cfiData.pluginUrl + 'assets/js/sw.js')
                .then(function(registration) {
                    console.log('CFI ServiceWorker registered:', registration.scope);
                    
                    // Check for updates
                    registration.addEventListener('updatefound', function() {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New service worker available
                                console.log('CFI: New version available');
                            }
                        });
                    });
                })
                .catch(function(error) {
                    console.log('CFI ServiceWorker registration failed:', error);
                });
        });
    }
})();
