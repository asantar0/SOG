/*!
 * Plugin Name: Secure Outbound Gateway (SOG)
 * Description: JS logic for external link and exceptions.
 * Author: Agustin S
 * Version: 1.0.2
 */

document.addEventListener("DOMContentLoaded", function () {
    const currentHost = window.location.host;

    fetch(`${sog_ajax.plugin_url}exceptions.json`)
        .then(response => {
            if (!response.ok) throw new Error("Failed to load exceptions.json");
            return response.json();
        })
        .then(exceptions => {
            initModal(exceptions);
        })
        .catch(error => {
            console.error("Error loading exceptions list:", error);
            initModal([]); 
        });

    function initModal(exceptions) {
        const modal = document.createElement("div");
        modal.innerHTML = `
          <div class="sog-modal-overlay" id="sogModal">
            <div class="sog-modal-box">
              <h2>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10
                           10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                ${sog_i18n.modal_title}
              </h2>
              <p>
                ${sog_i18n.modal_line_1.replace('%s', sog_i18n.site_domain)}
              </p>
              <p>
                ${sog_i18n.modal_line_2.replace('%s', sog_i18n.site_name)}
              </p>
              <p id="sog-link-display" class="sog-url"></p>
              <div class="sog-buttons">
                <button id="sog-cancel" aria-label="${sog_i18n.cancel_aria}">${sog_i18n.cancel_label}</button>
                <button id="sog-continue" aria-label="${sog_i18n.continue_aria}">${sog_i18n.continue_label}</button>
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(modal);

        const sogModal = document.getElementById("sogModal");
        const sogLinkDisplay = document.getElementById("sog-link-display");
        const sogCancel = document.getElementById("sog-cancel");
        const sogContinue = document.getElementById("sog-continue");

        let targetUrl = null;

        // Logging AJAX
        function logClick(url, actionType = 'click') {
            const data = new URLSearchParams();
            data.append('action', 'sog_log_click');
            data.append('url', url);
            data.append('action_type', actionType);
            data.append('nonce', sog_ajax.nonce);

            fetch(sog_ajax.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data.toString()
            });
        }

        // Cache for websites
        function getAcceptedDomains() {
            const raw = localStorage.getItem("sog_accepted_domains");
            return raw ? JSON.parse(raw) : [];
        }

        function addAcceptedDomain(domain) {
            const accepted = getAcceptedDomains();
            if (!accepted.includes(domain)) {
                accepted.push(domain);
                localStorage.setItem("sog_accepted_domains", JSON.stringify(accepted));
            }
        }

        document.querySelectorAll("a[href^='http']").forEach(link => {
            const url = new URL(link.href);

            if (url.host !== currentHost) {
                link.addEventListener("click", function (e) {
                    e.preventDefault();
                    const linkHost = url.host;

                    const accepted = getAcceptedDomains();
                    if (accepted.includes(linkHost)) {
                        logClick(link.href, 'continue (stored)');
                        window.location.href = link.href;
                        return;
                    }

                    const isException = exceptions.some(exc =>
                        exc.startsWith("http") ? link.href.startsWith(exc) : linkHost === exc
                    );

                    if (isException) {
                        window.location.href = link.href;
                        return;
                    }

                    targetUrl = link.href;
                    sogLinkDisplay.textContent = targetUrl;
                    sogModal.style.display = "flex";
                });
            }
        });

        sogCancel.addEventListener("click", () => {
            logClick(targetUrl, 'cancel');
            sogModal.style.display = "none";
            targetUrl = null;
        });

        sogContinue.addEventListener("click", () => {
            if (targetUrl) {
                const targetDomain = new URL(targetUrl).host;
                addAcceptedDomain(targetDomain);
                logClick(targetUrl, 'continue');
                window.location.href = targetUrl;
            }
        });
    }
});
